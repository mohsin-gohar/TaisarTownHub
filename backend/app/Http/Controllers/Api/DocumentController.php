<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Document;
use App\Models\DocumentAccessLog;
use App\Models\DocumentFolder;
use App\Models\DocumentQuarantine;
use App\Models\DocumentShare;
use App\Models\DocumentVersion;
use App\Models\Property;
use App\Models\SystemSetting;
use App\Support\PlanLimits;
use App\Services\MalwareScanner;
use App\Services\SecureFileStore;
use App\Support\UploadSecurity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * PRIVATE DOCUMENT VAULT.
 *
 * Files live on the `documents` private disk (storage/app/private/documents,
 * never web-served). Every read goes through authenticated, agency-scoped,
 * permission-checked, audit-logged endpoints below. Cross-agency ids return
 * 404 (never 403) so document existence cannot be probed.
 */
class DocumentController extends Controller
{
    use AuthorizesApi;

    /* ==================== Folders ==================== */

    public function folders(Request $request)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'documents.view');

        $folders = DocumentFolder::where('agency_id', $agencyId)
            ->orderBy('path')
            ->get()
            ->map(fn (DocumentFolder $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'path' => $f->path,
                'parent_id' => $f->parent_id,
                'property_id' => $f->property_id,
                'document_count' => $f->documents()->where('status', 'active')->count(),
                'created_at' => $f->created_at,
            ]);

        return response()->json(['success' => true, 'folders' => $folders]);
    }

    public function storeFolder(Request $request)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'documents.upload');

        $request->validate([
            'name' => 'required|string|min:1|max:80',
            'parent_id' => 'nullable|string|max:64',
            'property_id' => 'nullable|string|max:256',
        ]);

        $parent = null;
        if ($request->parent_id) {
            $parent = DocumentFolder::where('agency_id', $agencyId)
                ->where('id', $request->parent_id)
                ->firstOrFail();
        }
        if ($request->property_id) {
            Property::where('agency_id', $agencyId)->where('id', $request->property_id)->firstOrFail();
        }

        $slug = Str::slug($request->name) ?: 'folder';
        $basePath = $parent ? $parent->path . '/' . $slug : $slug;

        // Materialised path, unique per agency
        $path = $basePath;
        $i = 2;
        while (DocumentFolder::where('agency_id', $agencyId)->where('path', $path)->exists()) {
            $path = $basePath . '-' . $i++;
        }

        $folder = DocumentFolder::create([
            'agency_id' => $agencyId,
            'parent_id' => $parent?->id,
            'property_id' => $request->property_id,
            'name' => $request->name,
            'path' => $path,
            'created_by' => $request->user()->id,
        ]);

        $this->writeAudit($request, 'document.folder.create', $folder->id, "Folder created: {$folder->path}", [
            'agency_id' => $agencyId,
            'entity_type' => 'document_folder',
            'entity_id' => $folder->id,
        ]);

        return response()->json([
            'success' => true,
            'folder' => [
                'id' => $folder->id, 'name' => $folder->name, 'path' => $folder->path,
                'parent_id' => $folder->parent_id, 'document_count' => 0,
            ],
        ], 201);
    }

    public function destroyFolder(Request $request, string $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'documents.delete');

        $folder = DocumentFolder::where('agency_id', $agencyId)->findOrFail($id);

        $hasDocuments = $folder->documents()->exists();
        $hasChildren = DocumentFolder::where('parent_id', $folder->id)->exists();

        if ($hasDocuments || $hasChildren) {
            return response()->json([
                'success' => false,
                'message' => 'Folder is not empty. Move or delete its contents first.',
            ], 422);
        }

        $folder->delete();

        $this->writeAudit($request, 'document.folder.delete', $id, "Folder deleted: {$folder->path}", [
            'agency_id' => $agencyId,
            'entity_type' => 'document_folder',
            'entity_id' => $id,
        ]);

        return response()->json(['success' => true, 'message' => 'Folder deleted.']);
    }

    /* ==================== Documents ==================== */

    public function index(Request $request)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'documents.view');

        $query = Document::where('agency_id', $agencyId);

        if ($request->filled('folder_id')) {
            $query->where('folder_id', $request->folder_id);
        }
        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'active');
        }
        if ($request->filled('search')) {
            $s = addcslashes((string) $request->search, '%_\\');
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                    ->orWhere('original_name', 'like', "%{$s}%");
            });
        }

        // Sensitive documents are only listed for cleared users
        $canSeeSensitive = $this->can($request, 'documents.view_sensitive');
        if (!$canSeeSensitive) {
            $query->where('is_sensitive', false);
        }

        $page = $query->with(['folder:id,name,path'])
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->get('per_page', 25), 100));

        return response()->json([
            'success' => true,
            'documents' => $page->items(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
            'can_upload' => $this->can($request, 'documents.upload'),
            'can_see_sensitive' => $canSeeSensitive,
        ]);
    }

    /**
     * POST /api/agency/documents — upload a file to the private vault.
     * Multipart: file (required), title, category, folder_id, property_id, tags.
     *
     * Upload lifecycle: validation â†’ content checks (extension allowlist,
     * sniffed MIME, magic bytes from offset 0) â†’ malware scan â†’ verdict:
     *   clean       â†’ stored APPROVED on the private disk (encrypted at rest),
     *   infected    â†’ REJECTED; isolated copy kept on the quarantine disk,
     *   unavailable â†’ fail-closed: REJECTED (isolated copy kept),
     *                 fail-open: stored but QUARANTINED — never downloadable
     *                 until an administrator rescan clears it.
     */
    public function store(Request $request, MalwareScanner $scanner)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'documents.upload');

        $maxMb = (int) SystemSetting::get('storage.max_upload_mb', 25);

        $request->validate([
            'file' => 'required|file|max:' . ($maxMb * 1024),
            'title' => 'nullable|string|max:255',
            'category' => 'nullable|string|in:cnic,registry,allotment_letter,transfer_letter,ownership,sale_agreement,payment_receipt,booking_form,map_site_plan,image,video,pdf,other',
            'folder_id' => 'nullable|string|max:64',
            'property_id' => 'nullable|string|max:256',
            'tags' => 'nullable|array|max:20',
            'tags.*' => 'string|max:40',
        ]);

        $file = $request->file('file');
        $category = $request->get('category', 'other');
        $isSensitive = in_array($category, $this->sensitiveCategories(), true);

        // Authorize sensitive uploads and validate tenant relationships before
        // spending scanner resources or retaining a rejected/quarantined copy.
        if ($isSensitive) {
            $this->requirePermission($request, 'documents.view_sensitive');
        }
        if ($request->folder_id) {
            DocumentFolder::where('agency_id', $agencyId)->where('id', $request->folder_id)->firstOrFail();
        }
        if ($request->property_id) {
            Property::where('agency_id', $agencyId)->where('id', $request->property_id)->firstOrFail();
        }

        // Content security: extension allowlist + sniffed MIME blocklist +
        // magic bytes (offset 0). The browser-declared type is never trusted.
        $check = UploadSecurity::evaluate($file, UploadSecurity::allowedExtensions());
        if ($check['error'] !== null) {
            return $check['error'];
        }

        // Malware scan (provider-agnostic; clamscan/clamd). A file is NEVER
        // reported as clean unless a real scan ran and the scanner said so.
        $scan = $scanner->scan($file->getRealPath());
        // Unavailability is signalled two ways in this codebase: the canonical
        // `skipped` flag (scanner unreachable / disabled) or the literal
        // 'scan_unavailable' threat token from scanner wrappers.
        $scannerUnavailable = $scan->skipped || in_array('scan_unavailable', $scan->threats, true);
        $scanClean = false;

        if ($scannerUnavailable) {
            if (config('malware.fail_closed', false)) {
                $this->quarantineRejected($request, $agencyId, $file, $check['ext'], 'scan_unavailable', $scan->threats);
                Log::warning('Upload rejected: malware scanner unavailable (fail-closed)', [
                    'file' => $file->getClientOriginalName(),
                    'agency_id' => $agencyId,
                    'reason' => $scan->skipReason ?? 'scan_unavailable',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Malware scanner unavailable; upload rejected.',
                ], 422);
            }

            // Fail-open: keep for administrators on the vault disk but mark
            // the row quarantined — downloads stay blocked until a rescan.
            if (config('malware.log_skipped', true)) {
                Log::warning('Upload accepted without malware scan (quarantined)', [
                    'file' => $file->getClientOriginalName(),
                    'agency_id' => $agencyId,
                    'reason' => $scan->skipReason ?? 'scan_unavailable',
                ]);
            }
        } elseif (!$scan->isClean) {
            $this->quarantineRejected($request, $agencyId, $file, $check['ext'], 'infected', $scan->threats);
            Log::warning('Upload rejected: malware detected', [
                'file' => $file->getClientOriginalName(),
                'agency_id' => $agencyId,
                'threats' => $scan->threats,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'File failed malware scan and was rejected.',
            ], 422);
        } else {
            $scanClean = true;
        }

        // Storage quota/trial enforcement is not part of this release.
        // Individual file size, type, malware and authorization checks above
        // and below still apply.

        $documentId = 'doc_' . bin2hex(random_bytes(8));
        $storedName = $documentId . '.' . $check['ext'];
        $relativePath = $agencyId . '/' . now()->format('Y/m') . '/' . $storedName;
        $originalName = UploadSecurity::sanitizeOriginalName($file->getClientOriginalName());

        // Bytes are written through the SecureFileStore — encrypted at rest,
        // unpredictable path, never web-served. A fail-open hold stays on the
        // vault disk but the row is quarantined so downloads stay blocked.
        SecureFileStore::storeUpload('documents', $relativePath, $file);

        try {
            $document = Document::create([
                'id' => $documentId,
                'agency_id' => $agencyId,
                'folder_id' => $request->folder_id,
                'property_id' => $request->property_id,
                'category' => $category,
                'title' => $request->get('title') ?: $originalName,
                'original_name' => $originalName,
                'stored_name' => $storedName,
                'disk_path' => $relativePath,
                'storage_disk' => 'documents',
                'mime_type' => $check['mime'] ?: 'application/octet-stream',
                'size_bytes' => (int) $file->getSize(),
                'checksum' => hash_file('sha256', $file->getRealPath()) ?: null,
                'is_sensitive' => $isSensitive,
                'version' => 1,
                'status' => $scanClean ? 'active' : 'quarantined',
                'scan_status' => $scanClean ? 'scanned_clean' : 'skipped',
                'scan_threats' => $scanClean ? null : [$scan->skipReason ?? 'scan_unavailable'],
                'scan_completed_at' => $scanClean ? now() : null,
                'scanner' => $scanClean ? $scan->scanner : null,
                'tags' => $request->get('tags'),
                'uploaded_by' => $request->user()->id,
            ]);
        } catch (\Throwable $exception) {
            try {
                $storage = Storage::disk('documents');
                if ($storage->exists($relativePath) && !$storage->delete($relativePath)) {
                    Log::critical('Uncommitted document upload could not be cleaned after database failure', [
                        'document_id' => $documentId,
                        'path_len' => strlen($relativePath),
                    ]);
                }
            } catch (\Throwable $cleanupException) {
                Log::critical('Uncommitted document upload cleanup raised an exception', [
                    'document_id' => $documentId,
                    'error' => $cleanupException->getMessage(),
                ]);
            }

            throw $exception;
        }

        $this->logAccess($document, $agencyId, $request, 'upload');

        $this->writeAudit($request, 'document.upload', $document->id, "Uploaded: {$document->title}", [
            'agency_id' => $agencyId,
            'entity_type' => 'document',
            'entity_id' => $document->id,
            'metadata' => [
                'category' => $category,
                'is_sensitive' => $isSensitive,
                'size_bytes' => $document->size_bytes,
                'property_id' => $request->property_id,
                'scan_status' => $document->scan_status,
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => $scanClean
                ? 'Document uploaded to the private vault.'
                : 'Document held in quarantine until malware verification completes.',
            'document' => $document,
        ], 201);
    }

    /** PATCH /api/agency/documents/{id} — details only (never the file bytes). */
    public function update(Request $request, string $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'documents.update');

        $document = $this->findScoped($agencyId, $id);

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'category' => 'sometimes|string|in:cnic,registry,allotment_letter,transfer_letter,ownership,sale_agreement,payment_receipt,booking_form,map_site_plan,image,video,pdf,other',
            'folder_id' => 'nullable|string|max:64',
            'property_id' => 'nullable|string|max:256',
            'tags' => 'nullable|array|max:20',
            'tags.*' => 'string|max:40',
        ]);

        if ($request->filled('folder_id')) {
            DocumentFolder::where('agency_id', $agencyId)->where('id', $request->folder_id)->firstOrFail();
        }
        if ($request->filled('property_id')) {
            Property::where('agency_id', $agencyId)->where('id', $request->property_id)->firstOrFail();
        }

        if ($request->has('category')) {
            $wasSensitive = $document->is_sensitive;
            $document->is_sensitive = in_array($request->category, $this->sensitiveCategories(), true);
            if ($document->is_sensitive || $wasSensitive) {
                $this->requirePermission($request, 'documents.view_sensitive');
            }
        }

        $document->fill($request->only(['title', 'category', 'folder_id', 'property_id', 'tags']));
        $document->save();

        $this->writeAudit($request, 'document.update', $document->id, "Document details updated: {$document->title}", [
            'agency_id' => $agencyId,
            'entity_type' => 'document',
            'entity_id' => $document->id,
            'metadata' => ['fields' => array_keys($request->only(['title', 'category', 'folder_id', 'property_id', 'tags']))],
        ]);

        return response()->json(['success' => true, 'document' => $document]);
    }

    /**
     * POST /api/agency/documents/{id}/versions — upload a new version.
     *
     * Version bytes previously bypassed every content check the first upload
     * performs; they now run the SAME pipeline (extension allowlist, sniffed
     * MIME, magic bytes, malware scan) before the live document is touched.
     * A rejected version leaves the current document fully intact.
     */
    public function storeVersion(Request $request, string $id, MalwareScanner $scanner)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'documents.update');

        $document = $this->findScoped($agencyId, $id);
        if ($document->is_sensitive) {
            $this->requirePermission($request, 'documents.view_sensitive');
        }
        if ($document->status !== 'active' || $document->scan_status !== 'scanned_clean') {
            return response()->json([
                'success' => false,
                'message' => 'Versions can only be uploaded for active documents that have cleared malware verification.',
            ], 422);
        }

        $maxMb = (int) SystemSetting::get('storage.max_upload_mb', 25);
        $request->validate([
            'file' => 'required|file|max:' . ($maxMb * 1024),
            'note' => 'nullable|string|max:500',
        ]);

        $file = $request->file('file');
        $check = UploadSecurity::evaluate($file, UploadSecurity::allowedExtensions());
        if ($check['error'] !== null) {
            return $check['error'];
        }

        $scan = $scanner->scan($file->getRealPath());
        $scannerUnavailable = $scan->skipped || in_array('scan_unavailable', $scan->threats, true);

        if ($scannerUnavailable && config('malware.fail_closed', false)) {
            $this->quarantineRejected($request, $agencyId, $file, $check['ext'], 'scan_unavailable', $scan->threats, $document->id);
            Log::warning('Version rejected: malware scanner unavailable (fail-closed)', [
                'document_id' => $document->id,
                'agency_id' => $agencyId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Malware scanner unavailable; upload rejected.',
            ], 422);
        }

        if (!$scannerUnavailable && !$scan->isClean) {
            $this->quarantineRejected($request, $agencyId, $file, $check['ext'], 'infected', $scan->threats, $document->id);
            Log::warning('Version rejected: malware detected', [
                'document_id' => $document->id,
                'agency_id' => $agencyId,
                'threats' => $scan->threats,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'File failed malware scan and was rejected.',
            ], 422);
        }

        $scanClean = $scan->isClean;
        $originalName = UploadSecurity::sanitizeOriginalName($file->getClientOriginalName());
        $sizeBytes = (int) $file->getSize();
        $checksum = hash_file('sha256', $file->getRealPath()) ?: null;
        $writtenPaths = [];

        try {
            $document = DB::transaction(function () use (
                $id, $agencyId, $file, $check, $scan, $scanClean, $originalName,
                $sizeBytes, $checksum, $request, &$writtenPaths
            ): Document {
                // Serialize all version uploads for this document. The current
                // version is read only after the row lock, never from the
                // potentially stale pre-scan model.
                $locked = Document::where('agency_id', $agencyId)
                    ->lockForUpdate()
                    ->findOrFail($id);

                if ($locked->status !== 'active' || $locked->scan_status !== 'scanned_clean') {
                    abort(409, 'The document changed while the new version was being scanned. Refresh and retry.');
                }

                $nextVersion = (int) $locked->version + 1;
                $storedName = $locked->id . '_v' . $nextVersion . '_' . bin2hex(random_bytes(6)) . '.' . $check['ext'];
                $relativePath = $agencyId . '/' . now()->format('Y/m') . '/' . $storedName;

                // Track before writing so even a partial failed write is cleaned.
                $writtenPaths[] = $relativePath;
                SecureFileStore::storeUpload('documents', $relativePath, $file);

                $previous = [
                    'version' => $locked->version,
                    'stored_name' => $locked->stored_name,
                    'disk_path' => $locked->disk_path,
                    'storage_disk' => $locked->storage_disk ?? 'documents',
                    'mime_type' => $locked->mime_type,
                    'size_bytes' => $locked->size_bytes,
                    'checksum' => $locked->checksum,
                    'uploaded_by' => $locked->uploaded_by,
                    'created_at' => $locked->updated_at ?? now(),
                ];

                DocumentVersion::create([
                    'document_id' => $locked->id,
                    'version' => $previous['version'],
                    'stored_name' => $previous['stored_name'],
                    'disk_path' => $previous['disk_path'],
                    'storage_disk' => $previous['storage_disk'],
                    'mime_type' => $previous['mime_type'],
                    'size_bytes' => $previous['size_bytes'],
                    'checksum' => $previous['checksum'],
                    'uploaded_by' => $previous['uploaded_by'],
                    'note' => 'Superseded',
                    'created_at' => $previous['created_at'],
                ]);

                $locked->version = $nextVersion;
                $locked->stored_name = $storedName;
                $locked->disk_path = $relativePath;
                $locked->storage_disk = 'documents';
                $locked->mime_type = $check['mime'] ?: $locked->mime_type;
                $locked->size_bytes = $sizeBytes;
                $locked->checksum = $checksum;
                $locked->original_name = $originalName;
                $locked->scan_status = $scanClean ? 'scanned_clean' : 'skipped';
                $locked->scan_threats = $scanClean ? null : [$scan->skipReason ?? 'scan_unavailable'];
                $locked->scan_completed_at = $scanClean ? now() : null;
                $locked->scanner = $scanClean ? $scan->scanner : null;
                $locked->status = $scanClean ? 'active' : 'quarantined';
                $locked->save();

                DocumentVersion::create([
                    'document_id' => $locked->id,
                    'version' => $nextVersion,
                    'stored_name' => $storedName,
                    'disk_path' => $relativePath,
                    'storage_disk' => 'documents',
                    'mime_type' => $locked->mime_type,
                    'size_bytes' => $locked->size_bytes,
                    'checksum' => $locked->checksum,
                    'uploaded_by' => $request->user()->id,
                    'note' => $request->note,
                    'created_at' => now(),
                ]);

                return $locked;
            }, 3);
        } catch (\Throwable $exception) {
            foreach ($writtenPaths as $path) {
                try {
                    if (Storage::disk('documents')->exists($path) && !Storage::disk('documents')->delete($path)) {
                        Log::critical('Failed to clean an uncommitted document version file', [
                            'document_id' => $id,
                            'path_len' => strlen($path),
                        ]);
                    }
                } catch (\Throwable $cleanupException) {
                    Log::critical('Exception while cleaning an uncommitted document version file', [
                        'document_id' => $id,
                        'error' => $cleanupException->getMessage(),
                    ]);
                }
            }

            throw $exception;
        }

        // A deadlock retry may have written an earlier random path before the
        // successful attempt. Remove those unreferenced copies after commit.
        foreach ($writtenPaths as $path) {
            if ($path === $document->disk_path) {
                continue;
            }
            try {
                if (Storage::disk('documents')->exists($path) && !Storage::disk('documents')->delete($path)) {
                    Log::warning('Orphaned document version cleanup failed', [
                        'document_id' => $id,
                        'path_len' => strlen($path),
                    ]);
                }
            } catch (\Throwable $cleanupException) {
                Log::warning('Orphaned document version cleanup raised an exception', [
                    'document_id' => $id,
                    'error' => $cleanupException->getMessage(),
                ]);
            }
        }

        $this->logAccess($document, $agencyId, $request, 'upload');
        $this->writeAudit($request, 'document.version', $document->id, "New version v{$document->version}: {$document->title}", [
            'agency_id' => $agencyId,
            'entity_type' => 'document',
            'entity_id' => $document->id,
            'metadata' => ['version' => $document->version, 'note' => $request->note, 'scan_status' => $document->scan_status],
        ]);

        return response()->json([
            'success' => true,
            'message' => $scanClean ? 'New version stored.' : 'New version held in quarantine until malware verification completes.',
            'document' => $document,
        ]);
    }

    public function versions(Request $request, string $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'documents.view');

        $document = $this->findScoped($agencyId, $id);
        if ($document->is_sensitive) {
            $this->requirePermission($request, 'documents.view_sensitive');
        }

        return response()->json([
            'success' => true,
            'versions' => $document->versions()->with('uploader:id,name')->get(),
        ]);
    }

    /**
     * GET /api/agency/documents/{id}/download — authenticated, scoped,
     * permission-checked, audit-logged streaming from the private disk.
     */
    public function download(Request $request, string $id)
    {
        return $this->stream($request, $id, 'attachment');
    }

    /**
     * GET /api/agency/documents/{id}/preview — same controls, inline
     * disposition for browser preview of images/PDFs.
     */
    public function preview(Request $request, string $id)
    {
        return $this->stream($request, $id, 'inline');
    }

    private function stream(Request $request, string $id, string $disposition)
    {
        $agencyId = $this->requireAgency($request);

        // Denied attempts are audited BEFORE the abort so unauthorized
        // download probing leaves a trace (the FK-safe access-log row is
        // only written once the document actually resolves in this agency).
        if (!$this->can($request, 'documents.download')) {
            $this->writeAudit($request, 'document.download_denied', $id,
                'Download denied: missing documents.download permission', [
                    'agency_id' => $agencyId,
                    'entity_type' => 'document',
                    'entity_id' => $id,
                    'metadata' => ['permission' => 'documents.download'],
                ]);
        }
        $this->requirePermission($request, 'documents.download');

        $document = $this->findScoped($agencyId, $id);

        if ($document->is_sensitive && !$this->can($request, 'documents.view_sensitive')) {
            $this->logAccess($document, $agencyId, $request, 'download_denied');
            $this->writeAudit($request, 'document.download_denied', $document->id,
                'Download denied: sensitive document clearance missing', [
                    'agency_id' => $agencyId,
                    'entity_type' => 'document',
                    'entity_id' => $document->id,
                    'metadata' => ['permission' => 'documents.view_sensitive', 'is_sensitive' => true],
                ]);
        }

        if ($document->is_sensitive) {
            $this->requirePermission($request, 'documents.view_sensitive');
        }

        // Lifecycle gate FIRST: a document that has not cleared malware
        // verification is NEVER downloadable (fail-open holds, rescans) —
        // this applies before the trash check so held bytes can never be
        // confused with ordinary "deleted" responses.
        if ($document->scan_status !== 'scanned_clean') {
            $this->logAccess($document, $agencyId, $request, 'download_blocked');
            $this->writeAudit($request, 'document.download_blocked', $document->id,
                'Download blocked: malware verification not cleared', [
                    'agency_id' => $agencyId,
                    'entity_type' => 'document',
                    'entity_id' => $document->id,
                    'metadata' => ['scan_status' => $document->scan_status],
                ]);

            return response()->json([
                'success' => false,
                'message' => 'This document has not cleared malware verification yet.',
            ], 423);
        }

        if ($document->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'This document is in the trash.',
            ], 410);
        }

        $this->logAccess($document, $agencyId, $request, $disposition === 'inline' ? 'preview' : 'download');

        $this->writeAudit($request, $disposition === 'inline' ? 'document.preview' : 'document.download', $document->id,
            ($disposition === 'inline' ? 'Previewed: ' : 'Downloaded: ') . $document->title, [
                'agency_id' => $agencyId,
                'entity_type' => 'document',
                'entity_id' => $document->id,
                'metadata' => ['is_sensitive' => $document->is_sensitive],
            ]);

        return SecureFileStore::response(
            $document->storage_disk ?: 'documents',
            $document->disk_path,
            $document->original_name ?: $document->stored_name,
            $document->mime_type,
            $disposition
        );
    }

    /* ==================== Lifecycle ==================== */

    /** DELETE /api/agency/documents/{id} — move to trash (soft). */
    public function destroy(Request $request, string $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'documents.delete');

        $document = $this->findScoped($agencyId, $id);

        $document->status = 'trashed';
        $document->deleted_by = $request->user()->id;
        $document->save();
        $document->delete(); // sets deleted_at (SoftDeletes)

        $this->logAccess($document, $agencyId, $request, 'delete');
        $this->writeAudit($request, 'document.delete', $document->id, "Moved to trash: {$document->title}", [
            'agency_id' => $agencyId,
            'entity_type' => 'document',
            'entity_id' => $document->id,
        ]);

        return response()->json(['success' => true, 'message' => 'Document moved to trash.']);
    }

    public function restore(Request $request, string $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'documents.restore');

        $document = Document::where('agency_id', $agencyId)->withTrashed()->findOrFail($id);

        if (!$document->trashed() || $document->status !== 'trashed') {
            return response()->json([
                'success' => false,
                'message' => 'Only documents in the trash can be restored.',
            ], 422);
        }

        $disk = $document->storage_disk ?: 'documents';
        if (!$document->disk_path || !Storage::disk($disk)->exists($document->disk_path)) {
            return response()->json([
                'success' => false,
                'message' => 'File is no longer available on storage.',
            ], 410);
        }

        // A restore is not a malware approval. Previously this endpoint
        // unconditionally set status=active, bypassing the scan gate for
        // fail-open/infected documents that had been moved to the trash.
        $isClean = $document->scan_status === 'scanned_clean';
        $document->status = $isClean ? 'active' : 'quarantined';
        $document->deleted_by = null;
        $document->restore();

        $this->writeAudit($request, 'document.restore', $document->id, "Restored: {$document->title}", [
            'agency_id' => $agencyId,
            'entity_type' => 'document',
            'entity_id' => $document->id,
            'metadata' => ['restored_status' => $document->status, 'scan_status' => $document->scan_status],
        ]);

        return response()->json([
            'success' => true,
            'message' => $isClean
                ? 'Document restored.'
                : 'Document restored to quarantine; a clean malware rescan is required before download.',
            'status' => $document->status,
            'scan_status' => $document->scan_status,
        ]);
    }

    public function forceDestroy(Request $request, string $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'documents.delete');

        $document = Document::where('agency_id', $agencyId)->withTrashed()->findOrFail($id);

        if (!$document->trashed() || $document->status !== 'trashed') {
            return response()->json([
                'success' => false,
                'message' => 'Move the document to trash first.',
            ], 422);
        }

        try {
            $deleted = DB::transaction(function () use ($agencyId, $id): bool {
                $locked = Document::where('agency_id', $agencyId)
                    ->withTrashed()
                    ->lockForUpdate()
                    ->findOrFail($id);

                if (!$locked->trashed() || $locked->status !== 'trashed') {
                    return false;
                }

                $paths = [];
                if ($locked->disk_path) {
                    $paths[] = [$locked->storage_disk ?: 'documents', $locked->disk_path];
                }
                foreach (DocumentVersion::where('document_id', $locked->id)->get() as $version) {
                    if ($version->disk_path) {
                        $paths[] = [$version->storage_disk ?: 'documents', $version->disk_path];
                    }
                }

                $seen = [];
                foreach ($paths as [$disk, $path]) {
                    $key = $disk . '|' . $path;
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;

                    $storage = Storage::disk($disk);
                    if ($storage->exists($path) && !$storage->delete($path)) {
                        throw new \RuntimeException('A document file could not be removed from private storage.');
                    }
                }

                $locked->forceDelete();

                return true;
            }, 3);
        } catch (\Throwable $exception) {
            Log::warning('Permanent document deletion stopped because private storage cleanup failed', [
                'document_id' => $id,
                'agency_id' => $agencyId,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Private storage could not confirm deletion. The document record was retained; retry after storage is healthy.',
            ], 503);
        }

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Document state changed; refresh before permanently deleting it.',
            ], 409);
        }

        $this->writeAudit($request, 'document.permanent_delete', $document->id, "Permanently deleted: {$document->title}", [
            'agency_id' => $agencyId,
            'entity_type' => 'document',
            'entity_id' => $document->id,
        ]);

        return response()->json(['success' => true, 'message' => 'Document permanently deleted.']);
    }

    public function quarantineIndex(Request $request)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'documents.manage_permissions');

        $held = Document::where('agency_id', $agencyId)
            ->whereIn('status', ['quarantined'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Document $d) => [
                'id' => $d->id,
                'title' => $d->title,
                'category' => $d->category,
                'mime_type' => $d->mime_type,
                'size_bytes' => $d->size_bytes,
                'scan_status' => $d->scan_status,
                'scan_threats' => $d->scan_threats,
                'scan_completed_at' => $d->scan_completed_at,
                'status' => $d->status,
                'created_at' => $d->created_at,
                'source' => 'vault_hold',
            ]);

        $rejected = DocumentQuarantine::where('agency_id', $agencyId)
            ->with('uploader:id,name')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (DocumentQuarantine $q) => [
                'id' => $q->id,
                'title' => $q->original_name,
                'mime_type' => $q->mime_type,
                'size_bytes' => $q->size_bytes,
                'reason' => $q->reason,
                'scan_threats' => $q->threats,
                'created_at' => $q->created_at,
                'uploaded_by_name' => $q->uploader?->name,
                'document_id' => $q->document_id,
                'source' => 'rejected',
            ]);

        return response()->json([
            'success' => true,
            'quarantined' => $held->values()->all(),
            'rejected' => $rejected->all(),
        ]);
    }

    /**
     * POST /api/agency/documents/{id}/rescan — rerun the malware scan for a
     * held/rejected document. Requires documents.manage_permissions.
     * clean â†’ promotes the document back to active (moving bytes off the
     * quarantine disk when needed); infected â†’ stays quarantined with the
     * recorded threats; scanner unavailable â†’ stays held (fail-closed).
     */
    public function rescan(Request $request, string $id, MalwareScanner $scanner)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'documents.manage_permissions');

        $document = $this->findScoped($agencyId, $id);
        if ($document->status !== 'quarantined'
            || !in_array($document->scan_status, ['skipped', 'infected'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only quarantined documents with a pending or infected scan verdict can be rescanned.',
            ], 422);
        }

        $sourceDisk = $document->storage_disk ?: 'documents';
        $sourcePath = $document->disk_path;
        if (!in_array($sourceDisk, ['documents', 'quarantine'], true)
            || !$sourcePath
            || !Storage::disk($sourceDisk)->exists($sourcePath)) {
            return response()->json([
                'success' => false,
                'message' => 'File is no longer available on private storage.',
            ], 410);
        }

        try {
            $checksumBeforeScan = SecureFileStore::checksum($sourceDisk, $sourcePath);
        } catch (\Throwable $exception) {
            Log::warning('Rescan could not verify the quarantined file before scanning', [
                'document_id' => $document->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Private file integrity could not be verified; the document stays in quarantine.',
            ], 422);
        }

        $scan = SecureFileStore::scanStored($sourceDisk, $sourcePath, $scanner);
        $scannerUnavailable = $scan->skipped || in_array('scan_unavailable', $scan->threats, true);
        if ($scannerUnavailable) {
            Log::warning('Rescan skipped: scanner unavailable', [
                'document_id' => $document->id,
                'reason' => $scan->skipReason ?? 'scan_unavailable',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Malware scanner unavailable; the document stays in quarantine.',
            ], 422);
        }

        $scanClean = $scan->isClean;
        $sourceChecksum = SecureFileStore::checksum($sourceDisk, $sourcePath);
        if (!hash_equals($checksumBeforeScan, $sourceChecksum)) {
            return response()->json([
                'success' => false,
                'message' => 'The document bytes changed during scanning; retry the rescan.',
            ], 409);
        }
        $targetDisk = $sourceDisk;
        $targetPath = $sourcePath;
        $quarantineId = null;

        if ($scanClean && $sourceDisk === 'quarantine') {
            $extension = pathinfo($document->stored_name ?: $document->original_name, PATHINFO_EXTENSION) ?: 'bin';
            $targetDisk = 'documents';
            $targetPath = $agencyId . '/' . now()->format('Y/m') . '/'
                . $document->id . '_rescan_' . bin2hex(random_bytes(6)) . '.' . $extension;
        } elseif (!$scanClean && $sourceDisk === 'documents') {
            $quarantineId = 'qtn_' . bin2hex(random_bytes(8));
            $targetDisk = 'quarantine';
            $targetPath = $agencyId . '/' . now()->format('Y/m') . '/'
                . $quarantineId . '_' . ($document->stored_name ?: basename($sourcePath));
        }

        $copied = $targetDisk !== $sourceDisk || $targetPath !== $sourcePath;

        try {
            $updated = DB::transaction(function () use (
                $agencyId,
                $id,
                $sourceDisk,
                $sourcePath,
                $sourceChecksum,
                $targetDisk,
                $targetPath,
                $quarantineId,
                $scan,
                $scanClean,
                $copied,
                $request
            ): Document {
                $locked = Document::where('agency_id', $agencyId)
                    ->lockForUpdate()
                    ->findOrFail($id);

                if ($locked->status !== 'quarantined'
                    || !in_array($locked->scan_status, ['skipped', 'infected'], true)
                    || ($locked->storage_disk ?: 'documents') !== $sourceDisk
                    || $locked->disk_path !== $sourcePath) {
                    abort(409, 'The document changed while the rescan was running. Refresh and retry.');
                }

                // Verify the bytes have not changed since the scanner verdict.
                if (SecureFileStore::checksum($sourceDisk, $sourcePath) !== $sourceChecksum) {
                    abort(409, 'The document bytes changed during rescan. A new scan is required.');
                }

                // Keep the source intact until metadata commits. If the DB
                // transaction fails, the copied target is removed in catch.
                if ($copied) {
                    SecureFileStore::copy($sourceDisk, $sourcePath, $targetDisk, $targetPath);
                }

                if (!$scanClean && $quarantineId !== null) {
                    DocumentQuarantine::create([
                        'id' => $quarantineId,
                        'agency_id' => $agencyId,
                        'document_id' => $locked->id,
                        'original_name' => $locked->original_name,
                        'stored_name' => $locked->stored_name,
                        'disk_path' => $targetPath,
                        'mime_type' => $locked->mime_type,
                        'size_bytes' => $locked->size_bytes,
                        'reason' => 'infected',
                        'threats' => $scan->threats,
                        'uploaded_by' => $locked->uploaded_by,
                    ]);
                }

                $locked->disk_path = $targetPath;
                $locked->storage_disk = $targetDisk;
                $locked->scan_status = $scanClean ? 'scanned_clean' : 'infected';
                $locked->scan_threats = $scanClean ? null : $scan->threats;
                $locked->scan_completed_at = now();
                $locked->scanner = $scan->scanner;
                $locked->checksum = SecureFileStore::checksum($targetDisk, $targetPath);
                $locked->status = $scanClean ? 'active' : 'quarantined';
                $locked->save();

                // The current version row must follow the same physical bytes;
                // otherwise history and future vault key rotations point to a
                // path that was moved or deleted.
                DocumentVersion::where('document_id', $locked->id)
                    ->where('version', $locked->version)
                    ->update([
                        'disk_path' => $targetPath,
                        'storage_disk' => $targetDisk,
                        'checksum' => $locked->checksum,
                    ]);

                return $locked->fresh();
            }, 3);
        } catch (\Throwable $exception) {
            if ($copied) {
                try {
                    if (Storage::disk($targetDisk)->exists($targetPath)
                        && !Storage::disk($targetDisk)->delete($targetPath)) {
                        Log::critical('Failed to clean an uncommitted rescan copy', [
                            'document_id' => $id,
                            'target_disk' => $targetDisk,
                            'target_path_len' => strlen($targetPath),
                        ]);
                    }
                } catch (\Throwable $cleanupException) {
                    Log::critical('Rescan copy cleanup raised an exception', [
                        'document_id' => $id,
                        'error' => $cleanupException->getMessage(),
                    ]);
                }
            }

            throw $exception;
        }

        if ($copied) {
            try {
                if (Storage::disk($sourceDisk)->exists($sourcePath)
                    && !Storage::disk($sourceDisk)->delete($sourcePath)) {
                    // The DB now points at the verified target; a failed
                    // source cleanup is an orphan, not a broken document.
                    Log::critical('Rescan committed but old private bytes could not be removed', [
                        'document_id' => $id,
                        'source_disk' => $sourceDisk,
                        'source_path_len' => strlen($sourcePath),
                    ]);
                }
            } catch (\Throwable $cleanupException) {
                Log::critical('Old rescan source cleanup raised an exception', [
                    'document_id' => $id,
                    'error' => $cleanupException->getMessage(),
                ]);
            }
        }

        $this->writeAudit(
            $request,
            'document.rescan',
            $updated->id,
            $scanClean ? "Rescan approved: {$updated->title}" : "Rescan detected malware: {$updated->title}",
            [
                'agency_id' => $agencyId,
                'entity_type' => 'document',
                'entity_id' => $updated->id,
                'metadata' => [
                    'scan_status' => $updated->scan_status,
                    'source_disk' => $sourceDisk,
                    'target_disk' => $targetDisk,
                    'threats' => $scanClean ? [] : $scan->threats,
                ],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => $scanClean
                ? 'Rescan clean; document approved for download.'
                : 'Rescan detected malware; the document stays in quarantine.',
            'document' => $updated,
        ]);
    }

    public function usage(Request $request)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'documents.view');

        $agency = Agency::findOrFail($agencyId);
        $usage = PlanLimits::usage($agency);

        return response()->json([
            'success' => true,
            'storage_bytes' => $usage['storage_bytes'],
            'unlimited' => true,
            'monitoring_only' => true,
            'usage' => $usage,
        ]);
    }

    /* ==================== Access logs ==================== */

    public function accessLogs(Request $request, string $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'documents.manage_permissions');

        $document = $this->findScoped($agencyId, $id);
        if ($document->is_sensitive) {
            $this->requirePermission($request, 'documents.view_sensitive');
        }

        $logs = DocumentAccessLog::where('document_id', $document->id)
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'logs' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    public function activity(Request $request)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'documents.view');

        $query = DocumentAccessLog::where('agency_id', $agencyId);
        if (!$this->can($request, 'documents.view_sensitive')) {
            $query->whereHas('document', fn ($documentQuery) => $documentQuery->where('is_sensitive', false));
        }

        $logs = $query->with(['user:id,name,email', 'document:id,title,category'])
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->get('per_page', 30), 100));

        return response()->json([
            'success' => true,
            'activity' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    /* ==================== Helpers ==================== */

    /** Agency-scoped lookup: a foreign id is a 404, never a 403. */
    /* ==================== G-10: expiring share links ==================== */

    /**
     * GET /api/agency/documents/{id}/shares — list the document's share links
     * (metadata only; the hashed token is never exposed).
     */
    public function sharesIndex(Request $request, string $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'documents.view');

        $document = $this->findScoped($agencyId, $id);
        if ($document->is_sensitive) {
            $this->requirePermission($request, 'documents.view_sensitive');
        }

        $shares = DocumentShare::where('document_id', $document->id)
            ->with('creator:id,name')
            ->orderByDesc('created_at')
            ->get(['id', 'note', 'expires_at', 'max_downloads', 'download_count',
                'last_downloaded_at', 'revoked_at', 'created_by', 'created_at']);

        return response()->json([
            'success' => true,
            'shares' => $shares->map(fn (DocumentShare $share) => [
                'id' => $share->id,
                'note' => $share->note,
                'expires_at' => $share->expires_at?->toIso8601String(),
                'max_downloads' => $share->max_downloads,
                'download_count' => $share->download_count,
                'last_downloaded_at' => $share->last_downloaded_at?->toIso8601String(),
                'revoked_at' => $share->revoked_at?->toIso8601String(),
                'created_at' => $share->created_at?->toIso8601String(),
                'created_by_name' => $share->creator?->name,
                'is_active' => $share->isActive(),
            ]),
        ]);
    }

    /**
     * POST /api/agency/documents/{id}/shares — mint a public, expiring,
     * download-capped link. The plain token is returned ONCE; only its
     * SHA-256 hash is stored. Requires the "manage document sharing"
     * permission — downloaders must not be able to mint public links.
     */
    public function sharesStore(Request $request, string $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'documents.manage_permissions');

        $document = $this->findScoped($agencyId, $id);

        if ($document->is_sensitive) {
            $this->requirePermission($request, 'documents.view_sensitive');
        }

        if ($document->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Shares cannot be created for trashed documents.',
            ], 422);
        }

        // A share link is a public capability — it must never point at bytes
        // that have not cleared malware verification.
        if ($document->scan_status !== 'scanned_clean') {
            return response()->json([
                'success' => false,
                'message' => 'Shares cannot be created for documents that have not cleared malware verification.',
            ], 422);
        }

        $data = $request->validate([
            'expires_in_days' => 'nullable|integer|min:1|max:90',
            'max_downloads' => 'nullable|integer|min:1|max:10000',
            'note' => 'nullable|string|max:255',
        ]);

        [$share, $plainToken] = DocumentShare::issue($document, $agencyId, $request->user()?->id, [
            'expires_at' => now()->addDays((int) ($data['expires_in_days'] ?? 7)),
            'max_downloads' => $data['max_downloads'] ?? null,
            'note' => isset($data['note']) ? strip_tags($data['note']) : null,
        ]);

        $this->writeAudit($request, 'document.share_created', $document->id,
            "Share link created for: {$document->title}", [
                'agency_id' => $agencyId,
                'entity_type' => 'document_share',
                'entity_id' => $share->id,
                'metadata' => ['expires_at' => $share->expires_at->toIso8601String()],
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Share link created. Copy the token now — it is not shown again.',
            'share' => [
                'id' => $share->id,
                'note' => $share->note,
                'expires_at' => $share->expires_at->toIso8601String(),
                'max_downloads' => $share->max_downloads,
            ],
            'token' => $plainToken,
        ], 201);
    }

    /**
     * DELETE /api/agency/documents/{id}/shares/{shareId} — revoke a share
     * link (idempotent; the token stops working immediately).
     */
    public function sharesRevoke(Request $request, string $id, string $shareId)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'documents.manage_permissions');

        $document = $this->findScoped($agencyId, $id);

        $share = DocumentShare::where('document_id', $document->id)
            ->where('id', $shareId)
            ->first();

        if (!$share) {
            return response()->json(['success' => false, 'message' => 'Share link not found.'], 404);
        }

        if ($share->revoked_at === null) {
            $share->forceFill(['revoked_at' => now()])->save();

            $this->writeAudit($request, 'document.share_revoked', $document->id,
                "Share link revoked for: {$document->title}", [
                    'agency_id' => $agencyId,
                    'entity_type' => 'document_share',
                    'entity_id' => $share->id,
                ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Share link revoked.',
        ]);
    }

    /**
     * GET /api/shares/{token}/download — PUBLIC endpoint (no auth). The URL
     * token is hashed and matched against the stored hash; expired, revoked
     * and download-exhausted links are rejected. The download count is
     * incremented under a row lock so concurrent use cannot overshoot the cap.
     */
    public function sharedDownload(Request $request, string $token)
    {
        if (!preg_match('/^[A-Za-z0-9]{20,128}$/', $token)) {
            return response()->json(['success' => false, 'message' => 'Invalid share link.'], 404);
        }

        $share = DocumentShare::where('token_hash', hash('sha256', $token))
            ->with('document')
            ->first();

        if (!$share) {
            return response()->json(['success' => false, 'message' => 'This share link is no longer available.'], 410);
        }

        $document = $share->document;

        // Pre-checks BEFORE consuming a download counter. Missing files, failed
        // authorization, incomplete malware scans and revoked/expired links must
        // never count as a successful download.
        if ($document === null || $document->status !== 'active') {
            return response()->json(['success' => false, 'message' => 'This document is no longer available.'], 410);
        }

        if ($document->scan_status !== 'scanned_clean') {
            return response()->json([
                'success' => false,
                'message' => 'This document has not cleared malware verification yet.',
            ], 423);
        }

        if (!Storage::disk('documents')->exists($document->disk_path)) {
            Log::warning('Shared document file missing on disk', ['document_id' => $document->id]);
            return response()->json(['success' => false, 'message' => 'File is no longer available on storage.'], 410);
        }

        // Counter increment + availability check must be atomic under a row
        // lock so concurrent downloads cannot overshoot max_downloads.
        $result = DB::transaction(function () use ($share, $token): array {
            $locked = DocumentShare::where('id', $share->id)
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if (!$locked) {
                return ['share' => null, 'reason' => 'not_found'];
            }

            if ($locked->revoked_at !== null || !$locked->expires_at->isFuture()) {
                return ['share' => null, 'reason' => 'expired_or_revoked'];
            }

            if ($locked->max_downloads !== null && $locked->download_count >= $locked->max_downloads) {
                return ['share' => null, 'reason' => 'downloads_exhausted'];
            }

            // Atomic increment — no other transaction can overshoot the cap.
            $locked->forceFill([
                'download_count' => $locked->download_count + 1,
                'last_downloaded_at' => now(),
            ])->save();

            return ['share' => $locked, 'reason' => null];
        });

        if ($result['share'] === null) {
            return response()->json([
                'success' => false,
                'message' => 'This share link is no longer available.',
            ], 410);
        }

        $lockedShare = $result['share'];

        $this->logAccess($document, $lockedShare->agency_id, $request, 'share_download');

        $this->writeAudit($request, 'document.share_download', $document->id,
            'Downloaded via share link: ' . $document->title, [
                'agency_id' => $lockedShare->agency_id,
                'entity_type' => 'document_share',
                'entity_id' => $lockedShare->id,
                'metadata' => ['download_count' => $lockedShare->download_count],
            ]);

        return SecureFileStore::response(
            $document->storage_disk ?: 'documents',
            $document->disk_path,
            $document->original_name ?: $document->stored_name,
            $document->mime_type,
            'attachment'
        );
    }

    /**
     * Categories that always require sensitive-document clearance. Admin
     * settings may add categories, but cannot silently downgrade these
     * default identity/ownership document types to ordinary files.
     */
    private function sensitiveCategories(): array
    {
        $defaults = ['cnic', 'registry', 'ownership', 'sale_agreement', 'transfer_letter', 'allotment_letter'];
        $configured = SystemSetting::get('document.sensitive_categories', []);

        return array_values(array_unique(array_merge(
            $defaults,
            is_array($configured) ? array_filter($configured, 'is_string') : []
        )));
    }

    private function findScoped(string $agencyId, string $id): Document
    {
        $document = Document::where('agency_id', $agencyId)->where('id', $id)->first();

        if (!$document) {
            // Distinguish trashed for restore flows handled with withTrashed
            abort(404, 'Document not found.');
        }

        return $document;
    }

    private function logAccess(Document $document, string $agencyId, Request $request, string $action): void
    {
        try {
            DocumentAccessLog::create([
                'document_id' => $document->id,
                'agency_id' => $agencyId,
                'user_id' => $request->user()?->id,
                'action' => $action,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Document access log write failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Isolate a REJECTED upload (infected / scanner-unavailable) on the
     * quarantine disk and record it for administrator visibility. The bytes
     * are encrypted at rest like every other vault write, are never
     * web-served, and are purged by `vault:purge-quarantine`.
     */
    private function quarantineRejected(
        Request $request,
        string $agencyId,
        $file,
        string $ext,
        string $reason,
        array $threats,
        ?string $documentId = null
    ): void {
        $qPath = null;
        $recordCreated = false;

        try {
            $qId = 'qtn_' . bin2hex(random_bytes(8));
            $qPath = $agencyId . '/' . now()->format('Y/m') . '/' . $qId . '.' . $ext;
            $originalName = UploadSecurity::sanitizeOriginalName($file->getClientOriginalName());

            SecureFileStore::storeUpload('quarantine', $qPath, $file);

            DocumentQuarantine::create([
                'id' => $qId,
                'agency_id' => $agencyId,
                'document_id' => $documentId,
                'original_name' => $originalName,
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
            $recordCreated = true;

            $this->writeAudit($request, 'document.quarantined', $qId,
                "Upload quarantined ({$reason}): {$originalName}", [
                    'agency_id' => $agencyId,
                    'entity_type' => 'document_quarantine',
                    'entity_id' => $qId,
                    'metadata' => ['reason' => $reason, 'threats' => $threats, 'document_id' => $documentId],
                ]);
        } catch (\Throwable $e) {
            if (! $recordCreated && is_string($qPath) && $qPath !== '') {
                try {
                    if (\Illuminate\Support\Facades\Storage::disk('quarantine')->exists($qPath)
                        && ! \Illuminate\Support\Facades\Storage::disk('quarantine')->delete($qPath)) {
                        Log::critical('Rejected document quarantine bytes could not be cleaned after metadata failure', [
                            'path_len' => strlen($qPath),
                        ]);
                    }
                } catch (\Throwable $cleanupException) {
                    Log::critical('Rejected document quarantine cleanup raised an exception', [
                        'path_len' => strlen($qPath),
                        'error' => $cleanupException->getMessage(),
                    ]);
                }
            }
            Log::warning('Quarantine write failed', ['error' => $e->getMessage()]);
        }
    }
}
