import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Copy, Mail, Plus, RefreshCw, Shield, UserMinus, UserPlus, Users } from 'lucide-react';
import { api } from '../../services/api';
import { useApp } from '../../context/AppContext';
import {
  EnterpriseBadge,
  EnterpriseButton,
  EnterpriseCard,
  EnterpriseEmpty,
  EnterpriseNote,
  EnterprisePage,
  EnterpriseSpinner,
  enterpriseInputClass,
  formatDate,
  titleCase,
} from './AdminEnterpriseUi';

interface StaffManagerProps {
  canManage: boolean;
}

const COMMON_PERMISSIONS = [
  'properties.view', 'properties.create', 'properties.update', 'properties.delete',
  'crm.view', 'crm.create_lead', 'crm.update_lead', 'crm.assign_lead',
  'documents.view', 'documents.upload', 'documents.download',
  'finance.view', 'finance.create_transaction', 'finance.manage_payments',
  'agency.view', 'agency.manage_staff',
];

export const AdminStaffManager: React.FC<StaffManagerProps> = ({ canManage }) => {
  const { confirmAction } = useApp();
  const [members, setMembers] = useState<any[]>([]);
  const [invitations, setInvitations] = useState<any[]>([]);
  const [roles, setRoles] = useState<any[]>([]);
  const [assignable, setAssignable] = useState<string[]>([]);
  const [roleDrafts, setRoleDrafts] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState('');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const [inviteEmail, setInviteEmail] = useState('');
  const [inviteRoleId, setInviteRoleId] = useState('');
  const [inviteToken, setInviteToken] = useState('');

  const [newRoleName, setNewRoleName] = useState('');
  const [newRoleKey, setNewRoleKey] = useState('');
  const [newRolePermissions, setNewRolePermissions] = useState<string[]>(['properties.view']);
  const [showRolePermissions, setShowRolePermissions] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    const [membersBody, invitationsBody, rolesBody] = await Promise.all([
      api.getAgencyMembers(),
      api.getAgencyInvitations(),
      api.getAgencyRoles(),
    ]);

    if (!membersBody || membersBody.success === false) {
      setError(membersBody?.message || 'Could not load agency members. Does this account have an agency membership?');
      setMembers([]);
    } else {
      setMembers(Array.isArray(membersBody.members) ? membersBody.members : []);
      setRoleDrafts(Object.fromEntries(
        (membersBody.members ?? []).map((m: any) => [String(m.id), String(m.role_id ?? '')])
      ));
    }

    setInvitations(Array.isArray(invitationsBody?.invitations) ? invitationsBody.invitations : []);
    setRoles(Array.isArray(rolesBody?.roles) ? rolesBody.roles : []);
    setAssignable(Array.isArray(rolesBody?.assignable_permissions) ? rolesBody.assignable_permissions : COMMON_PERMISSIONS);
    if (!inviteRoleId && Array.isArray(rolesBody?.roles) && rolesBody.roles.length) {
      setInviteRoleId(String(rolesBody.roles[0].id));
    }
    setLoading(false);
  }, [inviteRoleId]);

  useEffect(() => {
    void load();
  }, [load]);

  const permissionGroups = useMemo(() => {
    const groups: Record<string, string[]> = {};
    for (const key of assignable) {
      const group = key.includes('.') ? key.split('.')[0] : 'other';
      groups[group] = groups[group] ?? [];
      groups[group].push(key);
    }
    return groups;
  }, [assignable]);

  const handleInvite = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!inviteEmail.trim() || !inviteRoleId) return;
    setBusy('invite');
    setError('');
    setNotice('');
    setInviteToken('');
    const result = await api.inviteAgencyMember({ email: inviteEmail.trim(), role_id: inviteRoleId });
    setBusy('');
    if (!result.ok) {
      setError(result.body?.message || 'Invitation could not be created.');
      return;
    }
    setInviteEmail('');
    setInviteToken(String(result.body?.invitation?.invite_token ?? ''));
    setNotice('Invitation created. Share the one-time token with the new team member.');
    await load();
  };

  const handleMemberRole = async (member: any) => {
    const roleId = roleDrafts[String(member.id)];
    if (!roleId || roleId === String(member.role_id)) return;
    setBusy(`member-${member.id}`);
    setError('');
    setNotice('');
    const result = await api.updateAgencyMember(String(member.id), { role_id: roleId });
    setBusy('');
    if (!result?.success) {
      setError('Member role could not be updated (owners must keep at least one active owner).');
      return;
    }
    setNotice('Member role updated.');
    await load();
  };

  const handleToggleMember = async (member: any) => {
    const nextStatus = member.status === 'active' ? 'deactivated' : 'active';
    if (nextStatus === 'deactivated') {
      const confirmed = await confirmAction(
        `Deactivate ${member.name || member.email}? They will lose agency access immediately.`,
        { title: 'Deactivate member', confirmLabel: 'Deactivate' },
      );
      if (!confirmed) return;
    }
    setBusy(`status-${member.id}`);
    setError('');
    setNotice('');
    const result = await api.updateAgencyMember(String(member.id), { status: nextStatus });
    setBusy('');
    if (!result?.success) {
      setError(result?.message || 'Member status could not be changed.');
      return;
    }
    setNotice(nextStatus === 'active' ? 'Member reactivated.' : 'Member deactivated.');
    await load();
  };

  const handleRevokeInvitation = async (invitation: any) => {
    const confirmed = await confirmAction(
      `Revoke the invitation for ${invitation.email}?`,
      { title: 'Revoke invitation', confirmLabel: 'Revoke invitation' },
    );
    if (!confirmed) return;
    setBusy(`invite-${invitation.id}`);
    setNotice('');
    const ok = await api.revokeAgencyInvitation(String(invitation.id));
    setBusy('');
    if (!ok) {
      setError('Invitation could not be revoked.');
      return;
    }
    setNotice('Invitation revoked.');
    await load();
  };

  const handleCreateRole = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!newRoleName.trim() || !newRoleKey.trim() || newRolePermissions.length === 0) return;
    setBusy('role');
    setError('');
    setNotice('');
    const result = await api.createAgencyRole({
      name: newRoleName.trim(),
      key: newRoleKey.trim().toLowerCase(),
      permissions: newRolePermissions,
    });
    setBusy('');
    if (!result.ok) {
      setError(result.body?.message || 'Role could not be created.');
      return;
    }
    setNewRoleName('');
    setNewRoleKey('');
    setNewRolePermissions(['properties.view']);
    setShowRolePermissions(false);
    setNotice('Custom role created.');
    await load();
  };

  const copyToken = async () => {
    try {
      await navigator.clipboard.writeText(inviteToken);
      setNotice('Invite token copied to clipboard.');
    } catch {
      setNotice('Copy is unavailable — select and copy the token manually.');
    }
  };

  return (
    <EnterprisePage
      title="Staff & Roles"
      subtitle="Invite team members, control roles and enforce least-privilege agency access."
      actions={
        <EnterpriseButton variant="secondary" onClick={() => void load()}>
          <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} /> Refresh
        </EnterpriseButton>
      }
    >
      {error && <EnterpriseNote tone="error">{error}</EnterpriseNote>}
      {notice && <EnterpriseNote tone="success">{notice}</EnterpriseNote>}
      {!canManage && (
        <EnterpriseNote tone="info">
          You have read-only access. `agency.manage_staff` is required to invite, change roles or deactivate members.
        </EnterpriseNote>
      )}

      {loading ? (
        <EnterpriseSpinner label="Loading staff directory…" />
      ) : (
        <div className="grid grid-cols-1 xl:grid-cols-12 gap-6">
          <div className="xl:col-span-8 space-y-6">
            <EnterpriseCard title="Team Members" subtitle={`${members.length} member${members.length === 1 ? '' : 's'}`}>
              {members.length === 0 ? (
                <EnterpriseEmpty title="No members yet" hint="Invite your first agent or manager using the form beside this card." />
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs">
                    <thead className="bg-[#07141E] text-slate-400 uppercase tracking-wider text-[10px]">
                      <tr>
                        <th className="py-2.5 px-3 rounded-l-lg">Member</th>
                        <th className="py-2.5 px-3">Role</th>
                        <th className="py-2.5 px-3">Status</th>
                        <th className="py-2.5 px-3">Joined</th>
                        <th className="py-2.5 px-3 rounded-r-lg text-right">Actions</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-800/80">
                      {members.map((member) => (
                        <tr key={member.id} className="hover:bg-slate-800/30 align-middle">
                          <td className="py-3 px-3">
                            <div className="font-semibold text-white">{member.name || '—'}</div>
                            <div className="text-[10px] text-slate-500">{member.email}</div>
                          </td>
                          <td className="py-3 px-3">
                            {canManage ? (
                              <div className="flex items-center gap-1.5">
                                <select
                                  value={roleDrafts[String(member.id)] ?? ''}
                                  onChange={(e) => setRoleDrafts((prev) => ({ ...prev, [String(member.id)]: e.target.value }))}
                                  className={`${enterpriseInputClass} min-w-[130px]`}
                                >
                                  {roles.map((role) => (
                                    <option key={role.id} value={role.id}>{role.name}</option>
                                  ))}
                                </select>
                                <EnterpriseButton
                                  variant="secondary"
                                  disabled={busy === `member-${member.id}` || roleDrafts[String(member.id)] === String(member.role_id)}
                                  onClick={() => void handleMemberRole(member)}
                                >
                                  Save
                                </EnterpriseButton>
                              </div>
                            ) : (
                              <span className="text-slate-300">{member.role_name || member.role_key || '—'}</span>
                            )}
                          </td>
                          <td className="py-3 px-3">
                            <EnterpriseBadge tone={member.status === 'active' ? 'green' : 'red'}>
                              {titleCase(member.status)}
                            </EnterpriseBadge>
                          </td>
                          <td className="py-3 px-3 text-slate-400">{formatDate(member.joined_at)}</td>
                          <td className="py-3 px-3 text-right">
                            {canManage && (
                              <EnterpriseButton
                                variant={member.status === 'active' ? 'danger' : 'secondary'}
                                disabled={busy === `status-${member.id}`}
                                onClick={() => void handleToggleMember(member)}
                              >
                                {member.status === 'active' ? (
                                  <><UserMinus className="w-3.5 h-3.5" /> Deactivate</>
                                ) : (
                                  <><UserPlus className="w-3.5 h-3.5" /> Reactivate</>
                                )}
                              </EnterpriseButton>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </EnterpriseCard>

            <EnterpriseCard title="Custom Roles" subtitle="Roles are agency-scoped and can never contain platform permissions">
              <div className="space-y-2 mb-4">
                {roles.length === 0 && <EnterpriseEmpty title="No roles available" />}
                {roles.map((role) => (
                  <div key={role.id} className="px-3 py-2.5 rounded-xl bg-[#07141E] border border-slate-800">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <div className="flex items-center gap-2">
                        <Shield className="w-4 h-4 text-[#E7C878]" />
                        <span className="text-xs font-semibold text-white">{role.name}</span>
                        <span className="text-[10px] text-slate-500">{role.key}</span>
                        {role.is_system && <EnterpriseBadge tone="gold">System</EnterpriseBadge>}
                      </div>
                      <span className="text-[10px] text-slate-400">{role.member_count ?? 0} member(s)</span>
                    </div>
                    <div className="flex flex-wrap gap-1 mt-2">
                      {(role.permissions ?? []).slice(0, 12).map((permission: string) => (
                        <span key={permission} className="px-1.5 py-0.5 rounded bg-slate-800 text-[9px] text-slate-300">
                          {permission}
                        </span>
                      ))}
                      {(role.permissions ?? []).length > 12 && (
                        <span className="text-[9px] text-slate-500">+{(role.permissions ?? []).length - 12} more</span>
                      )}
                    </div>
                  </div>
                ))}
              </div>

              {canManage && (
                <form onSubmit={handleCreateRole} className="border-t border-slate-800 pt-4 space-y-3">
                  <div className="grid sm:grid-cols-2 gap-3">
                    <div>
                      <label className="block text-[10px] text-slate-400 mb-1">Role name</label>
                      <input
                        className={enterpriseInputClass}
                        value={newRoleName}
                        onChange={(e) => setNewRoleName(e.target.value)}
                        placeholder="Senior Agent"
                        required
                      />
                    </div>
                    <div>
                      <label className="block text-[10px] text-slate-400 mb-1">Role key</label>
                      <input
                        className={enterpriseInputClass}
                        value={newRoleKey}
                        onChange={(e) => setNewRoleKey(e.target.value.replace(/[^a-z0-9_]/g, ''))}
                        placeholder="senior_agent"
                        required
                      />
                    </div>
                  </div>

                  <div>
                    <div className="flex items-center justify-between">
                      <span className="text-[10px] text-slate-400">
                        Permissions ({newRolePermissions.length} selected)
                      </span>
                      <button
                        type="button"
                        onClick={() => setShowRolePermissions((v) => !v)}
                        className="text-[10px] font-semibold text-[#E7C878] hover:underline cursor-pointer"
                      >
                        {showRolePermissions ? 'Hide catalog' : 'Choose permissions'}
                      </button>
                    </div>
                    {showRolePermissions && (
                      <div className="mt-2 max-h-56 overflow-y-auto rounded-xl border border-slate-800 bg-[#07141E] p-3 space-y-3">
                        {Object.entries(permissionGroups).map(([group, keys]) => (
                          <div key={group}>
                            <span className="block text-[9px] uppercase tracking-wider text-slate-500 mb-1">{group}</span>
                            <div className="grid sm:grid-cols-2 gap-1">
                              {keys.map((key) => (
                                <label key={key} className="flex items-center gap-1.5 text-[10px] text-slate-300 cursor-pointer">
                                  <input
                                    type="checkbox"
                                    checked={newRolePermissions.includes(key)}
                                    onChange={(e) => {
                                      setNewRolePermissions((prev) => e.target.checked
                                        ? [...prev, key]
                                        : prev.filter((k) => k !== key));
                                    }}
                                  />
                                  <span>{key}</span>
                                </label>
                              ))}
                            </div>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>

                  <EnterpriseButton type="submit" disabled={busy === 'role'}>
                    <Plus className="w-3.5 h-3.5" /> Create role
                  </EnterpriseButton>
                </form>
              )}
            </EnterpriseCard>
          </div>

          <div className="xl:col-span-4 space-y-6">
            <EnterpriseCard title="Invite a Member" subtitle="The API returns a one-time token to share">
              <form onSubmit={handleInvite} className="space-y-3">
                <div>
                  <label className="block text-[10px] text-slate-400 mb-1">Email</label>
                  <input
                    type="email"
                    className={enterpriseInputClass}
                    value={inviteEmail}
                    onChange={(e) => setInviteEmail(e.target.value)}
                    placeholder="agent@agency.com"
                    disabled={!canManage}
                    required
                  />
                </div>
                <div>
                  <label className="block text-[10px] text-slate-400 mb-1">Role</label>
                  <select
                    className={enterpriseInputClass}
                    value={inviteRoleId}
                    onChange={(e) => setInviteRoleId(e.target.value)}
                    disabled={!canManage}
                    required
                  >
                    <option value="">Select a role…</option>
                    {roles.map((role) => (
                      <option key={role.id} value={role.id}>{role.name}</option>
                    ))}
                  </select>
                </div>
                <EnterpriseButton type="submit" disabled={!canManage || busy === 'invite'} className="w-full">
                  <Mail className="w-3.5 h-3.5" /> Send invitation
                </EnterpriseButton>
              </form>

              {inviteToken && (
                <div className="mt-3 p-3 rounded-xl bg-[#07141E] border border-[#E7C878]/30 space-y-2">
                  <span className="block text-[10px] text-slate-400">One-time invite token</span>
                  <code className="block text-[10px] text-[#F6E3B4] break-all">{inviteToken}</code>
                  <EnterpriseButton variant="secondary" onClick={() => void copyToken()} className="w-full">
                    <Copy className="w-3.5 h-3.5" /> Copy token
                  </EnterpriseButton>
                </div>
              )}
            </EnterpriseCard>

            <EnterpriseCard title="Pending Invitations" subtitle={`${invitations.length} outstanding`}>
              {invitations.length === 0 ? (
                <EnterpriseEmpty title="No pending invitations" />
              ) : (
                <div className="space-y-2">
                  {invitations.map((invitation) => (
                    <div key={invitation.id} className="p-2.5 rounded-xl bg-[#07141E] border border-slate-800">
                      <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0">
                          <div className="text-xs font-semibold text-white truncate">{invitation.email}</div>
                          <div className="text-[10px] text-slate-500">
                            {invitation.role_name || invitation.role_id} · expires {formatDate(invitation.expires_at)}
                          </div>
                        </div>
                        <EnterpriseBadge tone={invitation.status === 'pending' ? 'gold' : 'slate'}>
                          {titleCase(invitation.status)}
                        </EnterpriseBadge>
                      </div>
                      {canManage && invitation.status === 'pending' && (
                        <EnterpriseButton
                          variant="danger"
                          className="mt-2 w-full"
                          disabled={busy === `invite-${invitation.id}`}
                          onClick={() => void handleRevokeInvitation(invitation)}
                        >
                          Revoke
                        </EnterpriseButton>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </EnterpriseCard>

            <EnterpriseCard title="Role hygiene" subtitle="Why this matters">
              <ul className="space-y-2 text-[11px] text-slate-400 list-disc pl-4">
                <li>Platform-only keys (`admin.*`, `properties.approve`, etc.) are rejected by the server for agency roles.</li>
                <li>An agency must always retain at least one active owner.</li>
                <li>Deactivation is soft — records and audit history are preserved.</li>
              </ul>
            </EnterpriseCard>
          </div>
        </div>
      )}
    </EnterprisePage>
  );
};

// The `Users` import is retained for the shared icon language in the header action row.
void Users;
