<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\BusinessRecord;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;

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

    public function test_platform_cms_editor_can_persist_allowlisted_public_settings(): void
    {
        $admin = $this->makeUser('super_admin');

        $this->putJson('/api/workspace', [
            'collections' => ['cms' => [
                'heroHeadline' => 'Verified Scheme 45 Homes',
                'seoTitle' => 'TaisarTownHub — Verified Listings',
                'internalNotes' => 'Never publish this value',
            ]],
        ], $this->authHeaders($this->tokenFor($admin)))
            ->assertOk()
            ->assertJsonPath('written.cms', 1);

        $this->assertDatabaseHas('business_records', [
            'collection' => 'cms',
            'record_id' => 'default',
            'deleted_at' => null,
        ]);

        $this->getJson('/api/catalog')
            ->assertOk()
            ->assertJsonPath('cms.heroHeadline', 'Verified Scheme 45 Homes')
            ->assertJsonPath('cms.seoTitle', 'TaisarTownHub — Verified Listings')
            ->assertJsonMissingPath('cms.internalNotes');
    }

    public function test_agency_owner_cannot_change_platform_wide_cms_settings(): void
    {
        [$user] = $this->agencyUser(Permissions::agencyRoleGrants('agency_owner'), 'agency_owner');
        BusinessRecord::put('cms', 'default', ['heroHeadline' => 'Platform approved headline']);

        $this->putJson('/api/workspace', [
            'collections' => ['cms' => ['heroHeadline' => 'Agency attempted overwrite']],
        ], $this->authHeaders($this->tokenFor($user)))
            ->assertOk()
            ->assertJsonPath('denied.0', 'cms');

        $this->assertSame(
            'Platform approved headline',
            BusinessRecord::where('collection', 'cms')->where('record_id', 'default')->firstOrFail()->payload['heroHeadline']
        );
    }

}
