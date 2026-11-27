<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencySubscription;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Platform oversight of agency storage — read-only monitoring.
 *
 * Super admins can see the same byte counters the upload pipeline records and
 * review file metadata (never file contents). This release has no storage
 * quota, trial or quota-extension control: files are never deleted or blocked
 * by the numbers on this screen.
 */
class AdminAgencyOversightController extends Controller
{
    use AuthorizesApi;

    /** GET /api/admin/agencies-oversight */
    public function index(Request $request)
    {
        $this->requireAny($request, ['admin.manage_agencies', 'admin.manage_subscriptions']);

        $query = Agency::query()
            ->with(['subscription.plan:id,key,name,limits'])
            ->withCount(['activeMembers', 'properties', 'documents', 'mediaFiles']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('verification_status')) {
            $query->where('verification_status', $request->verification_status);
        }
        if ($request->filled('search')) {
            $search = addcslashes((string) $request->search, '%_\\');
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        $page = $query->orderBy('name')->paginate(min((int) $request->get('per_page', 25), 100));
        $storageOnPage = 0;
        $agencyIds = collect($page->items())->pluck('id')->all();

        // Batch storage accounting for the current page. Calling
        // PlanLimits::usage() inside the map ran three SUM queries per agency
        // (documents, versions, media), turning a 100-row page into 300
        // additional queries.
        $documentBytesByAgency = collect();
        $versionBytesByAgency = collect();
        $mediaBytesByAgency = collect();

        if ($agencyIds !== []) {
            $documentBytesByAgency = Document::withTrashed()
                ->whereIn('agency_id', $agencyIds)
                ->selectRaw('agency_id, COALESCE(SUM(size_bytes), 0) as total_bytes')
                ->groupBy('agency_id')
                ->pluck('total_bytes', 'agency_id');

            $versionBytesByAgency = DB::table('document_versions')
                ->join('documents', 'documents.id', '=', 'document_versions.document_id')
                ->whereIn('documents.agency_id', $agencyIds)
                ->selectRaw('documents.agency_id as agency_id, COALESCE(SUM(document_versions.size_bytes), 0) as total_bytes')
                ->groupBy('documents.agency_id')
                ->pluck('total_bytes', 'agency_id');

            $mediaBytesByAgency = MediaFile::withTrashed()
                ->whereIn('agency_id', $agencyIds)
                ->selectRaw('agency_id, COALESCE(SUM(size_bytes), 0) as total_bytes')
                ->groupBy('agency_id')
                ->pluck('total_bytes', 'agency_id');
        }

        $agencies = collect($page->items())->map(function (Agency $agency) use (
            &$storageOnPage,
            $documentBytesByAgency,
            $versionBytesByAgency,
            $mediaBytesByAgency
        ) {
            $row = [
                'storage_used_bytes' => (int) ($documentBytesByAgency[$agency->id] ?? 0)
                    + (int) ($versionBytesByAgency[$agency->id] ?? 0)
                    + (int) ($mediaBytesByAgency[$agency->id] ?? 0),
            ];
            $storageOnPage += (int) $row['storage_used_bytes'];

            return array_merge([
                'id' => $agency->id,
                'name' => $agency->name,
                'slug' => $agency->slug,
                'logo_url' => $agency->logo_url,
                'city' => $agency->city,
                'email' => $agency->email,
                'status' => $agency->status,
                'verification_status' => $agency->verification_status,
                'created_at' => $agency->created_at,
                'members_count' => $agency->active_members_count,
                'properties_count' => $agency->properties_count,
                'documents_count' => $agency->documents_count,
                'media_count' => $agency->media_files_count,
                'plan' => $agency->subscription?->plan?->name,
                'subscription_status' => $agency->subscription?->status,
            ], $row);
        });

        return response()->json([
            'success' => true,
            'agencies' => $agencies,
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'per_page' => $page->perPage(),
            ],
            'summary' => [
                'agencies_on_page' => $agencies->count(),
                'storage_used_bytes_on_page' => $storageOnPage,
                'storage_mode' => 'monitor_only',
            ],
        ]);
    }

    /** GET /api/admin/agencies-oversight/{id} */
    public function show(Request $request, string $id)
    {
        $this->requireAny($request, ['admin.manage_agencies', 'admin.manage_subscriptions']);

        $agency = Agency::query()
            ->with(['subscription.plan', 'owner:id,name,email'])
            ->findOrFail($id);

        $documents = Document::withTrashed()
            ->where('agency_id', $agency->id)
            ->with('folder:id,name,path')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'folder_id', 'category', 'title', 'mime_type', 'size_bytes', 'status', 'scan_status', 'is_sensitive', 'version', 'created_at', 'deleted_at']);

        $media = MediaFile::withTrashed()
            ->where('agency_id', $agency->id)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'title', 'type', 'folder', 'mime_type', 'size_bytes', 'visibility', 'status', 'created_at', 'deleted_at']);

        $documentBytes = (int) Document::withTrashed()->where('agency_id', $agency->id)->sum('size_bytes');
        $versionTotals = DocumentVersion::query()
            ->whereIn('document_id', Document::withTrashed()->where('agency_id', $agency->id)->select('id'))
            ->selectRaw('COALESCE(SUM(size_bytes), 0) as total_bytes, COUNT(*) as version_count')
            ->first();
        $versionBytes = (int) ($versionTotals->total_bytes ?? 0);
        $mediaBytes = (int) MediaFile::withTrashed()->where('agency_id', $agency->id)->sum('size_bytes');

        return response()->json([
            'success' => true,
            'agency' => $agency,
            'storage' => [
                'storage_used_bytes' => $documentBytes + $versionBytes + $mediaBytes,
                'document_bytes' => $documentBytes,
                'version_bytes' => $versionBytes,
                'media_bytes' => $mediaBytes,
                'version_count' => (int) ($versionTotals->version_count ?? 0),
            ],
            'documents' => $documents,
            'media' => $media,
            'storage_mode' => 'monitor_only',
        ]);
    }

}
