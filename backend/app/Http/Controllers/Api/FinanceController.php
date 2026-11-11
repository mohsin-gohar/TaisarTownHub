<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Commission;
use App\Models\Dealer;
use App\Models\PaymentSchedule;
use App\Models\Property;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Finance: deals, payment ledger, installment schedules and commissions.
 *
 * Server-side invariants (mirrors frontend/src/domain/transactions.ts):
 *  - stage order & transitions enforced here, not in the browser
 *  - money amounts validated; ledger rows are append-only + audited
 *  - every row is agency-scoped from the membership context
 */
class FinanceController extends Controller
{
    use AuthorizesApi;

    /** Canonical stage order — must match frontend TRANSACTION_STAGES. */
    public const STAGES = [
        'Draft', 'Negotiation', 'Agreement Signed', 'Token Paid', 'Advance Received',
        'Payment Pending', 'Partially Paid', 'Fully Paid', 'Under Transfer', 'Completed',
    ];

    public const TERMINAL = ['Completed', 'Cancelled'];

    /* ==================== Deals ==================== */

    public function index(Request $request)
    {
        $agencyId = $this->agencyId($request);
        $this->requireAny($request, ['finance.view', 'view_transactions', 'view_my_deals', 'view_commissions']);

        $query = Transaction::query();

        if ($agencyId !== null) {
            $query->forAgency($agencyId);
        } elseif (!$request->user()->hasPermission('finance.view', null)
            && !$request->user()->hasPermission('view_transactions', null)) {
            // Legacy dealer scoping (no agency membership)
            $query->where('dealerId', $request->user()->dealer_id);
        }

        // Record-level: users without full view only see their own deals
        $canSeeAll = $this->can($request, 'finance.view')
            || $this->can($request, 'view_transactions');
        if (!$canSeeAll && $request->user()->dealer_id) {
            $query->where('dealerId', $request->user()->dealer_id);
        }

        if ($request->filled('dealStatus')) {
            $query->where('dealStatus', $request->dealStatus);
        }
        if ($request->filled('dealerId')) {
            $query->where('dealerId', $request->dealerId);
        }
        if ($request->filled('search')) {
            $s = addcslashes((string) $request->search, '%_\\');
            $query->where(function ($q) use ($s) {
                $q->where('dealNumber', 'like', "%{$s}%")
                    ->orWhere('buyerName', 'like', "%{$s}%")
                    ->orWhere('sellerName', 'like', "%{$s}%")
                    ->orWhere('propertyTitle', 'like', "%{$s}%");
            });
        }
        if ($request->boolean('include_voided') === false) {
            $query->whereNull('voided_at');
        }

        if ($request->has('page')) {
            $page = $query->with(['payments', 'commissions'])
                ->latest()
                ->paginate(min((int) $request->get('per_page', 20), 100));

            return response()->json([
                'success' => true,
                'transactions' => $this->decorate($page->items()),
                'pagination' => [
                    'current_page' => $page->currentPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                    'last_page' => $page->lastPage(),
                ],
            ]);
        }

        $rows = $query->with(['payments', 'commissions'])->latest()
            ->limit(min((int) $request->get('per_page', 100), 300))->get();

        return response()->json($this->decorate($rows->all()));
    }

