<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hot public-marketplace query support for enterprise scale:
 *  - properties(approval_status, visibility, listing_purpose, agency_id):
 *    every public listing query filters on these columns first.
 *  - agency_videos(created_at): listing/sort-by-newest + pagination.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            if (!Schema::hasIndex('properties', ['approval_status', 'visibility', 'listing_purpose', 'agency_id'])) {
                // Explicit short name: the generated name
                // properties_approval_status_visibility_listing_purpose_agency_id_index
                // exceeds MySQL's 64-character identifier limit.
                $table->index(
                    ['approval_status', 'visibility', 'listing_purpose', 'agency_id'],
                    'properties_public_listing_index'
                );
            }
        });

        Schema::table('agency_videos', function (Blueprint $table) {
            if (!Schema::hasIndex('agency_videos', ['created_at'])) {
                $table->index('created_at');
            }
        });

    }

    public function down(): void
    {
        Schema::table('agency_videos', function (Blueprint $table) {
            if (Schema::hasIndex('agency_videos', ['created_at'])) {
                $table->dropIndex('agency_videos_created_at_index');
            }
        });

        Schema::table('properties', function (Blueprint $table) {
            if (Schema::hasIndex('properties', ['approval_status', 'visibility', 'listing_purpose', 'agency_id'])) {
                $table->dropIndex('properties_public_listing_index');
            }
        });
    }
};
