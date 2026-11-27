<?php

namespace Tests\Feature;

/**
 * Regression coverage for with_meta leaks on agency-scoped property lists.
 * Confidential deal fields must require the explicit finance.view grant,
 * exactly like GET /api/properties/{id}.
 */
class SensitiveMetadataTest extends EnterpriseTestCase
{
    public function test_with_meta_does_not_expose_sensitive_fields_to_plain_agents(): void
    {
        [$user, $agency] = $this->agencyUser(['properties.view'], 'agent');
        $property = $this->makePublicProperty($agency, [
            'purchasePricePKR' => 5000000,
            'sellingPricePKR' => 7000000,
            'internalNotes' => 'confidential deal note',
        ]);

        // The properties index is a public route; explicitly authenticate the
        // Sanctum guard so the agency-scoped `with_meta` branch is exercised.
        $token = $this->tokenFor($user);
        $this->actingAs($user, 'sanctum');
        $response = $this->getJson(
            '/api/properties?scope=agency&with_meta=1',
            $this->authHeaders($token) + ['X-Agency-Id' => $agency->id]
        );
        $this->assertSame(200, $response->status(), $response->getContent());
        $payload = $response->json();

        $row = collect($payload)->firstWhere('id', $property->id);
        $this->assertNotNull($row, 'the agency listing must be present');
        foreach (['purchasePricePKR', 'sellingPricePKR', 'internalNotes'] as $key) {
            $this->assertArrayNotHasKey($key, $row, "{$key} leaked without the finance.view grant");
        }
    }

    public function test_property_show_hides_sensitive_fields_from_plain_agency_agents(): void
    {
        [$user, $agency] = $this->agencyUser(['properties.view'], 'agent');
        $property = $this->makePublicProperty($agency, [
            'purchasePricePKR' => 5000000,
            'sellingPricePKR' => 7000000,
            'internalNotes' => 'confidential deal note',
            'pending_changes' => ['title' => 'Unapproved staged title'],
        ]);

        $token = $this->tokenFor($user);
        $this->actingAs($user, 'sanctum');
        $response = $this->getJson(
            "/api/properties/{$property->id}",
            $this->authHeaders($token) + ['X-Agency-Id' => $agency->id]
        )->assertStatus(200);

        foreach (['purchasePricePKR', 'sellingPricePKR', 'internalNotes', 'pending_changes'] as $key) {
            $this->assertArrayNotHasKey($key, $response->json(), "{$key} leaked through GET /api/properties/{id}");
        }
    }




    public function test_plain_listing_creator_cannot_set_sensitive_financial_fields(): void
    {
        [$user, $agency] = $this->agencyUser(['properties.create'], 'agent');
        $token = $this->tokenFor($user);
        $this->actingAs($user, 'sanctum');

        $this->postJson(
            '/api/properties',
            [
                'title' => 'Sensitive create attempt',
                'pricePKR' => 1000000,
                'phase' => '1',
                'sector' => 'A',
                'sizeSqYards' => 120,
                'purchasePricePKR' => 1,
                'sellingPricePKR' => 2,
                'internalNotes' => 'unauthorized note',
            ],
            $this->authHeaders($token) + ['X-Agency-Id' => $agency->id]
        )->assertForbidden();

        $this->assertDatabaseCount('properties', 0);
    }

    public function test_plain_listing_editor_cannot_change_sensitive_financial_fields(): void
    {
        [$user, $agency] = $this->agencyUser(['properties.view', 'properties.update'], 'agent');
        $property = $this->makePublicProperty($agency, [
            'purchasePricePKR' => 5000000,
            'sellingPricePKR' => 7000000,
            'internalNotes' => 'original confidential note',
        ]);

        $token = $this->tokenFor($user);
        $this->actingAs($user, 'sanctum');
        $this->putJson(
            "/api/properties/{$property->id}",
            [
                'purchasePricePKR' => 1,
                'sellingPricePKR' => 2,
                'internalNotes' => 'attacker overwrite',
            ],
            $this->authHeaders($token) + ['X-Agency-Id' => $agency->id]
        )->assertForbidden();

        $this->assertDatabaseHas('properties', [
            'id' => $property->id,
            'purchasePricePKR' => 5000000,
            'sellingPricePKR' => 7000000,
            'internalNotes' => 'original confidential note',
        ]);
    }

    public function test_finance_permission_can_update_sensitive_fields_without_receiving_staged_changes(): void
    {
        [$user, $agency] = $this->agencyUser(['properties.view', 'properties.update', 'finance.view'], 'manager');
        $property = $this->makePublicProperty($agency, [
            'purchasePricePKR' => 5000000,
            'sellingPricePKR' => 7000000,
            'internalNotes' => 'original confidential note',
            'pending_changes' => ['title' => 'Staged title'],
        ]);

        $token = $this->tokenFor($user);
        $this->actingAs($user, 'sanctum');
        $response = $this->putJson(
            "/api/properties/{$property->id}",
            ['purchasePricePKR' => 5100000, 'internalNotes' => 'Updated finance note'],
            $this->authHeaders($token) + ['X-Agency-Id' => $agency->id]
        )->assertOk();

        $this->assertSame(5100000.0, (float) $response->json('purchasePricePKR'));
        $this->assertSame('Updated finance note', $response->json('internalNotes'));
        $this->assertArrayNotHasKey('pending_changes', $response->json());
    }

