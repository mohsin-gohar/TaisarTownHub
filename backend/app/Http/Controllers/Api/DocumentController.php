<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentAccessLog;
use App\Models\DocumentFolder;
use App\Models\DocumentVersion;
use App\Models\Property;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
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
    public function store(Request $request)
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

        $category = $request->get('category', 'other');
        $sensitiveCategories = SystemSetting::get('document.sensitive_categories', [
            'cnic', 'registry', 'ownership', 'sale_agreement', 'transfer_letter', 'allotment_letter',
        ]);
        $isSensitive = in_array($category, $sensitiveCategories, true);

        if ($isSensitive) {
            $this->requirePermission($request, 'documents.view_sensitive');
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
}
