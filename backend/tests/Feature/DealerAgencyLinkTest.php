<?php

namespace Tests\Feature;

use Illuminate\Support\Str;

/**
 * `dealers.agency_id` is the ownership link used when assigning a dealer to an
 * agency listing. The column existed and was backfilled, but dealer create /
 * update dropped it (not fillable), so every new dealer was permanently
 * unlinked and rejected by PropertyController::assertDealerInAgency().
 */
class DealerAgencyLinkTest extends EnterpriseTestCase
{
    public function test_platform_created_dealer_can_be_linked_to_an_agency(): void
    {
        $admin = $this->makeUser('super_admin');
        $agency = $this->makeAgency('Dealer Link Agency');
        $dealerId = 'deal-' . Str::random(8);

        $response = $this->postJson('/api/dealers', [
            'id' => $dealerId,
            'fullName' => 'Linked Dealer',
            'phone' => '03001234567',
            'agencyId' => $agency->id,
        ], $this->authHeaders($this->tokenFor($admin)))->assertCreated();

        $this->assertSame($agency->id, $response->json('agency_id'));
        $this->assertDatabaseHas('dealers', [
            'id' => $dealerId,
            'agency_id' => $agency->id,
        ]);
    }

    public function test_fixed_commission_can_exceed_one_hundred_but_percentage_is_capped(): void
    {
        $admin = $this->makeUser('super_admin');
        $headers = $this->authHeaders($this->tokenFor($admin));

        $fixed = $this->postJson('/api/dealers', [
            'id' => 'deal-' . Str::random(8),
            'fullName' => 'Fixed Commission Dealer',
            'phone' => '03001234567',
            'commissionType' => 'Fixed',
            'commissionRate' => 20000,
        ], $headers)->assertCreated();

        $this->assertSame(20000.0, (float) $fixed->json('commissionRate'));

        $this->postJson('/api/dealers', [
            'id' => 'deal-' . Str::random(8),
            'fullName' => 'Invalid Percentage Dealer',
            'phone' => '03007654321',
            'commissionType' => 'Percentage',
            'commissionRate' => 101,
        ], $headers)->assertStatus(422)
            ->assertJsonValidationErrors('commissionRate');

        $percentage = $this->postJson('/api/dealers', [
            'id' => 'deal-' . Str::random(8),
            'fullName' => 'Valid Percentage Dealer',
            'phone' => '03001112222',
            'commissionType' => 'Percentage',
            'commissionRate' => 2.5,
        ], $headers)->assertCreated();

        $this->assertSame(2.5, (float) $percentage->json('commissionRate'));
    }

    public function test_agency_scoped_publisher_is_confined_to_its_own_agency(): void
    {
        [$user, $agency] = $this->agencyUser(['manage_dealers'], 'agency_owner');
        [, $foreign] = $this->agencyUser([], 'agent');

        // Creation: a client-supplied foreign agency id is ignored, the
        // dealer is linked to the publisher's own agency.
        $created = $this->postJson('/api/dealers', [
            'id' => 'deal-' . Str::random(8),
            'fullName' => 'Scoped Dealer',
            'phone' => '03001234567',
            'agencyId' => $foreign->id,
        ], $this->authHeaders($this->tokenFor($user)))->assertCreated();

        $this->assertSame($agency->id, $created->json('agency_id'));

        // Update: a dealer outside the acting agency is not reachable.
        $foreignDealer = $this->postJson('/api/dealers', [
            'id' => 'deal-' . Str::random(8),
            'fullName' => 'Foreign Dealer',
            'phone' => '03007654321',
            'agencyId' => $foreign->id,
        ], $this->authHeaders($this->tokenFor($this->makeUser('super_admin'))))
            ->assertCreated()
            ->json('id');

        $this->patchJson(
            "/api/dealers/{$foreignDealer}",
            ['fullName' => 'Hijacked Dealer'],
            $this->authHeaders($this->tokenFor($user))
        )->assertStatus(404);
    }
}
