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
use App\Services\DealNumberAllocator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
use AuthorizesApi;

    /**
     * Find a transaction scoped to the given agency with access control.
     *
     * @param string|null $agencyId The acting agency context (null for platform-wide)
     * @param string $id The transaction ID to find
     * @param User $user The authenticated user
     * @return Transaction
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    protected function findScoped(?string $agencyId, string $id, $user): Transaction
    {
        $query = Transaction::query();

        // Scope to agency if one is set, otherwise check platform access
        if ($agencyId !== null) {
            $query->where('agency_id', $agencyId);
        } elseif (!$user->hasPermission('admin.view_reports')) {
            // Non-admin users without agency context cannot access transactions
            abort(404, 'Transaction not found.');
        }

        $transaction = $query->findOrFail($id);

        // Additional check: if the transaction has an agency_id and the user
        // doesn't have platform-wide access, ensure they can only see their own agency
        if ($transaction->agency_id !== null && $agencyId === null && !$user->hasPermission('admin.view_reports')) {
            abort(404, 'Transaction not found.');
        }

        return $transaction;
    }

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
            'transactions' => $transactions->items(),
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
        // Creating a deal is a finance operation. The previous gate used
        // `manage_deals` / `finance.manage_deals`, which exist in no role or
        // permission catalogue, so no real agency role could ever create a
        // deal. Accept the canonical + legacy finance keys (and keep the old
        // keys for custom integrations).
        $this->requireAny($request, [
            'finance.create_transaction', 'create_transactions',
            'manage_deals', 'finance.manage_deals',
        ]);

        $data = $request->validate([
            'propertyId' => [
                'nullable', 'string', 'max:256',
                Rule::exists('properties', 'id')->where(function ($q) use ($agencyId) {
                    if ($agencyId !== null) {
                        $q->where('agency_id', $agencyId);
                    }
                }),
            ],
            'propertyTitle' => 'required|string|max:255',
            'plotNumber' => 'nullable|string|max:50',
            'sector' => 'nullable|string|max:100',
            'sellerName' => 'required|string|max:255',
            'buyerName' => 'required|string|max:255',
            'dealerId' => 'nullable|string|max:64|exists:dealers,id',
            'transactionType' => 'nullable|in:Sale,Rent,Lease,Transfer',
            'agreementAmountPKR' => 'required|numeric|min:0|max:999999999999',
            'advancePaymentPKR' => 'nullable|numeric|min:0|max:999999999999',
            'commissionAmountPKR' => 'nullable|numeric|min:0|max:999999999999',
            'dealStatus' => 'nullable|string|in:Draft,Negotiation,Agreed,Completed,Cancelled',
            'transactionDate' => 'nullable|date',
        ]);

        // Terminal states (Completed/Cancelled) must be reached through the
        // update state machine — never at creation time.
        if (isset($data['dealStatus']) && in_array($data['dealStatus'], ['Completed', 'Cancelled'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'A new deal cannot be created directly in a terminal state.',
            ], 422);
        }

        // A dealer must belong to the acting agency. Prefer the explicit
        // dealers.agency_id link (set by DealerController); fall back to an
        // active membership for legacy rows that predate the link.
        if (!empty($data['dealerId']) && $agencyId !== null
            && !$this->dealerBelongsToAgency($data['dealerId'], $agencyId)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'dealerId' => ['Dealer does not belong to the acting agency.'],
            ]);
        }

        // Calculate remaining balance and defaults before creating the deal.
        $advance = (int) ($data['advancePaymentPKR'] ?? 0);
        $remainingBalance = (int) max(0, $data['agreementAmountPKR'] - $advance);
        $dealStatus = $data['dealStatus'] ?? 'Draft';
        $year = now()->year;

        $deal = DB::transaction(function () use ($data, $agencyId, $request, $year, $advance, $remainingBalance, $dealStatus) {
            $dealNumber = app(DealNumberAllocator::class)->nextForYear($year);

            $deal = Transaction::create([
                'id' => 'txn-' . Str::uuid(),
                'dealNumber' => $dealNumber,
                'agency_id' => $agencyId,
                // Actual schema columns are propertyId / dealerId.
                // The old property_id / dealer_id keys were silently
                // discarded by mass-assignment, losing attribution.
                'propertyId' => $data['propertyId'] ?? null,
                'propertyTitle' => $data['propertyTitle'],
                'plotNumber' => $data['plotNumber'] ?? null,
                'sector' => $data['sector'] ?? null,
                'sellerName' => $data['sellerName'],
                'buyerName' => $data['buyerName'],
                'dealerId' => $data['dealerId'] ?? null,
                'transactionType' => $data['transactionType'] ?? 'Sale',
                'agreementAmountPKR' => (int) $data['agreementAmountPKR'],
                'advancePaymentPKR' => $advance,
                'remainingBalancePKR' => $remainingBalance,
                'commissionAmountPKR' => (int) ($data['commissionAmountPKR'] ?? 0),
                'dealStatus' => $dealStatus,
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
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Deal created.',
            'transaction' => $deal,
        ], 201);
    }

public function update(Request $request, string $id)
    {
        $agencyId = $this->agencyId($request);
        // Same catalogue gap as store(): use the real update-transaction
        // grants, plus the legacy keys for custom integrations.
        $this->requireAny($request, [
            'finance.update_transaction', 'update_transactions',
            'manage_deals', 'finance.manage_deals',
        ]);

        $transaction = $this->findScoped($agencyId, $id, $request->user());

        if ($transaction->isVoided()) {
            return response()->json([
                'success' => false,
                'message' => 'Voided deals are read-only.',
            ], 409);
        }

        $data = $request->validate([
            'propertyTitle' => 'sometimes|string|max:255',
            'sellerName' => 'sometimes|string|max:255',
            'buyerName' => 'sometimes|string|max:255',
            'dealerId' => 'nullable|string|max:64|exists:dealers,id',
            'agreementAmountPKR' => 'sometimes|numeric|min:0|max:999999999999',
            'advancePaymentPKR' => 'nullable|numeric|min:0|max:999999999999',
            'commissionAmountPKR' => 'nullable|numeric|min:0|max:999999999999',
            'dealStatus' => 'sometimes|string|max:50',
            'status' => 'sometimes|string|max:50',
            'transactionDate' => 'nullable|date',
        ]);

        // Reject a dealer from another agency. Prefer dealers.agency_id and
        // fall back to active memberships for legacy unlinked rows.
        if (!empty($data['dealerId']) && $agencyId !== null
            && !$this->dealerBelongsToAgency($data['dealerId'], $agencyId)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'dealerId' => ['Dealer does not belong to the acting agency.'],
            ]);
        }

        // Handle dealStatus transition with state machine validation
        $newStatus = $data['dealStatus'] ?? $data['status'] ?? null;
        if ($newStatus !== null) {
            $currentStatus = $transaction->dealStatus;

            // Terminal states: Completed and Cancelled are read-only once reached
            if (in_array($currentStatus, ['Completed', 'Cancelled'], true)) {
                $message = $currentStatus === 'Cancelled'
                    ? 'Deal is cancelled — the record is read-only for bookkeeping.'
                    : 'Completed deals are read-only.';
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 422);
            }

            // Validate forward transitions
            $validForward = [
                'Draft' => ['Negotiation', 'Agreement Signed', 'Token Paid', 'Advance Received', 'Payment Pending', 'Partially Paid', 'Fully Paid', 'Under Transfer', 'Cancelled'],
                'Negotiation' => ['Agreement Signed', 'Token Paid', 'Advance Received', 'Payment Pending', 'Partially Paid', 'Fully Paid', 'Under Transfer', 'Cancelled'],
                'Agreement Signed' => ['Token Paid', 'Advance Received', 'Payment Pending', 'Partially Paid', 'Fully Paid', 'Under Transfer', 'Cancelled'],
                'Token Paid' => ['Advance Received', 'Payment Pending', 'Partially Paid', 'Fully Paid', 'Under Transfer', 'Cancelled'],
                'Advance Received' => ['Payment Pending', 'Partially Paid', 'Fully Paid', 'Under Transfer', 'Cancelled'],
                'Payment Pending' => ['Partially Paid', 'Fully Paid', 'Under Transfer', 'Cancelled'],
                'Partially Paid' => ['Fully Paid', 'Under Transfer', 'Cancelled'],
                'Fully Paid' => ['Under Transfer', 'Cancelled'],
                'Under Transfer' => ['Completed', 'Cancelled'],
            ];

            // Check if this is a valid forward transition
            $allowedNext = $validForward[$currentStatus] ?? [];
            if (!in_array($newStatus, $allowedNext, true)) {
                // Check if it's a backward transition (one step back is allowed)
                $backwardMap = [
                    'Negotiation' => 'Draft',
                    'Agreement Signed' => 'Negotiation',
                    'Token Paid' => 'Agreement Signed',
                    'Advance Received' => 'Token Paid',
                    'Payment Pending' => 'Advance Received',
                    'Partially Paid' => 'Payment Pending',
                    'Fully Paid' => 'Partially Paid',
                    'Under Transfer' => 'Fully Paid',
                ];

                $expectedPrev = $backwardMap[$currentStatus] ?? null;
                if ($newStatus !== $expectedPrev) {
                    // Special case for Completed: requires Under Transfer
                    if ($newStatus === 'Completed' && $currentStatus !== 'Under Transfer') {
                        return response()->json([
                            'success' => false,
                            'message' => 'A deal can only be marked Completed after it is Under Transfer at MDA.',
                        ], 422);
                    }

                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid status transition.',
                    ], 422);
                }
            }

            // Handle Cancelled - allowed from any non-terminal state
            if ($newStatus === 'Cancelled') {
                $data['dealStatus'] = 'Cancelled';
            }
        }

        $transaction->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Deal updated.',
            'transaction' => $transaction->fresh()->load(['payments', 'commissions']),
        ]);
    }

    /**
     * POST /api/agency/transactions/{id}/void
     * Void a transaction (mark as read-only for bookkeeping).
     */
    public function void(Request $request, string $id)
    {
        $agencyId = $this->agencyId($request);
        // Voiding is irreversible for bookkeeping. It must require an explicit
        // permission; previously any workspace member could void any deal.
        $this->requireAny($request, ['void_transactions', 'finance.update_transaction', 'update_transactions']);

        $transaction = $this->findScoped($agencyId, $id, $request->user());

        if ($transaction->isVoided()) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction is already voided.',
            ], 422);
        }

        $request->validate([
            'reason' => 'required|string|min:3|max:500',
        ]);

        $transaction->update([
            'voided_at' => now(),
            'void_reason' => $request->reason,
            'voided_by' => $request->user()->id,
        ]);

        $this->writeAudit($request, 'transaction.void', $transaction->id, "Transaction voided: {$request->reason}", [
            'agency_id' => $transaction->agency_id,
            'entity_type' => 'transaction',
            'entity_id' => $transaction->id,
            'metadata' => ['reason' => $request->reason],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transaction voided successfully.',
        ]);
    }

    /**
     * GET /api/agency/expenses
     * List expenses for the agency with totals.
     */
