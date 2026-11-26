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

    public function test_first_workspace_writer_atomically_owns_a_new_row(): void
    {
        [$agencyAUser, $agencyA] = $this->agencyUser(['properties.view', 'properties.create']);
        [$agencyBUser] = $this->agencyUser(['properties.view', 'properties.create']);

        $this->putJson('/api/workspace', [
            'collections' => ['properties' => [[
                'id' => 'atomic-shared-property',
                'title' => 'Agency A original',
            ]]],
        ], $this->authHeaders($this->tokenFor($agencyAUser)))->assertOk();

        $record = $this->record('properties', 'atomic-shared-property');
        $this->assertSame($agencyA->id, $record->agency_id);
        $this->assertSame('Agency A original', $record->payload['title']);

        // A second agency cannot claim the same previously-created ID.
        $this->putJson('/api/workspace', [
            'collections' => ['properties' => [[
                'id' => 'atomic-shared-property',
                'title' => 'Agency B takeover',
            ]]],
        ], $this->authHeaders($this->tokenFor($agencyBUser)))->assertOk();

        $record->refresh();
        $this->assertSame($agencyA->id, $record->agency_id);
        $this->assertSame('Agency A original', $record->payload['title']);
    }

    public function test_workspace_does_not_insert_new_rows_before_create_permission_is_checked(): void
    {
        [$viewer] = $this->agencyUser(['properties.view']);

        $this->putJson('/api/workspace', [
            'collections' => ['properties' => [[
                'id' => 'unauthorized-new-property',
                'title' => 'Must not be persisted',
            ]]],
        ], $this->authHeaders($this->tokenFor($viewer)))->assertOk();

        $this->assertDatabaseMissing('business_records', [
            'collection' => 'properties',
            'record_id' => 'unauthorized-new-property',
        ]);
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

    public function test_property_viewer_without_sensitive_permission_receives_redacted_workspace_rows(): void
    {
        [$user, $agency] = $this->agencyUser(['properties.view']);
        BusinessRecord::put('properties', 'redacted-property', [
            'id' => 'redacted-property',
            'title' => 'Public-facing title',
            'purchasePricePKR' => 4000000,
            'sellingPricePKR' => 5000000,
            'internalNotes' => 'Confidential owner note',
        ], $agency->id);

        $rows = $this->getJson('/api/workspace', $this->authHeaders($this->tokenFor($user)))
            ->assertOk()
            ->json('properties');

        $this->assertCount(1, $rows);
        $this->assertSame('Public-facing title', $rows[0]['title']);
        $this->assertArrayNotHasKey('purchasePricePKR', $rows[0]);
        $this->assertArrayNotHasKey('sellingPricePKR', $rows[0]);
        $this->assertArrayNotHasKey('internalNotes', $rows[0]);
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

    public function test_void_permission_can_only_void_unpaid_deals_without_paid_commissions(): void
    {
        [$user, $agency] = $this->agencyUser(['void_transactions']);
        BusinessRecord::put('transactions', 'unpaid-deal', [
            'id' => 'unpaid-deal',
            'status' => 'Draft',
            'voided' => false,
            'advancePaymentPKR' => 0,
            'agreementAmountPKR' => 100000,
            'remainingBalancePKR' => 100000,
        ], $agency->id);
        BusinessRecord::put('transactions', 'paid-deal', [
            'id' => 'paid-deal',
            'status' => 'Draft',
            'voided' => false,
            'advancePaymentPKR' => 5000,
            'agreementAmountPKR' => 100000,
            'remainingBalancePKR' => 95000,
        ], $agency->id);
        BusinessRecord::put('transactions', 'paid-commission-deal', [
            'id' => 'paid-commission-deal',
            'status' => 'Draft',
            'voided' => false,
            'advancePaymentPKR' => 0,
            'agreementAmountPKR' => 100000,
            'remainingBalancePKR' => 100000,
        ], $agency->id);
        BusinessRecord::put('payments', 'collected-payment', [
            'id' => 'collected-payment',
            'transactionId' => 'payment-ledger-deal',
            'amountPKR' => 5000,
            'type' => 'payment',
        ], $agency->id);
        BusinessRecord::put('transactions', 'payment-ledger-deal', [
            'id' => 'payment-ledger-deal',
            'status' => 'Draft',
            'voided' => false,
            'advancePaymentPKR' => 0,
            'agreementAmountPKR' => 100000,
            'remainingBalancePKR' => 100000,
        ], $agency->id);
        BusinessRecord::put('commissions', 'unpaid-commission', [
            'id' => 'unpaid-commission',
            'transactionId' => 'unpaid-deal',
            'status' => 'Accrued',
            'paidPKR' => 0,
        ], $agency->id);
        BusinessRecord::put('commissions', 'paid-commission', [
            'id' => 'paid-commission',
            'transactionId' => 'paid-commission-deal',
            'status' => 'Paid',
            'paidPKR' => 5000,
        ], $agency->id);

        $headers = $this->authHeaders($this->tokenFor($user));
        $this->putJson('/api/workspace', [
            'collections' => ['transactions' => [
                ['id' => 'unpaid-deal', 'status' => 'Cancelled', 'voided' => true, 'voidReason' => 'Duplicate booking.', 'advancePaymentPKR' => 0, 'agreementAmountPKR' => 100000, 'remainingBalancePKR' => 100000],
                ['id' => 'paid-deal', 'status' => 'Cancelled', 'voided' => true, 'voidReason' => 'Duplicate booking.', 'advancePaymentPKR' => 5000, 'agreementAmountPKR' => 100000, 'remainingBalancePKR' => 95000],
                ['id' => 'paid-commission-deal', 'status' => 'Cancelled', 'voided' => true, 'voidReason' => 'Duplicate booking.', 'advancePaymentPKR' => 0, 'agreementAmountPKR' => 100000, 'remainingBalancePKR' => 100000],
                ['id' => 'payment-ledger-deal', 'status' => 'Cancelled', 'voided' => true, 'voidReason' => 'Duplicate booking.', 'advancePaymentPKR' => 0, 'agreementAmountPKR' => 100000, 'remainingBalancePKR' => 100000],
            ]],
        ], $headers)->assertOk();

        $voided = $this->record('transactions', 'unpaid-deal')->payload;
        $this->assertSame('Cancelled', $voided['status']);
        $this->assertTrue($voided['voided']);
        $this->assertNotEmpty($voided['voidedAt']);
        $this->assertSame($user->name, $voided['voidedBy']);
        $this->assertSame('Cancelled', $voided['commissionStatus']);
        $this->assertSame('Cancelled', $this->record('commissions', 'unpaid-commission')->payload['status']);

        $this->assertSame('Draft', $this->record('transactions', 'paid-deal')->payload['status']);
        $this->assertSame('Draft', $this->record('transactions', 'paid-commission-deal')->payload['status']);
        $this->assertSame('Draft', $this->record('transactions', 'payment-ledger-deal')->payload['status']);
    }

    public function test_workspace_cannot_void_while_adding_payment_or_paid_commission_in_same_save(): void
    {
        [, $agency] = $this->agencyUser(['finance.manage_payments']);
        $this->seedPlatformRole('super_admin');
        $user = $this->makeUser('super_admin');
        BusinessRecord::put('transactions', 'same-save-payment-deal', [
            'id' => 'same-save-payment-deal',
            'status' => 'Draft',
            'voided' => false,
            'advancePaymentPKR' => 0,
            'agreementAmountPKR' => 100000,
            'remainingBalancePKR' => 100000,
        ], $agency->id);
        BusinessRecord::put('transactions', 'same-save-commission-deal', [
            'id' => 'same-save-commission-deal',
            'status' => 'Draft',
            'voided' => false,
            'advancePaymentPKR' => 0,
            'agreementAmountPKR' => 100000,
            'remainingBalancePKR' => 100000,
        ], $agency->id);
        BusinessRecord::put('commissions', 'same-save-paid-commission', [
            'id' => 'same-save-paid-commission',
            'transactionId' => 'same-save-commission-deal',
            'amountPKR' => 10000,
            'paidPKR' => 0,
            'status' => 'Accrued',
            'accruedOn' => now()->toDateString(),
            'payouts' => [],
        ], $agency->id);

        $this->putJson('/api/workspace', [
            'collections' => [
                'transactions' => [
                    ['id' => 'same-save-payment-deal', 'status' => 'Cancelled', 'voided' => true, 'voidReason' => 'Duplicate booking.', 'advancePaymentPKR' => 0, 'agreementAmountPKR' => 100000, 'remainingBalancePKR' => 100000],
                    ['id' => 'same-save-commission-deal', 'status' => 'Cancelled', 'voided' => true, 'voidReason' => 'Duplicate booking.', 'advancePaymentPKR' => 0, 'agreementAmountPKR' => 100000, 'remainingBalancePKR' => 100000],
                ],
                'payments' => [[
                    'id' => 'same-save-payment',
                    'transactionId' => 'same-save-payment-deal',
                    'amountPKR' => 5000,
                    'type' => 'payment',
                    'paidAt' => now()->toDateString(),
                ]],
                'commissions' => [[
                    'id' => 'same-save-paid-commission',
                    'transactionId' => 'same-save-commission-deal',
                    'amountPKR' => 10000,
                    'paidPKR' => 10000,
                    'status' => 'Paid',
                    'accruedOn' => now()->toDateString(),
                    'payouts' => [[
                        'id' => 'same-save-final-payout',
                        'amountPKR' => 10000,
                        'paidAt' => now()->toDateString(),
                        'recordedBy' => 'forged-client-value',
                        'note' => 'Final payout',
                    ]],
                ]],
            ],
        ], $this->authHeaders($this->tokenFor($user)))->assertOk();

        $this->assertSame('Draft', $this->record('transactions', 'same-save-payment-deal')->payload['status']);
        $this->assertSame('Draft', $this->record('transactions', 'same-save-commission-deal')->payload['status']);
        $this->assertDatabaseHas('business_records', [
            'collection' => 'payments',
            'record_id' => 'same-save-payment',
        ]);
        $this->assertDatabaseHas('business_records', [
            'collection' => 'commissions',
            'record_id' => 'same-save-paid-commission',
        ]);
    }

    public function test_workspace_payment_and_refund_movements_cannot_overdraw_or_overpay(): void
    {
        [$manager, $agency] = $this->agencyUser(['finance.manage_payments']);
        BusinessRecord::put('transactions', 'payment-guard-deal', [
            'id' => 'payment-guard-deal',
            'status' => 'Under Transfer',
            'agreementAmountPKR' => 100000,
            'advancePaymentPKR' => 0,
        ], $agency->id);
        $headers = $this->authHeaders($this->tokenFor($manager));

        $this->putJson('/api/workspace', [
            'collections' => ['payments' => [[
                'id' => 'payment-guard-1',
                'transactionId' => 'payment-guard-deal',
                'amountPKR' => 70000,
                'type' => 'payment',
            ]]],
        ], $headers)->assertOk();

        $this->putJson('/api/workspace', [
            'collections' => ['payments' => [[
                'id' => 'refund-overdraw',
                'transactionId' => 'payment-guard-deal',
                'amountPKR' => 80000,
                'type' => 'refund',
                'note' => 'Attempted over-refund',
            ]]],
        ], $headers)->assertStatus(422);

        $this->putJson('/api/workspace', [
            'collections' => ['payments' => [[
                'id' => 'payment-overpay',
                'transactionId' => 'payment-guard-deal',
                'amountPKR' => 40000,
                'type' => 'payment',
            ]]],
        ], $headers)->assertStatus(422);

        $this->putJson('/api/workspace', [
            'collections' => ['payments' => [[
                'id' => 'refund-valid',
                'transactionId' => 'payment-guard-deal',
                'amountPKR' => 20000,
                'type' => 'refund',
                'note' => 'Buyer refund approved by agency manager.',
            ]]],
        ], $headers)->assertOk();

        BusinessRecord::put('transactions', 'payment-guard-deal', [
            'id' => 'payment-guard-deal',
            'status' => 'Cancelled',
            'agreementAmountPKR' => 100000,
            'advancePaymentPKR' => 0,
        ], $agency->id);
        $this->putJson('/api/workspace', [
            'collections' => ['payments' => [[
                'id' => 'payment-after-cancel',
                'transactionId' => 'payment-guard-deal',
                'amountPKR' => 1,
                'type' => 'payment',
            ]]],
        ], $headers)->assertStatus(422);

        $this->putJson('/api/workspace', [
            'collections' => ['payments' => [[
                'id' => 'refund-after-cancel',
                'transactionId' => 'payment-guard-deal',
                'amountPKR' => 50000,
                'type' => 'refund',
                'note' => 'Final cancellation refund.',
            ]]],
        ], $headers)->assertOk();
    }

    public function test_first_advance_ledger_entry_does_not_double_count_legacy_advance(): void
    {
        [$manager, $agency] = $this->agencyUser(['finance.manage_payments']);
        BusinessRecord::put('transactions', 'advance-migration-deal', [
            'id' => 'advance-migration-deal',
            'status' => 'Fully Paid',
            'agreementAmountPKR' => 100000,
            'advancePaymentPKR' => 100000,
        ], $agency->id);
        $headers = $this->authHeaders($this->tokenFor($manager));

        $this->putJson('/api/workspace', [
            'collections' => ['payments' => [[
                'id' => 'advance-migration-ledger-row',
                'transactionId' => 'advance-migration-deal',
                'amountPKR' => 100000,
                'type' => 'payment',
                'note' => 'Advance recorded at deal creation.',
            ]]],
        ], $headers)->assertOk();

        $this->putJson('/api/workspace', [
            'collections' => ['payments' => [[
                'id' => 'advance-migration-overpay',
                'transactionId' => 'advance-migration-deal',
                'amountPKR' => 1,
                'type' => 'payment',
            ]]],
        ], $headers)->assertStatus(422);
    }

    public function test_workspace_commission_accrual_and_payout_ledger_are_server_enforced(): void
    {
        [$manager, $agency] = $this->agencyUser(['finance.manage_payments']);
        BusinessRecord::put('transactions', 'commission-ledger-deal', [
            'id' => 'commission-ledger-deal',
            'status' => 'Draft',
            'agreementAmountPKR' => 100000,
            'advancePaymentPKR' => 0,
            'remainingBalancePKR' => 100000,
        ], $agency->id);
        $managerHeaders = $this->authHeaders($this->tokenFor($manager));

        $this->putJson('/api/workspace', [
            'collections' => ['commissions' => [[
                'id' => 'commission-ledger-entry',
                'transactionId' => 'commission-ledger-deal',
                'amountPKR' => 10000,
                'paidPKR' => 10000,
                'status' => 'Paid',
                'accruedOn' => now()->toDateString(),
                'payouts' => [[
                    'id' => 'forged-payout',
                    'amountPKR' => 10000,
                    'paidAt' => now()->toDateString(),
                    'recordedBy' => 'forged',
                ]],
            ]]],
        ], $managerHeaders)->assertStatus(422);
        $this->assertDatabaseMissing('business_records', [
            'collection' => 'commissions',
            'record_id' => 'commission-ledger-entry',
        ]);

        $pending = [
            'id' => 'commission-ledger-entry',
            'transactionId' => 'commission-ledger-deal',
            'amountPKR' => 10000,
            'paidPKR' => 0,
            'status' => 'Pending Accrual',
            'payouts' => [],
        ];
        $this->putJson('/api/workspace', [
            'collections' => ['commissions' => [$pending]],
        ], $managerHeaders)->assertOk();

        $earlyAccrual = $pending;
        $earlyAccrual['status'] = 'Accrued';
        $earlyAccrual['accruedOn'] = now()->toDateString();
        $this->putJson('/api/workspace', [
            'collections' => ['commissions' => [$earlyAccrual]],
        ], $managerHeaders)->assertStatus(422);

        $this->putJson('/api/workspace', [
            'collections' => [
                'payments' => [[
                    'id' => 'commission-ledger-payment',
                    'transactionId' => 'commission-ledger-deal',
                    'amountPKR' => 100000,
                    'type' => 'payment',
                    'paidAt' => now()->toDateString(),
                ]],
                'commissions' => [$earlyAccrual],
            ],
        ], $managerHeaders)->assertOk();

        $partialPayout = $earlyAccrual;
        $partialPayout['status'] = 'Partially Paid';
        $partialPayout['paidPKR'] = 4000;
        $partialPayout['payouts'] = [[
            'id' => 'commission-payout-1',
            'amountPKR' => 4000,
            'paidAt' => 'client-supplied-date',
            'recordedBy' => 'client-supplied-actor',
            'note' => 'First payout',
        ]];
        $this->putJson('/api/workspace', [
            'collections' => ['commissions' => [$partialPayout]],
        ], $managerHeaders)->assertForbidden();

        $this->seedPlatformRole('super_admin');
        $platformAdmin = $this->makeUser('super_admin');
        $adminHeaders = $this->authHeaders($this->tokenFor($platformAdmin));
        $adminHeaders['X-Agency-Id'] = (string) $agency->id;

        $this->putJson('/api/workspace', [
            'collections' => ['commissions' => [$partialPayout]],
        ], $adminHeaders)->assertOk();

        $stored = $this->record('commissions', 'commission-ledger-entry')->payload;
        $this->assertSame(4000, (int) $stored['paidPKR']);
        $this->assertSame($platformAdmin->name, $stored['payouts'][0]['recordedBy']);
        $this->assertSame(now()->toDateString(), $stored['payouts'][0]['paidAt']);

        $editedPayout = $stored;
        $editedPayout['payouts'][0]['amountPKR'] = 3000;
        $editedPayout['paidPKR'] = 3000;
        $this->putJson('/api/workspace', [
            'collections' => ['commissions' => [$editedPayout]],
        ], $adminHeaders)->assertStatus(422);

        $overpaid = $stored;
        $overpaid['payouts'][] = [
            'id' => 'commission-payout-overpay',
            'amountPKR' => 7000,
            'note' => 'Would exceed commission.',
        ];
        $overpaid['paidPKR'] = 11000;
        $this->putJson('/api/workspace', [
            'collections' => ['commissions' => [$overpaid]],
        ], $adminHeaders)->assertStatus(422);

        $finalPayout = $stored;
        $finalPayout['payouts'][] = [
            'id' => 'commission-payout-2',
            'amountPKR' => 6000,
            'note' => 'Final payout',
        ];
        $finalPayout['paidPKR'] = 10000;
        $finalPayout['status'] = 'Paid';
        $this->putJson('/api/workspace', [
            'collections' => ['commissions' => [$finalPayout]],
        ], $adminHeaders)->assertOk();

        $paid = $this->record('commissions', 'commission-ledger-entry')->payload;
        $this->assertSame('Paid', $paid['status']);
        $this->assertSame(10000, (int) $paid['paidPKR']);
        $this->assertCount(2, $paid['payouts']);
    }

    public function test_workspace_requires_cancel_permission_and_reason_for_non_void_cancellation(): void
    {
        [$updater, $agency] = $this->agencyUser(['update_transactions']);
        BusinessRecord::put('transactions', 'cancelled-deal', [
            'id' => 'cancelled-deal',
            'status' => 'Draft',
            'advancePaymentPKR' => 0,
            'agreementAmountPKR' => 100000,
            'remainingBalancePKR' => 100000,
        ], $agency->id);

        $this->putJson('/api/workspace', [
            'collections' => ['transactions' => [[
                'id' => 'cancelled-deal', 'status' => 'Cancelled', 'notes' => 'Duplicate record.',
                'advancePaymentPKR' => 0, 'agreementAmountPKR' => 100000, 'remainingBalancePKR' => 100000,
            ]]],
        ], $this->authHeaders($this->tokenFor($updater)))->assertOk();
        $this->assertSame('Draft', $this->record('transactions', 'cancelled-deal')->payload['status']);

        [$canceller, $otherAgency] = $this->agencyUser(['legacy.cancel_transactions']);
        $this->assertTrue($canceller->hasPermission('legacy.cancel_transactions', $otherAgency->id));
        $this->assertTrue($canceller->hasPermission('cancel_transactions', $otherAgency->id));
        BusinessRecord::put('transactions', 'reason-required-deal', [
            'id' => 'reason-required-deal',
            'status' => 'Draft',
            'advancePaymentPKR' => 0,
            'agreementAmountPKR' => 100000,
            'remainingBalancePKR' => 100000,
        ], $otherAgency->id);
        $headers = $this->authHeaders($this->tokenFor($canceller));
        $this->putJson('/api/workspace', [
            'collections' => ['transactions' => [[
                'id' => 'reason-required-deal', 'status' => 'Cancelled',
                'advancePaymentPKR' => 0, 'agreementAmountPKR' => 100000, 'remainingBalancePKR' => 100000,
            ]]],
        ], $headers)->assertOk();
        $this->assertSame('Draft', $this->record('transactions', 'reason-required-deal')->payload['status']);

        $this->putJson('/api/workspace', [
            'collections' => ['transactions' => [[
                'id' => 'reason-required-deal', 'status' => 'Cancelled', 'notes' => 'Duplicate record.',
                'advancePaymentPKR' => 0, 'agreementAmountPKR' => 100000, 'remainingBalancePKR' => 100000,
            ]]],
        ], $headers)->assertOk();
        $this->assertSame('Cancelled', $this->record('transactions', 'reason-required-deal')->payload['status']);
    }

    public function test_workspace_enforces_deal_status_transition_rules(): void
    {
        [$user, $agency] = $this->agencyUser(['update_transactions']);
        BusinessRecord::put('transactions', 'stage-deal', [
            'id' => 'stage-deal',
            'status' => 'Draft',
            'advancePaymentPKR' => 0,
            'agreementAmountPKR' => 100000,
            'remainingBalancePKR' => 100000,
        ], $agency->id);
        $headers = $this->authHeaders($this->tokenFor($user));

        $this->putJson('/api/workspace', [
            'collections' => ['transactions' => [[
                'id' => 'stage-deal', 'status' => 'Completed',
                'advancePaymentPKR' => 0, 'agreementAmountPKR' => 100000, 'remainingBalancePKR' => 100000,
            ]]],
        ], $headers)->assertOk();
        $this->assertSame('Draft', $this->record('transactions', 'stage-deal')->payload['status']);

        $this->putJson('/api/workspace', [
            'collections' => ['transactions' => [[
                'id' => 'stage-deal', 'status' => 'Negotiation',
                'advancePaymentPKR' => 0, 'agreementAmountPKR' => 100000, 'remainingBalancePKR' => 100000,
            ]]],
        ], $headers)->assertOk();
        $this->assertSame('Negotiation', $this->record('transactions', 'stage-deal')->payload['status']);

        $this->putJson('/api/workspace', [
            'collections' => ['transactions' => [[
                'id' => 'stage-deal', 'status' => 'Draft',
                'advancePaymentPKR' => 0, 'agreementAmountPKR' => 100000, 'remainingBalancePKR' => 100000,
            ]]],
        ], $headers)->assertOk();
        $this->assertSame('Draft', $this->record('transactions', 'stage-deal')->payload['status']);

        BusinessRecord::where('collection', 'transactions')->where('record_id', 'stage-deal')
            ->update(['payload' => array_merge($this->record('transactions', 'stage-deal')->payload, ['status' => 'Fully Paid'])]);
        $this->putJson('/api/workspace', [
            'collections' => ['transactions' => [[
                'id' => 'stage-deal', 'status' => 'Draft',
                'advancePaymentPKR' => 0, 'agreementAmountPKR' => 100000, 'remainingBalancePKR' => 100000,
            ]]],
        ], $headers)->assertOk();
        $this->assertSame('Fully Paid', $this->record('transactions', 'stage-deal')->payload['status']);
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


    public function test_workspace_creates_parent_dealer_before_property_reference_in_one_sync(): void
    {
        [$user, $agency] = $this->agencyUser(['manage_dealers', 'properties.create']);

        $this->putJson('/api/workspace', [
            'collections' => [
                'dealers' => [[
                    'id' => 'workspace-new-dealer',
                    'fullName' => 'New Workspace Dealer',
                    'status' => 'Active',
                ]],
                'properties' => [[
                    'id' => 'property-assigned-to-new-dealer',
                    'title' => 'Property with new dealer',
                    'assignedDealerId' => 'workspace-new-dealer',
                ]],
            ],
        ], $this->authHeaders($this->tokenFor($user)))->assertOk();

        $this->assertDatabaseHas('business_records', [
            'collection' => 'dealers',
            'record_id' => 'workspace-new-dealer',
            'agency_id' => $agency->id,
        ]);
        $this->assertDatabaseHas('business_records', [
            'collection' => 'properties',
            'record_id' => 'property-assigned-to-new-dealer',
            'agency_id' => $agency->id,
        ]);
    }

}
