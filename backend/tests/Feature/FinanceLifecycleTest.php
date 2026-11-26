<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\Dealer;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Models\PaymentSchedule;
use Illuminate\Support\Str;

/**
 * Finance state machine + tenancy: forward transitions, one-step-back only,
 * Completed gating, terminal read-only, voiding, payments ledger, scoping.
 */
class FinanceLifecycleTest extends EnterpriseTestCase
{
    private function financePermissions(): array
    {
        return [
            'finance.view', 'view_transactions', 'finance.create_transaction',
            'create_transactions', 'finance.update_transaction', 'update_transactions',
            'finance.manage_payments', 'manage_payments', 'finance.view_commissions',
            'view_commissions', 'void_transactions', 'manage_deals',
        ];
    }

    private function createDeal($user, array $overrides = []): array
    {
        $payload = array_merge([
            'propertyTitle' => 'Finance Test Plot',
            'sellerName' => 'Seller Khan',
            'buyerName' => 'Buyer Ali',
            'agreementAmountPKR' => 10000000,
            'advancePaymentPKR' => 2500000,
            'commissionAmountPKR' => 200000,
            'transactionType' => 'Sale',
        ], $overrides);

        return $this->postJson('/api/agency/transactions', $payload, $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(201)
            ->json('transaction');
    }

    private function patchStatus($user, string $id, string $status)
    {
        return $this->patchJson(
            "/api/agency/transactions/{$id}",
            ['dealStatus' => $status],
            $this->authHeaders($this->tokenFor($user))
        );
    }

    public function test_deal_can_move_forward_through_the_whole_pipeline(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());
        $deal = $this->createDeal($user);

        $this->assertEquals('Draft', $deal['dealStatus']);
        $this->assertEquals(2500000, $deal['advancePaymentPKR']);
        $this->assertEquals(7500000, $deal['remainingBalancePKR']);
        $this->assertStringStartsWith('TXN-' . now()->format('Y') . '-', $deal['dealNumber']);

        foreach (['Negotiation', 'Agreement Signed', 'Token Paid', 'Advance Received', 'Payment Pending', 'Partially Paid', 'Fully Paid', 'Under Transfer', 'Completed'] as $stage) {
            $this->patchStatus($user, $deal['id'], $stage)
                ->assertStatus(200)
                ->assertJsonPath('transaction.dealStatus', $stage);
        }

        // Completed is terminal: further status edits are rejected.
        $this->patchStatus($user, $deal['id'], 'Cancelled')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_completed_requires_under_transfer(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());
        $deal = $this->createDeal($user);

        $this->patchStatus($user, $deal['id'], 'Completed')
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'A deal can only be marked Completed after it is Under Transfer at MDA.']);
    }

    public function test_deal_cannot_be_created_in_a_terminal_state(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());

        foreach (['Completed', 'Cancelled'] as $terminal) {
            $this->postJson('/api/agency/transactions', [
                'propertyTitle' => 'Terminal State Plot',
                'sellerName' => 'Seller Khan',
                'buyerName' => 'Buyer Ali',
                'agreementAmountPKR' => 10000000,
                'transactionType' => 'Sale',
                'dealStatus' => $terminal,
            ], $this->authHeaders($this->tokenFor($user)))
                ->assertStatus(422)
                ->assertJsonFragment(['message' => 'A new deal cannot be created directly in a terminal state.']);
        }

        // Non-terminal creation still works.
        $this->postJson('/api/agency/transactions', [
            'propertyTitle' => 'Draft Plot',
            'sellerName' => 'Seller Khan',
            'buyerName' => 'Buyer Ali',
            'agreementAmountPKR' => 5000000,
            'transactionType' => 'Sale',
            'dealStatus' => 'Draft',
        ], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(201)
            ->assertJsonPath('transaction.dealStatus', 'Draft');
    }

    public function test_backward_transition_is_only_one_stage(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());
        $deal = $this->createDeal($user);

        // Forward to Token Paid is allowed from Draft.
        $this->patchStatus($user, $deal['id'], 'Token Paid')->assertStatus(200);

        // One step back is a correction.
        $this->patchStatus($user, $deal['id'], 'Agreement Signed')
            ->assertStatus(200)
            ->assertJsonPath('transaction.dealStatus', 'Agreement Signed');

        // Jumping three stages back is rejected.
        $this->patchStatus($user, $deal['id'], 'Draft')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_cancelled_deal_is_terminal(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());
        $deal = $this->createDeal($user);

        $this->patchStatus($user, $deal['id'], 'Cancelled')->assertStatus(200);

        $this->patchStatus($user, $deal['id'], 'Negotiation')
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Deal is cancelled — the record is read-only for bookkeeping.']);
    }

    public function test_completed_deal_is_read_only_even_for_non_status_updates(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());
        $deal = $this->createDeal($user);

        foreach (['Negotiation', 'Agreement Signed', 'Token Paid', 'Advance Received', 'Payment Pending', 'Partially Paid', 'Fully Paid', 'Under Transfer', 'Completed'] as $stage) {
            $this->patchStatus($user, $deal['id'], $stage)->assertStatus(200);
        }

        $this->patchJson(
            "/api/agency/transactions/{$deal['id']}",
            ['propertyTitle' => 'Attempted post-completion edit'],
            $this->authHeaders($this->tokenFor($user))
        )->assertStatus(422)
            ->assertJsonFragment(['message' => 'Completed deals are read-only.']);

        $this->assertDatabaseHas('transactions', [
            'id' => $deal['id'],
            'propertyTitle' => 'Finance Test Plot',
            'dealStatus' => 'Completed',
        ]);
    }

    public function test_payment_endpoint_rechecks_void_state_before_writing(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());
        $deal = $this->createDeal($user, ['advancePaymentPKR' => 0]);
        $headers = $this->authHeaders($this->tokenFor($user));

        $this->postJson("/api/agency/transactions/{$deal['id']}/void", [
            'reason' => 'Duplicate deal record',
        ], $headers)->assertOk();

        $this->postJson("/api/agency/transactions/{$deal['id']}/payments", [
            'amount' => 1000,
            'type' => 'advance',
        ], $headers)->assertStatus(422);

        $this->assertDatabaseMissing('transaction_payments', [
            'transaction_id' => $deal['id'],
            'amount' => 1000,
        ]);
    }

    public function test_seeded_agency_manager_can_create_and_update_a_deal(): void
    {
        // Regression: store/update required `manage_deals`, a key that exists
        // in no permission catalogue, so the seeded agency roles (which hold
        // finance.create_transaction / finance.update_transaction) could not
        // work with deals at all.
        $grants = \App\Support\Permissions::agencyRoleGrants('agency_manager');
        $this->assertNotContains('manage_deals', $grants);

        [$user] = $this->agencyUser($grants, 'agency_manager');
        $deal = $this->createDeal($user);

        $this->patchJson(
            "/api/agency/transactions/{$deal['id']}",
            ['buyerName' => 'Updated Buyer'],
            $this->authHeaders($this->tokenFor($user))
        )->assertStatus(200)->assertJsonPath('transaction.buyerName', 'Updated Buyer');
    }

    public function test_only_agency_owner_can_void_seeded_agency_deals(): void
    {
        $managerGrants = \App\Support\Permissions::agencyRoleGrants('agency_manager');
        [$manager] = $this->agencyUser($managerGrants, 'agency_manager');
        $managerDeal = $this->createDeal($manager);

        $this->postJson(
            "/api/agency/transactions/{$managerDeal['id']}/approve",
            [],
            $this->authHeaders($this->tokenFor($manager))
        )->assertStatus(403);

        $this->postJson(
            "/api/agency/transactions/{$managerDeal['id']}/void",
            ['reason' => 'Manager attempted irreversible void'],
            $this->authHeaders($this->tokenFor($manager))
        )->assertStatus(403);

        $ownerGrants = \App\Support\Permissions::agencyRoleGrants('agency_owner');
        [$owner] = $this->agencyUser($ownerGrants, 'agency_owner');
        $ownerDeal = $this->createDeal($owner, ['advancePaymentPKR' => 0]);

        $this->postJson(
            "/api/agency/transactions/{$ownerDeal['id']}/approve",
            [],
            $this->authHeaders($this->tokenFor($owner))
        )->assertStatus(200)->assertJsonPath('success', true);

        $this->postJson(
            "/api/agency/transactions/{$ownerDeal['id']}/void",
            ['reason' => 'Owner approved bookkeeping correction'],
            $this->authHeaders($this->tokenFor($owner))
        )->assertStatus(200)->assertJsonPath('success', true);
    }

    public function test_linked_dealer_without_user_account_can_be_attached_to_a_deal(): void
    {
        [$user, $agency] = $this->agencyUser($this->financePermissions());

        // A dealer row linked to the agency can be assigned even without a
        // user account; legacy membership-based ownership remains a fallback.
        $dealer = Dealer::create([
            'id' => 'dlr_' . Str::random(10),
            'fullName' => 'Linked Dealer',
            'phone' => '03001234567',
            'agency_id' => $agency->id,
        ]);

        $deal = $this->createDeal($user, ['dealerId' => $dealer->id]);

        $this->assertSame($dealer->id, $deal['dealerId']);
    }

    public function test_agency_cannot_read_another_agencys_deal(): void
    {
        [$userA] = $this->agencyUser($this->financePermissions());
        [$userB] = $this->agencyUser($this->financePermissions());

        $deal = $this->createDeal($userA);

        $this->getJson("/api/agency/transactions/{$deal['id']}", $this->authHeaders($this->tokenFor($userB)))
            ->assertStatus(404);

        // Index is scoped too.
        $rows = $this->getJson('/api/agency/transactions?page=1', $this->authHeaders($this->tokenFor($userB)))
            ->assertStatus(200)
            ->json('transactions');
        $this->assertNotContains($deal['id'], array_column($rows, 'id'));
    }

    public function test_voided_deal_is_read_only_and_audited(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());
        $deal = $this->createDeal($user, ['advancePaymentPKR' => 0]);

        $this->postJson("/api/agency/transactions/{$deal['id']}/void", ['reason' => 'Buyer withdrew'], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->patchJson("/api/agency/transactions/{$deal['id']}", ['buyerName' => 'Someone Else'], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(409);

        $this->assertNotNull(Transaction::find($deal['id'])->voided_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'transaction.void',
            'entity_id' => $deal['id'],
        ]);
    }

    public function test_void_requires_refunds_and_cancels_unpaid_finance_items(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());
        $deal = $this->createDeal($user);
        $headers = $this->authHeaders($this->tokenFor($user));

        $commission = $this->postJson("/api/agency/transactions/{$deal['id']}/commissions", [
            'amount' => 5000,
            'beneficiary_type' => 'agency',
        ], $headers)->assertStatus(201)->json('commission');

        $this->postJson("/api/agency/transactions/{$deal['id']}/schedules", [
            'schedules' => [[
                'installment_no' => 1,
                'due_date' => now()->addWeek()->toDateString(),
                'amount' => 1000,
            ]],
        ], $headers)->assertStatus(201);

        $this->postJson("/api/agency/transactions/{$deal['id']}/void", [
            'reason' => 'Buyer withdrew',
        ], $headers)->assertStatus(409)
            ->assertJsonPath('message', 'Refund or reconcile all collected payments before voiding this deal.');

        $refund = $this->postJson("/api/agency/transactions/{$deal['id']}/payments", [
            'amount' => 2500000,
            'type' => 'refund',
        ], $headers)->assertStatus(201)->json('payment');

        $this->postJson("/api/agency/transactions/{$deal['id']}/void", [
            'reason' => 'Buyer withdrew after full refund',
        ], $headers)->assertStatus(200);

        $this->assertSame('cancelled', Commission::findOrFail($commission['id'])->status);
        $this->assertSame('cancelled', PaymentSchedule::where('transaction_id', $deal['id'])->value('status'));

        $this->postJson("/api/agency/transactions/{$deal['id']}/commissions", [
            'amount' => 1000,
            'beneficiary_type' => 'agency',
        ], $headers)->assertStatus(422);

        $this->deleteJson("/api/agency/payments/{$refund['id']}", [
            'reason' => 'Attempt to change a voided ledger',
        ], $headers)->assertStatus(409);
    }

    public function test_void_is_blocked_when_a_commission_has_already_been_paid(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());
        $deal = $this->createDeal($user, ['advancePaymentPKR' => 0]);
        $headers = $this->authHeaders($this->tokenFor($user));

        $commission = $this->postJson("/api/agency/transactions/{$deal['id']}/commissions", [
            'amount' => 5000,
            'beneficiary_type' => 'agency',
        ], $headers)->assertStatus(201)->json('commission');

        $this->patchJson("/api/agency/commissions/{$commission['id']}", [
            'status' => 'paid',
        ], $headers)->assertOk();

        $this->postJson("/api/agency/transactions/{$deal['id']}/void", [
            'reason' => 'Attempt to void after payout',
        ], $headers)->assertStatus(409)
            ->assertJsonPath('message', 'A deal with paid commissions cannot be voided until the payout is reversed through an audited adjustment.');
    }

    public function test_void_requires_a_reason(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());
        $deal = $this->createDeal($user);

        $this->postJson("/api/agency/transactions/{$deal['id']}/void", [], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_deal_amounts_reject_fractional_rupees_and_advance_over_agreement(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());
        $headers = $this->authHeaders($this->tokenFor($user));
        $base = [
            'propertyTitle' => 'Precision Test Plot',
            'sellerName' => 'Seller Khan',
            'buyerName' => 'Buyer Ali',
            'transactionType' => 'Sale',
        ];

        $this->postJson('/api/agency/transactions', $base + [
            'agreementAmountPKR' => '1000.50',
            'advancePaymentPKR' => 100,
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('agreementAmountPKR');

        $this->postJson('/api/agency/transactions', $base + [
            'agreementAmountPKR' => 1000,
            'advancePaymentPKR' => 1001,
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('advancePaymentPKR');

        $deal = $this->createDeal($user, ['agreementAmountPKR' => 1000, 'advancePaymentPKR' => 100]);

        $this->patchJson('/api/agency/transactions/' . $deal['id'], [
            'advancePaymentPKR' => 200,
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('advancePaymentPKR');

        $this->patchJson('/api/agency/transactions/' . $deal['id'], [
            'agreementAmountPKR' => 50,
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('agreementAmountPKR');

        $this->patchJson('/api/agency/transactions/' . $deal['id'], [
            'commissionAmountPKR' => '10.25',
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('commissionAmountPKR');
    }

    public function test_finance_ledger_rejects_fractional_pkr_amounts(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());
        $deal = $this->createDeal($user);
        $headers = $this->authHeaders($this->tokenFor($user));
        $initialPaymentCount = TransactionPayment::where('transaction_id', $deal['id'])->count();

        $this->postJson("/api/agency/transactions/{$deal['id']}/payments", [
            'amount' => 100.50,
            'type' => 'advance',
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->postJson("/api/agency/transactions/{$deal['id']}/schedules", [
            'schedules' => [[
                'installment_no' => 1,
                'due_date' => now()->addWeek()->toDateString(),
                'amount' => 100.50,
            ]],
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('schedules.0.amount');

        $this->postJson("/api/agency/transactions/{$deal['id']}/commissions", [
            'amount' => 100.50,
            'rate' => 1.5,
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->assertSame($initialPaymentCount, TransactionPayment::where('transaction_id', $deal['id'])->count());
        $this->assertSame(0, PaymentSchedule::where('transaction_id', $deal['id'])->count());
        $this->assertSame(0, Commission::where('transaction_id', $deal['id'])->count());
    }

    public function test_payments_ledger_updates_collected_and_balance(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());
        $deal = $this->createDeal($user, ['agreementAmountPKR' => 10000000, 'advancePaymentPKR' => 0]);

        $this->postJson("/api/agency/transactions/{$deal['id']}/payments", [
            'amount' => 3000000,
            'type' => 'advance',
            'method' => 'bank_transfer',
            'reference' => 'BANK-123',
        ], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('totals.collected', 3000000)
            ->assertJsonPath('totals.balance', 7000000);

        $this->getJson("/api/agency/transactions/{$deal['id']}/payments", $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(200)
            ->assertJsonPath('totals.collected', 3000000)
            ->assertJsonPath('totals.agreement', 10000000)
            ->assertJsonPath('totals.balance', 7000000)
            ->assertJsonCount(1, 'payments');

        $transaction = Transaction::find($deal['id']);
        $this->assertEquals('Partially Paid', $transaction->paymentStatus);
        $this->assertEquals(3000000, (int) $transaction->advancePaymentPKR);
    }

    public function test_commission_payouts_record_actor_and_paid_status_is_terminal(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());
        $deal = $this->createDeal($user);
        $headers = $this->authHeaders($this->tokenFor($user));

        $created = $this->postJson("/api/agency/transactions/{$deal['id']}/commissions", [
            'amount' => 25000,
            'rate' => 1.5,
            'beneficiary_type' => 'agency',
            'notes' => 'Agency commission',
        ], $headers)->assertStatus(201)->json('commission');

        $accrued = $this->patchJson("/api/agency/commissions/{$created['id']}", [
            'status' => 'accrued',
        ], $headers)->assertOk()->json('commission');
        $this->assertNotNull($accrued['accrued_at']);

        $paid = $this->patchJson("/api/agency/commissions/{$created['id']}", [
            'status' => 'paid',
        ], $headers)->assertOk()->json('commission');
        $this->assertNotNull($paid['paid_at']);
        $this->assertSame((string) $user->id, (string) $paid['paid_by']);

        $this->patchJson("/api/agency/commissions/{$created['id']}", [
            'status' => 'pending',
        ], $headers)->assertStatus(409);

        $this->assertSame('paid', Commission::findOrFail($created['id'])->status);
    }

    public function test_refunds_cannot_exceed_the_amount_already_collected(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());
        $deal = $this->createDeal($user);
        $headers = $this->authHeaders($this->tokenFor($user));

        $this->postJson("/api/agency/transactions/{$deal['id']}/payments", [
            'amount' => 2500001,
            'type' => 'refund',
        ], $headers)->assertStatus(422)
            ->assertJsonPath('message', 'Refund cannot exceed the amount already collected.');

        $transaction = Transaction::findOrFail($deal['id']);
        $this->assertSame(2500000.0, (float) $transaction->totalPaid());

        $this->postJson("/api/agency/transactions/{$deal['id']}/payments", [
            'amount' => 1500000,
            'type' => 'refund',
        ], $headers)->assertStatus(201);

        $this->postJson("/api/agency/transactions/{$deal['id']}/payments", [
            'amount' => 1000001,
            'type' => 'refund',
        ], $headers)->assertStatus(422);

        $this->assertSame(1000000.0, (float) Transaction::findOrFail($deal['id'])->totalPaid());
    }

    public function test_installment_schedule_cannot_exceed_agreement_even_across_partial_updates(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());
        $headers = $this->authHeaders($this->tokenFor($user));
        $deal = $this->createDeal($user, ['agreementAmountPKR' => 10000000, 'advancePaymentPKR' => 0]);
        $url = '/api/agency/transactions/' . $deal['id'] . '/schedules';

        $this->postJson($url, ['schedules' => [
            ['installment_no' => 1, 'due_date' => now()->addDays(7)->toDateString(), 'amount' => 6000000],
        ]], $headers)->assertStatus(201);

        PaymentSchedule::where('transaction_id', $deal['id'])->update(['status' => 'paid']);

        $this->postJson($url, ['schedules' => [
            ['installment_no' => 1, 'due_date' => now()->addDays(7)->toDateString(), 'amount' => 1000000],
        ]], $headers)->assertStatus(422)->assertJsonValidationErrors('schedules');

        // The endpoint upserts partial schedule payloads, so the first
        // installment remains part of the total when a second is submitted.
        $this->postJson($url, ['schedules' => [
            ['installment_no' => 2, 'due_date' => now()->addDays(14)->toDateString(), 'amount' => 5000000],
        ]], $headers)->assertStatus(422)->assertJsonValidationErrors('schedules');

        $this->postJson($url, ['schedules' => [
            ['installment_no' => 3, 'due_date' => now()->addDays(21)->toDateString(), 'amount' => 1000000],
            ['installment_no' => 3, 'due_date' => now()->addDays(28)->toDateString(), 'amount' => 1000000],
        ]], $headers)->assertStatus(422)->assertJsonValidationErrors('schedules.1.installment_no');
    }

    public function test_payment_cannot_exceed_server_limits_or_use_negative_amounts(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());
        $deal = $this->createDeal($user);

        $this->postJson("/api/agency/transactions/{$deal['id']}/payments", ['amount' => -5], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $this->postJson("/api/agency/transactions/{$deal['id']}/payments", ['amount' => 999999999999999], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }
}

