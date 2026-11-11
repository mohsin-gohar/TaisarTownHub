<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pending (unapproved) edits for approved listings: live public columns stay
 * untouched until a moderator applies the pending changes — the public copy
 * never changes silently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            if (!Schema::hasColumn('properties', 'pending_changes')) {
                $table->json('pending_changes')->nullable()->after('rejection_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            if (Schema::hasColumn('properties', 'pending_changes')) {
                $table->dropColumn('pending_changes');
            }
        });
    }
};
