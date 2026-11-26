<?php

namespace Tests\Feature;

use App\Models\BusinessRecord;
use App\Models\Dealer;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tenant-isolation matrix for every workspace collection.
 *
 * The 13 agency-scoped collections must never expose or mutate another
 * tenant's rows, and replace mode must only delete the writer's own rows.
 * Projects/testimonials are platform-global public content and are covered by
 * a separate role test.
 */
class WorkspaceTenantMatrixTest extends EnterpriseTestCase
{
    /**
     * collection => [read permission, write permission, replace permission]
     */
    public static function scopedCollections(): array
    {
        return [
            'properties' => ['properties', ['properties.view'], ['properties.create', 'properties.update'], ['properties.delete']],
            'dealers' => ['dealers', ['manage_dealers'], ['manage_dealers'], ['manage_dealers']],
            'sellers' => ['sellers', ['manage_sellers'], ['manage_sellers'], ['manage_sellers']],
            'buyers' => ['buyers', ['manage_buyers'], ['manage_buyers'], ['manage_buyers']],
            'transactions' => ['transactions', ['finance.view'], ['finance.create_transaction', 'update_transactions'], []],
            'payments' => ['payments', ['finance.view'], ['finance.manage_payments'], []],
            'commissions' => ['commissions', ['finance.view'], ['finance.manage_payments'], []],
            'files' => ['files', ['documents.view'], ['documents.upload', 'verify_documents'], ['documents.delete']],
            'plots' => ['plots', ['properties.view'], ['properties.update'], ['properties.delete']],
            'media' => ['media', ['manage_media'], ['manage_media'], ['manage_media']],
            'videos' => ['videos', ['manage_media'], ['manage_media'], ['manage_media']],
            'inquiries' => ['inquiries', ['crm.view'], ['crm.update_lead'], ['crm.delete_lead']],
            'subscribers' => ['subscribers', ['crm.view'], ['crm.update_lead'], ['crm.delete_lead']],
        ];
    }

    private function seedRecord(string $collection, string $id, string $title, ?string $agencyId): void
    {
        BusinessRecord::put($collection, $id, ['id' => $id, 'title' => $title]);
        if ($agencyId !== null) {
            BusinessRecord::where('collection', $collection)->where('record_id', $id)
                ->update(['agency_id' => $agencyId]);
        }
    }

    private function record(string $collection, string $id): BusinessRecord
    {
        return BusinessRecord::where('collection', $collection)
            ->where('record_id', $id)
            ->firstOrFail();
    }

