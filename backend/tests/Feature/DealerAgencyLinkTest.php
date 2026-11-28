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

    public function test_agency_scoped_publisher_cannot_delete_another_agencys_dealer(): void
    {
        [$user, $agency] = $this->agencyUser(['manage_dealers'], 'agency_owner');
        [, $foreign] = $this->agencyUser([], 'agent');

        // A dealer owned by the foreign agency, created by a platform admin.
        $foreignDealer = $this->postJson('/api/dealers', [
            'id' => 'deal-' . Str::random(8),
            'fullName' => 'Foreign Delete Target',
            'phone' => '03001112222',
            'agencyId' => $foreign->id,
        ], $this->authHeaders($this->tokenFor($this->makeUser('super_admin'))))
            ->assertCreated()
            ->json('id');

        // Cross-tenant delete must be rejected and must not remove the row.
        $this->deleteJson("/api/dealers/{$foreignDealer}", [], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(404);

        $this->assertDatabaseHas('dealers', ['id' => $foreignDealer]);
    }
}
