/**
 * Central authorization domain — Phase 2 (P0-4).
 *
 * Rules of this module:
 *  1. Exactly one canonical role id set and ONE permission matrix. UI labels
 *     are separate from ids, so two spellings of the same role can never again
 *     drift apart.
 *  2. `authorize()` is the single gate every mutating action must call.
 *     It returns a typed `{ ok }` result instead of throwing, and enforces
 *     scoping (a dealer may act only on their own records) in the domain
 *     layer — not just by hiding UI.
 *  3. Explicit `permissions` arrays on an AuthUser are treated as ADDITIONAL
 *     grants over the role matrix (union). 'all' grants everything.
 */
import { UserRole } from '../types';

export type Role = 'super_admin' | 'admin' | 'manager' | 'dealer' | 'operator' | 'visitor';

export const ROLE_LABELS: Record<Role, string> = {
  super_admin: 'Super Admin',
  admin: 'Agency Admin',
  manager: 'Branch Manager',
  dealer: 'Property Dealer',
  operator: 'Data Entry Operator',
  visitor: 'Public Visitor',
};

/**
 * Maps any legacy/display/alias role spelling to the canonical Role id.
 * Unknown values fall back to 'operator' (least privilege).
 */
export function normalizeRole(input: unknown): Role {
  const key = String(input ?? '').toLowerCase().replace(/[\s-]+/g, '_');
  switch (key) {
    case 'super_admin':
    case 'superadmin':
    case 'super admin':
      return 'super_admin';
    case 'admin':
    case 'agency_admin':
    case 'agency director admin':
      return 'admin';
    case 'manager':
    case 'branch_manager':
    case 'operations_manager':
      return 'manager';
    case 'dealer':
    case 'property_dealer':
    case 'real_estate_dealer':
    case 'broker':
      return 'dealer';
    case 'operator':
    case 'data_entry_operator':
    case 'data_operator':
    case 'entry_operator':
      return 'operator';
    case 'visitor':
    case 'anonymous':
    case 'guest':
    case 'public':
      return 'visitor';
    default:
      return 'operator';
  }
}

/** Every granular permission id used by the UI and the domain layer. */
export type Permission =
  | 'view_reports'
  | 'view_properties'
  | 'create_properties'
  | 'edit_properties'
  | 'delete_properties'
  | 'view_sensitive_cnic'
  | 'view_private_documents'
  | 'upload_documents'
  | 'verify_documents'
  | 'manage_sellers'
  | 'manage_buyers'
  | 'manage_dealers'
  | 'submit_buyer'
  | 'view_transactions'
  | 'view_my_deals'
  | 'create_transactions'
  | 'update_transactions'
  | 'cancel_transactions'
  | 'void_transactions'
  | 'approve_transactions'
  | 'manage_payments'
  | 'view_commissions'
  | 'view_my_commission'
  | 'pay_commissions'
  | 'view_inquiries'
  | 'crm.update_lead'
  | 'crm.delete_lead'
  | 'manage_projects'
  | 'manage_media'
  | 'upload_media'
  | 'edit_cms'
  | 'manage_users';

/** Human-readable labels for the Security & Roles matrix UI. */
export const PERMISSION_LABELS: Record<Permission, string> = {
  view_reports: 'View dashboard & reports',
  view_properties: 'View property listings',
  create_properties: 'Create properties',
  edit_properties: 'Edit properties',
  delete_properties: 'Delete properties',
  view_sensitive_cnic: 'View unmasked CNIC & private prices',
  view_private_documents: 'View private documents (CNIC copies, agreements)',
  upload_documents: 'Upload verification documents',
  verify_documents: 'Verify / approve documents',
  manage_sellers: 'Manage seller records',
  manage_buyers: 'Manage buyer records',
  manage_dealers: 'Manage dealer profiles',
  submit_buyer: 'Submit buyer references',
  view_transactions: 'View all deals & transactions',
  view_my_deals: 'View own (scoped) deals',
  create_transactions: 'Create deals',
  update_transactions: 'Update deal status & details',
  cancel_transactions: 'Cancel deals (with reason)',
  void_transactions: 'Void deals (permanent, audited)',
  approve_transactions: 'Approve deals & bypass helpers',
  manage_payments: 'Record payments & refunds',
  view_commissions: 'View all dealer commissions',
  view_my_commission: 'View own commission statement',
  pay_commissions: 'Execute commission payouts',
  view_inquiries: 'View inquiry CRM',
  'crm.update_lead': 'Update inquiry status & notes',
  'crm.delete_lead': 'Delete inquiries',
  manage_projects: 'Manage projects',
  manage_media: 'Manage media library',
  upload_media: 'Upload media',
  edit_cms: 'Edit website CMS',
  manage_users: 'Manage users & security settings',
};

