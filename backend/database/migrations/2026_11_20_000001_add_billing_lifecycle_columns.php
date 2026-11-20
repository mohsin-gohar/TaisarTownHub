<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing lifecycle columns that the payment/webhook code already uses.
 *
 * The original subscription migration only modelled the plan assignment. The
 * Stripe/Flutterwave controllers (and the agency subscription payload) read and
 * write renewal state, cancellation timestamps and gateway identifiers, so
 * those columns must exist for a real checkout/webhook to complete instead of
 * failing at the database layer.
 *
 * Purely additive — no existing billing row is modified or removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agency_subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('agency_subscriptions', 'auto_renew')) {
                $table->boolean('auto_renew')->default(false)->after('trial_ends_at');
            }
            if (!Schema::hasColumn('agency_subscriptions', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('auto_renew');
            }
            if (!Schema::hasColumn('agency_subscriptions', 'stripe_subscription_id')) {
                $table->string('stripe_subscription_id', 160)->nullable()->after('cancelled_at');
                $table->index('stripe_subscription_id');
            }
            if (!Schema::hasColumn('agency_subscriptions', 'provider')) {
                $table->string('provider', 30)->nullable()->after('stripe_subscription_id');
            }
        });

        Schema::table('subscription_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('subscription_invoices', 'stripe_session_id')) {
                $table->string('stripe_session_id', 160)->nullable()->unique();
            }
            if (!Schema::hasColumn('subscription_invoices', 'stripe_payment_intent_id')) {
                $table->string('stripe_payment_intent_id', 160)->nullable();
            }
            if (!Schema::hasColumn('subscription_invoices', 'stripe_subscription_id')) {
                $table->string('stripe_subscription_id', 160)->nullable();
            }
            if (!Schema::hasColumn('subscription_invoices', 'flutterwave_tx_ref')) {
                $table->string('flutterwave_tx_ref', 160)->nullable()->index();
            }
            if (!Schema::hasColumn('subscription_invoices', 'flutterwave_tx_id')) {
                $table->string('flutterwave_tx_id', 160)->nullable();
            }
            if (!Schema::hasColumn('subscription_invoices', 'due_at')) {
                $table->timestamp('due_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            foreach (['due_at', 'flutterwave_tx_id', 'flutterwave_tx_ref', 'stripe_subscription_id', 'stripe_payment_intent_id', 'stripe_session_id'] as $column) {
                if (Schema::hasColumn('subscription_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('agency_subscriptions', function (Blueprint $table) {
            foreach (['provider', 'stripe_subscription_id', 'cancelled_at', 'auto_renew'] as $column) {
                if (Schema::hasColumn('agency_subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
