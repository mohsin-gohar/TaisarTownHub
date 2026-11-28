<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Enforce numeric invariants at the database boundary.
 *
 * MySQL 8.0.16+ enforces CHECK constraints. Existing invalid rows are reported
 * before any DDL is attempted; this migration never rewrites financial data.
 */
return new class extends Migration
{
    private array $checks = [
        'transaction_payments' => [
            'transaction_payments_amount_nonnegative_chk' => 'amount >= 0',
        ],
        'payment_schedules' => [
            'payment_schedules_amount_nonnegative_chk' => 'amount >= 0',
            'payment_schedules_installment_positive_chk' => 'installment_no >= 1',
        ],
        'commissions' => [
            'commissions_amount_nonnegative_chk' => 'amount >= 0',
            'commissions_rate_range_chk' => '(rate IS NULL OR (rate >= 0 AND rate <= 100))',
        ],
        'documents' => [
            'documents_size_nonnegative_chk' => 'size_bytes >= 0',
            'documents_version_positive_chk' => 'version >= 1',
        ],
        'document_versions' => [
            'document_versions_size_nonnegative_chk' => 'size_bytes >= 0',
            'document_versions_version_positive_chk' => 'version >= 1',
        ],
        'media_files' => [
            'media_files_size_nonnegative_chk' => 'size_bytes >= 0',
        ],
        'subscription_plans' => [
            'subscription_plans_price_nonnegative_chk' => 'price >= 0',
        ],
    ];

    public function up(): void
    {
        // SQLite is used by some lightweight test environments; production's
        // supported database is MySQL 8. Do not emit non-portable DDL there.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $version = (string) DB::selectOne('SELECT VERSION() AS version')->version;
        if (stripos($version, 'mariadb') === false && version_compare($version, '8.0.16', '<')) {
            throw new RuntimeException('Database CHECK constraints require MySQL 8.0.16 or newer.');
        }

        $violations = [];
        foreach ($this->checks as $table => $constraints) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException("Cannot add database checks: required table {$table} is missing.");
            }

            foreach ($constraints as $name => $expression) {
                $column = preg_match('/^([a-z_]+)\s/', $expression, $matches) ? $matches[1] : null;
                if ($column !== null && !Schema::hasColumn($table, $column)) {
                    throw new RuntimeException("Cannot add database check {$name}: {$table}.{$column} is missing.");
                }

                if (DB::table($table)->whereRaw("NOT ({$expression})")->exists()) {
                    $violations[] = "{$table} violates {$name}";
                }
            }
        }

        if ($violations !== []) {
            throw new RuntimeException(
                'Cannot enforce database CHECK constraints; reconcile invalid rows first: '
                . implode('; ', $violations)
            );
        }

        foreach ($this->checks as $table => $constraints) {
            foreach ($constraints as $name => $expression) {
                $exists = DB::table('information_schema.TABLE_CONSTRAINTS')
                    ->where('CONSTRAINT_SCHEMA', DB::raw('DATABASE()'))
                    ->where('TABLE_NAME', $table)
                    ->where('CONSTRAINT_NAME', $name)
                    ->where('CONSTRAINT_TYPE', 'CHECK')
                    ->exists();

                if (!$exists) {
                    DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` CHECK ({$expression})");
                }
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->checks as $table => $constraints) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach (array_keys($constraints) as $name) {
                $exists = DB::table('information_schema.TABLE_CONSTRAINTS')
                    ->where('CONSTRAINT_SCHEMA', DB::raw('DATABASE()'))
                    ->where('TABLE_NAME', $table)
                    ->where('CONSTRAINT_NAME', $name)
                    ->where('CONSTRAINT_TYPE', 'CHECK')
                    ->exists();

                if ($exists) {
                    DB::statement("ALTER TABLE `{$table}` DROP CHECK `{$name}`");
                }
            }
        }
    }
};
