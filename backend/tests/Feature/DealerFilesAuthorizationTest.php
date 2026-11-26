<?php

namespace Tests\Feature;

use App\Models\AgencySubscription;
use App\Models\Dealer;
use App\Models\DealerFile;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Str;

/**
 * Dealer-file legacy endpoints must not bypass permissions, tenancy, quotas
 * or buyer-data protection just because a token is present.
 */
class DealerFilesAuthorizationTest extends EnterpriseTestCase
{
    private function makeDealer(string $name): Dealer
    {
        return Dealer::create([
            'id' => 'dlr_' . Str::random(8),
            'fullName' => $name,
            'phone' => '0300-0000000',
        ]);
    }

    private function dealerUser(array $permissions, Dealer $dealer, $agency = null): array
    {
        $user = $this->makeUser('agent', ['dealer_id' => $dealer->id]);
        $agency = $agency ?? $this->makeAgency('Dealer Files Agency');
        $this->makeMember($user, $agency, $this->makeRole('agency', 'dealer', $permissions));

        return [$user, $agency];
    }

    private function uploadPayload(): array
    {
        return [
            'title' => 'Registry File',
            'type' => 'pdf',
            'url' => 'https://example.test/file.pdf',
            'folder' => 'Documents',
            'file_size_kb' => 100,
        ];
    }

    public function test_upload_requires_upload_permission(): void
    {
        [$user] = $this->dealerUser(['documents.view'], $this->makeDealer('No Upload'));

        $this->postJson('/api/dealer-files', $this->uploadPayload(), $this->authHeaders($this->tokenFor($user)))
            ->assertForbidden();
        $this->assertSame(0, DealerFile::count());
    }

    public function test_sale_requires_transaction_permission(): void
    {
        $dealer = $this->makeDealer('No Sale');
        [$user] = $this->dealerUser(['documents.view'], $dealer);
        $file = DealerFile::create([
            'title' => 'Plot File', 'type' => 'image', 'url' => 'https://example.test/f.jpg',
            'folder' => 'Properties', 'file_size_kb' => 100,
            'dealer_id' => $dealer->id, 'uploaded_by' => $dealer->id, 'status' => 'available',
        ]);

        $this->postJson("/api/dealer-files/{$file->id}/sell", [
            'selling_price_kbr' => 9000, 'buyer_name' => 'Buyer', 'buyer_phone' => '0300-1',
        ], $this->authHeaders($this->tokenFor($user)))->assertForbidden();

        $this->assertSame('available', $file->fresh()->status);
    }

    public function test_account_without_dealer_identity_is_rejected(): void
    {
        [$user] = $this->agencyUser(['documents.view', 'documents.upload'], 'manager');

        $this->postJson('/api/dealer-files', $this->uploadPayload(), $this->authHeaders($this->tokenFor($user)))
            ->assertForbidden();
    }

    public function test_dealer_from_another_agency_cannot_act_in_this_agency(): void
    {
        [, $agencyA] = $this->agencyUser(['documents.view', 'documents.upload'], 'manager');
        $dealerB = $this->makeDealer('Agency B Dealer');
        [$userB] = $this->dealerUser(['documents.view', 'documents.upload'], $dealerB);

        // Give user B a valid token for A's agency context while their dealer
        // belongs elsewhere — the server must reject the mismatched identity.
        $headers = $this->authHeaders($this->tokenFor($userB));
        $headers['X-Agency-Id'] = $agencyA->id;

        $this->postJson('/api/dealer-files', $this->uploadPayload(), $headers)->assertForbidden();
    }

    public function test_sales_report_cannot_read_another_agencys_dealer(): void
    {
        $dealerB = $this->makeDealer('Foreign Sales Dealer');
        [$userB, $agencyB] = $this->dealerUser(['finance.view', 'documents.view'], $dealerB);
        [$userA] = $this->agencyUser(['finance.view', 'documents.view'], 'manager');
        $file = DealerFile::create([
            'title' => 'Sold File', 'type' => 'image', 'url' => 'https://example.test/s.jpg',
            'folder' => 'Properties', 'file_size_kb' => 100,
            'dealer_id' => $dealerB->id, 'uploaded_by' => $dealerB->id,
            'sold_by_dealer_id' => $dealerB->id, 'status' => 'sold', 'profit_kbr' => 1000,
        ]);

        $this->getJson("/api/dealers/{$dealerB->id}/sales-report", $this->authHeaders($this->tokenFor($userA)))
            ->assertForbidden();
        $this->getJson("/api/dealers/{$dealerB->id}/sales-report", $this->authHeaders($this->tokenFor($userB)))
            ->assertOk();
    }

