<?php

namespace App\Console\Commands;

use App\Models\BusinessRecord;
use App\Support\LegacyBackfillBackup;
use App\Support\LegacyTenancyResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * SAFE (non-destructive, reversible) tenant backfill for the legacy
 * `business_records` store.
 *
 * The command is **dry-run by default**: it prints the exact rows it would
 * stamp and writes nothing. Applying it requires `--apply` **and** a dump that
 * passes LegacyBackfillBackup's integrity checks. It is strictly additive: it
 * only writes the canonical `agency_id` COLUMN on rows that have no owner and
 * never overwrites an existing claim. No row is deleted, and the row count is
 * verified before and after. Mapping keys are composite
 * (`collection + record_id`) so identical IDs in different collections can
 * never cross-assign an owner. Every apply writes a checksummed rollback
 * journal; `--rollback=<journal>` restores the exact pre-apply values.
 */
class LegacyBackfillCommand extends Command
{
    /** Unit separator: cannot appear in a collection name or a record id. */
    private const KEY_SEPARATOR = "\x1f";

    protected $signature = 'platform:legacy-backfill
        {--apply : actually write the agency stamps (default: dry run)}
        {--backup= : path to an integrity-verified database dump (required with --apply)}
        {--mapping= : JSON file with composite { collection: { record_id: agency_id } } pairs}
        {--collection= : limit the run to one collection}
        {--journal= : rollback journal path (default: storage/app/legacy-backfill-rollback-<stamp>.json)}
        {--rollback= : restore the exact pre-apply values from a rollback journal}';

    protected $description = 'Plan (and optionally apply/rollback) the tenant backfill of the legacy business_records store';

