<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sanctum tokens expire after 24h (config/sanctum.php). Prune the rows daily
// so the personal_access_tokens table cannot grow without bound.
Schedule::command('sanctum:prune-expired --hours=48')->daily();

// Keep failed_jobs bounded when no queue dashboard is installed.
Schedule::command('queue:prune-failed --hours=168')->weekly();

// Marketplace saved-search email alerts. Every search carries its own window
// (instant/daily/weekly), so an hourly sweep is enough for all cadences.
Schedule::command('saved-searches:send-alerts')->hourly()->withoutOverlapping();

// Vault hygiene: rejected/quarantined uploads leave the isolated quarantine
// disk once their retention window (vault.quarantine.retention_days) passes.
Schedule::command('vault:purge-quarantine')->daily();
