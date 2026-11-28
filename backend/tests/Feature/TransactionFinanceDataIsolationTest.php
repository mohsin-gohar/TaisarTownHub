<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\TransactionPayment;
use Illuminate\Support\Facades\DB;
use Tests\Feature\EnterpriseTestCase;

/**
 * Phase 3.11 — adversarial transaction and finance tenant isolation.
 *
 * Covers tenant-scoped finance lists/reports, server-owned agency stamps,
 * and deliberately inconsistent child rows (a valid FK can still point to a
 * transaction belonging to a different agency).
 */
class TransactionFinanceDataIsolationTest extends EnterpriseTestCase
{
    private function financePermissions(): array
    {
        return [
            'finance.view', 'finance.view_reports', 'view_transactions',
            'finance.create_transaction', 'create_transactions',
            'finance.update_transaction', 'update_transactions',
            'finance.manage_payments', 'manage_payments',
            'view_commissions', 'pay_commissions', 'void_transactions',
            'approve_transactions', 'manage_deals',
        ];
    }

    private function createDeal($user, array $overrides = []): array
    {
        $payload = array_merge([
            'propertyTitle' => 'Tenant Isolation Plot',
            'sellerName' => 'Seller',
            'buyerName' => 'Buyer',
            'agreementAmountPKR' => 1000000,
            'advancePaymentPKR' => 0,
            'transactionType' => 'Sale',
        ], $overrides);

        return $this->postJson('/api/agency/transactions', $payload, $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(201)
            ->json('transaction');
    }

    private function createExpense($user, int $amount): string
    {
        return $this->postJson('/api/agency/expenses', [
            'expense_date' => now()->toDateString(),
            'category' => 'office',
            'amount_pkr' => $amount,
        ], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(201)
            ->json('expense.id');
    }

    public function test_finance_lists_and_reports_are_isolated_between_two_agencies(): void
    {
        [$userA, $agencyA] = $this->agencyUser($this->financePermissions(), 'manager');
        [$userB, $agencyB] = $this->agencyUser($this->financePermissions(), 'manager');

        $dealA = $this->createDeal($userA, ['agreementAmountPKR' => 1000000]);
        $dealB = $this->createDeal($userB, ['agreementAmountPKR' => 9000000]);

        $paymentA = $this->postJson("/api/agency/transactions/{$dealA['id']}/payments", [
            'amount' => 120000, 'type' => 'advance', 'method' => 'cash',
        ], $this->authHeaders($this->tokenFor($userA)))->assertStatus(201)->json('payment');

        $paymentB = $this->postJson("/api/agency/transactions/{$dealB['id']}/payments", [
            'amount' => 890000, 'type' => 'advance', 'method' => 'cash',
        ], $this->authHeaders($this->tokenFor($userB)))->assertStatus(201)->json('payment');

        $commissionA = $this->postJson("/api/agency/transactions/{$dealA['id']}/commissions", [
            'amount' => 15000, 'beneficiary_type' => 'agency',
        ], $this->authHeaders($this->tokenFor($userA)))->assertStatus(201)->json('commission');

        $commissionB = $this->postJson("/api/agency/transactions/{$dealB['id']}/commissions", [
            'amount' => 99000, 'beneficiary_type' => 'agency',
        ], $this->authHeaders($this->tokenFor($userB)))->assertStatus(201)->json('commission');

        $expenseA = $this->createExpense($userA, 7000);
        $expenseB = $this->createExpense($userB, 88000);

        $headersA = $this->authHeaders($this->tokenFor($userA));
        $headersB = $this->authHeaders($this->tokenFor($userB));

        $listA = $this->getJson('/api/agency/transactions', $headersA)->assertOk()->json('transactions');
        $listB = $this->getJson('/api/agency/transactions', $headersB)->assertOk()->json('transactions');
        $this->assertContains($dealA['id'], array_column($listA, 'id'));
        $this->assertNotContains($dealB['id'], array_column($listA, 'id'));
        $this->assertContains($dealB['id'], array_column($listB, 'id'));
        $this->assertNotContains($dealA['id'], array_column($listB, 'id'));

        // The legacy /api/transactions alias must preserve the same tenant
        // boundary as the canonical /api/agency/transactions endpoint.
        $legacyA = $this->getJson('/api/transactions', $headersA)->assertOk()->json();
        $legacyB = $this->getJson('/api/transactions', $headersB)->assertOk()->json();
        $this->assertContains($dealA['id'], array_column($legacyA, 'id'));
        $this->assertNotContains($dealB['id'], array_column($legacyA, 'id'));
        $this->assertContains($dealB['id'], array_column($legacyB, 'id'));
        $this->assertNotContains($dealA['id'], array_column($legacyB, 'id'));

        $commissionsA = $this->getJson('/api/agency/commissions', $headersA)->assertOk()->json('commissions');
        $commissionsB = $this->getJson('/api/agency/commissions', $headersB)->assertOk()->json('commissions');
        $this->assertContains($commissionA['id'], array_column($commissionsA, 'id'));
        $this->assertNotContains($commissionB['id'], array_column($commissionsA, 'id'));
        $this->assertContains($commissionB['id'], array_column($commissionsB, 'id'));
        $this->assertNotContains($commissionA['id'], array_column($commissionsB, 'id'));

        $expensesA = $this->getJson('/api/agency/expenses', $headersA)->assertOk()->json('expenses');
        $expensesB = $this->getJson('/api/agency/expenses', $headersB)->assertOk()->json('expenses');
        $this->assertSame([$expenseA], array_column($expensesA, 'id'));
        $this->assertSame([$expenseB], array_column($expensesB, 'id'));

        $reportA = $this->getJson('/api/agency/reports', $headersA)->assertOk()->json();
        $reportB = $this->getJson('/api/agency/reports', $headersB)->assertOk()->json();
        $this->assertSame(1000000.0, (float) $reportA['deals']['agreed_pkr']);
        $this->assertSame(120000.0, (float) $reportA['collected_pkr']);
        $this->assertSame(7000, (int) $reportA['expenses']['total_pkr']);
        $this->assertSame(15000.0, (float) $reportA['commissions']['pending_pkr']);
        $this->assertSame(9000000.0, (float) $reportB['deals']['agreed_pkr']);
        $this->assertSame(890000.0, (float) $reportB['collected_pkr']);
        $this->assertSame(88000, (int) $reportB['expenses']['total_pkr']);
        $this->assertSame(99000.0, (float) $reportB['commissions']['pending_pkr']);

        $this->assertSame($agencyA->id, Transaction::find($dealA['id'])->agency_id);
        $this->assertSame($agencyB->id, Transaction::find($dealB['id'])->agency_id);
        $this->assertSame($agencyA->id, TransactionPayment::find($paymentA['id'])->agency_id);
        $this->assertSame($agencyB->id, TransactionPayment::find($paymentB['id'])->agency_id);
    }

    public function test_client_supplied_agency_ids_cannot_reassign_deals_or_payments(): void
    {
        [$userA, $agencyA] = $this->agencyUser($this->financePermissions(), 'manager');
        [, $agencyB] = $this->agencyUser($this->financePermissions(), 'manager');

        $deal = $this->createDeal($userA, [
            'agency_id' => $agencyB->id,
            'agreementAmountPKR' => 500000,
        ]);
        $this->assertSame($agencyA->id, $deal['agency_id']);

        $payment = $this->postJson("/api/agency/transactions/{$deal['id']}/payments", [
            'agency_id' => $agencyB->id,
            'amount' => 25000,
            'type' => 'advance',
        ], $this->authHeaders($this->tokenFor($userA)))
            ->assertStatus(201)
            ->json('payment');

        $this->assertSame($agencyA->id, $payment['agency_id']);
        $this->assertDatabaseHas('transaction_payments', [
            'id' => $payment['id'],
            'agency_id' => $agencyA->id,
            'transaction_id' => $deal['id'],
        ]);
    }

    public function test_foreign_agency_child_rows_cannot_leak_through_a_deal_relationship_or_be_used_to_mutate_its_ledger(): void
    {
        [$userA, $agencyA] = $this->agencyUser($this->financePermissions(), 'manager');
        [$userB, $agencyB] = $this->agencyUser($this->financePermissions(), 'manager');
        $dealA = $this->createDeal($userA, ['agreementAmountPKR' => 100000]);

        // Each FK is individually valid, but the child tenant stamp is wrong.
        // This is precisely the gap left by non-composite foreign keys.
        $foreignPaymentId = DB::table('transaction_payments')->insertGetId([
            'agency_id' => $agencyB->id,
            'transaction_id' => $dealA['id'],
            'amount' => 5000,
            'type' => 'advance',
            'method' => 'cash',
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $foreignCommissionId = DB::table('commissions')->insertGetId([
            'agency_id' => $agencyB->id,
            'transaction_id' => $dealA['id'],
            'beneficiary_type' => 'agency',
            'amount' => 700,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('payment_schedules')->insert([
            'agency_id' => $agencyB->id,
            'transaction_id' => $dealA['id'],
            'installment_no' => 1,
            'due_date' => now()->addMonth()->toDateString(),
            'amount' => 3000,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $headersA = $this->authHeaders($this->tokenFor($userA));
        $detail = $this->getJson("/api/agency/transactions/{$dealA['id']}", $headersA)->assertOk();
        $this->assertNotContains($foreignPaymentId, array_column($detail->json('transaction.payments'), 'id'));
        $this->assertNotContains($foreignCommissionId, array_column($detail->json('transaction.commissions'), 'id'));
        $this->assertCount(0, Transaction::find($dealA['id'])->schedules()->get());

        $payments = $this->getJson("/api/agency/transactions/{$dealA['id']}/payments", $headersA)->assertOk();
        $this->assertNotContains($foreignPaymentId, array_column($payments->json('payments'), 'id'));

        // Agency B must not be able to delete its mis-stamped child in a way
        // that recalculates Agency A's parent transaction.
        $this->deleteJson("/api/agency/payments/{$foreignPaymentId}", [
            'reason' => 'tenant boundary probe',
        ], $this->authHeaders($this->tokenFor($userB)))->assertStatus(404);

        $this->assertNotNull(DB::table('transaction_payments')->where('id', $foreignPaymentId)->value('id'));
        $this->assertSame($agencyA->id, Transaction::find($dealA['id'])->agency_id);
    }
}
