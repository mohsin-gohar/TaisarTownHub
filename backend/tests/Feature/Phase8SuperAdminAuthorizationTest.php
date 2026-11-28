<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Phase 8 — server-side authorization boundary for every Super Admin surface.
 *
 * Verifies that lower-privilege personas cannot reach platform-only operations
 * by calling the API directly (a hidden button is not authorization).
 */
class Phase8SuperAdminAuthorizationTest extends EnterpriseTestCase
{
    private function platformAdmin(): User
    {
        $this->seedPlatformRole('super_admin');

        return $this->makeUser('super_admin', ['email' => 'p8-admin@example.test']);
    }

    private function agencyPersona(string $roleKey, array $permissions): array
    {
        return $this->agencyUser($permissions, $roleKey);
    }

    /** @return array<int, array{0:string, 1:string, 2:array}> */
    private function platformOnlyEndpoints(): array
    {
        return [
            ['get', '/api/admin/reports', []],
            ['get', '/api/admin/agencies', []],
            ['get', '/api/admin/users', []],
            ['get', '/api/admin/featured-agencies', []],
            ['get', '/api/admin/support-tickets', []],
            ['get', '/api/admin/settings', []],
            ['get', '/api/admin/audit-logs', []],
            ['get', '/api/admin/plans', []],
            ['get', '/api/admin/subscription-invoices', []],
            ['get', '/api/admin/agencies-oversight', []],
            ['get', '/api/admin/maps', []],
        ];
    }

    public function test_platform_admin_can_access_every_super_admin_surface(): void
    {
        $admin = $this->platformAdmin();
        $headers = $this->authHeaders($this->tokenFor($admin));

        foreach ($this->platformOnlyEndpoints() as [$method, $uri, $data]) {
            $response = $method === 'get'
                ? $this->getJson($uri, $headers)
                : $this->postJson($uri, $data, $headers);

            $response->assertStatus(200, "Admin should be able to access {$uri}");
        }
    }

    public function test_agency_owner_cannot_access_platform_only_surfaces(): void
    {
        [$owner] = $this->agencyPersona('agency_owner', ['agency.view', 'properties.view', 'finance.view']);
        $headers = $this->authHeaders($this->tokenFor($owner));

        foreach ($this->platformOnlyEndpoints() as [$method, $uri, $data]) {
            $response = $method === 'get'
                ? $this->getJson($uri, $headers)
                : $this->postJson($uri, $data, $headers);

            $this->assertContains($response->getStatusCode(), [403, 404], "Owner must be denied on {$uri}");
        }
    }

    public function test_agency_manager_cannot_approve_agency_or_moderate_listings(): void
    {
        [$manager, $agency] = $this->agencyPersona('manager', ['agency.view', 'agency.manage_staff']);
        $headers = $this->authHeaders($this->tokenFor($manager));

        $this->patchJson('/api/admin/agencies/'.$agency->id.'/verify', ['verification_status' => 'verified'], $headers)
            ->assertStatus(403);
        $this->patchJson('/api/admin/agencies/'.$agency->id.'/status', ['status' => 'active'], $headers)
            ->assertStatus(403);
        $this->postJson('/api/admin/plans', ['name' => 'Rogue', 'key' => 'rogue'], $headers)
            ->assertStatus(403);
        $this->patchJson('/api/admin/settings', ['key' => 'platform.name', 'value' => 'Hacked'], $headers)
            ->assertStatus(403);
    }

    public function test_ordinary_member_cannot_reach_platform_dashboard_or_audit_logs(): void
    {
        [$member] = $this->agencyPersona('viewer', ['properties.view']);
        $headers = $this->authHeaders($this->tokenFor($member));

        $this->getJson('/api/admin/reports', $headers)->assertStatus(403);
        $this->getJson('/api/admin/audit-logs', $headers)->assertStatus(403);
        $this->getJson('/api/admin/users', $headers)->assertStatus(403);
    }

    public function test_platform_reports_require_admin_view_reports_and_validate_dates(): void
    {
        $admin = $this->platformAdmin();
        $headers = $this->authHeaders($this->tokenFor($admin));

        $this->getJson('/api/admin/reports?from=2026-01-02&to=2026-01-01', $headers)
            ->assertStatus(422);
        $this->getJson('/api/admin/reports?from=not-a-date', $headers)
            ->assertStatus(422);

        $this->getJson('/api/admin/reports?from=2026-01-01&to=2026-01-31', $headers)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('period.from', '2026-01-01');
    }

    public function test_audit_logs_are_scoped_for_agency_reporters_and_platform_for_admins(): void
    {
        [$userA, $agencyA] = $this->agencyPersona('manager', ['view_reports']);
        [$userB, $agencyB] = $this->agencyPersona('manager', ['view_reports']);

        DB::table('audit_logs')->insert([
            ['actor' => 'A', 'action' => 'agency.test', 'detail' => 'a', 'agency_id' => $agencyA->id, 'created_at' => now()],
            ['actor' => 'B', 'action' => 'agency.test', 'detail' => 'b', 'agency_id' => $agencyB->id, 'created_at' => now()],
        ]);

        $logsA = $this->getJson('/api/admin/audit-logs',
            $this->authHeaders($this->tokenFor($userA)))->assertStatus(200)->json('logs');
        $agenciesA = array_values(array_unique(array_column($logsA, 'agency_id')));
        $this->assertSame([$agencyA->id], $agenciesA);

        $logsB = $this->getJson('/api/admin/audit-logs',
            $this->authHeaders($this->tokenFor($userB)))->assertStatus(200)->json('logs');
        $agenciesB = array_values(array_unique(array_column($logsB, 'agency_id')));
        $this->assertSame([$agencyB->id], $agenciesB);
    }
}
