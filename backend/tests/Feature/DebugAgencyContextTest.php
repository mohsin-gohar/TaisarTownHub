<?php

namespace Tests\Feature;

use App\Models\AgencyMember;
use App\Models\Commission;

class DebugAgencyContextTest extends EnterpriseTestCase
{
    public function test_debug(): void
    {
        $perms = ['finance.view', 'view_transactions', 'finance.create_transaction', 'create_transactions', 'finance.update_transaction', 'update_transactions', 'finance.manage_payments', 'manage_payments', 'view_commissions', 'view_my_commission', 'pay_commissions', 'void_transactions', 'approve_transactions', 'manage_deals'];

        [$userA, $agencyA] = $this->agencyUser($perms);
        [$userB, $agencyB] = $this->agencyUser($perms);

        fwrite(STDERR, "agencyA={$agencyA->id} agencyB={$agencyB->id}\n");
        fwrite(STDERR, 'memberships A: ' . json_encode(AgencyMember::where('user_id', $userA->id)->pluck('agency_id')->all()) . "\n");
        fwrite(STDERR, 'memberships B: ' . json_encode(AgencyMember::where('user_id', $userB->id)->pluck('agency_id')->all()) . "\n");
        fwrite(STDERR, 'resolve A: ' . var_export($userA->resolveAgencyId(), true) . "\n");
        fwrite(STDERR, 'resolve B: ' . var_export($userB->resolveAgencyId(), true) . "\n");

        $deal = $this->postJson('/api/agency/transactions', [
            'propertyTitle' => 'Debug Plot',
            'sellerName' => 'S', 'buyerName' => 'B',
            'agreementAmountPKR' => 1000, 'advancePaymentPKR' => 0,
        ], $this->authHeaders($this->tokenFor($userA)))->assertStatus(201)->json('transaction');
        fwrite(STDERR, 'deal agency: ' . var_export($deal['agency_id'] ?? null, true) . "\n");
        $created = $this->postJson("/api/agency/transactions/{$deal['id']}/commissions", ['amount' => 100], $this->authHeaders($this->tokenFor($userA)));
        fwrite(STDERR, 'commission create status: ' . $created->status() . ' body: ' . substr($created->getContent(), 0, 300) . "\n");
        $commission = Commission::first();
        fwrite(STDERR, 'commission agency: ' . $commission->agency_id . ' id=' . $commission->id . "\n");

        $resp = $this->putJson("/api/agency/commissions/{$commission->id}", ['status' => 'paid'], $this->authHeaders($this->tokenFor($userB)));
        fwrite(STDERR, 'PUT by B status: ' . $resp->status() . ' body: ' . substr($resp->getContent(), 0, 300) . "\n");

        $this->assertTrue(true);
    }
}