/**
     * POST /api/agency/transactions/{id}/approve
     * Approve a deal (bookkeeping sign-off).
     *
     * The row is locked for the duration of the check so two concurrent
     * approvals cannot both pass the voided-deal guard.
     */
    public function approve(Request $request, string $id)
    {
        $agencyId = $this->agencyId($request);
        $this->requireAny($request, ['approve_transactions', 'finance.update_transaction', 'update_transactions']);

        $transaction = $this->findScoped($agencyId, $id, $request->user());

        $approved = DB::transaction(function () use ($transaction, $request): ?Transaction {
            $locked = Transaction::where('id', $transaction->id)->lockForUpdate()->first();

            if (!$locked || $locked->isVoided()) {
                return null;
            }

            $locked->approved_at = now();
            $locked->approved_by = $request->user()->id;
            $locked->save();

            return $locked;
        });

        if ($approved === null) {
            return response()->json(['success' => false, 'message' => 'Voided deals cannot be approved.'], 422);
        }

        $this->writeAudit($request, 'transaction.approve', $approved->id, "Deal approved: {$approved->dealNumber}", [
            'agency_id' => $approved->agency_id,
            'entity_type' => 'transaction',
            'entity_id' => $approved->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Deal approved.',
            'transaction' => $approved->fresh()->load(['payments', 'commissions']),
        ]);
    }

    public function expensesIndex(Request $request)
    {
        $this->requirePermission($request, 'finance.view');

        $agencyId = $this->agencyId($request);
        if ($agencyId === null) {
            return response()->json([
                'success' => false,
                'message' => 'No agency context.',
            ], 403);
        }

        $query = Expense::where('agency_id', $agencyId)
            ->whereNull('deleted_at');

        // Filter by category if provided
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $expenses = $query->orderByDesc('expense_date')->get();
        $totalAmount = $expenses->sum('amount_pkr');

        return response()->json([
            'success' => true,
            'expenses' => $expenses,
            'totals' => [
                'amount_pkr' => (int) $totalAmount,
            ],
        ]);
    }

    /**
     * POST /api/agency/expenses
     * Create a new expense.
     */
    public function expensesStore(Request $request)
    {
        $this->requirePermission($request, 'finance.manage_payments');

        $agencyId = $this->agencyId($request);
        if ($agencyId === null) {
            return response()->json([
                'success' => false,
                'message' => 'No agency context.',
            ], 403);
        }

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
            'category' => $data['category'] ?? 'other',
            'amount_pkr' => $data['amount_pkr'],
            'description' => $data['description'] ?? null,
            'payment_method' => $data['payment_method'] ?? 'cash',
            'reference' => $data['reference'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $this->writeAudit($request, 'expense.created', $expense->id, "Expense created: {$expense->amount_pkr} PKR", [
            'agency_id' => $agencyId,
            'entity_type' => 'expense',
            'entity_id' => $expense->id,
            'metadata' => ['amount_pkr' => $expense->amount_pkr, 'category' => $expense->category],
        ]);

        return response()->json([
            'success' => true,
            'expense' => $expense,
        ], 201);
    }

    /**
     * DELETE /api/agency/expenses/{id}
     * Soft delete an expense.
     */
    public function expensesDestroy(Request $request, string $id)
    {
        $this->requirePermission($request, 'finance.manage_payments');

        $agencyId = $this->agencyId($request);
        if ($agencyId === null) {
            return response()->json([
                'success' => false,
                'message' => 'No agency context.',
            ], 403);
        }

        $expense = Expense::where('id', $id)
            ->where('agency_id', $agencyId)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $expense->delete(); // Soft delete

        $this->writeAudit($request, 'expense.deleted', $expense->id, "Expense deleted: {$expense->amount_pkr} PKR", [
            'agency_id' => $agencyId,
            'entity_type' => 'expense',
            'entity_id' => $expense->id,
            'metadata' => ['amount_pkr' => $expense->amount_pkr],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Expense deleted successfully.',
        ]);
    }

    /**
     * GET /api/agency/transactions/{id}/payments
     * List all payments for a transaction with totals.
     */
    public function payments(Request $request, string $id)
    {
        $agencyId = $this->agencyId($request);
        $this->requireAny($request, ['view_transactions', 'finance.view', 'transactions.view']);

        $transaction = $this->findScoped($agencyId, $id, $request->user());

        $payments = $transaction->payments()->orderBy('received_at')->get();
        $totalCollected = (float) $payments->sum('amount');
        $agreementAmount = (float) $transaction->agreementAmountPKR;

        return response()->json([
            'success' => true,
            'payments' => $payments,
            'totals' => [
                'collected' => $totalCollected,
                'agreement' => $agreementAmount,
                'balance' => max(0, $agreementAmount - $totalCollected),
            ],
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
        $this->requireAny($request, [
            'finance.update_transaction', 'update_transactions',
            'manage_deals', 'finance.manage_deals',
        ]);

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
        // are one transaction on the locked deal row — concurrent payments
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
                return [null, [
                    'overpayment' => true,
                    'collected' => $existingCollected,
                    'agreement' => $agreementAmount,
                    'amount' => $amount,
                ]];
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
                'message' => 'Overpayment: collected (' . number_format($over['collected'], 2) . ') plus amount (' . number_format($over['amount'], 2) . ') exceeds agreement (' . number_format($over['agreement'], 2) . ').',
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

        // Soft delete via the model's SoftDeletes trait so the audit trail is
        // preserved and aggregate totals automatically exclude voided rows.
        $payment->delete();

        if ($transaction) {
            $transaction->recalculateStatus();
        }

        return response()->json(['success' => true, 'message' => 'Payment voided.']);
    }

    /**
     * POST /api/agency/transactions/{id}/schedules
     * Create/replace the installment schedule of a deal.
     *
     * `payment_schedules.agency_id` is NOT NULL - the schedule rows are always
     * stamped with the owning agency so tenant scoping holds.
     */
    public function storeSchedules(Request $request, string $transactionId)
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
                    'agency_id' => $transaction->agency_id,
                    'due_date' => $row['due_date'],
                    'amount' => $row['amount'],
                    'notes' => $row['notes'] ?? null,
                ]
            );
        }

        // Mark overdue installments (same agency scope).
        PaymentSchedule::where('agency_id', $transaction->agency_id)
            ->where('transaction_id', $transaction->id)
            ->where('status', 'pending')
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);

        $this->writeAudit($request, 'schedule.save', $transaction->id, "Installment schedule saved for {$transaction->dealNumber}", [
            'agency_id' => $transaction->agency_id,
            'entity_type' => 'transaction',
            'entity_id' => $transaction->id,
            'metadata' => ['installments' => count($data['schedules'])],
        ]);

        return response()->json([
            'success' => true,
            'schedules' => $schedules,
        ], 201);
    }

    /**
     * Backwards-compatible alias for the previous internal method name.
     */
    public function createSchedule(Request $request, string $transactionId)
    {
        return $this->storeSchedules($request, $transactionId);
    }

    /**
     * GET /api/agency/commissions
     * Agency-scoped commission ledger.
     *
     * The Commission model has no `forAgency` scope (the previous
     * implementation called one and would have thrown), so the agency filter
     * is applied explicitly here. Dealers without the full view permission
     * only ever see their own commissions.
     */
    public function commissions(Request $request)
    {
        $agencyId = $this->agencyId($request);
        $this->requireAny($request, ['finance.view', 'view_commissions', 'view_my_commission']);

        $query = Commission::query()->with(['transaction:id,dealNumber,propertyTitle', 'dealer:id,fullName']);

        if ($agencyId !== null) {
            $query->where('agency_id', $agencyId);
        }

        $user = $request->user();
        $canSeeAll = $this->can($request, 'finance.view') || $this->can($request, 'view_commissions');
        if (!$canSeeAll) {
            if ($user->dealer_id === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No commission scope for this account.',
                ], 403);
            }
            $query->where('dealer_id', $user->dealer_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $page = $query->orderByDesc('created_at')->orderBy('id')
            ->paginate(min((int) $request->get('per_page', 25), 100));

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

    /**
     * POST /api/agency/transactions/{id}/commissions
     * Accrue a commission entry against a deal.
     *
     * Gate note: `finance.manage_commissions` / `manage_commissions` are NOT
     * defined in the RBAC catalogue, so the previous gate could never pass.
     * The effective grants are `finance.manage_payments` (admin/manager) and
     * the legacy `manage_payments` / `pay_commissions` keys.
     */
    public function storeCommission(Request $request, string $transactionId)
    {
        $agencyId = $this->agencyId($request);
        $this->requireAny($request, ['finance.manage_payments', 'manage_payments', 'pay_commissions']);

        $transaction = $this->findScoped($agencyId, $transactionId, $request->user());

        $data = $request->validate([
            'amount' => 'required|numeric|max:999999999999',
            'rate' => 'nullable|numeric|min:0|max:100',
            'dealer_id' => 'nullable|string|max:64',
            'user_id' => 'nullable|integer',
            'beneficiary_type' => 'nullable|in:agency,dealer,agent',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Beneficiary identifiers are references, not free text: validate that
        // they belong to the deal's tenant before writing to the ledger.
        if (!empty($data['dealer_id']) && !$this->dealerBelongsToAgency((string) $data['dealer_id'], $transaction->agency_id)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'dealer_id' => 'The selected dealer does not belong to this agency.',
            ]);
        }
        if (!empty($data['user_id'])) {
            $member = AgencyMember::where('agency_id', $transaction->agency_id)
                ->where('user_id', (int) $data['user_id'])
                ->where('status', 'active')
                ->exists();
            if (!$member) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'user_id' => 'The selected user is not an active member of this agency.',
                ]);
            }
        }

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

    /**
     * Backwards-compatible alias for the previous internal method name.
     */
    public function createCommission(Request $request, string $transactionId)
    {
        return $this->storeCommission($request, $transactionId);
    }

    public function updateCommission(Request $request, string $id)
    {
        $agencyId = $this->agencyId($request);
        $this->requireAny($request, ['finance.manage_payments', 'manage_payments', 'pay_commissions']);

        // Agency users are confined to their own ledger. A platform user (no
        // agency context) may act on any commission: `commissions()` already
        // lists platform-wide rows for them, and filtering by `agency_id IS
        // NULL` here made every platform payout a 404.
        $commission = Commission::where('id', $id)
            ->when($agencyId !== null, fn ($q) => $q->where('agency_id', $agencyId))
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

    /**
     * Backwards-compatible alias for the previous internal method name.
     *
     * The previous implementation had its own copy of the insert logic, gated
     * on permissions that are not defined in the RBAC catalogue
     * (`finance.manage_expenses` / `manage_expenses`) and without the
     * null-agency guard or the audit trail. It now delegates to the routed
     * implementation so both paths behave identically.
     */
    public function createExpense(Request $request)
    {
        return $this->expensesStore($request);
    }

    private function dealerBelongsToAgency(string $dealerId, string $agencyId): bool
    {
        $dealer = Dealer::find($dealerId);
        if ($dealer === null) {
            return false;
        }
        if ($dealer->agency_id !== null) {
            return $dealer->agency_id === $agencyId;
        }

        // Legacy rows have no agency_id; their only ownership evidence is an
        // active membership carrying the dealer identity.
        return User::where('dealer_id', $dealerId)
            ->whereHas('memberships', fn ($q) => $q->where('agency_id', $agencyId)->where('status', 'active'))
            ->exists();
    }

    public function reports(Request $request)
    {
        // Agency reports require finance.view_reports; platform reports require admin.view_reports
        $this->requireAny($request, ['finance.view_reports', 'admin.view_reports']);

        $agencyId = $this->agencyId($request);

        $data = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $from = now()->parse($data['from'] ?? now()->subDays(29)->toDateString())->startOfDay();
        $to = now()->parse($data['to'] ?? now()->toDateString())->endOfDay();

        // Scope to agency if one is set, otherwise platform-wide
        $agencies = [
            'total' => Agency::query()->when($agencyId !== null, fn ($q) => $q->where('id', $agencyId))->count(),
            'verified' => Agency::query()->when($agencyId !== null, fn ($q) => $q->where('id', $agencyId))
                ->where('verification_status', 'verified')->count(),
            'new_in_range' => Agency::query()->when($agencyId !== null, fn ($q) => $q->where('id', $agencyId))
                ->whereBetween('created_at', [$from, $to])->count(),
        ];

        $deals = Transaction::whereBetween('created_at', [$from, $to]);
        if ($agencyId !== null) {
            $deals->where('agency_id', $agencyId);
        }
        $dealAgreed = (clone $deals)->whereNull('voided_at')->selectRaw('COUNT(*) AS n, COALESCE(SUM(agreementAmountPKR), 0) AS total')->first();

        $collected = DB::table('transaction_payments')
            ->whereNull('deleted_at')
            ->whereBetween('received_at', [$from, $to]);
        if ($agencyId !== null) {
            $collected->where('agency_id', $agencyId);
        }
        $collected = $collected->selectRaw('COALESCE(SUM(amount), 0) AS total, COUNT(*) AS n')->first();

        // Commission aggregations
        $paidCommissions = DB::table('commissions')
            ->whereBetween('created_at', [$from, $to])
            ->where('status', 'paid');
        if ($agencyId !== null) {
            $paidCommissions->where('agency_id', $agencyId);
        }
        $paidCommissions = $paidCommissions->sum('amount');

        $pendingCommissions = DB::table('commissions')
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['pending', 'accrued']);
        if ($agencyId !== null) {
            $pendingCommissions->where('agency_id', $agencyId);
        }
        $pendingCommissions = $pendingCommissions->sum('amount');

        // Expense aggregations
        $expenses = DB::table('expenses')
            ->whereNull('deleted_at')
            ->whereDate('expense_date', '>=', $from->toDateString())
            ->whereDate('expense_date', '<=', $to->toDateString());
        if ($agencyId !== null) {
            $expenses->where('agency_id', $agencyId);
        }

        $totalExpenses = (float) $expenses->sum('amount_pkr');

        $expensesByCategory = DB::table('expenses')
            ->whereNull('deleted_at')
            ->whereDate('expense_date', '>=', $from->toDateString())
            ->whereDate('expense_date', '<=', $to->toDateString());
        if ($agencyId !== null) {
            $expensesByCategory->where('agency_id', $agencyId);
        }
        $expensesByCategory = $expensesByCategory->selectRaw('category, SUM(amount_pkr) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        // Subscription billing is platform revenue: only platform staff may
        // see it. Agency users get their own invoice figures instead of the
        // platform-wide totals (which were previously leaked here).
        if ($agencyId === null) {
            $invoices = [
                'paid_pkr' => (float) SubscriptionInvoice::where('status', 'paid')
                    ->whereBetween('paid_at', [$from, $to])->sum('total_pkr'),
                'paid_count' => SubscriptionInvoice::where('status', 'paid')
                    ->whereBetween('paid_at', [$from, $to])->count(),
                'outstanding_pkr' => (float) SubscriptionInvoice::where('status', 'pending')->sum('total_pkr'),
            ];
        } else {
            $invoices = [
                'paid_pkr' => (float) SubscriptionInvoice::where('status', 'paid')
                    ->where('agency_id', $agencyId)
                    ->whereBetween('paid_at', [$from, $to])->sum('total_pkr'),
                'paid_count' => SubscriptionInvoice::where('status', 'paid')
                    ->where('agency_id', $agencyId)
                    ->whereBetween('paid_at', [$from, $to])->count(),
                'outstanding_pkr' => (float) SubscriptionInvoice::where('status', 'pending')
                    ->where('agency_id', $agencyId)->sum('total_pkr'),
            ];
        }

        $listings = [
            'created_in_range' => Property::whereNull('deleted_at')
                ->when($agencyId !== null, fn ($q) => $q->where('agency_id', $agencyId))
                ->whereBetween('created_at', [$from, $to])->count(),
            'public' => Property::whereNull('deleted_at')
                ->when($agencyId !== null, fn ($q) => $q->where('agency_id', $agencyId))
                ->where('approval_status', 'approved')->where('visibility', 'public')->count(),
        ];

        $leads = Inquiry::whereBetween('created_at', [$from, $to])
            ->when($agencyId !== null, fn ($q) => $q->where('agency_id', $agencyId))
            ->count();

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
            'commissions' => [
                'paid_pkr' => (float) $paidCommissions,
                'pending_pkr' => (float) $pendingCommissions,
            ],
            'expenses' => [
                'total_pkr' => (int) $totalExpenses,
                'by_category' => $expensesByCategory->toArray(),
            ],
            'net_pkr' => (float) $collected->total - $totalExpenses,
            'invoices' => $invoices,
            'listings' => $listings,
            'leads_in_range' => $leads,
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
            ->whereNull('deleted_at')
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
