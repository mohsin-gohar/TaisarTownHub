<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Models\Property;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Agency media studio.
 *
 * Uploaded bytes live on the private `documents` disk; delivery happens only
 * through controller endpoints that check the `visibility` flag:
 *  - public media  → GET /api/media/{id}/file (no auth, public media only)
 *  - private media → GET /api/agency/media/{id}/file (auth + agency scope)
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
            ->paginate(min((int) $request->get('per_page', 24), 100));

        $page->through(function (MediaFile $m) use ($request) {
            $payload = $m->toArray();
            $payload['file_url'] = $m->visibility === 'public'
                ? '/api/media/' . $m->id . '/file'
                : '/api/agency/media/' . $m->id . '/file';
            $payload['can_edit'] = $this->can($request, 'manage_media')
                || $this->can($request, 'upload_media');
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
     */
    public function store(Request $request)
    {
        $agencyId = $this->requireAgency($request);
        $this->requireAny($request, ['manage_media', 'upload_media']);

        $maxMb = (int) SystemSetting::get('storage.max_upload_mb', 25);

        $request->validate([
            'file' => 'nullable|file|max:' . ($maxMb * 1024),
            'url' => 'nullable|url|max:2048',
            'title' => 'required|string|max:255',
            'folder' => 'nullable|string|max:60',
            'type' => 'nullable|in:image,video,document,banner,brochure,floor_plan',
            'visibility' => 'nullable|in:public,private',
            'property_id' => 'nullable|string|max:256',
            'description' => 'nullable|string|max:2000',
            'tags' => 'nullable|array|max:20',
            'tags.*' => 'string|max:40',
        ]);

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

            $type = $type ?: (in_array($ext, self::VIDEO_EXT, true) ? 'video'
                : (in_array($ext, self::IMAGE_EXT, true) ? 'image' : 'document'));

            $storedPath = 'media/' . $agencyId . '/' . now()->format('Y/m') . '/' . $mediaId . '.' . $ext;
            $stored = $file->storeAs(dirname($storedPath), basename($storedPath), 'documents');
            if (!$stored) {
                return response()->json(['success' => false, 'message' => 'Upload failed. Please retry.'], 500);
            }
            $storedPath = $stored;
            $originalName = $file->getClientOriginalName();
            $mimeType = $file->getClientMimeType();
            $size = $file->getSize();

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
            'uploaded_by' => $request->user()->id,
        ]);

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

        $media = MediaFile::where('agency_id', $agencyId)->findOrFail($id);

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'folder' => 'sometimes|string|max:60',
            'visibility' => 'sometimes|in:public,private',
            'property_id' => 'nullable|string|max:256',
            'description' => 'nullable|string|max:2000',
            'tags' => 'nullable|array|max:20',
            'tags.*' => 'string|max:40',
        ]);

        if ($request->filled('property_id')) {
            Property::where('agency_id', $agencyId)->where('id', $request->property_id)->firstOrFail();
        }

        $media->fill($request->only(['title', 'folder', 'visibility', 'property_id', 'description', 'tags']));
        $media->save();

        $this->writeAudit($request, 'media.update', $media->id, "Media updated: {$media->title}", [
            'agency_id' => $agencyId,
            'entity_type' => 'media_file',
            'entity_id' => $media->id,
            'metadata' => ['fields' => array_keys($request->only(['title', 'folder', 'visibility', 'property_id', 'description', 'tags']))],
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

        $request->validate([
            'selling_price_pk' => 'required|numeric|min:0',
            'cost_price_pk' => 'nullable|numeric|min:0',
            'buyer_name' => 'required|string|max:255',
            'buyer_phone' => 'nullable|string|max:30',
            'buyer_national_id_masked' => 'nullable|string|max:40',
            'sold_at' => 'nullable|date',
        ]);

        $media = MediaFile::where('agency_id', $agencyId)->findOrFail($id);

        if ($media->status === 'sold') {
            return response()->json([
                'success' => false,
                'message' => 'This item is already marked as sold.',
            ], 422);
        }

        $selling = (float) $request->selling_price_pk;
        $cost = $request->filled('cost_price_pk') ? (float) $request->cost_price_pk : null;

        $media->status = 'sold';
        $media->sold_at = $request->sold_at ? now()->parse($request->sold_at) : now();
        $media->sold_price_pk = $selling;
        $media->cost_price_pk = $cost;
        $media->profit_pk = $cost !== null ? max(0, $selling - $cost) : 0;
        $media->buyer_name = $request->buyer_name;
        $media->buyer_phone = $request->buyer_phone;
        $media->buyer_national_id_masked = $request->buyer_national_id_masked;
        $media->sold_by = $request->user()->name;
        $media->save();

        $this->writeAudit($request, 'media.sell', $media->id, "Marked sold: {$media->title}", [
            'agency_id' => $agencyId,
            'entity_type' => 'media_file',
            'entity_id' => $media->id,
            'metadata' => [
                'selling_price_pk' => $selling,
                'cost_price_pk' => $cost,
                'profit_pk' => $media->profit_pk,
                'buyer' => $request->buyer_name,
            ],
        ]);

        return response()->json(['success' => true, 'message' => 'Marked as sold.', 'media' => $media]);
    }

    /** DELETE /api/agency/media/{id} — refuses while referenced by a live listing. */
    public function destroy(Request $request, string $id)
    {
        $agencyId = $this->requireAgency($request);
        $this->requireAny($request, ['manage_media']);

        $media = MediaFile::where('agency_id', $agencyId)->findOrFail($id);

        // Reference check: does any active listing in this agency use the file?
        $referenced = Property::where('agency_id', $agencyId)
            ->whereNull('deleted_at')
            ->where('images', 'like', '%' . $media->id . '%')
            ->exists();

        $fileUrl = $media->disk_path ? basename($media->disk_path) : null;
        if (!$referenced && $fileUrl) {
            $referenced = Property::where('agency_id', $agencyId)
                ->whereNull('deleted_at')
                ->where('images', 'like', '%' . $fileUrl . '%')
                ->exists();
        }

        if ($referenced) {
            return response()->json([
                'success' => false,
                'message' => 'This media is used by an active listing. Remove it from the listing first.',
            ], 409);
        }

        if ($media->disk_path) {
            try {
                Storage::disk('documents')->delete($media->disk_path);
            } catch (\Throwable $e) {
                Log::warning('Media file delete failed', ['error' => $e->getMessage()]);
            }
        }

        $this->writeAudit($request, 'media.delete', $media->id, "Media deleted: {$media->title}", [
            'agency_id' => $agencyId,
            'entity_type' => 'media_file',
            'entity_id' => $media->id,
        ]);

        $media->delete();

        return response()->json(['success' => true, 'message' => 'Media deleted.']);
    }

    /**
     * GET /api/agency/media/{id}/file — authenticated delivery of agency media
     * (used for visibility=private files).
     */
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
            ->whereNull('deleted_at')
            ->firstOrFail();

        return $this->deliver($media);
    }

    private function deliver(MediaFile $media)
    {
        if ($media->disk_path) {
            if (!Storage::disk('documents')->exists($media->disk_path)) {
                abort(410, 'File is no longer available.');
            }
            // Inline delivery lets public marketing pages embed the asset
            // directly; private consumers still require authentication.
            return Storage::disk('documents')->response(
                $media->disk_path,
                $media->original_name ?: basename($media->disk_path),
                ['Content-Type' => $media->mime_type ?: 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff']
            );
        }

        if ($media->url) {
            return redirect()->away($media->url);
        }

        abort(410, 'File is no longer available.');
    }
}
