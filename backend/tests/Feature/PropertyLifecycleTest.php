<?php

namespace Tests\Feature;

use App\Models\Property;

/**
 * Property lifecycle: draft → pending → approved/rejected, agency ownership,
 * moderation permissions, and the re-approval staging flow.
 */
class PropertyLifecycleTest extends EnterpriseTestCase
{
    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Plot in Taisar Town ' . substr(md5(uniqid('', true)), 0, 6),
            'pricePKR' => 12500000,
            'phase' => '1',
            'sector' => 'A',
            'sizeSqYards' => 120,
            'description' => 'A test listing',
            'type' => 'Plot',
        ], $overrides);
    }

    private function agentPermissions(): array
    {
        return [
            'view_properties', 'create_properties', 'edit_properties', 'properties.view',
            'properties.create', 'properties.update', 'properties.publish', 'properties.delete',
        ];
    }

    private function moderatorPermissions(): array
    {
        return ['view_properties', 'properties.view', 'properties.approve', 'properties.feature'];
    }

    public function test_agency_cannot_self_assign_featured_status_when_creating_or_updating_listing(): void
    {
        [$agent] = $this->agencyUser($this->agentPermissions());
        $headers = $this->authHeaders($this->tokenFor($agent));

        $created = $this->postJson('/api/properties', $this->basePayload([
            'title' => 'Cannot Self Feature Listing',
            'isFeatured' => true,
            'submit' => true,
        ]), $headers)
            ->assertStatus(201)
            ->assertJsonPath('isFeatured', false)
            ->assertJsonPath('featured_status', 'none');

        $id = $created->json('id');

        $this->patchJson("/api/properties/{$id}", [
            'isFeatured' => true,
        ], $headers)
            ->assertStatus(200)
            ->assertJsonPath('isFeatured', false)
            ->assertJsonPath('featured_status', 'none');

        $this->assertDatabaseHas('properties', [
            'id' => $id,
            'isFeatured' => false,
            'featured_status' => 'none',
        ]);
    }

    public function test_member_creates_submits_and_moderator_approves_listing(): void
    {
        [$agent, $agency] = $this->agencyUser($this->agentPermissions());
        [$moderator] = $this->agencyUser($this->moderatorPermissions(), 'moderator');

        $create = $this->postJson('/api/properties', $this->basePayload([
            'title' => 'Public Marketplace Plot',
            'submit' => true,
        ]), $this->authHeaders($this->tokenFor($agent)));

        $create->assertStatus(201)
            ->assertJsonPath('approval_status', 'pending')
            ->assertJsonPath('visibility', 'private');

        $id = $create->json('id');
        $this->assertNotEmpty($id);

        // Pending listings must not appear in the public marketplace.
        // Feature tests reuse one app instance — forget the authenticated guard
        // so this request is genuinely anonymous (production boots fresh).
        $this->app['auth']->forgetGuards();
        $publicTitles = array_column($this->getJson('/api/properties')->json(), 'title');
        $this->assertNotContains('Public Marketplace Plot', $publicTitles);

        $queue = $this->getJson('/api/moderation/queue', $this->authHeaders($this->tokenFor($moderator)))
            ->assertStatus(200)
            ->json('queue');

        $this->assertContains($id, array_column($queue, 'id'));

        $this->postJson("/api/properties/{$id}/approve", [], $this->authHeaders($this->tokenFor($moderator)))
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('property.approval_status', 'approved')
            ->assertJsonPath('property.visibility', 'public');

        $this->app['auth']->forgetGuards();
        $publicTitlesAfter = array_column($this->getJson('/api/properties')->json(), 'title');
        $this->assertContains('Public Marketplace Plot', $publicTitlesAfter);

        // Audit trail captured the transition.
        $this->assertDatabaseHas('audit_logs', ['action' => 'property.approve', 'entity_id' => $id]);
    }

    public function test_public_marketplace_rejects_rent_and_hides_rental_records(): void
    {
        [$agent, $agency] = $this->agencyUser($this->agentPermissions());

        $this->postJson('/api/properties', $this->basePayload([
            'listing_purpose' => 'rent',
        ]), $this->authHeaders($this->tokenFor($agent)))
            ->assertStatus(422);

        $rent = Property::create(array_merge($this->basePayload([
            'title' => 'Historical Rental',
            'listing_purpose' => 'rent',
        ]), [
            'id' => 'prop-rent-' . substr(md5(uniqid('', true)), 0, 8),
            'agency_id' => $agency->id,
            'approval_status' => 'approved',
            'visibility' => 'public',
        ]));

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/properties/{$rent->id}")
            ->assertStatus(404);
        $this->getJson('/api/properties?purpose=rent')
            ->assertOk()
            ->assertJsonMissing(['title' => 'Historical Rental']);
    }

    public function test_public_property_detail_never_serializes_buyer_seller_or_internal_fields(): void
    {
        [, $agency] = $this->agencyUser($this->agentPermissions());

        $property = Property::create(array_merge($this->basePayload([
            'title' => 'Sale Privacy Test',
            'listing_purpose' => 'sale',
        ]), [
            'id' => 'prop-public-' . substr(md5(uniqid('', true)), 0, 8),
            'agency_id' => $agency->id,
            'approval_status' => 'approved',
            'visibility' => 'public',
            'purchasePricePKR' => 1000000,
            'sellingPricePKR' => 1500000,
            'internalNotes' => 'Secret internal note',
            'buyerId' => 'buyer-secret',
            'buyerName' => 'Private Buyer',
            'sellerId' => 'seller-secret',
            'sellerName' => 'Private Seller',
        ]));

        $this->app['auth']->forgetGuards();
        $response = $this->getJson("/api/properties/{$property->id}")
            ->assertOk();

        $body = $response->getContent();
        foreach (['purchasePricePKR', 'sellingPricePKR', 'internalNotes', 'buyerId', 'buyerName', 'sellerId', 'sellerName', 'pending_changes'] as $key) {
            $this->assertStringNotContainsString('"' . $key . '"', $body);
        }
    }

    public function test_agent_cannot_approve_own_listing(): void
    {
        [$agent] = $this->agencyUser($this->agentPermissions());

        $id = $this->postJson('/api/properties', $this->basePayload(['submit' => true]), $this->authHeaders($this->tokenFor($agent)))
            ->assertStatus(201)
            ->json('id');

        $this->postJson("/api/properties/{$id}/approve", [], $this->authHeaders($this->tokenFor($agent)))
            ->assertStatus(403);
    }

    public function test_agent_cannot_manage_another_agencys_listing(): void
    {
        [$ownerAgent, $agencyA] = $this->agencyUser($this->agentPermissions());
        [$rivalAgent] = $this->agencyUser($this->agentPermissions());

        $id = $this->postJson('/api/properties', $this->basePayload(), $this->authHeaders($this->tokenFor($ownerAgent)))
            ->assertStatus(201)
            ->json('id');

        // Rival agent (different agency) must not even learn the listing exists.
        $this->patchJson("/api/properties/{$id}", ['title' => 'Hijacked'], $this->authHeaders($this->tokenFor($rivalAgent)))
            ->assertStatus(404);

        $this->assertEquals($agencyA->id, Property::find($id)->agency_id);
        $this->assertNotEquals('Hijacked', Property::find($id)->title);
    }

    public function test_editing_an_approved_listing_stages_changes_for_reapproval(): void
    {
        [$agent, $agency] = $this->agencyUser($this->agentPermissions());
        [$moderator] = $this->agencyUser($this->moderatorPermissions(), 'moderator', $agency);

        $id = $this->postJson('/api/properties', $this->basePayload([
            'title' => 'Original Title',
            'submit' => true,
        ]), $this->authHeaders($this->tokenFor($agent)))->assertStatus(201)->json('id');

        $this->postJson("/api/properties/{$id}/approve", [], $this->authHeaders($this->tokenFor($moderator)))
            ->assertStatus(200);

        $this->patchJson("/api/properties/{$id}", ['title' => 'Staged New Title'], $this->authHeaders($this->tokenFor($agent)))
            ->assertStatus(200)
            ->assertJsonPath('approval_status', 'pending')
            ->assertJsonPath('pending_changes.title', 'Staged New Title');

        $property = Property::find($id);
        $this->assertEquals('Original Title', $property->title, 'live copy must stay unchanged while re-approval is pending');
        $this->assertEquals('Staged New Title', $property->pending_changes['title']);
    }

    public function test_rejecting_a_reapproval_edit_keeps_the_live_listing_approved(): void
    {
        [$agent, $agency] = $this->agencyUser($this->agentPermissions());
        [$moderator] = $this->agencyUser($this->moderatorPermissions(), 'moderator', $agency);

        $id = $this->postJson('/api/properties', $this->basePayload([
            'title' => 'Live Listing Title',
            'submit' => true,
        ]), $this->authHeaders($this->tokenFor($agent)))->assertStatus(201)->json('id');

        $this->postJson("/api/properties/{$id}/approve", [], $this->authHeaders($this->tokenFor($moderator)))
            ->assertStatus(200);

        $this->patchJson("/api/properties/{$id}", ['title' => 'Rejected Edit Title'], $this->authHeaders($this->tokenFor($agent)))
            ->assertStatus(200);

        $this->postJson("/api/properties/{$id}/reject", ['reason' => 'Title is misleading'], $this->authHeaders($this->tokenFor($moderator)))
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $property = Property::find($id);
        $this->assertEquals('approved', $property->approval_status);
        $this->assertEquals('public', $property->visibility);
        $this->assertEquals('Live Listing Title', $property->title);
        $this->assertNull($property->pending_changes);

        $publicTitles = array_column($this->getJson('/api/properties')->json(), 'title');
        $this->assertContains('Live Listing Title', $publicTitles);
    }

    public function test_rejecting_a_first_submission_hides_it_and_records_the_reason(): void
    {
        [$agent, $agency] = $this->agencyUser($this->agentPermissions());
        [$moderator] = $this->agencyUser($this->moderatorPermissions(), 'moderator', $agency);

        $id = $this->postJson('/api/properties', $this->basePayload([
            'title' => 'Rejected First Submission',
            'submit' => true,
        ]), $this->authHeaders($this->tokenFor($agent)))->assertStatus(201)->json('id');

        $this->postJson("/api/properties/{$id}/reject", ['reason' => 'Missing documents'], $this->authHeaders($this->tokenFor($moderator)))
            ->assertStatus(200)
            ->assertJsonPath('property.approval_status', 'rejected')
            ->assertJsonPath('property.rejection_reason', 'Missing documents');

        $property = Property::find($id);
        $this->assertEquals('private', $property->visibility);
        $this->assertFalse($property->isPubliclyVisible());
    }

    public function test_only_draft_or_rejected_listings_can_be_submitted(): void
    {
        [$agent] = $this->agencyUser($this->agentPermissions());

        $id = $this->postJson('/api/properties', $this->basePayload(['submit' => true]), $this->authHeaders($this->tokenFor($agent)))
            ->assertStatus(201)->json('id');

        // Pending → submit again is invalid.
        $this->postJson("/api/properties/{$id}/submit", [], $this->authHeaders($this->tokenFor($agent)))
            ->assertStatus(422);
    }

    public function test_denying_a_feature_request_clears_the_featured_flag(): void
    {
        $agentPerms = array_merge($this->agentPermissions(), ['properties.feature']);
        [$agent] = $this->agencyUser($agentPerms);
        [$moderator] = $this->agencyUser($this->moderatorPermissions(), 'moderator');

        $id = $this->postJson('/api/properties', $this->basePayload([
            'title' => 'Feature Denied Plot',
            'submit' => true,
        ]), $this->authHeaders($this->tokenFor($agent)))->assertStatus(201)->json('id');

        $this->postJson("/api/properties/{$id}/approve", [], $this->authHeaders($this->tokenFor($moderator)))
            ->assertStatus(200);

        // Agency requests featuring; the platform grants it.
        $this->postJson("/api/properties/{$id}/feature", [], $this->authHeaders($this->tokenFor($agent)))
            ->assertStatus(200)
            ->assertJsonPath('property.featured_status', 'requested');

        $this->postJson("/api/properties/{$id}/feature", ['status' => 'granted'], $this->authHeaders($this->tokenFor($moderator)))
            ->assertStatus(200)
            ->assertJsonPath('property.featured_status', 'granted')
            ->assertJsonPath('property.isFeatured', true);

        // A later denial must remove the listing from the featured set.
        $this->postJson("/api/properties/{$id}/feature", ['status' => 'denied'], $this->authHeaders($this->tokenFor($moderator)))
            ->assertStatus(200)
            ->assertJsonPath('property.featured_status', 'denied')
            ->assertJsonPath('property.isFeatured', false);

        $this->assertDatabaseHas('properties', ['id' => $id, 'isFeatured' => false]);
    }

    public function test_owner_detail_exposes_confidential_fields_but_never_a_storage_path(): void
    {
        [$agent, $agency] = $this->agencyUser($this->agentPermissions());

        $id = $this->postJson('/api/properties', $this->basePayload([
            'title' => 'Owner Confidentiality Plot',
            'submit' => true,
            'purchasePricePKR' => 4000000,
            'sellingPricePKR' => 5000000,
            'internalNotes' => 'Owner-only note',
        ]), $this->authHeaders($this->tokenFor($agent)))->assertStatus(201)->json('id');

        $response = $this->getJson("/api/properties/{$id}", $this->authHeaders($this->tokenFor($agent)))
            ->assertOk();

        // The owning agency sees the confidential deal columns...
        $response->assertJsonPath('purchasePricePKR', 4000000)
            ->assertJsonPath('sellingPricePKR', 5000000)
            ->assertJsonPath('internalNotes', 'Owner-only note');

        // ...but no server-side storage path is ever serialized on a Property.
        $this->assertArrayNotHasKey('disk_path', $response->json());
        $this->assertStringNotContainsString('"disk_path"', $response->getContent());
    }

    public function test_public_listing_detail_hides_private_internal_fields(): void
    {
        [$agent] = $this->agencyUser($this->agentPermissions());

        $id = $this->postJson('/api/properties', $this->basePayload([
            'title' => 'Detail Visibility Plot',
            'submit' => true,
            'purchasePricePKR' => 4000000,
            'sellingPricePKR' => 5000000,
            'internalNotes' => 'Secret internal note',
        ]), $this->authHeaders($this->tokenFor($agent)))->assertStatus(201)->json('id');

        // Pending/private listing is not publicly readable at all.
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/properties/{$id}")->assertStatus(404);
    }
}
