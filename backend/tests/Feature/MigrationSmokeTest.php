<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MigrationSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_run_on_sqlite(): void
    {
        $this->assertDatabaseCount('users', 0);
        $this->assertTrue(true);
    }
}
