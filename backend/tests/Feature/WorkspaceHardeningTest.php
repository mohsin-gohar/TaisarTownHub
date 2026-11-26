<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\BusinessRecord;
use App\Models\Role;
use App\Models\User;

/**
 * Workspace sync hardening (Phase 3): merge-only default, replace requires
 * delete-level rights, agency scoping, row-level ownership, and per-row
 * validation.
 */
class WorkspaceHardeningTest extends EnterpriseTestCase
{
    private function memberWithPropertiesWrite(): array
    {
        $user = $this->makeUser('agent');
        $agency = $this->makeAgency('Workspace Agency');
        $role = $this->makeRole('agency', 'agent', [
            'view_properties', 'create_properties', 'edit_properties', 'properties.create', 'properties.update',
        ]);
        $this->makeMember($user, $agency, $role);

        return [$user, $agency];
    }

    public function test_merge_save_never_deletes_rows_absent_from_payload(): void
    {
        [$user, $agency] = $this->memberWithPropertiesWrite();
        BusinessRecord::put('properties', 'keep-1', ['id' => 'keep-1', 'title' => 'Keep me']);
        BusinessRecord::where('collection', 'properties')->where('record_id', 'keep-1')
            ->update(['agency_id' => $agency->id]);

        $this->putJson('/api/workspace', [
            'collections' => ['properties' => [['id' => 'new-1', 'title' => 'New row']]],
        ], $this->authHeaders($this->tokenFor($user)))->assertStatus(200);

        $this->assertDatabaseHas('business_records', [
            'collection' => 'properties', 'record_id' => 'keep-1', 'deleted_at' => null,
        ]);
    }

    public function test_replace_mode_requires_delete_level_permission(): void
    {
        [$user, $agency] = $this->memberWithPropertiesWrite();
        BusinessRecord::put('properties', 'victim-1', ['id' => 'victim-1', 'title' => 'Victim']);
        BusinessRecord::where('collection', 'properties')->where('record_id', 'victim-1')
            ->update(['agency_id' => $agency->id]);

        // The agent role has no delete_properties → replace must be denied.
        $response = $this->putJson('/api/workspace', [
            'collections' => ['properties' => [['id' => 'survivor-1', 'title' => 'Survivor']]],
            'mode' => 'replace',
        ], $this->authHeaders($this->tokenFor($user)))->assertStatus(200);

        $response->assertJsonPath('denied.0', 'properties');
        $this->assertDatabaseHas('business_records', [
            'collection' => 'properties', 'record_id' => 'victim-1', 'deleted_at' => null,
        ]);
    }

    public function test_replace_mode_with_permission_is_scoped_to_own_agency(): void
    {
        [$user, $agency] = $this->memberWithPropertiesWrite();
        $user->memberships->first()->role->update([
            'permissions' => array_merge($user->memberships->first()->role->permissions ?? [], ['delete_properties', 'properties.delete']),
        ]);

        $otherAgency = $this->makeAgency('Other Agency');
        BusinessRecord::put('properties', 'own-victim', ['id' => 'own-victim', 'title' => 'Own victim']);
        BusinessRecord::where('collection', 'properties')->where('record_id', 'own-victim')
            ->update(['agency_id' => $agency->id]);
        BusinessRecord::put('properties', 'foreign-victim', ['id' => 'foreign-victim', 'title' => 'Foreign victim']);
        BusinessRecord::where('collection', 'properties')->where('record_id', 'foreign-victim')
            ->update(['agency_id' => $otherAgency->id]);

        $this->putJson('/api/workspace', [
            'collections' => ['properties' => [['id' => 'fresh', 'title' => 'Fresh']]],
            'mode' => 'replace',
        ], $this->authHeaders($this->tokenFor($user)))->assertStatus(200);

        $this->assertDatabaseHas('business_records', [
            'collection' => 'properties', 'record_id' => 'own-victim',
        ]);
        $this->assertNotNull(
            BusinessRecord::where('collection', 'properties')->where('record_id', 'own-victim')->first()->deleted_at,
            'own-agency rows must be replaced'
        );
        $this->assertNull(
            BusinessRecord::where('collection', 'properties')->where('record_id', 'foreign-victim')->first()->deleted_at,
            'foreign-agency rows must survive a member replace'
        );
    }

