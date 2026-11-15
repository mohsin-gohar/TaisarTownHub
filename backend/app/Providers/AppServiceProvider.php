<?php

namespace App\Providers;

use App\Services\ClamAVScanner;
use App\Services\MalwareScanner;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MalwareScanner::class, function ($app) {
            $config = config('malware.clamav', []);
            return new ClamAVScanner(
                $config['command'] ?? 'clamscan',
                $config['socket'] ?? null,
                (bool) ($config['fail_closed'] ?? false),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ------------------------------------------------------------------
        // API rate limiters (Phase 2 — production hardening)
        //
        // Values below are initial profiling targets, not blindly-applied
        // constants. Measure real traffic under load and tune before a
        // production cutover. Limits are keyed by authenticated user id when
        // available, falling back to the client IP (which TrustProxies
        // resolves from X-Forwarded-For only for configured trusted proxies,
        // preventing IP spoofing).
        // ------------------------------------------------------------------
        RateLimiter::for('api.public', function (Request $request) {
            return Limit::perMinute(300)->by($request->ip());
        }); // public reads

        RateLimiter::for('api.auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        }); // login/register/password/MFA — credential-attack surface

        RateLimiter::for('api.write', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        }); // state-changing writes

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        }); // legacy fallback limiter (kept for backwards compatibility)
    }
}