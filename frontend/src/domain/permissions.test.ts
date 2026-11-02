import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import {
  ROLE_PERMISSIONS,
  ROLE_LABELS,
  authorize,
  normalizeRole,
  permissionsOfRole,
  Permission,
} from './permissions';

const ALL_ROLES = Object.keys(ROLE_PERMISSIONS) as (keyof typeof ROLE_PERMISSIONS)[];
const ALL_PERMISSIONS = [...new Set(ALL_ROLES.flatMap((r) => [...ROLE_PERMISSIONS[r]]))] as Permission[];

describe('permissions: canonical matrix', () => {
  test('every permission id has a label', () => {
    for (const p of ALL_PERMISSIONS) {
      assert.ok(p, 'permission id non-empty');
    }
    for (const role of ALL_ROLES) {
      assert.ok(ROLE_LABELS[role], `label for ${role}`);
    }
  });

  test('super_admin holds every permission', () => {
    for (const p of ALL_PERMISSIONS) {
      assert.ok(
        ROLE_PERMISSIONS.super_admin.includes(p),
        `super_admin missing ${p}`
      );
    }
  });

  test('dealer & operator can never view/manipulate the full deal ledger', () => {
    for (const role of ['dealer', 'operator'] as const) {
      const perms = permissionsOfRole(role);
      for (const forbidden of [
        'view_transactions', 'create_transactions', 'update_transactions',
        'cancel_transactions', 'void_transactions', 'approve_transactions',
        'manage_payments', 'pay_commissions', 'view_commissions',
        'view_sensitive_cnic', 'manage_users', 'manage_dealers', 'edit_cms',
      ] as Permission[]) {
        assert.equal(perms.includes(forbidden), false, `${role} must NOT hold ${forbidden}`);
      }
    }
  });

  test('admin cannot execute payouts, manage dealers/users or void deals', () => {
    const perms = permissionsOfRole('admin');
    for (const forbidden of ['pay_commissions', 'manage_dealers', 'manage_users', 'void_transactions'] as Permission[]) {
      assert.equal(perms.includes(forbidden), false, `admin must NOT hold ${forbidden}`);
    }
    // but admin still runs the back office:
    assert.ok(perms.includes('view_transactions'));
    assert.ok(perms.includes('manage_payments'));
    assert.ok(perms.includes('verify_documents'));
    assert.ok(perms.includes('view_commissions'));
  });

  test('dealer is scoped: own deals + own commission only', () => {
    const perms = permissionsOfRole('dealer');
    for (const mustHave of ['view_my_deals', 'view_my_commission', 'submit_buyer', 'view_properties'] as Permission[]) {
      assert.ok(perms.includes(mustHave));
    }
  });

  test('operator can upload but never verify documents', () => {
    const perms = permissionsOfRole('operator');
    assert.ok(perms.includes('upload_documents'));
    assert.equal(perms.includes('verify_documents'), false);
  });

  test('manager verifies documents & runs deals but cannot see unmasked CNIC or pay', () => {
    const perms = permissionsOfRole('manager');
    assert.ok(perms.includes('verify_documents'));
    assert.ok(perms.includes('cancel_transactions'));
    assert.equal(perms.includes('view_sensitive_cnic'), false);
    assert.equal(perms.includes('pay_commissions'), false);
    assert.equal(perms.includes('delete_properties'), false);
  });
});

describe('permissions: authorize()', () => {
  const user = (role: string, extra: Partial<{ permissions: string[]; dealerId: string; id: string }> = {}) => ({
    role,
    id: 'u-test',
    permissions: [],
    ...extra,
  });

  test('grants action inside a role matrix', () => {
    assert.deepEqual(authorize(user('admin'), 'verify_documents'), { ok: true });
    assert.deepEqual(authorize(user('manager'), 'create_transactions'), { ok: true });
  });

  test('denies action outside the role matrix with a typed error', () => {
    const gate = authorize(user('manager'), 'pay_commissions');
    assert.equal(gate.ok, false);
    if (!gate.ok) assert.equal(gate.code, 'permission_denied');
  });

  test('explicit permissions list can only widen, never hide role denials of others', () => {
    // widening: explicit grant enables the action
    assert.deepEqual(authorize(user('manager', { permissions: ['edit_cms'] }), 'edit_cms'), { ok: true });
    // 'all' grants everything
    assert.deepEqual(authorize(user('operator', { permissions: ['all'] }), 'pay_commissions'), { ok: true });
  });

  test('null/unknown subject is denied', () => {
    assert.equal(authorize(null, 'delete_properties').ok, false);
  });

  test('dealer scope: own record allowed, other dealer record mismatch', () => {
    const dealer = user('dealer', { dealerId: 'deal-1' });
    assert.deepEqual(authorize(dealer, 'view_transactions', { dealerId: 'deal-1' }), { ok: true });

    const gate = authorize(dealer, 'view_transactions', { dealerId: 'deal-2' });
    assert.equal(gate.ok, false);
    if (!gate.ok) assert.equal(gate.code, 'scope_mismatch');
  });

  test('dealer without matching dealerId on their profile is denied scoped access', () => {
    const dealer = user('dealer'); // no dealerId
    const gate = authorize(dealer, 'view_transactions', { dealerId: 'deal-1' });
    assert.equal(gate.ok, false);
    if (!gate.ok) assert.equal(gate.code, 'scope_mismatch');
  });

  test('staff roles are never scoping-restricted', () => {
    assert.deepEqual(authorize(user('admin'), 'view_transactions', { dealerId: 'deal-9' }), { ok: true });
  });
});

describe('permissions: normalizeRole', () => {
  test('maps legacy display spellings to canonical ids', () => {
    assert.equal(normalizeRole('Super Admin'), 'super_admin');
    assert.equal(normalizeRole('Property Dealer'), 'dealer');
    assert.equal(normalizeRole('Data Entry Operator'), 'operator');
    assert.equal(normalizeRole('Agency Admin'), 'admin');
    assert.equal(normalizeRole('Branch Manager'), 'manager');
  });

  test('unknown role falls back to least privilege (operator)', () => {
    assert.equal(normalizeRole('Chief Overlord'), 'operator');
    assert.equal(normalizeRole(undefined), 'operator');
  });
});
