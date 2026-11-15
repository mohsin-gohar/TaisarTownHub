<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;

/** Approval is checked from live rows, not from the token's issue-time state. */
class WorkspaceApprovalTest extends EnterpriseTestCase
{
    private const PENDING_ACCESS = [
        'allowed' => false,
        'status' => 'pending',
        'message' => 'Your agency is pending platform-owner approval.',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');

        // Isolated fixture only: registration expects this existing system role.
        Role::create([
            'id' => 'role_agency_owner',
            'scope' => 'agency',
            'key' => 'agency_owner',
            'name' => 'Agency Owner',
            'is_system' => true,
            'permissions' => $this->ownerPermissions(),
        ]);
    }

    private function ownerPermissions(): array
    {
        return [
            'agency.view', 'view_properties', 'properties.view', 'properties.create',
            'documents.view', 'documents.upload', 'documents.download',
            'upload_media', 'manage_media',
        ];
    }

    public static function registrationRoutes(): array
    {
        return [
            'agency registration' => ['/api/agency/register', false],
            'legacy registration' => ['/api/auth/register', true],
        ];
    }

    private function registrationData(bool $legacy = false): array
    {
        return array_merge([
            'email' => 'new-owner@example.test',
            'password' => 'Secret123!',
            'phone' => '03001234567',
        ], $legacy ? [
            'fullName' => 'New Owner',
            'agencyOrBranch' => 'New Agency',
            'cnic' => '42101-1234567-1',
        ] : [
            'name' => 'New Agency',
            'contactName' => 'New Owner',
        ]);
    }

