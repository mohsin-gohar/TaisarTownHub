<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencyMember;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Shared helpers for the enterprise feature suite.
 *
 * Every test boots a fresh sqlite :memory: database (RefreshDatabase runs the
 * real migration set), so these factories create exactly the rows the API
 * endpoints need — no seeding side effects.
 */
abstract class EnterpriseTestCase extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(string $role = 'operator', array $attrs = []): User
    {
        return User::create(array_merge([
            'name' => 'Test ' . ucfirst($role),
            'email' => 'test+' . Str::random(10) . '@example.test',
            'password' => Hash::make('Secret123!'),
            'role' => $role,
            'status' => 'active',
        ], $attrs));
    }

    protected function makeAgency(string $name = 'Test Agency'): Agency
    {
        $agency = new Agency([
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(4),
            'status' => 'active',
            'verification_status' => 'verified',
        ]);
        $agency->id = 'agy_' . Str::random(12);
        $agency->save();

        return $agency;
    }

    protected function makeRole(string $scope, string $key, array $permissions = []): Role
    {
        $role = new Role([
            'scope' => $scope,
            'key' => $key,
            'name' => ucfirst($key),
            'is_system' => false,
            'permissions' => $permissions,
        ]);
        $role->id = 'role_' . Str::random(12);
        $role->save();

        return $role;
    }

    /**
     * Mirror the EnterpriseSeeder's platform role row for a legacy staff role
     * (grant set = Permissions::platformRoleGrants), so permission resolution
     * behaves exactly like production without running the full seeder.
     */
    protected function seedPlatformRole(string $key): Role
    {
        $role = new Role([
            'scope' => 'platform',
            'agency_id' => null,
            'key' => $key,
            'name' => ucfirst($key),
            'is_system' => true,
            'permissions' => \App\Support\Permissions::platformRoleGrants($key),
        ]);
        $role->id = 'role_platform_' . $key;
        $role->save();

        return $role;
    }

    protected function makeMember(User $user, Agency $agency, Role $role, string $status = 'active'): AgencyMember
    {
        return AgencyMember::create([
            'agency_id' => $agency->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => $status,
        ]);
    }

    /**
     * Create an agency + agency role + member user in one call.
     *
     * @return array{0: User, 1: Agency, 2: Role}
     */
    protected function agencyUser(array $permissions, string $roleKey = 'agent', ?Agency $agency = null): array
    {
        $agency = $agency ?? $this->makeAgency('Test Agency ' . Str::random(4));
        $role = $this->makeRole('agency', $roleKey, $permissions);
        $user = $this->makeUser('agent');
        $this->makeMember($user, $agency, $role);

        return [$user, $agency, $role];
    }

    protected function tokenFor(User $user): string
    {
        // Feature tests reuse one application instance; drop any user/guard
        // cached by a previous in-process request so this request resolves the
        // token it is actually sent with (production always boots fresh).
        $this->app['auth']->forgetGuards();

        return $user->createToken('test-token')->plainTextToken;
    }

    /** Create a listing that is live on the public marketplace. */
    protected function makePublicProperty(Agency $agency, array $attrs = []): Property
    {
        $property = new Property(array_merge([
            'title' => 'Public Plot ' . Str::random(5),
            'pricePKR' => 10000000,
            'phase' => '1',
            'sector' => 'A',
            'sizeSqYards' => 120,
            'type' => 'Plot',
            'status' => 'Available',
            'approval_status' => 'approved',
            'visibility' => 'public',
        ], $attrs));
        $property->id = 'prop_' . Str::random(12);
        $property->agency_id = $agency->id;
        $property->save();

        return $property;
    }

    /** An anonymous request helper that clears cached guards (fresh visitor). */
    protected function asGuest(): void
    {
        $this->app['auth']->forgetGuards();
    }

    /**
     * Solve the public inquiry captcha (G-11) for guest submissions.
     *
     * Fetches a real challenge from GET /api/inquiries/captcha, parses the
     * math question and returns the fields that must accompany
     * POST /api/inquiries. Tests must call this once per submission: the
     * token is single-use.
     */
    protected function solveInquiryCaptcha(): array
    {
        $challenge = $this->getJson('/api/inquiries/captcha')
            ->assertStatus(200)
            ->json();

        $this->assertIsArray($challenge, 'captcha endpoint must return JSON');
        $this->assertArrayHasKey('token', $challenge);
        $this->assertMatchesRegularExpression('/(\d+)\s*\+\s*(\d+)/', (string) $challenge['question']);
        preg_match('/(\d+)\s*\+\s*(\d+)/', (string) $challenge['question'], $m);

        return [
            'captchaToken' => $challenge['token'],
            'captchaAnswer' => (string) ((int) $m[1] + (int) $m[2]),
        ];
    }

    protected function authHeaders(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'];
    }
}
