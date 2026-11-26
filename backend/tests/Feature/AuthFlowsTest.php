<?php

namespace Tests\Feature;

use App\Models\EmailVerificationToken;
use App\Models\User;
use App\Notifications\ResetPasswordLink;
use App\Notifications\VerifyEmailLink;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\EnterpriseTestCase;

/**
 * G-4 — authentication flows: password reset (broker-backed, enumeration
 * safe, revokes all sessions) and email verification (hashed tokens, 60-min
 * expiry, tracked but not login-blocking).
 */
class AuthFlowsTest extends EnterpriseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Registration creates an agency_owner membership against this FK.
        \App\Models\Role::create([
            'id' => 'role_agency_owner',
            'scope' => 'agency',
            'key' => 'agency_owner',
            'name' => 'Agency Owner',
            'is_system' => true,
            'permissions' => ['agency.view', 'properties.view', 'documents.view'],
        ]);
    }

    /** Workspace access requires an active agency membership. */
    private function memberUser(string $roleKey, array $permissions = []): User
    {
        $agency = $this->makeAgency('Verify Agency ' . \Illuminate\Support\Str::random(4));
        $role = $this->makeRole('agency', $roleKey, $permissions);
        $user = $this->makeUser('agent');
        $this->makeMember($user, $agency, $role);

        return $user;
    }
    /* ------------------- Password reset ------------------- */

    public function test_forgot_password_is_enumeration_safe(): void
    {
        Notification::fake();
        $this->asGuest();

        // Unknown email: identical generic response, no mail.
        $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.test'])
            ->assertOk()
            ->assertJsonPath('success', true);

        Notification::assertNothingSent();

        // Known email: generic response, reset mail sent.
        $user = $this->makeUser('agent', ['email' => 'resetme@example.test']);

        $this->postJson('/api/auth/forgot-password', ['email' => 'resetme@example.test'])
            ->assertOk()
            ->assertJsonPath('success', true);

        Notification::assertSentTo($user, ResetPasswordLink::class);
    }

    public function test_reset_password_rotates_credentials_and_revokes_all_sessions(): void
    {
        Notification::fake();
        $user = $this->makeUser('agent', [
            'email' => 'resetflow@example.test',
            'password' => Hash::make('OldSecret123!'),
        ]);

        // A pre-existing session that must be invalidated by the reset.
        $staleToken = $this->tokenFor($user);
        $this->asGuest();

        $this->postJson('/api/auth/forgot-password', ['email' => 'resetflow@example.test'])->assertOk();

        $notification = Notification::sent($user, ResetPasswordLink::class)->first();
        $this->assertNotNull($notification, 'reset mail was not sent');

        // The mail link must point at the SPA frontend.
        $mailBody = $notification->toMail($user)->render();
        $this->assertStringContainsString('/reset-password?token=', $mailBody);

        $this->asGuest();
        $this->postJson('/api/auth/reset-password', [
            'token' => $notification->token,
            'email' => 'resetflow@example.test',
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ])->assertOk()->assertJsonPath('success', true);

        // New password works, old password does not.
        $this->asGuest();
        $this->postJson('/api/auth/login', [
            'email' => 'resetflow@example.test',
            'password' => 'NewSecret123!',
        ])->assertOk();

        $this->asGuest();
        $this->postJson('/api/auth/login', [
            'email' => 'resetflow@example.test',
            'password' => 'OldSecret123!',
        ])->assertStatus(422);

        // Every previously issued token was revoked (reset = global logout).
        $this->asGuest();
        $this->withHeaders($this->authHeaders($staleToken))
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_reset_with_invalid_token_is_rejected(): void
    {
        Notification::fake();
        $user = $this->makeUser('agent', [
            'email' => 'badtoken@example.test',
            'password' => Hash::make('OldSecret123!'),
        ]);

        $this->asGuest();
        $this->postJson('/api/auth/forgot-password', ['email' => 'badtoken@example.test'])->assertOk();

        $notification = Notification::sent($user, ResetPasswordLink::class)->first();

        $this->asGuest();
        $this->postJson('/api/auth/reset-password', [
            'token' => str_repeat('9', 64), // wrong token, same length class
            'email' => 'badtoken@example.test',
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ])->assertStatus(422)->assertJsonPath('success', false);

        $user->refresh();
        $this->assertTrue(Hash::check('OldSecret123!', $user->password));
    }

    /* ------------------- Email verification ------------------- */

    public function test_auth_mails_are_queueable(): void
    {
        // G-9: mail dispatch must never block request handling.
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, new VerifyEmailLink());
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, new ResetPasswordLink('token'));
    }

    public function test_registration_issues_a_verification_link_that_verifies(): void
    {
        Notification::fake();
        $this->asGuest();

        $response = $this->postJson('/api/agency/register', [
            'name' => 'Verify Flow Realty',
            'contactName' => 'Verify Flow Owner',
            'email' => 'verifyflow@example.test',
            'password' => 'Secret123!',
            'phone' => '0300-5550001',
        ])->assertStatus(201);

        $user = User::where('email', 'verifyflow@example.test')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at, 'fresh registration is not yet verified');

        $notification = Notification::sent($user, VerifyEmailLink::class)->first();
        $this->assertNotNull($notification, 'verification mail was not sent');
        $this->assertStringContainsString('/verify-email?email=', $notification->toMail($user)->render());

        // The stored row holds only the hash, never the plain token.
        $row = EmailVerificationToken::where('email', 'verifyflow@example.test')->first();
        $this->assertNotNull($row);
        $this->assertSame(hash('sha256', $notification->plainToken), $row->token_hash);
        $this->assertStringNotContainsString($notification->plainToken, json_encode($row->toArray()));

        // Verification consumes the token and stamps the user.
        $this->asGuest();
        $this->getJson('/api/auth/email/verify?email=verifyflow@example.test&token=' . $notification->plainToken)
            ->assertOk()
            ->assertJsonPath('already_verified', false);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull(EmailVerificationToken::where('email', 'verifyflow@example.test')->first());
    }

    public function test_verification_rejects_wrong_and_expired_tokens(): void
    {
        Notification::fake();
        $plain = VerifyEmailLink::issueFor('expired@example.test')->plainToken;
        EmailVerificationToken::where('email', 'expired@example.test')
            ->update(['created_at' => now()->subMinutes(90)]);

        $this->asGuest();
        $this->getJson('/api/auth/email/verify?email=expired@example.test&token=' . $plain)
            ->assertStatus(400)
            ->assertJsonPath('success', false);

        // Expired token must be consumed (single use).
        $this->assertNull(EmailVerificationToken::where('email', 'expired@example.test')->first());

        // Wrong token against a live row.
        $live = VerifyEmailLink::issueFor('wrongtok@example.test')->plainToken;
        $this->asGuest();
        $this->getJson('/api/auth/email/verify?email=wrongtok@example.test&token=' . str_repeat('f', 48))
            ->assertStatus(400);
        $this->assertNotNull(EmailVerificationToken::where('email', 'wrongtok@example.test')->first());
        $this->assertNotSame($live, str_repeat('f', 48));
    }

    public function test_resend_verification_for_an_already_verified_account_is_a_noop(): void
    {
        Notification::fake();
        $user = $this->memberUser('verified-staff');
        $user->forceFill(['email' => 'already@example.test', 'email_verified_at' => now()])->save();
        $token = $this->tokenFor($user);

        $this->postJson('/api/auth/email/resend', [], $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('already_verified', true);

        Notification::assertNothingSentTo($user, VerifyEmailLink::class);
    }

    public function test_resend_issues_a_fresh_token_for_unverified_accounts(): void
    {
        Notification::fake();
        $user = $this->memberUser('resend-staff');
        $user->forceFill(['email' => 'resendme@example.test'])->save();
        $token = $this->tokenFor($user);

        $this->postJson('/api/auth/email/resend', [], $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('already_verified', false);

        Notification::assertSentTo($user, VerifyEmailLink::class);
        $this->assertNotNull(EmailVerificationToken::where('email', 'resendme@example.test')->first());
    }
    public function test_agency_membership_never_receives_legacy_platform_permissions_from_user_role(): void
    {
        $user = $this->memberUser('viewer', ['agency.view', 'properties.view']);
        // Legacy base roles are not the agency permission source once a user
        // has an active membership. This guards stale manager/admin role data.
        $user->forceFill(['role' => 'manager'])->save();
        $agencyId = (string) $user->memberships()->value('agency_id');

        $response = $this->getJson('/api/auth/me', array_merge(
            $this->authHeaders($this->tokenFor($user)),
            ['X-Agency-Id' => $agencyId]
        ))->assertOk();

        $permissions = $response->json('user.permissions');
        $this->assertFalse($response->json('user.is_platform'));
        $this->assertContains('view_properties', $permissions);
        $this->assertNotContains('view_reports', $permissions);
        $this->assertNotContains('create_transactions', $permissions);
        $this->assertNotContains('manage_users', $permissions);
    }

}
