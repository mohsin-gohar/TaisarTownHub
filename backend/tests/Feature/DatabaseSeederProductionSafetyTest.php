<?php

namespace Tests\Feature;

use App\Models\BusinessRecord;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Production must never receive demo fixtures. The enterprise catalog is
 * configuration and may run; fixed-ID demo rows and demo accounts may not.
 */
class DatabaseSeederProductionSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_seed_skips_demo_fixtures_and_preserves_existing_records(): void
    {
        $this->app->instance('env', 'production');
        $this->assertSame('production', app()->environment());

        // Sentinels use the exact fixed IDs the demo seeder would overwrite.
        DB::table('dealers')->insert([
            'id' => 'deal-1', 'fullName' => 'Sentinel Dealer', 'phone' => '03000000000',
        ]);
        DB::table('properties')->insert([
            'id' => 'prop-1', 'title' => 'Sentinel Property', 'agency_id' => null,
        ]);
        User::create([
            'name' => 'Sentinel Admin',
            'email' => 'admin@taisartownhub.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->artisan('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--force' => true,
            '--no-interaction' => true,
        ])->assertExitCode(0);

        $this->assertSame('Sentinel Dealer', DB::table('dealers')->where('id', 'deal-1')->value('fullName'));
        $this->assertSame('Sentinel Property', DB::table('properties')->where('id', 'prop-1')->value('title'));
        $this->assertSame('Sentinel Admin', User::where('email', 'admin@taisartownhub.com')->value('name'));

        $this->assertSame(0, BusinessRecord::count(), 'Legacy/demo business records must not be seeded in production.');
        $this->assertSame(0, DB::table('agencies')->count(), 'Demo agency fixture must not be created in production.');
        $this->assertSame(0, User::whereIn('email', [
            'superadmin@taisartownhub.com', 'manager@taisartownhub.com',
            'dealer@taisartownhub.com', 'operator@taisartownhub.com',
        ])->count(), 'Demo staff accounts must not be created in production.');

        // The idempotent enterprise catalog IS seeded in production.
        $this->assertGreaterThan(0, DB::table('permissions')->count());
        $this->assertGreaterThan(0, DB::table('roles')->count());
    }
}
