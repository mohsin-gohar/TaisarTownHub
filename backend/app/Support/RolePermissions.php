<?php

namespace App\Support;

/**
 * Server-side copy of the frontend permission matrix
 * (frontend/src/domain/permissions.ts).
 *
 * Admin does not receive manage_dealers, manage_users, pay_commissions,
 * or void_transactions. Unknown roles resolve to operator.
 */
class RolePermissions
{
    public const MATRIX = [
        'super_admin' => [
            'view_reports', 'view_properties', 'create_properties', 'edit_properties', 'delete_properties',
            'view_sensitive_cnic', 'view_private_documents', 'upload_documents', 'verify_documents',
            'manage_sellers', 'manage_buyers', 'manage_dealers', 'submit_buyer',
            'view_transactions', 'view_my_deals', 'create_transactions', 'update_transactions',
            'cancel_transactions', 'void_transactions', 'approve_transactions',
            'manage_payments', 'view_commissions', 'view_my_commission', 'pay_commissions',
            'view_inquiries', 'manage_projects', 'manage_media', 'upload_media', 'edit_cms', 'manage_users',
        ],
        'admin' => [
            'view_reports', 'view_properties', 'create_properties', 'edit_properties', 'delete_properties',
            'view_sensitive_cnic', 'view_private_documents', 'upload_documents', 'verify_documents',
            'manage_sellers', 'manage_buyers', 'submit_buyer',
            'view_transactions', 'view_my_deals', 'create_transactions', 'update_transactions',
            'cancel_transactions', 'approve_transactions',
            'manage_payments', 'view_commissions', 'view_my_commission',
            'view_inquiries', 'manage_projects', 'manage_media', 'upload_media', 'edit_cms',
        ],
        'manager' => [
            'view_reports', 'view_properties', 'create_properties', 'edit_properties',
            'view_private_documents', 'upload_documents', 'verify_documents',
            'manage_sellers', 'manage_buyers', 'submit_buyer',
            'view_transactions', 'view_my_deals', 'create_transactions', 'update_transactions',
            'cancel_transactions',
            'view_inquiries', 'manage_projects', 'manage_media', 'upload_media',
        ],
        'dealer' => [
            'view_properties', 'submit_buyer', 'view_my_deals', 'view_my_commission',
        ],
        'operator' => [
            'view_reports', 'view_properties', 'create_properties', 'edit_properties',
            'upload_media', 'upload_documents',
        ],
    ];

    public static function forRole(?string $role): array
    {
        return self::MATRIX[$role] ?? self::MATRIX['operator'];
    }

    public static function allows(?string $role, string $permission): bool
    {
        return in_array($permission, self::MATRIX[$role] ?? [], true);
    }
}
