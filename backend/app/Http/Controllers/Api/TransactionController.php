<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dealer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DealNumberAllocator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    use AuthorizesApi;

    /**
     * List transactions with filtering.
     *
     * SECURITY: the legacy surface used to return every deal in the database.
     * It now honours the agency resolved by AgencyContext (never a client
     * header/body value) and falls back to dealer-self scoping for members
     * without a platform-wide view permission.
     */
    public function index(Request $request)
    {
        $this->requireAny($request, ['view_transactions', 'view_my_deals']);

        $agencyId = $this->agencyId($request);
        $platformView = $this->can($request, 'view_transactions');

        $query = Transaction::query();
        if ($agencyId !== null && $agencyId !== '') {
            // Explicit agency context: only that agency's rows.
            $query->where('agency_id', $agencyId);
        } elseif (!$platformView) {
            // No agency and no explicit platform grant: fail closed.
            $query->whereNull('agency_id');
        }

        if (!$platformView) {
            $query->where('dealerId', $request->user()->dealer_id);
        }

        // A client-supplied dealer filter is only honoured for a platform-wide
        // viewer with no agency context; it must never enumerate another
        // agency's dealers.
        if ($request->filled('dealerId') && ($agencyId === null || $agencyId === '') && $platformView) {
            $query->where('dealerId', $request->dealerId);
        }

        if ($request->filled('status')) {
            $query->where('dealStatus', $request->status);
        }

        return response()->json($query->orderByDesc('created_at')->orderBy('id')->get());
    }

    /**
     * Store a new transaction with validation and financial integrity.
     */
    public function store(Request $request)
    {
        $this->requireAny($request, ['create_transactions']);
        $agencyId = $this->requireAgency($request);

        $data = $request->validate([
            'propertyId' => [
                'required', 'string',
                Rule::exists('properties', 'id')->where(function ($q) use ($agencyId) {
                    $q->where('agency_id', $agencyId);
                }),
            ],
            'propertyTitle' => 'required|string|max:255',
            'plotNumber' => 'nullable|string|max:50',
            'sector' => 'nullable|string|max:100',
            'sellerName' => 'required|string|max:255',
            'buyerName' => 'required|string|max:255',
            'dealerId' => 'nullable|string|exists:dealers,id',
            'dealerName' => 'nullable|string|max:255',
            'transactionType' => 'required|string|in:Sale,Purchase,Lease,Transfer',
            'agreementAmountPKR' => 'required|numeric|min:0',
            'advancePaymentPKR' => 'nullable|numeric|min:0',
            'commissionAmountPKR' => 'nullable|numeric|min:0',
            'commissionStatus' => 'nullable|string|in:Pending Accrual,Accrued,Partially Paid,Completed,Cancelled',
            'dealStatus' => 'nullable|string|in:Draft,Negotiation,Agreement Signed,Token Paid,Advance Received,Payment Pending,Partially Paid,Fully Paid,Under Transfer,Completed,Cancelled',
        ]);

        // Prefer dealers.agency_id (the real ownership link); membership
        // remains the fallback for legacy rows.
        if (!empty($data['dealerId']) && !$this->dealerBelongsToAgency($data['dealerId'], $agencyId)) {
            throw ValidationException::withMessages([
                'dealerId' => ['Dealer does not belong to the acting agency.'],
            ]);
        }

        $advance = $data['advancePaymentPKR'] ?? 0;
        $data['remainingBalancePKR'] = max(0, $data['agreementAmountPKR'] - $advance);
        $data['dealStatus'] ??= 'Draft';
        $data['commissionStatus'] ??= 'Pending Accrual';
        // Tenant ownership is bound server-side; body agency_id is ignored.
        $data['agency_id'] = $agencyId;
        $data['created_by'] = $request->user()->id;
        $data['id'] = 'txn-' . Str::uuid();

        $year = now()->year;
        $transaction = DB::transaction(function () use ($data, $year) {
            $data['dealNumber'] = app(DealNumberAllocator::class)->nextForYear($year);

            return Transaction::create($data);
        }, 3);

        $this->writeAudit($request, 'transaction_created', $transaction->id, "Deal {$transaction->dealNumber} created", [
            'agency_id' => $agencyId,
        ]);

        return response()->json($transaction, 201);
    }

    /** Dealer ids are valid here only when linked to an active member of the agency. */
    private function dealerBelongsToAgency(string $dealerId, string $agencyId): bool
    {
        $dealer = Dealer::find($dealerId);
        if ($dealer === null) {
            return false;
        }
        if ($dealer->agency_id !== null) {
            return $dealer->agency_id === $agencyId;
        }

        return User::where('dealer_id', $dealerId)
            ->whereHas('memberships', fn ($q) => $q->where('agency_id', $agencyId)->where('status', 'active'))
            ->exists();
    }
}
