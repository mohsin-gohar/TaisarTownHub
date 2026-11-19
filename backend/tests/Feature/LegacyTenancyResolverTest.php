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
        $journal = $this->tempPath('rollback');
        $this->artisan('platform:legacy-backfill', [
            '--apply' => true,
            '--backup' => $this->writeDump(),
            '--journal' => $journal,
        ])->assertExitCode(0);
        $this->assertSame('agency-a', BusinessRecord::where('record_id', 'cm-1')->value('agency_id'));
        $this->assertSame($before, BusinessRecord::count());
        $this->assertFileExists($journal);
    }

    public function test_composite_mapping_never_cross_assigns_identical_record_ids(): void
    {
        BusinessRecord::put('properties', 'shared-1', ['title' => 'One']);
        BusinessRecord::put('videos', 'shared-1', ['title' => 'Two']);

        $mapping = $this->writeJson([
            'properties' => ['shared-1' => 'agency-a'],
            'videos' => ['shared-1' => 'agency-b'],
        ]);
        $journal = $this->tempPath('rollback');

        $this->artisan('platform:legacy-backfill', [
            '--mapping' => $mapping,
            '--apply' => true,
            '--backup' => $this->writeDump(),
            '--journal' => $journal,
        ])->assertExitCode(0);

        $this->assertSame('agency-a', BusinessRecord::where('collection', 'properties')->where('record_id', 'shared-1')->value('agency_id'));
        $this->assertSame('agency-b', BusinessRecord::where('collection', 'videos')->where('record_id', 'shared-1')->value('agency_id'));

        // Rollback restores both exactly, and replaying it is a no-op.
        $this->artisan('platform:legacy-backfill', ['--rollback' => $journal])->assertExitCode(0);
        $this->assertNull(BusinessRecord::where('collection', 'properties')->where('record_id', 'shared-1')->value('agency_id'));
        $this->assertNull(BusinessRecord::where('collection', 'videos')->where('record_id', 'shared-1')->value('agency_id'));
        $this->artisan('platform:legacy-backfill', ['--rollback' => $journal])->assertExitCode(0);
    }

    public function test_flat_mapping_key_is_skipped_when_record_id_is_ambiguous(): void
    {
        BusinessRecord::put('subscribers', 'shared-2', ['email' => 'one@example.test']);
        BusinessRecord::put('testimonials', 'shared-2', ['email' => 'two@example.test']);

        $this->artisan('platform:legacy-backfill', [
            '--mapping' => $this->writeJson(['shared-2' => 'agency-a']),
            '--apply' => true,
            '--backup' => $this->writeDump(),
            '--journal' => $this->tempPath('rollback'),
        ])->assertExitCode(0);

        $this->assertNull(BusinessRecord::where('collection', 'subscribers')->where('record_id', 'shared-2')->value('agency_id'));
        $this->assertNull(BusinessRecord::where('collection', 'testimonials')->where('record_id', 'shared-2')->value('agency_id'));
    }

    public function test_apply_rejects_unreadable_empty_and_unrelated_backups(): void
    {
        BusinessRecord::put('commissions', 'cm-1', ['dealerId' => 'deal-1']);

        $this->artisan('platform:legacy-backfill', ['--apply' => true])->assertExitCode(1);

        $empty = $this->tempPath('empty');
        file_put_contents($empty, '');
        $this->artisan('platform:legacy-backfill', ['--apply' => true, '--backup' => $empty])->assertExitCode(1);

        $unrelated = $this->tempPath('source');
        file_put_contents($unrelated, '<?php echo "not a database dump";');
        $this->artisan('platform:legacy-backfill', ['--apply' => true, '--backup' => $unrelated])->assertExitCode(1);

        $this->assertNull(BusinessRecord::where('record_id', 'cm-1')->value('agency_id'));
    }

    public function test_backup_verifier_accepts_gzip_and_detects_truncation(): void
    {
        $sql = "-- mysqldump 10.13\nCREATE TABLE `business_records` (`id` int NOT NULL);\n-- Dump completed on 2026-01-01 00:00:00\n";
        $gz = gzencode($sql);
        $good = $this->tempPath('dump.sql.gz');
        file_put_contents($good, $gz);

        $verified = \App\Support\LegacyBackfillBackup::verify($good);
        $this->assertSame('sql-dump', $verified->format());
        $this->assertSame(hash_file('sha256', $good), $verified->sha256());
        $this->assertSame(1, $verified->tableCount());

        $truncated = $this->tempPath('truncated.sql.gz');
        file_put_contents($truncated, substr($gz, 0, intdiv(strlen($gz), 2)));
        try {
            \App\Support\LegacyBackfillBackup::verify($truncated);
            $this->fail('Truncated gzip dump must be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('truncated', $e->getMessage());
        }

        $noMarker = $this->tempPath('no-marker.sql');
        file_put_contents($noMarker, str_replace('-- Dump completed on 2026-01-01 00:00:00', '', $sql));
        try {
            \App\Support\LegacyBackfillBackup::verify($noMarker);
            $this->fail('mysqldump without its completion marker must be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Dump completed', $e->getMessage());
        }
    }

    public function test_rollback_restores_existing_owner_without_touching_conflicting_rows(): void
    {
        BusinessRecord::put('commissions', 'cm-1', ['dealerId' => 'deal-1']);
        BusinessRecord::put('commissions', 'cm-2', ['dealerId' => 'deal-1'], 'agency-b');
        $journal = $this->tempPath('rollback');
        $dump = $this->writeDump();

        $this->artisan('platform:legacy-backfill', [
            '--apply' => true,
            '--backup' => $dump,
            '--journal' => $journal,
        ])->assertExitCode(0);

        $decoded = json_decode((string) file_get_contents($journal), true);
        $this->assertCount(1, $decoded['rows']);
        $this->assertSame('cm-1', $decoded['rows'][0]['record_id']);
        $this->assertNull($decoded['rows'][0]['previous_agency_id']);
        $this->assertSame('agency-a', $decoded['rows'][0]['stamped_agency_id']);
        $this->assertSame(hash_file('sha256', $dump), $decoded['backup']['sha256']);

        $this->artisan('platform:legacy-backfill', ['--rollback' => $journal])->assertExitCode(0);
        $this->assertNull(BusinessRecord::where('record_id', 'cm-1')->value('agency_id'));
        $this->assertSame('agency-b', BusinessRecord::where('record_id', 'cm-2')->value('agency_id'));
    }

    public function test_video_typed_twin_resolves_camel_case_owner_column(): void
    {
        DB::table('agency_videos')->insert([
            'id' => 'vid-twin-1',
            'title' => 'Twin Video',
            'videoUrl' => 'https://example.test/video.mp4',
            'agencyId' => 'agency-b',
            'agencyName' => 'Agency B',
            'phase' => 'Phase 1',
            'duration' => '3:00',
            'likes' => 0,
            'views' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = BusinessRecord::put('videos', 'vid-twin-1', ['title' => 'Twin Video']);

        $resolver = new LegacyTenancyResolver();
        $this->assertSame('agency-b', $resolver->resolve($row));
        $this->assertSame(['agency-b'], array_keys($resolver->candidates($row)));

        $this->artisan('platform:legacy-audit')->assertExitCode(0);
        $this->artisan('platform:legacy-ownership')->assertExitCode(0);
    }

    private function tempPath(string $label): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tth_' . $label . '_' . bin2hex(random_bytes(4));
    }

    private function writeDump(): string
    {
        $path = $this->tempPath('valid_dump.sql');
        file_put_contents($path, "-- MySQL dump\nCREATE TABLE `business_records` (`id` int NOT NULL);\n");

        return $path;
    }

    private function writeJson(array $data): string
    {
        $path = $this->tempPath('mapping.json');
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));

        return $path;
    }
}