    public function test_replace_mode_with_invalid_foreign_reference_does_not_delete_owned_rows(): void
    {
        [$user, $agency] = $this->memberWithPropertiesWrite();
        $role = $user->memberships->first()->role;
        $role->update([
            'permissions' => array_merge($role->permissions ?? [], ['delete_properties', 'properties.delete']),
        ]);

        $otherAgency = $this->makeAgency('Foreign Dealer Agency');
        BusinessRecord::put('properties', 'keep-invalid-ref', ['id' => 'keep-invalid-ref', 'title' => 'Keep this listing']);
        BusinessRecord::where('collection', 'properties')->where('record_id', 'keep-invalid-ref')
            ->update(['agency_id' => $agency->id]);
        BusinessRecord::put('dealers', 'foreign-dealer-ref', ['id' => 'foreign-dealer-ref', 'status' => 'Active']);
        BusinessRecord::where('collection', 'dealers')->where('record_id', 'foreign-dealer-ref')
            ->update(['agency_id' => $otherAgency->id]);

        $this->putJson('/api/workspace', [
            'collections' => ['properties' => [[
                'id' => 'invalid-reference-row',
                'title' => 'Invalid foreign dealer reference',
                'assignedDealerId' => 'foreign-dealer-ref',
            ]]],
            'mode' => 'replace',
        ], $this->authHeaders($this->tokenFor($user)))->assertStatus(200);

        $this->assertDatabaseHas('business_records', [
            'collection' => 'properties',
            'record_id' => 'keep-invalid-ref',
            'deleted_at' => null,
        ]);
        $this->assertDatabaseMissing('business_records', [
            'collection' => 'properties',
            'record_id' => 'invalid-reference-row',
        ]);
    }

    public function test_replace_mode_with_row_level_create_denial_does_not_delete_owned_rows(): void
    {
        [$user, $agency] = $this->memberWithPropertiesWrite();
        $role = $user->memberships->first()->role;
        // Collection-level update + delete permits reaching replace, but this
        // role deliberately has no create permission for a new property row.
        $role->update(['permissions' => ['properties.update', 'properties.delete']]);

        BusinessRecord::put('properties', 'keep-create-denied', ['id' => 'keep-create-denied', 'title' => 'Keep existing']);
        BusinessRecord::where('collection', 'properties')->where('record_id', 'keep-create-denied')
            ->update(['agency_id' => $agency->id]);

        $this->putJson('/api/workspace', [
            'collections' => ['properties' => [[
                'id' => 'denied-new-property',
                'title' => 'Create permission missing',
            ]]],
            'mode' => 'replace',
        ], $this->authHeaders($this->tokenFor($user)))->assertStatus(200);

        $this->assertDatabaseHas('business_records', [
            'collection' => 'properties',
            'record_id' => 'keep-create-denied',
            'deleted_at' => null,
        ]);
        $this->assertDatabaseMissing('business_records', [
            'collection' => 'properties',
            'record_id' => 'denied-new-property',
        ]);
    }

    public function test_member_cannot_overwrite_another_agencys_row(): void
    {
        [$userA, $agencyA] = $this->memberWithPropertiesWrite();
        $userB = $this->makeUser('agent');
        $agencyB = $this->makeAgency('Rival Agency');
        $roleB = $this->makeRole('agency', 'agent', ['view_properties', 'create_properties', 'edit_properties']);
        $this->makeMember($userB, $agencyB, $roleB);

        BusinessRecord::put('properties', 'rival-row', ['id' => 'rival-row', 'title' => 'Original title']);
        BusinessRecord::where('collection', 'properties')->where('record_id', 'rival-row')
            ->update(['agency_id' => $agencyB->id]);

        $this->putJson('/api/workspace', [
            'collections' => ['properties' => [['id' => 'rival-row', 'title' => 'Hijacked title']]],
        ], $this->authHeaders($this->tokenFor($userA)))->assertStatus(200);

        $row = BusinessRecord::where('collection', 'properties')->where('record_id', 'rival-row')->first();
        $this->assertEquals('Original title', $row->payload['title']);
        $this->assertEquals($agencyB->id, $row->agency_id);
    }

