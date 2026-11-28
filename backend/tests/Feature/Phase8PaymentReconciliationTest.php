<?php

namespace Tests\Feature;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/** Phase 8 — manual/cash ledger reconciliation integrity. */
class Phase8PaymentReconciliationTest extends EnterpriseTestCase
{
    private function financePermissions(): array
    {
        return ['finance.view', 'finance.view_reports', 'view_transactions',
            'finance.create_transaction', 'create_transactions',
            'finance.manage_payments', 'manage_payments', 'void_transactions'];
    }

    private function createDeal($user): array
    {
        return $this->postJson('/api/agency/transactions', [
            'propertyTitle' => 'Reconciliation Plot', 'sellerName' => 'S', 'buyerName' => 'B',
            'agreementAmountPKR' => 1000000, 'advancePaymentPKR' => 0, 'transactionType' => 'Sale',
        ], $this->authHeaders($this->tokenFor($user)))->assertStatus(201)->json('transaction');
    }

    public function test_duplicate_receipt_number_is_rejected_with_422(): void
    {
        [$user] = $this->agencyUser($this->financePermissions(), 'manager');
        $deal = $this->createDeal($user);

        $payload = ['amount' => 10000, 'type' => 'advance', 'method' => 'cash', 'receipt_no' => 'RCPT-P8-001'];
        $this->postJson("/api/agency/transactions/{$deal['id']}/payments", $payload,
            $this->authHeaders($this->tokenFor($user)))->assertStatus(201);

        $this->postJson("/api/agency/transactions/{$deal['id']}/payments", $payload,
            $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(1, DB::table('transaction_payments')
            ->where('receipt_no', 'RCPT-P8-001')->count());
    }

    public function test_overpayment_and_refund_bounds_are_enforced(): void
    {
        [$user] = $this->agencyUser($this->financePermissions(), 'manager');
        $deal = $this->createDeal($user);
        $headers = $this->authHeaders($this->tokenFor($user));

        $this->postJson("/api/agency/transactions/{$deal['id']}/payments", [
            'amount' => 1100000, 'type' => 'advance', 'method' => 'cash',
        ], $headers)->assertStatus(422)->assertJsonPath('success', false);

        $this->postJson("/api/agency/transactions/{$deal['id']}/payments", [
            'amount' => 50000, 'type' => 'refund', 'method' => 'cash',
        ], $headers)->assertStatus(422)->assertJsonPath('success', false);

        $this->assertSame(0.0, (float) Transaction::find($deal['id'])->totalPaid());
    }

    public function test_voided_deal_rejects_payments(): void
    {
        [$user] = $this->agencyUser($this->financePermissions(), 'manager');
        $deal = $this->createDeal($user);
        $headers = $this->authHeaders($this->tokenFor($user));

        $this->postJson("/api/agency/transactions/{$deal['id']}/void", ['reason' => 'test'],
            $headers)->assertStatus(200);

        $this->postJson("/api/agency/transactions/{$deal['id']}/payments", [
            'amount' => 1000, 'type' => 'advance', 'method' => 'cash',
        ], $headers)->assertStatus(422)->assertJsonPath('success', false);
    }
}
