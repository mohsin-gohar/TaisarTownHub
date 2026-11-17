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
        $scanHold = false; // fail-open: stored but quarantined (not downloadable)

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
            $scanHold = true;
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

        $category = $request->get('category', 'other');
        $sensitiveCategories = SystemSetting::get('document.sensitive_categories', [
            'cnic', 'registry', 'ownership', 'sale_agreement', 'transfer_letter', 'allotment_letter',
        ]);
        $isSensitive = in_array($category, $sensitiveCategories, true);

        if ($isSensitive) {
            $this->requirePermission($request, 'documents.view_sensitive');
        }

        // G-8: plan limit — vault storage quota is enforced server-side.
        $agency = Agency::findOrFail($agencyId);
        if ($block = PlanLimits::storageBlock($agency, (int) $file->getSize())) {
            return PlanLimits::deny('Storage limit reached for your plan. Delete unneeded documents or upgrade your plan.', $block);
        }

        if ($request->folder_id) {
            DocumentFolder::where('agency_id', $agencyId)->where('id', $request->folder_id)->firstOrFail();
        }
        if ($request->property_id) {
            Property::where('agency_id', $agencyId)->where('id', $request->property_id)->firstOrFail();
        }

        $documentId = 'doc_' . bin2hex(random_bytes(8));
        $storedName = $documentId . '.' . $check['ext'];
        $relativePath = $agencyId . '/' . now()->format('Y/m') . '/' . $storedName;
        $originalName = UploadSecurity::sanitizeOriginalName($file->getClientOriginalName());

        // Bytes are written through the SecureFileStore — encrypted at rest,
        // unpredictable path, never web-served. A fail-open hold stays on the
        // vault disk but the row is quarantined so downloads stay blocked.
        SecureFileStore::storeUpload('documents', $relativePath, $file);

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
            $sensitiveCategories = SystemSetting::get('document.sensitive_categories', []);
            $wasSensitive = $document->is_sensitive;
            $document->is_sensitive = in_array($request->category, $sensitiveCategories, true);
            if ($document->is_sensitive && !$wasSensitive) {
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

        if ($document->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Versions can only be uploaded for active documents.',
            ], 422);
        }

        $maxMb = (int) SystemSetting::get('storage.max_upload_mb', 25);
        $request->validate([
            'file' => 'required|file|max:' . ($maxMb * 1024),
            'note' => 'nullable|string|max:500',
        ]);

        $file = $request->file('file');

        // Same content-security pipeline as the first upload — a version must
        // never be a validation bypass.
        $check = UploadSecurity::evaluate($file, UploadSecurity::allowedExtensions());
        if ($check['error'] !== null) {
            return $check['error'];
        }

        $scan = $scanner->scan($file->getRealPath());
        $scannerUnavailable = $scan->skipped || in_array('scan_unavailable', $scan->threats, true);

        if ($scannerUnavailable) {
            if (config('malware.fail_closed', false)) {
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

            if (config('malware.log_skipped', true)) {
                Log::warning('Version accepted without malware scan (quarantined)', [
                    'document_id' => $document->id,
                    'agency_id' => $agencyId,
                ]);
            }
        } elseif (!$scan->isClean) {
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
        $storedName = $document->id . '_v' . ($document->version + 1) . '.' . $check['ext'];
        $relativePath = $agencyId . '/' . now()->format('Y/m') . '/' . $storedName;

        // Quota: a new version adds its bytes on top of every stored version.
        $agency = Agency::findOrFail($agencyId);
        if ($block = PlanLimits::storageBlock($agency, (int) $file->getSize())) {
            return PlanLimits::deny('Storage limit reached for your plan. Delete unneeded documents or upgrade your plan.', $block);
        }

        // Write the bytes first (encrypted at rest); only then flip the DB
        // pointers inside a transaction so the document never points at
        // bytes that were not stored.
        SecureFileStore::storeUpload('documents', $relativePath, $file);

        $previous = [
            'version' => $document->version,
            'stored_name' => $document->stored_name,
            'disk_path' => $document->disk_path,
            'storage_disk' => $document->storage_disk ?? 'documents',
            'mime_type' => $document->mime_type,
            'size_bytes' => $document->size_bytes,
            'checksum' => $document->checksum,
            'uploaded_by' => $document->uploaded_by,
            'created_at' => $document->updated_at ?? now(),
        ];

        DB::transaction(function () use ($document, $previous, $relativePath, $storedName, $check, $file, $scan, $scanClean, $request) {
            // Archive the current version.
            DocumentVersion::create([
                'document_id' => $document->id,
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

            $document->version += 1;
            $document->stored_name = $storedName;
            $document->disk_path = $relativePath;
            $document->storage_disk = 'documents';
            $document->mime_type = $check['mime'] ?: $document->mime_type;
            $document->size_bytes = (int) $file->getSize();
            $document->checksum = hash_file('sha256', $file->getRealPath()) ?: $document->checksum;
            $document->original_name = UploadSecurity::sanitizeOriginalName($file->getClientOriginalName());
            $document->scan_status = $scanClean ? 'scanned_clean' : 'skipped';
            $document->scan_threats = $scanClean ? null : [$scan->skipReason ?? 'scan_unavailable'];
            $document->scan_completed_at = $scanClean ? now() : null;
            $document->scanner = $scanClean ? $scan->scanner : null;
            $document->status = $scanClean ? 'active' : 'quarantined';
            $document->save();

            DocumentVersion::create([
                'document_id' => $document->id,
                'version' => $document->version,
                'stored_name' => $storedName,
                'disk_path' => $relativePath,
                'storage_disk' => 'documents',
                'mime_type' => $document->mime_type,
                'size_bytes' => $document->size_bytes,
                'checksum' => $document->checksum,
                'uploaded_by' => $request->user()->id,
                'note' => $request->note,
                'created_at' => now(),
            ]);
        });

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

        $document->status = 'active';
        $document->deleted_by = null;
        $document->save();
        $document->restore();

        $this->writeAudit($request, 'document.restore', $document->id, "Restored: {$document->title}", [
            'agency_id' => $agencyId,
            'entity_type' => 'document',
            'entity_id' => $document->id,
        ]);

        return response()->json(['success' => true, 'message' => 'Document restored.']);
    }

    /** DELETE /api/agency/documents/{id}/permanent — erase bytes + row (trashed only). */
    public function forceDestroy(Request $request, string $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'documents.delete');

        $document = Document::where('agency_id', $agencyId)->withTrashed()->findOrFail($id);

        if ($document->status !== 'trashed') {
            return response()->json([
                'success' => false,
                'message' => 'Move the document to trash first.',
            ], 422);
        }

        try {
            if ($document->disk_path && Storage::disk($document->storage_disk ?: 'documents')->exists($document->disk_path)) {
                Storage::disk($document->storage_disk ?: 'documents')->delete($document->disk_path);
            }
            foreach (DocumentVersion::where('document_id', $document->id)->get() as $version) {
                if ($version->disk_path && Storage::disk($version->storage_disk ?: 'documents')->exists($version->disk_path)) {
                    Storage::disk($version->storage_disk ?: 'documents')->delete($version->disk_path);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Document file deletion failed', ['error' => $e->getMessage()]);
        }

        $this->writeAudit($request, 'document.permanent_delete', $document->id, "Permanently deleted: {$document->title}", [
            'agency_id' => $agencyId,
            'entity_type' => 'document',
            'entity_id' => $document->id,
        ]);

        $document->forceDelete();

        return response()->json(['success' => true, 'message' => 'Document permanently deleted.']);
    }

    /* ==================== Scan lifecycle: quarantine & rescan ==================== */

    /**
     * GET /api/agency/documents-quarantine — administrator visibility into
     * every upload that did NOT clear malware verification:
     *   - vault rows held in quarantine (fail-open, awaiting rescan),
     *   - rejected uploads whose isolated bytes sit on the quarantine disk.
     * Requires documents.manage_permissions. Never exposes disk paths.
     */
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

        if (!in_array($document->scan_status, ['skipped', 'infected'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'This document has no pending scan verdict.',
            ], 422);
        }

        $disk = $document->storage_disk ?: 'documents';
        if (!Storage::disk($disk)->exists($document->disk_path)) {
            return response()->json([
                'success' => false,
                'message' => 'File is no longer available on storage.',
            ], 410);
        }

        $scan = SecureFileStore::scanStored($disk, $document->disk_path, $scanner);

        if ($scan->skipped) {
            Log::warning('Rescan skipped: scanner unavailable', ['document_id' => $document->id]);

            return response()->json([
                'success' => false,
                'message' => 'Malware scanner unavailable; the document stays in quarantine.',
            ], 422);
        }

        if (!$scan->isClean) {
            // Infected on rescan: if the bytes sit on the vault disk, move
            // them to the isolated quarantine disk and record the verdict.
            if ($disk === 'documents') {
                $qId = 'qtn_' . bin2hex(random_bytes(8));
                $qPath = $agencyId . '/' . now()->format('Y/m') . '/' . $qId . '_' . $document->stored_name;
                SecureFileStore::move('documents', $document->disk_path, 'quarantine', $qPath);
                $document->disk_path = $qPath;
                $document->storage_disk = 'quarantine';
                DocumentQuarantine::create([
                    'id' => $qId,
                    'agency_id' => $agencyId,
                    'document_id' => $document->id,
                    'original_name' => $document->original_name,
                    'stored_name' => $document->stored_name,
                    'disk_path' => $qPath,
                    'mime_type' => $document->mime_type,
                    'size_bytes' => $document->size_bytes,
                    'reason' => 'infected',
                    'threats' => $scan->threats,
                    'uploaded_by' => $document->uploaded_by,
                    'created_at' => now(),
                ]);
            }

            $document->scan_status = 'infected';
            $document->scan_threats = $scan->threats;
            $document->scan_completed_at = now();
            $document->scanner = $scan->scanner;
            $document->status = 'quarantined';
            $document->save();

            $this->writeAudit($request, 'document.rescan', $document->id, "Rescan detected malware: {$document->title}", [
                'agency_id' => $agencyId,
                'entity_type' => 'document',
                'entity_id' => $document->id,
                'metadata' => ['scan_status' => 'infected', 'threats' => $scan->threats],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rescan detected malware; the document stays in quarantine.',
                'document' => $document,
            ]);
        }

        // Clean: promote.
        if ($disk === 'quarantine') {
            $newPath = $agencyId . '/' . now()->format('Y/m') . '/' . $document->stored_name;
            SecureFileStore::move('quarantine', $document->disk_path, 'documents', $newPath);
            $document->disk_path = $newPath;
            $document->storage_disk = 'documents';
        }

        $document->scan_status = 'scanned_clean';
        $document->scan_threats = null;
        $document->scan_completed_at = now();
        $document->scanner = $scan->scanner;
        $document->checksum = SecureFileStore::checksum('documents', $document->disk_path);
        $document->status = 'active';
        $document->save();

        // Keep the newest version row in sync with the promoted bytes.
        DocumentVersion::where('document_id', $document->id)
            ->where('version', $document->version)
            ->update(['disk_path' => $document->disk_path, 'storage_disk' => 'documents']);

        $this->writeAudit($request, 'document.rescan', $document->id, "Rescan approved: {$document->title}", [
            'agency_id' => $agencyId,
            'entity_type' => 'document',
            'entity_id' => $document->id,
            'metadata' => ['scan_status' => 'scanned_clean'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rescan clean; document approved for download.',
            'document' => $document,
        ]);
    }

    /**
     * GET /api/agency/documents/storage-usage — server-side usage counters
     * (same source the quota gates read) so the UI can never drift from what
     * the API enforces.
     */
    public function usage(Request $request)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'documents.view');

        $agency = Agency::findOrFail($agencyId);
        $usage = PlanLimits::usage($agency);
        $limitMb = $agency->limits()['storage_mb'] ?? null;

        return response()->json([
            'success' => true,
            'storage_bytes' => $usage['storage_bytes'],
            'storage_mb_limit' => $limitMb !== null && (int) $limitMb > 0 ? (int) $limitMb : null,
            'unlimited' => $limitMb === null || (int) $limitMb <= 0,
            'usage' => $usage,
        ]);
    }

    /* ==================== Access logs ==================== */

    public function accessLogs(Request $request, string $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'documents.manage_permissions');

        $document = $this->findScoped($agencyId, $id);

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

        $logs = DocumentAccessLog::where('agency_id', $agencyId)
            ->with(['user:id,name,email', 'document:id,title,category'])
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

        if (!$share || !$share->isActive()) {
            return response()->json(['success' => false, 'message' => 'This share link is no longer available.'], 410);
        }

        $document = $share->document;

        if (!$document || $document->status !== 'active') {
            return response()->json(['success' => false, 'message' => 'This document is no longer available.'], 410);
        }

        // Lifecycle gate: public share links must never serve a document
        // that has not cleared malware verification.
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

        // Counter increment must be atomic with the availability check.
        DB::transaction(function () use ($share): void {
            $locked = DocumentShare::where('id', $share->id)->lockForUpdate()->first();
            if ($locked !== null) {
                $locked->forceFill([
                    'download_count' => $locked->download_count + 1,
                    'last_downloaded_at' => now(),
                ])->save();
            }
        });

        $this->logAccess($document, $share->agency_id, $request, 'share_download');

        $this->writeAudit($request, 'document.share_download', $document->id,
            'Downloaded via share link: ' . $document->title, [
                'agency_id' => $share->agency_id,
                'entity_type' => 'document_share',
                'entity_id' => $share->id,
                'metadata' => ['download_count' => $share->download_count + 1],
            ]);

        return SecureFileStore::response(
            $document->storage_disk ?: 'documents',
            $document->disk_path,
            $document->original_name ?: $document->stored_name,
            $document->mime_type,
            'attachment'
        );
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

            $this->writeAudit($request, 'document.quarantined', $qId,
                "Upload quarantined ({$reason}): {$originalName}", [
                    'agency_id' => $agencyId,
                    'entity_type' => 'document_quarantine',
                    'entity_id' => $qId,
                    'metadata' => ['reason' => $reason, 'threats' => $threats, 'document_id' => $documentId],
                ]);
        } catch (\Throwable $e) {
            Log::warning('Quarantine write failed', ['error' => $e->getMessage()]);
        }
    }
}
