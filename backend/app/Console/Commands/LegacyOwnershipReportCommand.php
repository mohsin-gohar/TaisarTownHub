<?php

namespace App\Console\Commands;

use App\Models\BusinessRecord;
use App\Support\LegacyTenancyResolver;
use Illuminate\Console\Command;

/**
 * Per-record ownership reconciliation report for the legacy `business_records`
 * JSON store.
 *
 * This command is STRICTLY READ-ONLY. It never writes, never stamps and never
 * deletes a row. It produces, for every legacy row that has no canonical owner
 * (agency_id IS NULL), a reconciliation record that states:
 *
 *   - whether VERIFIED evidence links it to exactly one agency
 *     (a typed twin, a stamped dealer/property reference, or an exact agency
 *     name),
 *   - the evidence chain that produced that conclusion, or
 *   - `unresolved` with the reason (no evidence / ambiguous / bogus owner).
 *
 * Ownership is NEVER assigned automatically. Rows that remain unresolved stay
 * hidden from agency workspaces (see WorkspaceController::allRows()) until a
 * human-approved mapping is applied with `platform:legacy-backfill --apply`.
 */
class LegacyOwnershipReportCommand extends Command
{
    protected $signature = 'platform:legacy-ownership
        {--json : print the machine-readable report to stdout}
        {--collection= : limit the report to one collection}
        {--write= : also write the JSON report to this path}';

    protected $description = 'Read-only per-record ownership reconciliation report for unowned legacy business_records';

    public function handle(): int
    {
        $rows = BusinessRecord::query()
            ->whereNull('agency_id')
            ->when($this->option('collection'), fn ($q) => $q->where('collection', $this->option('collection')))
            ->orderBy('collection')
            ->orderBy('record_id')
            ->get();

        $resolver = new LegacyTenancyResolver();
        $records = [];

        foreach ($rows as $row) {
            $candidates = $resolver->candidates($row);
            $bogusClaim = $resolver->bogusClaim($row);

            $status = 'unresolved';
            $resolvedOwner = null;
            $reason = null;

            if (count($candidates) === 1) {
                $status = 'confirmed';
                $resolvedOwner = array_key_first($candidates);
            } elseif (count($candidates) > 1) {
                $status = 'ambiguous';
                $reason = 'evidence points at multiple agencies: ' . implode(', ', array_keys($candidates));
            } elseif ($bogusClaim !== null) {
                $reason = "payload claims a non-existent agency '{$bogusClaim}'";
            } else {
                $reason = 'no owner evidence found (no stamped typed twin, stamped reference, or exact agency name)';
            }

            $records[] = [
                'collection' => $row->collection,
                'record_id' => $row->record_id,
                'status' => $status,
                'suggested_owner' => $resolvedOwner,
                'evidence' => array_values($candidates),
                'reason' => $reason,
                'bogus_claimed_owner' => $bogusClaim,
                'agency_id_column' => $row->agency_id,
            ];
        }

        $summary = [
            'confirmed' => 0,
            'ambiguous' => 0,
            'unresolved' => 0,
        ];
        foreach ($records as $record) {
            $summary[$record['status']] = ($summary[$record['status']] ?? 0) + 1;
        }

        $byCollection = [];
        foreach ($records as $record) {
            $byCollection[$record['collection']] = ($byCollection[$record['collection']] ?? 0) + 1;
        }
        ksort($byCollection);

        $report = [
            'generated_at' => now()->toIso8601String(),
            'mode' => 'read-only',
            'note' => 'Ownership is never assigned by this command. Unowned rows stay hidden from agency workspaces until an approved mapping is applied via platform:legacy-backfill --apply.',
            'unowned_total' => count($records),
            'summary' => $summary,
            'by_collection' => $byCollection,
            'records' => $records,
        ];

        if ($path = $this->option('write')) {
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->line("report written: {$path}");
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 0;
        }

        $this->line('Legacy ownership reconciliation (READ-ONLY — nothing was written)');
        $this->line('unowned rows: ' . count($records));
        $this->line('  evidence_confirmed : ' . $summary['confirmed']);
        $this->line('  ambiguous           : ' . $summary['ambiguous']);
        $this->line('  unresolved          : ' . $summary['unresolved']);
        $this->line('');
        $this->line('per collection:');
        foreach ($byCollection as $collection => $count) {
            $this->line(sprintf('  %-14s %d', $collection, $count));
        }
        $this->line('');
        $this->line('Confirmed rows still require an approved mapping file before any stamp is written.');
        $this->line('Command used: platform:legacy-backfill --apply --backup=<dump> --mapping=<mapping.json>');

        return 0;
    }
}
