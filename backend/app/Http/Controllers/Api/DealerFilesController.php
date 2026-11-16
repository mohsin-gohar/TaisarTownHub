<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dealer;
use App\Models\DealerFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $files = $query->orderByDesc('created_at')->orderBy('id')->get();

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
     *
     * Concurrency guard: the status re-check happens on the LOCKED row
     * (SELECT … FOR UPDATE inside a transaction), so two dealers racing on
     * the same file cannot both sell it — the loser commits against the
     * already-sold row and is rejected.
     */
    public function sell(Request $request, $id)
    {
        $user = $request->user();
        $dealerId = $user->dealer_id ?? $user->id;

        $validated = $request->validate([
            'selling_price_kbr' => 'required|numeric|min:0|max:9999999999',
            'buyer_name' => 'required|string|max:255',
            'buyer_phone' => 'required|string|max:50',
            'buyer_cnic' => 'nullable|string|max:20',
        ]);

        $profit = 0.0;

        $soldFile = DB::transaction(function () use ($request, $id, $dealerId, $validated, &$profit) {
            $file = DealerFile::where('id', $id)
                ->where('dealer_id', $dealerId)
                ->lockForUpdate()
                ->first();

            if (!$file) {
                abort(404);
            }

            if ($file->status === 'sold') {
                return null; // rejected below (already sold)
            }

            $costPrice = $file->cost_price_kbr ?? ($validated['selling_price_kbr'] * 0.7);
            $profit = round($validated['selling_price_kbr'] - $costPrice, 2);

            $file->update([
                'status' => 'sold',
                'selling_price_kbr' => $validated['selling_price_kbr'],
                'cost_price_kbr' => $costPrice,
                'profit_kbr' => $profit,
                'buyer_name' => strip_tags($validated['buyer_name']),
                'buyer_phone' => $validated['buyer_phone'],
                'buyer_cnic' => isset($validated['buyer_cnic']) && $validated['buyer_cnic'] !== null && $validated['buyer_cnic'] !== ''
                    ? $this->maskCnic($validated['buyer_cnic'])
                    : null,
                'sold_by_dealer_id' => $dealerId,
            ]);

            // Update dealer's commission statistics (locked within the same
            // transaction; monetary columns are integer PKR).
            $this->updateDealerCommission($dealerId, (int) round($profit));

            return $file;
        });

        if ($soldFile === null) {
            return response()->json([
                'success' => false,
                'message' => 'This file has already been sold.',
            ], 400);
        }

        $file = $soldFile;

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

        $canViewAll = $user->hasPermission('view_commissions');
        if (!$canViewAll && (string) $user->dealer_id !== (string) $dealerId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $dealer = Dealer::findOrFail($dealerId);

        $files = DealerFile::where('sold_by_dealer_id', $dealerId)
            ->where('status', 'sold')
            ->orderByDesc('created_at')
            ->orderBy('id')
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

        if (!$user->hasPermission('view_commissions')) {
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

        $files = $query->orderByDesc('created_at')->orderBy('id')->get();

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
        if (strlen($clean) < 7) {
            return str_repeat('*', strlen($clean));
        }
        return substr($clean, 0, 5) . str_repeat('*', strlen($clean) - 9) . substr($clean, -4);
    }
}
