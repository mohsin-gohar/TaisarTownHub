<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as BaseTrustProxies;

/**
 * Resolve the real client IP from X-Forwarded-For only for configured trusted
 * proxies, preventing IP spoofing so rate limiting and audit logs are bound
 * to the actual caller rather than a forgeable header.
 *
 * Set TRUSTED_PROXIES to a comma-separated list of CIDRs/IPs (e.g.
 * "127.0.0.1,10.0.0.0/8"). Defaults to 127.0.0.1 only (safe when no proxy is
 * in front of the app — the operator must add their real load-balancer IPs).
 */
class TrustProxies extends BaseTrustProxies
{
    protected $proxies = [];

    public function __construct()
    {
        $raw = (string) env('TRUSTED_PROXIES', '127.0.0.1');
        if ($raw !== '') {
            $this->proxies = array_unique(array_map('trim', explode(',', $raw)));
        }
    }
}
