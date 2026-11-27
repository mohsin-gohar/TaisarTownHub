<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\MfaService;
use App\Support\MfaVerification;

/**
 * Regression coverage for token-scoped MFA verification.
 * A user-wide "verified" stamp allowed any other token of that user to bypass
 * the second factor for 24 hours.
 */
class MfaEnforcementTest extends EnterpriseTestCase
{
    private function enroll(User $user, MfaService $service): array
    {
        $secret = $service->generateSecret();
        $codes = $service->generateRecoveryCodes();
        $user->forceFill([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => $service->hashRecoveryCodes($codes),
            'mfa_enabled_at' => now(),
        ])->save();

        return [$secret, $codes];
    }

    private function completeMfa(User $user, MfaService $service, string $secret): string
    {
        $challenge = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
        ])->assertOk()->json('challenge');

        return $this->postJson('/api/auth/mfa/verify', [
            'challenge' => $challenge,
            'code' => $service->currentCode($secret),
        ])->assertOk()->json('token');
    }

    public function test_recovery_codes_are_single_use_across_separate_mfa_challenges(): void
    {
        $user = $this->makeUser('operator', ['email' => 'recovery@example.test']);
        $service = app(MfaService::class);
        [, $codes] = $this->enroll($user, $service);
        $recoveryCode = $codes[0];

        $challenge = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
        ])->assertOk()->json('challenge');

        $this->postJson('/api/auth/mfa/verify', [
            'challenge' => $challenge,
            'code' => $recoveryCode,
        ])->assertOk()->assertJsonPath('mfa_used', true);

        $this->assertCount(7, $user->fresh()->mfa_recovery_codes);

        $secondChallenge = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
        ])->assertOk()->json('challenge');

        $this->postJson('/api/auth/mfa/verify', [
            'challenge' => $secondChallenge,
            'code' => $recoveryCode,
        ])->assertStatus(422);
        $this->assertCount(7, $user->fresh()->mfa_recovery_codes);
    }

    public function test_mfa_challenge_cannot_be_reused_after_successful_verification(): void
    {
        $user = $this->makeUser('operator', ['email' => 'challenge-once@example.test']);
        $service = app(MfaService::class);
        [$secret] = $this->enroll($user, $service);

        $challenge = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
        ])->assertOk()->json('challenge');

        $this->postJson('/api/auth/mfa/verify', [
            'challenge' => $challenge,
            'code' => $service->currentCode($secret),
        ])->assertOk();

        $this->postJson('/api/auth/mfa/verify', [
            'challenge' => $challenge,
            'code' => $service->currentCode($secret),
        ])->assertUnauthorized();
    }

    public function test_mfa_middleware_blocks_a_token_issued_before_enrolment(): void
    {
        $user = $this->makeUser('operator');
        $oldToken = $this->tokenFor($user);
        $this->getJson('/api/auth/me', $this->authHeaders($oldToken))->assertOk();

        $this->enroll($user, app(MfaService::class));

        $this->asGuest();
        $this->getJson('/api/auth/me', $this->authHeaders($oldToken))
            ->assertForbidden()
            ->assertJsonPath('mfa_required', true);
    }

    public function test_verification_does_not_authorize_another_token_of_the_same_user(): void
    {
        $user = $this->makeUser('operator');
        $service = app(MfaService::class);
        [$secret] = $this->enroll($user, $service);
        $preEnrolmentToken = $this->tokenFor($user);

        $verifiedToken = $this->completeMfa($user, $service, $secret);

        $this->asGuest();
        $this->getJson('/api/auth/me', $this->authHeaders($verifiedToken))->assertOk();
        $this->asGuest();
        $this->getJson('/api/auth/me', $this->authHeaders($preEnrolmentToken))->assertForbidden();
    }

    public function test_logout_clears_only_the_current_tokens_verification(): void
    {
        $user = $this->makeUser('operator');
        $service = app(MfaService::class);
        [$secret] = $this->enroll($user, $service);

        $tokenA = $this->completeMfa($user, $service, $secret);
        $tokenB = $this->completeMfa($user, $service, $secret);
        $tokenAId = (int) \Illuminate\Support\Facades\DB::table('personal_access_tokens')
            ->where('tokenable_id', $user->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->value('id');

        $this->asGuest();
        $this->getJson('/api/auth/me', $this->authHeaders($tokenB))->assertOk();
        $this->asGuest();
        $this->postJson('/api/auth/logout', [], $this->authHeaders($tokenA))->assertOk();

        $this->assertFalse(MfaVerification::isVerified((int) $user->id, $tokenAId));
    }

    public function test_enabling_mfa_keeps_the_current_session_working(): void
    {
        $user = $this->makeUser('operator');
        $token = $this->tokenFor($user);
        $service = app(MfaService::class);

        $secret = $this->postJson('/api/auth/mfa/setup', [], $this->authHeaders($token))
            ->assertOk()->json('secret');
        $this->postJson('/api/auth/mfa/enable', [
            'code' => $service->currentCode($secret),
        ], $this->authHeaders($token))->assertOk();

        $this->asGuest();
        $this->getJson('/api/auth/me', $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_global_mfa_policy_allows_required_agency_owner_to_enroll_mfa(): void
    {
        \Illuminate\Support\Facades\DB::table('system_settings')->updateOrInsert(
            ['key' => 'security.mfa_required_for_admins'],
            [
                'value' => json_encode(true),
                'group' => 'security',
                'description' => 'test',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        [$owner] = $this->agencyUser(['agency.view'], 'agency_owner');
        $token = $this->tokenFor($owner);
        $headers = $this->authHeaders($token);
        $service = app(MfaService::class);

        $this->getJson('/api/auth/mfa/status', $headers)
            ->assertOk()
            ->assertJsonPath('enabled', false);

        $secret = $this->postJson('/api/auth/mfa/setup', [], $headers)
            ->assertOk()
            ->json('secret');

        $this->postJson('/api/auth/mfa/enable', [
            'code' => $service->currentCode($secret),
        ], $headers)->assertOk();

        $this->asGuest();
        $this->getJson('/api/auth/me', $this->authHeaders($token))->assertOk();
    }

    public function test_global_mfa_policy_uses_active_agency_role(): void
    {
        \Illuminate\Support\Facades\DB::table('system_settings')->updateOrInsert(
            ['key' => 'security.mfa_required_for_admins'],
            [
                'value' => json_encode(true),
                'group' => 'security',
                'description' => 'test',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        [$owner] = $this->agencyUser(['agency.view'], 'agency_owner');
        [$manager] = $this->agencyUser(['agency.view'], 'agency_manager');
        [$viewer] = $this->agencyUser(['agency.view'], 'viewer');

        foreach ([$owner, $manager] as $privileged) {
            $this->asGuest();
            $this->getJson('/api/auth/me', $this->authHeaders($this->tokenFor($privileged)))
                ->assertForbidden()
                ->assertJsonPath('mfa_required', true);
        }

        $this->asGuest();
        $this->getJson('/api/auth/me', $this->authHeaders($this->tokenFor($viewer)))->assertOk();
    }

    public function test_global_mfa_policy_covers_custom_agency_roles_with_elevated_grants(): void
    {
        \Illuminate\Support\Facades\DB::table('system_settings')->updateOrInsert(
            ['key' => 'security.mfa_required_for_admins'],
            [
                'value' => json_encode(true),
                'group' => 'security',
                'description' => 'test',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        [$privileged] = $this->agencyUser(['finance.manage_payments'], 'finance_editor');
        [$plain] = $this->agencyUser(['finance.view'], 'viewer');

        $this->asGuest();
        $this->getJson('/api/auth/me', $this->authHeaders($this->tokenFor($privileged)))
            ->assertForbidden()
            ->assertJsonPath('mfa_required', true);

        $this->asGuest();
        $this->getJson('/api/auth/me', $this->authHeaders($this->tokenFor($plain)))->assertOk();
    }

    public function test_disabling_mfa_clears_every_verification_stamp(): void
    {
        $user = $this->makeUser('operator');
        $service = app(MfaService::class);
        [$secret] = $this->enroll($user, $service);
        $token = $this->completeMfa($user, $service, $secret);

        $tokenId = (int) \Illuminate\Support\Facades\DB::table('personal_access_tokens')
            ->where('tokenable_id', $user->id)->value('id');
        $this->assertTrue(MfaVerification::isVerified((int) $user->id, $tokenId));

        $this->postJson('/api/auth/mfa/disable', [
            'password' => 'Secret123!',
            'code' => $service->currentCode($secret),
        ], $this->authHeaders($token))->assertOk();

        $this->assertFalse(MfaVerification::isVerified((int) $user->id, $tokenId));
    }
}
