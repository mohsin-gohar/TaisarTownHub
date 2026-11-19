<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Legacy workspace sync (JSON store) — HARDENED.
 *
 * Security rules enforced here (Phase 3):
 *  1. Reads: per-collection view permissions + agency scoping — an agency
 *     member never receives another agency's rows.
 *  2. Writes: entry requires an actual WRITE permission (the old gate was
 *     `view_properties`, i.e. any viewer could overwrite everything).
 *  3. `agency_id` / `created_by` / `updated_by` are always server-derived —
 *     client-supplied tenancy fields are stripped from every row.
 *  4. Default sync is MERGE (upsert only, never touches rows the client did
 *     not send). Destructive wipe only happens with an explicit
 *     `mode=replace` AND the collection's delete-level permission, and even
 *     then it is scoped to the writer's agency.
 *  5. Every row is size/shape validated before persistence.
 *  6. Financial collections (transactions/payments/commissions) are audited
 *     on every sync.
 */
class WorkspaceController extends Controller
{
    use AuthorizesApi;

    private const COLLECTIONS = [
        'projects', 'properties', 'dealers', 'sellers', 'buyers',
        'transactions', 'payments', 'commissions', 'files', 'plots',
        'media', 'videos', 'inquiries', 'testimonials', 'subscribers',
    ];

    /** Collections whose rows belong to an agency (tenancy-scoped). */
    private const AGENCY_SCOPED = [
        'properties', 'dealers', 'sellers', 'buyers', 'transactions',
        'payments', 'commissions', 'files', 'plots', 'media', 'videos',
        'inquiries',
    ];

    /** Collections holding money — every sync on them is audit-logged. */
    private const FINANCIAL = ['transactions', 'payments', 'commissions'];

    /** Money-ish keys coerced/validated on financial rows. */
    private const MONEY_KEYS = [
        'agreementAmountPKR', 'advancePaymentPKR', 'commissionAmountPKR',
        'remainingBalancePKR', 'amountPKR', 'amount', 'pricePKR', 'profitPKR',
        'soldPricePKR', 'costPricePKR', 'totalEarnedPKR', 'pendingCommissionPKR',
        'totalPaidPKR', 'balancePKR', 'commission', 'commissionAmount',
    ];

    /** Server-owned keys never accepted from the client payload. */
    private const RESERVED_KEYS = [
        'agency_id', 'created_by', 'updated_by', 'deleted_by',
        'uploaded_by', 'recorded_by', 'verified_by',
    ];

    private const MAX_ROWS_PER_COLLECTION = 2000;
    private const MAX_ROW_BYTES = 512 * 1024; // 512 KB per row

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
        // Public listings must be explicitly governed: agency-owned, approved,
        // public and sale-only. Legacy BusinessRecord rows predate the
        // enterprise workflow, so they no longer default into the public
        // catalog — the governed Property API is the single public source.
        $listed = array_values(array_filter(
            $this->publicRows('properties', agencyOwnedOnly: true),
            fn ($row) => $this->publiclyListed($row)
        ));

