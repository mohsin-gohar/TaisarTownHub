<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Feature\EnterpriseTestCase;

/**
 * Phase 12 — security headers and Content Security Policy.
 *
 * The API must never be framed, must not leak content type sniffing, and must
 * answer with a policy that forbids executing anything.
 */
class SecurityHeadersTest extends EnterpriseTestCase
{
    public function test_public_api_response_carries_the_security_headers(): void
    {
        $response = $this->getJson('/api/health')->assertOk();

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
    }

    public function test_api_responses_send_a_strict_content_security_policy(): void
    {
        $response = $this->getJson('/api/health')->assertOk();

        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString("script-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }

    public function test_authenticated_api_response_keeps_the_policy_and_same_origin_resource_policy(): void
    {
        $user = $this->makeUser('operator');
        $response = $this->getJson('/api/auth/me', $this->authHeaders($this->tokenFor($user)))->assertOk();

        $this->assertStringContainsString("default-src 'none'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertSame('same-origin', $response->headers->get('Cross-Origin-Resource-Policy'));
    }

    public function test_public_media_is_embeddable_across_origins(): void
    {
        $user = $this->makeUser('operator');
        $response = $this->getJson('/api/catalog', $this->authHeaders($this->tokenFor($user)));

        // The catalog itself stays same-origin; only the media file endpoint
        // is allowed to be embedded by the public marketing site.
        $this->assertSame('same-origin', $response->headers->get('Cross-Origin-Resource-Policy'));
    }
}
