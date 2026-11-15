<?php

namespace Tests\Feature;

use App\Models\LoginHistory;
use App\Notifications\SuspiciousLoginDetected;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * Phase 6 — login history and suspicious-login monitoring.
 *
 * Every sign-in attempt (successful or not) is written to an append-only
 * trail. A successful sign-in from an IP address or device the account has
 * never used before is flagged and the owner is notified.
 */
class LoginHistoryTest extends EnterpriseTestCase
{
    public function test_successful_sign_in_is_recorded(): void
    {
        $user = $this->makeUser('operator');

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
        ])->assertOk();

        $row = LoginHistory::where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($row);
        $this->assertTrue($row->success);
        $this->assertFalse($row->mfa_used);
        $this->assertFalse($row->suspicious);
        $this->assertSame($user->email, $row->email);
    }

    public function test_failed_sign_in_is_recorded_without_leaking_the_account_state(): void
    {
        $user = $this->makeUser('operator');

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'TotallyWrong1!',
        ])->assertStatus(422);

        $row = LoginHistory::where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($row);
        $this->assertFalse($row->success);
        $this->assertSame('invalid_password', $row->reason);
    }

    public function test_unknown_account_attempt_is_recorded_without_a_user_id(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'ghost@example.test',
            'password' => 'Whatever123!',
        ])->assertStatus(422);

        $row = LoginHistory::where('email', 'ghost@example.test')->latest('id')->first();
        $this->assertNotNull($row);
        $this->assertNull($row->user_id);
        $this->assertFalse($row->success);
        $this->assertSame('unknown_account', $row->reason);
    }

    public function test_sign_in_from_a_new_ip_address_is_flagged_and_notified(): void
    {
        Notification::fake();

        $user = $this->makeUser('operator');

        // First sign-in from 10.0.0.1 — known device from now on.
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
        ], ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_USER_AGENT' => 'AuditBrowser/1.0'])->assertOk();

        $this->assertFalse(LoginHistory::where('user_id', $user->id)->latest('id')->first()->suspicious);

        // Second sign-in from a different IP address must be flagged.
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
        ], ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_USER_AGENT' => 'AuditBrowser/1.0'])->assertOk();

        $row = LoginHistory::where('user_id', $user->id)->latest('id')->first();
        $this->assertTrue($row->suspicious);
        $this->assertSame('new_ip_address', $row->reason);

        Notification::assertSentTo($user, SuspiciousLoginDetected::class);
    }

    public function test_password_reset_notifies_the_account_owner(): void
    {
        Notification::fake();

        $user = $this->makeUser('operator');
        $token = \Illuminate\Support\Facades\Password::createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'BrandNewPass1!',
            'password_confirmation' => 'BrandNewPass1!',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('BrandNewPass1!', $user->password));

        Notification::assertSentTo(
            $user,
            \App\Notifications\PasswordChangedNotification::class,
        );
    }
}