    public function test_buyer_pii_is_hidden_without_sensitive_clearance(): void
    {
        $dealer = $this->makeDealer('PII Dealer');
        [$user] = $this->dealerUser(['documents.view', 'finance.create_transaction'], $dealer);
        $file = DealerFile::create([
            'title' => 'PII File', 'type' => 'image', 'url' => 'https://example.test/p.jpg',
            'folder' => 'Properties', 'file_size_kb' => 100,
            'dealer_id' => $dealer->id, 'uploaded_by' => $dealer->id, 'status' => 'available',
        ]);

        $this->postJson("/api/dealer-files/{$file->id}/sell", [
            'selling_price_kbr' => 9000, 'buyer_name' => 'Secret Buyer', 'buyer_phone' => '0300-9999999',
        ], $this->authHeaders($this->tokenFor($user)))->assertOk();

        $report = $this->getJson("/api/dealers/{$dealer->id}/sales-report", $this->authHeaders($this->tokenFor($user)))
            ->assertOk()->json('data.files');
        $this->assertNotEmpty($report);
        $this->assertArrayNotHasKey('buyer_name', $report[0]);
        $this->assertArrayNotHasKey('buyer_phone', $report[0]);
        $this->assertArrayHasKey('profit_kbr', $report[0]);
    }

    public function test_short_buyer_cnic_is_fully_masked_on_sale(): void
    {
        // Regression: 7-8 digit buyer CNIC produced a negative str_repeat()
        // length in the mask helper (ValueError/500 instead of a sale).
        $dealer = $this->makeDealer('Short CNIC Buyer');
        [$user] = $this->dealerUser(['documents.view', 'finance.create_transaction'], $dealer);
        $file = DealerFile::create([
            'title' => 'Short CNIC File', 'type' => 'image', 'url' => 'https://example.test/s.jpg',
            'folder' => 'Properties', 'file_size_kb' => 100,
            'dealer_id' => $dealer->id, 'uploaded_by' => $dealer->id, 'status' => 'available',
        ]);

        $this->postJson("/api/dealer-files/{$file->id}/sell", [
            'selling_price_kbr' => 9000,
            'buyer_name' => 'Short CNIC Buyer',
            'buyer_phone' => '0300-1234567',
            'buyer_cnic' => '1234567',
        ], $this->authHeaders($this->tokenFor($user)))->assertOk();

        $this->assertSame('*******', $file->fresh()->buyer_cnic);
    }

    public function test_dealer_file_quota_blocks_uploads_past_the_limit(): void
    {
        [$user, $agency] = $this->agencyUser(['documents.view', 'documents.upload'], 'manager');
        $dealer = $this->makeDealer('Quota Dealer');
        $dealerUser = $this->makeUser('agent', ['dealer_id' => $dealer->id]);
        $this->makeMember($dealerUser, $agency, $this->makeRole('agency', 'agent', ['documents.upload']));

        $plan = SubscriptionPlan::create([
            'key' => 'quota-' . Str::random(6), 'name' => 'Quota', 'description' => 'test',
            'price' => 1000, 'currency' => 'PKR', 'interval' => 'month',
            'limits' => ['dealer_files' => 1], 'features' => [],
            'is_active' => true, 'is_default' => false, 'sort_order' => 1,
        ]);
        AgencySubscription::create([
            'agency_id' => $agency->id, 'plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now(),
        ]);

        $headers = $this->authHeaders($this->tokenFor($dealerUser));
        $this->postJson('/api/dealer-files', $this->uploadPayload(), $headers)->assertStatus(201);
        $this->postJson('/api/dealer-files', $this->uploadPayload(), $headers)
            ->assertStatus(402)
            ->assertJsonPath('code', 'plan_limit_reached');
    }

    private function makeSellableFile(Dealer $dealer): DealerFile
    {
        return DealerFile::create([
            'title' => 'Subscription Gate File', 'type' => 'image', 'url' => 'https://example.test/g.jpg',
            'folder' => 'Properties', 'file_size_kb' => 100,
            'dealer_id' => $dealer->id, 'uploaded_by' => $dealer->id, 'status' => 'available',
        ]);
    }

    private function sellRequest($user, DealerFile $file)
    {
        return $this->postJson("/api/dealer-files/{$file->id}/sell", [
            'selling_price_kbr' => 9000, 'buyer_name' => 'Gate Buyer', 'buyer_phone' => '0300-2',
        ], $this->authHeaders($this->tokenFor($user)));
    }