    public function test_visibility_mutation_response_does_not_leak_sensitive_property_fields(): void
    {
        [$user, $agency] = $this->agencyUser(['properties.view', 'properties.update'], 'agent');
        $property = $this->makePublicProperty($agency, [
            'purchasePricePKR' => 5000000,
            'sellingPricePKR' => 7000000,
            'internalNotes' => 'confidential visibility response note',
            'pending_changes' => ['title' => 'Unapproved staged title'],
        ]);

        $token = $this->tokenFor($user);
        $this->actingAs($user, 'sanctum');
        $response = $this->postJson(
            "/api/properties/{$property->id}/visibility",
            ['visibility' => 'private'],
            $this->authHeaders($token) + ['X-Agency-Id' => $agency->id]
        )->assertOk()->assertJsonPath('success', true);

        foreach (['purchasePricePKR', 'sellingPricePKR', 'internalNotes', 'pending_changes'] as $key) {
            $this->assertArrayNotHasKey($key, $response->json('property'), "{$key} leaked from visibility mutation response");
        }
    }

    public function test_property_show_requires_finance_permission_even_for_owning_agency(): void
    {
        [$user, $agency] = $this->agencyUser(['properties.view', 'finance.view'], 'manager');
        $property = $this->makePublicProperty($agency, [
            'purchasePricePKR' => 5000000,
            'sellingPricePKR' => 7000000,
            'internalNotes' => 'confidential deal note',
            'pending_changes' => ['title' => 'Unapproved staged title'],
        ]);

        $token = $this->tokenFor($user);
        $this->actingAs($user, 'sanctum');
        $response = $this->getJson(
            "/api/properties/{$property->id}",
            $this->authHeaders($token) + ['X-Agency-Id' => $agency->id]
        )->assertStatus(200);

        $this->assertSame(5000000.0, (float) $response->json('purchasePricePKR'));
        $this->assertSame(7000000.0, (float) $response->json('sellingPricePKR'));
        $this->assertSame('confidential deal note', $response->json('internalNotes'));
        $this->assertArrayNotHasKey('pending_changes', $response->json(), 'only moderators may read staged changes');
    }

    public function test_agency_finance_permission_cannot_read_another_agencys_private_prices(): void
    {
        [$user, $requestingAgency] = $this->agencyUser(
            ['properties.view', 'finance.view'],
            'manager'
        );
        $listingAgency = $this->makeAgency('Separate Listing Agency');
        $property = $this->makePublicProperty($listingAgency, [
            'purchasePricePKR' => 5000000,
            'sellingPricePKR' => 7000000,
            'internalNotes' => 'other agency confidential note',
        ]);

        $token = $this->tokenFor($user);
        $this->actingAs($user, 'sanctum');
        $response = $this->getJson(
            "/api/properties/{$property->id}",
            $this->authHeaders($token) + ['X-Agency-Id' => $requestingAgency->id]
        )->assertStatus(200);

        foreach (['purchasePricePKR', 'sellingPricePKR', 'internalNotes', 'pending_changes'] as $key) {
            $this->assertArrayNotHasKey($key, $response->json(), "cross-agency {$key} disclosure");
        }
    }

    public function test_moderation_scope_can_read_staged_changes(): void
    {
        $this->seedPlatformRole('moderator');
        $moderator = $this->makeUser('moderator');
        $agency = $this->makeAgency();
        $property = $this->makePublicProperty($agency, [
            'pending_changes' => ['title' => 'Unapproved staged title'],
        ]);

        $token = $this->tokenFor($moderator);
        $this->actingAs($moderator, 'sanctum');
        $response = $this->getJson(
            '/api/properties?scope=moderation&with_meta=1',
            $this->authHeaders($token)
        )->assertStatus(200);

        $row = collect($response->json())->firstWhere('id', $property->id);
        $this->assertNotNull($row, 'moderation queue must include the property');
        $this->assertSame('Unapproved staged title', $row['pending_changes']['title']);
    }

    public function test_with_meta_still_exposes_sensitive_fields_with_the_grant(): void
    {
        [$user, $agency] = $this->agencyUser(['properties.view', 'finance.view'], 'manager');
        $property = $this->makePublicProperty($agency, [
            'purchasePricePKR' => 5000000,
            'sellingPricePKR' => 7000000,
            'internalNotes' => 'confidential deal note',
        ]);

        // The properties index is a public route; explicitly authenticate the
        // Sanctum guard so the agency-scoped `with_meta` branch is exercised.
        $this->assertSame($agency->id, $user->resolveAgencyId($agency->id));
        $this->assertTrue($user->workspaceAccess($agency->id)['allowed']);
        $this->assertTrue($user->hasPermission('finance.view', $agency->id));

        $token = $this->tokenFor($user);
        $this->actingAs($user, 'sanctum');
        $response = $this->getJson(
            '/api/properties?scope=agency&with_meta=1',
            $this->authHeaders($token) + ['X-Agency-Id' => $agency->id]
        );
        $this->assertSame(200, $response->status(), $response->getContent());
        $payload = $response->json();

        $row = collect($payload)->firstWhere('id', $property->id);
        $this->assertSame(5000000.0, (float) $row['purchasePricePKR']);
        $this->assertSame('confidential deal note', $row['internalNotes']);
    }
}