/**
 * THE matrix (Phase 2). Granular by design:
 *  - Admin has NO manage_dealers / manage_users / pay_commissions / void.
 *    Only Super Admin executes payouts.
 *  - Manager handles operations (deals, verification) without unmasked CNIC,
 *    deletions or financial trust actions.
 *  - Dealer is scoped: own deals + own commission + buyer submission only.
 *  - Operator is data-entry: listings, uploads; NO deal visibility at all and
 *    no verification rights (their uploads land in the pending queue).
 */
export const ROLE_PERMISSIONS: Record<Role, readonly Permission[]> = {
  super_admin: [
    'view_reports', 'view_properties', 'create_properties', 'edit_properties', 'delete_properties',
    'view_sensitive_cnic', 'view_private_documents', 'upload_documents', 'verify_documents',
    'manage_sellers', 'manage_buyers', 'manage_dealers', 'submit_buyer',
    'view_transactions', 'view_my_deals', 'create_transactions', 'update_transactions',
    'cancel_transactions', 'void_transactions', 'approve_transactions',
    'manage_payments', 'view_commissions', 'view_my_commission', 'pay_commissions',
    'view_inquiries', 'manage_projects', 'manage_media', 'upload_media', 'edit_cms', 'manage_users',
  ],
  admin: [
    'view_reports', 'view_properties', 'create_properties', 'edit_properties', 'delete_properties',
    'view_sensitive_cnic', 'view_private_documents', 'upload_documents', 'verify_documents',
    'manage_sellers', 'manage_buyers', 'submit_buyer',
    'view_transactions', 'view_my_deals', 'create_transactions', 'update_transactions',
    'cancel_transactions', 'approve_transactions',
    'manage_payments', 'view_commissions', 'view_my_commission',
    'view_inquiries', 'manage_projects', 'manage_media', 'upload_media', 'edit_cms',
  ],
  manager: [
    'view_reports', 'view_properties', 'create_properties', 'edit_properties',
    'view_private_documents', 'upload_documents', 'verify_documents',
    'manage_sellers', 'manage_buyers', 'submit_buyer',
    'view_transactions', 'view_my_deals', 'create_transactions', 'update_transactions',
    'cancel_transactions',
    'view_inquiries', 'manage_projects', 'manage_media', 'upload_media',
  ],
  dealer: [
    'view_properties', 'submit_buyer', 'view_my_deals', 'view_my_commission',
  ],
  operator: [
    'view_reports', 'view_properties', 'create_properties', 'edit_properties',
    'upload_media', 'upload_documents',
  ],
  visitor: [],
};

/**
 * Some permissions have a SCOPED variant for dealers: the dealer cannot be
 * granted the global permission, but may exercise the same capability against
 * records that belong to them (verified via scope.dealerId).
 */
const SCOPED_EQUIVALENTS: Partial<Record<Permission, Permission>> = {
  view_transactions: 'view_my_deals',
  view_commissions: 'view_my_commission',
};

export function roleHasPermission(role: unknown, permission: Permission | Permission[]): boolean {
  const roleKey = normalizeRole(role);
  const perms = ROLE_PERMISSIONS[roleKey];
  const wanted = Array.isArray(permission) ? permission : [permission];
  return wanted.some((p) => perms.includes(p));
}

