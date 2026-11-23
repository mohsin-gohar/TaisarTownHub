<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment corrections are audit-preserving soft deletes. The controller has
 * been writing `deleted_at` since the original finance implementation, but the
 * column was never created, so every void request failed with SQLSTATE 42S22.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('transaction_payments') || Schema::hasColumn('transaction_payments', 'deleted_at')) {
            return;
        }

        Schema::table('transaction_payments', function (Blueprint $table): void {
            $table->softDeletes();
            $table->index(['transaction_id', 'deleted_at'], 'txn_pay_txn_deleted_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('transaction_payments') || !Schema::hasColumn('transaction_payments', 'deleted_at')) {
            return;
        }

        Schema::table('transaction_payments', function (Blueprint $table): void {
            $table->dropIndex('txn_pay_txn_deleted_idx');
            $table->dropSoftDeletes();
        });
    }
};
