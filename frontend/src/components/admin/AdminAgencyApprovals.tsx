import React, { useCallback, useEffect, useId, useRef, useState } from 'react';
import { RefreshCw, Search } from 'lucide-react';
import { useApp } from '../../context/AppContext';
import { api } from '../../services/api';
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

interface Agency {
  id: string | number;
  name: string;
  email?: string | null;
  phone?: string | null;
  whatsapp?: string | null;
  office_address?: string | null;
  city?: string | null;
  registration_number?: string | null;
  status?: string;
  verification_status?: string;
  verification_notes?: string | null;
  created_at?: string;
}

interface Pagination {
  current_page: number;
  last_page: number;
  total: number;
}

type Decision = 'approve' | 'reject' | 'suspend' | 'reactivate';
const decisionLabels: Record<Decision, string> = {
  approve: 'Approve', reject: 'Reject', suspend: 'Suspend', reactivate: 'Reactivate',
};
const decisionResults: Record<Decision, string> = {
  approve: 'approved', reject: 'rejected', suspend: 'suspended', reactivate: 'reactivated',
};
const focusClass = 'min-h-10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E7C878] focus-visible:ring-offset-2 focus-visible:ring-offset-[#0B1220]';

// Verification and account status are independent: suspension must not be
// mistaken for approval, and only verified agencies can be reactivated.
function availableDecisions(agency: Agency): Decision[] {
  if (agency.verification_status === 'verified') {
    if (agency.status === 'active') return ['suspend'];
    if (['suspended', 'rejected', 'pending'].includes(agency.status ?? '')) return ['reactivate'];
    return [];
  }
  if (['unverified', 'pending', 'rejected'].includes(agency.verification_status ?? '')) {
    return agency.verification_status === 'rejected' ? ['approve'] : ['approve', 'reject'];
  }
  return [];
}

