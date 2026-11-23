<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Real fresh-install migration smoke test on the configured MySQL test
 * database (phpunit.xml forces DB_CONNECTION=mysql; no SQLite fallback).
 */
class MigrationSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_migrations_create_the_core_schema(): void
    {
        foreach ([
            'users', 'agencies', 'agency_members', 'roles', 'permissions',
            'properties', 'transactions', 'transaction_payments', 'commissions',
            'system_settings', 'maps', 'documents', 'login_histories',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "missing table: {$table}");
        }

        $this->assertTrue(Schema::hasColumn('transaction_payments', 'deleted_at'));
        $this->assertTrue(Schema::hasColumn('properties', 'listing_purpose'));
        $this->assertTrue(Schema::hasColumn('properties', 'contactWhatsApp'));
        $this->assertTrue(Schema::hasColumn('agencies', 'whatsapp'));
        $this->assertDatabaseCount('users', 0);
    }
}
