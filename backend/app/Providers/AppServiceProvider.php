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
        $publicPerMinute = max(1, (int) env('API_PUBLIC_RATE_LIMIT', 300));
        $authPerMinute = max(1, (int) env('API_AUTH_RATE_LIMIT', 10));
        $writePerMinute = max(1, (int) env('API_WRITE_RATE_LIMIT', 30));
        $fallbackPerMinute = max(1, (int) env('API_FALLBACK_RATE_LIMIT', 300));
        $fallbackAuthenticatedPerMinute = max(1, (int) env('API_FALLBACK_AUTH_RATE_LIMIT', 600));

        RateLimiter::for('api.public', function (Request $request) use ($publicPerMinute) {
            return Limit::perMinute($publicPerMinute)->by($request->ip());
        }); // public reads

        RateLimiter::for('api.auth', function (Request $request) use ($authPerMinute) {
            return Limit::perMinute($authPerMinute)->by($request->ip());
        }); // login/register/password/MFA — credential-attack surface

        RateLimiter::for('api.write', function (Request $request) use ($writePerMinute) {
            return Limit::perMinute($writePerMinute)->by($request->user()?->id ?: $request->ip());
        }); // state-changing writes

        RateLimiter::for('api', function (Request $request) use ($fallbackPerMinute, $fallbackAuthenticatedPerMinute) {
            // Fallback must never sit below route-specific limits. Production
            // defaults remain 300/min public, 10/min auth, 30/min writes.
            // Disposable CI may raise these limits for exhaustive browser audits.
            return $request->user()
                ? Limit::perMinute($fallbackAuthenticatedPerMinute)->by($request->user()->getAuthIdentifier())
                : Limit::perMinute($fallbackPerMinute)->by($request->ip());
        }); // legacy fallback limiter (kept for backwards compatibility)
    }
}