<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Complete missing relational constraints on legacy ID columns.
 *
 * This migration deliberately refuses to discard or rewrite orphaned values.
 * If historical production data contains an orphan, reconcile it explicitly
 * and rerun the migration rather than silently nulling a reference.
 */
return new class extends Migration
{
    private function hasOrphans(string $table, string $column, string $parentTable, string $parentColumn = 'id'): bool
    {
        return DB::table($table)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->whereNotIn($column, DB::table($parentTable)->select($parentColumn))
            ->exists();
    }

    public function up(): void
    {
        $references = [
            ['users', 'dealer_id', 'dealers'],
            ['dealers', 'agency_id', 'agencies'],
            ['properties', 'assignedDealerId', 'dealers'],
            ['transactions', 'propertyId', 'properties'],
            ['transactions', 'dealerId', 'dealers'],
            ['business_records', 'agency_id', 'agencies'],
            ['property_status_history', 'agency_id', 'agencies'],
            ['login_histories', 'user_id', 'users'],
        ];

        $orphans = [];
        foreach ($references as [$table, $column, $parent]) {
            if ($this->hasOrphans($table, $column, $parent)) {
                $orphans[] = "{$table}.{$column} -> {$parent}.id";
            }
        }

        if ($orphans !== []) {
            throw new RuntimeException(
                "Cannot complete foreign-key integrity; orphaned references must be reconciled first: "
                . implode(', ', $orphans)
            );
        }

        // The existing legacy dealer lookup had no index. Add it explicitly
        // before the FK so MySQL can use the stable, predictable index name.
        if (!Schema::hasIndex('properties', ['assignedDealerId'])) {
            Schema::table('properties', function (Blueprint $table): void {
                $table->index('assignedDealerId', 'properties_assigned_dealer_idx');
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->foreign('dealer_id', 'users_dealer_fk')
                ->references('id')->on('dealers')->nullOnDelete();
        });

        Schema::table('dealers', function (Blueprint $table): void {
            $table->foreign('agency_id', 'dealers_agency_fk')
                ->references('id')->on('agencies')->nullOnDelete();
        });

        Schema::table('properties', function (Blueprint $table): void {
            $table->foreign('assignedDealerId', 'properties_assigned_dealer_fk')
                ->references('id')->on('dealers')->nullOnDelete();
        });

        Schema::table('transactions', function (Blueprint $table): void {
            // Preserve completed deal snapshots if a property/dealer is later
            // physically removed; only the optional live reference is cleared.
            $table->foreign('propertyId', 'transactions_property_fk')
                ->references('id')->on('properties')->nullOnDelete();
            $table->foreign('dealerId', 'transactions_dealer_fk')
                ->references('id')->on('dealers')->nullOnDelete();
        });

        Schema::table('business_records', function (Blueprint $table): void {
            $table->foreign('agency_id', 'business_records_agency_fk')
                ->references('id')->on('agencies')->nullOnDelete();
        });

        Schema::table('property_status_history', function (Blueprint $table): void {
            // History remains intact if an agency is physically removed.
            $table->foreign('agency_id', 'property_status_history_agency_fk')
                ->references('id')->on('agencies')->nullOnDelete();
        });

        Schema::table('login_histories', function (Blueprint $table): void {
            // Preserve security audit events when an account is deleted.
            $table->foreign('user_id', 'login_histories_user_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('login_histories', function (Blueprint $table): void {
            $table->dropForeign('login_histories_user_fk');
        });

        Schema::table('property_status_history', function (Blueprint $table): void {
            $table->dropForeign('property_status_history_agency_fk');
        });

        Schema::table('business_records', function (Blueprint $table): void {
            $table->dropForeign('business_records_agency_fk');
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropForeign('transactions_property_fk');
            $table->dropForeign('transactions_dealer_fk');
        });

        Schema::table('properties', function (Blueprint $table): void {
            $table->dropForeign('properties_assigned_dealer_fk');
        });

        Schema::table('dealers', function (Blueprint $table): void {
            $table->dropForeign('dealers_agency_fk');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign('users_dealer_fk');
        });

        $createdIndexExists = collect(Schema::getIndexes('properties'))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === 'properties_assigned_dealer_idx');

        if ($createdIndexExists) {
            Schema::table('properties', function (Blueprint $table): void {
                $table->dropIndex('properties_assigned_dealer_idx');
            });
        }
    }
};
