<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

trait AuthorizesApi
{
    protected function requireAny(Request $request, array $permissions): void
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Authentication required.');
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return;
            }
        }

        abort(403, 'Permission denied.');
    }

    protected function writeAudit(Request $request, string $action, string $target, string $detail): void
    {
        $user = $request->user();
        $row = [
            'actor' => $user ? $user->name : 'anonymous',
            'role' => $user ? $user->role : 'none',
            'action' => $action,
            'target' => $target,
            'detail' => $detail,
            'ip' => $request->ip(),
            'created_at' => now(),
        ];

        Log::info('AUDIT', $row);

        try {
            AuditLog::create($row);
        } catch (\Throwable $e) {
            Log::warning('Audit table write failed', ['error' => $e->getMessage()]);
        }
    }
}
