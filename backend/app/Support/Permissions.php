<?php

namespace App\Support;

/**
 * Canonical enterprise permission catalog.
 *
 * - CATALOG: every permission the platform knows (label, group, platform flag).
 * - AGENCY_ROLE_GRANTS: default grants for the seven system AGENCY roles.
 * - PLATFORM_ROLE_GRANTS: default grants for platform roles (resolved from DB;
 *   RolePermissions::MATRIX remains the legacy fallback for the five legacy keys).
 * - LEGACY_ALIASES: enterprise key → legacy frontend/backend keys that mean the
 *   same capability, so existing UI gates keep working for agency users.
 *
 * Security invariant: keys with is_platform = true (or in PLATFORM_ONLY) can
 * never be granted through an agency-scoped role — enforced by
 * Permissions::agencyAssignable() and AgencyRoleController validation.
 */
class Permissions
{
    /** key => [label, group, is_platform] */
    public const CATALOG = [
        // Agency
        'agency.view' => ['View agency profile', 'agency', false],
        'agency.update' => ['Update agency profile', 'agency', false],
        'agency.manage_staff' => ['Manage staff & roles', 'agency', false],
        'agency.manage_settings' => ['Manage agency settings', 'agency', false],
        'agency.subscribe' => ['Manage subscription & billing', 'agency', false],

        // Properties
        'properties.view' => ['View properties', 'properties', false],
        'properties.create' => ['Create properties', 'properties', false],
        'properties.update' => ['Update properties', 'properties', false],
        'properties.delete' => ['Delete properties', 'properties', false],
        'properties.publish' => ['Submit listings for approval', 'properties', false],
        'properties.approve' => ['Approve / reject listings', 'properties', true],
        'properties.assign' => ['Assign properties to agents', 'properties', false],
        'properties.feature' => ['Request featured listings', 'properties', false],

        // Documents
        'documents.view' => ['View documents', 'documents', false],
        'documents.upload' => ['Upload documents', 'documents', false],
        'documents.update' => ['Edit document details', 'documents', false],
        'documents.download' => ['Download documents', 'documents', false],
        'documents.delete' => ['Delete documents', 'documents', false],
        'documents.restore' => ['Restore deleted documents', 'documents', false],
        'documents.manage_permissions' => ['Manage document sharing', 'documents', false],
        'documents.view_sensitive' => ['View sensitive documents (CNIC/registry)', 'documents', false],

        // CRM
        'crm.view' => ['View leads & inquiries', 'crm', false],
        'crm.create_lead' => ['Create leads', 'crm', false],
        'crm.update_lead' => ['Update leads', 'crm', false],
        'crm.assign_lead' => ['Assign leads to agents', 'crm', false],
        'crm.delete_lead' => ['Delete leads', 'crm', false],
        'crm.export' => ['Export CRM data', 'crm', false],

        // Finance
        'finance.view' => ['View financial records', 'finance', false],
        'finance.create_transaction' => ['Create transactions', 'finance', false],
        'finance.update_transaction' => ['Update / void transactions', 'finance', false],
        'finance.manage_payments' => ['Record & manage payments', 'finance', false],
        'finance.view_reports' => ['View financial reports', 'finance', false],

        // Platform administration
        'admin.manage_agencies' => ['Manage agencies & verification', 'admin', true],
        'admin.manage_promotions' => ['Manage featured agency placements', 'admin', true],
        'admin.manage_users' => ['Manage platform users', 'admin', true],
        'admin.manage_roles' => ['Manage roles & permissions', 'admin', true],
        'admin.manage_subscriptions' => ['Manage plans & subscriptions', 'admin', true],
        'admin.view_audit_logs' => ['View audit logs', 'admin', true],
        'admin.manage_platform_settings' => ['Manage platform settings', 'admin', true],
        'admin.view_reports' => ['View platform analytics', 'admin', true],
        'admin.view_security_events' => ['View security events', 'admin', true],
        'admin.support_tickets' => ['Handle support tickets', 'admin', true],

        // Legacy aliases (group = legacy): same capability under the old key
        // used by RolePermissions::MATRIX and frontend/src/domain/permissions.ts.
        'legacy.view_reports' => ['(legacy) view_reports', 'legacy', false],
        'legacy.view_properties' => ['(legacy) view_properties', 'legacy', false],
        'legacy.create_properties' => ['(legacy) create_properties', 'legacy', false],
        'legacy.edit_properties' => ['(legacy) edit_properties', 'legacy', false],
        'legacy.delete_properties' => ['(legacy) delete_properties', 'legacy', false],
        'legacy.view_private_documents' => ['(legacy) view_private_documents', 'legacy', false],
        'legacy.view_sensitive_cnic' => ['(legacy) view_sensitive_cnic', 'legacy', false],
        'legacy.upload_documents' => ['(legacy) upload_documents', 'legacy', false],
        'legacy.verify_documents' => ['(legacy) verify_documents', 'legacy', false],
        'legacy.manage_sellers' => ['(legacy) manage_sellers', 'legacy', false],
        'legacy.manage_buyers' => ['(legacy) manage_buyers', 'legacy', false],
        'legacy.submit_buyer' => ['(legacy) submit_buyer', 'legacy', false],
        'legacy.view_transactions' => ['(legacy) view_transactions', 'legacy', false],
        'legacy.create_transactions' => ['(legacy) create_transactions', 'legacy', false],
        'legacy.update_transactions' => ['(legacy) update_transactions', 'legacy', false],
        'legacy.cancel_transactions' => ['(legacy) cancel_transactions', 'legacy', false],
        'legacy.view_commissions' => ['(legacy) view_commissions', 'legacy', false],
        'legacy.manage_payments' => ['(legacy) manage_payments', 'legacy', false],
        'legacy.view_inquiries' => ['(legacy) view_inquiries', 'legacy', false],
        'legacy.manage_projects' => ['(legacy) manage_projects', 'legacy', false],
        'legacy.manage_media' => ['(legacy) manage_media', 'legacy', false],
        'legacy.upload_media' => ['(legacy) upload_media', 'legacy', false],
        'legacy.view_my_deals' => ['(legacy) view_my_deals', 'legacy', false],
        'legacy.view_my_commission' => ['(legacy) view_my_commission', 'legacy', false],
    ];

