<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\MediaFile;
use App\Models\Property;
use App\Models\SystemSetting;
use App\Services\MalwareScanner;
use App\Services\SecureFileStore;
use App\Support\PlanLimits;
use App\Support\UploadSecurity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Agency media studio.
 *
 * Uploaded bytes live on the private `documents` disk; delivery happens only
 * through controller endpoints that check the `visibility` flag:
 *  - public media  â†’ GET /api/media/{id}/file (no auth, public media only)
 *  - private media â†’ GET /api/agency/media/{id}/file (auth + agency scope)
 */
class MediaController extends Controller
{
    use AuthorizesApi;

    private const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    private const VIDEO_EXT = ['mp4', 'mov', 'webm', 'mkv'];

    public function index(Request $request)
    {
        $agencyId = $this->requireAgency($request);
        $this->requireAny($request, ['manage_media', 'upload_media', 'properties.view', 'view_properties']);

        $query = MediaFile::where('agency_id', $agencyId);

        if ($request->filled('folder')) {
            $query->where('folder', $request->folder);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('visibility')) {
            $query->where('visibility', $request->visibility);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }
        if ($request->filled('search')) {
            $s = addcslashes((string) $request->search, '%_\\');
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                    ->orWhere('folder', 'like', "%{$s}%");
            });
        }

        $page = $query->orderByDesc('created_at')
            ->paginate(max(1, min((int) $request->get('per_page', 24), 100)));

        $page->through(function (MediaFile $m) use ($request) {
            $payload = $m->toArray();
            $payload['file_url'] = $m->visibility === 'public'
                ? '/api/media/' . $m->id . '/file'
                : '/api/agency/media/' . $m->id . '/file';
            $payload['can_edit'] = $this->can($request, 'manage_media')
                || $this->can($request, 'upload_media');

            // A property viewer can browse media metadata, but must not see
            // buyer PII or sale/accounting values from the media inventory.
            $canViewSensitive = $this->can($request, 'documents.view_sensitive')
                || $this->can($request, 'manage_media')
                || $this->can($request, 'admin.view_reports');
            $canViewFinance = $this->can($request, 'finance.view')
                || $this->can($request, 'manage_media')
                || $this->can($request, 'admin.view_reports');

            if (!$canViewSensitive) {
                unset($payload['buyer_name'], $payload['buyer_phone'], $payload['buyer_national_id_masked']);
            }
            if (!$canViewFinance) {
                unset($payload['sold_price_pk'], $payload['cost_price_pk'], $payload['profit_pk'], $payload['sold_by']);
            }

            return $payload;
        });

        return response()->json([
            'success' => true,
            'media' => $page->items(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/agency/media — upload a file or register an external URL.
     * Multipart `file` OR json `url`.
     *
     * Uploaded bytes run the SAME content-security pipeline as the vault
     * (extension allowlist, sniffed MIME, magic bytes, malware scan) and are
     * written encrypted-at-rest through the SecureFileStore. External URLs
     * must be HTTPS public hosts — `deliver()` refuses to redirect to
     * anything else.
     */
    public function store(Request $request, MalwareScanner $scanner)
    {
        $agencyId = $this->requireAgency($request);
        $this->requireAny($request, ['manage_media', 'upload_media']);

        $maxMb = (int) SystemSetting::get('storage.max_upload_mb', 25);

        $request->validate([
            'file' => 'nullable|file|max:' . ($maxMb * 1024),
            'url' => [
                'nullable', 'string', 'max:2048',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (is_string($value) && $value !== '' && !UploadSecurity::isAllowedExternalUrl($value)) {
                        $fail('Only HTTPS links to public hosts are allowed.');
                    }
                },
            ],
            'title' => 'required|string|max:255',
            'folder' => 'nullable|string|max:60',
            'type' => 'nullable|in:image,video,document,banner,brochure,floor_plan',
            'visibility' => 'nullable|in:public,private',
            'property_id' => 'nullable|string|max:256',
            'description' => 'nullable|string|max:2000',
            'tags' => 'nullable|array|max:20',
            'tags.*' => 'string|max:40',
        ]);

        if ($request->hasFile('file') === $request->filled('url')) {
            return response()->json([
                'success' => false,
                'message' => 'Provide exactly one source: either a file upload or an external URL.',
            ], 422);
        }

        if (!$request->hasFile('file') && !$request->filled('url')) {
            return response()->json([
                'success' => false,
                'message' => 'Provide a file to upload or an external URL.',
            ], 422);
        }

        if ($request->filled('property_id')) {
            Property::where('agency_id', $agencyId)->where('id', $request->property_id)->firstOrFail();
        }

        $mediaId = 'med_' . bin2hex(random_bytes(8));
        $visibility = $request->get('visibility', 'private');
        $type = $request->get('type');
        $storedPath = null;
        $originalName = null;
        $mimeType = null;
        $size = 0;
        $dimensions = null;
        $scanStatus = null;
        $scannedAt = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $ext = strtolower($file->getClientOriginalExtension());

            $allExt = array_merge(self::IMAGE_EXT, self::VIDEO_EXT, ['pdf', 'doc', 'docx', 'xls', 'xlsx']);
            if (!in_array($ext, $allExt, true)) {
                return response()->json([
                    'success' => false,
                    'message' => "File type .{$ext} is not allowed.",
                ], 422);
            }

            // Content security: sniffed MIME blocklist + magic bytes — the
            // same gates the document vault applies. The browser-declared
            // type is never trusted.
            $check = UploadSecurity::evaluate($file, $allExt);
            if ($check['error'] !== null) {
                return $check['error'];
            }

            $scan = $scanner->scan($file->getRealPath());
            $scannerUnavailable = $scan->skipped || in_array('scan_unavailable', $scan->threats, true);

            if ($scannerUnavailable && config('malware.fail_closed', false)) {
                $this->quarantineRejected($request, $agencyId, $file, $check['ext'], 'scan_unavailable', $scan->threats);
                Log::warning('Media upload rejected: malware scanner unavailable (fail-closed)', [
                    'file' => $file->getClientOriginalName(),
                    'agency_id' => $agencyId,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Malware scanner unavailable; upload rejected.',
                ], 422);
            }

            if (!$scannerUnavailable && !$scan->isClean && !$scan->skipped) {
                $this->quarantineRejected($request, $agencyId, $file, $check['ext'], 'infected', $scan->threats);
                Log::warning('Media upload rejected: malware detected', [
                    'file' => $file->getClientOriginalName(),
                    'agency_id' => $agencyId,
                    'threats' => $scan->threats,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'File failed malware scan and was rejected.',
                ], 422);
            }

            $type = $type ?: (in_array($ext, self::VIDEO_EXT, true) ? 'video'
                : (in_array($ext, self::IMAGE_EXT, true) ? 'image' : 'document'));

            if (($type === 'image' || $type === 'banner') && !in_array($ext, self::IMAGE_EXT, true)) {
                return response()->json(['success' => false, 'message' => 'Image and banner media must use an image file.'], 422);
            }
            if ($type === 'video' && !in_array($ext, self::VIDEO_EXT, true)) {
                return response()->json(['success' => false, 'message' => 'Video media must use a supported video file.'], 422);
            }
            if ($visibility === 'public' && $type === 'document') {
                return response()->json(['success' => false, 'message' => 'Sensitive document media must remain private.'], 422);
            }

            $storedPath = 'media/' . $agencyId . '/' . now()->format('Y/m') . '/' . $mediaId . '.' . $ext;

            // Storage quota/trial enforcement is not part of this release.
            // The per-file size limit, content checks and malware scan above
            // still apply before anything is written to the private disk.
            SecureFileStore::storeUpload('documents', $storedPath, $file);

            $originalName = UploadSecurity::sanitizeOriginalName($file->getClientOriginalName());
            $mimeType = $check['mime'] ?: 'application/octet-stream';
            $size = (int) $file->getSize();
            $scanStatus = $scan->isClean ? 'scanned_clean' : 'skipped';
            $scannedAt = $scan->isClean ? now() : null;

            if (str_starts_with((string) $mimeType, 'image/')) {
                $dims = @getimagesize($file->getRealPath());
                if ($dims) {
                    $dimensions = $dims[0] . 'x' . $dims[1];
                }
            }
        } else {
            $type = $type ?: 'image';
            $originalName = Str::slug($request->title);
        }

        try {
            $media = MediaFile::create([
            'id' => $mediaId,
            'agency_id' => $agencyId,
            'property_id' => $request->property_id,
            'folder' => $request->get('folder', 'General'),
            'title' => $request->title,
            'description' => $request->description,
            'type' => $type,
            'original_name' => $originalName,
            'stored_name' => $storedPath ? basename($storedPath) : null,
            'disk_path' => $storedPath,
            'url' => $request->url,
            'mime_type' => $mimeType,
            'size_bytes' => $size,
            'dimensions' => $dimensions,
            'visibility' => $visibility,
            'tags' => $request->tags,
            'status' => 'available',
            'scan_status' => $scanStatus,
            'scan_completed_at' => $scannedAt,
            'uploaded_by' => $request->user()->id,
        ]);
        } catch (\Throwable $exception) {
            if ($storedPath !== null) {
                try {
                    $storage = Storage::disk('documents');
                    if ($storage->exists($storedPath) && !$storage->delete($storedPath)) {
                        Log::critical('Uncommitted media upload could not be cleaned after database failure', [
                            'agency_id' => $agencyId,
                            'path_len' => strlen($storedPath),
                        ]);
                    }
                } catch (\Throwable $cleanupException) {
                    Log::critical('Uncommitted media upload cleanup raised an exception', [
                        'agency_id' => $agencyId,
                        'error' => $cleanupException->getMessage(),
                    ]);
                }
            }

            throw $exception;
        }

        $this->writeAudit($request, 'media.upload', $media->id, "Media added: {$media->title}", [
            'agency_id' => $agencyId,
            'entity_type' => 'media_file',
            'entity_id' => $media->id,
            'metadata' => ['visibility' => $visibility, 'type' => $type, 'size_bytes' => $size],
        ]);

        $payload = $media->toArray();
        $payload['file_url'] = $visibility === 'public'
            ? '/api/media/' . $media->id . '/file'
            : '/api/agency/media/' . $media->id . '/file';

        return response()->json([
            'success' => true,
            'message' => 'Media saved.',
            'media' => $payload,
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requireAny($request, ['manage_media', 'upload_media']);

        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'folder' => 'sometimes|string|max:60',
            'visibility' => 'sometimes|in:public,private',
            'property_id' => 'nullable|string|max:256',
            'description' => 'nullable|string|max:2000',
            'tags' => 'nullable|array|max:20',
            'tags.*' => 'string|max:40',
        ]);

        $media = DB::transaction(function () use ($request, $agencyId, $id, $data): MediaFile {
            $media = MediaFile::where('agency_id', $agencyId)
                ->lockForUpdate()
                ->findOrFail($id);

            if (array_key_exists('property_id', $data) && $data['property_id'] !== null) {
                Property::where('agency_id', $agencyId)->where('id', $data['property_id'])->firstOrFail();
            }

            $newVisibility = $data['visibility'] ?? $media->visibility;
            if ($newVisibility === 'public' && (
                $media->type === 'document'
                || $media->status === 'sold'
                || ($media->scan_status !== null && $media->scan_status !== 'scanned_clean')
            )) {
                abort(422, 'Sensitive, sold or unverified media cannot be made public.');
            }

            $media->fill($data);
            $media->save();

            return $media->fresh();
        }, 3);

        $this->writeAudit($request, 'media.update', $media->id, "Media updated: {$media->title}", [
            'agency_id' => $agencyId,
            'entity_type' => 'media_file',
            'entity_id' => $media->id,
            'metadata' => ['fields' => array_keys($data)],
        ]);

        return response()->json(['success' => true, 'media' => $media]);
    }

    /**
     * POST /api/agency/media/{id}/sell — mark as sold.
     * No guessed cost price: profit is only computed when a real cost is given.
     */
    public function sell(Request $request, string $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requireAny($request, ['manage_media']);

        $agency = Agency::find($agencyId);
        if ($agency !== null && !PlanLimits::subscriptionActive($agency)) {
            return PlanLimits::subscriptionDenied();
        }

        $request->validate([
            'selling_price_pk' => 'required|numeric|decimal:0,2|min:0|max:999999999999.99',
            'cost_price_pk' => 'nullable|numeric|decimal:0,2|min:0|max:999999999999.99',
            'buyer_name' => 'required|string|max:255',
            'buyer_phone' => 'nullable|string|max:30',
            'buyer_national_id_masked' => ['nullable', 'string', 'max:40', 'regex:/^\\*{5}-\\*{7}-\\d{1,4}$/'],
            'sold_at' => 'nullable|date',
        ]);

        $sellingMinor = $this->toMinorUnits($request->input('selling_price_pk'));
        $costMinor = $request->filled('cost_price_pk')
            ? $this->toMinorUnits($request->input('cost_price_pk'))
            : null;
        $soldAt = $request->filled('sold_at') ? now()->parse($request->input('sold_at')) : now();

        $result = DB::transaction(function () use ($agencyId, $id, $request, $sellingMinor, $costMinor, $soldAt): array {
            $media = MediaFile::where('agency_id', $agencyId)
                ->lockForUpdate()
                ->findOrFail($id);

            if ($media->status === 'sold') {
                return ['sold' => false, 'media' => $media];
            }

            $media->status = 'sold';
            $media->visibility = 'private';
            $media->sold_at = $soldAt;
            $media->sold_price_pk = $this->minorUnitsToDecimal($sellingMinor);
            $media->cost_price_pk = $costMinor !== null ? $this->minorUnitsToDecimal($costMinor) : null;
            $media->profit_pk = $costMinor !== null
                ? $this->minorUnitsToDecimal(max(0, $sellingMinor - $costMinor))
                : '0.00';
            $media->buyer_name = strip_tags($request->input('buyer_name'));
            $media->buyer_phone = $request->input('buyer_phone');
            $media->buyer_national_id_masked = $request->input('buyer_national_id_masked');
            $media->sold_by = $request->user()->name;
            $media->save();

            return ['sold' => true, 'media' => $media->fresh()];
        }, 3);

        if (!$result['sold']) {
            return response()->json([
                'success' => false,
                'message' => 'This item is already marked as sold.',
            ], 422);
        }

        $media = $result['media'];
        $this->writeAudit($request, 'media.sell', $media->id, "Marked sold: {$media->title}", [
            'agency_id' => $agencyId,
            'entity_type' => 'media_file',
            'entity_id' => $media->id,
            'metadata' => [
                'selling_price_pk' => $media->sold_price_pk,
                'cost_price_pk' => $media->cost_price_pk,
                'profit_pk' => $media->profit_pk,
                'buyer' => $media->buyer_name,
            ],
        ]);

        return response()->json(['success' => true, 'message' => 'Marked as sold.', 'media' => $media]);
    }

    public function destroy(Request $request, string $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requireAny($request, ['manage_media']);

        try {
            $result = DB::transaction(function () use ($agencyId, $id): array {
                $media = MediaFile::where('agency_id', $agencyId)
                    ->lockForUpdate()
                    ->findOrFail($id);

                $referenced = Property::where('agency_id', $agencyId)
                    ->whereNull('deleted_at')
                    ->where('images', 'like', '%' . $media->id . '%')
                    ->exists();

                $fileName = $media->disk_path ? basename($media->disk_path) : null;
                if (!$referenced && $fileName) {
                    $referenced = Property::where('agency_id', $agencyId)
                        ->whereNull('deleted_at')
                        ->where('images', 'like', '%' . $fileName . '%')
                        ->exists();
                }

                if ($referenced) {
                    return ['deleted' => false, 'reason' => 'in_use', 'media' => $media];
                }

                if ($media->disk_path) {
                    $storage = Storage::disk('documents');
                    if ($storage->exists($media->disk_path) && !$storage->delete($media->disk_path)) {
                        throw new \RuntimeException('Private media storage did not confirm file deletion.');
                    }
                }

                $media->delete();

                return ['deleted' => true, 'reason' => null, 'media' => $media];
            }, 3);
        } catch (\Throwable $exception) {
            Log::warning('Media delete stopped because private storage cleanup failed', [
                'agency_id' => $agencyId,
                'media_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Private storage could not confirm deletion. The media record was retained; retry after storage is healthy.',
            ], 503);
        }

        if (!$result['deleted']) {
            return response()->json([
                'success' => false,
                'message' => 'This media is used by an active listing. Remove it from the listing first.',
            ], 409);
        }

        $media = $result['media'];
        $this->writeAudit($request, 'media.delete', $media->id, "Media deleted: {$media->title}", [
            'agency_id' => $agencyId,
            'entity_type' => 'media_file',
            'entity_id' => $media->id,
        ]);

        return response()->json(['success' => true, 'message' => 'Media deleted.']);
    }

    public function file(Request $request, string $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requireAny($request, ['manage_media', 'upload_media', 'properties.view', 'view_properties']);

        $media = MediaFile::where('agency_id', $agencyId)->findOrFail($id);
        return $this->deliver($media);
    }

    /**
     * GET /api/media/{id}/file — public delivery of visibility=public media only.
     */
    public function publicFile(Request $request, string $id)
    {
        $media = MediaFile::where('id', $id)
            ->where('visibility', 'public')
            ->where('status', 'available')
            ->whereNull('deleted_at')
            ->firstOrFail();

        $agency = Agency::where('id', $media->agency_id)
            ->where('status', 'active')
            ->firstOrFail();

        if ($media->property_id !== null) {
            $property = Property::where('agency_id', $agency->id)
                ->publiclyVisible()
                ->findOrFail($media->property_id);

            if (!$property->isPubliclyVisible()) {
                abort(404);
            }
        }

        return $this->deliver($media);
    }

    private function deliver(MediaFile $media)
    {
        if ($media->disk_path) {
            // Lifecycle gate: media that has not cleared malware verification
            // (fail-open hold / infected holdover) never leaves the private
            // disk — neither privately nor publicly. Legacy rows recorded
            // before the scan lifecycle existed (scan_status = NULL) keep
            // streaming so historical content does not vanish.
            if ($media->scan_status !== null && $media->scan_status !== 'scanned_clean') {
                return response()->json([
                    'success' => false,
                    'message' => 'This media has not cleared malware verification yet.',
                ], 423);
            }

            // Authorised streaming through the SecureFileStore (decrypts
            // encrypted-at-rest bytes; header-safe disposition; nosniff).
            return SecureFileStore::response(
                'documents',
                $media->disk_path,
                $media->original_name ?: basename($media->disk_path),
                $media->mime_type ?: 'application/octet-stream',
                'inline'
            );
        }

        if ($media->url) {
            // The stored URL was validated at registration, but re-check on
            // every delivery (defence in depth): only HTTPS public hosts.
            if (!UploadSecurity::isAllowedExternalUrl($media->url)) {
                abort(410, 'External media URL is not allowed.');
            }

            return redirect()->away($media->url);
        }

        abort(410, 'File is no longer available.');
    }

    /**
     * Isolate a rejected media upload (infected / scanner-unavailable) on the
     * quarantine disk + visibility row, mirroring the vault behaviour.
     */
    private function quarantineRejected(
        Request $request,
        string $agencyId,
        $file,
        string $ext,
        string $reason,
        array $threats
    ): void {
        try {
            $qId = 'qtn_' . bin2hex(random_bytes(8));
            $qPath = $agencyId . '/' . now()->format('Y/m') . '/' . $qId . '.' . $ext;

            SecureFileStore::storeUpload('quarantine', $qPath, $file);

            \App\Models\DocumentQuarantine::create([
                'id' => $qId,
                'agency_id' => $agencyId,
                'document_id' => null,
                'original_name' => UploadSecurity::sanitizeOriginalName($file->getClientOriginalName()),
                'stored_name' => basename($qPath),
                'disk_path' => $qPath,
                'mime_type' => @mime_content_type($file->getRealPath()) ?: 'application/octet-stream',
                'size_bytes' => (int) $file->getSize(),
                'checksum' => hash_file('sha256', $file->getRealPath()) ?: null,
                'reason' => $reason,
                'threats' => $threats,
                'uploaded_by' => $request->user()?->id,
                'created_at' => now(),
            ]);

            $this->writeAudit($request, 'media.quarantined', $qId,
                'Media upload quarantined (' . $reason . ')', [
                    'agency_id' => $agencyId,
                    'entity_type' => 'document_quarantine',
                    'entity_id' => $qId,
                    'metadata' => ['reason' => $reason, 'threats' => $threats],
                ]);
        } catch (\Throwable $e) {
            Log::warning('Media quarantine write failed', ['error' => $e->getMessage()]);
        }
    }
    private function toMinorUnits(string|int|float $amount): int
    {
        $value = trim((string) $amount);
        if (!preg_match('/^\d{1,12}(?:\.\d{1,2})?$/', $value)) {
            throw new \InvalidArgumentException('Amount must be a non-negative PKR value with at most two decimal places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function minorUnitsToDecimal(int $minorUnits): string
    {
        return intdiv($minorUnits, 100) . '.' . str_pad((string) ($minorUnits % 100), 2, '0', STR_PAD_LEFT);
    }

}