    public function test_invalid_row_ids_are_rejected_before_persistence(): void
    {
        [$user] = $this->memberWithPropertiesWrite();

        $response = $this->putJson('/api/workspace', [
            'collections' => ['properties' => [
                ['id' => 'bad id with spaces!', 'title' => 'Invalid'],
                ['id' => str_repeat('x', 65), 'title' => 'Too long'],
                ['id' => 'valid-1', 'title' => 'Valid row'],
            ]],
        ], $this->authHeaders($this->tokenFor($user)))->assertStatus(200);

        $written = $response->json('written.properties');
        $this->assertEquals(1, $written, 'only the valid row is persisted');
        $this->assertDatabaseMissing('business_records', ['collection' => 'properties', 'record_id' => 'bad id with spaces!']);
        $this->assertDatabaseHas('business_records', ['collection' => 'properties', 'record_id' => 'valid-1']);
    }

    public function test_member_without_sensitive_permission_cannot_create_or_change_property_confidential_fields(): void
    {
        [$user, $agency] = $this->memberWithPropertiesWrite();
        $headers = $this->authHeaders($this->tokenFor($user));
        BusinessRecord::put('properties', 'private-property-row', [
            'id' => 'private-property-row',
            'title' => 'Original title',
            'purchasePricePKR' => 4000000,
            'sellingPricePKR' => 5000000,
            'internalNotes' => 'Private note',
        ], $agency->id);

        $this->putJson('/api/workspace', [
            'collections' => ['properties' => [
                [
                    'id' => 'private-property-row',
                    'title' => 'Attempted update',
                    'purchasePricePKR' => 1,
                    'sellingPricePKR' => 2,
                    'internalNotes' => 'Tampered',
                ],
                [
                    'id' => 'new-private-property-row',
                    'title' => 'Attempted confidential create',
                    'purchasePricePKR' => 3,
                ],
            ]],
        ], $headers)->assertOk();

        $stored = BusinessRecord::where('collection', 'properties')->where('record_id', 'private-property-row')->first();
        $this->assertSame('Original title', $stored->payload['title']);
        $this->assertSame(4000000, $stored->payload['purchasePricePKR']);
        $this->assertSame(5000000, $stored->payload['sellingPricePKR']);
        $this->assertSame('Private note', $stored->payload['internalNotes']);
        $this->assertDatabaseMissing('business_records', [
            'collection' => 'properties',
            'record_id' => 'new-private-property-row',
        ]);

        // A normal editor receives redacted fields; saving the rest of the
        // row must preserve the confidential values already stored.
        $this->putJson('/api/workspace', [
            'collections' => ['properties' => [[
                'id' => 'private-property-row',
                'title' => 'Ordinary editor update',
            ]]],
        ], $headers)->assertOk();

        $stored->refresh();
        $this->assertSame('Ordinary editor update', $stored->payload['title']);
        $this->assertSame(4000000, $stored->payload['purchasePricePKR']);
        $this->assertSame(5000000, $stored->payload['sellingPricePKR']);
        $this->assertSame('Private note', $stored->payload['internalNotes']);
    }

