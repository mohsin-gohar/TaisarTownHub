<?php

namespace Tests\Feature;

use App\Models\Dealer;
use App\Models\DealerFile;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\EnterpriseTestCase;

/**
 * G-2 — booking/sale concurrency guards.
 *
 * A dealer file can only be sold once, and the status re-check runs on the
 * LOCKED row inside a transaction (SELECT … FOR UPDATE on MySQL). The same
 * locking pattern protects finance void/approve/payment state transitions.
 */
class BookingConcurrencyTest extends EnterpriseTestCase
{
    private function dealerUser(): array
    {
        $dealer = Dealer::create([
            'id' => 'dlr_' . Str::random(10),
            'fullName' => 'Concurrency Dealer',
            'phone' => '0300-0000000',
        ]);

        $user = $this->makeUser('agent', ['dealer_id' => $dealer->id]);
        $agency = $this->makeAgency('Conc Agency');
        // Dealer-file sale now requires an explicit transaction permission.
        $role = $this->makeRole('agency', 'agent', ['finance.create_transaction', 'documents.view']);
        $this->makeMember($user, $agency, $role);

        return [$user, $dealer, $agency];
    }

    private function makeFile(Dealer $dealer, float $cost = null): DealerFile
    {
        return DealerFile::create([
            'title' => 'Plot File ' . Str::random(4),
            'type' => 'image',
            'url' => 'https://example.test/file.jpg',
            'folder' => 'Properties',
            'file_size_kb' => 120.5,
            'dealer_id' => $dealer->id,
            'uploaded_by' => $dealer->id,
            'status' => 'available',
            'cost_price_kbr' => $cost,
        ]);
    }

    public function test_whole_rupee_deal_columns_reject_fractional_input_instead_of_truncating(): void
    {
        [$user] = $this->agencyUser(['finance.create_transaction']);
        $this->postJson('/api/agency/transactions', [
            'propertyTitle' => 'Precision Validation Plot',
            'sellerName' => 'Seller',
            'buyerName' => 'Buyer',
            'agreementAmountPKR' => '100000.50',
            'advancePaymentPKR' => '1000.25',
            'commissionAmountPKR' => '250.50',
        ], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'agreementAmountPKR',
                'advancePaymentPKR',
                'commissionAmountPKR',
            ]);

