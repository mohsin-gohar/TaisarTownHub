import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  AlertCircle,
  Building2,
  CheckCircle2,
  ChevronRight,
  Clock3,
  Inbox,
  LifeBuoy,
  Loader2,
  MessageSquare,
  Plus,
  RefreshCw,
  Search,
  Send,
  ShieldCheck,
  User,
  X,
} from 'lucide-react';
import { useApp } from '../../context/AppContext';
import { api } from '../../services/api';

interface SupportTicketRecord {
  id: string;
  subject: string;
  category: string;
  priority: string;
  status: string;
  agency_id?: string | null;
  agency_name?: string | null;
  created_by?: string | null;
  assigned_to?: number | null;
  assigned_to_name?: string | null;
  first_response_at?: string | null;
  resolved_at?: string | null;
  closed_at?: string | null;
  created_at?: string | null;
}

interface SupportMessageRecord {
  id: number | string;
  body: string;
  is_staff: boolean;
  author_name?: string | null;
  created_at?: string | null;
}

interface SupportDetail {
  ticket: SupportTicketRecord;
  messages: SupportMessageRecord[];
}

interface StaffOption {
  id: number;
  name: string;
  email: string;
  role: string;
}

const STATUSES = ['open', 'pending', 'resolved', 'closed'] as const;
const CATEGORIES = ['general', 'billing', 'technical', 'document', 'finance'] as const;
const PRIORITIES = ['low', 'normal', 'high', 'urgent'] as const;

const statusStyle: Record<string, string> = {
  open: 'border-amber-500/30 bg-amber-500/10 text-amber-300',
  pending: 'border-sky-500/30 bg-sky-500/10 text-sky-300',
  resolved: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
  closed: 'border-slate-600 bg-slate-800 text-slate-400',
};

const priorityStyle: Record<string, string> = {
  low: 'text-slate-400',
  normal: 'text-slate-300',
  high: 'text-amber-300',
  urgent: 'text-rose-300',
};

