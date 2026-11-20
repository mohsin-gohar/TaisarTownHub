import React, { useCallback, useEffect, useState } from 'react';
import {
  Building2,
  Database,
  FileText,
  HardDrive,
  RefreshCw,
  Search,
  ShieldAlert,
  ShieldCheck,
  UserX,
  X,
} from 'lucide-react';
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
  EnterpriseStat,
  enterpriseInputClass,
  formatBytes,
  formatDate,
  titleCase,
} from './AdminEnterpriseUi';

interface OversightAgency {
  id: string;
  name: string;
  city?: string | null;
  email?: string | null;
  status: string;
  verification_status: string;
  plan?: string | null;
  subscription_status?: string | null;
  members_count: number;
  properties_count: number;
  documents_count: number;
  media_count: number;
  storage_used_bytes: number;
  storage_limit_mb: number | null;
  storage_limit_bytes: number | null;
  storage_percent: number;
  storage_remaining_bytes: number | null;
  over_quota: boolean;
}

interface Pagination {
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
}

const StorageMeter: React.FC<{ agency: OversightAgency }> = ({ agency }) => {
  const percent = Number(agency.storage_percent ?? 0);
  return (
    <div className="min-w-[150px]">
      <div className="flex items-center justify-between text-[10px] text-slate-400">
        <span>{formatBytes(agency.storage_used_bytes)}</span>
        <span>{agency.storage_limit_mb ? `${agency.storage_limit_mb} MB` : 'Unlimited'}</span>
      </div>
      <div className="mt-1 h-1.5 rounded-full bg-slate-800 overflow-hidden">
        <div
          className={`h-full rounded-full ${agency.over_quota ? 'bg-rose-500' : percent >= 90 ? 'bg-amber-400' : 'bg-[#E7C878]'}`}
          style={{ width: `${Math.max(2, Math.min(100, percent))}%` }}
        />
      </div>
      <div className="mt-1 text-[9px] text-slate-500">
        {agency.over_quota ? 'Over quota — uploads blocked' : `${percent}% used`}
      </div>
    </div>
  );
};

