<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;

/**
 * API rate limiting (Phase 2).
 *
 * Covers legitimate traffic under the limit, abuse that must receive 429,
 * per-class limits (public reads / auth / writes), and that rate limiting is
 * bound to the resolved client IP.
 *
 * All limits are exercised through real HTTP requests so the router's
 * throttle middleware (not the limiter facade) is what is under test.
 */
class RateLimitTest extends EnterpriseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Full-suite determinism: the `api.auth` limiter is IP-keyed and
        // shared per minute (10/min); other tests also hit /api/auth/login,
        // so clear the shared rate-limit/cache store before each test.
        Cache::flush();
    }

    public function test_login_rejects_abuse_with_429(): void
    {
        $this->makeUser('operator', ['email' => 'ratelimit@example.test']);

        // 10 attempts allowed (api.auth = 10/min). Wrong password => 422, but
        // NOT rate-limited yet.
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'ratelimit@example.test',
                'password' => 'WrongPassword!',
            ])->assertStatus(422);
        }

        // 11th must be throttled (429).
        $this->postJson('/api/auth/login', [
            'email' => 'ratelimit@example.test',
            'password' => 'WrongPassword!',
        ])->assertStatus(429);
    }

    public function test_public_read_catalog_under_limit_passes(): void
    {
        $this->makePublicProperty($this->makeAgency());
        $this->asGuest();

        // 100 requests must pass. Regression: the global `api` fallback used
        // to be 60/min and undercut the 300/min public-read limiter, so the
        // 61st request was throttled even though the route's own budget was
        // far from exhausted.
        for ($i = 0; $i < 100; $i++) {
            $this->getJson('/api/catalog')->assertStatus(200);
        }
    }

    public function test_write_endpoint_rejects_abuse_with_429(): void
    {
        $this->asGuest();

        // 30 public inquiry writes allowed (api.write = 30/min by IP).
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/api/inquiries', [
                'fullName' => 'Rate Tester ' . $i,
                'phone' => '+9230000000' . $i,
                'message' => 'Write rate-limit probe ' . $i,
            ] + $this->solveInquiryCaptcha())->assertStatus(201);
        }

        // 31st must be throttled (429). Rate limiting is enforced by the
        // router middleware BEFORE validation, so no captcha solve is
        // needed to prove the 429 path.
        $this->postJson('/api/inquiries', [
            'fullName' => 'Rate Overflow',
            'phone' => '+92300000000',
            'message' => 'Write rate-limit overflow',
        ])->assertStatus(429);
    }

    public function test_rate_limit_is_bound_to_resolved_client_ip(): void
    {
        // All test requests originate from 127.0.0.1, so IP-based limiting
        // counts them together regardless of any X-Forwarded-For header the
        // client sends (127.0.0.1 is the only trusted proxy by default; in
        // production TRUSTED_PROXIES must be set to the real load-balancer
        // IPs, otherwise spoofed X-Forwarded-For values would be trusted).
        $this->makeUser('operator', ['email' => 'iplimit@example.test']);

        // 10 attempts allowed (api.auth = 10/min). Wrong password => 422, not
        // yet rate-limited.
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'iplimit@example.test',
                'password' => 'WrongPassword!',
            ])->assertStatus(422);
        }

        // 11th must be throttled (429).
        $this->postJson('/api/auth/login', [
            'email' => 'iplimit@example.test',
            'password' => 'WrongPassword!',
        ])->assertStatus(429);
    }
}
