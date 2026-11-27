<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\Expense;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Finance module: cross-agency isolation for EVERY finance endpoint, the
 * restored route targets (approve / schedules / commissions / commission list)
 * and the integrity guarantees that protect the ledger.
 *
 * Regression coverage for the regression where the finance commit removed four
 * public controller methods while the routes stayed registered
 * (GET /agency/commissions, POST /transactions/{id}/approve,
 *  POST /transactions/{id}/schedules, POST /transactions/{id}/commissions).
 */
class FinanceIsolationTest extends EnterpriseTestCase
{
    private function financePermissions(): array
    {
        return [
            'finance.view', 'view_transactions', 'finance.create_transaction',
            'create_transactions', 'finance.update_transaction', 'update_transactions',
            'finance.manage_payments', 'manage_payments', 'view_commissions',
            'view_my_commission', 'pay_commissions', 'void_transactions',
            'approve_transactions', 'manage_deals',
        ];
    }

    private function createDeal($user, array $overrides = []): array
    {
        $payload = array_merge([
            'propertyTitle' => 'Isolation Test Plot',
            'sellerName' => 'Seller Khan',
            'buyerName' => 'Buyer Ali',
            'agreementAmountPKR' => 10000000,
            'advancePaymentPKR' => 0,
            'transactionType' => 'Sale',
        ], $overrides);

        return $this->postJson('/api/agency/transactions', $payload, $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(201)
            ->json('transaction');
    }

    public function test_finance_update_rejects_a_foreign_agency_dealer(): void
    {
        [$adminA] = $this->agencyUser($this->financePermissions(), 'manager');
        [$adminB, $agencyB] = $this->agencyUser($this->financePermissions(), 'manager');
        $deal = $this->createDeal($adminA);

        $foreignDealer = \App\Models\Dealer::create([
            'id' => 'dlr_' . \Illuminate\Support\Str::random(8),
            'fullName' => 'Foreign Dealer',
            'phone' => '0300-2222222',
        ]);
        $foreignUser = $this->makeUser('agent', ['dealer_id' => $foreignDealer->id]);
        $this->makeMember($foreignUser, $agencyB, $this->makeRole('agency', 'agent', []));

        $this->patchJson("/api/agency/transactions/{$deal['id']}", [
            'dealerId' => $foreignDealer->id,
        ], $this->authHeaders($this->tokenFor($adminA)))
            ->assertStatus(422)
            ->assertJsonValidationErrors('dealerId');
    }

    public function test_commission_rejects_a_foreign_agency_dealer(): void
    {
        [$adminA] = $this->agencyUser($this->financePermissions(), 'manager');
        [$adminB, $agencyB] = $this->agencyUser($this->financePermissions(), 'manager');
        $deal = $this->createDeal($adminA);

        $foreignDealer = \App\Models\Dealer::create([
            'id' => 'dlr_' . \Illuminate\Support\Str::random(8),
            'fullName' => 'Foreign Commission Dealer',
            'phone' => '0300-9999999',
            'agency_id' => $agencyB->id,
        ]);

        $this->postJson("/api/agency/transactions/{$deal['id']}/commissions", [
            'amount' => 5000,
            'dealer_id' => $foreignDealer->id,
            'beneficiary_type' => 'dealer',
        ], $this->authHeaders($this->tokenFor($adminA)))
            ->assertStatus(422)
            ->assertJsonValidationErrors('dealer_id');

        $this->assertDatabaseMissing('commissions', [
            'transaction_id' => $deal['id'],
            'dealer_id' => $foreignDealer->id,
        ]);
    }

    public function test_void_requires_an_explicit_permission(): void
    {
        [$admin, $agency] = $this->agencyUser($this->financePermissions(), 'manager');
        [$viewer] = $this->agencyUser(['finance.view'], 'viewer', $agency);
        $deal = $this->createDeal($admin);

        $this->postJson("/api/agency/transactions/{$deal['id']}/void", [
            'reason' => 'viewer should not be allowed',
        ], $this->authHeaders($this->tokenFor($viewer)))
            ->assertForbidden();

        $this->assertNull(Transaction::find($deal['id'])->voided_at);
    }

    public function test_finance_store_persists_property_and_dealer_references(): void
    {
        [$user, $agency] = $this->agencyUser($this->financePermissions(), 'manager');
        $property = $this->makePublicProperty($agency);
        $dealer = \App\Models\Dealer::create([
            'id' => 'dlr_' . \Illuminate\Support\Str::random(8),
            'fullName' => 'Agency Dealer',
            'phone' => '0300-1111111',
        ]);
        $dealerUser = $this->makeUser('agent', ['dealer_id' => $dealer->id]);
        $this->makeMember($dealerUser, $agency, $this->makeRole('agency', 'agent', []));

        $deal = $this->postJson('/api/agency/transactions', [
            'propertyId' => $property->id,
            'propertyTitle' => 'Attributed Plot',
            'sellerName' => 'Seller',
            'buyerName' => 'Buyer',
            'agreementAmountPKR' => 5000000,
            'dealerId' => $dealer->id,
        ], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(201)
            ->json('transaction');

        $this->assertSame($property->id, $deal['propertyId']);
        $this->assertSame($dealer->id, $deal['dealerId']);
        $this->assertDatabaseHas('transactions', [
            'id' => $deal['id'],
            'propertyId' => $property->id,
            'dealerId' => $dealer->id,
        ]);
    }

    public function test_finance_index_and_show_work_when_the_agency_has_deals(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());
        $deal = $this->createDeal($user);
        $headers = $this->authHeaders($this->tokenFor($user));

        $list = $this->getJson('/api/agency/transactions', $headers)
            ->assertStatus(200)
            ->json();
        $this->assertContains($deal['id'], array_column($list['transactions'] ?? [], 'id'));

        $this->getJson("/api/agency/transactions/{$deal['id']}", $headers)
            ->assertStatus(200)
            ->assertJsonPath('transaction.id', $deal['id']);
    }

    public function test_agency_cannot_read_or_mutate_another_agencys_deal(): void
    {
        [$userA, $agencyA] = $this->agencyUser($this->financePermissions());
        [$userB] = $this->agencyUser($this->financePermissions());

        $deal = $this->createDeal($userA);
        $headersB = fn () => $this->authHeaders($this->tokenFor($userB));
        $id = $deal['id'];

        // Reads
        $this->getJson("/api/agency/transactions/{$id}", $headersB())->assertStatus(404);
        $this->getJson("/api/agency/transactions/{$id}/payments", $headersB())->assertStatus(404);

        // List must not contain the other agency's deal.
        $list = $this->getJson('/api/agency/transactions', $headersB())->assertStatus(200)->json();
        $this->assertNotContains($id, array_column($list['transactions'] ?? [], 'id'));

        // Mutations
        $this->patchJson("/api/agency/transactions/{$id}", ['dealStatus' => 'Negotiation'], $headersB())->assertStatus(404);
        $this->postJson("/api/agency/transactions/{$id}/void", ['reason' => 'cross agency attempt'], $headersB())->assertStatus(404);
        $this->postJson("/api/agency/transactions/{$id}/approve", [], $headersB())->assertStatus(404);
        $this->postJson("/api/agency/transactions/{$id}/payments", ['amount' => 1000], $headersB())->assertStatus(404);
        $this->postJson("/api/agency/transactions/{$id}/schedules", [
            'schedules' => [['installment_no' => 1, 'due_date' => now()->addMonth()->toDateString(), 'amount' => 1000]],
        ], $headersB())->assertStatus(404);
        $this->postJson("/api/agency/transactions/{$id}/commissions", ['amount' => 1000], $headersB())->assertStatus(404);

        // The untouched deal still belongs to agency A.
        $this->assertSame($agencyA->id, Transaction::find($id)->agency_id);
        $this->assertSame('Draft', Transaction::find($id)->dealStatus);
    }

    public function test_agency_cannot_read_or_mutate_another_agencys_payment(): void
    {
        [$userA] = $this->agencyUser($this->financePermissions());
        [$userB] = $this->agencyUser($this->financePermissions());

        $deal = $this->createDeal($userA, ['agreementAmountPKR' => 5000000]);
        $paymentId = $this->postJson(
            "/api/agency/transactions/{$deal['id']}/payments",
            ['amount' => 1000, 'type' => 'advance', 'method' => 'cash'],
            $this->authHeaders($this->tokenFor($userA))
        )->assertStatus(201)->json('payment.id');

        $headersB = fn () => $this->authHeaders($this->tokenFor($userB));

        // Deleting another agency's payment is refused.
        $this->deleteJson("/api/agency/payments/{$paymentId}", ['reason' => 'cross agency attempt'], $headersB())
            ->assertStatus(404);

        $this->assertNotNull(TransactionPayment::find($paymentId), 'payment must still exist');
        $this->assertNull(TransactionPayment::find($paymentId)->deleted_at);
    }

    public function test_own_payment_can_be_voided_and_totals_are_recalculated(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());
        $deal = $this->createDeal($user, ['agreementAmountPKR' => 100000]);
        $headers = fn () => $this->authHeaders($this->tokenFor($user));
        $paymentId = $this->postJson(
            "/api/agency/transactions/{$deal['id']}/payments",
            ['amount' => 2500, 'type' => 'advance', 'method' => 'cash'],
            $headers()
        )->assertStatus(201)->json('payment.id');

        $this->assertSame(2500.0, (float) Transaction::find($deal['id'])->totalPaid());

        $this->deleteJson("/api/agency/payments/{$paymentId}", ['reason' => 'recorded in error'], $headers())
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        // Soft-deleted payments stay in the audit trail but stop counting.
        $this->assertNull(TransactionPayment::find($paymentId));
        $this->assertNotNull(TransactionPayment::withTrashed()->find($paymentId)->deleted_at);
        $this->assertSame(0.0, (float) Transaction::find($deal['id'])->totalPaid());
        $this->assertSame(100000.0, (float) Transaction::find($deal['id'])->remainingBalancePKR);
    }

