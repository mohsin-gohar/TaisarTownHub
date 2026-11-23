<?php

namespace Tests\Feature;

/**
 * Regression coverage for with_meta leaks on agency-scoped property lists.
 * Confidential deal fields must require the explicit sensitive-data grant,
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
            $this->assertArrayNotHasKey($key, $row, "{$key} leaked without the sensitive-data grant");
        }
    }

    public function test_with_meta_still_exposes_sensitive_fields_with_the_grant(): void
    {
        [$user, $agency] = $this->agencyUser(['properties.view', 'view_sensitive_cnic'], 'manager');
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
        $this->assertSame(5000000.0, (float) $row['purchasePricePKR']);
        $this->assertSame('confidential deal note', $row['internalNotes']);
    }
}
