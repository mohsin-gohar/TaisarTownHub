<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\DocumentQuarantine;
use App\Models\DocumentVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Erase quarantined material once its retention window has passed:
 *
 *   - rows in `document_quarantines` (rejected uploads — infected or scanned
 *     while the scanner was unavailable) whose bytes sit on the isolated
 *     `quarantine` disk, and
 *   - vault documents still held with status `quarantined` (never approved)
 *     older than the retention window, including their orphaned versions.
 *
 * Nothing live is touched: approved documents and trashed-but-retained
 * audit bytes are outside this command's scope. Schedule it daily.
 */
class PurgeQuarantine extends Command
{
    protected $signature = 'vault:purge-quarantine {--days= : Retention window override (days)}';

    protected $description = 'Delete rejected/quarantined vault uploads older than the retention window (default: vault.quarantine.retention_days)';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('vault.quarantine.retention_days', 30));
        $cutoff = now()->subDays(max(1, $days));

        $purgedRejected = 0;
        $failed = 0;
        DocumentQuarantine::where('created_at', '<', $cutoff)->get()->each(function (DocumentQuarantine $row) use (&$purgedRejected, &$failed) {
            $deleteFailed = false;
            try {
                if ($row->disk_path && Storage::disk('quarantine')->exists($row->disk_path)
                    && ! Storage::disk('quarantine')->delete($row->disk_path)) {
                    $deleteFailed = true;
                }
            } catch (\Throwable $e) {
                $deleteFailed = true;
                Log::warning('Quarantine purge byte delete failed', ['id' => $row->id, 'error' => $e->getMessage()]);
            }

            // Keep the quarantine record if the bytes could not be erased.
            // Otherwise a failed disk operation would orphan sensitive bytes
            // with no database row, audit trail, or retry candidate.
            if ($deleteFailed) {
                Log::warning('Quarantine purge skipped: physical delete did not complete', ['id' => $row->id]);
                $failed++;
                return;
            }

            $row->delete();
            $purgedRejected++;
        });

        $purgedHolds = 0;
        Document::withTrashed()
            ->where('status', 'quarantined')
            ->where('created_at', '<', $cutoff)
            ->get()
            ->each(function (Document $document) use (&$purgedHolds, &$failed) {
                $deleteFailed = false;
                try {
                    $disk = $document->storage_disk ?: 'documents';
                    if ($document->disk_path && Storage::disk($disk)->exists($document->disk_path)
                        && ! Storage::disk($disk)->delete($document->disk_path)) {
                        $deleteFailed = true;
                    }

                    if (! $deleteFailed) {
                        foreach (DocumentVersion::where('document_id', $document->id)->get() as $version) {
                            $vDisk = $version->storage_disk ?: 'documents';
                            if ($version->disk_path && Storage::disk($vDisk)->exists($version->disk_path)
                                && ! Storage::disk($vDisk)->delete($version->disk_path)) {
                                $deleteFailed = true;
                                break;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    $deleteFailed = true;
                    Log::warning('Quarantine hold purge failed', ['id' => $document->id, 'error' => $e->getMessage()]);
                }

                // Physical deletion must succeed for the document AND every
                // version before metadata is removed. Missing bytes are safe
                // on retries; failed deletes keep the row for the next run.
                if ($deleteFailed) {
                    $failed++;
                    return;
                }

                \App\Models\AuditLog::create([
                    'actor' => 'system',
                    'role' => 'system',
                    'action' => 'document.quarantine_purged',
                    'target' => $document->id,
                    'detail' => "Quarantined document purged after retention window: {$document->title}",
                    'agency_id' => $document->agency_id,
                    'entity_type' => 'document',
                    'entity_id' => $document->id,
                ]);

                $document->forceDelete();
                $purgedHolds++;
            });

        $this->info("Quarantine purge: {$purgedRejected} rejected upload(s), {$purgedHolds} expired hold(s) removed, {$failed} cleanup(s) failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
