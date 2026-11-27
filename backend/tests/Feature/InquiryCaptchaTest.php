<?php

namespace Tests\Feature;

use App\Models\BusinessRecord;
use App\Models\Inquiry;
use App\Support\InquiryCaptcha;

/**
 * G-11: bot protection on the public inquiry endpoint.
 *
 * The challenge is a signed, single-use, expiring math question. Submissions
 * without a valid answer must be rejected, solved tokens must not be
 * replayable, tampering with the token must be detected, and honeypot fills
 * must be silently discarded (fake 201, nothing stored).
 */
class InquiryCaptchaTest extends EnterpriseTestCase
{
    private function solveCaptcha(): array
    {
        $challenge = $this->getJson('/api/inquiries/captcha')->assertStatus(200)->json();
        $this->assertArrayHasKey('question', $challenge);
        $this->assertArrayHasKey('token', $challenge);
        $this->assertArrayHasKey('expiresAt', $challenge);
        preg_match('/(\d+)\s*\+\s*(\d+)/', (string) $challenge['question'], $m);

        return ['captchaToken' => $challenge['token'], 'captchaAnswer' => (string) ((int) $m[1] + (int) $m[2])];
    }

    private function basePayload(): array
    {
        return [
            'fullName' => 'Captcha Buyer',
            'phone' => '03009998887',
            'message' => 'I am a human and can add single-digit numbers.',
        ];
    }

    public function test_challenge_endpoint_returns_a_signed_expiring_question(): void
    {
        $challenge = $this->getJson('/api/inquiries/captcha')
            ->assertStatus(200)
            ->json();

        $this->assertMatchesRegularExpression('/\d+\s*\+\s*\d+/', (string) $challenge['question']);
        $this->assertNotSame('', $challenge['token']);
        $this->assertStringContainsString('.', (string) $challenge['token']);

        // Expiry is announced as ~10 minutes ahead (server time, ISO 8601).
        $expiry = strtotime((string) $challenge['expiresAt']);
        $this->assertGreaterThan(time(), $expiry);
        $this->assertLessThanOrEqual(time() + InquiryCaptcha::LIFETIME_SECONDS + 5, $expiry);
    }

    public function test_valid_submission_with_a_solved_captcha_is_accepted(): void
    {
        $this->asGuest();

        $this->postJson('/api/inquiries', $this->basePayload() + $this->solveCaptcha())
            ->assertStatus(201);

        $this->assertDatabaseHas('inquiries', ['phone' => '03009998887']);
    }


    public function test_failed_relational_inquiry_insert_rolls_back_legacy_mirror(): void
    {
        $this->asGuest();

        Inquiry::creating(function (): void {
            throw new \RuntimeException('Simulated inquiry persistence failure.');
        });

        $this->postJson('/api/inquiries', $this->basePayload() + $this->solveCaptcha())
            ->assertServerError();

        $this->assertDatabaseCount('inquiries', 0);
        $this->assertDatabaseCount('business_records', 0);
    }

    public function test_submission_without_captcha_fields_is_rejected(): void
    {
        $this->asGuest();

        $this->postJson('/api/inquiries', $this->basePayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['captchaToken', 'captchaAnswer']);

        $this->assertDatabaseMissing('inquiries', ['phone' => '03009998887']);
    }

    public function test_wrong_answer_is_rejected_with_a_machine_readable_code(): void
    {
        $this->asGuest();

        $solved = $this->solveCaptcha();
        $solved['captchaAnswer'] = (string) (((int) $solved['captchaAnswer']) + 1);

        $this->postJson('/api/inquiries', $this->basePayload() + $solved)
            ->assertStatus(422)
            ->assertJsonPath('code', 'captcha_failed');

        $this->assertDatabaseMissing('inquiries', ['phone' => '03009998887']);
    }

    public function test_a_solved_token_cannot_be_replayed(): void
    {
        $this->asGuest();

        $solved = $this->solveCaptcha();

        // First submission consumes the token.
        $this->postJson('/api/inquiries', [
            'fullName' => 'First Human',
            'phone' => '03009998881',
            'message' => 'First genuine submission.',
        ] + $solved)->assertStatus(201);

        // Replay with the SAME solved token must fail even though the answer
        // is still correct.
        $this->postJson('/api/inquiries', [
            'fullName' => 'Replay Bot',
            'phone' => '03009998882',
            'message' => 'Replaying a solved token.',
        ] + $solved)
            ->assertStatus(422)
            ->assertJsonPath('code', 'captcha_failed');

        $this->assertDatabaseMissing('inquiries', ['phone' => '03009998882']);
    }

    public function test_tampered_token_signature_is_rejected(): void
    {
        $this->asGuest();

        $solved = $this->solveCaptcha();
        $solved['captchaToken'] = substr($solved['captchaToken'], 0, -2) . 'zz';

        $this->postJson('/api/inquiries', $this->basePayload() + $solved)
            ->assertStatus(422)
            ->assertJsonPath('code', 'captcha_failed');

        $this->assertDatabaseMissing('inquiries', ['phone' => '03009998887']);
    }

    public function test_expired_token_is_rejected(): void
    {
        $this->asGuest();

        // Forge an otherwise-valid token whose expiry is in the past, using
        // the same construction as InquiryCaptcha (test-only; the app key is
        // fixed in phpunit.xml).
        $payload = base64_encode((string) json_encode([
            'a' => 3,
            'b' => 4,
            'n' => bin2hex(random_bytes(16)),
            'e' => time() - 10,
        ]));
        $signature = hash_hmac('sha256', $payload, (string) config('app.key'));

        $this->postJson('/api/inquiries', $this->basePayload() + [
            'captchaToken' => $payload . '.' . $signature,
            'captchaAnswer' => '7',
        ])->assertStatus(422)->assertJsonPath('code', 'captcha_failed');

        $this->assertDatabaseMissing('inquiries', ['phone' => '03009998887']);
    }

    public function test_honeypot_fill_returns_fake_success_and_stores_nothing(): void
    {
        $this->asGuest();

        $this->postJson('/api/inquiries', $this->basePayload() + $this->solveCaptcha() + [
            'companyWebsite' => 'http://spam.example',
        ])->assertStatus(201);

        // Convincing 201 for the bot, zero rows anywhere for the platform.
        $this->assertDatabaseMissing('inquiries', ['phone' => '03009998887']);
        $this->assertSame(0, Inquiry::where('phone', '03009998887')->count());
    }

    public function test_captcha_failure_does_not_burn_the_challenge_for_humans(): void
    {
        $this->asGuest();

        $challenge = $this->getJson('/api/inquiries/captcha')->assertStatus(200)->json();
        preg_match('/(\d+)\s*\+\s*(\d+)/', (string) $challenge['question'], $m);
        $token = $challenge['token'];
        $correct = (string) ((int) $m[1] + (int) $m[2]);

        // A typo (wrong answer) is rejected...
        $this->postJson('/api/inquiries', $this->basePayload() + [
            'captchaToken' => $token,
            'captchaAnswer' => (string) (((int) $correct) + 1),
        ])->assertStatus(422);

        // ...but the same token still accepts the corrected answer, so a
        // human with a typo is not forced into a loop.
        $this->postJson('/api/inquiries', $this->basePayload() + [
            'captchaToken' => $token,
            'captchaAnswer' => $correct,
        ])->assertStatus(201);

        $this->assertDatabaseHas('inquiries', ['phone' => '03009998887']);
    }
}