    public function test_agency_cannot_read_or_mutate_another_agencys_commission_or_expense(): void
    {
        [$userA, $agencyA] = $this->agencyUser($this->financePermissions());
        [$userB] = $this->agencyUser($this->financePermissions());
        $headersB = fn () => $this->authHeaders($this->tokenFor($userB));

        $deal = $this->createDeal($userA);
        $commission = $this->postJson(
            "/api/agency/transactions/{$deal['id']}/commissions",
            ['amount' => 5000, 'beneficiary_type' => 'dealer'],
            $this->authHeaders($this->tokenFor($userA))
        )->assertStatus(201)->json('commission');

        $this->putJson(
            "/api/agency/commissions/{$commission['id']}",
            ['status' => 'paid'],
            $headersB()
        )->assertStatus(404);

        $listB = $this->getJson('/api/agency/commissions', $headersB())->assertStatus(200)->json();
        $this->assertNotContains($commission['id'], array_column($listB['commissions'] ?? [], 'id'));

        // Expenses are agency-scoped as well.
        $expense = Expense::create([
            'id' => 'exp_' . bin2hex(random_bytes(6)),
            'agency_id' => $agencyA->id,
            'expense_date' => now()->toDateString(),
            'category' => 'other',
            'amount_pkr' => 1500,
            'created_by' => $userA->id,
        ]);

        $this->deleteJson("/api/agency/expenses/{$expense->id}", [], $headersB())->assertStatus(404);
        $expensesB = $this->getJson('/api/agency/expenses', $headersB())->assertStatus(200)->json();
        $this->assertNotContains($expense->id, array_column($expensesB['expenses'] ?? [], 'id'));
    }