export const AdminAgencyApprovals: React.FC = () => {
  const { confirmAction } = useApp();
  const fieldId = useId();
  const [query, setQuery] = useState({ page: 1, search: '', status: 'pending' });
  const [searchDraft, setSearchDraft] = useState('');
  const [agencies, setAgencies] = useState<Agency[]>([]);
  const [pagination, setPagination] = useState<Pagination | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState('');
  const [decisionError, setDecisionError] = useState('');
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState('');
  const [notes, setNotes] = useState<Record<string, string>>({});
  const requestId = useRef(0);
  const decisionInFlight = useRef(false);
  const mounted = useRef(true);

  useEffect(() => {
    mounted.current = true;
    return () => { mounted.current = false; requestId.current += 1; };
  }, []);

  const load = useCallback(async () => {
    const request = ++requestId.current;
    setLoading(true);
    setLoadError('');
    setAgencies([]);
    setPagination(null);
    try {
      const body = await api.getAdminAgencies(query);
      if (request !== requestId.current || !mounted.current) return;
      if (!body || !Array.isArray(body.agencies) || !body.pagination
        || ('success' in body && body.success === false)
        || !Number.isInteger(body.pagination.current_page) || body.pagination.current_page < 1
        || !Number.isInteger(body.pagination.last_page) || body.pagination.last_page < 1
        || !Number.isInteger(body.pagination.total) || body.pagination.total < 0) {
        throw new Error('Invalid agency response');
      }
      // A decision can remove the final row from the current filtered page.
      if (query.page > body.pagination.last_page) {
        setQuery((previous) => ({ ...previous, page: body.pagination.last_page }));
        return;
      }
      setAgencies(body.agencies);
      setPagination(body.pagination);
    } catch {
      if (request === requestId.current && mounted.current) {
        setLoadError('Could not load agencies. Check your connection and agency-management permission, then retry.');
      }
    } finally {
      if (request === requestId.current && mounted.current) setLoading(false);
    }
  }, [query]);

  useEffect(() => {
    void load();
    return () => { requestId.current += 1; };
  }, [load]);

  const handleDecision = async (agency: Agency, decision: Decision) => {
    if (decisionInFlight.current || loading || !availableDecisions(agency).includes(decision)) return;
    const id = String(agency.id);
    const reason = (notes[id] ?? '').trim();
    const requiresReason = decision === 'reject' || decision === 'suspend';
    setDecisionError('');
    setNotice('');
    if ((requiresReason && reason.length < 3) || reason.length > 1000) {
      setDecisionError('Enter decision notes of 3–1,000 characters to reject or suspend an agency.');
      return;
    }

    decisionInFlight.current = true;
    setBusy(`${decisionLabels[decision]} ${agency.name}`);
    const impact: Record<Decision, string> = {
      approve: 'Approval is required before this agency can access its dashboard and data.',
      reject: 'This agency will not be approved for dashboard or data access.',
      suspend: 'This blocks agency dashboard and data access until reactivated.',
      reactivate: 'This restores dashboard and data access for this verified agency.',
    };
    try {
      const confirmed = await confirmAction(
        `${decisionLabels[decision]} "${agency.name}"? ${impact[decision]}${reason ? `\n\nDecision notes: ${reason}` : ''}`,
        { title: `${decisionLabels[decision]} agency`, confirmLabel: `${decisionLabels[decision]} agency` },
      );
      if (!confirmed || !mounted.current) return;
      const acknowledged = decision === 'approve' || decision === 'reject'
        ? await api.verifyAdminAgency(id, decision === 'approve' ? 'verified' : 'rejected', reason || undefined)
        : await api.setAdminAgencyStatus(id, decision === 'suspend' ? 'suspended' : 'active', reason || undefined);
      if (!mounted.current) return;
      if (acknowledged !== true) throw new Error('Decision not acknowledged');
      setNotice(`"${agency.name}" ${decisionResults[decision]}.`);
      setNotes((previous) => ({ ...previous, [id]: '' }));
      await load();
    } catch {
      if (mounted.current) {
        setDecisionError(`Could not ${decision} "${agency.name}". No change was confirmed. Refresh to check its current status before retrying.`);
      }
    } finally {
      decisionInFlight.current = false;
      if (mounted.current) setBusy('');
    }
  };

  const disabled = loading || Boolean(busy);
  const updateQuery = (patch: Partial<typeof query>) => {
    if (disabled || decisionInFlight.current) return;
    setNotice('');
    setDecisionError('');
    setQuery((previous) => ({ ...previous, ...patch }));
  };

  return (
    <EnterprisePage
      title="Agency approvals"
      subtitle="Review agency registrations before granting dashboard and data access. Only verified, active agencies can enter their workspace."
      actions={
        <EnterpriseButton
          variant="secondary" className={focusClass} disabled={disabled}
          onClick={() => { setNotice(''); setDecisionError(''); void load(); }}
        >
          <RefreshCw className="w-3.5 h-3.5" aria-hidden="true" /> Refresh
        </EnterpriseButton>
      }
    >
      <EnterpriseCard>
        <form
          className="flex flex-col sm:flex-row sm:items-end gap-3"
          onSubmit={(event) => { event.preventDefault(); updateQuery({ page: 1, search: searchDraft.trim() }); }}
          aria-label="Filter agencies"
        >
          <div className="flex-1 min-w-0 space-y-1.5">
            <label htmlFor={`${fieldId}-search`} className="block text-xs text-slate-300">Search agencies</label>
            <input
              id={`${fieldId}-search`} type="search" className={`${enterpriseInputClass} ${focusClass}`}
              placeholder="Agency name or email" value={searchDraft} disabled={Boolean(busy)} maxLength={200}
              onChange={(event) => setSearchDraft(event.target.value)}
            />
          </div>
          <div className="sm:w-44 space-y-1.5">
            <label htmlFor={`${fieldId}-status`} className="block text-xs text-slate-300">Agency status</label>
            <select
              id={`${fieldId}-status`} className={`${enterpriseInputClass} ${focusClass}`}
              value={query.status} disabled={disabled}
              onChange={(event) => updateQuery({ page: 1, status: event.target.value })}
            >
              <option value="">All statuses</option>
              <option value="pending">Pending</option>
              <option value="rejected">Rejected</option>
              <option value="verified">Verified</option>
              <option value="suspended">Suspended</option>
            </select>
          </div>
          <EnterpriseButton type="submit" className={focusClass} disabled={disabled}>
            <Search className="w-3.5 h-3.5" aria-hidden="true" /> Search
          </EnterpriseButton>
        </form>
      </EnterpriseCard>

      {decisionError && <div role="alert"><EnterpriseNote tone="error">{decisionError}</EnterpriseNote></div>}
      <div role="status" aria-live="polite" aria-atomic="true">
        {notice && <EnterpriseNote tone="success">{notice}</EnterpriseNote>}
        {busy && <p className="text-xs text-slate-300 mt-2">Decision in progress: {busy}. Waiting for confirmation or server response.</p>}
      </div>

      <section aria-label="Agency approval results" aria-busy={disabled} className="space-y-4">
        {loading ? (
          <div role="status"><EnterpriseSpinner label="Loading agencies…" /></div>
        ) : loadError ? (
          <EnterpriseCard>
            <div role="alert"><EnterpriseNote tone="error">{loadError}</EnterpriseNote></div>
            <EnterpriseButton variant="secondary" className={`${focusClass} mt-3`} disabled={Boolean(busy)} onClick={() => void load()}>
              Retry loading agencies
            </EnterpriseButton>
          </EnterpriseCard>
        ) : agencies.length === 0 ? (
          <EnterpriseCard>
            <EnterpriseEmpty title="No agencies match these filters" hint="Try another status or search term, or refresh to check for new registrations." />
          </EnterpriseCard>
        ) : agencies.map((agency) => {
          const id = String(agency.id);
          const decisions = availableDecisions(agency);
          const reason = notes[id] ?? '';
          const verification = agency.verification_status === 'unverified' ? 'pending' : agency.verification_status;
          const details = [
            ['Email', agency.email], ['Phone', agency.phone], ['WhatsApp', agency.whatsapp],
            ['Office address', agency.office_address], ['City', agency.city], ['Registration number', agency.registration_number],
          ];
          return (
            <article key={id} aria-labelledby={`${fieldId}-${id}-name`}>
              <EnterpriseCard>
                <div className="flex flex-col lg:flex-row gap-5">
                  <div className="flex-1 min-w-0 space-y-4">
                    <div className="space-y-2">
                      <h3 id={`${fieldId}-${id}-name`} className="font-serif-luxury text-lg font-bold text-white break-words">{agency.name}</h3>
                      <div className="flex flex-wrap gap-2">
                        <EnterpriseBadge tone={verification === 'verified' ? 'green' : verification === 'rejected' ? 'red' : 'gold'}>
                          Verification: {titleCase(verification || 'unknown')}
                        </EnterpriseBadge>
                        <EnterpriseBadge tone={agency.status === 'active' && verification === 'verified' ? 'green' : agency.status === 'suspended' || agency.status === 'rejected' ? 'red' : 'slate'}>
                          Account: {titleCase(agency.status || 'unknown')}
                        </EnterpriseBadge>
                      </div>
                      <p className="text-[11px] text-slate-400">Registered {formatDate(agency.created_at)}</p>
                    </div>
                    <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-3 text-xs">
                      {details.map(([label, value]) => (
                        <div key={label} className="min-w-0">
                          <dt className="text-slate-400 mb-1">{label}</dt>
                          <dd className="text-slate-200 whitespace-pre-wrap break-words">{value || 'Not provided'}</dd>
                        </div>
                      ))}
                    </dl>
                    {agency.verification_notes && (
                      <div className="rounded-xl bg-[#07141E] border border-slate-800 p-3 text-xs">
                        <p className="text-slate-400 mb-1">Previous verification notes</p>
                        <p className="text-slate-200 whitespace-pre-wrap break-words">{agency.verification_notes}</p>
                      </div>
                    )}
                  </div>
                  <div className="lg:w-80 shrink-0 space-y-3">
                    {decisions.length > 0 ? (
                      <>
                        <label htmlFor={`${fieldId}-${id}-notes`} className="block text-xs text-slate-300">
                          Decision notes <span className="sr-only">for {agency.name}</span>
                        </label>
                        <textarea
                          id={`${fieldId}-${id}-notes`} rows={3} maxLength={1000} disabled={disabled}
                          className={`${enterpriseInputClass} ${focusClass} resize-y`}
                          value={reason} aria-describedby={`${fieldId}-${id}-hint`}
                          onChange={(event) => setNotes((previous) => ({ ...previous, [id]: event.target.value }))}
                        />
                        <p id={`${fieldId}-${id}-hint`} className="text-[11px] text-slate-400">
                          Required to reject or suspend (3–1,000 characters). Optional otherwise.
                        </p>
                        <div className="flex flex-wrap gap-2">
                          {decisions.map((decision) => (
                            <EnterpriseButton
                              key={decision} className={focusClass}
                              variant={decision === 'reject' || decision === 'suspend' ? 'danger' : 'primary'}
                              disabled={disabled || ((decision === 'reject' || decision === 'suspend') && reason.trim().length < 3)}
                              aria-label={`${decisionLabels[decision]} ${agency.name}`}
                              onClick={() => void handleDecision(agency, decision)}
                            >
                              {decisionLabels[decision]}
                            </EnterpriseButton>
                          ))}
                        </div>
                      </>
                    ) : (
                      <EnterpriseNote>Decision actions are unavailable for this verification or account status. Refresh to check the latest record.</EnterpriseNote>
                    )}
                  </div>
                </div>
              </EnterpriseCard>
            </article>
          );
        })}

        {!loading && !loadError && pagination && (
          <nav aria-label="Agency pagination" className="flex flex-wrap items-center justify-between gap-3 text-xs text-slate-400">
            <span aria-live="polite">Page {pagination.current_page} of {pagination.last_page} · {pagination.total} agencies</span>
            <div className="flex gap-2">
              <EnterpriseButton
                variant="secondary" className={focusClass} disabled={disabled || pagination.current_page <= 1}
                onClick={() => updateQuery({ page: pagination.current_page - 1 })}
              >Previous</EnterpriseButton>
              <EnterpriseButton
                variant="secondary" className={focusClass} disabled={disabled || pagination.current_page >= pagination.last_page}
                onClick={() => updateQuery({ page: pagination.current_page + 1 })}
              >Next</EnterpriseButton>
            </div>
          </nav>
        )}
      </section>
    </EnterprisePage>
  );
};
