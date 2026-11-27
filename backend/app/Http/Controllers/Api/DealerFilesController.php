<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Dealer;
use App\Models\DealerFile;
use App\Models\User;
use App\Support\PlanLimits;
use App\Support\UploadSecurity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DealerFilesController extends Controller
{
    use AuthorizesApi;

    /**
     * List files for the acting dealer only (agency-bound).
     */
    public function index(Request $request)
    {
        $this->requirePermission($request, 'documents.view');
        $dealerId = $this->actingDealerId($request);

        $query = DealerFile::where('dealer_id', $dealerId);

        $status = $request->query('status');
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $search = $request->query('search');
        if ($search) {
            $query->where('title', 'like', '%' . strip_tags($search) . '%');
        }

        $files = $query->orderByDesc('created_at')->orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data' => $files,
            'count' => $files->count(),
        ]);
    }

    /**
     * Register a file for the acting dealer, enforcing permissions and quota.
     */
    public function store(Request $request)
    {
        $this->requirePermission($request, 'documents.upload');
        $dealerId = $this->actingDealerId($request);
        $dealer = Dealer::findOrFail($dealerId);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:image,video,pdf',
            'url' => [
                'required', 'string', 'max:2048',
                // Registration-side guard: only HTTPS public hosts. The legacy
                // renderer points <img>/<video> at these URLs, so a non-HTTPS
                // or private-network target must never be accepted.
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (is_string($value) && $value !== '' && !UploadSecurity::isAllowedExternalUrl($value)) {
                        $fail('Only HTTPS links to public hosts are allowed.');
                    }
                },
            ],
            'folder' => 'required|in:Projects,Properties,Videos,Documents,Banners',
            'file_size_kb' => 'required|numeric|min:0|max:51200',
            'dimensions' => 'nullable|string|max:50',
        ]);

        // Legacy dealer files are URL registrations. Enforce the plan's file
        // count only; storage quotas are not part of this release.
        $agencyId = $this->agencyId($request);
        if ($agencyId !== null) {
            $agency = Agency::findOrFail($agencyId);
            $used = fn () => DealerFile::whereIn(
                'dealer_id',
                User::whereHas('memberships', fn ($q) => $q->where('agency_id', $agencyId)->where('status', 'active'))
                    ->whereNotNull('dealer_id')->pluck('dealer_id')
            )->count();
            if ($block = PlanLimits::block($agency, 'dealer_files', $used)) {
                return PlanLimits::deny('Dealer file limit reached for your subscription plan.', $block);
            }
        }

        $file = DealerFile::create([
            'title' => strip_tags($validated['title']),
            'type' => $validated['type'],
            'url' => $validated['url'],
            'folder' => $validated['folder'],
            'file_size_kb' => $validated['file_size_kb'],
            'dimensions' => $validated['dimensions'] ?? null,
            'dealer_id' => $dealerId,
            'uploaded_by' => $dealerId,
            'status' => 'available',
        ]);

        $this->writeAudit($request, 'dealer_file.upload', (string) $file->id,
            "File {$file->title} uploaded to dealer {$dealer->fullName}", [
                'agency_id' => $agencyId,
                'entity_type' => 'dealer_file',
                'entity_id' => (string) $file->id,
            ]);

        return response()->json([
            'success' => true,
            'data' => $file,
            'message' => 'File uploaded to your dashboard successfully.',
        ], 201);
    }

    /**
     * Mark a file as sold and calculate commission.
     *
     * The status re-check runs on the LOCKED row, so two dealers racing on the
     * same file cannot both sell it.
     */
    public function sell(Request $request, $id)
    {
        $this->requireAny($request, ['finance.create_transaction', 'manage_deals']);
        $user = $request->user();
        $dealerId = $this->actingDealerId($request);

        // Commerce gate: suspended agencies are already blocked by the
        // AgencyContext middleware; this additionally blocks agencies whose
        // subscription is past_due/cancelled or whose paid term has expired.
        $agencyId = $this->agencyId($request);
        if ($agencyId !== null) {
            $agency = Agency::find($agencyId);
            if ($agency !== null && !PlanLimits::subscriptionActive($agency)) {
                return PlanLimits::subscriptionDenied();
            }
        }

        $validated = $request->validate([
            'selling_price_kbr' => 'required|numeric|decimal:0,2|min:0|max:9999999999',
            'buyer_name' => 'required|string|max:255',
            'buyer_phone' => 'required|string|max:50',
            'buyer_cnic' => 'nullable|string|max:20',
        ]);

        $profitCents = 0;
        $profit = '0.00';

        $soldFile = DB::transaction(function () use ($id, $dealerId, $validated, &$profitCents, &$profit) {
            $file = DealerFile::where('id', $id)
                ->where('dealer_id', $dealerId)
                ->lockForUpdate()
                ->first();

            if (!$file) {
                abort(404);
            }

            if ($file->status === 'sold') {
                return null;
            }

            $sellingCents = $this->toCents($validated['selling_price_kbr']);
            $rawCostPrice = $file->getRawOriginal('cost_price_kbr');
            $costCents = $rawCostPrice !== null
                ? $this->toCents($rawCostPrice)
                : intdiv(($sellingCents * 7) + 5, 10); // exact 70% fallback, rounded to cents
            $profitCents = $sellingCents - $costCents;
            $costPrice = $this->fromCents($costCents);
            $profit = $this->fromCents($profitCents);

            $file->update([
                'status' => 'sold',
                'selling_price_kbr' => $this->fromCents($sellingCents),
                'cost_price_kbr' => $costPrice,
                'profit_kbr' => $profit,
                'buyer_name' => strip_tags($validated['buyer_name']),
                'buyer_phone' => $validated['buyer_phone'],
                'buyer_cnic' => isset($validated['buyer_cnic']) && $validated['buyer_cnic'] !== ''
                    ? $this->maskCnic($validated['buyer_cnic'])
                    : null,
                'sold_by_dealer_id' => $dealerId,
            ]);

            // Existing dealer commission counters are whole-PKR integer fields.
            $commissionRupees = $profitCents > 0 ? intdiv($profitCents + 50, 100) : 0;
            $this->updateDealerCommission($dealerId, $commissionRupees);

            return $file;
        });

        if ($soldFile === null) {
            return response()->json([
                'success' => false,
                'message' => 'This file has already been sold.',
            ], 400);
        }

        $this->writeAudit($request, 'dealer_file.sold', (string) $soldFile->id,
            "File {$soldFile->title} sold for PKR {$validated['selling_price_kbr']}, profit: PKR {$profit}", [
                'agency_id' => $this->agencyId($request),
                'entity_type' => 'dealer_file',
                'entity_id' => (string) $soldFile->id,
            ]);

        return response()->json([
            'success' => true,
            'data' => $soldFile,
            'message' => 'File marked as sold. Commission calculated and updated.',
            'profit' => (float) $profit, // numeric response contract; ledger storage remains exact decimal
        ]);
    }

    /**
     * Sales attribution for one dealer: self, or another dealer in the same
     * agency with finance.view / view_commissions. Platform-wide needs an
     * explicit platform report permission.
     */
    public function salesReport(Request $request, $dealerId)
    {
        $this->requirePermission($request, 'documents.view');
        $dealerId = (string) $dealerId;
        $isSelf = (string) $request->user()->dealer_id === $dealerId;

        if (!$isSelf) {
            $this->requireAny($request, ['finance.view', 'view_commissions', 'admin.view_reports']);
        }

        $agencyId = $this->agencyId($request);
        if ($agencyId !== null && !$this->dealerInAgency($dealerId, $agencyId)) {
            abort(403, 'Dealer does not belong to the acting agency.');
        }

        $dealer = Dealer::findOrFail($dealerId);
        $files = DealerFile::where('sold_by_dealer_id', $dealerId)
            ->where('status', 'sold')
            ->orderByDesc('created_at')->orderBy('id')->get();

        $withPii = $this->can($request, 'documents.view_sensitive');

        return response()->json([
            'success' => true,
            'data' => [
                'dealer' => $dealer,
                'total_files_sold' => $files->count(),
                'total_profit_kbr' => round($files->sum('profit_kbr'), 2),
                'files' => $files->map(fn ($f) => $this->presentFile($f, $withPii)),
            ],
        ]);
    }

    /**
     * Platform-wide sales report. Requires an explicit platform permission;
     * agency members can never reach another agency's sales this way.
     */
    public function allSalesReport(Request $request)
    {
        $this->requirePermission($request, 'admin.view_reports');

        $validated = $request->validate([
            'dealer_id' => 'nullable|string|max:64',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $query = DealerFile::where('status', 'sold');
        if (!empty($validated['dealer_id'])) {
            $query->where('sold_by_dealer_id', $validated['dealer_id']);
        }
        if (!empty($validated['start_date'])) {
            $query->where('created_at', '>=', $validated['start_date']);
        }
        if (!empty($validated['end_date'])) {
            $query->where('created_at', '<=', $validated['end_date']);
        }

        $files = $query->orderByDesc('created_at')->orderBy('id')->get();
        $withPii = $this->can($request, 'documents.view_sensitive');

        return response()->json([
            'success' => true,
            'data' => [
                'total_files_sold' => $files->count(),
                'total_profit_kbr' => round($files->sum('profit_kbr'), 2),
                'files' => $files->map(fn ($f) => $this->presentFile($f, $withPii)),
                'by_dealer' => $files->groupBy('dealer_id')->map(function ($group, $dealerId) {
                    $dealer = Dealer::find($dealerId);
                    return [
                        'dealer' => $dealer ? $dealer->fullName : 'Unknown',
                        'files_sold' => $group->count(),
                        'profit' => round($group->sum('profit_kbr'), 2),
                    ];
                })->values()->toArray(),
            ],
        ]);
    }

    /** Convert a validated non-negative decimal PKR amount to integer paise. */
    private function toCents(string|int|float $amount): int
    {
        $value = (string) $amount;
        if (! preg_match('/^(\\d+)(?:\\.(\\d{1,2}))?$/', $value, $matches)) {
            throw new \\InvalidArgumentException('Invalid decimal PKR amount.');
        }

        $rupees = (int) $matches[1];
        $paise = (int) str_pad($matches[2] ?? '', 2, '0');
        return ($rupees * 100) + $paise;
    }

    private function fromCents(int $amount): string
    {
        $sign = $amount < 0 ? '-' : '';
        $absolute = abs($amount);
        return $sign . intdiv($absolute, 100) . '.' . str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Resolve the acting dealer strictly from the authenticated account. */
    private function actingDealerId(Request $request): string
    {
        $dealerId = $request->user()->dealer_id;
        if (!$dealerId) {
            abort(403, 'This account has no dealer identity.');
        }
        $agencyId = $this->agencyId($request);
        if ($agencyId !== null && !$this->dealerInAgency((string) $dealerId, $agencyId)) {
            abort(403, 'Dealer does not belong to the acting agency.');
        }

        return (string) $dealerId;
    }

    /** Dealer ownership is derived from active agency memberships. */
    private function dealerInAgency(string $dealerId, string $agencyId): bool
    {
        return User::where('dealer_id', $dealerId)
            ->whereHas('memberships', fn ($q) => $q->where('agency_id', $agencyId)->where('status', 'active'))
            ->exists();
    }

    /** Never expose buyer PII to callers without the sensitive-document grant. */
    private function presentFile(DealerFile $file, bool $withPii): array
    {
        $data = [
            'id' => $file->id,
            'title' => $file->title,
            'type' => $file->type,
            'status' => $file->status,
            'folder' => $file->folder,
            'dealer_id' => $file->dealer_id,
            'sold_by_dealer_id' => $file->sold_by_dealer_id,
            'buyer_cnic' => $file->buyer_cnic, // already masked before storage
            'selling_price_kbr' => $file->selling_price_kbr,
            'cost_price_kbr' => $file->cost_price_kbr,
            'profit_kbr' => $file->profit_kbr,
            'created_at' => $file->created_at,
        ];
        if ($withPii) {
            $data['buyer_name'] = $file->buyer_name;
            $data['buyer_phone'] = $file->buyer_phone;
        }

        return $data;
    }

    protected function updateDealerCommission(string $dealerId, int $profit): void
    {
        $dealer = Dealer::where('id', $dealerId)->lockForUpdate()->first();
        if ($dealer) {
            $dealer->totalEarnedPKR = ($dealer->totalEarnedPKR ?? 0) + $profit;
            $dealer->pendingCommissionPKR = ($dealer->pendingCommissionPKR ?? 0) + $profit;
            $dealer->dealsClosed = ($dealer->dealsClosed ?? 0) + 1;
            $dealer->save();
        }
    }

    private function maskCnic(string $cnic): string
    {
        $clean = preg_replace('/[^0-9]/', '', $cnic);

        // Keep the familiar 13-digit mask (42101****5671). Anything shorter is
        // fully masked: it may be a partial CNIC, and computing
        // strlen($clean) - 9 for 7-8 digit input produced a negative
        // str_repeat() length, which throws ValueError on PHP 8.
        if (strlen($clean) === 13) {
            return substr($clean, 0, 5) . str_repeat('*', 4) . substr($clean, -4);
        }

        return str_repeat('*', strlen($clean));
    }
}