    #[DataProvider('scopedCollections')]
    public function test_scoped_collection_reads_and_writes_are_tenant_isolated(
        string $collection,
        array $readPermissions,
        array $writePermissions,
        array $replacePermissions
    ): void {
        [$user, $agency] = $this->agencyUser(array_values(array_unique(array_merge(
            $readPermissions,
            $writePermissions,
            $replacePermissions
        ))));
        $other = $this->makeAgency('Rival Agency');

        $ownId = 'own-'.$collection;
        $foreignId = 'foreign-'.$collection;
        $legacyId = 'legacy-'.$collection;

        $this->seedRecord($collection, $ownId, 'Own row', $agency->id);
        $this->seedRecord($collection, $foreignId, 'Foreign row', $other->id);
        $this->seedRecord($collection, $legacyId, 'Legacy row', null);

        // ---- read isolation ----
        $body = $this->getJson('/api/workspace', $this->authHeaders($this->tokenFor($user)))
            ->assertOk()
            ->json($collection);

        $this->assertIsArray($body, "{$collection} must be a list");
        $ids = array_column($body, 'id');
        $this->assertContains($ownId, $ids, "{$collection}: own row must be visible");
        $this->assertNotContains($foreignId, $ids, "{$collection}: foreign row must not be visible");
        $this->assertNotContains($legacyId, $ids, "{$collection}: platform-owned legacy row must not be visible");

        // ---- write isolation: own row updates, foreign row is untouched ----
        $this->putJson('/api/workspace', [
            'collections' => [$collection => [
                ['id' => $ownId, 'title' => 'Own updated'],
                ['id' => $foreignId, 'title' => 'Hijacked'],
            ]],
        ], $this->authHeaders($this->tokenFor($user)))->assertOk();

        $expectedOwnTitle = $collection === 'payments' ? 'Own row' : 'Own updated';
        $this->assertSame($expectedOwnTitle, $this->record($collection, $ownId)->payload['title']);
        $this->assertSame('Foreign row', $this->record($collection, $foreignId)->payload['title']);
        $this->assertSame('Legacy row', $this->record($collection, $legacyId)->payload['title']);
        $this->assertSame($other->id, $this->record($collection, $foreignId)->agency_id);
        $this->assertNull($this->record($collection, $legacyId)->agency_id);

        // ---- replace is scoped to the writer's agency ----
        $replaceResponse = $this->putJson('/api/workspace', [
            'collections' => [$collection => [
                ['id' => 'fresh-'.$collection, 'title' => 'Fresh'],
            ]],
            'mode' => 'replace',
        ], $this->authHeaders($this->tokenFor($user)))->assertOk();

        if ($replacePermissions === []) {
            $this->assertContains($collection, $replaceResponse->json('denied'));
            $this->assertNull($this->record($collection, $ownId)->deleted_at);
            $this->assertNull($this->record($collection, $foreignId)->deleted_at);
            $this->assertNull($this->record($collection, $legacyId)->deleted_at);

            return;
        }

        $this->assertNotNull(
            $this->record($collection, $ownId)->deleted_at,
            "{$collection}: own row absent from replace payload must be removed"
        );
        $this->assertNull(
            $this->record($collection, $foreignId)->deleted_at,
            "{$collection}: foreign row must survive a member replace"
        );
        $this->assertNull(
            $this->record($collection, $legacyId)->deleted_at,
            "{$collection}: legacy platform row must survive a member replace"
        );
    }

    public function test_global_project_and_testimonial_content_is_read_only_for_standard_agency_roles(): void
    {
        [$user, $agency] = $this->agencyUser([
            'properties.view', 'view_properties', 'crm.view',
        ]);

        $this->seedRecord('projects', 'global-project', 'Platform project', null);
        $this->seedRecord('testimonials', 'global-testimonial', 'Public testimonial', null);

        $body = $this->getJson('/api/workspace', $this->authHeaders($this->tokenFor($user)))
            ->assertOk()
            ->json();

        $this->assertContains('global-project', array_column($body['projects'], 'id'));
        $this->assertContains('global-testimonial', array_column($body['testimonials'], 'id'));

        $response = $this->putJson('/api/workspace', [
            'collections' => [
                'projects' => [['id' => 'global-project', 'title' => 'Hijacked project']],
                'testimonials' => [['id' => 'global-testimonial', 'title' => 'Hijacked testimonial']],
            ],
        ], $this->authHeaders($this->tokenFor($user)))->assertOk();

        $this->assertContains('projects', $response->json('denied'));
        $this->assertContains('testimonials', $response->json('denied'));
        $this->assertSame('Platform project', $this->record('projects', 'global-project')->payload['title']);
        $this->assertSame('Public testimonial', $this->record('testimonials', 'global-testimonial')->payload['title']);
    }
    public function test_soft_deleted_rows_are_not_returned_in_workspace_dumps(): void
    {
        [$user, $agency] = $this->agencyUser(['properties.view', 'view_properties']);

        $this->seedRecord('properties', 'visible-property', 'Visible property', $agency->id);
        $this->seedRecord('properties', 'deleted-property', 'Deleted property', $agency->id);
        $this->record('properties', 'deleted-property')->update(['deleted_at' => now()]);

        $body = $this->getJson('/api/workspace', $this->authHeaders($this->tokenFor($user)))
            ->assertOk()
            ->json('properties');

        $ids = array_column($body, 'id');
        $this->assertContains('visible-property', $ids);
        $this->assertNotContains('deleted-property', $ids);
    }


