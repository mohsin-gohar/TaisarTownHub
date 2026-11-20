<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\Feature\EnterpriseTestCase;

/**
 * Google Sign-In (Socialite) integration.
 *
 * The browser flow is: SPA obtains a consent URL with a per-tab nonce, Google
 * redirects to the API callback, the API verifies the one-time state and the
 * Google identity, then redirects to the SPA with a one-time exchange code.
 * These tests use a mocked Socialite provider so no real Google credentials
 * are required; the live provider handshake is documented as unverified.
 */
class GoogleOAuthTest extends EnterpriseTestCase
{
    private const NONCE = 'test-browser-nonce-0123456789';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'http://127.0.0.1:8080/api/auth/google/callback',
            'app.frontend_url' => 'http://127.0.0.1:3000',
        ]);
    }

    private function fakeGoogleUser(array $attrs = []): SocialiteUser
    {
        $user = Mockery::mock(SocialiteUser::class);
        $user->shouldReceive('getId')->andReturn($attrs['id'] ?? 'google-123');
        $user->shouldReceive('getEmail')->andReturn($attrs['email'] ?? 'google-user@example.test');
        $user->shouldReceive('getName')->andReturn($attrs['name'] ?? 'Google User');
        $user->shouldReceive('getAvatar')->andReturn($attrs['avatar'] ?? 'https://example.com/avatar.png');
        $user->user = ['email_verified' => $attrs['verified'] ?? true];

        return $user;
    }

    private function mockSocialite(SocialiteUser $googleUser): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    /** @return array{state: string, nonce_hash: string} */
    private function startFlow(string $nonce = self::NONCE): array
    {
        $body = $this->getJson('/api/auth/google/redirect?nonce=' . urlencode($nonce))
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->json();

        parse_str((string) parse_url((string) $body['url'], PHP_URL_QUERY), $query);

        $this->assertNotEmpty($query['state'] ?? null);
        $this->assertSame('http://127.0.0.1:8080/api/auth/google/callback', $query['redirect_uri'] ?? null);
        $this->assertSame('code', $query['response_type'] ?? null);
        $this->assertSame(hash('sha256', $nonce), $body['nonce_hash']);

        return ['state' => (string) $query['state'], 'nonce_hash' => (string) $body['nonce_hash']];
    }

    /** @return array<string, string> */
    private function redirectQuery(string $location): array
    {
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return $query;
    }

    private function exchange(string $code): array
    {
        $response = $this->postJson('/api/auth/google/exchange', ['code' => $code]);
        $response->assertStatus(200)->assertJsonPath('success', true);

        return $response->json();
    }

    public function test_redirect_reports_unconfigured_google_oauth(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
            'services.google.redirect' => null,
        ]);

        $this->getJson('/api/auth/google/redirect?nonce=' . urlencode(self::NONCE))
            ->assertStatus(503)
            ->assertJsonPath('success', false);
    }

    public function test_redirect_requires_a_browser_nonce_for_csrf_binding(): void
    {
        $this->getJson('/api/auth/google/redirect')
            ->assertStatus(422)
            ->assertJsonValidationErrors('nonce');

        $this->getJson('/api/auth/google/redirect?nonce=short')
            ->assertStatus(422);
    }

    public function test_redirect_builds_a_google_url_with_one_time_state(): void
    {
        $flow = $this->startFlow();

        // The state is server-side and single-use: consuming it once works,
        // the same state cannot be replayed by a second callback.
        $this->assertTrue(Cache::has('auth:google:state:' . $flow['state']));
    }

    public function test_callback_rejects_invalid_state_before_contacting_google(): void
    {
        $response = $this->get('/api/auth/google/callback?code=abc&state=not-a-valid-state');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('google_error=invalid_state', $location);
        $this->assertStringNotContainsString('google_nonce=', $location);
    }

    public function test_callback_with_legacy_state_payload_is_rejected(): void
    {
        Cache::put('auth:google:state:legacy-state', true, now()->addMinutes(5));

        $response = $this->get('/api/auth/google/callback?code=abc&state=legacy-state');

        $this->assertStringContainsString(
            'google_error=invalid_state',
            (string) $response->headers->get('Location')
        );
    }

    public function test_callback_returns_google_error_with_the_flow_nonce(): void
    {
        $flow = $this->startFlow();

        $response = $this->get('/api/auth/google/callback?error=access_denied&state=' . $flow['state']);
        $query = $this->redirectQuery((string) $response->headers->get('Location'));

        $this->assertSame('access_denied', $query['google_error'] ?? null);
        $this->assertSame($flow['nonce_hash'], $query['google_nonce'] ?? null);
    }

    public function test_callback_handles_provider_failure_without_issuing_a_token(): void
    {
        $flow = $this->startFlow();

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andThrow(new \RuntimeException('provider down'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/api/auth/google/callback?code=abc&state=' . $flow['state']);
        $query = $this->redirectQuery((string) $response->headers->get('Location'));

        $this->assertSame('oauth_failed', $query['google_error'] ?? null);
        $this->assertArrayNotHasKey('google_code', $query);
        $this->assertSame($flow['nonce_hash'], $query['google_nonce'] ?? null);
    }

    public function test_exchange_rejects_unknown_code(): void
    {
        $this->postJson('/api/auth/google/exchange', [
            'code' => 'this-code-was-never-issued-by-the-callback',
        ])->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_callback_links_existing_user_by_verified_email_and_preserves_password_login(): void
    {
        $existing = $this->makeUser('dealer', ['email' => 'link@example.test']);
        $googleUser = $this->fakeGoogleUser([
            'id' => 'google-linked-1',
            'email' => 'link@example.test',
        ]);
        $this->mockSocialite($googleUser);

        $flow = $this->startFlow();
        $response = $this->get('/api/auth/google/callback?code=abc&state=' . $flow['state']);
        $query = $this->redirectQuery((string) $response->headers->get('Location'));

        $this->assertArrayHasKey('google_code', $query);
        $this->assertSame($flow['nonce_hash'], $query['google_nonce'] ?? null);

        $token = $this->exchange($query['google_code'])['token'];
        $this->assertNotEmpty($token);

        $me = $this->getJson('/api/auth/me', $this->authHeaders($token))
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->json();

        $this->assertSame($existing->id, $me['user']['id']);
        $this->assertSame('google-linked-1', $existing->fresh()->google_id);
        $this->assertNotNull($existing->fresh()->email_verified_at);

        // Existing email/password authentication must keep working.
        $this->postJson('/api/auth/login', [
            'email' => 'link@example.test',
            'password' => 'Secret123!',
        ])->assertStatus(200)->assertJsonPath('success', true);
    }

    public function test_linking_is_case_insensitive_for_legacy_mixed_case_rows(): void
    {
        $existing = $this->makeUser('dealer', ['email' => 'legacy@example.test']);
        // Simulate a legacy row written before email normalisation existed.
        DB::table('users')->where('id', $existing->id)->update(['email' => 'Legacy.Mixed@Example.Test']);

        $googleUser = $this->fakeGoogleUser([
            'id' => 'google-legacy-case',
            'email' => 'legacy.mixed@example.test',
        ]);
        $this->mockSocialite($googleUser);

        $flow = $this->startFlow();
        $response = $this->get('/api/auth/google/callback?code=abc&state=' . $flow['state']);
        $query = $this->redirectQuery((string) $response->headers->get('Location'));

        $this->exchange($query['google_code']);
        $this->assertSame('google-legacy-case', $existing->fresh()->google_id);
    }

    public function test_callback_creates_a_new_pending_account_for_an_unknown_google_email(): void
    {
        $googleUser = $this->fakeGoogleUser([
            'id' => 'google-new-1',
            'email' => 'Brand-New-Google@Example.Test',
            'name' => 'Brand New',
        ]);
        $this->mockSocialite($googleUser);

        $flow = $this->startFlow();
        $response = $this->get('/api/auth/google/callback?code=abc&state=' . $flow['state']);
        $query = $this->redirectQuery((string) $response->headers->get('Location'));
        $token = $this->exchange($query['google_code'])['token'];

        $user = User::where('google_id', 'google-new-1')->firstOrFail();
        $this->assertSame('brand-new-google@example.test', $user->email);
        $this->assertSame('pending', $user->status);
        $this->assertSame('dealer', $user->role);
        $this->assertNotNull($user->email_verified_at);

        // A pending account may sign in to see its status, but the workspace
        // must stay locked until the agency is approved.
        $this->getJson('/api/auth/me', $this->authHeaders($token))
            ->assertStatus(200)
            ->assertJsonPath('user.workspace_access.allowed', false)
            ->assertJsonPath('user.workspace_access.status', 'pending');
    }

    public function test_callback_rejects_unverified_google_email(): void
    {
        $googleUser = $this->fakeGoogleUser([
            'id' => 'google-unverified-1',
            'email' => 'unverified-google@example.test',
            'verified' => false,
        ]);
        $this->mockSocialite($googleUser);

        $flow = $this->startFlow();
        $response = $this->get('/api/auth/google/callback?code=abc&state=' . $flow['state']);
        $query = $this->redirectQuery((string) $response->headers->get('Location'));

        $this->assertSame('email_unverified', $query['google_error'] ?? null);
        $this->assertArrayNotHasKey('google_code', $query);
        $this->assertSame($flow['nonce_hash'], $query['google_nonce'] ?? null);
        $this->assertDatabaseMissing('users', ['google_id' => 'google-unverified-1']);
    }

    public function test_callback_rejects_missing_google_email(): void
    {
        $googleUser = $this->fakeGoogleUser([
            'id' => 'google-no-email',
            'email' => '',
        ]);
        $this->mockSocialite($googleUser);

        $flow = $this->startFlow();
        $response = $this->get('/api/auth/google/callback?code=abc&state=' . $flow['state']);
        $query = $this->redirectQuery((string) $response->headers->get('Location'));

        $this->assertSame('email_unverified', $query['google_error'] ?? null);
        $this->assertDatabaseMissing('users', ['google_id' => 'google-no-email']);
    }

    public function test_suspended_user_cannot_link_google_or_obtain_a_session(): void
    {
        $existing = $this->makeUser('dealer', ['email' => 'suspended-google@example.test']);
        $existing->forceFill(['status' => 'suspended'])->save();

        $googleUser = $this->fakeGoogleUser([
            'id' => 'google-suspended-1',
            'email' => 'suspended-google@example.test',
        ]);
        $this->mockSocialite($googleUser);

        $flow = $this->startFlow();
        $response = $this->get('/api/auth/google/callback?code=abc&state=' . $flow['state']);
        $query = $this->redirectQuery((string) $response->headers->get('Location'));

        $this->assertSame('account_disabled', $query['google_error'] ?? null);
        $this->assertArrayNotHasKey('google_code', $query);
        $this->assertNull($existing->fresh()->google_id, 'blocked accounts must not be linked');
    }

    public static function blockedStatuses(): array
    {
        return [['inactive'], ['rejected']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('blockedStatuses')]
    public function test_inactive_and_rejected_users_cannot_obtain_a_session(string $status): void
    {
        $existing = $this->makeUser('dealer', ['email' => $status . '-google@example.test']);
        $existing->forceFill(['status' => $status])->save();

        $googleUser = $this->fakeGoogleUser([
            'id' => 'google-' . $status,
            'email' => $status . '-google@example.test',
        ]);
        $this->mockSocialite($googleUser);

        $flow = $this->startFlow();
        $response = $this->get('/api/auth/google/callback?code=abc&state=' . $flow['state']);
        $query = $this->redirectQuery((string) $response->headers->get('Location'));

        $this->assertSame('account_disabled', $query['google_error'] ?? null);
        $this->assertArrayNotHasKey('google_code', $query);
    }

    public function test_password_login_uses_the_same_blocked_status_rules(): void
    {
        $user = $this->makeUser('dealer', ['email' => 'blocked-password@example.test']);
        $user->forceFill(['status' => 'inactive'])->save();

        $this->postJson('/api/auth/login', [
            'email' => 'blocked-password@example.test',
            'password' => 'Secret123!',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_locked_account_cannot_use_google_sign_in(): void
    {
        $existing = $this->makeUser('dealer', ['email' => 'locked-google@example.test']);
        $existing->forceFill(['locked_until' => now()->addMinutes(10)])->save();

        $googleUser = $this->fakeGoogleUser([
            'id' => 'google-locked-1',
            'email' => 'locked-google@example.test',
        ]);
        $this->mockSocialite($googleUser);

        $flow = $this->startFlow();
        $response = $this->get('/api/auth/google/callback?code=abc&state=' . $flow['state']);
        $query = $this->redirectQuery((string) $response->headers->get('Location'));

        $this->assertSame('account_locked', $query['google_error'] ?? null);
        $this->assertArrayNotHasKey('google_code', $query);
        $this->assertNull($existing->fresh()->google_id);
    }

    public function test_exchange_code_is_single_use(): void
    {
        $googleUser = $this->fakeGoogleUser([
            'id' => 'google-replay-1',
            'email' => 'replay-google@example.test',
        ]);
        $this->mockSocialite($googleUser);

        $flow = $this->startFlow();
        $response = $this->get('/api/auth/google/callback?code=abc&state=' . $flow['state']);
        $code = $this->redirectQuery((string) $response->headers->get('Location'))['google_code'];

        $this->exchange($code);

        $this->postJson('/api/auth/google/exchange', ['code' => $code])
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_mfa_enabled_user_receives_a_challenge_not_a_token(): void
    {
        $existing = $this->makeUser('dealer', ['email' => 'mfa-google@example.test']);
        $existing->forceFill([
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
            'mfa_enabled_at' => now(),
        ])->save();

        $googleUser = $this->fakeGoogleUser([
            'id' => 'google-mfa-1',
            'email' => 'mfa-google@example.test',
        ]);
        $this->mockSocialite($googleUser);

        $flow = $this->startFlow();
        $response = $this->get('/api/auth/google/callback?code=abc&state=' . $flow['state']);
        $query = $this->redirectQuery((string) $response->headers->get('Location'));

        $this->assertArrayHasKey('google_challenge', $query);
        $this->assertArrayNotHasKey('google_code', $query);
        $this->assertSame((string) \App\Http\Controllers\Api\AuthController::MFA_CHALLENGE_TTL, $query['expires'] ?? null);
        $this->assertSame($flow['nonce_hash'], $query['google_nonce'] ?? null);

        // The challenge is backed by the existing MFA cache contract.
        $this->assertTrue(Cache::has('auth:mfa:challenge:' . hash('sha256', $query['google_challenge'])));
    }

    public function test_state_cannot_be_replayed(): void
    {
        $googleUser = $this->fakeGoogleUser([
            'id' => 'google-state-replay',
            'email' => 'state-replay@example.test',
        ]);
        $this->mockSocialite($googleUser);

        $flow = $this->startFlow();

        // First use consumes the state.
        $this->get('/api/auth/google/callback?code=abc&state=' . $flow['state'])->assertRedirect();

        // Second use of the same state must be rejected.
        $response = $this->get('/api/auth/google/callback?code=abc&state=' . $flow['state']);
        $this->assertStringContainsString(
            'google_error=invalid_state',
            (string) $response->headers->get('Location')
        );
    }
}
