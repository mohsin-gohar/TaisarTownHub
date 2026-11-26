<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Dealer;

class DealerControllerSecurityTest extends EnterpriseTestCase
{
    private function makeDealer(Agency $agency, string $id, string $status = 'Active'): Dealer
    {
        return Dealer::create([
            'id' => $id,
            'fullName' => 'Test Dealer ' . $id,
            'phone' => '03001234567',
            'agency_id' => $agency->id,
            'status' => $status,
        ]);
    }

    public function test_platform_dealer_manager_cannot_delete_a_dealer_outside_the_selected_agency(): void
    {
        $manager = $this->makeUser('super_admin');
        $agencyA = $this->makeAgency('Selected Dealer Agency');
        $agencyB = $this->makeAgency('Other Dealer Agency');
        $dealerB = $this->makeDealer($agencyB, 'dealer_other_agency');

        $headers = $this->authHeaders($this->tokenFor($manager));
        $headers['X-Agency-Id'] = $agencyA->id;

        $this->deleteJson('/api/dealers/' . $dealerB->id, $headers)->assertNotFound();

        $this->assertNull($dealerB->fresh()->deleted_at);
    }

    public function test_public_dealer_directory_hides_inactive_dealers_and_unverified_agencies(): void
    {
        $verifiedAgency = $this->makeAgency('Verified Dealer Agency');
        $unverifiedAgency = $this->makeAgency('Unverified Dealer Agency');
        $unverifiedAgency->verification_status = 'pending';
        $unverifiedAgency->save();

        $visible = $this->makeDealer($verifiedAgency, 'dealer_public_visible');
        $hiddenAgency = $this->makeDealer($unverifiedAgency, 'dealer_unverified_agency');
        $inactive = $this->makeDealer($verifiedAgency, 'dealer_inactive', 'Inactive');

        $this->asGuest();
        $response = $this->getJson('/api/dealers')->assertOk();

        $response->assertJsonFragment(['id' => $visible->id]);
        $response->assertJsonMissing(['id' => $hiddenAgency->id]);
        $response->assertJsonMissing(['id' => $inactive->id]);
        $this->getJson('/api/dealers/' . $hiddenAgency->id)->assertNotFound();
    }

    public function test_platform_publisher_must_assign_a_dealer_to_an_agency(): void
    {
        $admin = $this->makeUser('super_admin');
        $headers = $this->authHeaders($this->tokenFor($admin));

        $this->postJson('/api/dealers', [
            'id' => 'dealer_without_agency',
            'fullName' => 'Unassigned Dealer',
            'phone' => '03001112223',
        ], $headers)->assertStatus(422);

        $this->assertDatabaseMissing('dealers', ['id' => 'dealer_without_agency']);
    }

    public function test_platform_cannot_reassign_or_delete_a_dealer_with_linked_properties(): void
    {
        $admin = $this->makeUser('super_admin');
        $agencyA = $this->makeAgency('Target Agency');
        $agencyB = $this->makeAgency('Current Agency');
        $dealer = $this->makeDealer($agencyB, 'dealer_with_property');
        $this->makePublicProperty($agencyB, [
            'title' => 'Dealer Linked Property',
            'assignedDealerId' => $dealer->id,
        ]);
        $headers = $this->authHeaders($this->tokenFor($admin));

        $this->patchJson('/api/dealers/' . $dealer->id, ['agencyId' => $agencyA->id], $headers)
            ->assertStatus(409);
        $this->deleteJson('/api/dealers/' . $dealer->id, $headers)
            ->assertStatus(409);

        $this->assertSame($agencyB->id, $dealer->fresh()->agency_id);
        $this->assertNull($dealer->fresh()->deleted_at);
    }
}
