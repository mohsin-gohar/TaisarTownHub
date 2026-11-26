<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transaction-safe yearly invoice numbering. Rows for this and next year are
 * pre-created so simultaneous first invoices cannot race on the primary key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_invoice_number_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('next_number')->default(0);
            $table->timestamps();
        });

        $now = now();
        foreach ([$now->year, $now->year + 1] as $year) {
            $max = 0;
            foreach (DB::table('subscription_invoices')
                ->where('invoice_number', 'like', "INV-{$year}-%")
                ->pluck('invoice_number') as $invoiceNumber) {
                if (preg_match('/^INV-\d{4}-(\d+)$/', (string) $invoiceNumber, $matches) === 1) {
                    $max = max($max, (int) $matches[1]);
                }
            }

            DB::table('subscription_invoice_number_sequences')->insert([
                'year' => $year,
                'next_number' => $max,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoice_number_sequences');
    }
};
