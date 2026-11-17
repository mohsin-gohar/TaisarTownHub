<?php

namespace Tests\Feature;

use App\Models\Dealer;
use App\Models\DealerFile;
use App\Models\Property;
use App\Models\Transaction;
use Illuminate\Support\Str;

/**
 * The legacy /api/transactions surface is still routable. These tests pin the
 * tenant isolation and server-side ownership binding that was missing there.
 */
class LegacyTransactionIsolationTest extends EnterpriseTestCase
{
    private function legacyPermissions(): array
    {
        return [
            'view_transactions', 'view_my_deals', 'create_transactions',
            'finance.view', 'manage_deals',
        ];
    }

    private function makeLegacyDeal(string $agencyId, string $propertyId, string $dealNumber): Transaction
    {
        $deal = new Transaction([
            'dealNumber' => $dealNumber,
            'propertyId' => $propertyId,
            'propertyTitle' => 'Legacy Plot',
            'sellerName' => 'Seller',
            'buyerName' => 'Buyer',
            'transactionType' => 'Sale',
            'agreementAmountPKR' => 10000000,
            'dealStatus' => 'Draft',
        ]);
        $deal->id = 'txn_' . Str::random(10);
        $deal->agency_id = $agencyId;
        $deal->save();

        return $deal;
    }

    private function legacyPayload(string $propertyId, array $overrides = []): array
    {
        return array_merge([
            'propertyId' => $propertyId,
            'propertyTitle' => 'Legacy Plot',
            'sellerName' => 'Seller Khan',
            'buyerName' => 'Buyer Ali',
            'transactionType' => 'Sale',
            'agreementAmountPKR' => 8000000,
            'advancePaymentPKR' => 1000000,
        ], $overrides);
    }

    public function test_legacy_index_is_scoped_to_the_resolved_agency(): void
    {
        [$userA, $agencyA] = $this->agencyUser($this->legacyPermissions(), 'manager');
        [$userB, $agencyB] = $this->agencyUser($this->legacyPermissions(), 'manager');
        $propA = $this->makePublicProperty($agencyA);
        $propB = $this->makePublicProperty($agencyB);
        $dealA = $this->makeLegacyDeal($agencyA->id, $propA->id, 'TXN-2026-0001');
        $dealB = $this->makeLegacyDeal($agencyB->id, $propB->id, 'TXN-2026-0002');

        $listA = $this->getJson('/api/transactions', $this->authHeaders($this->tokenFor($userA)))
            ->assertOk()->json();
        $idsA = array_column($listA, 'id');
        $this->assertContains($dealA->id, $idsA);
        $this->assertNotContains($dealB->id, $idsA);

        $listB = $this->getJson('/api/transactions', $this->authHeaders($this->tokenFor($userB)))
            ->assertOk()->json();
        $this->assertContains($dealB->id, array_column($listB, 'id'));
        $this->assertNotContains($dealA->id, array_column($listB, 'id'));
    }

    public function test_legacy_index_ignores_a_foreign_dealer_filter(): void
    {
        [$userA, $agencyA] = $this->agencyUser($this->legacyPermissions(), 'manager');
        [$userB, $agencyB] = $this->agencyUser($this->legacyPermissions(), 'manager');
        $propB = $this->makePublicProperty($agencyB);
        $dealB = $this->makeLegacyDeal($agencyB->id, $propB->id, 'TXN-2026-0003');

        $dealB->update(['dealerId' => 'dlr_foreign']);

        $list = $this->getJson('/api/transactions?dealerId=dlr_foreign', $this->authHeaders($this->tokenFor($userA)))
            ->assertOk()->json();
        $this->assertNotContains($dealB->id, array_column($list, 'id'));
    }

    public function test_legacy_store_binds_agency_server_side_and_ignores_body_agency_id(): void
    {
        [$userA, $agencyA] = $this->agencyUser($this->legacyPermissions(), 'manager');
        [, $agencyB] = $this->agencyUser($this->legacyPermissions(), 'manager');
        $propA = $this->makePublicProperty($agencyA);

        $created = $this->postJson('/api/transactions', $this->legacyPayload($propA->id, [
            'agency_id' => $agencyB->id,
            'created_by' => 999999,
        ]), $this->authHeaders($this->tokenFor($userA)))
            ->assertStatus(201)
            ->json();

        $this->assertSame($agencyA->id, $created['agency_id']);
        $this->assertSame($userA->id, $created['created_by']);
        $this->assertDatabaseHas('transactions', [
            'id' => $created['id'],
            'agency_id' => $agencyA->id,
            'created_by' => $userA->id,
        ]);
    }

    public function test_legacy_store_rejects_a_foreign_agency_property(): void
    {
        [$userA] = $this->agencyUser($this->legacyPermissions(), 'manager');
        [, $agencyB] = $this->agencyUser($this->legacyPermissions(), 'manager');
        $propB = $this->makePublicProperty($agencyB);

        $this->postJson('/api/transactions', $this->legacyPayload($propB->id), $this->authHeaders($this->tokenFor($userA)))
            ->assertStatus(422)
            ->assertJsonValidationErrors('propertyId');
    }

    public function test_legacy_store_rejects_a_foreign_agency_dealer(): void
    {
        [$userA, $agencyA] = $this->agencyUser($this->legacyPermissions(), 'manager');
        [, $agencyB] = $this->agencyUser($this->legacyPermissions(), 'manager');
        $propA = $this->makePublicProperty($agencyA);

        $dealerB = Dealer::create([
            'id' => 'dlr_' . Str::random(8),
            'fullName' => 'Foreign Dealer',
            'phone' => '0300-0000000',
        ]);
        $dealerUserB = $this->makeUser('agent', ['dealer_id' => $dealerB->id]);
        $this->makeMember($dealerUserB, $agencyB, $this->makeRole('agency', 'agent_b', []));

        $this->postJson('/api/transactions', $this->legacyPayload($propA->id, [
            'dealerId' => $dealerB->id,
        ]), $this->authHeaders($this->tokenFor($userA)))
            ->assertStatus(422)
            ->assertJsonValidationErrors('dealerId');
    }
}
