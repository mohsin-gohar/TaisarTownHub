<?php

namespace App\Console\Commands;

use App\Models\BusinessRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Per-record ownership reconciliation report for the legacy `business_records`
 * JSON store.
 *
 * This command is STRICTLY READ-ONLY. It never writes, never stamps and never
 * deletes a row. It produces, for every legacy row that has no owner
 * (agency_id IS NULL), a reconciliation record that states:
 *
 *   - which collection / record_id it is,
 *   - whether VERIFIED evidence links it to exactly one agency
 *     (a typed twin or a dealer/agent reference that is itself agency-stamped),
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

    /** Payload keys that reference a dealer/agent (whose typed row carries the owner). */
    private const DEALER_REF_KEYS = [
        'dealerId', 'dealer_id', 'assignedDealerId', 'soldByDealerId', 'agentId', 'agent_id',
    ];

    /** collection => [typed table, id column] used as the evidence twin. */
    private const TYPED_TWINS = [
        'properties' => ['properties', 'id'],
        'dealers' => ['dealers', 'id'],
        'inquiries' => ['inquiries', 'id'],
        'transactions' => ['transactions', 'id'],
        'projects' => ['projects', 'id'],
        'videos' => ['agency_videos', 'id'],
        'media' => ['media_files', 'id'],
    ];

    public function handle(): int
    {
        $rows = BusinessRecord::query()
            ->whereNull('agency_id')
            ->when($this->option('collection'), fn ($q) => $q->where('collection', $this->option('collection')))
            ->orderBy('collection')
            ->orderBy('record_id')
            ->get();

        $agencyIds = DB::table('agencies')->pluck('id')->all();
        $dealerAgencies = DB::table('dealers')->whereNotNull('agency_id')->pluck('agency_id', 'id')->all();
        $propertyAgencies = DB::table('properties')->whereNotNull('agency_id')->pluck('agency_id', 'id')->all();
        $inquiryAgencies = DB::table('inquiries')->whereNotNull('agency_id')->pluck('agency_id', 'id')->all();
        $transactionAgencies = DB::table('transactions')->whereNotNull('agency_id')->pluck('agency_id', 'id')->all();

        $records = [];

        foreach ($rows as $row) {
            $payload = is_array($row->payload) ? $row->payload : [];
            $evidence = [];
            $candidates = [];

            // 1) A typed twin that is already agency-stamped.
            $twin = self::TYPED_TWINS[$row->collection] ?? null;
            if ($twin) {
                [$table, $column] = $twin;
                $typedAgency = null;
                if ($table === 'properties') {
                    $typedAgency = $propertyAgencies[$row->record_id] ?? null;
                } elseif ($table === 'inquiries') {
                    $typedAgency = $inquiryAgencies[$row->record_id] ?? null;
                } elseif ($table === 'transactions') {
                    $typedAgency = $transactionAgencies[$row->record_id] ?? null;
                } elseif ($table === 'dealers') {
                    $typedAgency = $dealerAgencies[$row->record_id] ?? null;
                } else {
                    $typedAgency = DB::table($table)->where($column, $row->record_id)->value('agency_id');
                }
                if ($typedAgency) {
                    $candidates[] = $typedAgency;
                    $evidence[] = "typed twin {$table}.{$row->record_id}.agency_id = {$typedAgency}";
                }
            }

            // 2) A dealer/agent reference whose typed dealer row carries the owner.
            foreach (self::DEALER_REF_KEYS as $key) {
                $ref = $payload[$key] ?? null;
                if ($ref && isset($dealerAgencies[$ref])) {
                    $candidates[] = $dealerAgencies[$ref];
                    $evidence[] = "payload.{$key} = {$ref} -> dealers.{$ref}.agency_id = {$dealerAgencies[$ref]}";
                }
            }

            // 3) Payments / commissions inherit the owner of their parent deal.
            if (in_array($row->collection, ['payments', 'commissions'], true)) {
                $parentId = $payload['transactionId'] ?? $payload['transaction_id'] ?? null;
                if ($parentId && isset($transactionAgencies[$parentId])) {
                    $candidates[] = $transactionAgencies[$parentId];
                    $evidence[] = "parent transaction {$parentId}.agency_id = {$transactionAgencies[$parentId]}";
                }
            }

            // 4) A claimed owner inside the payload that does not exist as an agency.
            $claimed = $payload['agencyId'] ?? $payload['agency_id'] ?? null;
            $bogusClaim = null;
            if ($claimed && !in_array($claimed, $agencyIds, true)) {
                $bogusClaim = $claimed;
            }

            $candidates = array_values(array_unique(array_filter($candidates)));
            $status = 'unresolved';
            $resolvedOwner = null;
            $reason = null;

            if (count($candidates) === 1) {
                $status = 'evidence_confirmed';
                $resolvedOwner = $candidates[0];
            } elseif (count($candidates) > 1) {
                $status = 'ambiguous';
                $reason = 'evidence points at multiple agencies: ' . implode(', ', $candidates);
            } elseif ($bogusClaim !== null) {
                $reason = "payload claims a non-existent agency '{$bogusClaim}'";
            } else {
                $reason = 'no owner evidence found (no stamped typed twin, no stamped dealer reference)';
            }

            $records[] = [
                'collection' => $row->collection,
                'record_id' => $row->record_id,
                'status' => $status,
                'suggested_owner' => $resolvedOwner,
                'evidence' => $evidence,
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
