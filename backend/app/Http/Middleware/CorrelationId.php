<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * G-16 — request correlation IDs.
 *
 * Every request gets an `X-Request-Id` (echoed from the caller when it looks
 * sane, otherwise generated). The ID is:
 *   - returned on every response as `X-Request-Id`,
 *   - attached to every log line written during the request,
 *   - available to controllers via $request->attributes->get('request_id')
 *     so audit trails can reference a whole request.
 */
class CorrelationId
{
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next)
    {
        $incoming = (string) $request->headers->get(self::HEADER, '');

        // Accept only sane client-supplied ids (log-injection safety).
        $requestId = preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{7,63}$/', $incoming)
            ? $incoming
            : (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);

        Log::withContext(['request_id' => $requestId]);

        $response = $next($request);

        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }
}