    public function test_past_due_subscription_blocks_dealer_file_sales(): void
    {
        $dealer = $this->makeDealer('Past Due Dealer');
        [$user, $agency] = $this->dealerUser(['documents.view', 'finance.create_transaction'], $dealer);
        $plan = SubscriptionPlan::create([
            'key' => 'pastdue-' . Str::random(6), 'name' => 'Past Due', 'description' => 'test',
            'price' => 1000, 'currency' => 'PKR', 'interval' => 'month',
            'limits' => [], 'features' => [], 'is_active' => true, 'is_default' => false, 'sort_order' => 1,
        ]);
        AgencySubscription::create([
            'agency_id' => $agency->id, 'plan_id' => $plan->id, 'status' => 'past_due',
            'starts_at' => now()->subMonth(),
        ]);
        $file = $this->makeSellableFile($dealer);

        $this->sellRequest($user, $file)
            ->assertForbidden()
            ->assertJsonPath('code', 'subscription_inactive');

        // The file must remain unsold.
        $this->assertSame('available', $file->fresh()->status);
    }

    public function test_expired_subscription_blocks_dealer_file_sales(): void
    {
        $dealer = $this->makeDealer('Expired Dealer');
        [$user, $agency] = $this->dealerUser(['documents.view', 'finance.create_transaction'], $dealer);
        $plan = SubscriptionPlan::create([
            'key' => 'expired-' . Str::random(6), 'name' => 'Expired', 'description' => 'test',
            'price' => 1000, 'currency' => 'PKR', 'interval' => 'month',
            'limits' => [], 'features' => [], 'is_active' => true, 'is_default' => false, 'sort_order' => 1,
        ]);
        AgencySubscription::create([
            'agency_id' => $agency->id, 'plan_id' => $plan->id, 'status' => 'active',
            'starts_at' => now()->subMonths(2), 'ends_at' => now()->subDay(),
        ]);
        $file = $this->makeSellableFile($dealer);

        $this->sellRequest($user, $file)
            ->assertForbidden()
            ->assertJsonPath('code', 'subscription_inactive');

        $this->assertSame('available', $file->fresh()->status);
    }

    public function test_active_subscription_allows_dealer_file_sales(): void
    {
        $dealer = $this->makeDealer('Active Dealer');
        [$user, $agency] = $this->dealerUser(['documents.view', 'finance.create_transaction'], $dealer);
        $plan = SubscriptionPlan::create([
            'key' => 'active-' . Str::random(6), 'name' => 'Active', 'description' => 'test',
            'price' => 1000, 'currency' => 'PKR', 'interval' => 'month',
            'limits' => [], 'features' => [], 'is_active' => true, 'is_default' => false, 'sort_order' => 1,
        ]);
        AgencySubscription::create([
            'agency_id' => $agency->id, 'plan_id' => $plan->id, 'status' => 'active',
            'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(),
        ]);
        $file = $this->makeSellableFile($dealer);

        $this->sellRequest($user, $file)->assertOk();

        $this->assertSame('sold', $file->fresh()->status);
    }

    public function test_file_index_redacts_buyer_pii_for_users_without_sensitive_clearance(): void
    {
        $dealer = $this->makeDealer('Index PII Dealer');
        [$user] = $this->dealerUser(['documents.view'], $dealer);
        DealerFile::create([
            'title' => 'Sold Registry File',
            'type' => 'pdf',
            'url' => 'https://example.test/registry.pdf',
            'folder' => 'Documents',
            'file_size_kb' => 100,
            'dealer_id' => $dealer->id,
            'uploaded_by' => $dealer->id,
            'status' => 'sold',
            'buyer_name' => 'Private Buyer',
            'buyer_phone' => '03009990000',
            'buyer_cnic' => '42101****5671',
        ]);

        $row = $this->getJson('/api/dealer-files', $this->authHeaders($this->tokenFor($user)))
            ->assertOk()
            ->json('data.0');

        $this->assertArrayNotHasKey('buyer_name', $row);
        $this->assertArrayNotHasKey('buyer_phone', $row);
        $this->assertSame('42101****5671', $row['buyer_cnic']);
        $this->assertSame('https://example.test/registry.pdf', $row['url']);
    }

    public function test_sale_response_does_not_return_buyer_pii_without_sensitive_clearance(): void
    {
        $dealer = $this->makeDealer('Sale Response PII Dealer');
        [$user] = $this->dealerUser(['documents.view', 'finance.create_transaction'], $dealer);
        $file = $this->makeSellableFile($dealer);

        $payload = $this->postJson("/api/dealer-files/{$file->id}/sell", [
            'selling_price_kbr' => 9000,
            'buyer_name' => 'Private Buyer',
            'buyer_phone' => '03009990000',
            'buyer_cnic' => '42101-1234567-1',
        ], $this->authHeaders($this->tokenFor($user)))
            ->assertOk()
            ->json('data');

        $this->assertArrayNotHasKey('buyer_name', $payload);
        $this->assertArrayNotHasKey('buyer_phone', $payload);
        $this->assertSame('42101****5671', $payload['buyer_cnic']);
    }

}
