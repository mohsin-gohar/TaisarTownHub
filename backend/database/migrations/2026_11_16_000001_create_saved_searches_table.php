<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace saved searches + email alerts.
 *
 * A saved search belongs to exactly one account (never an agency — the same
 * person may browse across agencies). Filters are stored as JSON so the
 * marketplace can evolve its filter set without a schema migration; alert
 * delivery uses the shared PropertySearchFilters builder, so the alert
 * criteria and the on-site criteria cannot drift apart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->json('filters');
            $table->string('frequency', 20)->default('daily'); // instant|daily|weekly
            $table->boolean('alerts_enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();

            // Listing a user's searches is the only read pattern.
            $table->index(['user_id', 'created_at']);
            // The alert command scans enabled searches ordered by age.
            $table->index(['alerts_enabled', 'frequency', 'last_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
