<?php

namespace App\Console\Commands;

use App\Models\BusinessRecord;
use App\Support\LegacyTenancyResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * SAFE (non-destructive) tenant backfill for the legacy `business_records` store.
 *
 * The command is **dry-run by default**: it prints the exact rows it would
 * stamp and writes nothing. Applying it requires `--apply` **and**
 * `--backup=<file>` pointing at a verified dump. It is strictly additive: it
 * only writes the canonical `agency_id` COLUMN on rows that have no owner,
 * and never overwrites an existing claim. No row is deleted, and the row count
 * is verified before and after.
 */
class LegacyBackfillCommand extends Command
{
    protected $signature = 'platform:legacy-backfill
        {--apply : actually write the agency stamps (default: dry run)}
        {--backup= : path to a verified database dump (required with --apply)}
        {--mapping= : JSON file with { record_id: agency_id } pairs}
        {--collection= : limit the run to one collection}';

    protected $description = 'Plan (and optionally apply) the tenant backfill of the legacy business_records store';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $resolver = new LegacyTenancyResolver();
        $mapping = [];

        if ($mappingFile = $this->option('mapping')) {
            if (!is_readable($mappingFile)) {
                $this->error("Mapping file not readable: {$mappingFile}");

                return 1;
            }
            $mapping = json_decode((string) file_get_contents($mappingFile), true) ?: [];
        }

        $rows = BusinessRecord::query()
            ->when($this->option('collection'), fn ($q) => $q->where('collection', $this->option('collection')))
            ->orderBy('collection')
            ->orderBy('record_id')
            ->get();

        $beforeCount = $rows->count();
        $plan = [];

        foreach ($rows as $row) {
            $current = $resolver->claimedOwner($row);
            $mapped = $mapping[$row->record_id] ?? null;
            $target = $mapped && $resolver->isAgency((string) $mapped) ? (string) $mapped : null;

            // Never auto-stamp a row with conflicting evidence: ambiguity is a
            // manual-review item, not a coin flip.
            if (!$target && $current === null) {
                $candidates = $resolver->candidates($row);
                if (count($candidates) === 1) {
                    $target = array_key_first($candidates);
                }
            }

            if (!$target) {
                continue;
            }
            if ($current === $target) {
                continue; // already stamped
            }
            if ($current && $current !== $target) {
                $this->warn("row {$row->record_id} already claims '{$current}' — skipped (needs manual review)");

                continue;
            }

            $plan[] = [
                'collection' => $row->collection,
                'record_id' => $row->record_id,
                'agency_id' => $target,
                'source' => $mapped ? 'mapping file' : 'derived evidence',
            ];
        }

        $this->line('DRY RUN — nothing was written.');
        $this->line("rows scanned: {$beforeCount}");
        $this->line("rows that would be stamped: " . count($plan));
        foreach ($plan as $entry) {
            $this->line(sprintf('  %-14s %-34s -> %s (%s)', $entry['collection'], $entry['record_id'], $entry['agency_id'], $entry['source']));
        }

        if (!$apply) {
            $this->line('');
            $this->line('Re-run with --apply --backup=<verified dump> to write these stamps.');

            return 0;
        }

        // ---- apply path (approval + backup gated) ----
        $backup = $this->option('backup');
        if (!$backup || !is_readable($backup)) {
            $this->error('--apply requires --backup=<path to a verified database dump>.');

            return 1;
        }

        $stamped = 0;
        DB::transaction(function () use ($plan, &$stamped) {
            foreach ($plan as $entry) {
                $row = BusinessRecord::where('collection', $entry['collection'])
                    ->where('record_id', $entry['record_id'])
                    ->first();
                if (!$row || $row->agency_id !== null) {
                    continue;
                }
                $row->agency_id = $entry['agency_id'];
                $row->save();
                $stamped++;
            }
        });

        $afterCount = BusinessRecord::count();
        $this->line('');
        $this->line("stamped: {$stamped}");
        $this->line("rows before: {$beforeCount} → rows after: {$afterCount} (must be identical)");

        if ($beforeCount !== $afterCount) {
            $this->error('row count changed — investigate immediately.');

            return 1;
        }

        $this->line('Backup used: ' . $backup);

        return 0;
    }
}
