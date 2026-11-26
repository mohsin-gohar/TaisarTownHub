<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pre-create sequence rows for the current and next calendar year. This keeps
 * the first live transaction of a new year from making concurrent workers race
 * to insert the same sequence row during deal creation.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([$now->year, $now->year + 1] as $year) {
            $lastIssued = 0;
            foreach (DB::table('transactions')
                ->where('dealNumber', 'like', "TXN-{$year}-%")
                ->pluck('dealNumber') as $dealNumber) {
                if (preg_match('/^TXN-\d{4}-(\d+)$/', (string) $dealNumber, $matches) === 1) {
                    $lastIssued = max($lastIssued, (int) $matches[1]);
                }
            }

            DB::table('transaction_number_sequences')->insertOrIgnore([
                'year' => $year,
                // The allocator consumes next_number as the next value to issue,
                // not the last value already present in transactions.
                'next_number' => $lastIssued + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Sequence state is durable financial numbering history; retain rows.
    }
};
