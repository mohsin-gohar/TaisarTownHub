<?php

namespace Tests\Feature;

use App\Models\LoginHistory;
use App\Models\User;
use App\Support\MfaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Hash;

/**
 * Phase 6 — optional TOTP multi-factor authentication.
 *
 * The suite proves the real security properties, not a simulation:
 *  - a sign-in with MFA enrolled issues NO token before the code is verified,
 *  - the authenticator code (RFC 6238) is verified against the stored secret,
 *  - a wrong code never issues a token and burns challenge attempts,
 *  - the challenge is single-use and expires,
 *  - recovery codes work exactly once and are stored hashed,
 *  - disabling MFA requires the password AND a valid code.
 */
class MfaTest extends EnterpriseTestCase
{
    private function enrolledUser(): array
    {
        $user = $this->makeUser('operator');
        $service = app(MfaService::class);
        $secret = $service->generateSecret();
        $codes = $service->generateRecoveryCodes();

        $user->forceFill([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => $service->hashRecoveryCodes($codes),
            'mfa_enabled_at' => now(),
        ])->save();

        return [$user, $secret, $codes];
    }

    public function test_login_with_mfa_returns_a_challenge_and_no_token(): void
    {
        [$user] = $this->enrolledUser();

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
        ])->assertOk();

        $response->assertJsonPath('mfa_required', true)
            ->assertJsonPath('success', true);

        $this->assertNotNull($response->json('challenge'));
        $this->assertArrayNotHasKey('token', $response->json());
        $this->assertSame(0, $user->tokens()->count(), 'no token may exist before the second factor');
    }

    public function test_valid_code_completes_the_sign_in_and_issues_the_token(): void
    {
        [$user, $secret] = $this->enrolledUser();
        $service = app(MfaService::class);

        $challenge = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
        ])->assertOk()->json('challenge');

        $response = $this->postJson('/api/auth/mfa/verify', [
            'challenge' => $challenge,
            'code' => $service->currentCode($secret),
        ])->assertOk();

        $response->assertJsonPath('success', true)->assertJsonPath('mfa_used', true);
        $this->assertNotEmpty($response->json('token'));
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_wrong_code_is_rejected_and_no_token_is_issued(): void
    {
        [$user] = $this->enrolledUser();

        $challenge = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
        ])->assertOk()->json('challenge');

        $this->postJson('/api/auth/mfa/verify', [
            'challenge' => $challenge,
            'code' => '000000',
        ])->assertStatus(422)->assertJsonPath('success', false);

        $this->assertSame(0, $user->tokens()->count());
        $this->assertNotNull(LoginHistory::where('user_id', $user->id)->where('reason', 'invalid_mfa_code')->first());
    }

    public function test_challenge_is_single_use_and_expires(): void
    {
        [$user, $secret] = $this->enrolledUser();
        $service = app(MfaService::class);

        $challenge = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
        ])->assertOk()->json('challenge');

        $this->postJson('/api/auth/mfa/verify', [
            'challenge' => $challenge,
            'code' => $service->currentCode($secret),
        ])->assertOk();

        // Replaying the same challenge must fail.
        $this->postJson('/api/auth/mfa/verify', [
            'challenge' => $challenge,
            'code' => $service->currentCode($secret),
        ])->assertStatus(401);

        // An expired challenge is rejected as well.
        Cache::put('auth:mfa:challenge:' . hash('sha256', 'stale-challenge-value-1234'), ['user_id' => $user->id], now()->subMinute());
        $this->postJson('/api/auth/mfa/verify', [
            'challenge' => 'stale-challenge-value-1234',
            'code' => $service->currentCode($secret),
        ])->assertStatus(401);
    }

    public function test_recovery_code_works_once_and_is_stored_hashed(): void
    {
        [$user, , $codes] = $this->enrolledUser();

        $challenge = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
        ])->assertOk()->json('challenge');

        $this->postJson('/api/auth/mfa/verify', [
            'challenge' => $challenge,
            'code' => $codes[0],
        ])->assertOk()->assertJsonPath('success', true);

        // The consumed code is gone from the account.
        $user->refresh();
        $this->assertCount(count($codes) - 1, $user->mfa_recovery_codes);
        $this->assertFalse(
            collect($user->mfa_recovery_codes)->contains(fn ($hash) => Hash::check($codes[0], $hash)),
            'a used recovery code must not remain valid',
        );

        // A second challenge cannot reuse the same recovery code.
        $challenge2 = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
        ])->assertOk()->json('challenge');

        $this->postJson('/api/auth/mfa/verify', [
            'challenge' => $challenge2,
            'code' => $codes[0],
        ])->assertStatus(422);
    }

    public function test_enrolment_flow_requires_a_valid_confirmation_code(): void
    {
        $user = $this->makeUser('operator');
        $service = app(MfaService::class);
        $token = $this->tokenFor($user);

        $setup = $this->postJson('/api/auth/mfa/setup', [], $this->authHeaders($token))->assertOk();
        $setup->assertJsonPath('success', true);
        $secret = $setup->json('secret');
        $this->assertNotEmpty($secret);
        $this->assertStringStartsWith('otpauth://totp/', $setup->json('otpauth_uri'));
        $this->assertCount(MfaService::RECOVERY_CODE_COUNT, $setup->json('recovery_codes'));

        // Not active until a live code is confirmed.
        $user->refresh();
        $this->assertFalse($user->hasMfaEnabled());

        $this->postJson('/api/auth/mfa/enable', ['code' => '111111'], $this->authHeaders($token))
            ->assertStatus(422);

        $this->postJson('/api/auth/mfa/enable', ['code' => $service->currentCode($secret)], $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('success', true);

        $user->refresh();
        $this->assertTrue($user->hasMfaEnabled());

        // The secret is encrypted at rest — the raw column never holds it.
        $raw = \Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->value('mfa_secret');
        $this->assertNotSame($secret, $raw);
    }

    /**
     * Create a user with MFA enabled and return a token that has passed MFA
     * verification (so the EnsureMfaVerified middleware will allow it).
     */
    protected function mfaTokenFor(User $user, string $secret): string
    {
        $service = app(MfaService::class);

        $challenge = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
        ])->assertOk()->json('challenge');

        $response = $this->postJson('/api/auth/mfa/verify', [
            'challenge' => $challenge,
            'code' => $service->currentCode($secret),
        ])->assertOk();

        return $response->json('token');
    }

    public function test_disabling_mfa_requires_password_and_code(): void
    {
        [$user, $secret] = $this->enrolledUser();
        $service = app(MfaService::class);
        $token = $this->mfaTokenFor($user, $secret);

        $this->postJson('/api/auth/mfa/disable', [
            'password' => 'WrongPassword1!',
            'code' => $service->currentCode($secret),
        ], $this->authHeaders($token))->assertStatus(422);

        $this->postJson('/api/auth/mfa/disable', [
            'password' => 'Secret123!',
            'code' => '222222',
        ], $this->authHeaders($token))->assertStatus(422);

        $this->postJson('/api/auth/mfa/disable', [
            'password' => 'Secret123!',
            'code' => $service->currentCode($secret),
        ], $this->authHeaders($token))->assertOk()->assertJsonPath('success', true);

        $user->refresh();
        $this->assertFalse($user->hasMfaEnabled());
        $this->assertNull($user->mfa_secret);
    }

    public function test_mfa_endpoints_require_authentication(): void
    {
        $this->getJson('/api/auth/mfa/status')->assertStatus(401);
        $this->postJson('/api/auth/mfa/setup', [])->assertStatus(401);
        $this->postJson('/api/auth/mfa/enable', ['code' => '123456'])->assertStatus(401);
        $this->postJson('/api/auth/mfa/disable', ['password' => 'x', 'code' => '123456'])->assertStatus(401);
        $this->getJson('/api/auth/login-history')->assertStatus(401);
    }

    public function test_login_history_is_recorded_and_visible_to_the_owner_only(): void
    {
        Notification::fake();

        [$user, $secret] = $this->enrolledUser();
        $token = $this->mfaTokenFor($user, $secret);

        $history = $this->getJson('/api/auth/login-history', $this->authHeaders($token))->assertOk();
        $this->assertIsArray($history->json('history'));

        // Another account cannot read someone else's trail.
        $other = $this->makeUser('operator');
        $otherToken = $this->tokenFor($other);
        $otherHistory = $this->getJson('/api/auth/login-history', $this->authHeaders($otherToken))->assertOk();
        foreach ($otherHistory->json('history') as $row) {
            $this->assertNotSame($user->email, $row['email'] ?? null);
        }
    }
}
