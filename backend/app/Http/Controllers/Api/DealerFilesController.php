<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dealer;
use App\Models\DealerFile;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DealerFilesController extends Controller
{
    /**
     * List all files for the authenticated dealer.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $dealerId = $user->dealer_id ?? $user->id;

        $query = DealerFile::where('dealer_id', $dealerId);

        // Filter by status
        $status = $request->query('status');
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        // Search by title
        $search = $request->query('search');
        if ($search) {
            $query->where('title', 'like', '%' . strip_tags($search) . '%');
        }

        $files = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $files,
            'count' => $files->count(),
        ]);
    }

    /**
     * Upload a new file to dealer's account.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $dealerId = $user->dealer_id ?? $user->id;

        // Verify dealer exists
        $dealer = Dealer::findOrFail($dealerId);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:image,video,pdf',
            'url' => 'required|url|max:2048',
            'folder' => 'required|in:Projects,Properties,Videos,Documents,Banners',
            'file_size_kb' => 'required|numeric|min:0|max:51200',
            'dimensions' => 'nullable|string|max:50',
        ]);

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

        // Audit log
        \Log::info('AUDIT', [
            'actor' => $user->name,
            'role' => $user->role,
            'action' => 'file_uploaded',
            'target' => $file->id,
            'detail' => "File {$file->title} uploaded to dealer {$dealer->fullName}",
            'timestamp' => now()->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $file,
            'message' => 'File uploaded to your dashboard successfully.',
        ], 201);
    }

    /**
     * Mark a file as sold and calculate commission.
     */
    public function sell(Request $request, $id)
    {
        $user = $request->user();
        $dealerId = $user->dealer_id ?? $user->id;

        $file = DealerFile::where('id', $id)
            ->where('dealer_id', $dealerId)
            ->firstOrFail();

        if ($file->status === 'sold') {
            return response()->json([
                'success' => false,
                'message' => 'This file has already been sold.',
            ], 400);
        }

        $validated = $request->validate([
            'selling_price_kbr' => 'required|numeric|min:0',
            'buyer_name' => 'required|string|max:255',
            'buyer_phone' => 'required|string|max:50',
            'buyer_cnic' => 'nullable|string|max:20',
        ]);

        // Calculate profit: selling price - cost price (30% default margin)
        $costPrice = $file->cost_price_kbr ?? ($validated['selling_price_kbr'] * 0.7);
        $profit = round($validated['selling_price_kbr'] - $costPrice, 2);

        // Update the file as sold
        $file->update([
            'status' => 'sold',
            'selling_price_kbr' => $validated['selling_price_kbr'],
            'cost_price_kbr' => $costPrice,
            'profit_kbr' => $profit,
            'buyer_name' => strip_tags($validated['buyer_name']),
            'buyer_phone' => $validated['buyer_phone'],
            'buyer_cnic' => $validated['buyer_cnic'] ? $this->maskCnic($validated['buyer_cnic']) : null,
            'sold_by_dealer_id' => $dealerId,
        ]);

        // Update dealer's commission statistics
        $this->updateDealerCommission($dealerId, $profit);

        // Audit log
        \Log::info('AUDIT', [
            'actor' => $user->name,
            'role' => $user->role,
            'action' => 'file_sold',
            'target' => $file->id,
            'detail' => "File {$file->title} sold for PKR {$validated['selling_price_kbr']}, profit: PKR {$profit}",
            'timestamp' => now()->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $file,
            'message' => 'File marked as sold. Commission calculated and updated.',
            'profit' => $profit,
        ]);
    }

    /**
     * Get sales attribution report for a specific dealer.
     */
    public function salesReport(Request $request, $dealerId)
    {
        $user = $request->user();

        // Only super_admin and admin can view other dealers' reports
        if ($user->role !== 'super_admin' && $user->role !== 'admin' && $user->dealer_id !== $dealerId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $dealer = Dealer::findOrFail($dealerId);

        $files = DealerFile::where('sold_by_dealer_id', $dealerId)
            ->where('status', 'sold')
            ->latest()
            ->get();

        $totalProfit = $files->sum('profit_kbr');
        $totalSold = $files->count();

        return response()->json([
            'success' => true,
            'data' => [
                'dealer' => $dealer,
                'total_files_sold' => $totalSold,
                'total_profit_kbr' => round($totalProfit, 2),
                'files' => $files,
            ],
        ]);
    }

    /**
     * Get all sales across the system (super admin only).
     */
    public function allSalesReport(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'super_admin' && $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = DealerFile::where('status', 'sold');

        // Filter by dealer if specified
        $dealerId = $request->query('dealer_id');
        if ($dealerId) {
            $query->where('sold_by_dealer_id', $dealerId);
        }

        // Filter by date range
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $files = $query->latest()->get();

        $totalProfit = $files->sum('profit_kbr');
        $totalSold = $files->count();

        $byDealer = $files->groupBy('dealer_id');

        return response()->json([
            'success' => true,
            'data' => [
                'total_files_sold' => $totalSold,
                'total_profit_kbr' => round($totalProfit, 2),
                'files' => $files,
                'by_dealer' => $byDealer->map(function ($files, $dealerId) {
                    $dealer = Dealer::find($dealerId);
                    return [
                        'dealer' => $dealer ? $dealer->fullName : 'Unknown',
                        'files_sold' => $files->count(),
                        'profit' => round($files->sum('profit_kbr'), 2),
                    ];
                })->values()->toArray(),
            ],
        ]);
    }

    protected function updateDealerCommission(string $dealerId, float $profit): void
    {
        $dealer = Dealer::find($dealerId);
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
        if (strlen($clean) < 7) {
            return str_repeat('*', strlen($clean));
        }
        return substr($clean, 0, 5) . str_repeat('*', strlen($clean) - 9) . substr($clean, -4);
    }
}