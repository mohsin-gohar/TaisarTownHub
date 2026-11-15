import type { AuthUser, WorkspaceAccess } from '../types';
import { normalizeRole } from './permissions';

export const anonymousUser: AuthUser = {
  id: 'anonymous',
  name: 'Visitor',
  email: '',
  role: 'visitor',
  permissions: [],
};

export function userFromServer(payload: Record<string, unknown>): AuthUser {
  const access = payload.workspace_access as Partial<WorkspaceAccess> | undefined;
  const permissions = [payload.permissions, payload.enterprise_permissions]
    .flatMap((items) => Array.isArray(items) ? items.filter((item): item is string => typeof item === 'string') : []);
  return {
    id: String(payload.id ?? ''),
    name: String(payload.name ?? ''),
    email: String(payload.email ?? ''),
    role: normalizeRole(payload.role),
    dealerId: payload.dealer_id ? String(payload.dealer_id) : undefined,
    permissions: [...new Set(permissions)],
    workspaceAccess: {
      allowed: access?.allowed === true,
      status: typeof access?.status === 'string' ? access.status : 'unavailable',
      message: typeof access?.message === 'string' ? access.message : 'We could not verify workspace access. Please check again.',
    },
  };
}

export function defaultAgencyId(payload: Record<string, unknown>): string | null {
  const memberships = Array.isArray(payload.agencies) ? payload.agencies : [];
  const id = memberships[0]?.agency_id;
  return typeof id === 'string' && id !== '' ? id : null;
}
