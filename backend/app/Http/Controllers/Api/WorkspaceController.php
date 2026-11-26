<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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

    /**
     * Collections whose rows belong to an agency (tenancy-scoped).
     *
     * `subscribers` is scoped too: the public subscribe endpoint creates
     * platform-owned rows (agency_id NULL), and subscriber emails must never
     * be visible to another tenant. Projects/testimonials are deliberately
     * platform-global public content and are not listed here.
     */
    private const AGENCY_SCOPED = [
        'properties', 'dealers', 'sellers', 'buyers', 'transactions',
        'payments', 'commissions', 'files', 'plots', 'media', 'videos',
        'inquiries', 'subscribers',
    ];

    /** Collections holding money — every sync on them is audit-logged. */
    private const FINANCIAL = ['transactions', 'payments', 'commissions'];

    /** Whole-PKR fields accepted by the legacy workspace sync surface. */
    private const MONEY_KEYS = [
        'agreementAmountPKR', 'purchaseAmountPKR', 'finalSellingAmountPKR',
        'dealAmountPKR', 'advancePaymentPKR', 'advancePKR', 'remainingBalancePKR',
        'balancePKR', 'commissionAmountPKR', 'commissionPKR', 'commissionPaidPKR',
        'amountPKR', 'amount', 'paidPKR', 'pricePKR', 'profitPKR', 'soldPricePKR',
        'costPricePKR', 'totalEarnedPKR', 'pendingCommissionPKR', 'totalPaidPKR',
        'commission', 'commissionAmount',
    ];

    private const MAX_WHOLE_PKR = 999999999999;

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

    /**
     * Public catalog projections are ALLOWLISTS, never blacklists: a new
     * private field added to a workspace row must not become public by
     * default. Dealer CNIC, phone, email and commission figures are internal.
     */
    private const PUBLIC_DEALER_KEYS = [
        'id', 'fullName', 'avatar', 'role', 'designation',
        'agencyAffiliation', 'agencyOrBranch', 'dealsClosed',
        'completedDealsCount', 'status', 'createdAt', 'verified', 'rating',
        'city', 'specialties', 'experienceYears',
    ];

    private const PUBLIC_VIDEO_KEYS = [
        'id', 'title', 'description', 'videoUrl', 'thumbnailUrl', 'agencyId',
        'agencyName', 'dealerName', 'dealerAvatar', 'phase', 'sector',
        'duration', 'uploadedAt', 'likes', 'views', 'tags', 'comments',
    ];

    private const PUBLIC_VIDEO_COMMENT_KEYS = [
        'id', 'userName', 'comment', 'createdAt', 'userAvatar', 'isDealer',
    ];

    private const PUBLIC_PLOT_KEYS = [
        'id', 'plotNumber', 'sector', 'subSector', 'block', 'street',
        'sizeSqYards', 'dimensions', 'roadWidth', 'status', 'pricePKR',
        'roadFacing', 'is100ftRoadFacing', 'corner', 'isCorner', 'westOpen',
        'isWestOpen', 'gridCol', 'gridRow', 'propertyId',
    ];

    private const PUBLIC_TESTIMONIAL_KEYS = [
        'id', 'clientName', 'designation', 'quote', 'avatar',
        'propertyPurchased', 'rating', 'isVerifiedDemo',
    ];

    private const PUBLIC_MEDIA_KEYS = [
        'id', 'title', 'description', 'fileName', 'fileType', 'mimeType',
        'type', 'folder', 'url', 'thumbnailUrl', 'width', 'height',
        'dimensions', 'fileSizeKB', 'uploadedAt', 'usageCount', 'visibility',
    ];

    /** Mirrors the frontend CMSConfig contract; unknown payload keys are dropped. */
    private const PUBLIC_CMS_KEYS = [
        'agencyName', 'tagline', 'heroHeadline', 'heroSubtitle', 'heroBackground',
        'statsProjectsCount', 'statsPropertiesCount', 'statsSatisfiedClients',
        'statsAcresPlanned', 'aboutTitle', 'aboutDescription', 'aboutMission',
        'aboutVision', 'officeAddress', 'contactPhone', 'contactWhatsApp',
        'contactEmail', 'workingHours', 'googleMapEmbedUrl', 'facebookUrl',
        'youtubeUrl', 'instagramUrl', 'seoTitle', 'seoDescription',
        'noticeBannerText',
    ];

    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
        ]);
        $email = strtolower(trim($data['email']));
        $id = 'sub-'.substr(sha1($email), 0, 16);
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
            'dealers' => array_values(array_map(
                fn (array $row) => $this->publicDealer($row),
                $this->publiclyVisibleRows('dealers')
            )),
            'videos' => array_values(array_map(
                fn (array $row) => $this->publicVideo($row),
                $this->publiclyVisibleRows('videos')
            )),
            'plots' => array_values(array_map(
                fn (array $row) => $this->publicPlot($row),
                $this->publiclyVisibleRows('plots')
            )),
            'media' => array_values(array_map(
                fn (array $row) => $this->publicMedia($row),
                array_filter(
                    $this->publicRows('media'),
                    fn (array $row) => ($row['visibility'] ?? 'private') === 'public'
                        && $this->isPubliclyVisible($row)
                )
            )),
            'testimonials' => array_values(array_map(
                fn (array $row) => $this->onlyKeys($row, self::PUBLIC_TESTIMONIAL_KEYS),
                $this->publiclyVisibleRows('testimonials')
            )),
            'cms' => $this->publicCms(),
        ]);
    }

    /**
     * Full workspace dump (legacy contract).
     *
     * Each collection is filtered by its own view gate AND by the acting
     * agency (agency members receive only their own rows; platform users see
     * everything). A user with no readable collection receives 403.
     */
    public function show(Request $request)
    {
        $user = $request->user();
        $agencyId = $this->agencyId($request);

        $all = [];
        $anyReadable = false;
        foreach (self::COLLECTIONS as $name) {
            $allowed = $this->canRead($request, $user, $name, $agencyId);
            if ($allowed) {
                $anyReadable = true;
            }
            $all[$name] = $allowed ? $this->allRows($name, $agencyId, $request, $user) : [];
        }

        $cmsAllowed = $this->canRead($request, $user, 'cms', $agencyId);
        if ($cmsAllowed) {
            $anyReadable = true;
        }
        $all['cms'] = $cmsAllowed ? $this->cmsPayload() : [];

        if (! $anyReadable) {
            abort(403, 'No workspace collection is readable with your permissions.');
        }

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
        if (! $user) {
            abort(401, 'Authentication required.');
        }

        $validated = $request->validate([
            'collections' => 'required|array',
            'mode' => 'nullable|in:merge,replace',
            // Destructive replace is never implicit. An empty payload would
            // otherwise soft-delete every visible row (platform-wide for a
            // Super Admin), so it is always refused. A non-empty platform-wide
            // replace requires its own explicit confirmation.
            'confirm_platform_replace' => 'sometimes|boolean',
        ]);

        $collections = $validated['collections'];
        $mode = $validated['mode'] ?? 'merge';
        $agencyId = $this->agencyId($request);
        return DB::transaction(function () use ($request, $user, $collections, $mode, $agencyId) {
            if ($agencyId !== null) {
                \\App\\Models\\Agency::whereKey($agencyId)->lockForUpdate()->firstOrFail();
            }

            $replaced = [];
            $written = [];
            $denied = [];
            $skipped = [];
    
            foreach (self::COLLECTIONS as $name) {
                if (! array_key_exists($name, $collections) || ! is_array($collections[$name])) {
                    continue;
                }
    
                // ---- permission gate (per collection, agency-scoped) ----
                if (! $this->canWrite($request, $user, $name, $agencyId)) {
                    $denied[] = $name;
    
                    continue;
                }
    
                if ($mode === 'replace' && ! $this->canReplace($request, $user, $name, $agencyId)) {
                    $denied[] = $name; // replace needs delete-level rights
    
                    continue;
                }
    
                $rows = array_values(array_filter(
                    $collections[$name],
                    fn ($row) => is_array($row) && isset($row['id'])
                ));
    
                if (count($rows) > self::MAX_ROWS_PER_COLLECTION) {
                    abort(422, "Collection '{$name}' exceeds ".self::MAX_ROWS_PER_COLLECTION.' rows per request.');
                }
    
                // ---- per-row validation + server-owned field stamping ----
                $clean = [];
                foreach ($rows as $rowIndex => $row) {
                    if (in_array($name, self::FINANCIAL, true)) {
                        $this->validateWholePkrAmounts($name, $row, (int) $rowIndex);
                    }
    
                    $sanitized = $this->sanitizeRow($name, $row, $agencyId, $user);
                    if ($sanitized !== null) {
                        $clean[] = $sanitized;
                    }
                }
    
                // ---- destructive replace safety interlocks ----
                // `$clean` is empty when the client sent [] OR when every row was
                // rejected by validation. Treat both as an empty replace: deleting
                // on an accidental/malformed payload is never acceptable, so an
                // empty replace is always skipped (there is no bypass flag).
                if ($mode === 'replace') {
                    if (count($clean) === 0) {
                        $skipped[] = $name;
                        Log::warning('Workspace empty replace skipped', [
                            'collection' => $name,
                            'agency_id' => $agencyId,
                            'user_id' => $user->id,
                            'platform_wide' => $agencyId === null,
                        ]);
    
                        continue;
                    }
    
                    if ($agencyId === null && ! $request->boolean('confirm_platform_replace')) {
                        $skipped[] = $name;
                        Log::warning('Workspace platform-wide replace skipped (confirmation required)', [
                            'collection' => $name,
                            'user_id' => $user->id,
                        ]);
    
                        continue;
                    }
                }
    
                // Financial integrity: audit newly-sold media assets (sale
                // attribution) BEFORE the payload sync overwrites previous state.
                if ($name === 'media') {
                    $this->auditNewMediaSales($request, $clean);
                }
    
                $result = $this->syncList($request, $user, $name, $clean, $agencyId, $mode);
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
                $this->writeAudit($request, 'workspace.save', 'workspace:'.implode(',', array_keys($written)),
                    'Workspace sync ('.$mode.'): '.json_encode($written), [
                        'metadata' => [
                            'mode' => $mode,
                            'written' => $written,
                            'replaced' => $replaced,
                            'denied' => $denied,
                            'skipped' => $skipped,
                            'platform_wide' => $agencyId === null,
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
                'skipped' => $skipped,
            ]);
        }, 3);
    }

    /* ================================================================ */
    /* Validation & stamping */
    /* ================================================================ */

    /**
     * Validate one incoming row and stamp server-owned fields.
     * Returns null when the row is unusable (silently skipped).
     */
    private function sanitizeRow(string $collection, array $row, ?string $agencyId, $user): ?array
    {
        $id = $row['id'] ?? null;
        if (! is_string($id) && ! is_int($id)) {
            return null;
        }
        $id = trim((string) $id);
        if ($id === '' || strlen($id) > 64 || ! preg_match('/^[A-Za-z0-9_\-.:]+$/', $id)) {
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

        // Whole-PKR values are normalized to integers after validation.
        if (in_array($collection, self::FINANCIAL, true)) {
            foreach (self::MONEY_KEYS as $key) {
                if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                    $row[$key] = $this->normalizeWholePkr($row[$key]);
                }
            }
            if ($collection === 'commissions' && isset($row['payouts']) && is_array($row['payouts'])) {
                foreach ($row['payouts'] as $index => $payout) {
                    if (is_array($payout) && array_key_exists('amountPKR', $payout)
                        && $payout['amountPKR'] !== null && $payout['amountPKR'] !== '') {
                        $row['payouts'][$index]['amountPKR'] = $this->normalizeWholePkr($payout['amountPKR']);
                    }
                }
            }
        }

        $row['updated_by'] = $user->id;

        return $row;
    }

    /**
     * Reject fractional, negative, non-finite and out-of-range PKR values on
     * every legacy workspace write. FinanceController's strict validators
     * are not enough because the SPA persists its ledger through this endpoint.
     */
    private function validateWholePkrAmounts(string $collection, array $row, int $rowIndex): void
    {
        foreach (self::MONEY_KEYS as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
                continue;
            }

            if ($this->normalizeWholePkr($row[$key]) === null) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "collections.{$collection}.{$rowIndex}.{$key}" => [
                        'This amount must be a whole PKR value between 0 and ' . self::MAX_WHOLE_PKR . '.',
                    ],
                ]);
            }
        }

        if ($collection === 'commissions' && isset($row['payouts'])) {
            if (! is_array($row['payouts'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "collections.{$collection}.{$rowIndex}.payouts" => ['Payouts must be an array.'],
                ]);
            }

            foreach ($row['payouts'] as $payoutIndex => $payout) {
                if (! is_array($payout) || ! array_key_exists('amountPKR', $payout)
                    || $payout['amountPKR'] === null || $payout['amountPKR'] === '') {
                    continue;
                }

                if ($this->normalizeWholePkr($payout['amountPKR']) === null) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "collections.{$collection}.{$rowIndex}.payouts.{$payoutIndex}.amountPKR" => [
                            'This payout must be a whole PKR value between 0 and ' . self::MAX_WHOLE_PKR . '.',
                        ],
                    ]);
                }
            }
        }
    }

    /** Return a canonical integer PKR value, or null when the input is invalid. */
    private function normalizeWholePkr(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 && $value <= self::MAX_WHOLE_PKR ? $value : null;
        }

        if (is_float($value)) {
            if (! is_finite($value) || floor($value) !== $value
                || $value < 0 || $value > self::MAX_WHOLE_PKR) {
                return null;
            }

            return (int) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if (! preg_match('/^\\+?\\d+(?:\\.0+)?$/D', $value)) {
            return null;
        }

        $whole = explode('.', ltrim($value, '+'), 2)[0];
        $canonical = ltrim($whole, '0') ?: '0';
        $maximum = (string) self::MAX_WHOLE_PKR;
        if (strlen($canonical) > strlen($maximum)
            || (strlen($canonical) === strlen($maximum) && strcmp($canonical, $maximum) > 0)) {
            return null;
        }

        return (int) $canonical;
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
            if (! mb_check_encoding($value, 'UTF-8')) {
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
    /* Sync */
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
    private function syncList(Request $request, $user, string $collection, array $rows, ?string $agencyId, string $mode): array
    {
        $ids = [];
        $changed = [];
        $removed = 0;
        $blocked = 0;
        $accepted = 0;
        $scoped = in_array($collection, self::AGENCY_SCOPED, true);

        foreach ($rows as $row) {
            $recordId = (string) $row['id'];
            $ids[] = $recordId;

            $existing = BusinessRecord::where('collection', $collection)
                ->where('record_id', $recordId)
                ->lockForUpdate()
                ->first();

            $owner = $existing ? ($existing->agency_id ?? null) : null;

            // ---- row-level ownership enforcement ----
            if ($scoped && $agencyId !== null && $existing && $owner !== null && $owner !== $agencyId) {
                $blocked++; // another agency's row — never touch

                continue;
            }

            // BLK-06 (Option B): unresolved legacy rows are platform-owned.
            // Agency writers must not co-claim them via the sync surface —
            // rows with no owner stay read/write for platform users only
            // until a human-approved mapping is applied with
            // platform:legacy-backfill (resolver evidence or --mapping).
            if ($scoped && $agencyId !== null && $existing && $owner === null) {
                $blocked++;

                continue;
            }

            $accepted++;

            if ($existing) {
                // Preserve the original creator stamped by an earlier sync
                $previous = $existing->payload ?? [];
                if (isset($previous['created_by']) && ! isset($row['created_by'])) {
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
            $deletedAt = ! empty($row['deletedAt']) ? now() : null;
            $oldDeletedAt = $existing?->deleted_at;

            $payloadDiffers = $oldPayload === null
                || json_encode($oldPayload) !== json_encode($newPayload);
            $deletedDiffers = ($oldDeletedAt === null) !== ($deletedAt === null);

            if (! $payloadDiffers && ! $deletedDiffers) {
                continue; // nothing changed — skip write
            }

            // Client-supplied deletedAt cannot turn an update permission into
            // a delete/restore permission.
            if (($deletedAt !== null || $oldDeletedAt !== null) && ! $this->canReplace($request, $user, $collection, $agencyId)) {
                $blocked++;

                continue;
            }

            if ($existing === null && ! $this->canCreateRow($request, $user, $collection, $agencyId)) {
                $blocked++;

                continue;
            }

            if ($existing !== null && $deletedAt === null && $oldDeletedAt === null
                && ! $this->canUpdateRow($request, $user, $collection, $agencyId, $oldPayload ?? [], $newPayload)) {
                $blocked++;

                continue;
            }

            BusinessRecord::put($collection, $recordId, $row);
            $changed[] = $recordId;

            // Stamp tenancy COLUMN for new rows and rows already owned by the
            // writer. Shared legacy rows (NULL) stay NULL — never hijacked.
            if ($scoped && $agencyId !== null && ($existing === null || ($owner !== null && $owner === $agencyId))) {
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

        // Defence in depth: an empty id set — or a payload where every row was
        // rejected as foreign/platform-owned — must never reach the
        // destructive delete query. save() intercepts an empty payload, but
        // this also stops a replace that only contains rows the writer cannot
        // touch from wiping their own collection.
        if (count($ids) === 0 || $accepted === 0) {
            Log::warning('Workspace replace skipped at sync layer (no writable rows)', [
                'collection' => $collection,
                'agency_id' => $agencyId,
                'user_id' => auth()->id(),
                'blocked' => $blocked,
            ]);

            return ['changed' => $changed, 'removed' => 0];
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
        if (! $existing) {
            return $row;
        }
        $old = $existing->payload ?? [];
        $row['likes'] = max((int) ($old['likes'] ?? 0), (int) ($row['likes'] ?? 0));
        $row['views'] = max((int) ($old['views'] ?? 0), (int) ($row['views'] ?? 0));
        $comments = [];
        foreach (array_merge($old['comments'] ?? [], $row['comments'] ?? []) as $comment) {
            if (is_array($comment) && ! empty($comment['id'])) {
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
            if (! empty($previous['soldAt'])) {
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
        if (! $changed) {
            return;
        }

        try {
            $ids = array_slice($changedIds, 0, 50);
            $total = 0.0;
            foreach ($changed as $row) {
                $total += (float) ($row['amount'] ?? $row['amountPKR']
                    ?? $row['agreementAmountPKR'] ?? $row['commissionAmountPKR'] ?? 0);
            }

            $this->writeAudit($request, 'workspace.'.$collection, $collection.':'.implode(',', $ids),
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
    /* Reads */
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
                if (! empty($payload['deletedAt'])) {
                    return null;
                }

                return $payload;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Rows whose payload explicitly opts into public visibility. Legacy rows
     * without governance keys remain eligible (they are the demo/public seed),
     * but anything marked private/draft/rejected/inactive is never returned.
     */
    private function publiclyVisibleRows(string $collection): array
    {
        return array_values(array_filter(
            $this->publicRows($collection),
            fn (array $row) => $this->isPubliclyVisible($row)
        ));
    }

    private function isPubliclyVisible(array $row): bool
    {
        if (array_key_exists('visibility', $row)
            && ! in_array($row['visibility'], ['public', 'published'], true)) {
            return false;
        }
        if (array_key_exists('approval_status', $row) && $row['approval_status'] !== 'approved') {
            return false;
        }
        if (array_key_exists('isPublic', $row) && $row['isPublic'] !== true) {
            return false;
        }
        if (array_key_exists('published', $row) && $row['published'] !== true) {
            return false;
        }
        if (isset($row['status']) && is_string($row['status'])
            && in_array(strtolower($row['status']), [
                'inactive', 'disabled', 'archived', 'hidden', 'draft', 'private', 'rejected',
            ], true)) {
            return false;
        }

        return true;
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
    private function allRows(string $collection, ?string $agencyId, Request $request, $user): array
    {
        $query = BusinessRecord::where('collection', $collection)->whereNull('deleted_at');

        if (in_array($collection, self::AGENCY_SCOPED, true) && $agencyId !== null) {
            $query->where('agency_id', $agencyId);
        }

        $rows = $query->orderBy('record_id')->limit(self::MAX_ROWS_PER_COLLECTION)
            ->get()->map(fn ($record) => $record->payload ?? [])->all();

        $hasBroadFinanceRead = $this->perm($request, $user, 'finance.view', $agencyId)
            || $this->perm($request, $user, 'view_transactions', $agencyId);
        $hasBroadCommissionRead = $this->perm($request, $user, 'finance.view', $agencyId)
            || $this->perm($request, $user, 'view_commissions', $agencyId);

        if ($collection === 'transactions'
            && $this->perm($request, $user, 'view_my_deals', $agencyId)
            && ! $hasBroadFinanceRead) {
            $dealerId = (string) ($user->dealer_id ?? '');

            return $dealerId === '' ? [] : array_values(array_filter($rows, function ($row) use ($dealerId) {
                $rowDealerId = (string) ($row['dealerId'] ?? $row['assignedDealerId'] ?? $row['dealer_id'] ?? '');

                return $rowDealerId === $dealerId;
            }));
        }

        if ($collection === 'payments'
            && $this->perm($request, $user, 'view_my_deals', $agencyId)
            && ! $hasBroadFinanceRead) {
            $ownedTransactions = $this->allRows('transactions', $agencyId, $request, $user);
            $ownedIds = array_fill_keys(array_filter(array_map(
                fn ($row) => (string) ($row['id'] ?? ''),
                $ownedTransactions
            )), true);

            return array_values(array_filter($rows, function ($row) use ($ownedIds) {
                $transactionId = (string) ($row['transactionId'] ?? $row['transaction_id'] ?? $row['dealId'] ?? '');

                return $transactionId !== '' && isset($ownedIds[$transactionId]);
            }));
        }

        if ($collection === 'commissions'
            && $this->perm($request, $user, 'view_my_commission', $agencyId)
            && ! $hasBroadCommissionRead) {
            $dealerId = (string) ($user->dealer_id ?? '');

            return $dealerId === '' ? [] : array_values(array_filter($rows, function ($row) use ($dealerId) {
                $rowDealerId = (string) ($row['dealerId'] ?? $row['dealer_id'] ?? '');

                return $rowDealerId === $dealerId;
            }));
        }

        return $rows;
    }


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

    /** Read gate per collection. Sensitive collections require their own least-privilege permission. */
    private function canRead(Request $request, $user, string $collection, ?string $agencyId): bool
    {
        $gates = [
            'projects' => ['view_properties', 'properties.view', 'manage_projects'],
            'properties' => ['view_properties', 'properties.view'],
            'dealers' => ['view_dealers', 'manage_dealers'],
            'sellers' => ['manage_sellers'],
            'buyers' => ['manage_buyers'],
            'transactions' => ['view_transactions', 'finance.view', 'view_my_deals'],
            'payments' => ['view_transactions', 'finance.view', 'view_my_deals'],
            'commissions' => ['view_commissions', 'finance.view', 'view_my_commission'],
            'files' => ['upload_documents', 'verify_documents', 'view_private_documents', 'documents.view'],
            'plots' => ['view_properties', 'properties.view'],
            'media' => ['manage_media', 'upload_media'],
            'videos' => ['manage_media', 'upload_media'],
            'inquiries' => ['view_inquiries', 'crm.view'],
            'subscribers' => ['view_inquiries', 'crm.view'],
            'testimonials' => ['edit_cms', 'view_reports', 'view_properties', 'properties.view'],
            'cms' => ['edit_cms', 'view_reports', 'view_properties', 'properties.view'],
        ];

        foreach ($gates[$collection] ?? [] as $permission) {
            if ($this->perm($request, $user, $permission, $agencyId)) {
                return true;
            }
        }

        return false;
    }

    /** Write gate per collection. Platform-global collections are never agency-writable. */
    private function canWrite(Request $request, $user, string $collection, ?string $agencyId = null): bool
    {
        if (in_array($collection, ['projects', 'testimonials', 'cms'], true) && ! $user->isPlatformUser()) {
            return false;
        }

        $gates = [
            'projects' => ['manage_projects'],
            'properties' => ['create_properties', 'edit_properties', 'properties.create', 'properties.update'],
            'dealers' => ['manage_dealers'],
            'sellers' => ['manage_sellers'],
            'buyers' => ['manage_buyers', 'submit_buyer'],
            'transactions' => ['create_transactions', 'update_transactions', 'void_transactions', 'finance.create_transaction'],
            'payments' => ['manage_payments', 'finance.manage_payments'],
            'commissions' => ['pay_commissions', 'manage_payments', 'finance.manage_payments'],
            'files' => ['upload_documents', 'verify_documents', 'documents.upload'],
            'plots' => ['edit_properties', 'properties.update'],
            'media' => ['manage_media', 'upload_media'],
            'videos' => ['manage_media', 'upload_media'],
            'inquiries' => ['crm.update_lead'],
            'subscribers' => ['crm.update_lead'],
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

    private function canCreateRow(Request $request, $user, string $collection, ?string $agencyId): bool
    {
        if (in_array($collection, ['projects', 'testimonials', 'cms'], true) && ! $user->isPlatformUser()) {
            return false;
        }

        $gates = [
            'projects' => ['manage_projects'],
            'properties' => ['create_properties', 'properties.create'],
            'dealers' => ['manage_dealers'],
            'sellers' => ['manage_sellers'],
            'buyers' => ['submit_buyer', 'manage_buyers'],
            'transactions' => ['create_transactions', 'finance.create_transaction'],
            'payments' => ['manage_payments', 'finance.manage_payments'],
            'commissions' => ['pay_commissions', 'manage_payments', 'finance.manage_payments'],
            'files' => ['upload_documents', 'documents.upload'],
            'plots' => ['edit_properties', 'properties.update'],
            'media' => ['upload_media', 'manage_media'],
            'videos' => ['upload_media', 'manage_media'],
            'inquiries' => ['crm.update_lead'],
            'subscribers' => ['crm.update_lead'],
            'testimonials' => ['edit_cms'],
        ];

        foreach ($gates[$collection] ?? [] as $permission) {
            if ($this->perm($request, $user, $permission, $agencyId)) {
                return true;
            }
        }

        return false;
    }

    private function canUpdateRow(
        Request $request,
        $user,
        string $collection,
        ?string $agencyId,
        array $oldPayload,
        array $newPayload
    ): bool {
        if (in_array($collection, ['projects', 'testimonials', 'cms'], true) && ! $user->isPlatformUser()) {
            return false;
        }

        $gates = [
            'projects' => ['manage_projects'],
            'properties' => ['edit_properties', 'properties.update'],
            'dealers' => ['manage_dealers'],
            'sellers' => ['manage_sellers'],
            'buyers' => ['manage_buyers'],
            'transactions' => ['update_transactions'],
            'payments' => [], // payment records are append-only; create a refund entry instead
            'commissions' => ['pay_commissions', 'manage_payments', 'finance.manage_payments'],
            'files' => ['verify_documents', 'documents.update'],
            'plots' => ['edit_properties', 'properties.update'],
            'media' => ['manage_media'],
            'videos' => ['manage_media'],
            'inquiries' => ['crm.update_lead'],
            'subscribers' => ['crm.update_lead'],
            'testimonials' => ['edit_cms'],
        ];

        if ($collection === 'transactions') {
            $moneyKeys = [
                'agreementAmountPKR', 'advancePaymentPKR', 'remainingBalancePKR',
                'totalPaidPKR', 'commissionAmountPKR', 'paymentStatus',
            ];
            $moneyChanged = false;
            foreach ($moneyKeys as $key) {
                if (($oldPayload[$key] ?? null) !== ($newPayload[$key] ?? null)) {
                    $moneyChanged = true;
                    break;
                }
            }
            if ($moneyChanged && ! $this->perm($request, $user, 'finance.manage_payments', $agencyId)) {
                return false;
            }

            $oldStatus = strtolower((string) ($oldPayload['status'] ?? ''));
            $newStatus = strtolower((string) ($newPayload['status'] ?? ''));
            if ($newStatus === 'voided' && $oldStatus !== 'voided') {
                return $this->perm($request, $user, 'void_transactions', $agencyId)
                    && filled($newPayload['voidReason'] ?? $newPayload['notes'] ?? null)
                    && (int) ($oldPayload['advancePaymentPKR'] ?? 0) === 0
                    && $this->netCollectedForTransaction((string) ($newPayload['id'] ?? ''), $agencyId) === 0;
            }
        }

        if ($collection === 'commissions') {
            $oldStatus = strtolower((string) ($oldPayload['status'] ?? ''));
            $newStatus = strtolower((string) ($newPayload['status'] ?? ''));
            $amountKeys = ['commissionAmountPKR', 'amountPKR', 'commissionAmount'];
            foreach ($amountKeys as $key) {
                if (($oldPayload[$key] ?? null) !== ($newPayload[$key] ?? null)
                    && ! $this->perm($request, $user, 'finance.manage_payments', $agencyId)) {
                    return false;
                }
            }
            if ($oldStatus === 'paid' && $oldPayload !== $newPayload) {
                return false;
            }
            if ($newStatus === 'paid' && $oldStatus !== 'paid'
                && ! $this->perm($request, $user, 'pay_commissions', $agencyId)
                && ! $this->perm($request, $user, 'finance.manage_payments', $agencyId)) {
                return false;
            }
        }

        foreach ($gates[$collection] ?? [] as $permission) {
            if ($this->perm($request, $user, $permission, $agencyId)) {
                return true;
            }
        }

        return false;
    }

    private function netCollectedForTransaction(string $transactionId, ?string $agencyId): int
    {
        if ($transactionId === '') {
            return 0;
        }

        $query = BusinessRecord::where('collection', 'payments')->whereNull('deleted_at');
        if ($agencyId !== null) {
            $query->where('agency_id', $agencyId);
        }

        return (int) $query->get()->sum(function (BusinessRecord $record) use ($transactionId): int {
            $row = $record->payload ?? [];
            $rowTransactionId = (string) ($row['transactionId'] ?? $row['transaction_id'] ?? $row['dealId'] ?? '');
            if ($rowTransactionId !== $transactionId) {
                return 0;
            }

            $amount = (int) ($row['amountPKR'] ?? $row['paymentAmountPKR'] ?? $row['amount'] ?? 0);
            $type = strtolower((string) ($row['type'] ?? $row['paymentType'] ?? 'payment'));

            return str_contains($type, 'refund') ? -abs($amount) : abs($amount);
        });
    }

    /** Replace (destructive) mode requires delete-level rights; financial ledgers cannot be bulk-replaced. */
    private function canReplace(Request $request, $user, string $collection, ?string $agencyId): bool
    {
        if (in_array($collection, ['projects', 'testimonials', 'cms'], true) && ! $user->isPlatformUser()) {
            return false;
        }

        $gates = [
            'properties' => ['delete_properties', 'properties.delete'],
            'dealers' => ['manage_dealers'],
            'sellers' => ['manage_sellers'],
            'buyers' => ['manage_buyers'],
            'transactions' => [],
            'payments' => [],
            'commissions' => [],
            'files' => ['documents.delete'],
            'plots' => ['delete_properties', 'properties.delete'],
            'media' => ['manage_media'],
            'videos' => ['manage_media'],
            'inquiries' => ['crm.delete_lead'],
            'subscribers' => ['crm.delete_lead'],
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