    public function test_client_supplied_tenancy_fields_are_ignored(): void
    {
        [$user, $agency] = $this->memberWithPropertiesWrite();
        $otherAgency = $this->makeAgency('Payload Agency');

        $this->putJson('/api/workspace', [
            'collections' => ['properties' => [[
                'id' => 'stamp-1',
                'title' => 'Stamped row',
                'agency_id' => $otherAgency->id,
                'created_by' => 999,
            ]],
        ]], $this->authHeaders($this->tokenFor($user)))->assertStatus(200);

        $row = BusinessRecord::where('collection', 'properties')->where('record_id', 'stamp-1')->first();
        $this->assertEquals($agency->id, $row->agency_id, 'tenancy comes from the membership, not the payload');
        $this->assertArrayNotHasKey('agency_id', $row->payload, 'payload must not carry tenancy fields');
        $this->assertEquals($user->id, $row->payload['created_by']);
    }
    public function test_workspace_financial_sync_rejects_fractional_pkr_on_every_ledger_collection(): void
    {
        [$user, $agency] = $this->agencyUser([
            'finance.view',
            'finance.create_transaction',
            'finance.manage_payments',
        ]);
        $headers = $this->authHeaders($this->tokenFor($user));

        $cases = [
            ['transactions', 'agreementAmountPKR', 1000.50],
            ['transactions', 'commissionPKR', 250.25],
            ['payments', 'amountPKR', 1000.50],
            ['commissions', 'amountPKR', 500.75],
            ['commissions', 'paidPKR', 100.25],
        ];

        foreach ($cases as [$collection, $field, $amount]) {
            $id = 'fractional-' . $collection . '-' . $field;
            $this->putJson('/api/workspace', [
                'collections' => [$collection => [[
                    'id' => $id,
                    $field => $amount,
                ]]],
            ], $headers)
                ->assertStatus(422)
                ->assertJsonValidationErrors("collections.{$collection}.0.{$field}");

            $this->assertDatabaseMissing('business_records', [
                'collection' => $collection,
                'record_id' => $id,
            ]);
        }
    }

    public function test_workspace_commission_sync_rejects_fractional_nested_payouts(): void
    {
        [$user] = $this->agencyUser([
            'finance.view',
            'finance.manage_payments',
        ]);

        $this->putJson('/api/workspace', [
            'collections' => ['commissions' => [[
                'id' => 'fractional-payout',
                'amountPKR' => 1000,
                'paidPKR' => 100,
                'payouts' => [[
                    'id' => 'payout-1',
                    'amountPKR' => 100.50,
                ]],
            ]]],
        ], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(422)
            ->assertJsonValidationErrors('collections.commissions.0.payouts.0.amountPKR');

        $this->assertDatabaseMissing('business_records', [
            'collection' => 'commissions',
            'record_id' => 'fractional-payout',
        ]);
    }

    public function test_workspace_financial_sync_preserves_existing_row_when_fractional_amount_is_rejected(): void
    {
        [$user, $agency] = $this->agencyUser([
            'finance.view',
            'finance.create_transaction',
        ]);
        BusinessRecord::put('transactions', 'precision-existing', [
            'id' => 'precision-existing',
            'agreementAmountPKR' => 1000,
        ]);
        BusinessRecord::where('collection', 'transactions')
            ->where('record_id', 'precision-existing')
            ->update(['agency_id' => $agency->id]);

        $this->putJson('/api/workspace', [
            'collections' => ['transactions' => [[
                'id' => 'precision-existing',
                'agreementAmountPKR' => 1000.50,
            ]]],
        ], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(422)
            ->assertJsonValidationErrors('collections.transactions.0.agreementAmountPKR');

        $this->assertSame(
            1000,
            BusinessRecord::where('collection', 'transactions')
                ->where('record_id', 'precision-existing')
                ->firstOrFail()->payload['agreementAmountPKR']
        );
    }

    public function test_workspace_financial_sync_normalizes_whole_rupee_decimal_strings_to_integers(): void
    {
        [$user] = $this->agencyUser([
            'finance.view',
            'finance.manage_payments',
        ]);

        $this->putJson('/api/workspace', [
            'collections' => ['payments' => [[
                'id' => 'whole-rupee-payment',
                'amountPKR' => '1000.00',
            ]]],
        ], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(200);

        $this->assertSame(
            1000,
            BusinessRecord::where('collection', 'payments')
                ->where('record_id', 'whole-rupee-payment')
                ->firstOrFail()->payload['amountPKR']
        );
    }

}
