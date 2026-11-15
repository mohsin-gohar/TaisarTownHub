<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Baseline security headers for every response.
 *
 * - Stops MIME sniffing, clickjacking, and cross-origin opener abuse.
 * - HSTS only on HTTPS responses (skipped for local http).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        // Content Security Policy (Phase 12): the API answers with JSON, media
        // or downloadable files — nothing here may ever execute or frame
        // content. Any HTML error page is rendered without scripts.
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'; object-src 'none'; script-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:; sandbox"
        );
        // Public marketing media must render from a different origin (the
        // frontend domain) when the API lives on its own subdomain. Private
        // API responses stay same-origin.
        $publicAsset = $request->is('api/media/*/file') || $request->is('storage/*');
        $response->headers->set(
            'Cross-Origin-Resource-Policy',
            $publicAsset ? 'cross-origin' : 'same-origin'
        );

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
