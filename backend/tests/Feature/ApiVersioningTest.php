<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\EnterpriseTestCase;

/**
 * G-1 — /api/v1 versioning.
 *
 * The shared route definitions are registered twice: the legacy `/api` prefix
 * (the frontend contract, must never break) and the versioned `/api/v1` prefix.
 * Both must behave identically.
 */
class ApiVersioningTest extends EnterpriseTestCase
{
    public function test_legacy_and_v1_health_both_respond(): void
    {
        $this->asGuest();
        $this->getJson('/api/health')->assertOk();
        $this->getJson('/api/v1/health')->assertOk();
    }

    public function test_legacy_and_v1_catalog_are_parity_public_reads(): void
    {
        $this->asGuest();
        $this->getJson('/api/catalog')->assertOk();
        $this->getJson('/api/v1/catalog')->assertOk();
        $this->getJson('/api/v1/properties')->assertOk();
    }

    public function test_v1_protected_routes_require_authentication(): void
    {
        $this->asGuest();
        $this->getJson('/api/v1/agency/profile')->assertUnauthorized();
        $this->getJson('/api/agency/profile')->assertUnauthorized();
    }

    public function test_v1_login_and_me_roundtrip(): void
    {
        $user = $this->makeUser('operator', [
            'email' => 'v1roundtrip@example.test',
            'password' => Hash::make('Secret123!'),
        ]);

        $this->asGuest();
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'v1roundtrip@example.test',
            'password' => 'Secret123!',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $token = $response->json('token');

        $this->withHeaders($this->authHeaders($token))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_every_legacy_api_route_has_a_v1_counterpart(): void
    {
        $legacy = [];
        $v1 = [];

        foreach (\Illuminate\Support\Facades\Route::getRoutes() as $route) {
            $path = rtrim($route->uri(), '/');
            $methods = array_diff($route->methods(), ['HEAD', 'OPTIONS']);

            if (str_starts_with($route->uri(), 'api/v1')) {
                $v1[substr($path, 7)] = true; // strip api/v1
            } elseif (str_starts_with($route->uri(), 'api/')) {
                $legacy[substr($path, 4)] = true; // strip api/
            }

            unset($methods);
        }

        $this->assertNotEmpty($legacy);
        // Route-for-route parity: every legacy path exists under v1.
        $missing = array_diff_key($legacy, $v1);
        $this->assertSame([], $missing, 'v1 is missing legacy paths: ' . implode(', ', array_keys($missing)));
    }

    public function test_no_duplicate_route_names_are_registered(): void
    {
        $names = [];
        foreach (\Illuminate\Support\Facades\Route::getRoutes() as $route) {
            $name = $route->getName();
            if ($name !== null) {
                $names[] = $name;
            }
        }

        $this->assertSame(count($names), count(array_unique($names)), 'duplicate route names registered');
    }
}
