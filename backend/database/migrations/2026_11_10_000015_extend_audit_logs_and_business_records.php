<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Audit log tenancy/attribution + agency scoping for the legacy JSON store. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('audit_logs', 'agency_id')) {
                $table->string('agency_id', 64)->nullable()->after('role');
                $table->index('agency_id');
            }
            if (!Schema::hasColumn('audit_logs', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('agency_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('audit_logs', 'entity_type')) {
                $table->string('entity_type')->nullable()->after('target');
            }
            if (!Schema::hasColumn('audit_logs', 'entity_id')) {
                $table->string('entity_id')->nullable()->after('entity_type');
            }
            if (!Schema::hasColumn('audit_logs', 'metadata')) {
                $table->json('metadata')->nullable()->after('detail');
            }
            if (!Schema::hasColumn('audit_logs', 'cross_agency')) {
                $table->boolean('cross_agency')->default(false)->after('metadata');
            }
            $table->index(['agency_id', 'created_at']);
        });

        Schema::table('business_records', function (Blueprint $table) {
            if (!Schema::hasColumn('business_records', 'agency_id')) {
                $table->string('agency_id', 64)->nullable()->after('collection');
                $table->index('agency_id');
                $table->index(['agency_id', 'collection']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('business_records', function (Blueprint $table) {
            $table->dropIndex(['agency_id', 'collection']);
            $table->dropIndex(['agency_id']);
            $table->dropColumn('agency_id');
        });
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['agency_id', 'created_at']);
            $table->dropIndex(['agency_id']);
            $table->dropColumn([
                'agency_id', 'user_id', 'entity_type', 'entity_id', 'metadata', 'cross_agency',
            ]);
        });
    }
};