/** Subject shape accepted by authorize(). `dealerId` enables scoped checks. */
export interface AuthorizeSubject {
  role: unknown;
  permissions?: string[];
  id?: string;
  dealerId?: string;
  /** Owning agency. Required for agency-scoped checks on admin/manager roles. */
  agencyId?: string;
  /** True for the platform owner; bypasses agency scoping. */
  isSuperAdmin?: boolean;
}

export interface AuthorizeScope {
  /** When set and the acting user is a dealer, it must equal subject.dealerId. */
  dealerId?: string;
  /** When set and the acting user is an admin, it must equal subject.agencyId. */
  agencyId?: string;
  /** Override agency scoping for Super Admin. */
  overrideAgency?: boolean;
}

export type AuthzResult =
  | { ok: true; error?: never }
  | { ok: false; code: 'permission_denied' | 'scope_mismatch'; error: string };

/**
 * Authorize an action. Use from every mutating context action:
 *   const gate = authorize(currentUser, 'verify_documents');
 *   if (!gate.ok) { showToast('error', gate.error); return; }
 * Permission arrays act as any-of. Scope is enforced for dealer roles.
 */
export function authorize(
  subject: AuthorizeSubject | null | undefined,
  permission: Permission | Permission[],
  scope?: AuthorizeScope,
): AuthzResult {
  if (!subject) {
    return { ok: false, code: 'permission_denied', error: 'No active session. Access denied.' };
  }
  const roleKey = normalizeRole(subject.role);
  let roleGrant = roleHasPermission(roleKey, permission);

  // Dealer scoping: a scoped-equivalent permission + matching scope.dealerId
  // authorizes the same action restricted to the dealer's own records.
  if (!roleGrant && roleKey === 'dealer' && scope?.dealerId !== undefined) {
    const wanted = Array.isArray(permission) ? permission : [permission];
    roleGrant = wanted.some((p) => {
      const scoped = SCOPED_EQUIVALENTS[p];
      return scoped ? ROLE_PERMISSIONS.dealer.includes(scoped) : false;
    });
  }

  const explicit = subject.permissions || [];
  const explicitGrant = explicit.includes('all')
    ? true
    : (Array.isArray(permission) ? permission : [permission]).some((p) => explicit.includes(p));

  if (!roleGrant && !explicitGrant) {
    return {
      ok: false,
      code: 'permission_denied',
      error: `Permission denied: ${ROLE_LABELS[roleKey]} cannot ${Array.isArray(permission) ? permission.join('/') : permission}.`,
    };
  }

  if (scope && scope.dealerId !== undefined && roleKey === 'dealer') {
    if (!subject.dealerId || subject.dealerId !== scope.dealerId) {
      return { ok: false, code: 'scope_mismatch', error: 'This record belongs to another dealer.' };
    }
  }

  // Agency admin scoping: an admin action is restricted to records belonging
  // to the actor's own agency. Super Admins and explicit overrides bypass this.
  if (scope && scope.agencyId !== undefined) {
    const bypassAgencyScope =
      roleKey === 'super_admin' || subject.isSuperAdmin === true || scope.overrideAgency === true;
    if (!bypassAgencyScope && (!subject.agencyId || subject.agencyId !== scope.agencyId)) {
      return { ok: false, code: 'scope_mismatch', error: 'This record belongs to another agency.' };
    }
  }

  return { ok: true };
}

/** All permissions of a role (for the Security & Roles matrix display). */
export function permissionsOfRole(role: unknown): readonly Permission[] {
  return ROLE_PERMISSIONS[normalizeRole(role)];
}

/** Legacy UserRole spellings (display names) → canonical, for seed cleanup. */
export function toLegacyRoleLabel(role: Role): UserRole {
  switch (role) {
    case 'super_admin': return 'Super Admin' as UserRole;
    case 'admin': return 'Admin' as UserRole;
    case 'manager': return 'Manager' as UserRole;
    case 'dealer': return 'Property Dealer' as UserRole;
    case 'operator': return 'Data Entry Operator' as UserRole;
    case 'visitor': return 'visitor' as UserRole;
  }
}
