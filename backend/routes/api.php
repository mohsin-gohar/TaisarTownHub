<?php

/*
|--------------------------------------------------------------------------
| API entry point — legacy + versioned registrations
|--------------------------------------------------------------------------
| The shared definitions (routes/api_definitions.php) are registered twice:
|
|   /api/*     — legacy contract. The current frontend ships against this;
|                kept working verbatim during the /api/v1 migration.
|   /api/v1/*  — versioned contract (route names prefixed v1.) for the
|                OpenAPI-documented public surface.
|
| Both use the same controllers, middleware and throttle rules, so behaviour
| is identical and v1 can be adopted incrementally.
*/

use Illuminate\Support\Facades\Route;

require __DIR__ . '/api_definitions.php';

// NOTE: no route-name prefix on the v1 group — Laravel's name-group prefix
// would stamp unnamed routes with the bare prefix string ("v1."), creating
// hundreds of duplicate names. This API is path-based; names are unused.
Route::prefix('v1')->group(function (): void {
    require __DIR__ . '/api_definitions.php';
});

