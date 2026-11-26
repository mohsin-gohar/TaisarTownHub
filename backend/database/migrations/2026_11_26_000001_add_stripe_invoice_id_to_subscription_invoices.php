<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store Stripe's recurring invoice identifier for a durable, idempotent
 * subscription billing ledger. Nullable values preserve existing invoices.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('subscription_invoices', 'stripe_invoice_id')) {
            Schema::table('subscription_invoices', function (Blueprint $table) {
                $table->string('stripe_invoice_id', 160)->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('subscription_invoices', 'stripe_invoice_id')) {
            Schema::table('subscription_invoices', function (Blueprint $table) {
                $table->dropUnique(['stripe_invoice_id']);
                $table->dropColumn('stripe_invoice_id');
            });
        }
    }
};
