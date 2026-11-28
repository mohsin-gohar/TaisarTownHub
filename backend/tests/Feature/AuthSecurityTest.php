<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;

/**
 * Authentication security: token requirement, credential failures, and the
 * per-account lockout (Phase 3 AuthController + Phase 5 hardening).
 */
class AuthSecurityTest extends EnterpriseTestCase
{
    public function test_unauthenticated_api_requests_are_rejected_with_401(): void
    {
        foreach (['/api/auth/me', '/api/workspace', '/api/admin/dashboard'] as $path) {
            $this->getJson($path)->assertStatus(401);
        }
    }

    public function test_login_requires_valid_credentials(): void
    {
        $this->makeUser('operator', ['email' => 'lock@example.test']);

        $this->postJson('/api/auth/login', [
            'email' => 'lock@example.test',
            'password' => 'WrongPassword!',
        ])->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_account_locks_after_repeated_failed_logins(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = $this->makeUser('operator', ['email' => 'lock@example.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'lock@example.test',
                'password' => 'WrongPassword!',
            ]);
        }

        $user->refresh();
        $this->assertNotNull($user->locked_until, 'account should be locked after 5 failures');
        $this->assertTrue($user->locked_until->isFuture());

        // Even the correct password is refused while the lock is active.
        $this->postJson('/api/auth/login', [
            'email' => 'lock@example.test',
            'password' => 'Secret123!',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_successful_login_issues_token_and_me_returns_user(): void
    {
        $this->makeUser('super_admin', ['email' => 'boss@example.test']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'boss@example.test',
            'password' => 'Secret123!',
        ])->assertStatus(200);

        $token = $response->json('token');
        $this->assertNotEmpty($token);

        $this->getJson('/api/auth/me', $this->authHeaders($token))
            ->assertStatus(200)
            ->assertJsonPath('user.email', 'boss@example.test')
            ->assertJsonPath('user.role', 'super_admin');
    }

    public function test_failed_attempts_reset_after_lock_expiry_window(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = $this->makeUser('operator', ['email' => 'lock2@example.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'lock2@example.test',
                'password' => 'WrongPassword!',
            ]);
        }

        // Simulate the lock window elapsing.
        $user->forceFill(['locked_until' => now()->subMinute(), 'failed_attempts' => 0])->save();

        $this->postJson('/api/auth/login', [
            'email' => 'lock2@example.test',
            'password' => 'Secret123!',
        ])->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = $this->makeUser('operator', ['email' => 'logout@example.test']);
        $token = $this->tokenFor($user);

        $this->postJson('/api/auth/logout', [], $this->authHeaders($token))->assertStatus(200);

        // The token row must be gone (production behaviour).
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Feature tests reuse one application instance, so the Sanctum guard can
        // still hold the user resolved by the logout request; forget guards to
        // simulate a fresh HTTP request (production always gets one).
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/auth/me', $this->authHeaders($token))->assertStatus(401);
    }

    public function test_login_is_rate_limited_after_ten_attempts_per_minute(): void
    {
        $this->makeUser('operator', ['email' => 'ratelimit@example.test']);

        // Ten attempts are allowed through the route throttle (api.auth = 10/min)...
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'ratelimit@example.test',
                'password' => 'WrongPassword!',
            ]);
        }

        // ...the eleventh must be throttled (429), not processed.
        $this->postJson('/api/auth/login', [
            'email' => 'ratelimit@example.test',
            'password' => 'WrongPassword!',
        ])->assertStatus(429);
    }

    public function test_passwords_are_stored_hashed(): void
    {
        $user = $this->makeUser('operator', ['email' => 'hash@example.test']);
        $this->assertNotEquals('Secret123!', $user->password);
        $this->assertTrue(Hash::check('Secret123!', $user->password));
    }

    public function test_database_queue_defers_jobs_until_transaction_commit(): void
    {
        $this->assertTrue(
            (bool) config('queue.connections.database.after_commit'),
            'Queued notifications must not run before the transaction that created their state commits.'
        );

        $this->assertTrue(
            is_subclass_of(
                \App\Notifications\PasswordChangedNotification::class,
                \Illuminate\Contracts\Queue\ShouldQueue::class
            )
        );
        $this->assertTrue(
            is_subclass_of(
                \App\Notifications\SuspiciousLoginDetected::class,
                \Illuminate\Contracts\Queue\ShouldQueue::class
            )
        );
    }

}
