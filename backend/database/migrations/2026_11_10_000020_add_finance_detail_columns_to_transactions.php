<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The finance API (FinanceController) accepts and persists deal payment
 * metadata, but the original `transactions` table never had these columns.
 * Additive fix: nullable columns only, no data rewrite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'paymentMethod')) {
                $table->string('paymentMethod')->nullable()->after('commissionStatus');
            }
            if (!Schema::hasColumn('transactions', 'paymentStatus')) {
                $table->string('paymentStatus')->nullable()->after('paymentMethod');
            }
            if (!Schema::hasColumn('transactions', 'transactionDate')) {
                $table->date('transactionDate')->nullable()->after('paymentStatus');
            }
            if (!Schema::hasColumn('transactions', 'internalRemarks')) {
                $table->text('internalRemarks')->nullable()->after('transactionDate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            foreach (['internalRemarks', 'transactionDate', 'paymentStatus', 'paymentMethod'] as $column) {
                if (Schema::hasColumn('transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