    /** Enterprise key => legacy keys with identical meaning. */
    public const LEGACY_ALIASES = [
        'properties.view' => ['view_properties'],
        'properties.create' => ['create_properties'],
        'properties.update' => ['edit_properties'],
        'properties.delete' => ['delete_properties'],
        'documents.view' => ['view_private_documents'],
        'documents.upload' => ['upload_documents'],
        'crm.view' => ['view_inquiries'],
        'finance.view' => ['view_transactions', 'view_commissions'],
        'finance.create_transaction' => ['create_transactions'],
        'finance.update_transaction' => ['update_transactions', 'cancel_transactions', 'void_transactions'],
        'finance.manage_payments' => ['manage_payments'],
        'agency.view' => ['view_reports'],
        'documents.manage_permissions' => ['verify_documents'],
    ];

    /** System agency role key => enterprise permission keys (aliases appended by grants()). */
    public const AGENCY_ROLE_GRANTS = [
        'agency_owner' => [
            'agency.view', 'agency.update', 'agency.manage_staff', 'agency.manage_settings', 'agency.subscribe',
            'properties.view', 'properties.create', 'properties.update', 'properties.delete',
            'properties.publish', 'properties.assign', 'properties.feature',
            'documents.view', 'documents.upload', 'documents.update', 'documents.download',
            'documents.delete', 'documents.restore', 'documents.manage_permissions', 'documents.view_sensitive',
            'crm.view', 'crm.create_lead', 'crm.update_lead', 'crm.assign_lead', 'crm.delete_lead', 'crm.export',
            'finance.view', 'finance.create_transaction', 'finance.update_transaction',
            'finance.manage_payments', 'finance.view_reports',
            'legacy.manage_sellers', 'legacy.manage_buyers', 'legacy.submit_buyer',
            'legacy.manage_media', 'legacy.upload_media',
        ],
        'agency_manager' => [
            'agency.view', 'agency.update', 'agency.manage_staff',
            'properties.view', 'properties.create', 'properties.update', 'properties.delete',
            'properties.publish', 'properties.assign', 'properties.feature',
            'documents.view', 'documents.upload', 'documents.update', 'documents.download',
            'documents.delete', 'documents.restore', 'documents.manage_permissions', 'documents.view_sensitive',
            'crm.view', 'crm.create_lead', 'crm.update_lead', 'crm.assign_lead', 'crm.delete_lead', 'crm.export',
            'finance.view', 'finance.create_transaction', 'finance.update_transaction',
            'finance.manage_payments', 'finance.view_reports',
            'legacy.manage_sellers', 'legacy.manage_buyers', 'legacy.submit_buyer',
            'legacy.manage_media', 'legacy.upload_media',
        ],
        'property_manager' => [
            'agency.view',
            'properties.view', 'properties.create', 'properties.update', 'properties.delete',
            'properties.publish', 'properties.assign', 'properties.feature',
            'documents.view', 'documents.upload', 'documents.update', 'documents.download',
            'documents.delete', 'documents.restore', 'documents.view_sensitive',
            'crm.view', 'crm.create_lead', 'crm.update_lead',
            'legacy.upload_media',
        ],
        'agent' => [
            'agency.view',
            'properties.view', 'properties.create', 'properties.update', 'properties.publish',
            'documents.view', 'documents.upload', 'documents.update', 'documents.download',
            'crm.view', 'crm.create_lead', 'crm.update_lead',
            // Legacy dealer capabilities preserved (demo dealer account parity)
            'legacy.submit_buyer', 'legacy.view_my_deals', 'legacy.view_my_commission',
        ],
        'document_manager' => [
            'agency.view',
            'properties.view',
            'documents.view', 'documents.upload', 'documents.update', 'documents.download',
            'documents.delete', 'documents.restore', 'documents.manage_permissions',
            'documents.view_sensitive',
            'legacy.upload_media', 'legacy.verify_documents',
        ],
        'accountant' => [
            'agency.view',
            'properties.view',
            'documents.view', 'documents.upload', 'documents.download',
            'finance.view', 'finance.create_transaction', 'finance.update_transaction',
            'finance.manage_payments', 'finance.view_reports',
            'legacy.view_commissions',
        ],
        'viewer' => [
            'agency.view',
            'properties.view',
            'documents.view',
            'crm.view',
        ],
    ];

