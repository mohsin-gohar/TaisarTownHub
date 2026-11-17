<?php

namespace Tests\Feature;

use App\Models\Transaction;

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
        $deal = $this->createDeal($user);

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

    public function test_void_requires_a_reason(): void
    {
        [$user] = $this->agencyUser($this->financePermissions());
        $deal = $this->createDeal($user);

        $this->postJson("/api/agency/transactions/{$deal['id']}/void", [], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
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

