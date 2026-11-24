<?php

namespace Tests\Feature;

use App\Models\AgencySubscription;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\EnterpriseTestCase;

/**
 * G-8 — server-side plan-limit enforcement. The API (never the browser)
 * decides when an agency has exhausted its listings, staff, storage or lead
 * quota. Limit 0 / absent = unlimited, matching the usage display.
 */
class PlanLimitEnforcementTest extends EnterpriseTestCase
{
    private function agencyWithLimits(array $limits): array
    {
        $agency = $this->makeAgency('Limited Agency');
        $plan = SubscriptionPlan::create([
            'key' => 'plan_lim_' . bin2hex(random_bytes(4)),
            'name' => 'Limit Test Plan',
            'price' => 0,
            'limits' => $limits,
        ]);
        AgencySubscription::create([
            'agency_id' => $agency->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        return [$agency, $plan];
    }

    public function test_listing_limit_blocks_creation_until_raised_by_override(): void
    {
        [$agency, $plan] = $this->agencyWithLimits(['listings' => 1]);
        [$user] = $this->agencyUser(['properties.create'], 'agent', $agency);

        $payload = [
            'title' => 'Limit Plot',
            'pricePKR' => 5000000,
            'phase' => '1',
            'sector' => 'A',
            'sizeSqYards' => 100,
        ];

        $h = fn () => $this->authHeaders($this->tokenFor($user));

        $this->postJson('/api/properties', $payload, $h())
            ->assertStatus(201);

        $this->postJson('/api/properties', array_merge($payload, ['title' => 'Limit Plot 2']), $h())
            ->assertStatus(402)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'plan_limit_reached')
            ->assertJsonPath('limit', 1)
            ->assertJsonPath('used', 1);

        // Per-agency override raises the quota without touching the plan.
        AgencySubscription::where('agency_id', $agency->id)
            ->update(['limits_override' => ['listings' => 3]]);

        $this->postJson('/api/properties', array_merge($payload, ['title' => 'Limit Plot 3']), $h())
            ->assertStatus(201);
    }

    public function test_zero_and_missing_limits_mean_unlimited(): void
    {
        [$agency] = $this->agencyWithLimits(['listings' => 0]);
        [$user] = $this->agencyUser(['properties.create'], 'agent', $agency);
        $h = fn () => $this->authHeaders($this->tokenFor($user));

        for ($i = 1; $i <= 3; $i++) {
            $this->postJson('/api/properties', [
                'title' => "Unlimited Plot {$i}",
                'pricePKR' => 1000000,
                'phase' => '1',
                'sector' => 'B',
                'sizeSqYards' => 80,
            ], $h())->assertStatus(201);
        }
    }

    public function test_storage_quota_does_not_block_document_uploads(): void
    {
        Storage::fake('documents');
        [$agency] = $this->agencyWithLimits(['storage_mb' => 1]);
        [$user] = $this->agencyUser(['documents.view', 'documents.upload'], 'document_manager', $agency);
        $h = fn () => $this->authHeaders($this->tokenFor($user));

        // 600 KB each → first fits in 1 MB, second would exceed it.
        $file = UploadedFile::fake()->create('report.pdf', 600, 'application/pdf');

        $this->post('/api/agency/documents', ['file' => $file, 'title' => 'Report One'], $h())
            ->assertStatus(201);

        $second = UploadedFile::fake()->create('report2.pdf', 600, 'application/pdf');
        $this->post('/api/agency/documents', ['file' => $second, 'title' => 'Report Two'], $h())
            ->assertStatus(201);

        $this->assertSame(
            2,
            \App\Models\Document::where('agency_id', $agency->id)->count()
        );
    }

    public function test_staff_invite_counts_pending_invitations_against_the_quota(): void
    {
        // staff=2: the owner occupies one seat, one invitation fits.
        [$agency] = $this->agencyWithLimits(['staff' => 2]);
        $role = $this->makeRole('agency', 'invite-target', ['agency.view']);
        [$owner] = $this->agencyUser(['agency.view', 'agency.manage_staff'], 'agency_manager', $agency);
        $h = fn () => $this->authHeaders($this->tokenFor($owner));

        $this->postJson('/api/agency/members/invite', [
            'email' => 'invitee-one@example.test',
            'role_id' => $role->id,
        ], $h())->assertStatus(201);

        // The owner (member) + the pending invitation already fill both seats.
        $this->postJson('/api/agency/members/invite', [
            'email' => 'invitee-two@example.test',
            'role_id' => $role->id,
        ], $h())
            ->assertStatus(402)
            ->assertJsonPath('code', 'plan_limit_reached')
            ->assertJsonPath('limit', 2)
            ->assertJsonPath('used', 2);
    }

    public function test_lead_limit_blocks_public_inquiries_with_a_visitor_safe_message(): void
    {
        [$agency] = $this->agencyWithLimits(['leads' => 1]);
        $property = $this->makePublicProperty($agency);

        $payload = [
            'fullName' => 'Public Buyer',
            'phone' => '0300-1112223',
            'message' => 'I am interested in this plot, please contact me.',
            'propertyId' => $property->id,
        ];

        $this->asGuest();
        $this->postJson('/api/inquiries', $payload + $this->solveInquiryCaptcha())->assertStatus(201);

        $this->asGuest();
        $blocked = $this->postJson('/api/inquiries', [
            'fullName' => 'Second Buyer',
            'phone' => '0300-1112224',
            'message' => 'Me too, please share the details.',
            'propertyId' => $property->id,
        ] + $this->solveInquiryCaptcha())
            ->assertStatus(402)
            ->assertJsonPath('code', 'plan_limit_reached')
            // No internal quota numbers leak to public visitors.
            ->assertJsonMissing(['limit' => 1])
            ->assertJsonMissing(['used' => 1]);
        $this->assertStringContainsString('not accepting new inquiries', $blocked->getContent());

        // Only the first lead reached the pipeline.
        $this->assertSame(1, \App\Models\Inquiry::where('property_id', $property->id)->count());
    }
}
