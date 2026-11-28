<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;\nuse Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseSchemaContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every table introduced by the migration history must exist after a
     * clean migration. This catches missing/renamed tables independently
     * from whether a particular model currently queries them.
     */
    public function test_all_migration_owned_tables_exist(): void
    {
        $tables = [
            'users', 'password_reset_tokens', 'sessions',
            'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
            'personal_access_tokens',
            'dealers', 'properties', 'agency_videos', 'video_comments',
            'inquiries', 'transactions', 'projects', 'dealer_files',
            'audit_logs', 'business_records',
            'agencies', 'agency_invitations', 'permissions', 'roles',
            'role_permissions', 'agency_members',
            'sectors', 'blocks', 'property_categories',
            'property_status_history', 'property_favorites', 'property_views',
            'document_folders', 'documents', 'document_versions',
            'document_access_logs', 'media_files', 'lead_activities',
            'transaction_payments', 'payment_schedules', 'commissions',
            'notifications', 'subscription_plans', 'agency_subscriptions',
            'system_settings', 'email_verification_tokens',
            'document_shares', 'support_tickets', 'support_messages',
            'expenses', 'subscription_invoices', 'login_histories',
            'saved_searches', 'document_quarantines',
            'promotion_packages', 'agency_promotions', 'maps', 'video_likes',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing migrated table: {$table}");
        }
    }

    /**
     * Keep Eloquent persistence contracts aligned with actual SQL schema.
     * Both mass-assignable attributes and cast attributes must resolve to
     * real columns; this catches drift after migrations/model refactors.
     */
    public function test_decimal_money_casts_preserve_exact_two_decimal_values(): void
    {
        $cases = [
            [\App\Models\TransactionPayment::class, 'amount'],
            [\App\Models\PaymentSchedule::class, 'amount'],
            [\App\Models\Commission::class, 'amount'],
            [\App\Models\Commission::class, 'rate'],
            [\App\Models\DealerFile::class, 'selling_price_kbr'],
            [\App\Models\DealerFile::class, 'cost_price_kbr'],
            [\App\Models\DealerFile::class, 'profit_kbr'],
            [\App\Models\MediaFile::class, 'sold_price_pk'],
            [\App\Models\MediaFile::class, 'cost_price_pk'],
            [\App\Models\MediaFile::class, 'profit_pk'],
            [\App\Models\SubscriptionPlan::class, 'price'],
        ];

        foreach ($cases as [$class, $column]) {
            $model = new $class();
            $model->setRawAttributes([$column => '123456789.12'], true);

            $this->assertSame(
                '123456789.12',
                $model->getAttribute($column),
                "{$class}.{$column} must preserve DECIMAL precision without float conversion"
            );
        }
    }

    public function test_core_foreign_key_relationships_are_enforced(): void
    {
        $expected = [
            'users' => ['dealer_id' => 'dealers'],
            'dealers' => ['agency_id' => 'agencies'],
            'agency_members' => [
                'agency_id' => 'agencies', 'user_id' => 'users', 'role_id' => 'roles',
            ],
            'agency_invitations' => ['agency_id' => 'agencies', 'role_id' => 'roles'],
            'properties' => ['agency_id' => 'agencies', 'assignedDealerId' => 'dealers'],
            'transactions' => [
                'agency_id' => 'agencies', 'propertyId' => 'properties', 'dealerId' => 'dealers',
            ],
            'business_records' => ['agency_id' => 'agencies'],
            'property_status_history' => [
                'property_id' => 'properties', 'agency_id' => 'agencies',
            ],
            'login_histories' => ['user_id' => 'users'],
            'inquiries' => [
                'agency_id' => 'agencies', 'property_id' => 'properties', 'assigned_agent_id' => 'users',
            ],
            'lead_activities' => ['agency_id' => 'agencies', 'inquiry_id' => 'inquiries'],
            'media_files' => ['agency_id' => 'agencies', 'property_id' => 'properties'],
            'documents' => [
                'agency_id' => 'agencies', 'folder_id' => 'document_folders', 'property_id' => 'properties',
            ],
            'role_permissions' => ['role_id' => 'roles', 'permission_id' => 'permissions'],
            'video_comments' => [
                'agency_video_id' => 'agency_videos', 'user_id' => 'users',
            ],
            'video_likes' => [
                'agency_video_id' => 'agency_videos', 'user_id' => 'users',
            ],
        ];

        foreach ($expected as $table => $relationships) {
            $foreignKeys = Schema::getForeignKeys($table);

            foreach ($relationships as $column => $parentTable) {
                $exists = collect($foreignKeys)->contains(
                    fn (array $foreignKey) =>
                        ($foreignKey['columns'] ?? []) === [$column]
                        && ($foreignKey['foreign_table'] ?? null) === $parentTable
                );

                $this->assertTrue(
                    $exists,
                    "Missing foreign key: {$table}.{$column} -> {$parentTable}.id"
                );
            }
        }
    }

    public function test_critical_query_indexes_and_uniques_exist(): void
    {
        $expected = [
            ['transactions', ['agency_id', 'created_at'], false],
            ['properties', ['approval_status', 'visibility', 'listing_purpose', 'agency_id'], false],
            ['properties', ['assignedDealerId'], false],
            ['agency_videos', ['created_at'], false],
            ['business_records', ['collection', 'record_id'], true],
            ['agency_members', ['agency_id', 'user_id'], true],
            ['role_permissions', ['role_id', 'permission_id'], true],
            ['transaction_payments', ['transaction_id', 'received_at'], false],
            ['payment_schedules', ['transaction_id', 'installment_no'], true],
            ['saved_searches', ['user_id', 'created_at'], false],
            ['transactions', ['agency_id', 'dealStatus', 'created_at'], false],
            ['agency_promotions', ['agency_id', 'status', 'starts_at', 'ends_at', 'position'], false],
            ['inquiries', ['agency_id', 'created_at'], false],
            ['inquiries', ['agency_id', 'lead_status', 'created_at'], false],
            ['inquiries', ['agency_id', 'assigned_agent_id', 'created_at'], false],
            ['inquiries', ['agency_id', 'follow_up_at'], false],
            ['documents', ['folder_id', 'status'], false],
            ['agency_videos', ['phase', 'created_at', 'id'], false],
            ['lead_activities', ['inquiry_id', 'created_at'], false],
            ['document_folders', ['agency_id', 'path'], true],
            ['document_versions', ['document_id', 'version'], true],
            ['video_likes', ['agency_video_id', 'user_id'], true],
        ];

        foreach ($expected as [$table, $columns, $unique]) {
            $indexes = Schema::getIndexes($table);
            $exists = collect($indexes)->contains(
                fn (array $index) =>
                    ($index['columns'] ?? []) === $columns
                    && (bool) ($index['unique'] ?? false) === $unique
            );

            $this->assertTrue(
                $exists,
                "Missing " . ($unique ? 'unique ' : '') . "index on {$table}(" . implode(', ', $columns) . ")"
            );
        }
    }


    public function test_mysql_numeric_check_constraints_are_enforced(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Named CHECK constraints are verified on the supported MySQL database.');
        }

        $expected = [
            ['transaction_payments', 'transaction_payments_amount_nonnegative_chk'],
            ['payment_schedules', 'payment_schedules_amount_nonnegative_chk'],
            ['payment_schedules', 'payment_schedules_installment_positive_chk'],
            ['commissions', 'commissions_amount_nonnegative_chk'],
            ['commissions', 'commissions_rate_range_chk'],
            ['documents', 'documents_size_nonnegative_chk'],
            ['documents', 'documents_version_positive_chk'],
            ['document_versions', 'document_versions_size_nonnegative_chk'],
            ['document_versions', 'document_versions_version_positive_chk'],
            ['media_files', 'media_files_size_nonnegative_chk'],
            ['subscription_plans', 'subscription_plans_price_nonnegative_chk'],
        ];

        foreach ($expected as [$table, $constraint]) {
            $exists = DB::table('information_schema.TABLE_CONSTRAINTS')
                ->where('CONSTRAINT_SCHEMA', DB::raw('DATABASE()'))
                ->where('TABLE_NAME', $table)
                ->where('CONSTRAINT_NAME', $constraint)
                ->where('CONSTRAINT_TYPE', 'CHECK')
                ->exists();

            $this->assertTrue($exists, "Missing enforced CHECK constraint {$table}.{$constraint}");
        }
    }

    public function test_all_model_fillable_and_cast_attributes_exist_as_columns(): void
    {
        $modelFiles = glob(app_path('Models/*.php')) ?: [];
        $this->assertNotEmpty($modelFiles, 'No Eloquent models were discovered.');

        foreach ($modelFiles as $file) {
            $class = 'App\\Models\\' . basename($file, '.php');
            $this->assertTrue(class_exists($class), "Model class could not be autoloaded: {$class}");

            $model = new $class();
            $table = $model->getTable();
            $this->assertTrue(Schema::hasTable($table), "{$class} points to missing table {$table}");

            if (in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
                $this->assertTrue(
                    Schema::hasColumn($table, 'deleted_at'),
                    "{$class} uses SoftDeletes but {$table}.deleted_at is missing"
                );
            }

            if ($model->usesTimestamps()) {
                $this->assertTrue(Schema::hasColumn($table, 'created_at'), "{$class} expects {$table}.created_at");
                $this->assertTrue(Schema::hasColumn($table, 'updated_at'), "{$class} expects {$table}.updated_at");
            }

            $columns = array_unique(array_merge(
                $model->getFillable(),
                array_keys($model->getCasts())
            ));

            foreach ($columns as $column) {
                $this->assertTrue(
                    Schema::hasColumn($table, $column),
                    "{$class} references missing column {$table}.{$column}"
                );
            }
        }
    }
}