const formatDate = (value?: string | null) => {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '—';
  return date.toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const label = (value: string) => value.charAt(0).toUpperCase() + value.slice(1);

export const AdminSupportDesk: React.FC<{ canManage: boolean }> = ({ canManage }) => {
  const { showToast } = useApp();

  const [lane, setLane] = useState<'mine' | 'all'>(canManage ? 'all' : 'mine');
  const [tickets, setTickets] = useState<SupportTicketRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState('all');
  const [categoryFilter, setCategoryFilter] = useState('all');
  const [search, setSearch] = useState('');
  const [appliedSearch, setAppliedSearch] = useState('');

  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [detail, setDetail] = useState<SupportDetail | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [detailError, setDetailError] = useState<string | null>(null);
  const [reply, setReply] = useState('');
  const [sendingReply, setSendingReply] = useState(false);
  const [statusBusy, setStatusBusy] = useState(false);

  const [staff, setStaff] = useState<StaffOption[]>([]);
  const [assignedTo, setAssignedTo] = useState('');
  const [assigning, setAssigning] = useState(false);

  const [createOpen, setCreateOpen] = useState(false);
  const [newSubject, setNewSubject] = useState('');
  const [newBody, setNewBody] = useState('');
  const [newCategory, setNewCategory] = useState('general');
  const [newPriority, setNewPriority] = useState('normal');
  const [creating, setCreating] = useState(false);
  const [createError, setCreateError] = useState<string | null>(null);

  useEffect(() => {
    const timer = window.setTimeout(() => setAppliedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  const loadTickets = useCallback(async () => {
    setLoading(true);
    setLoadError(null);
    const params = {
      status: statusFilter,
      category: categoryFilter,
      search: appliedSearch,
    };
    const items = lane === 'all' && canManage
      ? await api.getAdminSupportTickets(params)
      : await api.getSupportTickets(params);
    if (items === null) {
      setLoadError('Could not load support tickets. Check the backend connection and try again.');
      setTickets([]);
    } else {
      setTickets(items as SupportTicketRecord[]);
    }
    setLoading(false);
  }, [appliedSearch, canManage, categoryFilter, lane, statusFilter]);

  useEffect(() => {
    void loadTickets();
  }, [loadTickets]);

  useEffect(() => {
    if (!canManage) return;
    let active = true;
    void api.getSupportAssignableStaff().then((items) => {
      if (active && Array.isArray(items)) setStaff(items as StaffOption[]);
    });
    return () => {
      active = false;
    };
  }, [canManage]);

  useEffect(() => {
    if (tickets.length === 0) {
      setSelectedId(null);
      setDetail(null);
      return;
    }
    if (!selectedId || !tickets.some((ticket) => ticket.id === selectedId)) {
      setSelectedId(tickets[0].id);
    }
  }, [selectedId, tickets]);

  const loadDetail = useCallback(async (id: string) => {
    setDetailLoading(true);
    setDetailError(null);
    const result = await api.getSupportTicket(id);
    if (!result || !result.ticket) {
      setDetailError('Could not open this ticket. It may have been removed or is outside your access scope.');
      setDetail(null);
    } else {
      setDetail(result as SupportDetail);
      setAssignedTo(result.ticket.assigned_to ? String(result.ticket.assigned_to) : '');
    }
    setDetailLoading(false);
  }, []);

  useEffect(() => {
    if (selectedId) void loadDetail(selectedId);
  }, [loadDetail, selectedId]);

  const counts = useMemo(() => {
    return {
      open: tickets.filter((ticket) => ticket.status === 'open').length,
      pending: tickets.filter((ticket) => ticket.status === 'pending').length,
      resolved: tickets.filter((ticket) => ticket.status === 'resolved').length,
      closed: tickets.filter((ticket) => ticket.status === 'closed').length,
    };
  }, [tickets]);

  const submitReply = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!selectedId || reply.trim().length < 2 || sendingReply) return;
    setSendingReply(true);
    const result = await api.addSupportTicketMessage(selectedId, reply.trim());
    setSendingReply(false);
    if (!result.ok) {
      showToast('error', result.error || 'Could not send your reply.');
      return;
    }
    setReply('');
    showToast('success', 'Reply added to the ticket.');
    await loadDetail(selectedId);
    await loadTickets();
  };

  const changeStatus = async (status: string) => {
    if (!selectedId || statusBusy) return;
    setStatusBusy(true);
    const result = await api.updateSupportTicketStatus(selectedId, status);
    setStatusBusy(false);
    if (!result.ok) {
      showToast('error', result.error || 'Could not update the ticket status.');
      return;
    }
    showToast('success', `Ticket marked ${status}.`);
    await loadDetail(selectedId);
    await loadTickets();
  };

  const assignTicket = async () => {
    if (!selectedId || !assignedTo || assigning) return;
    setAssigning(true);
    const result = await api.assignSupportTicket(selectedId, Number(assignedTo));
    setAssigning(false);
    if (!result.ok) {
      showToast('error', result.error || 'Could not assign the ticket.');
      return;
    }
    showToast('success', 'Ticket assigned to the selected staff member.');
    await loadDetail(selectedId);
    await loadTickets();
  };

  const createTicket = async (event: React.FormEvent) => {
    event.preventDefault();
    if (creating) return;
    setCreateError(null);
    if (newSubject.trim().length < 5 || newBody.trim().length < 10) {
      setCreateError('Please enter a clear subject (5+ characters) and a description (10+ characters).');
      return;
    }
    setCreating(true);
    const result = await api.createSupportTicket({
      subject: newSubject.trim(),
      body: newBody.trim(),
      category: newCategory,
      priority: newPriority,
    });
    setCreating(false);
    if (!result.ok) {
      setCreateError(result.error || 'Could not create the ticket.');
      return;
    }
    setCreateOpen(false);
    setNewSubject('');
    setNewBody('');
    setNewCategory('general');
    setNewPriority('normal');
    showToast('success', 'Support ticket created. Our team will respond soon.');
    await loadTickets();
    if (result.ticket?.id) setSelectedId(result.ticket.id);
  };

  const activeTicket = detail?.ticket ?? tickets.find((ticket) => ticket.id === selectedId) ?? null;

  return (
    <div className="space-y-5">
      <header className="flex flex-col gap-3 rounded-2xl border border-slate-800 bg-[#101C2C] p-5 shadow-xl sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-start gap-3">
          <span className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-[#C5A46D]/30 bg-[#C5A46D]/10 text-[#C5A46D]">
            <LifeBuoy className="h-5 w-5" />
          </span>
          <div>
            <h1 className="font-serif-luxury text-2xl font-bold text-white">Support Center</h1>
            <p className="mt-0.5 text-xs text-slate-400">
              {canManage
                ? 'Respond to agency and platform tickets, assign owners and track resolution time.'
                : 'Raise a ticket with the platform team and follow the conversation in one place.'}
            </p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => void loadTickets()}
            className="inline-flex items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-[11px] font-semibold text-slate-200 transition hover:border-[#C5A46D]/50 hover:text-white"
          >
            <RefreshCw className={`h-3.5 w-3.5 ${loading ? 'animate-spin' : ''}`} />
            Refresh
          </button>
          <button
            type="button"
            onClick={() => {
              setCreateError(null);
              setCreateOpen(true);
            }}
            className="inline-flex items-center gap-1.5 rounded-xl bg-[#C5A46D] px-3.5 py-2 text-[11px] font-bold text-[#101C2C] transition hover:bg-[#D4AF37]"
          >
            <Plus className="h-3.5 w-3.5" />
            Raise a ticket
          </button>
        </div>
      </header>

      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        {[
          { key: 'open', label: 'Open', value: counts.open, icon: Inbox, tone: 'text-amber-300' },
          { key: 'pending', label: 'Pending', value: counts.pending, icon: Clock3, tone: 'text-sky-300' },
          { key: 'resolved', label: 'Resolved', value: counts.resolved, icon: CheckCircle2, tone: 'text-emerald-300' },
          { key: 'closed', label: 'Closed', value: counts.closed, icon: ShieldCheck, tone: 'text-slate-400' },
        ].map((stat) => (
          <div key={stat.key} className="rounded-xl border border-slate-800 bg-[#0E1724] p-3.5">
            <div className="flex items-center justify-between">
              <span className="text-[10px] font-bold uppercase tracking-wider text-slate-500">{stat.label}</span>
              <stat.icon className={`h-4 w-4 ${stat.tone}`} />
            </div>
            <p className="mt-1.5 font-serif-luxury text-2xl font-bold text-white">{stat.value}</p>
          </div>
        ))}
      </div>

      <div className="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(300px,0.9fr)_minmax(0,1.4fr)]">
        {/* Ticket list */}
        <section className="flex min-h-[520px] flex-col overflow-hidden rounded-2xl border border-slate-800 bg-[#101C2C] shadow-xl">
          <div className="space-y-2.5 border-b border-slate-800 p-3.5">
            {canManage && (
              <div className="flex rounded-lg border border-slate-800 bg-[#0B1220] p-0.5">
                {(['all', 'mine'] as const).map((value) => (
                  <button
                    key={value}
                    type="button"
                    onClick={() => setLane(value)}
                    className={`flex-1 rounded-md px-2 py-1.5 text-[10px] font-bold uppercase tracking-wider transition ${
                      lane === value ? 'bg-[#C5A46D] text-[#101C2C]' : 'text-slate-400 hover:text-white'
                    }`}
                  >
                    {value === 'all' ? 'All tickets' : 'My agency'}
                  </button>
                ))}
              </div>
            )}
            <div className="relative">
              <Search className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-500" />
              <input
                type="search"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Search ticket subject…"
                aria-label="Search support tickets"
                className="w-full rounded-lg border border-slate-700 bg-[#0B1220] py-2 pl-8 pr-2.5 text-[11px] text-white placeholder:text-slate-500 focus:border-[#C5A46D] focus:outline-none"
              />
            </div>
            <div className="grid grid-cols-2 gap-2">
              <select
                value={statusFilter}
                onChange={(event) => setStatusFilter(event.target.value)}
                aria-label="Filter by ticket status"
                className="rounded-lg border border-slate-700 bg-[#0B1220] px-2 py-1.5 text-[10px] text-white focus:border-[#C5A46D] focus:outline-none"
              >
                <option value="all">All statuses</option>
                {STATUSES.map((status) => (
                  <option key={status} value={status}>{label(status)}</option>
                ))}
              </select>
              <select
                value={categoryFilter}
                onChange={(event) => setCategoryFilter(event.target.value)}
                aria-label="Filter by ticket category"
                className="rounded-lg border border-slate-700 bg-[#0B1220] px-2 py-1.5 text-[10px] text-white focus:border-[#C5A46D] focus:outline-none"
              >
                <option value="all">All categories</option>
                {CATEGORIES.map((category) => (
                  <option key={category} value={category}>{label(category)}</option>
                ))}
              </select>
            </div>
          </div>

          <div className="flex-1 overflow-y-auto">
            {loading ? (
              <div className="flex items-center justify-center gap-2 p-8 text-xs text-slate-400">
                <Loader2 className="h-4 w-4 animate-spin text-[#C5A46D]" /> Loading tickets…
              </div>
            ) : loadError ? (
              <div className="m-3 rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-[11px] text-rose-300">
                <div className="flex items-start gap-2">
                  <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                  <div>
                    <p>{loadError}</p>
                    <button type="button" onClick={() => void loadTickets()} className="mt-2 font-bold underline">
                      Retry
                    </button>
                  </div>
                </div>
              </div>
            ) : tickets.length === 0 ? (
              <div className="flex flex-col items-center justify-center gap-2 p-10 text-center">
                <Inbox className="h-8 w-8 text-slate-600" />
                <p className="text-xs font-semibold text-slate-300">No tickets in this view</p>
                <p className="max-w-[240px] text-[11px] text-slate-500">
                  {statusFilter !== 'all' || categoryFilter !== 'all' || appliedSearch
                    ? 'Clear the filters to see the full support queue.'
                    : 'Raise a ticket and the platform team will pick it up.'}
                </p>
              </div>
            ) : (
              <ul className="divide-y divide-slate-800">
                {tickets.map((ticket) => {
                  const active = selectedId === ticket.id;
                  return (
                    <li key={ticket.id}>
                      <button
                        type="button"
                        onClick={() => setSelectedId(ticket.id)}
                        className={`w-full px-3.5 py-3 text-left transition ${
                          active ? 'bg-[#C5A46D]/10 border-l-2 border-[#C5A46D]' : 'border-l-2 border-transparent hover:bg-slate-800/40'
                        }`}
                      >
                        <div className="flex items-start justify-between gap-2">
                          <p className="line-clamp-2 text-[11px] font-bold text-white">{ticket.subject}</p>
                          <ChevronRight className={`mt-0.5 h-3.5 w-3.5 shrink-0 ${active ? 'text-[#C5A46D]' : 'text-slate-600'}`} />
                        </div>
                        <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                          <span className={`rounded-full border px-1.5 py-0.5 text-[9px] font-bold uppercase ${statusStyle[ticket.status] || statusStyle.closed}`}>
                            {ticket.status}
                          </span>
                          <span className={`text-[9px] font-semibold uppercase ${priorityStyle[ticket.priority] || priorityStyle.normal}`}>
                            {ticket.priority}
                          </span>
                          <span className="text-[9px] text-slate-500">· {ticket.category}</span>
                        </div>
                        {lane === 'all' && ticket.agency_name && (
                          <p className="mt-1 inline-flex items-center gap-1 text-[9px] text-slate-500">
                            <Building2 className="h-2.5 w-2.5" /> {ticket.agency_name}
                          </p>
                        )}
                        <p className="mt-1 text-[9px] text-slate-600">{formatDate(ticket.created_at)}</p>
                      </button>
                    </li>
                  );
                })}
              </ul>
            )}
          </div>
        </section>

        {/* Thread */}
        <section className="flex min-h-[520px] flex-col overflow-hidden rounded-2xl border border-slate-800 bg-[#101C2C] shadow-xl">
          {!selectedId ? (
            <div className="flex flex-1 flex-col items-center justify-center gap-2 p-10 text-center">
              <MessageSquare className="h-8 w-8 text-slate-600" />
              <p className="text-xs font-semibold text-slate-300">Select a ticket</p>
              <p className="max-w-[280px] text-[11px] text-slate-500">Choose a ticket from the queue to read the thread and reply.</p>
            </div>
          ) : detailLoading ? (
            <div className="flex flex-1 items-center justify-center gap-2 text-xs text-slate-400">
              <Loader2 className="h-4 w-4 animate-spin text-[#C5A46D]" /> Opening ticket…
            </div>
          ) : detailError ? (
            <div className="m-4 rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-[11px] text-rose-300">
              {detailError}
            </div>
          ) : activeTicket && detail ? (
            <>
              <header className="space-y-2.5 border-b border-slate-800 p-4">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="text-[9px] font-bold uppercase tracking-[0.18em] text-slate-500">
                      {detail.ticket.id} · {detail.ticket.category}
                    </p>
                    <h2 className="mt-1 font-serif-luxury text-lg font-bold text-white">{detail.ticket.subject}</h2>
                  </div>
                  <span className={`shrink-0 rounded-full border px-2 py-0.5 text-[9px] font-bold uppercase ${statusStyle[detail.ticket.status] || statusStyle.closed}`}>
                    {detail.ticket.status}
                  </span>
                </div>
                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-[10px] text-slate-400">
                  <span className="inline-flex items-center gap-1"><User className="h-3 w-3" /> {detail.ticket.created_by || 'Account'}</span>
                  <span className="inline-flex items-center gap-1"><Clock3 className="h-3 w-3" /> {formatDate(detail.ticket.created_at)}</span>
                  {detail.ticket.assigned_to_name && (
                    <span className="inline-flex items-center gap-1"><ShieldCheck className="h-3 w-3" /> {detail.ticket.assigned_to_name}</span>
                  )}
                </div>

                <div className="flex flex-wrap items-center gap-1.5 pt-1">
                  {STATUSES.map((status) => (
                    <button
                      key={status}
                      type="button"
                      disabled={statusBusy || detail.ticket.status === status}
                      onClick={() => void changeStatus(status)}
                      className={`rounded-lg border px-2.5 py-1 text-[10px] font-semibold transition disabled:cursor-not-allowed disabled:opacity-50 ${
                        detail.ticket.status === status
                          ? 'border-[#C5A46D] bg-[#C5A46D]/15 text-[#C5A46D]'
                          : 'border-slate-700 text-slate-400 hover:text-white'
                      }`}
                    >
                      Mark {status}
                    </button>
                  ))}
                </div>

                {canManage && staff.length > 0 && (
                  <div className="flex flex-wrap items-center gap-2 rounded-lg border border-slate-800 bg-[#0B1220] p-2">
                    <label htmlFor="support-assignee" className="text-[10px] font-semibold text-slate-400">Assign to</label>
                    <select
                      id="support-assignee"
                      value={assignedTo}
                      onChange={(event) => setAssignedTo(event.target.value)}
                      className="min-w-[150px] flex-1 rounded-md border border-slate-700 bg-[#0E1724] px-2 py-1.5 text-[10px] text-white focus:border-[#C5A46D] focus:outline-none"
                    >
                      <option value="">Unassigned</option>
                      {staff.map((member) => (
                        <option key={member.id} value={member.id}>{member.name} · {member.role}</option>
                      ))}
                    </select>
                    <button
                      type="button"
                      onClick={() => void assignTicket()}
                      disabled={!assignedTo || assigning}
                      className="inline-flex items-center gap-1 rounded-md bg-slate-800 px-2.5 py-1.5 text-[10px] font-bold text-slate-200 transition hover:bg-slate-700 disabled:opacity-50"
                    >
                      {assigning && <Loader2 className="h-3 w-3 animate-spin" />}
                      Assign
                    </button>
                  </div>
                )}
              </header>

              <div className="flex-1 space-y-3 overflow-y-auto p-4" aria-live="polite">
                {detail.messages.map((message) => (
                  <article
                    key={message.id}
                    className={`max-w-[88%] rounded-xl border p-3 ${
                      message.is_staff
                        ? 'ml-auto border-[#C5A46D]/30 bg-[#C5A46D]/10'
                        : 'border-slate-800 bg-[#0B1220]'
                    }`}
                  >
                    <div className="mb-1 flex items-center justify-between gap-4">
                      <span className="text-[10px] font-bold text-slate-200">
                        {message.author_name || (message.is_staff ? 'Support team' : 'Account')}
                        {message.is_staff && (
                          <span className="ml-1.5 rounded bg-[#C5A46D]/20 px-1 py-0.5 text-[8px] font-bold uppercase tracking-wider text-[#C5A46D]">
                            Staff
                          </span>
                        )}
                      </span>
                      <span className="text-[9px] text-slate-500">{formatDate(message.created_at)}</span>
                    </div>
                    <p className="whitespace-pre-wrap text-[11px] leading-relaxed text-slate-300">{message.body}</p>
                  </article>
                ))}
              </div>

              <form onSubmit={submitReply} className="border-t border-slate-800 p-3">
                <label htmlFor="support-reply" className="sr-only">Reply to ticket</label>
                <div className="flex items-end gap-2">
                  <textarea
                    id="support-reply"
                    value={reply}
                    onChange={(event) => setReply(event.target.value)}
                    rows={2}
                    maxLength={5000}
                    placeholder={
                      detail.ticket.status === 'closed'
                        ? 'This ticket is closed. Raise a new ticket to continue.'
                        : 'Write a reply to the support thread…'
                    }
                    disabled={detail.ticket.status === 'closed'}
                    className="min-h-[44px] flex-1 resize-y rounded-lg border border-slate-700 bg-[#0B1220] px-3 py-2 text-[11px] text-white placeholder:text-slate-500 focus:border-[#C5A46D] focus:outline-none disabled:opacity-50"
                  />
                  <button
                    type="submit"
                    disabled={sendingReply || reply.trim().length < 2 || detail.ticket.status === 'closed'}
                    className="inline-flex items-center gap-1.5 rounded-lg bg-[#C5A46D] px-3 py-2.5 text-[11px] font-bold text-[#101C2C] transition hover:bg-[#D4AF37] disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-400"
                  >
                    {sendingReply ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Send className="h-3.5 w-3.5" />}
                    Send
                  </button>
                </div>
              </form>
            </>
          ) : (
            <div className="flex flex-1 items-center justify-center p-8 text-xs text-slate-500">Select a ticket to view the thread.</div>
          )}
        </section>
      </div>

      {/* Create ticket dialog */}
      {createOpen && (
        <div
          className="fixed inset-0 z-[60] flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget) setCreateOpen(false);
          }}
        >
          <form
            onSubmit={createTicket}
            role="dialog"
            aria-modal="true"
            aria-labelledby="new-ticket-title"
            className="w-full max-w-lg overflow-hidden rounded-2xl border border-[#C5A46D]/35 bg-[#101C2C] shadow-2xl"
          >
            <header className="flex items-start justify-between gap-3 border-b border-slate-800 px-5 py-4">
              <div className="flex items-center gap-2.5">
                <span className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#C5A46D]/30 bg-[#C5A46D]/10 text-[#C5A46D]">
                  <LifeBuoy className="h-4 w-4" />
                </span>
                <div>
                  <h2 id="new-ticket-title" className="font-serif-luxury text-lg font-bold text-white">Raise a support ticket</h2>
                  <p className="text-[11px] text-slate-400">Our platform team responds during business hours.</p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setCreateOpen(false)}
                aria-label="Close ticket dialog"
                className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-800 hover:text-white"
              >
                <X className="h-4 w-4" />
              </button>
            </header>

            <div className="space-y-3 px-5 py-4">
              <label className="block space-y-1">
                <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Subject</span>
                <input
                  type="text"
                  value={newSubject}
                  onChange={(event) => setNewSubject(event.target.value)}
                  minLength={5}
                  maxLength={190}
                  required
                  placeholder="Summarise the issue"
                  className="w-full rounded-lg border border-slate-700 bg-[#0B1220] px-3 py-2 text-xs text-white placeholder:text-slate-500 focus:border-[#C5A46D] focus:outline-none"
                />
              </label>

              <div className="grid grid-cols-2 gap-3">
                <label className="block space-y-1">
                  <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Category</span>
                  <select
                    value={newCategory}
                    onChange={(event) => setNewCategory(event.target.value)}
                    className="w-full rounded-lg border border-slate-700 bg-[#0B1220] px-2.5 py-2 text-xs text-white focus:border-[#C5A46D] focus:outline-none"
                  >
                    {CATEGORIES.map((category) => (
                      <option key={category} value={category}>{label(category)}</option>
                    ))}
                  </select>
                </label>
                <label className="block space-y-1">
                  <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Priority</span>
                  <select
                    value={newPriority}
                    onChange={(event) => setNewPriority(event.target.value)}
                    className="w-full rounded-lg border border-slate-700 bg-[#0B1220] px-2.5 py-2 text-xs text-white focus:border-[#C5A46D] focus:outline-none"
                  >
                    {PRIORITIES.map((priority) => (
                      <option key={priority} value={priority}>{label(priority)}</option>
                    ))}
                  </select>
                </label>
              </div>

              <label className="block space-y-1">
                <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Describe the issue</span>
                <textarea
                  value={newBody}
                  onChange={(event) => setNewBody(event.target.value)}
                  minLength={10}
                  maxLength={5000}
                  rows={5}
                  required
                  placeholder="What happened, which screen or document was involved, and what did you expect?"
                  className="w-full resize-y rounded-lg border border-slate-700 bg-[#0B1220] px-3 py-2 text-xs text-white placeholder:text-slate-500 focus:border-[#C5A46D] focus:outline-none"
                />
              </label>

              {createError && (
                <p role="alert" className="rounded-lg border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-[11px] text-rose-300">
                  {createError}
                </p>
              )}
            </div>

            <footer className="flex items-center justify-end gap-2 border-t border-slate-800 bg-[#0E1724] px-5 py-3">
              <button
                type="button"
                onClick={() => setCreateOpen(false)}
                className="rounded-xl border border-slate-700 px-4 py-2 text-[11px] font-semibold text-slate-300 hover:bg-slate-800"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={creating}
                className="inline-flex items-center gap-1.5 rounded-xl bg-[#C5A46D] px-4 py-2 text-[11px] font-bold text-[#101C2C] transition hover:bg-[#D4AF37] disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-400"
              >
                {creating && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                {creating ? 'Creating…' : 'Create ticket'}
              </button>
            </footer>
          </form>
        </div>
      )}
    </div>
  );
};
