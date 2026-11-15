<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencyInvitation;
use App\Models\AgencyMember;
use App\Models\BusinessRecord;
use App\Models\Commission;
use App\Models\Dealer;
use App\Models\DealerFile;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Inquiry;
use App\Models\PaymentSchedule;
use App\Models\Property;
use App\Models\Role;
use App\Models\SubscriptionInvoice;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    use AuthorizesApi;

    /**
     * GET /api/agency/transactions
     * Agency-scoped or platform-wide list with filters.
     */
    public function index(Request $request)
    {
        $this->requireAny($request, ['view_transactions', 'finance.view', 'admin.view_reports', 'transactions.view']);

        $user = $request->user();
        $agencyId = $this->agencyId($request);

        $query = Transaction::query()->with(['payments', 'commissions', 'dealer', 'businessRecord']);

        if ($agencyId !== null) {
            $query->where('agency_id', $agencyId);
        }

        // Legacy support: if no agency context and user is agent (dealer_id null), scope to their agency
        if ($agencyId === null && $user->hasPermission('finance.view') && !$user->hasPermission('admin.view_reports')) {
            if ($user->dealer_id === null && $user->agency_id !== null) {
                $query->where('agency_id', $user->agency_id);
            }
        }

        if ($request->filled('status')) {
            $query->where('dealStatus', $request->status);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('referenceNo', 'like', "%{$search}%")
                    ->orWhereHas('businessRecord', fn ($q) => $q->where('title', 'like', "%{$search}%"))
                    ->orWhereHas('dealer', fn ($q) => $q->where('fullName', 'like', "%{$search}%"));
            });
        }

        $transactions = $query->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'pagination' => [
                'total' => $transactions->total(),
                'per_page' => $transactions->perPage(),
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $agencyId = $this->agencyId($request);
        $this->requireAny($request, ['manage_deals', 'finance.manage_deals']);

        $data = $request->validate([
            'propertyId' => 'nullable|string|max:256',
            'propertyTitle' => 'required|string|max:255',
            'plotNumber' => 'nullable|string|max:50',
            'sector' => 'nullable|string|max:100',
            'sellerName' => 'required|string|max:255',
            'buyerName' => 'required|string|max:255',
            'dealerId' => 'nullable|string|max:64',
            'transactionType' => 'nullable|in:Sale,Rent,Lease,Transfer',
            'agreementAmountPKR' => 'required|numeric|min:0|max:999999999999',
            'advancePaymentPKR' => 'nullable|numeric|min:0|max:999999999999',
            'commissionAmountPKR' => 'nullable|numeric|min:0|max:999999999999',
            'dealStatus' => 'nullable|in:Draft,Negotiation,Agreed,Completed,Cancelled',
            'transactionDate' => 'nullable|date',
        ]);

        $deal = DB::transaction(function () use ($data, $agencyId, $request) {
            $deal = Transaction::create([
                'agency_id' => $agencyId,
                'property_id' => $data['propertyId'] ?? null,
                'propertyTitle' => $data['propertyTitle'],
                'plotNumber' => $data['plotNumber'],
                'sector' => $data['sector'],
                'sellerName' => $data['sellerName'],
                'buyerName' => $data['buyerName'],
                'dealer_id' => $data['dealerId'] ?? null,
                'transactionType' => $data['transactionType'] ?? 'Sale',
                'agreementAmountPKR' => (int) $data['agreementAmountPKR'],
                'advancePaymentPKR' => (int) ($data['advancePaymentPKR'] ?? 0),
                'commissionAmountPKR' => (int) ($data['commissionAmountPKR'] ?? 0),
                'dealStatus' => $data['dealStatus'] ?? 'Draft',
                'transactionDate' => $data['transactionDate'] ?? now()->toDateString(),
                'created_by' => $request->user()->id,
            ]);

            if ($deal->advancePaymentPKR > 0) {
                TransactionPayment::create([
                    'agency_id' => $agencyId,
                    'transaction_id' => $deal->id,
                    'amount' => $deal->advancePaymentPKR,
                    'type' => 'advance',
                    'method' => 'cash',
                    'received_at' => now(),
                ]);
            }

            return $deal->load(['payments', 'commissions']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Deal created.',
            'transaction' => $deal,
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        $agencyId = $this->agencyId($request);
        $this->requireAny($request, ['manage_deals', 'finance.manage_deals']);

        $transaction = $this->findScoped($agencyId, $id, $request->user());

        if ($transaction->isVoided()) {
            return response()->json([
                'success' => false,
                'message' => 'Voided deals are read-only.',
            ], 422);
        }

        // Prevent editing Completed/Cancelled deals
        if (in_array($transaction->dealStatus, ['Completed', 'Cancelled'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Completed or cancelled deals are read-only.',
            ], 422);
        }

        $data = $request->validate([
            'propertyTitle' => 'sometimes|string|max:255',
            'sellerName' => 'sometimes|string|max:255',
            'buyerName' => 'sometimes|string|max:255',
            'dealerId' => 'nullable|string|max:64',
            'agreementAmountPKR' => 'sometimes|numeric|min:0|max:999999999999',
            'advancePaymentPKR' => 'nullable|numeric|min:0|max:999999999999',
            'commissionAmountPKR' => 'nullable|numeric|min:0|max:999999999999',
            'dealStatus' => 'sometimes|string|max:50',
            'status' => 'sometimes|string|max:50',
            'transactionDate' => 'nullable|date',
        ]);

        // Prevent creating deal directly with Completed status
        if (isset($data['dealStatus']) && in_array($data['dealStatus'], ['Completed', 'Cancelled'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'A new deal cannot be created directly in a terminal state.',
            ], 422);
        }

        $transaction->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Deal updated.',
            'transaction' => $transaction->fresh()->load(['payments', 'commissions']),
        ]);
    }

    public function show(Request $request, string $id)
    {
        $agencyId = $this->agencyId($request);
        $this->requireAny($request, ['view_transactions', 'finance.view', 'transactions.view']);

        $transaction = $this->findScoped($agencyId, $id, $request->user());

        return response()->json([
            'success' => true,
            'transaction' => $transaction->load(['payments', 'commissions', 'dealer', 'businessRecord']),
        ]);
    }

    public function destroy(Request $request, string $id)
    {
        $agencyId = $this->agencyId($request);
        $this->requireAny($request, ['manage_deals', 'finance.manage_deals']);

        $transaction = $this->findScoped($agencyId, $id, $request->user());

        if (!$transaction->isVoided()) {
            return response()->json([
                'success' => false,
                'message' => 'Only voided transactions can be deleted.',
            ], 422);
        }

        $transaction->delete();

        return response()->json(['success' => true, 'message' => 'Transaction deleted.']);
    }

    public function storePayment(Request $request, string $transactionId)
    {
        $agencyId = $this->agencyId($request);
        $this->requireAny($request, ['finance.manage_payments', 'manage_payments']);

        $transaction = $this->findScoped($agencyId, $transactionId, $request->user());

        if ($transaction->isVoided()) {
            return response()->json(['success' => false, 'message' => 'Cannot record payments on a voided deal.'], 422);
        }

        $request->validate([
            'amount' => [
                'required',
                'numeric',
                'max:999999999999',
                function ($attribute, $value, $fail) use ($request) {
                    if ($value < 0 && ($request->type ?? 'advance') !== 'refund') {
                        $fail('Negative amounts are only allowed for refund payments.');
                    }
                },
            ],
            'type' => 'nullable|in:token,advance,installment,refund',
            'method' => 'nullable|in:cash,bank_transfer,cheque,online,other',
            'reference' => 'nullable|string|max:120',
            'receipt_no' => 'nullable|string|max:60',
            'received_at' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Ledger integrity: the payment insert and the derived status update
        // are one transaction on the locked deal row â€” concurrent payments
        // cannot overshoot the agreement amount silently.
        $result = DB::transaction(function () use ($transaction, $request): array {
            $locked = Transaction::where('id', $transaction->id)->lockForUpdate()->first();

            if ($locked->isVoided()) {
                return [null, null];
            }

            // Refunds are stored as negative amounts so they reduce collected totals.
            $isRefund = $request->get('type', 'advance') === 'refund';
            $amount = (float) $request->amount;
            if ($isRefund) {
                $amount = -abs($amount);
            }

            // Overpayment guard: collected + amount must not exceed agreement
            $existingCollected = (float) $locked->totalPaid();
            $agreementAmount = (float) $locked->agreementAmountPKR;
            if ($existingCollected + $amount > $agreementAmount) {
                return [null, ['overpayment' => true, 'collected' => $existingCollected, 'agreement' => $agreementAmount]];
            }

            $payment = TransactionPayment::create([
                'agency_id' => $locked->agency_id,
                'transaction_id' => $locked->id,
                'amount' => $amount,
                'type' => $request->get('type', 'advance'),
                'method' => $request->get('method', 'cash'),
                'reference' => $request->reference,
                'receipt_no' => $request->receipt_no,
                'received_at' => $request->received_at ? now()->parse($request->received_at) : now(),
                'notes' => $request->notes,
            ]);

            // Update deal status based on payment totals
            $locked->refresh();
            $locked->recalculateStatus();

            return [$payment, null];
        });

        if (isset($result[1]['overpayment'])) {
            $over = $result[1];
            return response()->json([
                'success' => false,
                'message' => 'Overpayment: collected (' . number_format($over['collected'], 2) . ') plus amount (' . number_format($amount, 2) . ') exceeds agreement (' . number_format($over['agreement'], 2) . ').',
            ], 422);
        }

        $payment = $result[0];
        $locked = $payment->transaction;

        $collected = (float) $locked->totalPaid();
        $agreement = (float) $locked->agreementAmountPKR;

        return response()->json([
            'success' => true,
            'message' => 'Payment recorded.',
            'payment' => $payment,
            'totals' => [
                'collected' => $collected,
                'agreement' => $agreement,
                'balance' => max(0, $agreement - $collected),
            ],
        ], 201);
    }

    /** DELETE /api/agency/payments/{id} â€” correction, heavily audited. */
    public function destroyPayment(Request $request, int $id)
    {
        $agencyId = $this->agencyId($request);
        $this->requireAny($request, ['finance.manage_payments', 'manage_payments']);

        $payment = TransactionPayment::where('id', $id);
        if ($agencyId !== null) {
            $payment->where('agency_id', $agencyId);
        }
        $payment = $payment->firstOrFail();

        $transaction = Transaction::where('id', $payment->transaction_id)->first();

        $request->validate(['reason' => 'required|string|min:3|max:500']);

        $this->writeAudit($request, 'payment.delete', $payment->id, "Payment {$payment->amount} removed ({$request->reason})", [
            'agency_id' => $payment->agency_id,
            'entity_type' => 'transaction_payment',
            'entity_id' => (string) $payment->id,
            'metadata' => ['amount' => $payment->amount, 'reason' => $request->reason],
        ]);

        // Soft delete instead of hard delete for audit trail
        $payment->deleted_at = now();
        $payment->save();

        if ($transaction) {
            $transaction->recalculateStatus();
        }

        return response()->json(['success' => true, 'message' => 'Payment voided.']);
    }

    public function createSchedule(Request $request, string $transactionId)
    {
        $agencyId = $this->agencyId($request);
        $this->requireAny($request, ['finance.manage_payments', 'manage_payments']);

        $transaction = $this->findScoped($agencyId, $transactionId, $request->user());

        if ($transaction->isVoided()) {
            return response()->json(['success' => false, 'message' => 'Voided deal cannot have payment schedules.'], 422);
        }

        $data = $request->validate([
            'schedules' => 'required|array|min:1|max:120',
            'schedules.*.installment_no' => 'required|integer|min:1|max:9999',
            'schedules.*.due_date' => 'required|date',
            'schedules.*.amount' => 'required|numeric|min:0.01|max:999999999999',
            'schedules.*.notes' => 'nullable|string|max:500',
        ]);

        $schedules = [];
        foreach ($data['schedules'] as $row) {
            $schedules[] = PaymentSchedule::updateOrCreate(
                [
                    'transaction_id' => $transaction->id,
                    'installment_no' => $row['installment_no'],
                ],
                [
                    'due_date' => $row['due_date'],
                    'amount' => $row['amount'],
                    'notes' => $row['notes'] ?? null,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'schedules' => $schedules,
        ], 201);
    }

    public function createCommission(Request $request, string $transactionId)
    {
        $agencyId = $this->agencyId($request);
        $this->requireAny($request, ['finance.manage_commissions', 'manage_commissions']);

        $transaction = $this->findScoped($agencyId, $transactionId, $request->user());

        $data = $request->validate([
            'amount' => 'required|numeric|max:999999999999',
            'rate' => 'nullable|numeric|min:0|max:100',
            'dealer_id' => 'nullable|string|max:64',
            'user_id' => 'nullable|integer',
            'beneficiary_type' => 'nullable|in:agency,dealer,agent',
            'notes' => 'nullable|string|max:1000',
        ]);

        $commission = Commission::create([
            'agency_id' => $transaction->agency_id,
            'transaction_id' => $transaction->id,
            'amount' => (float) $data['amount'],
            'rate' => $data['rate'] ?? null,
            'dealer_id' => $data['dealer_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'beneficiary_type' => $data['beneficiary_type'] ?? 'dealer',
            'status' => 'pending',
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'commission' => $commission,
        ], 201);
    }

    public function updateCommission(Request $request, string $id)
    {
        $agencyId = $this->agencyId($request);
        $this->requireAny($request, ['finance.manage_commissions', 'manage_commissions']);

        $commission = Commission::where('id', $id)
            ->where('agency_id', $agencyId)
            ->firstOrFail();

        $request->validate([
            'status' => 'required|in:pending,accrued,paid,cancelled',
            'notes' => 'nullable|string|max:1000',
        ]);

        $allowed = ['pending', 'accrued', 'paid', 'cancelled'];
        if (!in_array($request->status, $allowed, true)) {
            return response()->json(['success' => false, 'message' => 'Invalid commission status.'], 422);
        }

        $from = $commission->status;
        $commission->update([
            'status' => $request->status,
            'notes' => $request->notes ?? $commission->notes,
        ]);

        $this->writeAudit($request, 'commission.status_change', $commission->id, "Commission status changed from {$from} to {$request->status}", [
            'agency_id' => $commission->agency_id,
            'entity_type' => 'commission',
            'entity_id' => $commission->id,
            'metadata' => ['from' => $from, 'to' => $request->status],
        ]);

        return response()->json([
            'success' => true,
            'commission' => $commission->fresh(),
        ]);
    }

    public function createExpense(Request $request)
    {
        $agencyId = $this->agencyId($request);
        $this->requireAny($request, ['finance.manage_expenses', 'manage_expenses']);

        $data = $request->validate([
            'expense_date' => 'required|date|before_or_equal:today',
            'category' => 'nullable|string|in:' . implode(',', Expense::CATEGORIES),
            'amount_pkr' => 'required|integer|min:1|max:9999999999',
            'description' => 'nullable|string|max:500',
            'payment_method' => 'nullable|string|in:' . implode(',', Expense::PAYMENT_METHODS),
            'reference' => 'nullable|string|max:120',
        ]);

        $expense = Expense::create([
            'id' => 'exp_' . bin2hex(random_bytes(8)),
            'agency_id' => $agencyId,
            'expense_date' => $data['expense_date'],
            'category' => $data['category'] ?? null,
            'amount_pkr' => $data['amount_pkr'],
            'description' => $data['description'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
            'reference' => $data['reference'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'expense' => $expense,
        ], 201);
    }

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
