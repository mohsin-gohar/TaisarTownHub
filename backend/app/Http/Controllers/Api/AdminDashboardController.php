<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\BusinessRecord;
use App\Models\Document;
use App\Models\Inquiry;
use App\Models\Property;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Real dashboard analytics — replaces the hard-coded chart/metrics that the
 * Phase 1 audit found in AdminOverview.tsx. All numbers are computed from the
 * database for the requested period.
 */
class AdminDashboardController extends Controller
{
    use AuthorizesApi;

    /**
     * GET /api/admin/reports?from=&to= — platform-wide, date-filtered report
     * (G-12): agencies, deals, collections, invoice revenue and marketplace
     * activity computed from the database for the requested window.
     * Requires `admin.view_reports` (platform-only).
     */
    public function reports(Request $request)
    {
        $this->requirePermission($request, 'admin.view_reports');

        $data = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $from = now()->parse($data['from'] ?? now()->subDays(29)->toDateString())->startOfDay();
        $to = now()->parse($data['to'] ?? now()->toDateString())->endOfDay();

        $agencies = [
            'total' => Agency::count(),
            'verified' => Agency::where('verification_status', 'verified')->count(),
            'new_in_range' => Agency::whereBetween('created_at', [$from, $to])->count(),
        ];

        $deals = Transaction::whereBetween('created_at', [$from, $to]);
        $dealAgreed = (clone $deals)->whereNull('voided_at')->selectRaw('COUNT(*) AS n, COALESCE(SUM(agreementAmountPKR), 0) AS total')->first();

        $collected = DB::table('transaction_payments')
            ->whereBetween('received_at', [$from, $to])
            ->selectRaw('COALESCE(SUM(amount), 0) AS total, COUNT(*) AS n')
            ->first();

        $invoices = [
            'paid_pkr' => (float) \App\Models\SubscriptionInvoice::where('status', 'paid')
                ->whereBetween('paid_at', [$from, $to])->sum('total_pkr'),
            'paid_count' => \App\Models\SubscriptionInvoice::where('status', 'paid')
                ->whereBetween('paid_at', [$from, $to])->count(),
            'outstanding_pkr' => (float) \App\Models\SubscriptionInvoice::where('status', 'pending')->sum('total_pkr'),
        ];

        $listings = [
            'created_in_range' => Property::whereNull('deleted_at')->whereBetween('created_at', [$from, $to])->count(),
            'public' => Property::whereNull('deleted_at')->where('approval_status', 'approved')->where('visibility', 'public')->count(),
        ];

        $leads = Inquiry::whereBetween('created_at', [$from, $to])->count();
        $expenses = (float) \App\Models\Expense::whereNull('deleted_at')
            ->whereDate('expense_date', '>=', $from->toDateString())
            ->whereDate('expense_date', '<=', $to->toDateString())
            ->sum('amount_pkr');

        return response()->json([
            'success' => true,
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'agencies' => $agencies,
            'deals' => [
                'count' => (int) $dealAgreed->n,
                'agreed_pkr' => (float) $dealAgreed->total,
                'voided_count' => (clone $deals)->whereNotNull('voided_at')->count(),
            ],
            'collected_pkr' => (float) $collected->total,
            'payments_count' => (int) $collected->n,
            'invoices' => $invoices,
            'listings' => $listings,
            'leads_in_range' => $leads,
            'expenses_pkr' => $expenses,
        ]);
    }