    public function test_property_viewer_cannot_read_finance_crm_documents_or_private_media_collections(): void
    {
        [$user, $agency] = $this->agencyUser(['properties.view']);
        $privateCollections = [
            'dealers', 'sellers', 'buyers', 'transactions', 'payments',
            'commissions', 'files', 'media', 'videos', 'inquiries', 'subscribers',
        ];

        foreach ($privateCollections as $collection) {
            $this->seedRecord($collection, 'private-' . $collection, 'Private row', $agency->id);
        }

        $body = $this->getJson('/api/workspace', $this->authHeaders($this->tokenFor($user)))
            ->assertOk()
            ->json();

        foreach ($privateCollections as $collection) {
            $this->assertSame([], $body[$collection], "{$collection} must not be readable with properties.view alone");
        }
    }

    public function test_dealer_self_service_reads_are_scoped_to_the_authenticated_dealer(): void
    {
        [$user, $agency] = $this->agencyUser(['view_my_deals', 'view_my_commission'], 'dealer');
        $user->dealer_id = 'dealer-self';
        $user->save();

        BusinessRecord::put('transactions', 'deal-self', ['id' => 'deal-self', 'dealerId' => 'dealer-self'], $agency->id);
        BusinessRecord::put('transactions', 'deal-other', ['id' => 'deal-other', 'dealerId' => 'dealer-other'], $agency->id);
        BusinessRecord::put('payments', 'payment-self', ['id' => 'payment-self', 'transactionId' => 'deal-self'], $agency->id);
        BusinessRecord::put('payments', 'payment-other', ['id' => 'payment-other', 'transactionId' => 'deal-other'], $agency->id);
        BusinessRecord::put('commissions', 'commission-self', ['id' => 'commission-self', 'dealerId' => 'dealer-self'], $agency->id);
        BusinessRecord::put('commissions', 'commission-other', ['id' => 'commission-other', 'dealerId' => 'dealer-other'], $agency->id);

        $body = $this->getJson('/api/workspace', $this->authHeaders($this->tokenFor($user)))
            ->assertOk()
            ->json();

        $this->assertSame(['deal-self'], array_column($body['transactions'], 'id'));
        $this->assertSame(['payment-self'], array_column($body['payments'], 'id'));
        $this->assertSame(['commission-self'], array_column($body['commissions'], 'id'));
    }


    public function test_create_only_finance_permission_cannot_update_an_existing_transaction(): void
    {
        [$user, $agency] = $this->agencyUser(['finance.create_transaction']);
        BusinessRecord::put('transactions', 'existing-deal', [
            'id' => 'existing-deal',
            'title' => 'Original title',
            'agreementAmountPKR' => 100000,
            'advancePaymentPKR' => 0,
            'remainingBalancePKR' => 100000,
        ], $agency->id);

        $this->putJson('/api/workspace', [
            'collections' => ['transactions' => [[
                'id' => 'existing-deal',
                'title' => 'Tampered title',
                'agreementAmountPKR' => 1,
                'advancePaymentPKR' => 0,
                'remainingBalancePKR' => 1,
            ]]],
        ], $this->authHeaders($this->tokenFor($user)))->assertOk();

        $this->assertSame('Original title', $this->record('transactions', 'existing-deal')->payload['title']);
        $this->assertSame(100000, $this->record('transactions', 'existing-deal')->payload['agreementAmountPKR']);
    }

