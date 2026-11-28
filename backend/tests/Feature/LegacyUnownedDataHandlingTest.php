<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Support\LegacyTenancyResolver;
use Illuminate\Support\Facades\DB;

/** Regression coverage for unresolved ownership and legacy data reconciliation. */
class LegacyUnownedDataHandlingTest extends EnterpriseTestCase
{
    public function test_legacy_dealer_mine_view_excludes_unowned_inquiries_without_property_evidence(): void
    {
        $agency = $this->makeAgency('Legacy Inquiry Fixture');
        DB::table('dealers')->insert([
            'id' => 'legacy-dealer-312',
            'fullName' => 'Legacy Dealer',
            'phone' => '03000000312',
            'agency_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $property = $this->makePublicProperty($agency, [
            'title' => 'Legacy Assigned Property',
            'assignedDealerId' => 'legacy-dealer-312',
        ]);
        // Simulate a pre-enterprise listing: assigned dealer is the evidence,
        // but there is no canonical agency stamp.
        $property->agency_id = null;
        $property->save();

        $user = $this->makeUser('manager', ['dealer_id' => 'legacy-dealer-312']);
        Inquiry::create([
            'id' => 'inq-legacy-dealer-owned',
            'fullName' => 'Assigned lead',
            'phone' => '03000000313',
            'subject' => 'Assigned property lead',
            'message' => 'Evidence-linked lead',
            'status' => 'Pending',
            'source' => 'Legacy',
            'agency_id' => null,
            'property_id' => $property->id,
            'lead_status' => 'new',
        ]);
        Inquiry::create([
            'id' => 'inq-legacy-unowned',
            'fullName' => 'Unowned lead',
            'phone' => '03000000314',
            'subject' => 'General lead',
            'message' => 'No ownership evidence',
            'status' => 'Pending',
            'source' => 'Legacy',
            'agency_id' => null,
            'property_id' => null,
            'lead_status' => 'new',
        ]);

        $response = $this->getJson('/api/inquiries?mine=1', $this->authHeaders($this->tokenFor($user)))
            ->assertOk();

        $ids = array_column($response->json(), 'id');
        $this->assertContains('inq-legacy-dealer-owned', $ids);
        $this->assertNotContains('inq-legacy-unowned', $ids);
    }

    public function test_read_only_legacy_ownership_report_counts_unowned_typed_rows_and_finance_parent_mismatches(): void
    {
        [$userA, $agencyA] = $this->agencyUser(['finance.view', 'finance.manage_payments'], 'manager');
        [, $agencyB] = $this->agencyUser(['finance.view', 'finance.manage_payments'], 'manager');

        $deal = Transaction::create([
            'id' => 'txn-legacy-a-312',
            'dealNumber' => 'LEGACY-312-A',
            'propertyTitle' => 'Legacy finance record',
            'sellerName' => 'Seller',
            'buyerName' => 'Buyer',
            'agency_id' => $agencyA->id,
            'transactionType' => 'Sale',
            'agreementAmountPKR' => 100000,
            'advancePaymentPKR' => 0,
            'remainingBalancePKR' => 100000,
            'commissionAmountPKR' => 0,
            'commissionStatus' => 'Pending',
            'dealStatus' => 'Draft',
        ]);

        TransactionPayment::create([
            'agency_id' => $agencyB->id,
            'transaction_id' => $deal->id,
            'amount' => 1000,
            'type' => 'advance',
            'method' => 'cash',
            'received_at' => now(),
        ]);

        DB::table('properties')->insert([
            'id' => 'prop-unowned-312',
            'title' => 'Unowned legacy property',
            'pricePKR' => 100000,
            'phase' => 'Phase 1',
            'sector' => 'Legacy',
            'sizeSqYards' => 120,
            'type' => 'Plot',
            'status' => 'Available',
            'agency_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('platform:legacy-ownership', ['--json' => true])
            ->expectsOutputToContain('"typed_table_ownership"')
            ->expectsOutputToContain('"properties"')
            ->expectsOutputToContain('"transaction_payments": 1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('properties', ['id' => 'prop-unowned-312', 'agency_id' => null]);
        $this->assertDatabaseHas('transaction_payments', [
            'transaction_id' => $deal->id,
            'agency_id' => $agencyB->id,
        ]);
    }

    public function test_duplicate_agency_names_are_not_accepted_as_unique_ownership_evidence(): void
    {
        DB::table('agencies')->insert([
            'id' => 'agency-name-duplicate-312',
            'name' => 'Duplicate Legacy Name',
            'slug' => 'duplicate-legacy-name-312',
            'status' => 'active',
            'verification_status' => 'verified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('agencies')->insert([
            'id' => 'agency-name-duplicate-313',
            'name' => 'Duplicate Legacy Name',
            'slug' => 'duplicate-legacy-name-313',
            'status' => 'active',
            'verification_status' => 'verified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = \App\Models\BusinessRecord::put('videos', 'legacy-video-312', [
            'agencyName' => 'Duplicate Legacy Name',
        ]);

        $resolver = new LegacyTenancyResolver();
        $this->assertNull($resolver->resolve($row));
        $this->assertCount(2, $resolver->candidates($row));
    }
}
