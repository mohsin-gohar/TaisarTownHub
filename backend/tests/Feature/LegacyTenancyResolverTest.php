<?php

namespace Tests\Feature;

use App\Models\BusinessRecord;
use App\Support\LegacyTenancyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyTenancyResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('agencies')->insert([
            ['id' => 'agency-a', 'name' => 'Agency A', 'slug' => 'agency-a'],
            ['id' => 'agency-b', 'name' => 'Agency B', 'slug' => 'agency-b'],
        ]);
        DB::table('dealers')->insert([
            ['id' => 'deal-1', 'fullName' => 'Dealer One', 'phone' => '03000000001', 'agency_id' => 'agency-a'],
        ]);
        DB::table('properties')->insert([
            ['id' => 'prop-1', 'title' => 'Property One', 'agency_id' => 'agency-b'],
        ]);
    }

    public function test_canonical_column_wins_over_a_bogus_payload_claim(): void
    {
        $row = BusinessRecord::put('properties', 'owned', [
            'agencyId' => 'no-such-agency',
        ], 'agency-a');

        $resolver = new LegacyTenancyResolver();
        $this->assertSame('agency-a', $resolver->claimedOwner($row));
        $this->assertNull($resolver->bogusClaim($row));
    }

    public function test_resolves_dealer_property_and_exact_agency_name_evidence(): void
    {
        $resolver = new LegacyTenancyResolver();

        $viaDealer = BusinessRecord::put('commissions', 'cm-1', ['dealerId' => 'deal-1']);
        $this->assertSame('agency-a', $resolver->resolve($viaDealer));

        // Legacy videos store a dealer id inside agencyId; it is still a
        // provable reference through the stamped dealer row.
        $viaLegacyDealer = BusinessRecord::put('videos', 'vid-1', ['agencyId' => 'deal-1']);
        $this->assertSame('agency-a', $resolver->resolve($viaLegacyDealer));

        $viaProperty = BusinessRecord::put('files', 'doc-1', ['propertyId' => 'prop-1']);
        $this->assertSame('agency-b', $resolver->resolve($viaProperty));

        $viaName = BusinessRecord::put('videos', 'vid-2', ['agencyName' => 'Agency B']);
        $this->assertSame('agency-b', $resolver->resolve($viaName));
    }

    public function test_never_guesses_when_evidence_is_missing_or_conflicting(): void
    {
        $resolver = new LegacyTenancyResolver();

        $noEvidence = BusinessRecord::put('subscribers', 'sub-1', ['email' => 'x@example.test']);
        $this->assertNull($resolver->resolve($noEvidence));
        $this->assertNull($resolver->bogusClaim($noEvidence));

        $bogus = BusinessRecord::put('videos', 'vid-3', ['agencyId' => 'deal-missing']);
        $this->assertNull($resolver->resolve($bogus));
        $this->assertSame('deal-missing', $resolver->bogusClaim($bogus));

        $conflicting = BusinessRecord::put('videos', 'vid-4', [
            'dealerId' => 'deal-1', 'agencyName' => 'Agency B',
        ]);
        $this->assertNull($resolver->resolve($conflicting));
        $this->assertCount(2, $resolver->candidates($conflicting));
    }

    public function test_backfill_dry_run_writes_nothing_and_apply_requires_a_backup(): void
    {
        BusinessRecord::put('commissions', 'cm-1', ['dealerId' => 'deal-1']);

        $this->artisan('platform:legacy-backfill')->assertExitCode(0);
        $this->assertNull(BusinessRecord::where('record_id', 'cm-1')->value('agency_id'));

        $this->artisan('platform:legacy-backfill', ['--apply' => true])->assertExitCode(1);
        $this->assertNull(BusinessRecord::where('record_id', 'cm-1')->value('agency_id'));
    }

    public function test_ownership_report_stays_read_only_and_flags_confirmed_rows(): void
    {
        BusinessRecord::put('commissions', 'cm-1', ['dealerId' => 'deal-1']);

        $this->artisan('platform:legacy-ownership')->assertExitCode(0);
        $this->assertNull(BusinessRecord::where('record_id', 'cm-1')->value('agency_id'));

        $before = BusinessRecord::count();
        $this->artisan('platform:legacy-backfill', ['--apply' => true, '--backup' => __FILE__])->assertExitCode(0);
        $this->assertSame('agency-a', BusinessRecord::where('record_id', 'cm-1')->value('agency_id'));
        $this->assertSame($before, BusinessRecord::count());
    }
}
