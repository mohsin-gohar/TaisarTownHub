<?php

namespace App\Console\Commands;

use App\Models\BusinessRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only reconciliation of the legacy `business_records` JSON store against
 * the typed enterprise tables (Phase 3 — multi-tenant data migration audit).
 *
 * It never writes: it reports, per collection, how many rows exist, who owns
 * them (agency), whether the same record also exists in the typed table, and
 * which rows are migration candidates. The destructive half of the migration
 * (copying/archiving) is a separate, approval-gated operation.
 */
class BusinessRecordsAuditCommand extends Command
{
    protected $signature = 'platform:legacy-audit {--json}';

    protected $description = 'Audit the legacy business_records store against the typed tables';

    /** collection => [typed table, id column] */
    private const TYPED_COUNTERPARTS = [
        'properties' => ['properties', 'id'],
        'dealers' => ['dealers', 'id'],
        'inquiries' => ['inquiries', 'id'],
        'transactions' => ['transactions', 'id'],
        'projects' => ['projects', 'id'],
        'videos' => ['agency_videos', 'id'],
        'media' => ['media_files', 'id'],
        'payments' => ['transaction_payments', 'id'],
        'commissions' => ['commissions', 'id'],
    ];

    public function handle(): int
    {
        $rows = BusinessRecord::orderBy('collection')->orderBy('record_id')->get();
        $report = [
            'generated_at' => now()->toIso8601String(),
            'total_rows' => $rows->count(),
            'collections' => [],
            'typed_counterparts' => self::TYPED_COUNTERPARTS,
        ];

        // Owner resolution map: legacy rows point at dealers / properties /
        // inquiries whose *typed* twin already carries an agency stamp.
        $dealerAgencies = DB::table('dealers')->whereNotNull('agency_id')->pluck('agency_id', 'id')->all();
        $propertyAgencies = DB::table('properties')->whereNotNull('agency_id')->pluck('agency_id', 'id')->all();
        $inquiryAgencies = DB::table('inquiries')->whereNotNull('agency_id')->pluck('agency_id', 'id')->all();

        foreach ($rows->groupBy('collection') as $collection => $collectionRows) {
            $withAgency = 0;
            $withoutAgency = 0;
            $bogusAgency = 0;
            $softDeleted = 0;
            $alsoInTyped = 0;
            $derivable = 0;
            $derivableMap = [];
            $migrationCandidates = [];
            $typedTable = self::TYPED_COUNTERPARTS[$collection][0] ?? null;
            $typedColumn = self::TYPED_COUNTERPARTS[$collection][1] ?? null;
            $bogusExamples = [];

            foreach ($collectionRows as $row) {
                $payload = is_array($row->payload) ? $row->payload : [];
                $agencyId = $payload['agencyId'] ?? $payload['agency_id'] ?? null;

                if ($agencyId) {
                    $withAgency++;
                    if (!DB::table('agencies')->where('id', $agencyId)->exists()) {
                        $bogusAgency++;
                        if (count($bogusExamples) < 10) {
                            $bogusExamples[] = ['record_id' => $row->record_id, 'agency_id' => $agencyId];
                        }
                    }
                } else {
                    $withoutAgency++;
                }

                // Derive the owner from an already-stamped sibling record.
                $derived = null;
                foreach (['dealerId', 'dealer_id', 'assignedDealerId', 'soldByDealerId', 'agentId', 'agent_id'] as $key) {
                    $ref = $payload[$key] ?? null;
                    if ($ref && isset($dealerAgencies[$ref])) {
                        $derived = $dealerAgencies[$ref];
                        break;
                    }
                }
                if (!$derived && isset($propertyAgencies[$row->record_id])) {
                    $derived = $propertyAgencies[$row->record_id];
                }
                if (!$derived && isset($inquiryAgencies[$row->record_id])) {
                    $derived = $inquiryAgencies[$row->record_id];
                }
                if (!$derived && isset($payload['agencyName'])) {
                    $agency = DB::table('agencies')->where('name', $payload['agencyName'])->first();
                    $derived = $agency->id ?? null;
                }
                if ($derived) {
                    $derivable++;
                    $derivableMap[$row->record_id] = $derived;
                }

                if ($row->deleted_at !== null) {
                    $softDeleted++;
                }

                if ($typedTable) {
                    $exists = DB::table($typedTable)->where($typedColumn, $row->record_id)->exists();
                    if ($exists) {
                        $alsoInTyped++;
                    } else {
                        $migrationCandidates[] = [
                            'record_id' => $row->record_id,
                            'agency_id' => $agencyId,
                            'derivable_agency_id' => $derived,
                            'deleted' => $row->deleted_at !== null,
                            'updated_at' => (string) $row->updated_at,
                        ];
                    }
                }
            }

            $report['collections'][$collection] = [
                'rows' => $collectionRows->count(),
                'with_agency_id' => $withAgency,
                'with_valid_agency_id' => $withAgency - $bogusAgency,
                'with_bogus_agency_id' => $bogusAgency,
                'bogus_examples' => $bogusExamples,
                'without_agency_id' => $withoutAgency,
                'derivable_agency_id' => $derivable,
                'derivable_map' => $derivableMap,
                'soft_deleted' => $softDeleted,
                'typed_table' => $typedTable,
                'also_present_in_typed_table' => $alsoInTyped,
                'migration_candidates' => count($migrationCandidates),
                'candidates' => array_slice($migrationCandidates, 0, 50),
            ];
        }

        // Global tenancy picture
        $report['tenancy'] = [
            'rows_with_agency' => $rows->filter(fn ($r) => !empty($r->payload['agencyId'] ?? $r->payload['agency_id'] ?? null))->count(),
            'rows_without_agency' => $rows->filter(fn ($r) => empty($r->payload['agencyId'] ?? $r->payload['agency_id'] ?? null))->count(),
            'distinct_agencies' => $rows->map(fn ($r) => $r->payload['agencyId'] ?? $r->payload['agency_id'] ?? null)->filter()->unique()->values()->all(),
        ];

        // Typed table reference counts for the same identifiers
        $report['typed_table_counts'] = [
            'properties' => DB::table('properties')->count(),
            'dealers' => DB::table('dealers')->count(),
            'inquiries' => DB::table('inquiries')->count(),
            'transactions' => DB::table('transactions')->count(),
            'projects' => DB::table('projects')->count(),
            'agency_videos' => DB::table('agency_videos')->count(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));

            return 0;
        }

        $this->line("business_records: {$report['total_rows']} rows");
        $this->line(sprintf('%-16s %5s %9s %7s %7s %7s %9s %7s %8s', 'collection', 'rows', 'w/agency', 'valid', 'bogus', 'none', 'derivable', 'deleted', 'in typed'));
        foreach ($report['collections'] as $name => $data) {
            $this->line(sprintf(
                '%-16s %5d %9d %7d %7d %7d %9d %7d %8d',
                $name,
                $data['rows'],
                $data['with_agency_id'],
                $data['with_valid_agency_id'],
                $data['with_bogus_agency_id'],
                $data['without_agency_id'],
                $data['derivable_agency_id'],
                $data['soft_deleted'],
                $data['also_present_in_typed_table'],
            ));
        }
        $this->line('');
        $this->line('tenancy: ' . json_encode($report['tenancy']));
        $this->line('typed counts: ' . json_encode($report['typed_table_counts']));

        return 0;
    }
}