        return response()->json([
            'properties' => array_values(array_map(
                fn ($row) => $this->onlyKeys($row, self::PUBLIC_PROPERTY_KEYS),
                $listed
            )),
            'dealers' => array_map(fn ($row) => $this->publicDealer($row), $this->publicRows('dealers')),
            'videos' => array_map(fn ($row) => $this->publicVideo($row), $this->publicRows('videos')),
            'plots' => array_map(fn ($row) => $this->publicPlot($row), $this->publicRows('plots')),
            'media' => array_values(array_map(
                fn (array $row) => $this->publicMedia($row),
                array_filter(
                    $this->publicRows('media'),
                    fn (array $row) => ($row['visibility'] ?? 'private') === 'public'
                )
            )),
            'testimonials' => $this->publicRows('testimonials'),
            'cms' => $this->cmsPayload(),
        ]);
    }

    /**
     * Full workspace dump (legacy contract).
     *
     * Entry requires a read permission; each collection is then filtered by
     * its own view gate AND by the acting agency (agency members receive
     * their own rows + unowned platform rows only).
     */
    public function show(Request $request)
    {
        $this->requireAny($request, [
            'view_properties', 'view_transactions', 'view_inquiries',
            'view_reports', 'finance.view', 'crm.view', 'properties.view',
        ]);

        $user = $request->user();
        $agencyId = $this->agencyId($request);

        $all = [];
        foreach (self::COLLECTIONS as $name) {
            $all[$name] = $this->canRead($request, $user, $name, $agencyId)
                ? $this->allRows($name, $agencyId)
                : [];
        }
        $all['cms'] = $this->canRead($request, $user, 'cms', $agencyId) ? $this->cmsPayload() : [];

        return response()->json($all);
    }

    /**
     * Save workspace collections.
     *
     * `mode` (optional): 'merge' (default — upsert only) or 'replace'
     * (explicit destructive sync, requires delete-level permission and is
     * scoped to the writer's agency).
     */
    public function save(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Authentication required.');
        }

        $validated = $request->validate([
            'collections' => 'required|array',
            'mode' => 'nullable|in:merge,replace',
        ]);

        $collections = $validated['collections'];
        $mode = $validated['mode'] ?? 'merge';
        $agencyId = $this->agencyId($request);
        $replaced = [];
        $written = [];
        $denied = [];

        foreach (self::COLLECTIONS as $name) {
            if (!array_key_exists($name, $collections) || !is_array($collections[$name])) {
                continue;
            }

            // ---- permission gate (per collection, agency-scoped) ----
            if (!$this->canWrite($request, $user, $name, $agencyId)) {
                $denied[] = $name;
                continue;
            }

            if ($mode === 'replace' && !$this->canReplace($request, $user, $name, $agencyId)) {
                $denied[] = $name; // replace needs delete-level rights
                continue;
            }

            $rows = array_values(array_filter(
                $collections[$name],
                fn ($row) => is_array($row) && isset($row['id'])
            ));

            if (count($rows) > self::MAX_ROWS_PER_COLLECTION) {
                abort(422, "Collection '{$name}' exceeds " . self::MAX_ROWS_PER_COLLECTION . " rows per request.");
            }

            // ---- per-row validation + server-owned field stamping ----
            $clean = [];
            foreach ($rows as $row) {
                $sanitized = $this->sanitizeRow($name, $row, $agencyId, $user);
                if ($sanitized !== null) {
                    $clean[] = $sanitized;
                }
            }

            // Financial integrity: audit newly-sold media assets (sale
            // attribution) BEFORE the payload sync overwrites previous state.
            if ($name === 'media') {
                $this->auditNewMediaSales($request, $clean);
            }

            $result = $this->syncList($name, $clean, $agencyId, $mode);
            $changedIds = $result['changed'];
            $removed = $result['removed'];

            if (in_array($name, self::FINANCIAL, true) && $changedIds) {
                $this->auditFinancialSync($request, $name, $clean, $changedIds, $agencyId);
            }

            if ($changedIds || $removed) {
                $written[$name] = count($changedIds) + $removed;
            }
            if ($mode === 'replace' && $removed) {
                $replaced[] = $name;
            }
        }

        if (isset($collections['cms']) && is_array($collections['cms'])) {
            if ($this->canWrite($request, $user, 'cms', $agencyId)) {
                if (strlen(json_encode($collections['cms'])) > self::MAX_ROW_BYTES) {
                    abort(422, 'CMS payload too large.');
                }
                BusinessRecord::put('cms', 'default', $collections['cms']);
                $written['cms'] = 1;
            } else {
                $denied[] = 'cms';
            }
        }

        if ($written) {
            $this->writeAudit($request, 'workspace.save', 'workspace:' . implode(',', array_keys($written)),
                'Workspace sync (' . $mode . '): ' . json_encode($written), [
                    'metadata' => [
                        'mode' => $mode,
                        'written' => $written,
                        'replaced' => $replaced,
                        'denied' => $denied,
                    ],
                ]);
        }

        if ($denied) {
            Log::info('Workspace save: collections denied', [
                'user_id' => $user->id, 'denied' => $denied,
            ]);
        }

        // Writes are enforced per collection above; a user with no write
        // rights simply persists nothing (matches legacy no-op behaviour and
        // avoids error toasts for read-only viewers of the workspace).
        return response()->json([
            'success' => true,
            'mode' => $mode,
            'written' => $written,
            'denied' => $denied,
        ]);
    }

    /* ================================================================ */
    /* Validation & stamping                                             */
    /* ================================================================ */

    /**
     * Validate one incoming row and stamp server-owned fields.
     * Returns null when the row is unusable (silently skipped).
     */
    private function sanitizeRow(string $collection, array $row, ?string $agencyId, $user): ?array
    {
        $id = $row['id'] ?? null;
        if (!is_string($id) && !is_int($id)) {
            return null;
        }
        $id = trim((string) $id);
        if ($id === '' || strlen($id) > 64 || !preg_match('/^[A-Za-z0-9_\-.:]+$/', $id)) {
            return null;
        }

        // Server-owned tenancy/ownership keys are stripped — the business_records
        // `agency_id` COLUMN is the single source of truth for scoping (set in
        // syncList), never a key inside the payload.
        foreach (self::RESERVED_KEYS as $key) {
            unset($row[$key]);
        }

        // Strip non-UTF8 / oversized scalars, cap nesting depth
        $row = $this->filterValue($row, 0);
        if ($row === null) {
            return null;
        }

        if (strlen(json_encode($row)) > self::MAX_ROW_BYTES) {
            Log::warning('Workspace row rejected (too large)', [
                'collection' => $collection, 'id' => $id,
            ]);
            return null;
        }

        $row['id'] = $id;

        // Money fields: numeric, finite, capped
        if (in_array($collection, self::FINANCIAL, true)) {
            foreach (self::MONEY_KEYS as $key) {
                if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                    $value = (float) $row[$key];
                    if (!is_finite($value) || $value < 0 || $value > 999999999999) {
                        $row[$key] = 0;
                    } else {
                        $row[$key] = $value;
                    }
                }
            }
        }

        $row['updated_by'] = $user->id;

        return $row;
    }

    /** Recursively sanitize a value: depth cap, string length cap, UTF-8. */
    private function filterValue($value, int $depth)
    {
        if ($depth > 8) {
            return null; // too deep — reject the branch
        }

        if (is_array($value)) {
            if (count($value) > 5000) {
                return null;
            }
            $out = [];
            foreach ($value as $key => $item) {
                if (is_string($key) && strlen($key) > 100) {
                    continue;
                }
                $filtered = $this->filterValue($item, $depth + 1);
                if ($filtered === null && $item !== null) {
                    return null;
                }
                $out[$key] = $filtered;
            }
            return $out;
        }

        if (is_string($value)) {
            if (!mb_check_encoding($value, 'UTF-8')) {
                $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            }
            return strlen($value) > 65536 ? substr($value, 0, 65536) : $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return $value;
        }

        // Objects / resources are not storable payload
        return null;
    }

    /* ================================================================ */
    /* Sync                                                              */
    /* ================================================================ */

    /**
     * MERGE (default): upsert the given rows only — rows absent from the
     * payload are never touched (no cross-collection wipes).
     *
     * REPLACE (explicit + elevated): upsert, then soft-delete rows that are
     * absent — scoped to the writer's agency for members, platform-wide for
     * platform users (requires delete-level permission, audited).
     *
     * OWNERSHIP (enforced on every row):
     *  - member (agencyId set): may CREATE rows (stamped with their agency)
     *    and UPDATE rows owned by their agency. Rows owned by ANOTHER agency
     *    are never touched. Legacy platform rows (agency_id NULL) remain
     *    shared/read-write for members but are never re-stamped (no hijack).
     *  - platform user (agencyId null): full access, column left as-is.
     *
     * Returns ['changed' => ids actually modified, 'removed' => count].
     * Unchanged rows are skipped so the debounced frontend autosave does not
     * generate audit/write churn on every keystroke.
     */
    private function syncList(string $collection, array $rows, ?string $agencyId, string $mode): array
    {
        $ids = [];
        $changed = [];
        $removed = 0;
        $blocked = 0;
        $scoped = in_array($collection, self::AGENCY_SCOPED, true);

        foreach ($rows as $row) {
            $recordId = (string) $row['id'];
            $ids[] = $recordId;

            $existing = BusinessRecord::where('collection', $collection)
                ->where('record_id', $recordId)
                ->first();

            $owner = $existing ? ($existing->agency_id ?? null) : null;

            // ---- row-level ownership enforcement ----
            if ($scoped && $agencyId !== null && $existing && $owner !== null && $owner !== $agencyId) {
                $blocked++; // another agency's row — never touch
                continue;
            }

            if ($existing) {
                // Preserve the original creator stamped by an earlier sync
                $previous = $existing->payload ?? [];
                if (isset($previous['created_by']) && !isset($row['created_by'])) {
                    $row['created_by'] = $previous['created_by'];
                }
            } elseif ($scoped && $agencyId !== null) {
                // New row created by a member is owned by their agency
                $row['created_by'] = auth()->id();
            }

            if ($collection === 'videos') {
                $row = $this->mergeVideo($row, $existing);
            }

            $newPayload = $row;
            $oldPayload = $existing?->payload ?? null;
            $deletedAt = !empty($row['deletedAt']) ? now() : null;
            $oldDeletedAt = $existing?->deleted_at;

            $payloadDiffers = $oldPayload === null
                || json_encode($oldPayload) !== json_encode($newPayload);
            $deletedDiffers = ($oldDeletedAt === null) !== ($deletedAt === null);

            if (!$payloadDiffers && !$deletedDiffers) {
                continue; // nothing changed — skip write
            }

            BusinessRecord::put($collection, $recordId, $row);
            $changed[] = $recordId;

            // Stamp tenancy COLUMN for new rows and rows already owned by the
            // writer. Shared legacy rows (NULL) stay NULL — never hijacked.
            if ($scoped && $agencyId !== null && ($existing === null || $owner === $agencyId)) {
                BusinessRecord::where('collection', $collection)
                    ->where('record_id', $recordId)
                    ->update(['agency_id' => $agencyId]);
            }
            // platform writer: leave the existing column untouched
        }

        if ($blocked > 0) {
            Log::warning('Workspace save: rows skipped (not owned by writer)', [
                'collection' => $collection,
                'agency_id' => $agencyId,
                'blocked' => $blocked,
                'user_id' => auth()->id(),
            ]);
        }

        if ($mode !== 'replace') {
            return ['changed' => $changed, 'removed' => 0]; // merge-only
        }

        $query = BusinessRecord::where('collection', $collection)
            ->whereNull('deleted_at');

        if (count($ids) > 0) {
            $query->whereNotIn('record_id', $ids);
        }

        // Destructive replace: members only within their own agency;
        // platform users replace platform-wide (elevated + audited).
        if ($scoped && $agencyId !== null) {
            $query->where('agency_id', $agencyId);
        }

        $removed = $query->update(['deleted_at' => now()]);

        if ($removed > 0) {
            Log::info('Workspace replace-mode removals', [
                'collection' => $collection, 'count' => $removed,
            ]);
        }

        return ['changed' => $changed, 'removed' => $removed];
    }

    private function mergeVideo(array $row, ?BusinessRecord $existing): array
    {
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

    /** Append-only audit entry for changed financial rows only. */
    private function auditFinancialSync(Request $request, string $collection, array $rows, array $changedIds, ?string $agencyId): void
    {
        $changed = array_filter($rows, fn ($row) => in_array((string) $row['id'], $changedIds, true));
        if (!$changed) {
            return;
        }

        try {
            $ids = array_slice($changedIds, 0, 50);
            $total = 0.0;
            foreach ($changed as $row) {
                $total += (float) ($row['amount'] ?? $row['amountPKR']
                    ?? $row['agreementAmountPKR'] ?? $row['commissionAmountPKR'] ?? 0);
            }

            $this->writeAudit($request, 'workspace.' . $collection, $collection . ':' . implode(',', $ids),
                sprintf('%s sync: %d row(s) changed, total value PKR %s', $collection, count($changed), number_format($total)), [
                    'agency_id' => $agencyId,
                    'entity_type' => 'business_records',
                    'metadata' => [
                        'collection' => $collection,
                        'count' => count($changed),
                        'ids' => $ids,
                        'total' => $total,
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('Financial sync audit failed', ['error' => $e->getMessage()]);
        }
    }

    /* ================================================================ */
    /* Reads                                                             */
    /* ================================================================ */

    private function publicRows(string $collection, bool $agencyOwnedOnly = false): array
    {
        $query = BusinessRecord::where('collection', $collection)
            ->whereNull('deleted_at');

        if ($agencyOwnedOnly) {
            $query->whereNotNull('agency_id');
        }

        return $query
            ->orderByDesc('updated_at')
            ->limit(1000)
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

    /** Public catalog only lists agency-owned, explicitly approved sale listings. */
    private function publiclyListed(array $row): bool
    {
        // No defaults: missing governance keys mean the row is not publishable.
        // The owning agency is enforced at the query level (agency_id column).
        return ($row['approval_status'] ?? null) === 'approved'
            && ($row['visibility'] ?? null) === 'public'
            && ($row['listing_purpose'] ?? null) === 'sale';
    }

    /**
     * Agency-scoped full read for the legacy workspace contract.
     *
     * Agency members receive only their OWN rows. Legacy unowned/platform rows
     * (agency_id NULL — pre-enterprise data with no explicit owner) are hidden
     * from agency workspaces to prevent cross-tenant exposure until an explicit
     * ownership decision is applied. Rows tagged with ANOTHER agency are never
     * returned. Platform users (no agency membership scope) see everything.
     */
    private function allRows(string $collection, ?string $agencyId): array
    {
        $query = BusinessRecord::where('collection', $collection);

        if (in_array($collection, self::AGENCY_SCOPED, true) && $agencyId !== null) {
            $query->where('agency_id', $agencyId);
        }

        return $query
            ->orderByDesc('updated_at')
            ->limit(2000)
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
        // Public catalog: strip all PII and financial fields.
        unset(
            $row['cnic'], $row['cnicMasked'], $row['CNIC'],
            $row['phone'], $row['whatsApp'], $row['email'],
            $row['totalEarnedPKR'], $row['pendingCommissionPKR'],
            $row['commissionRate'], $row['commissionType'],
            $row['commissionValue'], $row['accountNumber'],
            $row['internalNotes'], $row['notes']
        );
        return $row;
    }

    private function publicVideo(array $row): array
    {
        // Strip internal user ids and sensitive fields from public videos.
        unset($row['likedBy'], $row['liked_by'], $row['authorId'], $row['author_id'],
            $row['internalNotes'], $row['notes']);
        return $row;
    }

    private function publicPlot(array $row): array
    {
        // Strip owner names and sensitive fields from public plots.
        unset($row['markerLabel'], $row['marker_label'], $row['ownerName'],
            $row['owner_name'], $row['internalNotes'], $row['notes']);
        return $row;
    }

    private function publicMedia(array $row): array
    {
        $allowed = [
            'id', 'title', 'description', 'fileName', 'fileType', 'mimeType',
            'url', 'thumbnailUrl', 'width', 'height', 'visibility',
        ];
        return $this->onlyKeys($row, $allowed);
    }

    /* ================================================================ */
    /* Permission gates                                                  */
    /* ================================================================ */

    /**
     * Permission check mirroring AuthorizesApi: agency-scoped grants when the
     * user acts within an agency, PLUS platform-wide grants for cross-agency
     * platform admins (flagged by AgencyContext via X-Agency-Id).
     */
    private function perm(Request $request, $user, string $permission, ?string $agencyId): bool
    {
        if ($user->hasPermission($permission, $agencyId)) {
            return true;
        }
        if ($agencyId !== null && $this->isCrossAgency($request) && $user->hasPermission($permission)) {
            return true;
        }
        return false;
    }

    /** Read gate per collection (agency-scoped keys checked when scoped). */
    private function canRead(Request $request, $user, string $collection, ?string $agencyId): bool
    {
        // `view_properties` is the legacy universal read gate — kept as the
        // fallback so existing staff screens keep working; agency scoping
        // (allRows) is what actually isolates tenants on reads.
        $gates = [
            'projects' => ['view_properties', 'properties.view', 'manage_projects'],
            'properties' => ['view_properties', 'properties.view'],
            'dealers' => ['view_dealers', 'manage_dealers', 'properties.view', 'view_properties'],
            'sellers' => ['manage_sellers', 'view_properties'],
            'buyers' => ['manage_buyers', 'submit_buyer', 'view_properties'],
            'transactions' => ['view_transactions', 'finance.view', 'view_my_deals', 'view_properties'],
            'payments' => ['view_transactions', 'finance.view', 'view_my_deals', 'view_properties'],
            'commissions' => ['view_commissions', 'finance.view', 'view_my_commission', 'view_properties'],
            'files' => ['upload_documents', 'verify_documents', 'view_private_documents', 'documents.view', 'view_properties'],
            'plots' => ['view_properties', 'properties.view'],
            'media' => ['view_properties', 'properties.view', 'manage_media', 'upload_media'],
            'videos' => ['view_properties', 'properties.view', 'manage_media', 'upload_media'],
            'inquiries' => ['view_inquiries', 'crm.view', 'view_properties'],
            'subscribers' => ['view_inquiries', 'crm.view', 'view_properties'],
            'testimonials' => ['edit_cms', 'view_reports', 'view_properties'],
            'cms' => ['edit_cms', 'view_reports', 'view_properties'],
        ];

        foreach ($gates[$collection] ?? ['view_properties'] as $permission) {
            if ($this->perm($request, $user, $permission, $agencyId)) {
                return true;
            }
        }
        return false;
    }

    /** Write gate per collection (agency-scoped). */
    private function canWrite(Request $request, $user, string $collection, ?string $agencyId = null): bool
    {
        $gates = [
            'projects' => ['manage_projects'],
            'properties' => ['create_properties', 'edit_properties', 'properties.create', 'properties.update'],
            'dealers' => ['manage_dealers', 'pay_commissions'],
            'sellers' => ['manage_sellers'],
            'buyers' => ['manage_buyers', 'submit_buyer'],
            'transactions' => ['create_transactions', 'update_transactions', 'void_transactions', 'finance.create_transaction'],
            'payments' => ['manage_payments', 'finance.manage_payments'],
            'commissions' => ['pay_commissions', 'manage_payments', 'create_transactions', 'finance.manage_payments'],
            'files' => ['upload_documents', 'verify_documents', 'documents.upload'],
            'plots' => ['edit_properties', 'properties.update'],
            'media' => ['manage_media', 'upload_media'],
            'videos' => ['manage_media', 'upload_media'],
            'inquiries' => ['view_inquiries', 'crm.update_lead'],
            'subscribers' => ['view_inquiries'],
            'testimonials' => ['edit_cms'],
            'cms' => ['edit_cms'],
        ];

        foreach ($gates[$collection] ?? [] as $permission) {
            if ($this->perm($request, $user, $permission, $agencyId)) {
                return true;
            }
        }
        return false;
    }

    /** Replace (destructive) mode requires delete-level rights. */
    private function canReplace(Request $request, $user, string $collection, ?string $agencyId): bool
    {
        $gates = [
            'properties' => ['delete_properties', 'properties.delete'],
            'dealers' => ['manage_dealers'],
            'sellers' => ['manage_sellers'],
            'buyers' => ['manage_buyers'],
            'transactions' => ['void_transactions'],
            'payments' => ['manage_payments', 'finance.manage_payments'],
            'commissions' => ['pay_commissions'],
            'files' => ['verify_documents', 'documents.delete'],
            'plots' => ['delete_properties', 'properties.delete'],
            'media' => ['manage_media'],
            'videos' => ['manage_media'],
            'inquiries' => ['crm.delete_lead'],
            'subscribers' => ['view_inquiries'],
            'testimonials' => ['edit_cms'],
            'projects' => ['manage_projects'],
        ];

        foreach ($gates[$collection] ?? [] as $permission) {
            if ($this->perm($request, $user, $permission, $agencyId)) {
                return true;
            }
        }
        return false;
    }
}




