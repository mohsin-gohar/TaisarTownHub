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
        $retainedRejected = 0;
        DocumentQuarantine::where('created_at', '<', $cutoff)->get()->each(function (DocumentQuarantine $row) use (&$purgedRejected, &$retainedRejected) {
            try {
                $disk = Storage::disk('quarantine');
                if ($row->disk_path && $disk->exists($row->disk_path)
                    && $disk->delete($row->disk_path) === false) {
                    $retainedRejected++;
                    Log::warning('Quarantine purge retained metadata because byte deletion returned false', [
                        'id' => $row->id,
                        'disk_path' => $row->disk_path,
                    ]);

                    return;
                }
            } catch (\Throwable $e) {
                $retainedRejected++;
                Log::warning('Quarantine purge byte delete failed; metadata retained for retry', [
                    'id' => $row->id,
                    'error' => $e->getMessage(),
                ]);

                return;
            }

            $row->delete();
            $purgedRejected++;
        });

        $purgedHolds = 0;
        $retainedHolds = 0;
        Document::withTrashed()
            ->where('status', 'quarantined')
            ->where('created_at', '<', $cutoff)
            ->get()
            ->each(function (Document $document) use (&$purgedHolds, &$retainedHolds) {
                try {
                    $disk = Storage::disk($document->storage_disk ?: 'documents');
                    if ($document->disk_path && $disk->exists($document->disk_path)
                        && $disk->delete($document->disk_path) === false) {
                        $retainedHolds++;
                        Log::warning('Quarantine hold retained because document byte deletion returned false', [
                            'id' => $document->id,
                            'disk_path' => $document->disk_path,
                        ]);

                        return;
                    }

                    foreach (DocumentVersion::where('document_id', $document->id)->get() as $version) {
                        $versionDisk = Storage::disk($version->storage_disk ?: 'documents');
                        if ($version->disk_path && $versionDisk->exists($version->disk_path)
                            && $versionDisk->delete($version->disk_path) === false) {
                            $retainedHolds++;
                            Log::warning('Quarantine hold retained because version byte deletion returned false', [
                                'id' => $document->id,
                                'version_id' => $version->id,
                                'disk_path' => $version->disk_path,
                            ]);

                            return;
                        }
                    }
                } catch (\Throwable $e) {
                    $retainedHolds++;
                    Log::warning('Quarantine hold purge failed; metadata retained for retry', [
                        'id' => $document->id,
                        'error' => $e->getMessage(),
                    ]);

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

        $this->info("Quarantine purge: {$purgedRejected} rejected upload(s), {$purgedHolds} expired hold(s) removed; {$retainedRejected} rejected upload(s) and {$retainedHolds} document hold(s) retained for retry.");

        return ($retainedRejected + $retainedHolds) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
