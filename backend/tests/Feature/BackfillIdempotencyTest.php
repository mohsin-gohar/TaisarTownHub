<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;

/**
 * Regression coverage for the legacy enterprise-relations backfill:
 * re-running it must not rewrite newer relational data or public slugs.
 */
class BackfillIdempotencyTest extends EnterpriseTestCase
{
    public function test_backfill_is_gap_filling_and_does_not_clobber_newer_rows(): void
    {
        [$user, $agency] = $this->agencyUser(['properties.view'], 'manager');
        $property = $this->makePublicProperty($agency, ['slug' => 'stable-public-slug']);

        DB::table('business_records')->insert([
            'collection' => 'properties',
            'record_id' => $property->id,
            'payload' => json_encode([
                'id' => $property->id,
                'title' => 'Legacy title',
                'slug' => 'legacy-slug',
                'type' => 'Plot',
                'agencyName' => $agency->name,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('transactions')->insert([
            'id' => 'txn_legacy_1',
            'dealNumber' => 'DN-LEGACY-1',
            'propertyId' => $property->id,
            'propertyTitle' => 'Current title',
            'sellerName' => 'Current seller',
            'buyerName' => 'Current buyer',
            'agreementAmountPKR' => 999999,
            'dealStatus' => 'Approved',
            'agency_id' => $agency->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('business_records')->insert([
            'collection' => 'transactions',
            'record_id' => 'txn_legacy_1',
            'payload' => json_encode([
                'id' => 'txn_legacy_1',
                'dealNumber' => 'DN-LEGACY-1',
                'status' => 'Draft',
                'agreementAmountPKR' => 100,
                'propertyId' => $property->id,
                'propertyTitle' => 'Legacy title',
                'sellerName' => 'Legacy seller',
                'buyerName' => 'Legacy buyer',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_11_10_000016_backfill_enterprise_relations.php');
        $migration->up();

        $this->assertSame('stable-public-slug', $property->fresh()->slug);
        $this->assertSame(999999, (int) DB::table('transactions')->where('id', 'txn_legacy_1')->value('agreementAmountPKR'));
        $this->assertSame('Approved', DB::table('transactions')->where('id', 'txn_legacy_1')->value('dealStatus'));
    }
}