    public function show(Request $request, string $id)
    {
        $agencyId = $this->agencyId($request);
        $this->requireAny($request, ['finance.view', 'view_transactions', 'view_my_deals', 'view_commissions']);

        $transaction = $this->findScoped($agencyId, $id, $request->user());

        return response()->json([
            'success' => true,
            'transaction' => $this->decorate([$transaction])[0],
            'payments' => $transaction->payments,
            'schedules' => $transaction->schedules,
            'commissions' => $transaction->commissions()->with('dealer:id,fullName')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $agencyId = $this->agencyId($request);
        $this->requireAny($request, ['finance.create_transaction', 'create_transactions']);

        $request->validate([
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
            'dealStatus' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:50',
            'transactionDate' => 'nullable|date',
            'paymentMethod' => 'nullable|string|max:50',
            'paymentStatus' => 'nullable|string|max:50',
            'internalRemarks' => 'nullable|string|max:4000',
        ]);

        if ($request->filled('propertyId')) {
            $propertyQuery = Property::where('id', $request->propertyId)->whereNull('deleted_at');
            if ($agencyId !== null) {
                $propertyQuery->where('agency_id', $agencyId);
            }
            if (!$propertyQuery->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unknown property for your agency.',
                ], 422);
            }
        }

        if ($request->filled('dealerId')) {
            $dealerQuery = Dealer::where('id', $request->dealerId);
            if ($agencyId !== null) {
                $dealerQuery->where('agency_id', $agencyId);
            }
            if (!$dealerQuery->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dealer is not linked to your agency.',
                ], 422);
            }
        }

        $requestedStatus = $request->dealStatus ?? $request->status ?? 'Draft';
        if (!in_array($requestedStatus, self::STAGES, true)) {
            $requestedStatus = 'Draft';
        }

        $transaction = DB::transaction(function () use ($request, $agencyId, $requestedStatus) {
            // Race-safe unique deal number (unique index on dealNumber)
            $year = now()->format('Y');
            $prefix = 'TXN-' . $year . '-';
            $attempt = 0;
            do {
                $last = Transaction::where('dealNumber', 'like', $prefix . '%')
                    ->lockForUpdate()
                    ->orderByDesc('dealNumber')
                    ->value('dealNumber');
                $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
                $dealNumber = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
                $attempt++;
            } while ($attempt < 20 && Transaction::where('dealNumber', $dealNumber)->exists());

            return Transaction::create([
                'id' => 'tx_' . bin2hex(random_bytes(8)),
                'dealNumber' => $dealNumber,
                // Legacy schema keeps propertyId NOT NULL with no default —
                // unlinked deals store an empty string rather than null.
                'propertyId' => (string) ($request->propertyId ?? ''),
                'propertyTitle' => $request->propertyTitle,
                'plotNumber' => $request->plotNumber,
                'sector' => $request->sector,
                'sellerName' => $request->sellerName,
                'buyerName' => $request->buyerName,
                'dealerId' => $request->dealerId,
                'dealerName' => $request->dealerId ? optional(Dealer::find($request->dealerId))->fullName : null,
                'transactionType' => $request->get('transactionType', 'Sale'),
                'agreementAmountPKR' => (int) $request->agreementAmountPKR,
                'advancePaymentPKR' => (int) $request->get('advancePaymentPKR', 0),
                'remainingBalancePKR' => max(0, (int) $request->agreementAmountPKR - (int) $request->get('advancePaymentPKR', 0)),
                'commissionAmountPKR' => (int) $request->get('commissionAmountPKR', 0),
                'commissionStatus' => 'Pending',
                'dealStatus' => $requestedStatus,
                'agency_id' => $agencyId,
                'created_by' => $request->user()->id,
            ]);
        });

        $this->writeAudit($request, 'transaction.create', $transaction->id, "Deal created: {$transaction->dealNumber}", [
            'agency_id' => $agencyId,
            'entity_type' => 'transaction',
            'entity_id' => $transaction->id,
            'metadata' => [
                'agreementAmountPKR' => $transaction->agreementAmountPKR,
                'buyer' => $transaction->buyerName,
                'dealerId' => $transaction->dealerId,
            ],
        ]);

        return response()->json([
            'success' => true,
            'transaction' => $this->decorate([$transaction])[0],
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        $agencyId = $this->agencyId($request);
        $this->requireAny($request, ['finance.update_transaction', 'update_transactions']);

        $transaction = $this->findScoped($agencyId, $id, $request->user());

        if ($transaction->isVoided()) {
            return response()->json([
                'success' => false,
                'message' => 'Voided deals are read-only.',
            ], 409);
        }

        $request->validate([
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
            'paymentMethod' => 'nullable|string|max:50',
            'paymentStatus' => 'nullable|string|max:50',
            'internalRemarks' => 'nullable|string|max:4000',
        ]);

        if ($request->filled('dealerId')) {
            $dealerQuery = Dealer::where('id', $request->dealerId);
            if ($agencyId !== null) {
                $dealerQuery->where('agency_id', $agencyId);
            }
            if (!$dealerQuery->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dealer is not linked to your agency.',
                ], 422);
            }
        }

        $payload = $request->only([
            'propertyTitle', 'sellerName', 'buyerName', 'dealerId',
            'agreementAmountPKR', 'advancePaymentPKR', 'commissionAmountPKR',
            'transactionDate', 'paymentMethod', 'paymentStatus', 'internalRemarks',
        ]);
        $payload = array_filter($payload, fn ($v) => $v !== null);

        // Status transition through the server-side state machine
        $newStatus = $request->dealStatus ?? $request->status;
        if ($newStatus !== null && $newStatus !== $transaction->dealStatus) {
            $check = $this->validateTransition($transaction->dealStatus, $newStatus);
            if (!$check['ok']) {
                return response()->json([
                    'success' => false,
                    'message' => $check['error'],
                ], 422);
            }
            $payload['dealStatus'] = $newStatus;
        }

        if (array_key_exists('agreementAmountPKR', $payload) || array_key_exists('advancePaymentPKR', $payload)) {
            $agreement = (int) ($payload['agreementAmountPKR'] ?? $transaction->agreementAmountPKR);
            $advance = (int) ($payload['advancePaymentPKR'] ?? $transaction->advancePaymentPKR);
            $payload['remainingBalancePKR'] = max(0, $agreement - $advance);
        }

        $payload['updated_by'] = $request->user()->id;
        $transaction->fill($payload);
        $transaction->save();

        $this->writeAudit($request, 'transaction.update', $transaction->id, "Deal updated: {$transaction->dealNumber}", [
            'agency_id' => $transaction->agency_id,
            'entity_type' => 'transaction',
            'entity_id' => $transaction->id,
            'metadata' => ['fields' => array_keys($payload), 'dealStatus' => $transaction->dealStatus],
        ]);

        return response()->json([
            'success' => true,
            'transaction' => $this->decorate([$transaction])[0],
        ]);
    }

    /** POST /api/agency/transactions/{id}/void — reason required, audited. */
    public function void(Request $request, string $id)
    {
        $agencyId = $this->agencyId($request);
        $user = $request->user();

        // Platform: legacy `void_transactions`; agency: finance.update_transaction
        if (!$this->can($request, 'void_transactions') && !$this->can($request, 'finance.update_transaction')) {
            abort(403, 'Permission denied.');
        }

        $transaction = $this->findScoped($agencyId, $id, $user);

        $request->validate([
            'reason' => 'required|string|min:3|max:1000',
        ]);

        if ($transaction->isVoided()) {
            return response()->json([
                'success' => false,
                'message' => 'Deal is already voided.',
            ], 422);
        }

        $transaction->voided_at = now();
        $transaction->void_reason = $request->reason;
        $transaction->voided_by = $user->id;
        $transaction->save();

        $this->writeAudit($request, 'transaction.void', $transaction->id, "Deal voided: {$transaction->dealNumber}", [
            'agency_id' => $transaction->agency_id,
            'entity_type' => 'transaction',
            'entity_id' => $transaction->id,
            'metadata' => ['reason' => $request->reason],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Deal voided. The record is kept for audit.',
            'transaction' => $transaction,
        ]);
    }

    /** POST /api/agency/transactions/{id}/approve */
    public function approve(Request $request, string $id)
    {
        $agencyId = $this->agencyId($request);
        $this->requireAny($request, ['approve_transactions', 'finance.update_transaction', 'update_transactions']);

        $transaction = $this->findScoped($agencyId, $id, $request->user());

        if ($transaction->isVoided()) {
            return response()->json(['success' => false, 'message' => 'Voided deals cannot be approved.'], 422);
        }

        $transaction->approved_at = now();
        $transaction->approved_by = $request->user()->id;
        $transaction->save();

        $this->writeAudit($request, 'transaction.approve', $transaction->id, "Deal approved: {$transaction->dealNumber}", [
            'agency_id' => $transaction->agency_id,
            'entity_type' => 'transaction',
            'entity_id' => $transaction->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Deal approved.',
            'transaction' => $transaction,
        ]);
    }

    /* ==================== Payments ==================== */

    public function payments(Request $request, string $transactionId)
    {
        $agencyId = $this->agencyId($request);
        $this->requireAny($request, ['finance.view', 'view_transactions', 'view_my_deals', 'view_commissions']);

        $transaction = $this->findScoped($agencyId, $transactionId, $request->user());

        return response()->json([
            'success' => true,
            'payments' => $transaction->payments,
            'totals' => [
                'collected' => $transaction->totalPaid(),
                'agreement' => (float) $transaction->agreementAmountPKR,
                'balance' => $transaction->outstandingBalance(),
            ],
        ]);
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
            'amount' => 'required|numeric|min:0.01|max:999999999999',
            'type' => 'nullable|in:token,advance,installment,refund',
            'method' => 'nullable|in:cash,bank_transfer,cheque,online,other',
            'reference' => 'nullable|string|max:120',
            'receipt_no' => 'nullable|string|max:60',
            'received_at' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $payment = TransactionPayment::create([
            'agency_id' => $transaction->agency_id,
            'transaction_id' => $transaction->id,
            'amount' => (float) $request->amount,
            'type' => $request->get('type', 'advance'),
            'method' => $request->get('method', 'cash'),
            'reference' => $request->reference,
            'receipt_no' => $request->receipt_no,
            'received_at' => $request->received_at ? now()->parse($request->received_at) : now(),
            'recorded_by' => $request->user()->id,
            'notes' => $request->notes,
        ]);

        // Derive payment status from the ledger
        $collected = $transaction->totalPaid();
        $agreement = (float) $transaction->agreementAmountPKR;
        if ($agreement > 0 && $collected >= $agreement) {
            $transaction->paymentStatus = 'Fully Paid';
        } elseif ($collected > 0) {
            $transaction->paymentStatus = 'Partially Paid';
        }
        $transaction->advancePaymentPKR = (int) $collected;
        $transaction->remainingBalancePKR = max(0, (int) $agreement - (int) $collected);
        $transaction->save();

        $this->writeAudit($request, 'payment.record', $payment->id, "Payment {$payment->amount} recorded on {$transaction->dealNumber}", [
            'agency_id' => $transaction->agency_id,
            'entity_type' => 'transaction_payment',
            'entity_id' => (string) $payment->id,
            'metadata' => [
                'amount' => $payment->amount,
                'method' => $payment->method,
                'transaction' => $transaction->dealNumber,
                'collected_total' => $collected,
            ],
        ]);

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

    /** DELETE /api/agency/payments/{id} — correction, heavily audited. */
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

        $payment->delete();

        if ($transaction) {
            $collected = $transaction->totalPaid();
            $agreement = (float) $transaction->agreementAmountPKR;
            $transaction->advancePaymentPKR = (int) $collected;
            $transaction->remainingBalancePKR = max(0, (int) $agreement - (int) $collected);
            $transaction->paymentStatus = $collected <= 0 ? 'Pending'
                : ($collected >= $agreement && $agreement > 0 ? 'Fully Paid' : 'Partially Paid');
            $transaction->save();
        }

        return response()->json(['success' => true, 'message' => 'Payment removed and totals recalculated.']);
    }

    /* ==================== Schedules ==================== */

    public function storeSchedules(Request $request, string $transactionId)
    {
        $agencyId = $this->agencyId($request);
        $this->requireAny($request, ['finance.manage_payments', 'manage_payments']);

        $transaction = $this->findScoped($agencyId, $transactionId, $request->user());

        $request->validate([
            'schedules' => 'required|array|min:1|max:120',
            'schedules.*.installment_no' => 'required|integer|min:1|max:9999',
            'schedules.*.due_date' => 'required|date',
            'schedules.*.amount' => 'required|numeric|min:0.01|max:999999999999',
            'schedules.*.notes' => 'nullable|string|max:500',
        ]);

        foreach ($request->schedules as $row) {
            PaymentSchedule::updateOrCreate(
                [
                    'transaction_id' => $transaction->id,
                    'installment_no' => $row['installment_no'],
                ],
                [
                    'agency_id' => $transaction->agency_id,
                    'due_date' => $row['due_date'],
                    'amount' => (float) $row['amount'],
                    'notes' => $row['notes'] ?? null,
                ]
            );
        }

        // Mark overdue
        PaymentSchedule::where('transaction_id', $transaction->id)
            ->where('status', 'pending')
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);

        $this->writeAudit($request, 'schedule.save', $transaction->id, "Installment schedule saved for {$transaction->dealNumber}", [
            'agency_id' => $transaction->agency_id,
            'entity_type' => 'transaction',
            'entity_id' => $transaction->id,
            'metadata' => ['installments' => count($request->schedules)],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Installment schedule saved.',
            'schedules' => $transaction->schedules,
        ]);
    }

    /* ==================== Commissions ==================== */

    public function commissions(Request $request)
    {
        $agencyId = $this->agencyId($request);
        $this->requireAny($request, ['finance.view', 'view_commissions', 'view_my_commission']);

        $query = Commission::query()->with(['transaction:id,dealNumber,propertyTitle', 'dealer:id,fullName']);

        if ($agencyId !== null) {
            $query->forAgency($agencyId);
        }

        $user = $request->user();
        $canSeeAll = $this->can($request, 'finance.view')
            || $this->can($request, 'view_commissions');
        if (!$canSeeAll) {
            $query->where('dealer_id', $user->dealer_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $page = $query->latest()->paginate(min((int) $request->get('per_page', 25), 100));

        return response()->json([
            'success' => true,
            'commissions' => $page->items(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function storeCommission(Request $request, string $transactionId)
    {
        $agencyId = $this->agencyId($request);
        $this->requireAny($request, ['finance.manage_payments', 'manage_payments', 'pay_commissions']);

        $transaction = $this->findScoped($agencyId, $transactionId, $request->user());

        $request->validate([
            'amount' => 'required|numeric|min:0.01|max:999999999999',
            'rate' => 'nullable|numeric|min:0|max:100',
            'dealer_id' => 'nullable|string|max:64',
            'user_id' => 'nullable|integer',
            'beneficiary_type' => 'nullable|in:agency,dealer,agent',
            'notes' => 'nullable|string|max:1000',
        ]);

        $commission = Commission::create([
            'agency_id' => $transaction->agency_id,
            'transaction_id' => $transaction->id,
            'dealer_id' => $request->dealer_id,
            'user_id' => $request->user_id,
            'beneficiary_type' => $request->get('beneficiary_type', 'dealer'),
            'amount' => (float) $request->amount,
            'rate' => $request->rate,
            'status' => 'pending',
            'notes' => $request->notes,
        ]);

        $this->writeAudit($request, 'commission.create', $commission->id, "Commission accrued on {$transaction->dealNumber}", [
            'agency_id' => $transaction->agency_id,
            'entity_type' => 'commission',
            'entity_id' => (string) $commission->id,
            'metadata' => ['amount' => $commission->amount, 'rate' => $commission->rate],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commission entry created.',
            'commission' => $commission,
        ], 201);
    }

    public function updateCommission(Request $request, int $id)
    {
        $agencyId = $this->agencyId($request);
        $this->requireAny($request, ['finance.manage_payments', 'manage_payments', 'pay_commissions']);

        $query = Commission::where('id', $id);
        if ($agencyId !== null) {
            $query->where('agency_id', $agencyId);
        }
        $commission = $query->firstOrFail();

        $request->validate([
            'status' => 'required|in:pending,accrued,paid,cancelled',
            'notes' => 'nullable|string|max:1000',
        ]);

        $allowed = ['pending', 'accrued', 'paid', 'cancelled'];
        if (!in_array($request->status, $allowed, true)) {
            return response()->json(['success' => false, 'message' => 'Invalid commission status.'], 422);
        }

        $from = $commission->status;
        $commission->status = $request->status;
        if ($request->status === 'accrued' && !$commission->accrued_at) {
            $commission->accrued_at = now();
        }
        if ($request->status === 'paid') {
            $commission->paid_at = now();
            $commission->paid_by = $request->user()->id;
        }
        if ($request->filled('notes')) {
            $commission->notes = $request->notes;
        }
        $commission->save();

        $this->writeAudit($request, 'commission.status', $commission->id, "Commission {$from} → {$request->status}", [
            'agency_id' => $commission->agency_id,
            'entity_type' => 'commission',
            'entity_id' => (string) $commission->id,
            'metadata' => ['amount' => $commission->amount, 'from' => $from, 'to' => $request->status],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commission updated.',
            'commission' => $commission,
        ]);
    }

    /* ==================== State machine ==================== */

    /**
     * Server-side mirror of frontend validateTransition():
     * forward always allowed; backward only to the neighbouring stage;
     * terminal stages are read-only; Completed only from Under Transfer.
     */
    private function validateTransition(string $from, string $to): array
    {
        if ($from === $to) {
            return ['ok' => true];
        }

        $known = array_merge(self::STAGES, ['Cancelled']);
        if (!in_array($to, $known, true)) {
            return ['ok' => false, 'error' => "Unknown deal status '{$to}'."];
        }

        if (in_array($from, self::TERMINAL, true)) {
            return ['ok' => false, 'error' => "Deal is " . strtolower($from) . " — the record is read-only for bookkeeping."];
        }

        if ($to === 'Completed') {
            if ($from !== 'Under Transfer') {
                return ['ok' => false, 'error' => 'A deal can only be marked Completed after it is Under Transfer at MDA.'];
            }
            return ['ok' => true];
        }

        if ($to === 'Cancelled') {
            return ['ok' => true];
        }

        $fromIdx = array_search($from, self::STAGES, true);
        $toIdx = array_search($to, self::STAGES, true);

        if ($fromIdx === false || $toIdx === false) {
            return ['ok' => false, 'error' => "Unknown deal status '{$from}'."];
        }

        // backward: only neighbouring stage (correction)
        if ($toIdx < $fromIdx && ($fromIdx - $toIdx) > 1) {
            return ['ok' => false, 'error' => "Cannot move from {$from} back to {$to} — only one stage back is allowed."];
        }

        return ['ok' => true];
    }

    /* ==================== Helpers ==================== */

    private function findScoped(?string $agencyId, string $id, $user): Transaction
    {
        $query = Transaction::where('id', $id);

        if ($agencyId !== null) {
            $query->where('agency_id', $agencyId);
        }

        $transaction = $query->first();

        if (!$transaction) {
            abort(404, 'Transaction not found.');
        }

        // Record-level: dealers only ever see their own deals
        $request = request();
        $canSeeAll = $this->can($request, 'finance.view')
            || $this->can($request, 'view_transactions');

        if (!$canSeeAll && $transaction->dealerId !== $user->dealer_id) {
            abort(404, 'Transaction not found.');
        }

        return $transaction;
    }

    /** @param Transaction[] $rows */
    private function decorate(array $rows): array
    {
        return array_map(function (Transaction $t) {
            $payload = $t->toArray();
            $payload['collectedPKR'] = $t->totalPaid();
            $payload['balancePKR'] = $t->outstandingBalance();
            $payload['isVoided'] = $t->isVoided();
            $payload['isApproved'] = $t->isApproved();
            $payload['payments_count'] = $t->payments ? $t->payments->count() : $t->payments()->count();
            return $payload;
        }, $rows);
    }
}
