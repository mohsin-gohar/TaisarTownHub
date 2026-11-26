<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Allocates globally unique, monotonically increasing deal numbers per year.
 *
 * Locking the latest transaction row is unsafe when the year has no rows yet
 * and can deadlock under concurrent inserts. A stable sequence row serializes
 * allocations across workers and replicas. Call inside the same DB transaction
 * that creates the transaction so both writes commit or roll back together.
 */
final class DealNumberAllocator
{
    public function nextForYear(int $year): string
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('Deal numbers must be allocated inside a database transaction.');
        }

        $now = now();
        DB::table('transaction_number_sequences')->insertOrIgnore([
            'year' => $year,
            'next_number' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sequence = DB::table('transaction_number_sequences')
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if ($sequence === null) {
            throw new RuntimeException("Could not lock the deal-number sequence for {$year}.");
        }

        $nextNumber = (int) $sequence->next_number;
        if ($nextNumber < 1) {
            // Backwards-compatible one-time bootstrap for existing deals.
            $lastIssued = 0;
            foreach (Transaction::query()
                ->where('dealNumber', 'like', "TXN-{$year}-%")
                ->pluck('dealNumber') as $dealNumber) {
                if (preg_match('/^TXN-\d{4}-(\d+)$/', (string) $dealNumber, $matches) === 1) {
                    $lastIssued = max($lastIssued, (int) $matches[1]);
                }
            }
            $nextNumber = $lastIssued + 1;
        }

        DB::table('transaction_number_sequences')
            ->where('year', $year)
            ->update([
                'next_number' => $nextNumber + 1,
                'updated_at' => now(),
            ]);

        return sprintf('TXN-%d-%04d', $year, $nextNumber);
    }
}
