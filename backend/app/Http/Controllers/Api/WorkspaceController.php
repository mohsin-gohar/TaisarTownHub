<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessRecord;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    use AuthorizesApi;

    private const COLLECTIONS = [
        'projects', 'properties', 'dealers', 'sellers', 'buyers',
        'transactions', 'payments', 'commissions', 'files', 'plots',
        'media', 'videos', 'inquiries', 'testimonials', 'subscribers',
    ];

    private const PUBLIC_PROPERTY_KEYS = [
        'id', 'title', 'description', 'pricePKR', 'formattedPrice', 'location',
        'phase', 'sector', 'block', 'streetNumber', 'plotNumber', 'fileNumber',
        'sizeSqYards', 'sizeUnit', 'frontFeet', 'frontMeasurementFt', 'depthFeet',
        'depthMeasurementFt', 'roadWidthFeet', 'roadWidthFt', 'isRoadFacing',
        'is100ftRoadFacing', 'type', 'status', 'isFeatured', 'images', 'videoUrl',
        'features', 'bedrooms', 'bathrooms', 'possessionReady', 'mdaApproved',
        'cornerPlot', 'westOpen', 'parkFacing', 'mainBoulevard', 'createdDate',
        'contactPerson', 'contactPhone', 'contactWhatsApp', 'assignedDealerId',
        'agencyName',
    ];

    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
        ]);
        $email = strtolower(trim($data['email']));
        $id = 'sub-' . substr(sha1($email), 0, 16);
        BusinessRecord::put('subscribers', $id, [
            'id' => $id,
            'email' => $email,
            'subscribedAt' => now()->toIso8601String(),
        ]);

        return response()->json(['success' => true]);
    }

    public function catalog()
    {
        return response()->json([
            'projects' => $this->publicRows('projects'),
            'properties' => array_map(fn ($row) => $this->onlyKeys($row, self::PUBLIC_PROPERTY_KEYS), $this->publicRows('properties')),
            'dealers' => array_map(fn ($row) => $this->publicDealer($row), $this->publicRows('dealers')),
            'videos' => $this->publicRows('videos'),
            'plots' => $this->publicRows('plots'),
            'media' => $this->publicRows('media'),
            'testimonials' => $this->publicRows('testimonials'),
            'cms' => $this->cmsPayload(),
        ]);
    }

    public function show(Request $request)
    {
        $this->requireAny($request, ['view_properties']);

        $all = [];
        foreach (self::COLLECTIONS as $name) {
            $all[$name] = $this->allRows($name);
        }
        $all['cms'] = $this->cmsPayload();

        return response()->json($all);
    }

    public function save(Request $request)
    {
        $this->requireAny($request, ['view_properties']);

        $collections = $request->validate([
            'collections' => 'required|array',
        ])['collections'];

        $user = $request->user();
        foreach (self::COLLECTIONS as $name) {
            if (!array_key_exists($name, $collections) || !is_array($collections[$name])) {
                continue;
            }
            if (!$this->canWrite($user, $name)) {
                continue;
            }
            $rows = array_values(array_filter($collections[$name], fn ($row) => is_array($row) && !empty($row['id'])));

            // Financial integrity: audit newly-sold media assets (sale
            // attribution) BEFORE the payload sync overwrites previous state.
            if ($name === 'media') {
                $this->auditNewMediaSales($request, $rows);
            }

            // Allow persisting an intentionally emptied collection (the previous
            // guard skipped the sync whenever the incoming rows were empty, which
            // made "delete all" impossible and silently dropped the change).
            $this->syncList($name, $rows);
        }

        if (isset($collections['cms']) && is_array($collections['cms']) && $this->canWrite($user, 'cms')) {
            BusinessRecord::put('cms', 'default', $collections['cms']);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Server-side audit trail for media sales: every asset that transitions
     * to "sold" through the workspace sync is recorded in audit_logs with the
     * seller, buyer attribution and profit — append-only, never deleted.
     */
    private function auditNewMediaSales(Request $request, array $rows): void
    {
        foreach ($rows as $row) {
            if (empty($row['id']) || empty($row['soldAt'])) {
                continue;
            }
            $existing = BusinessRecord::where('collection', 'media')->where('record_id', (string) $row['id'])->first();
            $previous = $existing?->payload ?? [];
            if (!empty($previous['soldAt'])) {
                continue; // already audited on its original sale
            }
            $price = (float) ($row['soldPricePKR'] ?? 0);
            $profit = (float) ($row['profitPKR'] ?? 0);
            $this->writeAudit(
                $request,
                'media_sold',
                (string) $row['id'],
                sprintf(
                    '"%s" sold for PKR %s (profit PKR %s) to %s by %s',
                    (string) ($row['title'] ?? 'asset'),
                    number_format($price),
                    number_format($profit),
                    (string) ($row['buyerName'] ?? 'unknown'),
                    (string) ($row['soldBy'] ?? 'unknown')
                )
            );
        }
    }

    private function syncList(string $collection, array $rows): void
    {
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (string) $row['id'];
            if ($collection === 'videos') {
                $row = $this->mergeVideo($row);
            }
            BusinessRecord::put($collection, (string) $row['id'], $row);
        }

        if (count($ids) > 0) {
            BusinessRecord::where('collection', $collection)
                ->whereNotIn('record_id', $ids)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);
        }
    }

    private function mergeVideo(array $row): array
    {
        $existing = BusinessRecord::where('collection', 'videos')->where('record_id', $row['id'])->first();
        if (!$existing) {
            return $row;
        }
        $old = $existing->payload ?? [];
        $row['likes'] = max((int) ($old['likes'] ?? 0), (int) ($row['likes'] ?? 0));
        $row['views'] = max((int) ($old['views'] ?? 0), (int) ($row['views'] ?? 0));
        $comments = [];
        foreach (array_merge($old['comments'] ?? [], $row['comments'] ?? []) as $comment) {
            if (is_array($comment) && !empty($comment['id'])) {
                $comments[$comment['id']] = $comment;
            }
        }
        $row['comments'] = array_values($comments);
        return $row;
    }

    private function publicRows(string $collection): array
    {
        return BusinessRecord::where('collection', $collection)
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (BusinessRecord $record) {
                $payload = $record->payload ?? [];
                if (!empty($payload['deletedAt'])) {
                    return null;
                }
                return $payload;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function allRows(string $collection): array
    {
        return BusinessRecord::where('collection', $collection)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (BusinessRecord $record) => $record->payload ?? [])
            ->filter(fn ($row) => !empty($row['id']))
            ->values()
            ->all();
    }

    private function cmsPayload(): array
    {
        $record = BusinessRecord::where('collection', 'cms')->where('record_id', 'default')->first();
        return $record?->payload ?? [];
    }

    private function onlyKeys(array $row, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                $out[$key] = $row[$key];
            }
        }
        return $out;
    }

    private function publicDealer(array $row): array
    {
        unset($row['cnic']);
        return $row;
    }

    private function canWrite($user, string $collection): bool
    {
        $gates = [
            'projects' => ['manage_projects'],
            'properties' => ['create_properties', 'edit_properties', 'delete_properties'],
            'dealers' => ['manage_dealers', 'pay_commissions'],
            'sellers' => ['manage_sellers'],
            'buyers' => ['manage_buyers', 'submit_buyer'],
            'transactions' => ['create_transactions', 'update_transactions', 'void_transactions'],
            'payments' => ['manage_payments'],
            'commissions' => ['pay_commissions', 'manage_payments', 'create_transactions'],
            'files' => ['upload_documents', 'verify_documents'],
            'plots' => ['edit_properties'],
            'media' => ['manage_media', 'upload_media'],
            'videos' => ['manage_media', 'upload_media'],
            'inquiries' => ['view_inquiries'],
            'subscribers' => ['view_inquiries'],
            'testimonials' => ['edit_cms'],
            'cms' => ['edit_cms'],
        ];

        foreach ($gates[$collection] ?? [] as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }
}
