<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscription plans (configurable limits, seeded defaults — no hardcoded
 * prices in application code) and per-agency subscriptions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->char('currency', 3)->default('PKR');
            $table->string('interval', 10)->default('month'); // month|year
            $table->json('limits')->nullable();   // {listings, staff, storage_mb, images, videos, featured_credits, listing_days}
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('agency_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('agency_id', 64)->unique();
            $table->foreignId('plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->string('status', 20)->default('trialing'); // trialing|active|expired|cancelled
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->json('limits_override')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('ends_at');
            $table->foreign('agency_id')->references('id')->on('agencies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
