<?php

namespace App\Providers;

use App\Services\ClamAVScanner;
use App\Services\DisabledMalwareScanner;
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
            // Master switch: when scanning is disabled no scan is attempted and
            // nothing is ever reported as "clean" (DisabledMalwareScanner).
            if (!config('malware.enabled', false)) {
                return new DisabledMalwareScanner();
            }

            $config = config('malware.clamav', []);

            return new ClamAVScanner(
                $config['command'] ?? 'clamscan',
                $config['socket'] ?? null,
                (bool) config('malware.fail_closed', false),
                (int) ($config['timeout'] ?? 30),
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
            // Fallback for routes without a specific limiter. throttleApi()
            // applies this in addition to every route's own throttle, so this
            // ceiling must never sit BELOW the route-specific limits: the old
            // 60/min value silently capped the 300/min public marketplace and
            // throttled ordinary browsing (61st request -> 429). Auth (10/min)
            // and write (30/min) routes still enforce their stricter limits.
            return $request->user()
                ? Limit::perMinute(600)->by($request->user()->getAuthIdentifier())
                : Limit::perMinute(300)->by($request->ip());
        }); // legacy fallback limiter (kept for backwards compatibility)
    }
}