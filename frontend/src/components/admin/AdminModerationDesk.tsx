import React, { useCallback, useEffect, useState } from 'react';
import { BadgeCheck, Check, Clock3, RefreshCw, ShieldAlert, X } from 'lucide-react';
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
  formatPKR,
  titleCase,
} from './AdminEnterpriseUi';

interface ModerationDeskProps {
  canModerate: boolean;
}

const summarizeChanges = (changes: Record<string, unknown> | null | undefined): string[] => {
  if (!changes || typeof changes !== 'object') return [];
  return Object.entries(changes)
    .filter(([, value]) => value !== undefined)
    .map(([key, value]) => {
      const shown = value === null || value === ''
        ? '—'
        : typeof value === 'object'
          ? JSON.stringify(value).slice(0, 120)
          : String(value).slice(0, 160);
      return `${titleCase(key)}: ${shown}`;
    });
};

export const AdminModerationDesk: React.FC<ModerationDeskProps> = ({ canModerate }) => {
  const [queue, setQueue] = useState<any[]>([]);
  const [pagination, setPagination] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState('');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [reasons, setReasons] = useState<Record<string, string>>({});

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    const body = await api.getModerationQueue();
    if (!body || body.success === false) {
      setError(body?.message || 'Could not open the moderation queue. `properties.approve` is required.');
      setQueue([]);
      setPagination(null);
    } else {
      setQueue(Array.isArray(body.queue) ? body.queue : []);
      setPagination(body.pagination ?? null);
    }
    setLoading(false);
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const handleApprove = async (property: any) => {
    setBusy(`approve-${property.id}`);
    setError('');
    setNotice('');
    const result = await api.approveProperty(String(property.id));
    setBusy('');
    if (!result || result.success === false) {
      setError(result?.message || 'Listing could not be approved.');
      return;
    }
    setNotice(`"${property.title}" approved and published.`);
    await load();
  };

  const handleReject = async (property: any) => {
    const reason = (reasons[String(property.id)] || '').trim();
    if (reason.length < 3) {
      setError('Enter a rejection reason of at least 3 characters so the agency can correct the listing.');
      return;
    }
    setBusy(`reject-${property.id}`);
    setError('');
    setNotice('');
    const result = await api.rejectProperty(String(property.id), reason);
    setBusy('');
    if (!result || result.success === false) {
      setError(result?.message || 'Listing could not be rejected.');
      return;
    }
    setReasons((prev) => ({ ...prev, [String(property.id)]: '' }));
    setNotice(`"${property.title}" rejected. The agency can revise and resubmit.`);
    await load();
  };

  return (
    <EnterprisePage
      title="Moderation Desk"
      subtitle="Every new listing and every material edit to a live listing passes through this queue."
      actions={
        <EnterpriseButton variant="secondary" onClick={() => void load()}>
          <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} /> Refresh
        </EnterpriseButton>
      }
    >
      {error && <EnterpriseNote tone="error">{error}</EnterpriseNote>}
      {notice && <EnterpriseNote tone="success">{notice}</EnterpriseNote>}
      {!canModerate && (
        <EnterpriseNote tone="info">
          `properties.approve` is required to approve or reject listings. You can still review the queue.
        </EnterpriseNote>
      )}

      {loading ? (
        <EnterpriseSpinner label="Loading pending listings…" />
      ) : queue.length === 0 ? (
        <EnterpriseCard title="Queue is clear" subtitle="Nothing is awaiting moderation right now">
          <EnterpriseEmpty
            title="No pending listings"
            hint="New submissions and staged edits will appear here automatically."
          />
        </EnterpriseCard>
      ) : (
        <div className="space-y-4">
          {queue.map((property) => {
            const changes = summarizeChanges(property.pending_changes);
            const isReapproval = Boolean(property.pending_changes);
            return (
              <EnterpriseCard key={property.id}>
                <div className="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                  <div className="min-w-0 space-y-2">
                    <div className="flex flex-wrap items-center gap-2">
                      <h3 className="font-serif-luxury text-lg font-bold text-white">{property.title}</h3>
                      <EnterpriseBadge tone="gold">
                        {isReapproval ? 'Edit re-approval' : 'New submission'}
                      </EnterpriseBadge>
                      <EnterpriseBadge tone={property.visibility === 'public' ? 'green' : 'slate'}>
                        {titleCase(property.visibility || 'private')}
                      </EnterpriseBadge>
                    </div>
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-slate-400">
                      <span>{property.agency_name || property.agency?.name || 'Unknown agency'}</span>
                      <span>·</span>
                      <span>{property.phase || 'Taisar Town'}</span>
                      <span>·</span>
                      <span>{property.propertyType || property.type || 'Property'}</span>
                      <span>·</span>
                      <span className="text-[#C5A46D] font-semibold">{formatPKR(property.price)}</span>
                    </div>
                    <div className="flex flex-wrap items-center gap-3 text-[10px] text-slate-500">
                      <span className="inline-flex items-center gap-1">
                        <Clock3 className="w-3 h-3" /> Submitted {formatDate(property.submitted_at || property.updated_at)}
                      </span>
                      {property.creator_name && <span>By {property.creator_name}</span>}
                      {property.plotNumber && <span>Plot {property.plotNumber}</span>}
                    </div>

                    {changes.length > 0 && (
                      <div className="rounded-xl bg-[#0B1220] border border-[#C5A46D]/25 p-3">
                        <div className="flex items-center gap-1.5 text-[10px] uppercase tracking-wider text-[#C5A46D] font-bold mb-1.5">
                          <ShieldAlert className="w-3 h-3" /> Staged changes awaiting re-approval
                        </div>
                        <ul className="space-y-1 text-[11px] text-slate-300 list-disc pl-4">
                          {changes.map((line) => (
                            <li key={line}>{line}</li>
                          ))}
                        </ul>
                        <p className="text-[10px] text-slate-500 mt-1.5">
                          The current live copy stays published until these changes are approved.
                        </p>
                      </div>
                    )}
                  </div>

                  <div className="lg:w-80 shrink-0 space-y-2">
                    <div className="rounded-xl bg-[#0B1220] border border-slate-800 p-3 text-[11px] text-slate-300 space-y-1">
                      <div className="flex justify-between"><span className="text-slate-500">Area</span><span>{property.area ? `${property.area} ${property.areaUnit || 'sq.ft'}` : '—'}</span></div>
                      <div className="flex justify-between"><span className="text-slate-500">Category</span><span>{titleCase(property.category || property.propertyType || '—')}</span></div>
                      <div className="flex justify-between"><span className="text-slate-500">Approval</span><span>{titleCase(property.approval_status || 'pending')}</span></div>
                    </div>

                    {canModerate && (
                      <>
                        <EnterpriseButton
                          className="w-full"
                          disabled={busy === `approve-${property.id}`}
                          onClick={() => void handleApprove(property)}
                        >
                          <Check className="w-3.5 h-3.5" /> Approve & publish
                        </EnterpriseButton>
                        <input
                          className={enterpriseInputClass}
                          placeholder="Rejection reason (required)"
                          value={reasons[String(property.id)] ?? ''}
                          onChange={(e) => setReasons((prev) => ({ ...prev, [String(property.id)]: e.target.value }))}
                        />
                        <EnterpriseButton
                          variant="danger"
                          className="w-full"
                          disabled={busy === `reject-${property.id}`}
                          onClick={() => void handleReject(property)}
                        >
                          <X className="w-3.5 h-3.5" /> Reject submission
                        </EnterpriseButton>
                      </>
                    )}
                  </div>
                </div>
              </EnterpriseCard>
            );
          })}

          {pagination && pagination.last_page > 1 && (
            <div className="flex items-center justify-between text-[11px] text-slate-400">
              <span>Showing {queue.length} of {pagination.total} pending listing(s)</span>
              <EnterpriseBadge tone="slate">
                <BadgeCheck className="w-3 h-3 mr-1" /> Page {pagination.current_page} of {pagination.last_page}
              </EnterpriseBadge>
            </div>
          )}
        </div>
      )}
    </EnterprisePage>
  );
};