    public function test_void_permission_can_only_void_unpaid_deals_with_a_reason(): void
    {
        [$user, $agency] = $this->agencyUser(['void_transactions']);
        BusinessRecord::put('transactions', 'unpaid-deal', [
            'id' => 'unpaid-deal',
            'status' => 'Active',
            'advancePaymentPKR' => 0,
            'agreementAmountPKR' => 100000,
            'remainingBalancePKR' => 100000,
        ], $agency->id);
        BusinessRecord::put('transactions', 'paid-deal', [
            'id' => 'paid-deal',
            'status' => 'Active',
            'advancePaymentPKR' => 5000,
            'agreementAmountPKR' => 100000,
            'remainingBalancePKR' => 95000,
        ], $agency->id);

        $this->putJson('/api/workspace', [
            'collections' => ['transactions' => [
                [
                    'id' => 'unpaid-deal',
                    'status' => 'Voided',
                    'voidReason' => 'Duplicate booking.',
                    'advancePaymentPKR' => 0,
                    'agreementAmountPKR' => 100000,
                    'remainingBalancePKR' => 100000,
                ],
                [
                    'id' => 'paid-deal',
                    'status' => 'Voided',
                    'voidReason' => 'Duplicate booking.',
                    'advancePaymentPKR' => 5000,
                    'agreementAmountPKR' => 100000,
                    'remainingBalancePKR' => 95000,
                ],
            ]],
        ], $this->authHeaders($this->tokenFor($user)))->assertOk();

        $this->assertSame('Voided', $this->record('transactions', 'unpaid-deal')->payload['status']);
        $this->assertSame('Active', $this->record('transactions', 'paid-deal')->payload['status']);
    }

    public function test_update_permission_cannot_soft_delete_a_row_without_delete_permission(): void
    {
        [$user, $agency] = $this->agencyUser(['properties.update']);
        $this->seedRecord('properties', 'protected-property', 'Protected', $agency->id);

        $this->putJson('/api/workspace', [
            'collections' => ['properties' => [[
                'id' => 'protected-property',
                'title' => 'Protected',
                'deletedAt' => now()->toIso8601String(),
            ]]],
        ], $this->authHeaders($this->tokenFor($user)))->assertOk();

        $this->assertNull($this->record('properties', 'protected-property')->deleted_at);
    }


    public function test_workspace_multi_collection_save_rolls_back_earlier_writes_when_later_validation_fails(): void
    {
        [$user] = $this->agencyUser(['properties.create', 'finance.create_transaction']);

        $this->putJson('/api/workspace', [
            'collections' => [
                'properties' => [[
                    'id' => 'atomic-property',
                    'title' => 'Must roll back',
                ]],
                'transactions' => [[
                    'id' => 'invalid-fractional-deal',
                    'agreementAmountPKR' => 1000.50,
                ]],
            ],
        ], $this->authHeaders($this->tokenFor($user)))->assertStatus(422);

        $this->assertDatabaseMissing('business_records', [
            'collection' => 'properties',
            'record_id' => 'atomic-property',
        ]);
    }


    public function test_workspace_cannot_link_an_agency_property_or_deal_to_another_agencys_dealer(): void
    {
        [$user, $agencyA] = $this->agencyUser(['properties.create', 'finance.create_transaction']);
        $agencyB = $this->makeAgency('Foreign Dealer Owner');
        $foreignDealer = Dealer::create([
            'id' => 'foreign-workspace-dealer',
            'fullName' => 'Foreign Dealer',
            'phone' => '03007776655',
            'agency_id' => $agencyB->id,
            'status' => 'Active',
        ]);

        $this->putJson('/api/workspace', [
            'collections' => [
                'properties' => [[
                    'id' => 'property-with-foreign-dealer',
                    'title' => 'Must not link foreign dealer',
                    'assignedDealerId' => $foreignDealer->id,
                ]],
                'transactions' => [[
                    'id' => 'deal-with-foreign-dealer',
                    'title' => 'Must not link foreign dealer',
                    'dealerId' => $foreignDealer->id,
                ]],
            ],
        ], $this->authHeaders($this->tokenFor($user)))->assertOk();

        $this->assertDatabaseMissing('business_records', [
            'collection' => 'properties',
            'record_id' => 'property-with-foreign-dealer',
            'agency_id' => $agencyA->id,
        ]);
        $this->assertDatabaseMissing('business_records', [
            'collection' => 'transactions',
            'record_id' => 'deal-with-foreign-dealer',
            'agency_id' => $agencyA->id,
        ]);
    }

}
