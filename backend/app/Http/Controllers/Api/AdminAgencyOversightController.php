<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencySubscription;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\MediaFile;
use App\Support\PlanLimits;
use Illuminate\Http\Request;

/**
 * Platform oversight of agency storage.
 *
 * Super admins can see the same byte counters the upload gates enforce, review
 * file metadata (never file contents), and extend an agency's quota. Extending
 * a quota never deletes or downgrades data.
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
        $overQuota = 0;

        $agencies = collect($page->items())->map(function (Agency $agency) use (&$overQuota) {
            $row = $this->storageRow($agency);
            if ($row['over_quota']) {
                $overQuota++;
            }

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
                'over_quota_on_page' => $overQuota,
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
        $versionBytes = (int) DocumentVersion::query()
            ->whereIn('document_id', Document::withTrashed()->where('agency_id', $agency->id)->select('id'))
            ->sum('size_bytes');
        $mediaBytes = (int) MediaFile::withTrashed()->where('agency_id', $agency->id)->sum('size_bytes');

        return response()->json([
            'success' => true,
            'agency' => $agency,
            'storage' => array_merge($this->storageRow($agency), [
                'document_bytes' => $documentBytes,
                'version_bytes' => $versionBytes,
                'media_bytes' => $mediaBytes,
                'version_count' => DocumentVersion::query()
                    ->whereIn('document_id', Document::withTrashed()->where('agency_id', $agency->id)->select('id'))
                    ->count(),
            ]),
            'documents' => $documents,
            'media' => $media,
            'quota_history' => \App\Models\AuditLog::query()
                ->where('action', 'agency.quota.extend')
                ->where('entity_id', $agency->id)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(['id', 'actor', 'detail', 'metadata', 'created_at']),
        ]);
    }

    /** Shared storage shape for list + detail. */
    private function storageRow(Agency $agency): array
    {
        $usage = PlanLimits::usage($agency);
        $used = (int) $usage['storage_bytes'];
        $limitMb = $agency->limits()['storage_mb'] ?? null;
        $limitMb = $limitMb !== null && (int) $limitMb > 0 ? (int) $limitMb : null;
        $limitBytes = $limitMb !== null ? $limitMb * 1048576 : null;

        return [
            'storage_used_bytes' => $used,
            'storage_limit_mb' => $limitMb,
            'storage_limit_bytes' => $limitBytes,
            'storage_percent' => $limitBytes !== null && $limitBytes > 0
                ? round(min(100, ($used / $limitBytes) * 100), 1)
                : 0,
            'storage_remaining_bytes' => $limitBytes !== null ? max(0, $limitBytes - $used) : null,
            'over_quota' => $limitBytes !== null && $used > $limitBytes,
        ];
    }
}
