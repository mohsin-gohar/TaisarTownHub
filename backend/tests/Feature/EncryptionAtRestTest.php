<?php

namespace Tests\Feature;

use App\Models\Dealer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\EnterpriseTestCase;

/**
 * G-3 — encryption at rest for the full national ID (`dealers.cnic`).
 *
 * The value is AES-256 encrypted via Laravel's `encrypted` cast, never
 * leaves the API (`$hidden`), and pre-existing plaintext rows were migrated
 * by 2026_11_13_000001_encrypt_dealer_cnic_at_rest.php.
 */
class EncryptionAtRestTest extends EnterpriseTestCase
{
    public function test_full_cnic_is_encrypted_in_the_database_and_decrypts_in_the_model(): void
    {
        $plain = '42101-8815027-3';

        $dealer = Dealer::create([
            'id' => 'dlr_' . Str::random(10),
            'fullName' => 'Encrypted Dealer',
            'phone' => '0300-0000001',
            'cnic' => $plain,
            'cnicMasked' => '42101-*******-3',
        ]);

        $raw = DB::table('dealers')->where('id', $dealer->id)->value('cnic');

        $this->assertNotSame($plain, $raw, 'full CNIC must not be stored as plaintext');
        $this->assertStringStartsWith('eyJ', (string) $raw, 'Laravel encrypted payloads are base64 JSON');

        $fresh = Dealer::find($dealer->id);
        $this->assertSame($plain, $fresh->cnic);
        $this->assertSame('42101-*******-3', $fresh->cnicMasked);
    }

    public function test_public_dealer_directory_never_exposes_the_full_cnic(): void
    {
        Dealer::create([
            'id' => 'dlr_' . Str::random(10),
            'fullName' => 'Public Dealer',
            'phone' => '0300-0000002',
            'cnic' => '35202-3306745-5',
            'cnicMasked' => '35202-*******-5',
        ]);

        $this->asGuest();
        $response = $this->getJson('/api/dealers')->assertOk();

        // The hidden attribute must never appear anywhere in the payload.
        $this->assertStringNotContainsString('3306745', $response->getContent());
        $this->assertStringNotContainsString('"cnic"', $response->getContent());
    }

    public function test_store_via_api_masks_cnic_and_keeps_full_value_encrypted(): void
    {
        [$user] = $this->agencyUser(['manage_dealers']);
        $token = $this->tokenFor($user);

        $response = $this->postJson('/api/dealers', [
            'id' => 'dlr_' . Str::random(10),
            'fullName' => 'API Masked Dealer',
            'phone' => '0300-0000003',
            'cnic' => '42201-4429816-7',
        ], $this->authHeaders($token))->assertStatus(201);

        $this->assertNull($response->json('cnic'));
        $this->assertSame('42201****8167', $response->json('cnicMasked'));

        $raw = DB::table('dealers')->where('fullName', 'API Masked Dealer')->value('cnic');
        $this->assertStringStartsWith('eyJ', (string) $raw);
        $this->assertStringNotContainsString('4429816', (string) $raw);

        $stored = Dealer::where('fullName', 'API Masked Dealer')->first();
        $this->assertSame('42201-4429816-7', $stored->cnic);
    }
}
