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
                $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                // Never leak internals for server errors; exception details are
                // written to the Laravel log instead. Class names are only
                // appended in local debug mode for non-5xx responses.
                $message = $status >= 500 ? 'Server error.' : $e->getMessage();
                if (config('app.debug') && $status < 500) {
                    $message .= ' [' . get_class($e) . ']';
                }
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], $status);
            }
        });
    })->create();