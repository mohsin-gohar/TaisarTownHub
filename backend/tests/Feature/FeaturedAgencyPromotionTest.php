<?php

namespace Tests\Feature;

use App\Models\AgencyPromotion;
use App\Models\Property;
use App\Models\SystemSetting;
use App\Services\AgencyPromotionService;
use Illuminate\Support\Carbon;
use Tests\Feature\EnterpriseTestCase;

/**
 * Featured-agency advertising: eligibility, slot limits, truthful badges,
 * expiry, ordering, tenant isolation and property priority.
 */
class FeaturedAgencyPromotionTest extends EnterpriseTestCase
{
    private function platformAdmin(): array
    {
        $this->seedPlatformRole('super_admin');
        $admin = $this->makeUser('super_admin', ['email' => 'promo-admin-' . bin2hex(random_bytes(4)) . '@example.test']);

        return [$admin, $this->authHeaders($this->tokenFor($admin))];
    }

    public function test_admin_can_place_only_approved_verified_agencies(): void
    {
        [$admin, $headers] = $this->platformAdmin();
        $eligible = $this->makeAgency('Eligible Agency');

        $created = $this->postJson('/api/admin/featured-agencies', [
            'agency_id' => $eligible->id,
            'duration_days' => 30,
            'position' => 1,
            'notes' => 'Free launch spotlight',
        ], $headers)
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('placement.agency_id', $eligible->id)
            ->json('placement');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'featured_agency.create',
            'entity_id' => (string) $created['id'],
        ]);

        // The homepage shows the placement and a truthful "Featured" badge.
        $this->asGuest();
        $public = $this->getJson('/api/agencies/featured')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('featured', true)
            ->json();

        $this->assertSame($eligible->id, $public['agencies'][0]['id']);
        $this->assertSame('Featured', $public['agencies'][0]['badge']);
        $this->assertFalse($public['agencies'][0]['is_paid']);

        // Unverified / suspended agencies are rejected server-side.
        $unverified = $this->makeAgency('Unverified Agency');
        $unverified->verification_status = 'unverified';
        $unverified->save();

        $this->postJson('/api/admin/featured-agencies', [
            'agency_id' => $unverified->id,
            'duration_days' => 30,
        ], $headers)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $suspended = $this->makeAgency('Suspended Agency');
        $suspended->status = 'suspended';
        $suspended->save();

        $this->postJson('/api/admin/featured-agencies', [
            'agency_id' => $suspended->id,
            'duration_days' => 30,
        ], $headers)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(1, AgencyPromotion::count());
    }

    public function test_payment_fields_are_neutralised_during_the_free_launch(): void
    {
        [$admin, $headers] = $this->platformAdmin();
        $agency = $this->makeAgency('Paying Agency');

        // A caller may still send legacy payment fields. They must never turn
        // a free placement into a paid advertisement or a Sponsored badge.
        $placement = $this->postJson('/api/admin/featured-agencies', [
            'agency_id' => $agency->id,
            'duration_days' => 7,
            'price' => 5000,
            'payment_status' => 'paid',
            'payment_method' => 'manual',
            'payment_reference' => 'CASH-001',
        ], $headers)
            ->assertStatus(201)
            ->json('placement');

        $this->assertSame('0.00', (string) $placement['price']);
        $this->assertSame('waived', $placement['payment_status']);
        $this->assertNull($placement['payment_method']);
        $this->assertNull($placement['payment_reference']);

        $stored = AgencyPromotion::findOrFail($placement['id']);
        $this->assertSame(0.0, (float) $stored->price);
        $this->assertSame('waived', $stored->payment_status);
        $this->assertNull($stored->payment_method);
        $this->assertNull($stored->payment_reference);

        $this->asGuest();
        $public = $this->getJson('/api/agencies/featured')->assertStatus(200)->json();
        // Free launch: every placement is complimentary and labelled Featured.
        $this->assertSame('Featured', $public['agencies'][0]['badge']);
        $this->assertFalse($public['agencies'][0]['is_paid']);
    }

    public function test_slot_limit_is_enforced_and_can_be_raised(): void
    {
        SystemSetting::set('promotions.max_slots', 1);
        [$admin, $headers] = $this->platformAdmin();

        $first = $this->makeAgency('First Slot Agency');
        $second = $this->makeAgency('Second Slot Agency');

        $this->postJson('/api/admin/featured-agencies', [
            'agency_id' => $first->id,
            'duration_days' => 30,
        ], $headers)->assertStatus(201);

        $this->postJson('/api/admin/featured-agencies', [
            'agency_id' => $second->id,
            'duration_days' => 30,
        ], $headers)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        SystemSetting::set('promotions.max_slots', 2);

        $this->postJson('/api/admin/featured-agencies', [
            'agency_id' => $second->id,
            'duration_days' => 30,
        ], $headers)->assertStatus(201);

        $this->assertSame(2, AgencyPromotion::where('status', 'active')->count());
    }

    public function test_expiry_removes_homepage_advertisement_without_touching_agency_data(): void
    {
        $agency = $this->makeAgency('Expiring Agency');
        $property = $this->makePublicProperty($agency);

        $promotion = AgencyPromotion::create([
            'agency_id' => $agency->id,
            'status' => 'active',
            'position' => 1,
            'starts_at' => Carbon::now()->subDays(2),
            'ends_at' => Carbon::now()->subDay(),
            'price' => 0,
            'currency' => 'PKR',
            'payment_status' => 'waived',
        ]);

        $expired = app(AgencyPromotionService::class)->expireDue();
        $this->assertSame(1, $expired);
        $this->assertSame('expired', $promotion->fresh()->status);

        // Agency and listing survive unchanged; the fallback roster is not
        // labelled as featured.
        $this->assertDatabaseHas('agencies', ['id' => $agency->id]);
        $this->assertDatabaseHas('properties', ['id' => $property->id]);

        $this->asGuest();
        $public = $this->getJson('/api/agencies/featured')->assertStatus(200)->json();
        $this->assertFalse($public['featured']);
        foreach ($public['agencies'] as $row) {
            $this->assertNull($row['badge']);
        }
    }

    public function test_admin_can_reorder_live_placements(): void
    {
        [$admin, $headers] = $this->platformAdmin();
        $a = $this->makeAgency('Order Agency A');
        $b = $this->makeAgency('Order Agency B');

        $first = $this->postJson('/api/admin/featured-agencies', [
            'agency_id' => $a->id,
            'duration_days' => 30,
        ], $headers)->assertStatus(201)->json('placement');

        $second = $this->postJson('/api/admin/featured-agencies', [
            'agency_id' => $b->id,
            'duration_days' => 30,
        ], $headers)->assertStatus(201)->json('placement');

        $this->putJson('/api/admin/featured-agencies/order', [
            'ids' => [$second['id'], $first['id']],
        ], $headers)->assertStatus(200)->assertJsonPath('success', true);

        $this->assertSame(1, AgencyPromotion::find($second['id'])->position);
        $this->assertSame(2, AgencyPromotion::find($first['id'])->position);

        // The public roster follows the configured order.
        $this->asGuest();
        $public = $this->getJson('/api/agencies/featured')->assertStatus(200)->json();
        $this->assertSame($b->id, $public['agencies'][0]['id']);
        $this->assertSame($a->id, $public['agencies'][1]['id']);
    }

    public function test_promoted_agency_listings_are_prioritised_on_the_public_marketplace(): void
    {
        [$admin, $headers] = $this->platformAdmin();
        $promoted = $this->makeAgency('Promoted Agency');
        $ordinary = $this->makeAgency('Ordinary Agency');

        $older = $this->makePublicProperty($promoted, ['title' => 'Promoted Older Plot']);
        Carbon::setTestNow(Carbon::now()->addMinute());
        $newer = $this->makePublicProperty($ordinary, ['title' => 'Ordinary Newer Plot']);
        Carbon::setTestNow();

        $this->postJson('/api/admin/featured-agencies', [
            'agency_id' => $promoted->id,
            'duration_days' => 30,
            'position' => 1,
        ], $headers)->assertStatus(201);

        $this->asGuest();
        $rows = $this->getJson('/api/properties')->assertStatus(200)->json();

        $this->assertSame($older->id, $rows[0]['id'], 'promoted agency listing should be first');
        $this->assertSame('Featured', $rows[0]['agency_featured_badge']);
        $this->assertSame($newer->id, $rows[1]['id']);
        $this->assertArrayNotHasKey('agency_featured_badge', $rows[1]);
    }

    public function test_agency_members_cannot_manage_placements(): void
    {
        [$agent] = $this->agencyUser(['agency.view', 'properties.view']);
        $headers = $this->authHeaders($this->tokenFor($agent));
        $agency = $this->makeAgency('Target Agency');

        $this->getJson('/api/admin/featured-agencies', $headers)->assertStatus(403);
        $this->postJson('/api/admin/featured-agencies', [
            'agency_id' => $agency->id,
            'duration_days' => 30,
        ], $headers)->assertStatus(403);
        $this->putJson('/api/admin/featured-agencies/order', ['ids' => [1]], $headers)->assertStatus(403);
        $this->assertSame(0, AgencyPromotion::count());
    }
    public function test_future_scheduled_placement_reserves_capacity_before_it_goes_live(): void
    {
        SystemSetting::set('promotions.max_slots', 1);
        [$admin, $headers] = $this->platformAdmin();

        $first = $this->makeAgency('Scheduled Slot Agency');
        $second = $this->makeAgency('Next Scheduled Slot Agency');
        $startsAt = Carbon::now()->addDay();
        $endsAt = $startsAt->copy()->addDays(30);

        $this->postJson('/api/admin/featured-agencies', [
            'agency_id' => $first->id,
            'duration_days' => 30,
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt->toDateTimeString(),
            'status' => 'active',
        ], $headers)->assertStatus(201);

        $this->assertSame([], app(AgencyPromotionService::class)->publicFeatured());
        $this->assertSame(0, app(AgencyPromotionService::class)->stats()['available_slots']);

        $this->postJson('/api/admin/featured-agencies', [
            'agency_id' => $second->id,
            'duration_days' => 30,
        ], $headers)->assertStatus(422);
    }

    public function test_pending_placements_cannot_be_activated_twice_for_the_same_agency(): void
    {
        [$admin] = $this->platformAdmin();
        $agency = $this->makeAgency('Single Active Placement Agency');
        $service = app(AgencyPromotionService::class);

        $first = $service->promote($agency, ['status' => 'pending'], $admin);
        $second = $service->promote($agency, ['status' => 'pending'], $admin);

        $service->update($first, ['status' => 'active'], $admin);

        try {
            $service->update($second, ['status' => 'active'], $admin);
            $this->fail('A second active placement for the same agency must be rejected.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('already has a current featured placement', $exception->getMessage());
        }

        $this->assertSame(1, AgencyPromotion::query()->current()->where('agency_id', $agency->id)->count());
    }

    public function test_reorder_requires_the_complete_current_placement_set(): void
    {
        [$admin] = $this->platformAdmin();
        $service = app(AgencyPromotionService::class);
        $first = $this->makeAgency('Complete Reorder Agency One');
        $second = $this->makeAgency('Complete Reorder Agency Two');

        $one = $service->promote($first, [], $admin);
        $service->promote($second, [], $admin);

        $this->expectException(\DomainException::class);
        $service->reorder([$one->id], $admin);
    }

}