    public function test_restored_approve_endpoint_works_and_is_audited(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());
        $deal = $this->createDeal($user);

        $this->postJson("/api/agency/transactions/{$deal['id']}/approve", [], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $stored = Transaction::find($deal['id']);
        $this->assertNotNull($stored->approved_at);
        $this->assertSame($user->id, $stored->approved_by);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'transaction.approve',
            'entity_id' => $deal['id'],
        ]);
    }

    public function test_restored_schedules_endpoint_stamps_the_agency(): void
    {
        [$user, $agency] = $this->agencyUser($this->financePermissions());
        $deal = $this->createDeal($user);

        $response = $this->postJson(
            "/api/agency/transactions/{$deal['id']}/schedules",
            [
                'schedules' => [
                    ['installment_no' => 1, 'due_date' => now()->addMonth()->toDateString(), 'amount' => 100000],
                    ['installment_no' => 2, 'due_date' => now()->addMonths(2)->toDateString(), 'amount' => 100000],
                ],
            ],
            $this->authHeaders($this->tokenFor($user))
        )->assertStatus(201);

        $this->assertCount(2, $response->json('schedules'));

        $rows = DB::table('payment_schedules')->where('transaction_id', $deal['id'])->get();
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertSame($agency->id, $row->agency_id, 'schedules must be agency-stamped');
        }

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'schedule.save',
            'entity_id' => $deal['id'],
        ]);
    }

    public function test_restored_commissions_endpoint_creates_and_lists_commissions(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());
        $headers = fn () => $this->authHeaders($this->tokenFor($user));
        $deal = $this->createDeal($user);

        $commission = $this->postJson(
            "/api/agency/transactions/{$deal['id']}/commissions",
            ['amount' => 250000, 'rate' => 2.5, 'beneficiary_type' => 'dealer'],
            $headers()
        )->assertStatus(201)->json('commission');

        $this->assertSame('pending', $commission['status']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'commission.create',
            'entity_id' => $commission['id'],
        ]);

        $list = $this->getJson('/api/agency/commissions', $headers())->assertStatus(200)->json();
        $this->assertContains($commission['id'], array_column($list['commissions'] ?? [], 'id'));

        // Status change is allowed with the real permission set.
        $this->putJson(
            "/api/agency/commissions/{$commission['id']}",
            ['status' => 'accrued'],
            $headers()
        )->assertStatus(200)->assertJsonPath('commission.status', 'accrued');
    }

    public function test_commission_status_change_is_denied_without_permission(): void
    {
        [$userA] = $this->agencyUser($this->financePermissions());
        $deal = $this->createDeal($userA);
        $commission = $this->postJson(
            "/api/agency/transactions/{$deal['id']}/commissions",
            ['amount' => 1000],
            $this->authHeaders($this->tokenFor($userA))
        )->json('commission');

        // A viewer without finance permissions cannot touch commissions.
        [$viewer] = $this->agencyUser(['view_properties', 'properties.view']);
        $this->putJson(
            "/api/agency/commissions/{$commission['id']}",
            ['status' => 'paid'],
            $this->authHeaders($this->tokenFor($viewer))
        )->assertStatus(403);
    }

    public function test_deal_numbers_are_unique_and_sequential(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());

        $numbers = [];
        for ($i = 0; $i < 5; $i++) {
            $numbers[] = $this->createDeal($user, ['propertyTitle' => "Plot {$i}"])['dealNumber'];
        }

        $this->assertCount(5, array_unique($numbers), 'deal numbers must be unique');

        $suffixes = array_map(fn ($n) => (int) substr($n, -4), $numbers);
        sort($suffixes);
        $this->assertSame(range($suffixes[0], $suffixes[0] + 4), $suffixes, 'deal numbers must be sequential');
    }

    public function test_database_rejects_a_duplicate_deal_number(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());
        $deal = $this->createDeal($user);

        // The unique constraint is the safety net behind the retry logic —
        // it must still be enforced by the database.
        $this->expectException(QueryException::class);

        DB::table('transactions')->insert([
            'id' => 'txn_duplicate_' . bin2hex(random_bytes(4)),
            'dealNumber' => $deal['dealNumber'],
            'propertyTitle' => 'Duplicate',
            'sellerName' => 'S',
            'buyerName' => 'B',
            'agreementAmountPKR' => 1000,
            'advancePaymentPKR' => 0,
            'remainingBalancePKR' => 1000,
            'commissionAmountPKR' => 0,
            'dealStatus' => 'Draft',
            'transactionType' => 'Sale',
            'transactionDate' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_payment_cannot_push_the_ledger_past_the_agreement_amount(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());
        $headers = fn () => $this->authHeaders($this->tokenFor($user));
        $deal = $this->createDeal($user, ['agreementAmountPKR' => 100000, 'advancePaymentPKR' => 0]);

        $this->postJson("/api/agency/transactions/{$deal['id']}/payments", ['amount' => 60000], $headers())
            ->assertStatus(201);

        // The second payment would exceed the agreement amount -> rejected.
        $this->postJson("/api/agency/transactions/{$deal['id']}/payments", ['amount' => 60000], $headers())
            ->assertStatus(422);

        $this->assertSame(60000.0, (float) TransactionPayment::where('transaction_id', $deal['id'])->sum('amount'));
    }

    public function test_duplicate_installment_numbers_are_rejected_without_partial_schedule_writes(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());
        $deal = $this->createDeal($user);
        $headers = $this->authHeaders($this->tokenFor($user));

        $this->postJson("/api/agency/transactions/{$deal['id']}/schedules", [
            'schedules' => [
                ['installment_no' => 1, 'due_date' => now()->addMonth()->toDateString(), 'amount' => 1000],
                ['installment_no' => 1, 'due_date' => now()->addMonths(2)->toDateString(), 'amount' => 2000],
            ],
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('schedules.1.installment_no');

        $this->assertDatabaseCount('payment_schedules', 0);
    }

    public function test_voided_deal_rejects_further_payments_and_schedules(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());
        $headers = fn () => $this->authHeaders($this->tokenFor($user));
        $deal = $this->createDeal($user);

        $this->postJson("/api/agency/transactions/{$deal['id']}/void", ['reason' => 'audit test void'], $headers())
            ->assertStatus(200);

        $this->postJson("/api/agency/transactions/{$deal['id']}/payments", ['amount' => 1000], $headers())
            ->assertStatus(422);

        $this->postJson("/api/agency/transactions/{$deal['id']}/schedules", [
            'schedules' => [['installment_no' => 1, 'due_date' => now()->addMonth()->toDateString(), 'amount' => 1000]],
        ], $headers())->assertStatus(422);

        $this->postJson("/api/agency/transactions/{$deal['id']}/commissions", [
            'amount' => 1000,
        ], $headers())->assertStatus(422);
        $this->assertDatabaseMissing('commissions', ['transaction_id' => $deal['id']]);

        // A voided deal cannot be approved either.
        $this->postJson("/api/agency/transactions/{$deal['id']}/approve", [], $headers())->assertStatus(422);
    }

    public function test_platform_super_admin_can_update_a_commission_without_agency_context(): void
    {
        [$agencyUser] = $this->agencyUser($this->financePermissions());
        $deal = $this->createDeal($agencyUser);
        $commission = $this->postJson(
            "/api/agency/transactions/{$deal['id']}/commissions",
            ['amount' => 1000],
            $this->authHeaders($this->tokenFor($agencyUser))
        )->assertStatus(201)->json('commission');

        // Regression: updateCommission filtered `agency_id = NULL` for platform
        // users, so commissions() listed every row but every payout was a 404.
        $admin = $this->makeUser('super_admin');
        $this->putJson(
            "/api/agency/commissions/{$commission['id']}",
            ['status' => 'paid'],
            $this->authHeaders($this->tokenFor($admin))
        )->assertStatus(200)->assertJsonPath('commission.status', 'paid');
    }

    public function test_commission_rows_are_agency_scoped(): void
    {
        [$userA, $agencyA] = $this->agencyUser($this->financePermissions());
        $deal = $this->createDeal($userA);

        $this->postJson(
            "/api/agency/transactions/{$deal['id']}/commissions",
            ['amount' => 1000],
            $this->authHeaders($this->tokenFor($userA))
        )->assertStatus(201);

        $commission = Commission::where('transaction_id', $deal['id'])->first();
        $this->assertNotNull($commission);
        $this->assertSame($agencyA->id, $commission->agency_id);
    }
}
