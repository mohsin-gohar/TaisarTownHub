<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    use AuthorizesApi;

    /**
     * List transactions with filtering.
     */
    public function index(Request $request)
    {
        $this->requireAny($request, ['view_transactions', 'view_my_deals']);

        $query = Transaction::query();

        $user = $request->user();
        if (!$user->hasPermission('view_transactions')) {
            $query->where('dealerId', $user->dealer_id);
        }

        if ($request->filled('dealerId')) {
            $query->where('dealerId', $request->dealerId);
        }

        if ($request->filled('status')) {
            $query->where('dealStatus', $request->status);
        }

        return response()->json($query->latest()->get());
    }

    /**
     * Store a new transaction with validation and financial integrity.
     */
    public function store(Request $request)
    {
        $this->requireAny($request, ['create_transactions']);

        $data = $request->validate([
            'propertyId' => 'required|string|exists:properties,id',
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

        // Generate unique deal number
        $year = now()->year;
        $lastDeal = Transaction::where('dealNumber', 'like', "TXN-{$year}-%")
            ->orderByDesc('dealNumber')
            ->first();
        $nextNumber = 1;
        if ($lastDeal && preg_match('/TXN-\d{4}-(\d+)/', $lastDeal->dealNumber, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        }
        $data['dealNumber'] = sprintf('TXN-%d-%04d', $year, $nextNumber);
        $data['id'] = 'txn-' . Str::uuid();

        // Calculate remaining balance
        $advance = $data['advancePaymentPKR'] ?? 0;
        $data['remainingBalancePKR'] = max(0, $data['agreementAmountPKR'] - $advance);

        // Set default status if not provided
        if (!isset($data['dealStatus'])) {
            $data['dealStatus'] = 'Draft';
        }
        if (!isset($data['commissionStatus'])) {
            $data['commissionStatus'] = 'Pending Accrual';
        }

        $transaction = Transaction::create($data);

        // Audit log
        $this->writeAudit($request, 'transaction_created', $transaction->id, "Deal {$transaction->dealNumber} created");

        return response()->json($transaction, 201);
    }
}