    #[DataProvider('registrationRoutes')]
    public function test_registration_creates_pending_agency_and_owner_with_no_workspace_access(string $route, bool $legacy): void
    {
        $response = $this->postJson($route, array_merge($this->registrationData($legacy), [
            'role' => 'super_admin',
            'status' => 'active',
            'verification_status' => 'verified',
            'agency_id' => 'client-chosen',
        ]))->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('agency.status', 'pending')
            ->assertJsonPath('agency.verification_status', 'unverified')
            ->assertJsonPath('user.workspace_access', self::PENDING_ACCESS)
            ->assertJsonPath('user.role', 'dealer')
            ->assertJsonPath('user.dealer_id', null)
            ->assertJsonPath('user.is_platform', false)
            ->assertJsonPath('user.permissions', [])
            ->assertJsonPath('user.enterprise_permissions', [])
            ->assertJsonPath('user.agencies.0.agency_status', 'pending')
            ->assertJsonPath('user.agencies.0.verification_status', 'unverified')
            ->assertJsonPath('user.agencies.0.role_key', 'agency_owner');

        $agencyId = $response->json('agency.id');
        $this->assertNotSame('client-chosen', $agencyId);
        $this->assertDatabaseHas('agencies', [
            'id' => $agencyId, 'name' => 'New Agency', 'owner_user_id' => $response->json('user.id'),
        ]);
        $this->assertDatabaseHas('agency_members', [
            'agency_id' => $agencyId, 'user_id' => $response->json('user.id'),
            'role_id' => 'role_agency_owner', 'status' => 'active',
        ]);
        $this->assertDatabaseHas('users', ['id' => $response->json('user.id'), 'status' => 'active']);
        $this->assertDatabaseCount('dealers', 0);
        $this->assertDatabaseCount('business_records', 0);
        $this->assertStringNotContainsString('42101-1234567-1', $response->getContent());
        $this->assertArrayNotHasKey('password', $response->json('user'));
        $this->assertNotEmpty($response->json('token'));

        $this->assertWorkspaceBlocked($response->json('token'), 'pending');
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('media_files', 0);
        $this->assertDatabaseCount('agency_videos', 0);
        $this->assertDatabaseCount('properties', 0);
        $this->assertDatabaseCount('business_records', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    #[DataProvider('registrationRoutes')]
    public function test_registration_rolls_back_if_membership_cannot_be_created(string $route, bool $legacy): void
    {
        Role::whereKey('role_agency_owner')->delete();
        $this->postJson($route, $this->registrationData($legacy))->assertStatus(500);

        foreach (['users', 'agencies', 'agency_members', 'personal_access_tokens', 'dealers', 'business_records'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    #[DataProvider('registrationRoutes')]
    public function test_registration_setting_applies_to_both_routes(string $route, bool $legacy): void
    {
        SystemSetting::set('registration.agency_enabled', false);
        $this->postJson($route, $this->registrationData($legacy))->assertForbidden();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('agencies', 0);
    }

    public function test_legacy_name_mapping_is_validated_and_has_a_safe_default(): void
    {
        $data = $this->registrationData(true);
        $this->postJson('/api/auth/register', array_merge($data, ['fullName' => 'X']))
            ->assertUnprocessable()->assertJsonValidationErrors('fullName');
        unset($data['agencyOrBranch']);
        $this->postJson('/api/auth/register', $data)->assertCreated()
            ->assertJsonPath('agency.name', 'New Owner & Associates')
            ->assertJsonPath('user.name', 'New Owner');
    }

    private function assertWorkspaceBlocked(string $token, string $status): void
    {
        $this->asGuest(); // Fresh HTTP request, but keep the SAME issued token.
        $headers = $this->authHeaders($token);
        foreach (['/api/workspace', '/api/admin/dashboard', '/api/agency/profile', '/api/agency/documents', '/api/agency/media'] as $route) {
            $response = $this->getJson($route, $headers)->assertForbidden()
                ->assertJsonPath('success', false)
                ->assertJsonPath('workspace_access.allowed', false)
                ->assertJsonPath('workspace_access.status', $status);
            $this->assertSame($response->json('workspace_access.message'), $response->json('message'));
        }

        $this->putJson('/api/workspace', ['collections' => [
            'files' => [['id' => 'blocked-file']],
            'media' => [['id' => 'blocked-media']],
            'videos' => [['id' => 'blocked-video']],
            'properties' => [['id' => 'blocked-property']],
        ]], $headers)->assertForbidden()->assertJsonPath('workspace_access.status', $status);

        $this->post('/api/agency/documents', [
            'file' => UploadedFile::fake()->create('deed.pdf', 10, 'application/pdf'),
            'category' => 'other',
        ], $headers)->assertForbidden()->assertJsonPath('workspace_access.status', $status);

        foreach (['image' => ['photo.jpg', 'image/jpeg'], 'video' => ['tour.mp4', 'video/mp4']] as $type => [$name, $mime]) {
            $this->post('/api/agency/media', [
                'file' => UploadedFile::fake()->create($name, 10, $mime),
                'title' => 'Blocked upload', 'type' => $type,
            ], $headers)->assertForbidden()->assertJsonPath('workspace_access.status', $status);
        }
        $this->postJson('/api/agency/media', [
            'url' => 'https://example.test/image.jpg', 'title' => 'Blocked URL',
        ], $headers)->assertForbidden()->assertJsonPath('workspace_access.status', $status);
        $this->postJson('/api/videos', [
            'title' => 'Blocked video', 'agencyName' => 'Test', 'videoUrl' => 'https://example.test/tour.mp4',
        ], $headers)->assertForbidden()->assertJsonPath('workspace_access.status', $status);
        $this->postJson('/api/properties', $this->listingData(), $headers)
            ->assertForbidden()->assertJsonPath('workspace_access.status', $status);
    }

    private function listingData(): array
    {
        return ['title' => 'Test plot', 'pricePKR' => 1000000, 'phase' => '1', 'sector' => 'A', 'sizeSqYards' => 120];
    }

    public static function agencyStates(): array
    {
        return [
            'pending' => ['pending', 'unverified', 'pending'],
            'verified but not active' => ['pending', 'verified', 'pending'],
            'active but not verified' => ['active', 'unverified', 'pending'],
            'verification rejected' => ['active', 'rejected', 'rejected'],
            'agency rejected' => ['rejected', 'verified', 'rejected'],
            'suspended' => ['suspended', 'verified', 'suspended'],
        ];
    }

    #[DataProvider('agencyStates')]
    public function test_existing_token_is_blocked_as_soon_as_agency_is_not_active_and_verified(string $status, string $verification, string $expected): void
    {
        [$user, $agency] = $this->agencyUser($this->ownerPermissions());
        $token = $this->tokenFor($user);
        $this->getJson('/api/workspace', $this->authHeaders($token))->assertOk();
        $agency->update(['status' => $status, 'verification_status' => $verification]);

        $this->assertWorkspaceBlocked($token, $expected);
        $this->getJson('/api/auth/me', $this->authHeaders($token))->assertOk()
            ->assertJsonPath('user.workspace_access.status', $expected)
            ->assertJsonPath('user.agencies.0.agency_status', $status)
            ->assertJsonPath('user.agencies.0.verification_status', $verification);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_platform_admin_approval_unlocks_the_original_registration_token_and_uploads(): void
    {
        $registration = $this->postJson('/api/agency/register', $this->registrationData())->assertCreated();
        $token = $registration->json('token');
        $agencyId = $registration->json('agency.id');
        $this->seedPlatformRole('super_admin');
        $admin = $this->makeUser('super_admin');

        $this->patchJson("/api/admin/agencies/{$agencyId}/verify", ['verification_status' => 'verified'],
            $this->authHeaders($this->tokenFor($admin)))
            ->assertOk()->assertJsonPath('agency.status', 'active')
            ->assertJsonPath('agency.verification_status', 'verified');
        $this->assertDatabaseHas('agencies', ['id' => $agencyId, 'verified_by' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'agency.verify', 'agency_id' => $agencyId]);

        $this->asGuest();
        $headers = $this->authHeaders($token);
        $this->getJson('/api/auth/me', $headers)->assertOk()
            ->assertJsonPath('user.workspace_access', [
                'allowed' => true, 'status' => 'approved', 'message' => 'Workspace access is approved.',
            ]);
        $this->getJson('/api/workspace', $headers)->assertOk();
        $this->getJson('/api/admin/dashboard', $headers)->assertOk()->assertJsonPath('scope', $agencyId);
        $documentId = $this->post('/api/agency/documents', [
            'file' => UploadedFile::fake()->create('deed.pdf', 10, 'application/pdf'), 'category' => 'other',
        ], $headers)->assertCreated()->json('document.id');
        $mediaId = $this->post('/api/agency/media', [
            'file' => UploadedFile::fake()->create('photo.jpg', 10, 'image/jpeg'), 'title' => 'Photo',
        ], $headers)->assertCreated()->json('media.id');
        $this->post('/api/agency/media', [
            'file' => UploadedFile::fake()->create('tour.mp4', 10, 'video/mp4'), 'title' => 'Tour',
        ], $headers)->assertCreated();
        $this->postJson('/api/videos', [
            'title' => 'Tour', 'agencyName' => 'New Agency', 'videoUrl' => 'https://example.test/tour.mp4',
        ], $headers)->assertCreated();
        $this->postJson('/api/properties', $this->listingData(), $headers)->assertCreated();

        // Suspension also blocks existing private download URLs, not just uploads.
        Agency::findOrFail($agencyId)->update(['status' => 'suspended']);
        $this->asGuest();
        $this->getJson("/api/agency/documents/{$documentId}/download", $headers)->assertForbidden()
            ->assertJsonPath('workspace_access.status', 'suspended');
        $this->getJson("/api/agency/media/{$mediaId}/file", $headers)->assertForbidden()
            ->assertJsonPath('workspace_access.status', 'suspended');
    }

    public static function inactiveMemberships(): array
    {
        return [['revoked'], ['inactive'], ['deactivated'], ['invited']];
    }

    #[DataProvider('inactiveMemberships')]
    public function test_inactive_membership_never_falls_back_to_legacy_platform_privileges(string $status): void
    {
        $user = $this->makeUser('manager'); // Has powerful legacy grants, but no explicit cross-agency grant.
        $agency = $this->makeAgency();
        $membership = $this->makeMember($user, $agency, Role::findOrFail('role_agency_owner'));
        $token = $this->tokenFor($user);
        $this->getJson('/api/workspace', $this->authHeaders($token))->assertOk();
        $membership->update(['status' => $status]);

        $this->assertWorkspaceBlocked($token, 'membership_inactive');
        $this->assertFalse($user->isPlatformUser());
        $this->assertSame([], $user->permissionsFor());
        $this->assertFalse($user->hasPermission('upload_documents'));
        $this->getJson('/api/auth/me', $this->authHeaders($token))->assertOk()
            ->assertJsonPath('user.is_platform', false)
            ->assertJsonPath('user.agencies', [])
            ->assertJsonPath('user.enterprise_permissions', [])
            ->assertJsonPath('user.workspace_access.status', 'membership_inactive');
        $this->getJson('/api/workspace', array_merge($this->authHeaders($token), ['X-Agency-Id' => $agency->id]))
            ->assertForbidden()->assertJsonPath('workspace_access.status', 'membership_inactive');
    }

    public function test_legacy_dealer_without_membership_cannot_access_platform_workspace(): void
    {
        $user = $this->makeUser('dealer');
        $this->assertWorkspaceBlocked($this->tokenFor($user), 'membership_required');
        $this->assertFalse($user->isPlatformUser());
        $this->assertSame([], $user->permissionsFor());
    }

    public function test_explicit_agency_selection_matches_me_permissions_and_approval_status(): void
    {
        [$user, $pending] = $this->agencyUser($this->ownerPermissions());
        $pending->update(['status' => 'pending', 'verification_status' => 'unverified']);
        $approved = $this->makeAgency('Approved second agency');
        $role = $this->makeRole('agency', 'second', ['agency.view', 'documents.view']);
        $this->makeMember($user, $approved, $role);
        $headers = $this->authHeaders($this->tokenFor($user));

        $this->getJson('/api/agency/profile', $headers)->assertForbidden()
            ->assertJsonPath('workspace_access.status', 'pending');
        $selected = array_merge($headers, ['X-Agency-Id' => $approved->id]);
        $this->getJson('/api/agency/profile', $selected)->assertOk()->assertJsonPath('agency.id', $approved->id);
        $this->getJson('/api/auth/me', $selected)->assertOk()
            ->assertJsonPath('agency_id', $approved->id)
            ->assertJsonPath('user.workspace_access.allowed', true)
            ->assertJsonPath('user.enterprise_permissions', ['agency.view', 'documents.view']);

        $foreign = $this->makeAgency('Not a member');
        $invalid = array_merge($headers, ['X-Agency-Id' => $foreign->id]);
        $this->getJson('/api/workspace', $invalid)->assertForbidden()
            ->assertJsonPath('workspace_access.status', 'forbidden');
        $this->getJson('/api/auth/me', $invalid)->assertOk()
            ->assertJsonPath('agency_id', null)
            ->assertJsonPath('user.workspace_access.status', 'forbidden')
            ->assertJsonPath('user.enterprise_permissions', []);

        $user->memberships()->where('agency_id', $approved->id)->update(['status' => 'deactivated']);
        $this->getJson('/api/agency/profile', $selected)->assertForbidden()
            ->assertJsonPath('workspace_access.status', 'membership_inactive');
        $this->getJson('/api/auth/me', $selected)->assertOk()
            ->assertJsonPath('user.workspace_access.status', 'membership_inactive');
    }

    public function test_public_routes_login_status_and_logout_remain_available_to_pending_users(): void
    {
        [$user, $agency] = $this->agencyUser($this->ownerPermissions());
        $agency->update(['status' => 'pending', 'verification_status' => 'unverified']);
        $token = $this->tokenFor($user);
        $headers = array_merge($this->authHeaders($token), ['X-Agency-Id' => 'stale-client-context']);
        foreach (['/api/catalog', '/api/agencies', '/api/properties', '/api/videos', '/api/plans'] as $route) {
            $this->getJson($route, $headers)->assertOk();
        }
        $this->getJson('/api/auth/me', $this->authHeaders($token))->assertOk()
            ->assertJsonPath('user.workspace_access', self::PENDING_ACCESS);
        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email, 'password' => 'Secret123!',
        ], $this->authHeaders($token))->assertOk()->assertJsonPath('user.workspace_access', self::PENDING_ACCESS);
        $this->assertNotEmpty($login->json('token'));
        $this->postJson('/api/agency/register', $this->registrationData(), $headers)->assertCreated();
        $legacy = array_merge($this->registrationData(true), ['email' => 'legacy-second@example.test']);
        $this->postJson('/api/auth/register', $legacy, $headers)->assertCreated();

        $this->asGuest();
        $this->postJson('/api/auth/logout', [], $headers)->assertOk();
        $this->asGuest();
        $this->getJson('/api/auth/me', $this->authHeaders($token))->assertUnauthorized();
        $this->postJson('/api/auth/logout-all', [], array_merge(
            $this->authHeaders($login->json('token')), ['X-Agency-Id' => 'stale-client-context']
        ))->assertOk();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_denied_user_sees_only_public_listings_not_the_privileged_agency_view(): void
    {
        [$user, $agency] = $this->agencyUser($this->ownerPermissions());
        $private = $this->makePublicProperty($agency, ['approval_status' => 'draft', 'visibility' => 'private']);
        $public = $this->makePublicProperty($this->makeAgency('Public agency'));
        $token = $this->tokenFor($user);
        $this->getJson("/api/properties/{$private->id}", $this->authHeaders($token))->assertOk();
        $agency->update(['status' => 'suspended']);
        $this->asGuest();
        $this->getJson("/api/properties/{$private->id}", $this->authHeaders($token))->assertNotFound();
        $listings = $this->getJson('/api/properties', $this->authHeaders($token))->assertOk()->json();
        $this->assertSame([$public->id], array_column($listings, 'id'));
    }

    public static function accountStates(): array
    {
        return [['pending'], ['rejected'], ['suspended'], ['inactive']];
    }

    #[DataProvider('accountStates')]
    public function test_account_status_overrides_an_approved_agency_and_existing_token(string $status): void
    {
        [$user] = $this->agencyUser($this->ownerPermissions());
        $token = $this->tokenFor($user);
        $user->update(['status' => $status]);
        $this->asGuest();
        $this->getJson('/api/workspace', $this->authHeaders($token))->assertForbidden()
            ->assertJsonPath('workspace_access.status', $status);
        $this->getJson('/api/auth/me', $this->authHeaders($token))->assertOk()
            ->assertJsonPath('user.workspace_access.status', $status);
        if ($status === 'pending') {
            $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'Secret123!'])
                ->assertOk()->assertJsonPath('user.workspace_access.status', 'pending');
        }
        $this->postJson('/api/auth/logout', [], $this->authHeaders($token))->assertOk();
    }

    public function test_explicit_platform_admin_grant_survives_agency_membership_but_not_account_suspension(): void
    {
        $this->seedPlatformRole('super_admin');
        $admin = $this->makeUser('super_admin');
        $agency = $this->makeAgency('Under review');
        $agency->update(['status' => 'pending', 'verification_status' => 'unverified']);
        $membership = $this->makeMember($admin, $agency, Role::findOrFail('role_agency_owner'));
        $headers = $this->authHeaders($this->tokenFor($admin));
        $crossAgency = array_merge($headers, ['X-Agency-Id' => $agency->id]);

        foreach (['active', 'deactivated'] as $membershipStatus) {
            $membership->update(['status' => $membershipStatus]);
            $this->asGuest();
            $this->getJson('/api/admin/agencies', $headers)->assertOk();
            $this->getJson('/api/admin/dashboard', $headers)->assertOk()->assertJsonPath('scope', 'platform');
            $this->getJson('/api/agency/profile', $crossAgency)->assertOk();
            $this->getJson('/api/auth/me', $crossAgency)->assertOk()
                ->assertJsonPath('user.workspace_access.allowed', true)
                ->assertJsonPath('user.is_platform', true);
        }
        $this->patchJson("/api/admin/agencies/{$agency->id}/verify", ['verification_status' => 'verified'], $crossAgency)
            ->assertOk()->assertJsonPath('agency.status', 'active');
        $admin->update(['status' => 'suspended']);
        $this->asGuest();
        $this->getJson('/api/admin/agencies', $headers)->assertForbidden()
            ->assertJsonPath('workspace_access.status', 'suspended');
    }

    public function test_deleted_agency_and_missing_explicit_platform_target_fail_closed(): void
    {
        [$user, $agency] = $this->agencyUser($this->ownerPermissions());
        $agency->delete();
        $this->getJson('/api/workspace', $this->authHeaders($this->tokenFor($user)))->assertForbidden()
            ->assertJsonPath('workspace_access.status', 'agency_unavailable');

        $this->seedPlatformRole('super_admin');
        $admin = $this->makeUser('super_admin');
        $this->getJson('/api/workspace', array_merge(
            $this->authHeaders($this->tokenFor($admin)), ['X-Agency-Id' => 'does-not-exist']
        ))->assertForbidden()->assertJsonPath('workspace_access.status', 'agency_unavailable');
    }
}
