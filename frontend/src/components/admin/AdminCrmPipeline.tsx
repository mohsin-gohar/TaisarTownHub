import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  CalendarClock,
  Download,
  Mail,
  Phone,
  Plus,
  RefreshCw,
  UserRound,
  Users,
} from 'lucide-react';
import { api } from '../../services/api';
import {
  EnterpriseBadge,
  EnterpriseButton,
  EnterpriseCard,
  EnterpriseEmpty,
  EnterpriseNote,
  EnterprisePage,
  EnterpriseSpinner,
  EnterpriseStat,
  enterpriseInputClass,
  formatDate,
  titleCase,
} from './AdminEnterpriseUi';

interface CrmPipelineProps {
  canAssign: boolean;
  canEdit: boolean;
  canCreate: boolean;
}

const STATUSES = ['new', 'contacted', 'interested', 'negotiation', 'converted', 'lost'] as const;

const statusTone = (status: string): 'gold' | 'green' | 'red' | 'blue' | 'slate' => {
  if (status === 'converted') return 'green';
  if (status === 'lost') return 'red';
  if (status === 'negotiation') return 'gold';
  if (status === 'interested' || status === 'contacted') return 'blue';
  return 'slate';
};

export const AdminCrmPipeline: React.FC<CrmPipelineProps> = ({ canAssign, canEdit, canCreate }) => {
  const [leads, setLeads] = useState<any[]>([]);
  const [pipeline, setPipeline] = useState<Record<string, number>>({});
  const [members, setMembers] = useState<any[]>([]);
  const [pagination, setPagination] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState('');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const [statusFilter, setStatusFilter] = useState('');
  const [search, setSearch] = useState('');
  const [statusDrafts, setStatusDrafts] = useState<Record<string, string>>({});
  const [agentDrafts, setAgentDrafts] = useState<Record<string, string>>({});

  const [newLead, setNewLead] = useState({
    fullName: '', phone: '', email: '', budgetRange: '', message: '',
  });

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    const [leadsBody, membersBody] = await Promise.all([
      api.getAgencyLeads(),
      api.getAgencyMembers(),
    ]);

    if (!leadsBody || leadsBody.success === false) {
      setError(leadsBody?.message || 'Could not load the CRM pipeline. `crm.view` is required.');
      setLeads([]);
      setPipeline({});
      setPagination(null);
    } else {
      const rows = Array.isArray(leadsBody.leads) ? leadsBody.leads : [];
      setLeads(rows);
      setPipeline(leadsBody.pipeline ?? {});
      setPagination(leadsBody.pagination ?? null);
      setStatusDrafts(Object.fromEntries(rows.map((l: any) => [String(l.id), String(l.lead_status ?? 'new')])));
      setAgentDrafts(Object.fromEntries(rows.map((l: any) => [String(l.id), String(l.assigned_agent_id ?? '')])));
    }
    setMembers(Array.isArray(membersBody?.members) ? membersBody.members : []);
    setLoading(false);
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const visibleLeads = useMemo(() => {
    const query = search.trim().toLowerCase();
    return leads.filter((lead) => {
      if (statusFilter && lead.lead_status !== statusFilter) return false;
      if (!query) return true;
      return [lead.fullName, lead.phone, lead.email, lead.preferredPropertyOrProject, lead.message]
        .filter(Boolean)
        .some((value) => String(value).toLowerCase().includes(query));
    });
  }, [leads, statusFilter, search]);

  const activeMembers = members.filter((member) => member.status === 'active');

  const handleStatus = async (lead: any) => {
    const next = statusDrafts[String(lead.id)];
    if (!next || next === lead.lead_status) return;
    setBusy(`status-${lead.id}`);
    setError('');
    setNotice('');
    const result = await api.updateAgencyLead(String(lead.id), { lead_status: next });
    setBusy('');
    if (!result || result.success === false) {
      setError(result?.message || 'Lead status could not be updated.');
      return;
    }
    setNotice('Lead status updated and audited.');
    await load();
  };

  const handleAssign = async (lead: any) => {
    const next = agentDrafts[String(lead.id)] ?? '';
    if (next === String(lead.assigned_agent_id ?? '')) return;
    setBusy(`assign-${lead.id}`);
    setError('');
    setNotice('');
    const ok = await api.assignAgencyLead(String(lead.id), next);
    setBusy('');
    if (!ok) {
      setError('Lead could not be assigned. You may lack `crm.assign_lead` or the agent is outside this agency.');
      return;
    }
    setNotice(next ? 'Lead assigned to the selected agent.' : 'Lead unassigned.');
    await load();
  };

  const handleExport = async () => {
    setBusy('export');
    setError('');
    const csv = await api.exportAgencyLeads();
    setBusy('');
    if (!csv) {
      setError('CRM export failed.');
      return;
    }
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const anchor = window.document.createElement('a');
    anchor.href = url;
    anchor.download = `agency-leads-${new Date().toISOString().slice(0, 10)}.csv`;
    anchor.click();
    URL.revokeObjectURL(url);
    setNotice('Lead CSV exported.');
  };

  const handleCreate = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!newLead.fullName.trim() || !newLead.phone.trim()) {
      setError('A lead needs at least a name and phone number.');
      return;
    }
    setBusy('create');
    setError('');
    setNotice('');
    const result = await api.createAgencyLead({
      ...newLead,
      source: 'Manual entry',
      lead_status: 'new',
    });
    setBusy('');
    if (!result || result.success === false) {
      setError(result?.message || 'Lead could not be created.');
      return;
    }
    setNewLead({ fullName: '', phone: '', email: '', budgetRange: '', message: '' });
    setNotice('Lead created and added to the pipeline.');
    await load();
  };

  const totalLeads = Number(pagination?.total ?? leads.length);

  return (
    <EnterprisePage
      title="CRM Pipeline"
      subtitle="Every inquiry is attributed to this agency and moves through a validated, audited pipeline."
      actions={
        <>
          <EnterpriseButton variant="secondary" onClick={() => void handleExport()} disabled={busy === 'export'}>
            <Download className="w-3.5 h-3.5" /> Export CSV
          </EnterpriseButton>
          <EnterpriseButton variant="secondary" onClick={() => void load()}>
            <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} /> Refresh
          </EnterpriseButton>
        </>
      }
    >
      {error && <EnterpriseNote tone="error">{error}</EnterpriseNote>}
      {notice && <EnterpriseNote tone="success">{notice}</EnterpriseNote>}

      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        {STATUSES.map((status) => (
          <button
            key={status}
            onClick={() => setStatusFilter((current) => (current === status ? '' : status))}
            className={`text-left cursor-pointer transition-opacity ${statusFilter && statusFilter !== status ? 'opacity-50' : ''}`}
          >
            <EnterpriseStat
              label={titleCase(status)}
              value={pipeline[status] ?? 0}
              tone={status === 'converted' ? 'green' : status === 'lost' ? 'red' : status === 'new' ? 'gold' : 'white'}
            />
          </button>
        ))}
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-12 gap-6">
        <div className="xl:col-span-8 space-y-4">
          <div className="flex flex-col sm:flex-row gap-2">
            <input
              className={enterpriseInputClass}
              placeholder="Search name, phone, email, project or message…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
            <select className={enterpriseInputClass} value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}>
              <option value="">All statuses</option>
              {STATUSES.map((status) => (
                <option key={status} value={status}>{titleCase(status)}</option>
              ))}
            </select>
          </div>

          {loading ? (
            <EnterpriseSpinner label="Loading agency leads…" />
          ) : visibleLeads.length === 0 ? (
            <EnterpriseCard>
              <EnterpriseEmpty
                title="No leads match this view"
                hint="Public inquiries on this agency's listings land here automatically."
              />
            </EnterpriseCard>
          ) : (
            <div className="space-y-3">
              {visibleLeads.map((lead) => (
                <EnterpriseCard key={lead.id}>
                  <div className="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                    <div className="min-w-0 space-y-1.5">
                      <div className="flex flex-wrap items-center gap-2">
                        <h3 className="text-sm font-bold text-white">{lead.fullName || 'Unnamed lead'}</h3>
                        <EnterpriseBadge tone={statusTone(lead.lead_status)}>{titleCase(lead.lead_status)}</EnterpriseBadge>
                        <EnterpriseBadge tone="slate">{lead.source || 'Website'}</EnterpriseBadge>
                      </div>
                      <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-slate-400">
                        {lead.phone && (
                          <span className="inline-flex items-center gap-1"><Phone className="w-3 h-3" /> {lead.phone}</span>
                        )}
                        {lead.email && (
                          <span className="inline-flex items-center gap-1"><Mail className="w-3 h-3" /> {lead.email}</span>
                        )}
                        {lead.budgetRange && <span>Budget: {lead.budgetRange}</span>}
                      </div>
                      <p className="text-[11px] text-slate-500 line-clamp-2">{lead.message || 'No message supplied.'}</p>
                      <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-[10px] text-slate-500">
                        <span>Received {formatDate(lead.created_at)}</span>
                        {lead.preferredPropertyOrProject && <span>Interest: {lead.preferredPropertyOrProject}</span>}
                        {lead.follow_up_at && (
                          <span className="inline-flex items-center gap-1 text-amber-300">
                            <CalendarClock className="w-3 h-3" /> Follow-up {formatDate(lead.follow_up_at)}
                          </span>
                        )}
                      </div>
                    </div>

                    <div className="lg:w-72 shrink-0 space-y-2">
                      <div className="flex items-center gap-1.5 text-[10px] text-slate-400">
                        <UserRound className="w-3 h-3 text-[#E7C878]" />
                        <span>{lead.assigned_agent?.name || 'Unassigned'}</span>
                      </div>

                      {canEdit && (
                        <div className="flex gap-1.5">
                          <select
                            className={enterpriseInputClass}
                            value={statusDrafts[String(lead.id)] ?? lead.lead_status}
                            onChange={(e) => setStatusDrafts((prev) => ({ ...prev, [String(lead.id)]: e.target.value }))}
                          >
                            {STATUSES.map((status) => (
                              <option key={status} value={status}>{titleCase(status)}</option>
                            ))}
                          </select>
                          <EnterpriseButton
                            variant="secondary"
                            disabled={busy === `status-${lead.id}` || statusDrafts[String(lead.id)] === lead.lead_status}
                            onClick={() => void handleStatus(lead)}
                          >
                            Save
                          </EnterpriseButton>
                        </div>
                      )}

                      {canAssign && (
                        <div className="flex gap-1.5">
                          <select
                            className={enterpriseInputClass}
                            value={agentDrafts[String(lead.id)] ?? ''}
                            onChange={(e) => setAgentDrafts((prev) => ({ ...prev, [String(lead.id)]: e.target.value }))}
                          >
                            <option value="">Unassigned</option>
                            {activeMembers.map((member) => (
                              <option key={member.user_id} value={member.user_id}>{member.name || member.email}</option>
                            ))}
                          </select>
                          <EnterpriseButton
                            variant="secondary"
                            disabled={busy === `assign-${lead.id}` || (agentDrafts[String(lead.id)] ?? '') === String(lead.assigned_agent_id ?? '')}
                            onClick={() => void handleAssign(lead)}
                          >
                            Assign
                          </EnterpriseButton>
                        </div>
                      )}
                    </div>
                  </div>
                </EnterpriseCard>
              ))}
            </div>
          )}

          {pagination && pagination.last_page > 1 && (
            <p className="text-[11px] text-slate-500">
              Showing the first {leads.length} of {totalLeads} leads. Refine the search to narrow the view.
            </p>
          )}
        </div>

        <div className="xl:col-span-4 space-y-6">
          {canCreate && (
            <EnterpriseCard title="Add a Lead" subtitle="Manual entry enters the same audited pipeline">
              <form onSubmit={handleCreate} className="space-y-2.5">
                <input
                  className={enterpriseInputClass}
                  placeholder="Full name *"
                  value={newLead.fullName}
                  onChange={(e) => setNewLead((prev) => ({ ...prev, fullName: e.target.value }))}
                  required
                />
                <input
                  className={enterpriseInputClass}
                  placeholder="Phone *"
                  value={newLead.phone}
                  onChange={(e) => setNewLead((prev) => ({ ...prev, phone: e.target.value }))}
                  required
                />
                <input
                  className={enterpriseInputClass}
                  type="email"
                  placeholder="Email"
                  value={newLead.email}
                  onChange={(e) => setNewLead((prev) => ({ ...prev, email: e.target.value }))}
                />
                <input
                  className={enterpriseInputClass}
                  placeholder="Budget range (e.g. PKR 2–3 crore)"
                  value={newLead.budgetRange}
                  onChange={(e) => setNewLead((prev) => ({ ...prev, budgetRange: e.target.value }))}
                />
                <textarea
                  className={`${enterpriseInputClass} min-h-[70px]`}
                  placeholder="Notes / requirement"
                  value={newLead.message}
                  onChange={(e) => setNewLead((prev) => ({ ...prev, message: e.target.value }))}
                />
                <EnterpriseButton type="submit" disabled={busy === 'create'} className="w-full">
                  <Plus className="w-3.5 h-3.5" /> Create lead
                </EnterpriseButton>
              </form>
            </EnterpriseCard>
          )}

          <EnterpriseCard title="Team seats" subtitle="Agents available for assignment">
            {activeMembers.length === 0 ? (
              <EnterpriseEmpty title="No active members" hint="Invite staff first, then assign leads." />
            ) : (
              <div className="space-y-1.5">
                {activeMembers.map((member) => (
                  <div key={member.id} className="flex items-center justify-between gap-2 px-2.5 py-2 rounded-lg bg-[#07141E] border border-slate-800">
                    <div className="min-w-0">
                      <div className="text-[11px] font-semibold text-white truncate">{member.name || member.email}</div>
                      <div className="text-[9px] text-slate-500">{member.role_name || member.role_key || 'Member'}</div>
                    </div>
                    <Users className="w-3.5 h-3.5 text-[#E7C878] shrink-0" />
                  </div>
                ))}
              </div>
            )}
          </EnterpriseCard>

          <EnterpriseCard title="Pipeline rules">
            <ul className="space-y-2 text-[11px] text-slate-400 list-disc pl-4">
              <li>Agents without `crm.assign_lead` only see their own and unassigned leads.</li>
              <li>Status changes are validated against the server-side state list and audited.</li>
              <li>Public inquiries attach the listing's agency automatically — never the client's claim.</li>
            </ul>
          </EnterpriseCard>
        </div>
      </div>
    </EnterprisePage>
  );
};
