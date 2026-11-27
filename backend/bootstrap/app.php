<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->throttleApi();
        // G-16: outermost middleware so the correlation ID wraps (and is
        // echoed on) every response, including error renderers.
        $middleware->prepend([\App\Http\Middleware\CorrelationId::class]);
        // Resolve the real client IP from X-Forwarded-For only for configured
        // trusted proxies (prevents IP spoofing for rate limiting & audit logs).
        $middleware->append([\App\Http\Middleware\TrustProxies::class]);
        // Baseline security headers on every response (global stack).
        $middleware->append([\App\Http\Middleware\SecurityHeaders::class]);
        // Resolve the acting agency from the authenticated user's OWN
        // memberships (never from request bodies) for every API request.
        $middleware->api(append: [\App\Http\Middleware\AgencyContext::class]);
        // Token-only API: never redirect unauthenticated guests to a named
        // 'login' web route (Laravel's default calls route('login') inside the
        // Authenticate middleware, which crashes with RouteNotFoundException
        // before the 401 JSON renderer can run).
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Unauthenticated API requests must get JSON 401 — never a redirect to
        // a named 'login' route (which does not exist in this token-only API).
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required.',
                ], 401);
            }
        });

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                // Preserve Laravel's expected HTTP semantics for common
                // framework exceptions instead of flattening them into 500s.
                $status = match (true) {
                    $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException => 404,
                    $e instanceof \Illuminate\Auth\Access\AuthorizationException => 403,
                    $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface => $e->getStatusCode(),
                    default => 500,
                };

                // Keep API responses stable and avoid disclosing model names,
                // SQL details, file paths or internal exception messages.
                $message = match ($status) {
                    403 => 'Permission denied.',
                    404 => 'Resource not found.',
                    422 => 'Validation failed.',
                    429 => 'Too many requests.',
                    default => 'Server error.',
                };
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], $status);
            }
        });
    })->create();