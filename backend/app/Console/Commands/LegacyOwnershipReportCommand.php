<?php

namespace App\Console\Commands;

use App\Models\BusinessRecord;
use App\Support\LegacyTenancyResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
 * hidden from agency workspaces (see WorkspaceController::allRows()) until an
 * explicitly approved backfill apply stamps unique evidence or a reviewed mapping.
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
            ->where(function ($query) {
                $query->whereNull('agency_id')
                    ->orWhereNotExists(function ($subquery) {
                        $subquery->selectRaw('1')
                            ->from('agencies')
                            ->whereColumn('agencies.id', 'business_records.agency_id');
                    });
            })
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

            if ($bogusClaim !== null) {
                $reason = $row->agency_id !== null
                    ? "canonical agency_id '{$bogusClaim}' does not reference an existing agency"
                    : "payload claims a non-existent agency '{$bogusClaim}'";
            } elseif (count($candidates) === 1) {
                $status = 'confirmed';
                $resolvedOwner = array_key_first($candidates);
            } elseif (count($candidates) > 1) {
                $status = 'ambiguous';
                $reason = 'evidence points at multiple agencies: ' . implode(', ', array_keys($candidates));
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

        // Typed relational tables also retain legacy/unowned rows. Report
        // counts without emitting customer payloads or attempting any repair.
        $typedTenantTables = [
            'dealers', 'properties', 'inquiries', 'transactions',
            'transaction_payments', 'payment_schedules', 'commissions',
            'expenses', 'documents', 'document_folders', 'media_files',
            'support_tickets', 'business_records',
        ];
        $typedOwnership = [];
        foreach ($typedTenantTables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'agency_id')) {
                continue;
            }

            $typedOwnership[$table] = [
                'rows' => DB::table($table)->count(),
                'unowned' => DB::table($table)->whereNull('agency_id')->count(),
                'invalid_agency_reference' => DB::table($table)
                    ->leftJoin('agencies as owner_agencies', "{$table}.agency_id", '=', 'owner_agencies.id')
                    ->whereNotNull("{$table}.agency_id")
                    ->whereNull('owner_agencies.id')
                    ->count(),
            ];
        }

        $financeParentMismatches = [];
        foreach (['transaction_payments', 'payment_schedules', 'commissions'] as $table) {
            if (!Schema::hasTable($table)
                || !Schema::hasColumn($table, 'agency_id')
                || !Schema::hasColumn($table, 'transaction_id')
                || !Schema::hasTable('transactions')) {
                continue;
            }

            $financeParentMismatches[$table] = DB::table("{$table} as child")
                ->join('transactions as parent_transaction', 'child.transaction_id', '=', 'parent_transaction.id')
                ->where(function ($query) {
                    $query->whereColumn('child.agency_id', '!=', 'parent_transaction.agency_id')
                        ->orWhere(fn ($q) => $q->whereNull('child.agency_id')->whereNotNull('parent_transaction.agency_id'))
                        ->orWhere(fn ($q) => $q->whereNotNull('child.agency_id')->whereNull('parent_transaction.agency_id'));
                })
                ->count();
        }

        $report = [
            'generated_at' => now()->toIso8601String(),
            'typed_table_ownership' => $typedOwnership,
            'finance_parent_tenant_mismatches' => $financeParentMismatches,
            'mode' => 'read-only',
            'note' => 'Ownership is never assigned by this command. Unowned rows stay hidden from agency workspaces until an explicit, backup-gated backfill applies unique evidence or a reviewed mapping.',
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
        $this->line('Unique evidence appears in the dry-run plan; every write still requires explicit --apply and a verified backup. Ambiguous/bogus claims require a reviewed mapping.');
        $this->line('Command used: platform:legacy-backfill --apply --backup=<dump> --mapping=<mapping.json>');
        $this->line('typed ownership (rows / unowned / invalid agency refs):');
        foreach ($typedOwnership as $table => $counts) {
            $this->line(sprintf(
                '  %-24s %8d / %8d / %8d',
                $table,
                $counts['rows'],
                $counts['unowned'],
                $counts['invalid_agency_reference']
            ));
        }
        $this->line('finance parent tenant mismatches: ' . json_encode($financeParentMismatches));

        return 0;
    }
}
