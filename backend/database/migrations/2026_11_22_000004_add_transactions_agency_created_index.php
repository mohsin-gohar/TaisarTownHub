<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finance dashboards and reports filter by agency and sort/range-scan by
 * created_at (deal listings, period comparisons). The existing
 * (agency_id, dealStatus) index cannot serve that ordering, so every
 * agency-scoped report query degraded to a filesort as transaction volume
 * grew. Additive-only index; safe on MySQL 8.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasIndex('transactions', ['agency_id', 'created_at'])) {
                $table->index(['agency_id', 'created_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasIndex('transactions', ['agency_id', 'created_at'])) {
                $table->dropIndex(['agency_id', 'created_at']);
            }
        });
    }
};
