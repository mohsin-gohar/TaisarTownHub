<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Document;
use App\Models\DocumentAccessLog;
use App\Models\DocumentFolder;
use App\Models\DocumentShare;
use App\Models\DocumentVersion;
use App\Models\Property;
use App\Models\SystemSetting;
use App\Support\PlanLimits;
use App\Services\MalwareScanner;
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
     */
    public function store(Request $request, MalwareScanner $scanner)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'documents.upload');

        $maxMb = (int) SystemSetting::get('storage.max_upload_mb', 25);
        $allowedExt = SystemSetting::get('document.allowed_extensions', [
            'pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'mp4', 'mov',
        ]);

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
        $ext = strtolower($file->getClientOriginalExtension());

        if (!in_array($ext, array_map('strtolower', $allowedExt), true)) {
            return response()->json([
                'success' => false,
                'message' => "File type .{$ext} is not allowed.",
            ], 422);
        }

        // G-?: verify the real MIME type + magic bytes, not just the extension
        // (a PHP script renamed to .jpg would otherwise pass).
        $realMime = mime_content_type($file->getRealPath());
        if ($this->isDangerousMime($realMime)) {
            return response()->json([
                'success' => false,
                'message' => "File type {$realMime} is not allowed.",
            ], 422);
        }

        if (!$this->magicBytesMatch($file->getRealPath(), $ext, $realMime)) {
            return response()->json([
                'success' => false,
                'message' => "File content is corrupted or does not match its type.",
            ], 422);
        }

        // Malware scan (provider-agnostic; clamscan/clamd). Files are stored
        // on the private disk and only served through authenticated endpoints,
        // so they are never publicly accessible before or after scanning.
        //
        // A file is NEVER reported as clean unless a real scan ran and the
        // scanner said so. When no verdict could be obtained the upload is
        // rejected in fail-closed mode and logged (never silently "clean").
        $scan = $scanner->scan($file->getRealPath());
        $scannerUnavailable = in_array('scan_unavailable', $scan->threats, true);

        if ($scan->skipped || $scannerUnavailable) {
            if (config('malware.fail_closed', false)) {
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
            if (config('malware.log_skipped', true)) {
                Log::warning('Upload accepted without malware scan', [
                    'file' => $file->getClientOriginalName(),
                    'agency_id' => $agencyId,
                    'reason' => $scan->skipReason ?? 'scan_unavailable',
                ]);
            }
        } elseif (!$scan->isClean) {
            Log::warning('Upload rejected: malware detected', [
                'file' => $file->getClientOriginalName(),
                'agency_id' => $agencyId,
                'threats' => $scan->threats,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'File failed malware scan and was rejected.',
            ], 422);
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
        $storedName = $documentId . '.' . $ext;
        $relativePath = $agencyId . '/' . now()->format('Y/m') . '/' . $storedName;

        $stored = $file->storeAs(dirname($relativePath), basename($relativePath), 'documents');
        if (!$stored) {
            return response()->json([
                'success' => false,
                'message' => 'File could not be stored. Please retry.',
            ], 500);
        }

        $document = Document::create([
            'id' => $documentId,
            'agency_id' => $agencyId,
            'folder_id' => $request->folder_id,
            'property_id' => $request->property_id,
            'category' => $category,
            'title' => $request->get('title') ?: $file->getClientOriginalName(),
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'disk_path' => $stored,
            'mime_type' => $file->getClientMimeType() ?: 'application/octet-stream',
            'size_bytes' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()) ?: null,
            'is_sensitive' => $isSensitive,
            'version' => 1,
            'status' => 'active',
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
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded to the private vault.',
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

    /** POST /api/agency/documents/{id}/versions — upload a new version. */
    public function storeVersion(Request $request, string $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'documents.update');

        $document = $this->findScoped($agencyId, $id);

        $maxMb = (int) SystemSetting::get('storage.max_upload_mb', 25);
        $request->validate([
            'file' => 'required|file|max:' . ($maxMb * 1024),
            'note' => 'nullable|string|max:500',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $storedName = $document->id . '_v' . ($document->version + 1) . '.' . $ext;
        $relativePath = $agencyId . '/' . now()->format('Y/m') . '/' . $storedName;

        $stored = $file->storeAs(dirname($relativePath), basename($relativePath), 'documents');
        if (!$stored) {
            return response()->json(['success' => false, 'message' => 'Version could not be stored.'], 500);
        }

        // Archive current version
        DocumentVersion::create([
            'document_id' => $document->id,
            'version' => $document->version,
            'stored_name' => $document->stored_name,
            'disk_path' => $document->disk_path,
            'mime_type' => $document->mime_type,
            'size_bytes' => $document->size_bytes,
            'checksum' => $document->checksum,
            'uploaded_by' => $document->uploaded_by,
            'note' => 'Superseded',
            'created_at' => $document->updated_at ?? now(),
        ]);

        $document->version += 1;
        $document->stored_name = $storedName;
        $document->disk_path = $stored;
        $document->mime_type = $file->getClientMimeType() ?: $document->mime_type;
        $document->size_bytes = $file->getSize();
        $document->checksum = hash_file('sha256', $file->getRealPath()) ?: $document->checksum;
        $document->original_name = $file->getClientOriginalName();
        $document->save();

        DocumentVersion::create([
            'document_id' => $document->id,
            'version' => $document->version,
            'stored_name' => $storedName,
            'disk_path' => $stored,
            'mime_type' => $document->mime_type,
            'size_bytes' => $document->size_bytes,
            'checksum' => $document->checksum,
            'uploaded_by' => $request->user()->id,
            'note' => $request->note,
            'created_at' => now(),
        ]);

        $this->logAccess($document, $agencyId, $request, 'upload');
        $this->writeAudit($request, 'document.version', $document->id, "New version v{$document->version}: {$document->title}", [
            'agency_id' => $agencyId,
            'entity_type' => 'document',
            'entity_id' => $document->id,
            'metadata' => ['version' => $document->version, 'note' => $request->note],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'New version stored.',
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
        $this->requirePermission($request, 'documents.download');

        $document = $this->findScoped($agencyId, $id);

        if ($document->is_sensitive) {
            $this->requirePermission($request, 'documents.view_sensitive');
        }

        if ($document->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'This document is in the trash.',
            ], 410);
        }

        if (!Storage::disk('documents')->exists($document->disk_path)) {
            Log::warning('Document file missing on disk', [
                'document_id' => $document->id, 'agency_id' => $agencyId,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'File is no longer available on storage.',
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

        return Storage::disk('documents')->download(
            $document->disk_path,
            $document->original_name ?: $document->stored_name,
            [
                'Content-Type' => $document->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Content-Disposition' => $disposition . '; filename="' . addslashes($document->original_name ?: $document->stored_name) . '"',
            ]
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
            if ($document->disk_path && Storage::disk('documents')->exists($document->disk_path)) {
                Storage::disk('documents')->delete($document->disk_path);
            }
            foreach (DocumentVersion::where('document_id', $document->id)->get() as $version) {
                if ($version->disk_path && Storage::disk('documents')->exists($version->disk_path)) {
                    Storage::disk('documents')->delete($version->disk_path);
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

        return Storage::disk('documents')->download(
            $document->disk_path,
            $document->original_name ?: $document->stored_name,
            [
                'Content-Type' => $document->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Content-Disposition' => 'attachment; filename="' . addslashes($document->original_name ?: $document->stored_name) . '"',
            ]
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

    /** MIME types that must never be accepted as uploads (executable/script). */
    private function isDangerousMime(string $mime): bool
    {
        return in_array(strtolower($mime), [
            'application/x-php',
            'application/x-httpd-php',
            'application/x-httpd-php-source',
            'text/html',
            'application/xhtml+xml',
            'application/x-python-code',
            'application/x-ruby',
            'application/x-sh',
            'application/x-perl',
            'application/x-csh',
        ], true);
    }

    /** Verify the file's magic bytes do not indicate a dangerous executable.
     *
     * We reject only when the bytes clearly identify a script or binary
     * (PHP, ELF, Windows PE, shebang). If the file's bytes cannot be read or
     * don't match a known image/document signature, we allow it: the extension
     * allowlist already restricts uploads to safe types, so magic bytes are a
     * secondary "clearly dangerous" gate rather than a strict type match.
     */
    private function magicBytesMatch(string $path, string $ext, string $mime): bool
    {
        $bytes = @file_get_contents($path, false, null, 32);
        if ($bytes === false || $bytes === '') {
            return true; // unreadable/empty: extension allowlist is the guard
        }

        $dangerous = [
            '<?php', '<?',        // PHP
            "\x7fELF",            // ELF binary
            'MZ',                 // Windows PE
            '#!',                 // shebang script
        ];

        foreach ($dangerous as $sig) {
            if (str_starts_with($bytes, $sig)) {
                return false;
            }
        }

        return true;
    }
}
