<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

/** Platform system administration: audit trail search + system settings. */
class AdminSystemController extends Controller
{
    use AuthorizesApi;

    public function auditLogs(Request $request)
    {
        $query = AuditLog::query();

        // Agency reporting permission grants access only to the active
        // agency's audit trail. Platform-wide access is a separate,
        // explicitly privileged capability; broad report viewers must not
        // inherit the entire platform's audit log by having no tenant scope.
        $agencyId = $this->agencyId($request);
        if ($agencyId !== null) {
            $this->requireAny($request, ['admin.view_audit_logs', 'view_reports', 'admin.view_reports']);
            $query->where('agency_id', $agencyId);
        } else {
            $this->requirePermission($request, 'admin.view_audit_logs');
            if ($request->filled('agency_id')) {
                $query->where('agency_id', $request->agency_id);
            }
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', addcslashes((string) $request->action, '%_\\') . '%');
        }
        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to . ' 23:59:59');
        }
        if ($request->filled('search')) {
            $s = addcslashes((string) $request->search, '%_\\');
            $query->where(function ($q) use ($s) {
                $q->where('actor', 'like', "%{$s}%")
                    ->orWhere('target', 'like', "%{$s}%")
                    ->orWhere('detail', 'like', "%{$s}%");
            });
        }
        if ($request->boolean('cross_agency_only')) {
            $query->where('cross_agency', true);
        }

        $page = $query->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->get('per_page', 50), 200));

        return response()->json([
            'success' => true,
            'logs' => $page->items(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    /* -------- Settings -------- */

    public function settings(Request $request)
    {
        $this->requireAny($request, ['admin.manage_platform_settings', 'admin.manage_agencies']);

        $settings = SystemSetting::orderBy('group')->orderBy('key')->get();

        return response()->json([
            'success' => true,
            'settings' => $settings->map(fn (SystemSetting $s) => [
                'key' => $s->key,
                'value' => $s->value,
                'group' => $s->group,
                'description' => $s->description,
                'updated_at' => $s->updated_at,
            ]),
        ]);
    }

    public function updateSetting(Request $request)
    {
        $this->requirePermission($request, 'admin.manage_platform_settings');

        $request->validate([
            'key' => 'required|string|max:120',
            'value' => 'required',
        ]);

        $setting = SystemSetting::where('key', $request->key)->first();
        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Unknown setting key.',
            ], 404);
        }

        $old = $setting->value;
        $setting->value = $request->value;
        $setting->updated_by = $request->user()->id;
        $setting->save();

        $this->writeAudit($request, 'setting.update', $setting->key, "Setting changed: {$setting->key}", [
            'entity_type' => 'system_setting',
            'entity_id' => (string) $setting->id,
            'cross_agency' => true,
            'metadata' => ['old' => $old, 'new' => $request->value],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Setting updated.',
            'setting' => ['key' => $setting->key, 'value' => $setting->value],
        ]);
    }
}