    public function index(Request $request)
    {
        $this->requireAny($request, ['view_reports', 'admin.view_reports', 'properties.view', 'view_properties', 'finance.view']);

        $user = $request->user();
        $agencyId = $this->agencyId($request);
        $days = min(max((int) $request->get('days', 30), 7), 365);
        $since = now()->subDays($days);

        $isPlatform = $agencyId === null;

        /* -------- listing metrics -------- */
        $propertyQuery = Property::query()->whereNull('deleted_at');
        $leadQuery = Inquiry::query();
        $txnQuery = Transaction::query();
        $userQuery = User::query();
        $agencyQuery = Agency::query();

        if ($agencyId !== null) {
            $propertyQuery->where('agency_id', $agencyId);
            $leadQuery->where('agency_id', $agencyId);
            $txnQuery->where('agency_id', $agencyId);
        }

        $listings = [
            'total' => (clone $propertyQuery)->count(),
            'public' => (clone $propertyQuery)->where('approval_status', 'approved')->where('visibility', 'public')->count(),
            'pending' => (clone $propertyQuery)->where('approval_status', 'pending')->count(),
            'draft' => (clone $propertyQuery)->where('approval_status', 'draft')->count(),
            'rejected' => (clone $propertyQuery)->where('approval_status', 'rejected')->count(),
            'featured' => (clone $propertyQuery)->where('isFeatured', true)->count(),
            'created_' . $days . 'd' => (clone $propertyQuery)->where('created_at', '>=', $since)->count(),
        ];

        $leads = [
            'total' => (clone $leadQuery)->count(),
            'new' => (clone $leadQuery)->where('lead_status', 'new')->count(),
            'open' => (clone $leadQuery)->whereIn('lead_status', ['new', 'contacted', 'interested', 'negotiation'])->count(),
            'converted' => (clone $leadQuery)->where('lead_status', 'converted')->count(),
            'due_follow_ups' => (clone $leadQuery)->whereNotNull('follow_up_at')->where('follow_up_at', '<=', now())->count(),
            'created_' . $days . 'd' => (clone $leadQuery)->where('created_at', '>=', $since)->count(),
        ];

        /* -------- money (real ledger) -------- */
        $canSeeFinance = $user->hasPermission('finance.view', $agencyId) || $user->hasPermission('view_reports', $agencyId) || $user->hasPermission('admin.view_reports', null);
        $paymentScope = DB::table('transaction_payments');
        $commissionScope = DB::table('commissions');
        if ($agencyId !== null) {
            $paymentScope->where('agency_id', $agencyId);
            $commissionScope->where('agency_id', $agencyId);
        }

        $finance = $canSeeFinance ? [
            'collected_total' => (float) (clone $paymentScope)->sum('amount'),
            'collected_' . $days . 'd' => (float) (clone $paymentScope)->where('received_at', '>=', $since)->sum('amount'),
            'commission_pending' => (float) (clone $commissionScope)->whereIn('status', ['pending', 'accrued'])->sum('amount'),
            'commission_paid' => (float) (clone $commissionScope)->where('status', 'paid')->sum('amount'),
            'active_deals' => (clone $txnQuery)->whereNull('voided_at')->count(),
            'voided_deals' => (clone $txnQuery)->whereNotNull('voided_at')->count(),
            'deal_value' => (float) (clone $txnQuery)->whereNull('voided_at')->sum('agreementAmountPKR'),
        ] : [];

        /* -------- conversions -------- */
        $approvedListings = max(1, $listings['public']);
        $convertedLeads = $leads['converted'];
        $completedDeals = (clone $txnQuery)->where('dealStatus', 'Completed')->count();
        $activeDeals = $canSeeFinance ? (clone $txnQuery)->whereNull('voided_at')->count() : 0;

        $metrics = [
            'lead_conversion_pct' => round(($convertedLeads / max(1, $leads['total'])) * 100, 1),
            'deal_close_pct' => round(($completedDeals / max(1, $activeDeals)) * 100, 1),
            'pending_approval_pct' => round(($listings['pending'] / max(1, $listings['total'])) * 100, 1),
            'listings_per_lead' => round($approvedListings / max(1, $leads['total']), 2),
        ];

        /* -------- time series (real, not hard-coded) -------- */
        $series = $this->timeseries($days, $agencyId);

        /* -------- platform-only totals -------- */
        $platform = [];
        if ($isPlatform && $user->hasPermission('admin.view_reports', null)) {
            $platform = [
                'users' => $userQuery->count(),
                'agencies_total' => $agencyQuery->count(),
                'agencies_pending' => (clone $agencyQuery)->where('status', 'pending')->count(),
                'agencies_unverified' => (clone $agencyQuery)->where('verification_status', '!=', 'verified')->count(),
                'documents' => Document::count(),
                'audit_events_' . $days . 'd' => AuditLog::where('created_at', '>=', $since)->count(),
            ];
        }

        return response()->json([
            'success' => true,
            'period_days' => $days,
            'scope' => $agencyId ?? 'platform',
            'listings' => $listings,
            'leads' => $leads,
            'finance' => $finance,
            'metrics' => $metrics,
            'series' => $series,
            'platform' => $platform,
        ]);
    }

    /** Daily counts for the dashboard chart. */
    private function timeseries(int $days, ?string $agencyId): array
    {
        $range = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $range[$date] = ['date' => $date, 'listings' => 0, 'leads' => 0, 'payments' => 0];
        }

        $listingRows = Property::query()
            ->whereNull('deleted_at')
            ->where('created_at', '>=', now()->subDays($days))
            ->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId))
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')->pluck('c', 'd');

        $leadRows = Inquiry::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId))
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')->pluck('c', 'd');

        $paymentRows = DB::table('transaction_payments')
            ->where('received_at', '>=', now()->subDays($days))
            ->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId))
            ->selectRaw('DATE(received_at) as d, COUNT(*) as c')
            ->groupBy('d')->pluck('c', 'd');

        foreach ($range as $date => $row) {
            $range[$date]['listings'] = (int) ($listingRows[$date] ?? 0);
            $range[$date]['leads'] = (int) ($leadRows[$date] ?? 0);
            $range[$date]['payments'] = (int) ($paymentRows[$date] ?? 0);
        }

        return array_values($range);
    }
}