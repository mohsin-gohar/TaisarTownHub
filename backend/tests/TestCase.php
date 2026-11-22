<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Bound every MySQL session to a short InnoDB lock-wait timeout. CI (and
     * any MySQL-backed run) can otherwise hang for minutes when a test holds
     * a row lock while a worker process waits on the same row — the default
     * 50s timeout multiplies across the suite. SQLite ignores this entirely.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('SET SESSION innodb_lock_wait_timeout = 10');
        }
    }
}