        $this->assertDatabaseMissing('transactions', [
            'propertyTitle' => 'Precision Validation Plot',
        ]);
    }

    public function test_file_can_be_sold_exactly_once(): void
    {
        [$user, $dealer] = $this->dealerUser();
        $file = $this->makeFile($dealer, 7000.0);
        $token = $this->tokenFor($user);

        $this->postJson('/api/dealer-files/' . $file->id . '/sell', [
            'selling_price_kbr' => 10000,
            'buyer_name' => 'Buyer One',
            'buyer_phone' => '0301-1111111',
        ], $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('success', true);

        $file->refresh();
        $this->assertSame('sold', $file->status);
        $this->assertSame(10000.0, (float) $file->selling_price_kbr);
        $this->assertSame(3000.0, (float) $file->profit_kbr);
        $this->assertSame('Buyer One', $file->buyer_name);
        $this->assertNull($file->buyer_cnic);

        $dealer->refresh();
        $this->assertSame(3000, (int) $dealer->totalEarnedPKR);
        $this->assertSame(3000, (int) $dealer->pendingCommissionPKR);
        $this->assertSame(1, (int) $dealer->dealsClosed);
    }

    public function test_second_sell_of_the_same_file_is_rejected(): void
    {
        [$user, $dealer] = $this->dealerUser();
        $file = $this->makeFile($dealer, 7000.0);
        $token = $this->tokenFor($user);

        $this->postJson('/api/dealer-files/' . $file->id . '/sell', [
            'selling_price_kbr' => 10000,
            'buyer_name' => 'Buyer One',
            'buyer_phone' => '0301-1111111',
        ], $this->authHeaders($token))->assertOk();

        $this->postJson('/api/dealer-files/' . $file->id . '/sell', [
            'selling_price_kbr' => 12000,
            'buyer_name' => 'Buyer Two',
            'buyer_phone' => '0301-2222222',
        ], $this->authHeaders($token))
            ->assertStatus(400)
            ->assertJsonPath('success', false);

        // Commission is never double-counted by the losing race.
        $dealer->refresh();
        $this->assertSame(3000, (int) $dealer->totalEarnedPKR);
        $this->assertSame(1, (int) $dealer->dealsClosed);
        $this->assertSame('Buyer One', $file->refresh()->buyer_name);
    }

    public function test_buyer_cnic_is_masked_before_storage(): void
    {
        [$user, $dealer] = $this->dealerUser();
        $file = $this->makeFile($dealer, null);
        $token = $this->tokenFor($user);

        $this->postJson('/api/dealer-files/' . $file->id . '/sell', [
            'selling_price_kbr' => 9000,
            'buyer_name' => 'CNIC Buyer',
            'buyer_phone' => '0301-3333333',
            'buyer_cnic' => '42101-1234567-1',
        ], $this->authHeaders($token))->assertOk();

        $file->refresh();
        $this->assertSame('42101****5671', $file->buyer_cnic);
        // The raw value must never reach storage.
        $raw = DB::table('dealer_files')->where('id', $file->id)->value('buyer_cnic');
        $this->assertStringNotContainsString('1234567-1', (string) $raw);
    }

    public function test_void_is_idempotent_and_payments_are_blocked_on_voided_deals(): void
    {
        [$user, $agency] = $this->agencyUser(['finance.update_transaction', 'finance.manage_payments']);
        $token = $this->tokenFor($user);

        $transaction = Transaction::create([
            'id' => 'txn_' . Str::random(10),
            'dealNumber' => 'DEAL-' . Str::upper(Str::random(6)),
            'propertyId' => 'prop_' . Str::random(8),
            'propertyTitle' => 'Concurrency Plot ' . Str::random(4),
            'sellerName' => 'Seller ' . Str::random(4),
            'buyerName' => 'Buyer ' . Str::random(4),
            'agency_id' => $agency->id,
            'transactionType' => 'sale',
            'agreementAmountPKR' => 500000,
            'dealStatus' => 'Active',
        ]);

        $headers = $this->authHeaders($token);

        $this->postJson('/api/agency/transactions/' . $transaction->id . '/void', [
            'reason' => 'Buyer withdrew.',
        ], $headers)->assertOk()->assertJsonPath('success', true);

        $this->postJson('/api/agency/transactions/' . $transaction->id . '/void', [
            'reason' => 'Second void attempt.',
        ], $headers)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->postJson('/api/agency/transactions/' . $transaction->id . '/payments', [
            'amount' => 50000,
        ], $headers)
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_fractional_payments_use_exact_paise_and_block_one_paisa_overpayment(): void
    {
        [$user, $agency] = $this->agencyUser(['finance.manage_payments']);
        $transaction = Transaction::create([
            'id' => 'txn_' . Str::random(10),
            'dealNumber' => 'DEAL-' . Str::upper(Str::random(6)),
            'propertyId' => 'prop_' . Str::random(8),
            'propertyTitle' => 'Decimal Ledger Plot',
            'sellerName' => 'Seller',
            'buyerName' => 'Buyer',
            'agency_id' => $agency->id,
            'transactionType' => 'sale',
            'agreementAmountPKR' => 100000,
            'dealStatus' => 'Active',
        ]);
        $headers = $this->authHeaders($this->tokenFor($user));

        $this->postJson('/api/agency/transactions/' . $transaction->id . '/payments', [
            'amount' => '1000.25',
            'type' => 'advance',
            'method' => 'bank_transfer',
        ], $headers)->assertStatus(201);

        $transaction->refresh();
        $this->assertSame(100025, $transaction->totalPaidCents());
        $this->assertSame(1000.25, (float) $transaction->totalPaid());
        $this->assertDatabaseHas('transaction_payments', [
            'transaction_id' => $transaction->id,
            'amount' => '1000.25',
        ]);

        // 98,999.76 + 1,000.25 = 100,000.01: exactly one paisa over.
        $this->postJson('/api/agency/transactions/' . $transaction->id . '/payments', [
            'amount' => '98999.76',
            'type' => 'installment',
            'method' => 'bank_transfer',
        ], $headers)->assertStatus(422);

        $this->assertSame(100025, $transaction->fresh()->totalPaidCents());
        $this->assertDatabaseCount('transaction_payments', 1);

        // A refund greater than all collected funds must not create a negative ledger.
        $this->postJson('/api/agency/transactions/' . $transaction->id . '/payments', [
            'amount' => '1000.26',
            'type' => 'refund',
            'method' => 'bank_transfer',
        ], $headers)->assertStatus(422)
            ->assertJsonFragment(['message' => 'Refund cannot exceed the amount already collected.']);

        $this->assertSame(100025, $transaction->fresh()->totalPaidCents());
        $this->assertDatabaseCount('transaction_payments', 1);
    }

    public function test_payment_updates_derived_ledger_status(): void
    {
        [$user, $agency] = $this->agencyUser(['finance.manage_payments']);
        $token = $this->tokenFor($user);

        $transaction = Transaction::create([
            'id' => 'txn_' . Str::random(10),
            'dealNumber' => 'DEAL-' . Str::upper(Str::random(6)),
            'propertyId' => 'prop_' . Str::random(8),
            'propertyTitle' => 'Ledger Plot ' . Str::random(4),
            'sellerName' => 'Seller ' . Str::random(4),
            'buyerName' => 'Buyer ' . Str::random(4),
            'agency_id' => $agency->id,
            'transactionType' => 'sale',
            'agreementAmountPKR' => 500000,
            'dealStatus' => 'Active',
        ]);

        $response = $this->postJson('/api/agency/transactions/' . $transaction->id . '/payments', [
            'amount' => 200000,
            'type' => 'advance',
            'method' => 'bank_transfer',
        ], $this->authHeaders($token))
            ->assertStatus(201);

        $this->assertSame(200000.0, (float) $response->json('totals.collected'));

        $transaction->refresh();
        $this->assertSame('Partially Paid', $transaction->paymentStatus);
        $this->assertSame(200000, (int) $transaction->advancePaymentPKR);
        $this->assertSame(300000, (int) $transaction->remainingBalancePKR);

        $paymentId = DB::table('transaction_payments')
            ->where('transaction_id', $transaction->id)
            ->value('id');
        $this->deleteJson('/api/agency/payments/' . $paymentId, [
            'reason' => 'Duplicate payment correction.',
        ], $this->authHeaders($token))->assertOk();

        $transaction->refresh();
        $this->assertSame(0, $transaction->totalPaidCents());
        $this->assertSame('Pending', $transaction->paymentStatus);
        $this->assertSame(0, (int) $transaction->advancePaymentPKR);
        $this->assertSame(500000, (int) $transaction->remainingBalancePKR);
    }
}
