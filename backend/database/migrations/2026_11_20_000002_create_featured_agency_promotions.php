<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Featured agency advertising (Titanium-style placement).
 *
 * - promotion_packages: admin-configurable duration/price catalogue.
 * - agency_promotions: one placement record per purchased/curated slot. Rows
 *   are history-bearing (cancel/expire instead of delete), so analytics and
 *   payment reconciliation survive.
 *
 * No public pricing is exposed by the read API; the homepage only receives
 * eligibility and a transparent free/paid badge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_packages', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->char('currency', 3)->default('PKR');
            $table->unsignedInteger('duration_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('agency_promotions', function (Blueprint $table) {
            $table->id();
            $table->string('agency_id', 64)->index();
            $table->foreignId('package_id')->nullable()->constrained('promotion_packages')->nullOnDelete();

            // pending = reserved but not live, active = scheduled/live,
            // paused = temporarily hidden, expired = term ended, cancelled = admin ended.
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            // Price is snapshotted at placement time — later catalogue edits do
            // not rewrite financial history.
            $table->decimal('price', 12, 2)->default(0);
            $table->char('currency', 3)->default('PKR');
            $table->string('payment_status', 20)->default('waived'); // waived|pending|paid|refunded
            $table->string('payment_method', 40)->nullable();
            $table->string('payment_reference', 120)->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at', 'ends_at']);
            $table->index(['agency_id', 'status']);
            $table->index('position');
            $table->foreign('agency_id')->references('id')->on('agencies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_promotions');
        Schema::dropIfExists('promotion_packages');
    }
};