    public const AGENCY_ROLE_NAMES = [
        'agency_owner' => 'Agency Owner',
        'agency_manager' => 'Agency Manager',
        'property_manager' => 'Property Manager',
        'agent' => 'Agent',
        'document_manager' => 'Document Manager',
        'accountant' => 'Accountant',
        'viewer' => 'Viewer',
    ];

    /** Platform role key => permission keys (legacy + enterprise). */
    public const PLATFORM_ROLE_GRANTS = [
        'super_admin' => null, // null = ALL catalog keys
        'admin' => null,       // ALL except platform-settings/users management below
        'moderator' => [
            'properties.approve', 'properties.view', 'properties.update',
            'admin.view_audit_logs', 'admin.view_reports', 'admin.view_security_events',
            'crm.view', 'documents.view', 'legacy.verify_documents', 'legacy.view_inquiries',
            'legacy.view_properties', 'legacy.edit_properties', 'legacy.view_reports',
        ],
        // Legacy users.role values that are platform-level (no agency membership)
        // receive the same enterprise grants as their closest platform role.
        'manager' => [
            'properties.approve', 'properties.view', 'properties.create', 'properties.update',
            'properties.delete', 'admin.view_audit_logs', 'admin.view_reports',
            'crm.view', 'documents.view', 'documents.upload', 'documents.view_sensitive',
            'finance.view', 'finance.view_reports',
            'legacy.verify_documents', 'legacy.view_inquiries', 'legacy.manage_projects',
            'legacy.manage_media', 'legacy.upload_media', 'legacy.upload_documents',
            'legacy.view_private_documents', 'legacy.view_transactions',
        ],
        'operator' => [
            'admin.support_tickets', 'agency.view', 'properties.view', 'properties.create',
            'properties.update', 'documents.upload', 'legacy.upload_media',
            'legacy.upload_documents', 'legacy.view_reports', 'legacy.view_properties',
            'legacy.create_properties', 'legacy.edit_properties',
        ],
        'support' => [
            'admin.support_tickets', 'legacy.view_reports', 'agency.view',
        ],
    ];

    /** Platform keys that even the legacy `admin` role does not get when resolving "ALL". */
    public const ADMIN_EXCLUDED = [
        'admin.manage_platform_settings',
        'admin.manage_users',
    ];

    public static function allKeys(): array
    {
        return array_keys(self::CATALOG);
    }

    /** Permissions an agency-scoped role may grant (no platform keys, no legacy dupes). */
    public static function agencyAssignable(): array
    {
        $keys = [];
        foreach (self::CATALOG as $key => [$label, $group, $isPlatform]) {
            if (!$isPlatform && $group !== 'legacy') {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    public static function isPlatform(string $key): bool
    {
        return (self::CATALOG[$key][2] ?? true) === true;
    }

    public static function label(string $key): string
    {
        return self::CATALOG[$key][0] ?? $key;
    }

    public static function group(string $key): string
    {
        return self::CATALOG[$key][1] ?? 'other';
    }

    /**
     * Full grant list for a system agency role: enterprise keys + their
     * legacy aliases (so legacy UI gates keep working).
     */
    public static function agencyRoleGrants(string $roleKey): array
    {
        $base = self::AGENCY_ROLE_GRANTS[$roleKey] ?? [];
        $legacy = [];
        foreach ($base as $key) {
            foreach (self::LEGACY_ALIASES[$key] ?? [] as $alias) {
                $legacy[] = 'legacy.' . $alias;
            }
        }
        return array_values(array_unique(array_merge($base, $legacy)));
    }

    /** Full grant list for a platform role. */
    public static function platformRoleGrants(string $roleKey): array
    {
        $all = self::allKeys();
        $grants = self::PLATFORM_ROLE_GRANTS[$roleKey] ?? null;

        if ($roleKey === 'super_admin') {
            return $all;
        }
        if ($roleKey === 'admin') {
            return array_values(array_diff($all, self::ADMIN_EXCLUDED));
        }
        return $grants ?? [];
    }

    /** Legacy RolePermissions matrix keys for a legacy role (kept in sync by RolePermissionsTest). */
    public static function legacyMatrix(): array
    {
        return RolePermissions::MATRIX;
    }
}
