<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Redis;

/**
 * Read-only production readiness audit.
 *
 * Exit code 1 when any hard failure is found. Warnings are safe to accept on
 * a development machine but should be resolved before going live. Run this on
 * the production server after `php artisan optimize`:
 *
 *     php artisan app:production-check
 *     php artisan app:production-check --json --strict
 */
class ProductionCheck extends Command
{
    protected $signature = 'app:production-check
                            {--json : Emit a machine-readable JSON report}
                            {--strict : Treat warnings as failures}';

    protected $description = 'Audit environment, security, database, storage, queue and cache readiness';

    /** @var array<int, array{key:string,label:string,status:string,value:string,hint:string}> */
    private array $checks = [];

    public function handle(): int
    {
        $production = app()->environment('production');
        $appUrl = (string) config('app.url');
        $https = str_starts_with(strtolower($appUrl), 'https://');

        $this->add('app_env', 'APP_ENV', app()->environment(),
            $production ? 'PASS' : 'WARN',
            $production ? 'Production environment.' : 'Set APP_ENV=production on the live server.');

        $this->add('app_debug', 'APP_DEBUG', config('app.debug') ? 'true' : 'false',
            config('app.debug') ? 'FAIL' : 'PASS',
            'APP_DEBUG must be false so stack traces never reach clients.');

        $appKey = (string) config('app.key');
        $keyMaterial = str_starts_with($appKey, 'base64:')
            ? base64_decode(substr($appKey, 7), true)
            : $appKey;
        $keyValid = is_string($keyMaterial) && strlen($keyMaterial) === 32;
        $this->add('app_key', 'APP_KEY', $appKey === '' ? 'missing' : ($keyValid ? 'valid 32-byte key' : 'invalid key material'),
            $keyValid ? 'PASS' : ($production ? 'FAIL' : 'WARN'),
            'Use a stable Laravel key that decodes to exactly 32 bytes; keep it unchanged for restores and all replicas.');

        $this->add('app_url', 'APP_URL', $appUrl,
            (!$production || $https) ? 'PASS' : 'WARN',
            'Use the public HTTPS API URL in production.');

        $this->add('app_name', 'APP_NAME', (string) config('app.name'),
            (string) config('app.name') !== 'Laravel' ? 'PASS' : 'WARN',
            'Set a product name instead of the framework default.');

        $logChannel = (string) config('logging.default', 'stack');
        $logLevel = (string) config('logging.channels.' . $logChannel . '.level', config('logging.channels.single.level', 'debug'));
        $safeLogLevels = ['warning', 'error', 'critical', 'alert', 'emergency'];
        $logStatus = (!$production || in_array(strtolower($logLevel), $safeLogLevels, true)) ? 'PASS' : 'FAIL';
        $this->add('log_level', 'LOG_CHANNEL / LOG_LEVEL', $logChannel . ' / ' . $logLevel,
            $logStatus,
            'Use stderr/daily/single logging at warning or higher in production.');

        $cors = config('cors.allowed_origins', []);
        $badOrigins = array_values(array_filter((array) $cors, fn ($origin) => str_contains((string) $origin, 'localhost')
            || str_contains((string) $origin, '127.0.0.1')
            || $origin === '*'));
        $corsStatus = empty($cors) ? 'FAIL' : (($production && $badOrigins) ? 'FAIL' : 'PASS');
        $this->add('cors', 'CORS origins', empty($cors) ? 'none' : implode(', ', (array) $cors),
            $corsStatus,
            $production
                ? 'Production CORS must list only the real frontend origin(s) — no localhost or wildcard.'
                : 'Development origins are acceptable locally; tighten before production.');

        $sessionSecure = (bool) config('session.secure');
        $this->add('session_secure', 'SESSION_SECURE_COOKIE', $sessionSecure ? 'true' : 'false',
            (!$production || !$https || $sessionSecure) ? 'PASS' : 'WARN',
            'Set SESSION_SECURE_COOKIE=true when the site is served over HTTPS.');

        $stateful = implode(',', (array) config('sanctum.stateful', []));
        $statefulLocal = str_contains($stateful, 'localhost') || str_contains($stateful, '127.0.0.1');
        $this->add('sanctum_stateful', 'SANCTUM_STATEFUL_DOMAINS',
            $stateful === '' ? 'default' : $stateful,
            (!$production || !$statefulLocal) ? 'PASS' : 'WARN',
            'List only the production frontend domain(s) in production.');

        $expiration = config('sanctum.expiration');
        $this->add('sanctum_expiration', 'Token lifetime',
            $expiration ? $expiration . ' minutes' : 'never',
            ($production && !$expiration) ? 'WARN' : 'PASS',
            'Keep a finite Sanctum token lifetime in production.');

        $prefix = (string) config('sanctum.token_prefix', '');
        $this->add('token_prefix', 'SANCTUM_TOKEN_PREFIX', $prefix === '' ? 'none' : $prefix,
            ($production && $prefix === '') ? 'WARN' : 'PASS',
            'A token prefix makes leaked credentials identifiable and revocable.');

        $dbOk = false;
        try {
            DB::select('select 1');
            $dbOk = true;
            $this->add('database', 'Database', DB::connection()->getDriverName(),
                'PASS', 'Connection established.');
        } catch (\Throwable $e) {
            $this->add('database', 'Database', 'unreachable', 'FAIL',
                'Fix DB connection settings: ' . $e->getMessage());
        }

        if ($dbOk) {
            try {
                $ran = DB::table('migrations')->pluck('migration')->all();
                $files = array_map(
                    fn ($path) => basename($path, '.php'),
                    glob(database_path('migrations/*.php')) ?: []
                );
                $pending = array_values(array_diff($files, $ran));
                $this->add('migrations', 'Pending migrations', (string) count($pending),
                    empty($pending) ? 'PASS' : 'FAIL',
                    empty($pending)
                        ? 'Schema is up to date.'
                        : 'Run php artisan migrate --force before serving traffic: ' . implode(', ', array_slice($pending, 0, 5)));
            } catch (\Throwable $e) {
                $this->add('migrations', 'Pending migrations', 'unknown', 'WARN',
                    'Could not read the migrations table: ' . $e->getMessage());
            }
        } else {
            $this->add('migrations', 'Pending migrations', 'skipped', 'WARN', 'Database unavailable.');
        }

        $storage = [
            'storage/framework/cache',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/logs',
            'storage/app/private',
            'storage/app/private/documents',
            'storage/app/private/quarantine',
            'storage/app/public',
        ];
        $unwritable = [];
        foreach ($storage as $relative) {
            $path = base_path($relative);
            if (!is_dir($path) || !is_writable($path)) {
                $unwritable[] = $relative;
            }
        }
        $this->add('storage', 'Writable storage',
            (count($storage) - count($unwritable)) . '/' . count($storage) . ' directories',
            empty($unwritable) ? 'PASS' : 'FAIL',
            empty($unwritable)
                ? 'Private vault and framework caches are writable.'
                : 'Make writable by the PHP user: ' . implode(', ', $unwritable));

        $vaultEncryptionEnabled = \\App\\Services\\FileVaultCrypto::enabled();
        $this->add('vault_encryption', 'Private vault encryption', $vaultEncryptionEnabled ? 'enabled' : 'disabled',
            (!$production || $vaultEncryptionEnabled) ? 'PASS' : 'FAIL',
            'AES-256-GCM encryption must remain enabled for private document and quarantine storage.');

        $malwareEnabled = (bool) config('malware.enabled');
        $malwareFailClosed = (bool) config('malware.fail_closed');
        $malwareScanner = (string) config('malware.scanner', '');
        $clamdSocket = (string) config('malware.clamav.socket', '');
        $clamdReachable = false;
        if ($clamdSocket !== '' && str_contains($clamdSocket, ':')) {
            [$clamdHost, $clamdPort] = explode(':', $clamdSocket, 2);
            if ($clamdHost !== '' && ctype_digit($clamdPort)) {
                $clamd = @fsockopen($clamdHost, (int) $clamdPort, $clamdErrno, $clamdError, 2);
                if (is_resource($clamd)) {
                    fclose($clamd);
                    $clamdReachable = true;
                }
            }
        }
        $malwareReady = $malwareEnabled && $malwareFailClosed && $malwareScanner === 'clamav' && $clamdReachable;
        $this->add('malware_scan', 'Fail-closed ClamAV scanning',
            $malwareScanner . ' / ' . ($malwareEnabled ? 'enabled' : 'disabled') . ' / ' . ($clamdReachable ? 'reachable' : 'unreachable'),
            (!$production || $malwareReady) ? 'PASS' : 'FAIL',
            'Production uploads require MALWARE_SCAN_ENABLED=true, MALWARE_SCAN_FAIL_CLOSED=true and a reachable clamd socket.');

        $queue = (string) config('queue.default');
        $queueStatus = 'PASS';
        $queueHint = 'Queue driver is production-safe.';
        if ($production && $queue === 'sync') {
            $queueStatus = 'WARN';
            $queueHint = 'Use database/redis and run a supervised queue worker.';
        } elseif ($queue === 'database' && !Schema::hasTable('jobs')) {
            $queueStatus = 'FAIL';
            $queueHint = 'The jobs table is missing — run migrations.';
        }
        $this->add('queue', 'Queue connection', $queue, $queueStatus, $queueHint);

        $cache = (string) config('cache.default');
        $cacheStatus = ($production && $cache === 'array') ? 'WARN' : 'PASS';
        if ($cache === 'database' && !Schema::hasTable('cache')) {
            $cacheStatus = 'FAIL';
        }
        $this->add('cache', 'Cache store', $cache, $cacheStatus,
            $cacheStatus === 'PASS' ? 'Cache is shared and durable.' : 'Use database/redis in production.');

        $session = (string) config('session.driver');
        $sessionStatus = ($production && $session === 'array') ? 'WARN' : 'PASS';
        if ($session === 'database' && !Schema::hasTable('sessions')) {
            $sessionStatus = 'FAIL';
        }
        $this->add('session', 'Session driver', $session, $sessionStatus,
            $sessionStatus === 'PASS' ? 'Session storage is durable.' : 'Use database/redis in production.');

        $redisRequired = $production && in_array('redis', [$queue, $cache, $session], true);
        $redisStatus = 'PASS';
        $redisValue = 'not required';
        $redisHint = 'No Redis-backed production service is configured.';
        if ($redisRequired) {
            try {
                Redis::connection()->ping();
                $redisValue = 'reachable';
                $redisHint = 'Redis connectivity verified for cache, queue and/or sessions.';
            } catch (\\Throwable $exception) {
                $redisStatus = 'FAIL';
                $redisValue = 'unreachable';
                $redisHint = 'Redis must be reachable from the application container: ' . $exception->getMessage();
            }
        }
        $this->add('redis', 'Redis connectivity', $redisValue, $redisStatus, $redisHint);

        $mailer = (string) config('mail.default', 'log');
        $mailHost = strtolower(trim((string) config('mail.mailers.smtp.host', '')));
        $mailFrom = (string) config('mail.from.address', '');
        $mailUsername = (string) config('mail.mailers.smtp.username', '');
        $mailPassword = (string) config('mail.mailers.smtp.password', '');
        $mailHostValid = $mailHost !== '' && !in_array($mailHost, ['localhost', '127.0.0.1'], true)
            && !str_contains($mailHost, 'example.com') && !str_contains($mailHost, 'change_me');
        $mailFromValid = filter_var($mailFrom, FILTER_VALIDATE_EMAIL) !== false
            && !str_contains(strtolower($mailFrom), 'example.com');
        $mailAuthConsistent = ($mailUsername === '' && $mailPassword === '') || ($mailUsername !== '' && $mailPassword !== '');
        $mailCredentialsReal = !str_starts_with(strtolower($mailUsername), 'change_me')
            && !str_starts_with(strtolower($mailPassword), 'change_me');
        $mailReady = !in_array($mailer, ['log', 'array'], true) && $mailHostValid && $mailFromValid && $mailAuthConsistent && $mailCredentialsReal;
        $this->add('mail', 'SMTP configuration', $mailer . ' / ' . ($mailHost !== '' ? $mailHost : 'missing') . ' / ' . $mailFrom,
            (!$production || $mailReady) ? 'PASS' : 'FAIL',
            'Configure a real SMTP host and sender address; this check validates configuration, not inbox delivery.');

        $googleClientId = trim((string) config('services.google.client_id', ''));
        $googleClientSecret = trim((string) config('services.google.client_secret', ''));
        $googleRedirect = (string) config('services.google.redirect', '');
        $googlePartial = ($googleClientId === '') xor ($googleClientSecret === '');
        $googleRedirectValid = ($googleClientId === '' && $googleClientSecret === '')
            || str_starts_with(strtolower($googleRedirect), 'https://');
        $googleStatus = ($googlePartial || !$googleRedirectValid) ? 'FAIL' : 'PASS';
        $this->add('google_oauth', 'Google OAuth', ($googleClientId !== '' && $googleClientSecret !== '') ? 'configured' : 'disabled',
            $googleStatus,
            'Google OAuth is optional; when enabled, both credentials and an HTTPS callback URI are required.');

        $this->add('config_cache', 'Configuration cache',
            app()->configurationIsCached() ? 'cached' : 'not cached',
            (!$production || app()->configurationIsCached()) ? 'PASS' : 'WARN',
            'Run php artisan optimize on the production server.');

        $this->add('route_cache', 'Route cache',
            app()->routesAreCached() ? 'cached' : 'not cached',
            (!$production || app()->routesAreCached()) ? 'PASS' : 'WARN',
            'Run php artisan optimize on the production server.');

        $rounds = (int) config('hashing.bcrypt.rounds', 12);
        $this->add('bcrypt', 'BCRYPT_ROUNDS', (string) $rounds,
            ($production && $rounds < 10) ? 'FAIL' : 'PASS',
            'Keep bcrypt cost >= 10 in production.');

        try {
            $scheduled = count(app(Schedule::class)->events());
            $this->add('scheduler', 'Scheduled tasks', $scheduled . ' registered',
                $scheduled > 0 ? 'PASS' : 'WARN',
                'Ensure cron runs: * * * * * php artisan schedule:run >> /dev/null 2>&1');
        } catch (\Throwable $e) {
            $this->add('scheduler', 'Scheduled tasks', 'unknown', 'WARN', $e->getMessage());
        }

        $summary = [
            'pass' => count(array_filter($this->checks, fn ($c) => $c['status'] === 'PASS')),
            'warn' => count(array_filter($this->checks, fn ($c) => $c['status'] === 'WARN')),
            'fail' => count(array_filter($this->checks, fn ($c) => $c['status'] === 'FAIL')),
        ];

        if ($this->option('json')) {
            $this->output->writeln(json_encode([
                'environment' => app()->environment(),
                'generated_at' => now()->toIso8601String(),
                'checks' => $this->checks,
                'summary' => $summary,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->newLine();
            $this->line('  TaisarTownHub production readiness — ' . app()->environment());
            $this->newLine();
            $this->table(
                ['Check', 'Status', 'Value', 'Hint'],
                array_map(fn ($c) => [$c['label'], $c['status'], $c['value'], $c['hint']], $this->checks)
            );
            $this->line(sprintf(
                '  %d passed · %d warnings · %d failures',
                $summary['pass'],
                $summary['warn'],
                $summary['fail']
            ));

            if ($summary['fail'] > 0) {
                $this->error('Production readiness FAILED — resolve the failures above.');
            } elseif ($this->option('strict') && $summary['warn'] > 0) {
                $this->error('Strict mode: warnings are treated as failures.');
            } else {
                $this->info($summary['warn'] > 0
                    ? 'No hard failures. Review the warnings before go-live.'
                    : 'All production checks passed.');
            }
        }

        if ($summary['fail'] > 0 || ($this->option('strict') && $summary['warn'] > 0)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function add(string $key, string $label, string $value, string $status, string $hint): void
    {
        $this->checks[] = compact('key', 'label', 'status', 'value', 'hint');
    }
}
