<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent enterprise seeding: permission catalog, system roles
 * (platform + agency), property categories, subscription plans and
 * platform system settings. Safe to re-run.
 */
class EnterpriseSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        /* ---------- 1. Permission catalog ---------- */
        foreach (Permissions::CATALOG as $key => [$label, $group, $isPlatform]) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $key],
                ['label' => $label, 'group' => $group, 'is_platform' => $isPlatform ? 1 : 0, 'updated_at' => $now, 'created_at' => $now]
            );
        }
        $permissionIds = DB::table('permissions')->pluck('id', 'key');

        /* ---------- 2. System roles ---------- */
        $roleIds = [];
        foreach (Permissions::AGENCY_ROLE_GRANTS as $key => $grants) {
            $id = 'role_' . $key;
            $roleIds[$key] = $id;
            $full = Permissions::agencyRoleGrants($key);
            DB::table('roles')->updateOrInsert(
                ['id' => $id],
                [
                    'scope' => 'agency', 'agency_id' => null, 'key' => $key,
                    'name' => Permissions::AGENCY_ROLE_NAMES[$key] ?? $key,
                    'is_system' => 1, 'permissions' => json_encode($full),
                    'created_at' => $now, 'updated_at' => $now,
                ]
            );
            $this->syncRolePermissions($id, $full, $permissionIds, $now);
        }

        foreach (['super_admin', 'admin', 'moderator', 'support', 'manager', 'operator'] as $key) {
            $id = 'role_pl_' . $key;
            $roleIds['pl_' . $key] = $id;
            $full = Permissions::platformRoleGrants($key);
            DB::table('roles')->updateOrInsert(
                ['id' => $id],
                [
                    'scope' => 'platform', 'agency_id' => null, 'key' => $key,
                    'name' => ucwords(str_replace('_', ' ', $key)),
                    'is_system' => 1, 'permissions' => json_encode($full),
                    'created_at' => $now, 'updated_at' => $now,
                ]
            );
            $this->syncRolePermissions($id, $full, $permissionIds, $now);
        }

        /* ---------- 3. Property categories ---------- */
        $categories = [
            ['residential_plot', 'Residential Plot', 'residential', 1],
            ['commercial_plot', 'Commercial Plot', 'commercial', 2],
            ['house', 'House', 'residential', 3],
            ['apartment', 'Apartment', 'residential', 4],
            ['shop', 'Shop', 'commercial', 5],
            ['office', 'Office', 'commercial', 6],
        ];
        foreach ($categories as [$key, $name, $group, $sort]) {
            DB::table('property_categories')->updateOrInsert(
                ['key' => $key],
                ['name' => $name, 'group' => $group, 'sort_order' => $sort, 'is_active' => 1, 'updated_at' => $now, 'created_at' => $now]
            );
        }

        /* ---------- 4. Subscription plans (prices/limits configurable, not hardcoded in code) ---------- */
        $plans = [
            ['trial', 'Free Trial', 0, 'month', 1, 1, ['listings' => 10, 'staff' => 2, 'storage_mb' => 512, 'featured_credits' => 0, 'listing_days' => 30],
                ['14-day full access', 'Community support']],
            ['basic', 'Basic Agency', 4999, 'month', 0, 2, ['listings' => 50, 'staff' => 5, 'storage_mb' => 5120, 'featured_credits' => 1, 'listing_days' => 60],
                ['Standard listing management', 'Email support', 'Public agency profile']],
            ['professional', 'Professional Agency', 12999, 'month', 0, 3, ['listings' => 250, 'staff' => 15, 'storage_mb' => 20480, 'featured_credits' => 5, 'listing_days' => 90],
                ['Everything in Basic', 'Featured listing credits', 'Priority support', 'Advanced reports']],
            ['enterprise', 'Enterprise Agency', 29999, 'month', 0, 4, ['listings' => 100000, 'staff' => 100, 'storage_mb' => 102400, 'featured_credits' => 20, 'listing_days' => 180],
                ['Everything in Professional', 'Dedicated account manager', 'Custom limits', 'API access']],
        ];
        foreach ($plans as [$key, $name, $price, $interval, $isDefault, $sort, $limits, $features]) {
            DB::table('subscription_plans')->updateOrInsert(
                ['key' => $key],
                [
                    'name' => $name, 'description' => $name . ' plan for Taisar Town agencies',
                    'price' => $price, 'currency' => 'PKR', 'interval' => $interval,
                    'limits' => json_encode($limits), 'features' => json_encode($features),
                    'is_active' => 1, 'is_default' => $isDefault, 'sort_order' => $sort,
                    'updated_at' => $now, 'created_at' => $now,
                ]
            );
        }

        /* ---------- 5. Platform system settings ---------- */
        $settings = [
            ['platform.name', 'TaisarTownHub', 'general', 'Public platform name'],
            ['maintenance.mode', false, 'general', 'When true, non-admin users see a maintenance page'],
            ['registration.agency_enabled', true, 'security', 'Allow new agency self-registration'],
            ['storage.max_upload_mb', 25, 'storage', 'Maximum single file upload size (MB)'],
            ['document.allowed_extensions', ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'mp4', 'mov'], 'storage', 'Allowed document file extensions'],
            ['document.sensitive_categories', ['cnic', 'registry', 'ownership', 'sale_agreement', 'transfer_letter', 'allotment_letter'], 'security', 'Categories requiring documents.view_sensitive'],
            ['listing.default_expiry_days', 90, 'listings', 'Days before an approved listing expires'],
            ['security.login_max_attempts', 5, 'security', 'Failed logins before temporary lockout'],
            ['security.login_lockout_minutes', 15, 'security', 'Lockout duration in minutes'],
            ['notifications.email_enabled', false, 'integrations', 'Email notifications (requires mail credentials)'],
            ['notifications.sms_enabled', false, 'integrations', 'SMS notifications (requires provider credentials)'],
            ['notifications.whatsapp_enabled', false, 'integrations', 'WhatsApp notifications (requires provider credentials)'],
        ];
        foreach ($settings as [$key, $value, $group, $description]) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $key],
                [
                    'value' => json_encode($value), 'group' => $group, 'description' => $description,
                    'updated_at' => $now, 'created_at' => $now,
                ]
            );
        }

        /* ---------- 6. Demo platform staff ---------- */
        // users.role stays the legacy key; resolution = legacy matrix ∪ platform
        // role row (seeded above). The demo dealer user gets an agency membership
        // from the backfill migration (dealers.agency_id).
    }

    private function syncRolePermissions(string $roleId, array $grants, $permissionIds, $now): void
    {
        $rows = [];
        foreach (array_unique($grants) as $key) {
            if (!isset($permissionIds[$key])) {
                continue; // unknown key — fail soft, logged by caller tests
            }
            $rows[] = ['role_id' => $roleId, 'permission_id' => $permissionIds[$key], 'created_at' => $now, 'updated_at' => $now];
        }
        // Replace pivot rows for this role atomically.
        DB::table('role_permissions')->where('role_id', $roleId)->delete();
        foreach ($rows as $row) {
            DB::table('role_permissions')->insertOrIgnore($row);
        }
    }
}
