<?php

namespace App\Console\Commands;

use App\Models\BusinessRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * SAFE (non-destructive) tenant backfill for the legacy `business_records` store.
 *
 * Phase 3 of the deployment plan. The command is **dry-run by default**: it
 * prints the exact rows it would stamp and writes nothing. Applying it
 * requires `--apply` **and** `--backup=<file>` pointing at a verified dump, and
 * it is strictly additive: it only adds an `agency_id` key to the JSON payload.
 * No row is ever deleted, and the row count is verified before and after.
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

        // Derivable owners: a legacy row that references a dealer/property/
        // inquiry whose typed twin already carries an agency stamp.
        $dealerAgencies = DB::table('dealers')->whereNotNull('agency_id')->pluck('agency_id', 'id')->all();
        $propertyAgencies = DB::table('properties')->whereNotNull('agency_id')->pluck('agency_id', 'id')->all();
        $inquiryAgencies = DB::table('inquiries')->whereNotNull('agency_id')->pluck('agency_id', 'id')->all();

        $plan = [];
        foreach ($rows as $row) {
            $payload = is_array($row->payload) ? $row->payload : [];
            $current = $payload['agencyId'] ?? $payload['agency_id'] ?? null;
            $target = $mapping[$row->record_id] ?? null;

            if (!$target) {
                foreach (['dealerId', 'dealer_id', 'assignedDealerId', 'soldByDealerId', 'agentId', 'agent_id'] as $key) {
                    $ref = $payload[$key] ?? null;
                    if ($ref && isset($dealerAgencies[$ref])) {
                        $target = $dealerAgencies[$ref];
                        break;
                    }
                }
            }
            if (!$target && isset($propertyAgencies[$row->record_id])) {
                $target = $propertyAgencies[$row->record_id];
            }
            if (!$target && isset($inquiryAgencies[$row->record_id])) {
                $target = $inquiryAgencies[$row->record_id];
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
            if (!DB::table('agencies')->where('id', $target)->exists()) {
                $this->warn("row {$row->record_id}: derived agency '{$target}' does not exist — skipped");

                continue;
            }

            $plan[] = [
                'collection' => $row->collection,
                'record_id' => $row->record_id,
                'agency_id' => $target,
                'source' => isset($mapping[$row->record_id]) ? 'mapping file' : 'derived',
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
                if (!$row) {
                    continue;
                }
                $payload = is_array($row->payload) ? $row->payload : [];
                $payload['agency_id'] = $entry['agency_id'];
                $row->payload = $payload;
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
