<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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