export const AdminAgencyOversight: React.FC = () => {
  const { confirmAction } = useApp();
  const [agencies, setAgencies] = useState<OversightAgency[]>([]);
  const [pagination, setPagination] = useState<Pagination | null>(null);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [summary, setSummary] = useState<any>({});
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState('');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const [detail, setDetail] = useState<any | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [quotaMb, setQuotaMb] = useState('');
  const [quotaReason, setQuotaReason] = useState('');
  const [decisionReason, setDecisionReason] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    const body = await api.getAdminAgencyOversight({
      page,
      search: search.trim() || undefined,
      status: status || undefined,
      per_page: 25,
    });
    if (!body || body.success === false) {
      setError(body?.message || 'Could not load agency storage oversight.');
      setAgencies([]);
      setPagination(null);
    } else {
      setAgencies(Array.isArray(body.agencies) ? body.agencies : []);
      setPagination(body.pagination ?? null);
      setSummary(body.summary ?? {});
    }
    setLoading(false);
  }, [page, search, status]);

  useEffect(() => {
    void load();
  }, [load]);

  const openDetail = async (agency: OversightAgency) => {
    setDetailLoading(true);
    setError('');
    setNotice('');
    const body = await api.getAdminAgencyOversightDetail(agency.id);
    setDetailLoading(false);
    if (!body || body.success === false) {
      setError(body?.message || 'Agency storage detail could not be loaded.');
      return;
    }
    setDetail(body);
    setQuotaMb(String(body.storage?.storage_limit_mb ?? ''));
    setQuotaReason('');
    setDecisionReason('');
  };

  const saveQuota = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!detail?.agency?.id) return;
    const value = Number(quotaMb);
    if (!Number.isFinite(value) || value < 1) {
      setError('Enter a storage quota of at least 1 MB.');
      return;
    }
    setBusy('quota');
    setError('');
    setNotice('');
    const result = await api.extendAgencyStorageQuota(String(detail.agency.id), {
      storage_mb: Math.round(value),
      reason: quotaReason || undefined,
    });
    setBusy('');
    if (!result.ok) {
      setError(result.body?.message || 'Storage quota could not be updated.');
      return;
    }
    setNotice('Storage quota updated. No existing file was deleted or downgraded.');
    setQuotaReason('');
    await openDetail(detail.agency);
    await load();
  };

  const changeStatus = async (nextStatus: 'suspended' | 'active') => {
    if (!detail?.agency?.id) return;
    const confirmed = await confirmAction(
      nextStatus === 'suspended'
        ? `Suspend "${detail.agency.name}"? Agency dashboard access is blocked until reactivation. Files and listings are preserved.`
        : `Reactivate "${detail.agency.name}"? This restores agency workspace access.`,
      {
        title: nextStatus === 'suspended' ? 'Suspend agency' : 'Reactivate agency',
        confirmLabel: nextStatus === 'suspended' ? 'Suspend agency' : 'Reactivate agency',
      },
    );
    if (!confirmed) return;
    setBusy('status');
    setError('');
    setNotice('');
    const ok = await api.setAdminAgencyStatus(String(detail.agency.id), nextStatus, decisionReason || undefined);
    setBusy('');
    if (!ok) {
      setError('Agency status could not be changed.');
      return;
    }
    setNotice(nextStatus === 'suspended' ? 'Agency suspended.' : 'Agency reactivated.');
    await openDetail(detail.agency);
    await load();
  };

  const totalPages = Number(pagination?.last_page ?? 1);

  return (
    <EnterprisePage
      title="Agency Oversight"
      subtitle="Storage usage, file metadata, subscription status and quota controls. File contents are never exposed here."
      actions={
        <EnterpriseButton variant="secondary" onClick={() => void load()}>
          <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} /> Refresh
        </EnterpriseButton>
      }
    >
      {error && <EnterpriseNote tone="error">{error}</EnterpriseNote>}
      {notice && <EnterpriseNote tone="success">{notice}</EnterpriseNote>}

      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        <EnterpriseStat label="Agencies" value={String(pagination?.total ?? 0)} hint="All workspaces" />
        <EnterpriseStat label="On this page" value={String(summary.agencies_on_page ?? agencies.length)} hint="Current page" />
        <EnterpriseStat
          label="Over quota"
          value={String(summary.over_quota_on_page ?? agencies.filter((agency) => agency.over_quota).length)}
          hint="Uploads blocked"
        />
        <EnterpriseStat label="Storage mode" value="Monitor only" hint="No quota enforcement" />
      </div>

      <EnterpriseCard title="Agency storage" subtitle="Server-side counters used by upload enforcement">
        <div className="grid sm:grid-cols-3 gap-2 mb-4">
          <div className="relative sm:col-span-2">
            <Search className="w-3.5 h-3.5 text-slate-500 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              className={`${enterpriseInputClass} pl-8`}
              placeholder="Search agency name or email…"
              value={search}
              onChange={(e) => { setSearch(e.target.value); setPage(1); }}
            />
          </div>
          <select
            className={enterpriseInputClass}
            value={status}
            onChange={(e) => { setStatus(e.target.value); setPage(1); }}
          >
            <option value="">All statuses</option>
            {['active', 'suspended', 'pending', 'rejected'].map((value) => (
              <option key={value} value={value}>{titleCase(value)}</option>
            ))}
          </select>
        </div>

        {loading ? (
          <EnterpriseSpinner label="Measuring agency storage…" />
        ) : agencies.length === 0 ? (
          <EnterpriseEmpty title="No agencies match" hint="Clear the filters or check another status." />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-[#07141E] text-slate-400 uppercase tracking-wider text-[10px]">
                <tr>
                  <th className="py-2.5 px-3 rounded-l-lg">Agency</th>
                  <th className="py-2.5 px-3">Plan / status</th>
                  <th className="py-2.5 px-3">Inventory</th>
                  <th className="py-2.5 px-3">Storage</th>
                  <th className="py-2.5 px-3 rounded-r-lg text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/80">
                {agencies.map((agency) => (
                  <tr key={agency.id} className="hover:bg-slate-800/30 align-middle">
                    <td className="py-3 px-3">
                      <div className="flex items-start gap-2">
                        <Building2 className="w-4 h-4 text-[#E7C878] shrink-0 mt-0.5" />
                        <div className="min-w-0">
                          <div className="font-semibold text-white truncate">{agency.name}</div>
                          <div className="text-[10px] text-slate-500 truncate">{agency.email || agency.city || '—'}</div>
                        </div>
                      </div>
                    </td>
                    <td className="py-3 px-3">
                      <div className="text-[11px] text-slate-300">{agency.plan || 'Starter Workspace'}</div>
                      <div className="mt-1 flex flex-wrap gap-1">
                        <EnterpriseBadge tone={agency.status === 'active' ? 'green' : 'red'}>{titleCase(agency.status)}</EnterpriseBadge>
                        <EnterpriseBadge tone={agency.verification_status === 'verified' ? 'gold' : 'slate'}>
                          {titleCase(agency.verification_status)}
                        </EnterpriseBadge>
                      </div>
                    </td>
                    <td className="py-3 px-3 text-[10px] text-slate-400">
                      <div>{agency.properties_count} listings</div>
                      <div>{agency.documents_count} documents · {agency.media_count} media</div>
                      <div>{agency.members_count} members</div>
                    </td>
                    <td className="py-3 px-3">
                      <StorageMeter agency={agency} />
                    </td>
                    <td className="py-3 px-3 text-right">
                      <EnterpriseButton variant="secondary" onClick={() => void openDetail(agency)}>
                        <HardDrive className="w-3.5 h-3.5" /> Review
                      </EnterpriseButton>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {totalPages > 1 && (
          <div className="flex items-center justify-between mt-4 text-[11px] text-slate-400">
            <span>Page {pagination?.current_page ?? page} of {totalPages}</span>
            <div className="flex gap-2">
              <EnterpriseButton variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => Math.max(1, p - 1))}>
                Previous
              </EnterpriseButton>
              <EnterpriseButton variant="secondary" disabled={page >= totalPages} onClick={() => setPage((p) => p + 1)}>
                Next
              </EnterpriseButton>
            </div>
          </div>
        )}
      </EnterpriseCard>

      {(detailLoading || detail) && (
        <EnterpriseCard
          title={detail ? `Review · ${detail.agency?.name || detail.agency?.id}` : 'Loading agency detail…'}
          subtitle="Quota extension is additive: existing files are never deleted or downgraded."
          actions={
            <EnterpriseButton variant="secondary" onClick={() => setDetail(null)}>
              <X className="w-3.5 h-3.5" /> Close
            </EnterpriseButton>
          }
        >
          {detailLoading ? (
            <EnterpriseSpinner label="Loading file metadata…" />
          ) : detail && (
            <div className="space-y-5">
              <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                <EnterpriseStat label="Used" value={formatBytes(detail.storage?.storage_used_bytes)} hint="Documents + versions + media" />
                <EnterpriseStat label="Quota" value={detail.storage?.storage_limit_mb ? `${detail.storage.storage_limit_mb} MB` : 'Unlimited'} hint="Editable below" />
                <EnterpriseStat label="Percent" value={`${detail.storage?.storage_percent ?? 0}%`} hint={detail.storage?.over_quota ? 'Over quota' : 'Within quota'} />
                <EnterpriseStat label="Files" value={String((detail.documents?.length ?? 0) + (detail.media?.length ?? 0))} hint="Metadata rows shown" />
              </div>

              {detail.storage?.over_quota && (
                <EnterpriseNote tone="error">
                  This agency is over its storage quota. Uploads are already being rejected by the API.
                  Existing files remain available; extend the quota below or ask the agency to remove files.
                </EnterpriseNote>
              )}

              <div className="grid md:grid-cols-2 gap-4">
                <div className="rounded-xl border border-slate-800 bg-[#07141E] p-3">
                  <h3 className="text-xs font-bold text-white flex items-center gap-2">
                    <Database className="w-4 h-4 text-[#E7C878]" /> Storage breakdown
                  </h3>
                  <ul className="mt-2 space-y-1 text-[11px] text-slate-400">
                    <li className="flex justify-between"><span>Documents</span><span>{formatBytes(detail.storage?.document_bytes)}</span></li>
                    <li className="flex justify-between"><span>Document versions ({detail.storage?.version_count ?? 0})</span><span>{formatBytes(detail.storage?.version_bytes)}</span></li>
                    <li className="flex justify-between"><span>Media library</span><span>{formatBytes(detail.storage?.media_bytes)}</span></li>
                    <li className="flex justify-between border-t border-slate-800 pt-1 mt-1 text-slate-200"><span>Total</span><span>{formatBytes(detail.storage?.storage_used_bytes)}</span></li>
                  </ul>
                </div>

                <div className="rounded-xl border border-slate-800 bg-[#07141E] p-3 space-y-2">
                  <h3 className="text-xs font-bold text-white flex items-center gap-2">
                    <HardDrive className="w-4 h-4 text-[#E7C878]" /> Storage monitoring only
                  </h3>
                  <p className="text-[10px] text-slate-500">
                    This release does not enforce a storage quota or trial. Upload limits and security validation remain in effect; usage is shown for monitoring and audit.
                  </p>
                </div>
              </div>

              <div className="rounded-xl border border-slate-800 bg-[#07141E] p-3">
                <h3 className="text-xs font-bold text-white flex items-center gap-2">
                  <ShieldAlert className="w-4 h-4 text-[#E7C878]" /> Agency controls
                </h3>
                <div className="mt-2 grid sm:grid-cols-3 gap-2">
                  <input
                    className={`${enterpriseInputClass} sm:col-span-2`}
                    placeholder="Reason for suspension / reactivation (audited)"
                    value={decisionReason}
                    onChange={(e) => setDecisionReason(e.target.value)}
                  />
                  <div className="flex gap-2">
                    <EnterpriseButton
                      variant="danger"
                      className="flex-1"
                      disabled={busy === 'status' || detail.agency?.status === 'suspended'}
                      onClick={() => void changeStatus('suspended')}
                    >
                      <UserX className="w-3.5 h-3.5" /> Suspend
                    </EnterpriseButton>
                    <EnterpriseButton
                      variant="secondary"
                      className="flex-1"
                      disabled={busy === 'status' || detail.agency?.status === 'active'}
                      onClick={() => void changeStatus('active')}
                    >
                      <ShieldCheck className="w-3.5 h-3.5" /> Reactivate
                    </EnterpriseButton>
                  </div>
                </div>
              </div>

              <div className="grid lg:grid-cols-2 gap-4">
                <div className="rounded-xl border border-slate-800 bg-[#07141E] p-3">
                  <h3 className="text-xs font-bold text-white flex items-center gap-2">
                    <FileText className="w-4 h-4 text-[#E7C878]" /> Vault metadata ({detail.documents?.length ?? 0})
                  </h3>
                  <div className="mt-2 max-h-64 overflow-y-auto space-y-1.5">
                    {(detail.documents ?? []).map((document: any) => (
                      <div key={document.id} className="flex items-start justify-between gap-2 text-[10px] border-b border-slate-800/60 pb-1.5">
                        <div className="min-w-0">
                          <div className="text-slate-200 font-semibold truncate">{document.title}</div>
                          <div className="text-slate-500">
                            {titleCase(document.category || 'other')} · {formatBytes(document.size_bytes)} · {formatDate(document.created_at)}
                            {document.deleted_at ? ' · trashed' : ''}
                          </div>
                        </div>
                        <div className="flex gap-1 shrink-0">
                          <EnterpriseBadge tone={document.status === 'active' ? 'green' : 'slate'}>{titleCase(document.status)}</EnterpriseBadge>
                          {document.scan_status && document.scan_status !== 'scanned_clean' && (
                            <EnterpriseBadge tone="gold">{titleCase(document.scan_status)}</EnterpriseBadge>
                          )}
                        </div>
                      </div>
                    ))}
                    {(detail.documents ?? []).length === 0 && <p className="text-[10px] text-slate-500">No vault documents.</p>}
                  </div>
                </div>

                <div className="rounded-xl border border-slate-800 bg-[#07141E] p-3">
                  <h3 className="text-xs font-bold text-white flex items-center gap-2">
                    <FileText className="w-4 h-4 text-[#E7C878]" /> Media metadata ({detail.media?.length ?? 0})
                  </h3>
                  <div className="mt-2 max-h-64 overflow-y-auto space-y-1.5">
                    {(detail.media ?? []).map((item: any) => (
                      <div key={item.id} className="flex items-start justify-between gap-2 text-[10px] border-b border-slate-800/60 pb-1.5">
                        <div className="min-w-0">
                          <div className="text-slate-200 font-semibold truncate">{item.title}</div>
                          <div className="text-slate-500">
                            {titleCase(item.type || 'file')} · {formatBytes(item.size_bytes)} · {titleCase(item.visibility || 'private')}
                            {item.deleted_at ? ' · trashed' : ''}
                          </div>
                        </div>
                        <EnterpriseBadge tone={item.status === 'available' ? 'green' : 'slate'}>{titleCase(item.status)}</EnterpriseBadge>
                      </div>
                    ))}
                    {(detail.media ?? []).length === 0 && <p className="text-[10px] text-slate-500">No media files.</p>}
                  </div>
                </div>
              </div>

              {(detail.quota_history ?? []).length > 0 && (
                <div className="rounded-xl border border-slate-800 bg-[#07141E] p-3">
                  <h3 className="text-xs font-bold text-white">Quota change history</h3>
                  <div className="mt-2 space-y-1">
                    {(detail.quota_history ?? []).map((entry: any) => (
                      <div key={entry.id} className="text-[10px] text-slate-400 flex flex-wrap gap-x-2">
                        <span className="text-slate-300">{entry.actor}</span>
                        <span>{entry.detail}</span>
                        <span className="text-slate-600">{formatDate(entry.created_at)}</span>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          )}
        </EnterpriseCard>
      )}
    </EnterprisePage>
  );
};
