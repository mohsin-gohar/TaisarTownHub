<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dealer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\QueryException;
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
        } elseif (! $platformView) {
            // No agency and no explicit platform grant: fail closed.
            $query->whereNull('agency_id');
        }

        if (! $platformView) {
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
        if (! empty($data['dealerId']) && ! $this->dealerBelongsToAgency($data['dealerId'], $agencyId)) {
            throw ValidationException::withMessages([
                'dealerId' => ['Dealer does not belong to the acting agency.'],
            ]);
        }

        $agreement = (float) $data['agreementAmountPKR'];
        $advance = (float) ($data['advancePaymentPKR'] ?? 0);

        if ($advance > $agreement) {
            throw ValidationException::withMessages([
                'advancePaymentPKR' => ['Advance payment cannot exceed the agreement amount.'],
            ]);
        }

        // Monetary values are stored as decimal(14,2); do all arithmetic on
        // integer minor units (paisa) so binary float drift cannot corrupt
        // the derived balance.
        $remaining = (int) round($agreement * 100) - (int) round($advance * 100);
        if ($remaining < 0) {
            $remaining = 0;
        }
        $data['remainingBalancePKR'] = $remaining / 100;
        $data['advancePaymentPKR'] = $advance;
        $data['agreementAmountPKR'] = $agreement;
        $data['dealStatus'] ??= 'Draft';
        $data['commissionStatus'] ??= 'Pending Accrual';
        // Tenant ownership is bound server-side; body agency_id is ignored.
        $data['agency_id'] = $agencyId;
        $data['created_by'] = $request->user()->id;
        $data['id'] = 'txn-'.Str::uuid();

        $year = now()->year;
        $transaction = null;

        // Deal numbers must be unique under concurrent requests. The locked
        // read + unique index + bounded retry mirrors FinanceController::store.
        for ($attempt = 0; $attempt < 3 && $transaction === null; $attempt++) {
            try {
                $transaction = DB::transaction(function () use ($data, $year) {
                    $lastDeal = Transaction::where('dealNumber', 'like', "TXN-{$year}-%")
                        ->lockForUpdate()
                        ->orderByDesc('dealNumber')
                        ->first();
                    $nextNumber = 1;
                    if ($lastDeal && preg_match('/TXN-\d{4}-(\d+)/', $lastDeal->dealNumber, $matches)) {
                        $nextNumber = (int) $matches[1] + 1;
                    }
                    $data['dealNumber'] = sprintf('TXN-%d-%04d', $year, $nextNumber);

                    return Transaction::create($data);
                }, 3);
            } catch (QueryException $e) {
                if (! str_contains(strtolower($e->getMessage()), 'duplicate') && $e->getCode() !== '23000') {
                    throw $e;
                }
            }
        }

        if ($transaction === null) {
            throw ValidationException::withMessages([
                'dealNumber' => ['Could not allocate a unique deal number. Please retry.'],
            ]);
        }

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