    public function handle(): int
    {
        if ($rollbackFile = $this->option('rollback')) {
            return $this->rollback($rollbackFile);
        }

        $apply = (bool) $this->option('apply');
        $resolver = new LegacyTenancyResolver();
        $mapping = [];

        if ($mappingFile = $this->option('mapping')) {
            if (!is_readable($mappingFile)) {
                $this->error("Mapping file not readable: {$mappingFile}");

                return 1;
            }
            $mapping = json_decode((string) file_get_contents($mappingFile), true) ?: [];
            if (!is_array($mapping)) {
                $this->error('Mapping file must contain a JSON object.');

                return 1;
            }
        }

        [$composite, $legacy] = $this->parseMapping($mapping);

        $rows = BusinessRecord::query()
            ->when($this->option('collection'), fn ($q) => $q->where('collection', $this->option('collection')))
            ->orderBy('collection')
            ->orderBy('record_id')
            ->get();

        $beforeCount = $rows->count();

        // A deprecated flat key cannot distinguish two collections that reuse
        // the same record id; precompute that ambiguity before planning.
        $collectionsPerRecord = [];
        foreach ($rows as $row) {
            $collectionsPerRecord[$row->record_id][$row->collection] = true;
        }

        $plan = [];

        foreach ($rows as $row) {
            $current = $resolver->claimedOwner($row);

            // A non-null but invalid canonical agency_id is an orphaned
            // reference. Never treat it as NULL or overwrite it from weaker
            // payload evidence; reconcile it explicitly after investigation.
            if ($row->agency_id !== null && $row->agency_id !== '' && $current === null) {
                $this->warn("row {$row->collection}::{$row->record_id} has orphaned agency_id '{$row->agency_id}' — skipped for manual reconciliation");

                continue;
            }

            $key = $this->compositeKey($row->collection, (string) $row->record_id);
            $mapped = $composite[$key] ?? null;
            $source = 'mapping file';

            if ($mapped === null && isset($legacy[$row->record_id])) {
                if (count($collectionsPerRecord[$row->record_id] ?? []) > 1) {
                    $this->warn("row {$row->record_id} exists in multiple collections — flat mapping is ambiguous; use collection::record_id keys.");
                } else {
                    $mapped = $legacy[$row->record_id];
                    $source = 'legacy flat mapping (deprecated)';
                }
            }

            if ($mapped !== null && !$resolver->isAgency((string) $mapped)) {
                $this->warn("mapping for {$row->collection}::{$row->record_id} names unknown agency '{$mapped}' — ignored.");
                $mapped = null;
            }

            $target = $mapped !== null ? (string) $mapped : null;

            // Never auto-stamp a row with conflicting evidence: ambiguity is a
            // manual-review item, not a coin flip.
            if (!$target && $current === null) {
                $bogusClaim = $resolver->bogusClaim($row);
                if ($bogusClaim !== null) {
                    $this->warn("row {$row->collection}::{$row->record_id} has an invalid ownership claim '{$bogusClaim}' — derived evidence requires manual review");
                } else {
                    $candidates = $resolver->candidates($row);
                    if (count($candidates) === 1) {
                        $target = array_key_first($candidates);
                        $source = 'derived evidence';
                    }
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
                'source' => $source,
            ];
        }

        $this->line('DRY RUN — nothing was written.');
        $this->line("rows scanned: {$beforeCount}");
        $this->line('rows that would be stamped: ' . count($plan));
        foreach ($plan as $entry) {
            $this->line(sprintf('  %-14s %-34s -> %s (%s)', $entry['collection'], $entry['record_id'], $entry['agency_id'], $entry['source']));
        }

        if (!$apply) {
            $this->line('');
            $this->line('Re-run with --apply --backup=<verified dump> to write these stamps.');

            return 0;
        }

        // ---- apply path (approval + verified backup gate) ----
        $backup = $this->option('backup');
        if (!$backup) {
            $this->error('--apply requires --backup=<integrity-verified database dump>.');

            return 1;
        }

        try {
            $verified = LegacyBackfillBackup::verify($backup);
        } catch (RuntimeException $e) {
            $this->error('Backup verification failed: ' . $e->getMessage());

            return 1;
        }
        $this->line('Backup verified: ' . $verified->summary());

        if ($plan === []) {
            $this->line('No rows needed stamping — nothing was written.');

            return 0;
        }

        // Capture the exact pre-state and persist an integrity-checked journal
        // BEFORE mutating anything, so every apply is reversible.
        $rollbackRows = [];
        foreach ($plan as $entry) {
            $row = BusinessRecord::where('collection', $entry['collection'])
                ->where('record_id', $entry['record_id'])
                ->first();
            if (!$row) {
                continue;
            }
            $rollbackRows[] = [
                'collection' => $entry['collection'],
                'record_id' => $entry['record_id'],
                'previous_agency_id' => $row->agency_id === null ? null : (string) $row->agency_id,
                'stamped_agency_id' => (string) $entry['agency_id'],
            ];
        }

        $journalPath = $this->option('journal') ?: storage_path(
            'app/legacy-backfill-rollback-' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.json'
        );
        $journal = [
            'schema' => 1,
            'created_at' => now()->toIso8601String(),
            'command' => 'platform:legacy-backfill --apply',
            'database' => (string) config('database.default'),
            'backup' => [
                'path' => $backup,
                'sha256' => $verified->sha256(),
                'format' => $verified->format(),
                'tables' => $verified->tableCount(),
            ],
            'rows' => $rollbackRows,
        ];

        try {
            $json = json_encode($journal, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->error('Could not encode rollback journal: ' . $e->getMessage());

            return 1;
        }

        $written = @file_put_contents($journalPath, $json, LOCK_EX);
        if ($written === false || $written !== strlen($json) || @file_get_contents($journalPath) !== $json) {
            $this->error("Rollback journal could not be written/verified: {$journalPath} — refusing to apply.");

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
            $this->error('row count changed — investigate immediately. Roll back with: php artisan platform:legacy-backfill --rollback=' . $journalPath);

            return 1;
        }

        $this->line('Rollback journal: ' . $journalPath . ' (sha256 ' . hash('sha256', $json) . ')');
        $this->line('Restore with: php artisan platform:legacy-backfill --rollback=' . $journalPath);

        return 0;
    }

    /**
     * Restore the exact pre-apply agency_id values recorded in a journal.
     * Idempotent: re-running is a no-op once rows are already at pre-state.
     */
    private function rollback(string $path): int
    {
        if (!is_file($path) || !is_readable($path)) {
            $this->error("Rollback journal not readable: {$path}");

            return 1;
        }

        $journal = json_decode((string) @file_get_contents($path), true);
        if (!is_array($journal)
            || ($journal['schema'] ?? null) !== 1
            || !isset($journal['rows'])
            || !is_array($journal['rows'])) {
            $this->error('Rollback journal is not a valid v1 journal.');

            return 1;
        }

        foreach ($journal['rows'] as $entry) {
            if (!is_array($entry)
                || !isset($entry['collection'], $entry['record_id'])
                || !array_key_exists('previous_agency_id', $entry)
                || !array_key_exists('stamped_agency_id', $entry)
                || !is_string($entry['collection'])
                || !is_string($entry['record_id'])
                || !$this->isNullableId($entry['previous_agency_id'])
                || !$this->isNullableId($entry['stamped_agency_id'])) {
                $this->error('Rollback journal contains a malformed row entry.');

                return 1;
            }
        }

        // If the original dump is still present, re-verify its checksum so a
        // journal cannot be replayed against a different database snapshot.
        $backupPath = $journal['backup']['path'] ?? null;
        if (is_string($backupPath) && is_file($backupPath)) {
            try {
                $verified = LegacyBackfillBackup::verify($backupPath);
                if (!hash_equals((string) ($journal['backup']['sha256'] ?? ''), $verified->sha256())) {
                    $this->error('Backup checksum changed since apply — refusing rollback.');

                    return 1;
                }
            } catch (RuntimeException $e) {
                $this->error('Backup re-verification failed: ' . $e->getMessage());

                return 1;
            }
        } else {
            $this->warn('Original backup is unavailable; proceeding with structurally validated journal only.');
        }

        $before = BusinessRecord::count();
        $restored = 0;
        $skipped = 0;

        DB::transaction(function () use ($journal, &$restored, &$skipped) {
            foreach ($journal['rows'] as $entry) {
                $row = BusinessRecord::where('collection', $entry['collection'])
                    ->where('record_id', $entry['record_id'])
                    ->first();
                if (!$row) {
                    $skipped++;

                    continue;
                }

                $normalize = fn ($value) => $value === null ? null : (string) $value;
                $current = $normalize($row->agency_id);
                $previous = $normalize($entry['previous_agency_id']);
                $stamped = $normalize($entry['stamped_agency_id']);

                if ($current === $previous) {
                    $restored++; // already at pre-state (idempotent replay)

                    continue;
                }
                if ($stamped !== null && $current !== $stamped) {
                    $this->warn("{$entry['collection']}::{$entry['record_id']} owner changed since apply — skipped to avoid clobbering.");
                    $skipped++;

                    continue;
                }

                $row->agency_id = $previous;
                $row->save();
                $restored++;
            }
        });

        $after = BusinessRecord::count();
        if ($before !== $after) {
            $this->error('row count changed during rollback — investigate immediately.');

            return 1;
        }

        $this->line("rollback: {$restored} row(s) at pre-apply state, {$skipped} skipped; row count {$before} unchanged.");

        return 0;
    }

    private function compositeKey(string $collection, string $recordId): string
    {
        return $collection . self::KEY_SEPARATOR . $recordId;
    }

    private function isNullableId(mixed $value): bool
    {
        return $value === null || is_string($value) || is_int($value);
    }

    /**
     * Normalize a mapping file into a composite (collection, record_id) lookup
     * plus a deprecated flat lookup. Supported shapes (first match wins):
     *
     *   { "properties": { "prop-1": "agency-a" } }   nested by collection
     *   { "properties::prop-1": "agency-a" }         composite flat key (also "/" or "|")
     *   { "prop-1": "agency-a" }                     legacy flat key (deprecated)
     *
     * @return array{0: array<string,string>, 1: array<string,string>}
     */
    private function parseMapping(array $raw): array
    {
        $composite = [];
        $legacy = [];

        foreach ($raw as $key => $value) {
            $key = (string) $key;

            if (is_array($value)) {
                foreach ($value as $recordId => $agencyId) {
                    if (is_scalar($agencyId)) {
                        $composite[$this->compositeKey($key, (string) $recordId)] = (string) $agencyId;
                    }
                }

                continue;
            }
            if (!is_scalar($value)) {
                continue;
            }
            $agency = (string) $value;

            foreach (['::', '/', '|'] as $separator) {
                if (str_contains($key, $separator)) {
                    [$collection, $recordId] = explode($separator, $key, 2);
                    if ($collection !== '' && $recordId !== '') {
                        $composite[$this->compositeKey($collection, $recordId)] = $agency;

                        continue 2;
                    }
                }
            }

            $legacy[$key] = $agency;
        }

        return [$composite, $legacy];
    }
}
