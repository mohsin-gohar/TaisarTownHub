<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Property lifecycle + tenancy + publishing fields.
 * Existing rows default to approval_status='approved', visibility='public'
 * so the public marketplace keeps showing every listing it shows today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('agency_id', 64)->nullable()->after('id');
            $table->foreign('agency_id')->references('id')->on('agencies')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->after('agency_id')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();

            $table->string('approval_status', 20)->default('approved')->after('status');
            $table->string('visibility', 20)->default('public')->after('approval_status');
            $table->string('featured_status', 20)->nullable()->after('visibility'); // null|requested|granted|denied
            $table->string('listing_purpose', 10)->default('sale')->after('visibility'); // sale|rent
            $table->string('slug')->nullable()->unique();

            $table->foreignId('category_id')->nullable()->after('type')->constrained('property_categories')->nullOnDelete();
            $table->string('sector_id', 64)->nullable()->after('sector');
            $table->string('block_id', 64)->nullable()->after('sector_id');
            $table->foreign('sector_id')->references('id')->on('sectors')->nullOnDelete();
            $table->foreign('block_id')->references('id')->on('blocks')->nullOnDelete();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->index(['agency_id', 'approval_status']);
            $table->index(['approval_status', 'visibility']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['agency_id', 'approval_status']);
            $table->dropIndex(['approval_status', 'visibility']);
            $table->dropIndex(['expires_at']);
            $table->dropForeign(['agency_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropForeign(['category_id']);
            $table->dropForeign(['sector_id']);
            $table->dropForeign(['block_id']);
            $table->dropColumn([
                'agency_id', 'created_by', 'updated_by', 'approval_status', 'visibility',
                'featured_status', 'listing_purpose', 'slug', 'category_id', 'sector_id',
                'block_id', 'submitted_at', 'published_at', 'expires_at', 'rejection_reason',
            ]);
        });
    }
};
