import React from 'react';
import { Bell, BellOff, Bookmark, BookmarkCheck, Clock3, Loader2, Trash2, Wand2 } from 'lucide-react';

export interface SavedSearchRecord {
  id: string;
  name: string;
  filters: Record<string, unknown>;
  frequency: 'instant' | 'daily' | 'weekly' | string;
  alerts_enabled: boolean;
  summary?: string;
  last_run_at?: string | null;
  last_notified_at?: string | null;
  created_at?: string | null;
}

interface SavedSearchPanelProps {
  searches: SavedSearchRecord[];
  loading: boolean;
  authenticated: boolean;
  onSaveCurrent: () => void;
  onApply: (search: SavedSearchRecord) => void;
  onToggleAlerts: (search: SavedSearchRecord) => void;
  onDelete: (search: SavedSearchRecord) => void;
  busyId?: string | null;
}

const frequencyLabel = (frequency: string) =>
  frequency === 'instant' ? 'Instant alerts' : frequency === 'weekly' ? 'Weekly digest' : 'Daily digest';

export const SavedSearchPanel: React.FC<SavedSearchPanelProps> = ({
  searches,
  loading,
  authenticated,
  onSaveCurrent,
  onApply,
  onToggleAlerts,
  onDelete,
  busyId,
}) => {
  return (
    <section
      aria-labelledby="saved-searches-heading"
      className="rounded-2xl border border-slate-800 bg-[#101C2C] p-5 shadow-xl space-y-4"
    >
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-2.5">
          <span className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#C5A46D]/30 bg-[#C5A46D]/10 text-[#C5A46D]">
            <BookmarkCheck className="h-4 w-4" />
          </span>
          <div>
            <h2 id="saved-searches-heading" className="text-sm font-bold text-white">
              Saved searches &amp; email alerts
            </h2>
            <p className="text-[11px] text-slate-400">
              Keep your criteria on file and get notified when new matching listings are published.
            </p>
          </div>
        </div>
        <button
          type="button"
          onClick={onSaveCurrent}
          className="inline-flex items-center gap-1.5 rounded-xl border border-[#C5A46D]/40 bg-[#C5A46D]/10 px-3 py-2 text-[11px] font-bold text-[#C5A46D] transition hover:bg-[#C5A46D] hover:text-[#101C2C]"
        >
          <Wand2 className="h-3.5 w-3.5" />
          Save current search
        </button>
      </div>

      {!authenticated ? (
        <div className="rounded-xl border border-slate-800 bg-[#0B1220] p-4 text-xs text-slate-400">
          <div className="flex items-start gap-2.5">
            <Bell className="mt-0.5 h-4 w-4 shrink-0 text-[#C5A46D]" />
            <p>
              Sign in to save this search and receive email alerts. Your filters stay in the URL, so you can come back to
              this exact result set at any time.
            </p>
          </div>
        </div>
      ) : loading ? (
        <div className="flex items-center gap-2 rounded-xl border border-slate-800 bg-[#0B1220] p-4 text-xs text-slate-400">
          <Loader2 className="h-4 w-4 animate-spin text-[#C5A46D]" />
          Loading saved searches…
        </div>
      ) : searches.length === 0 ? (
        <div className="rounded-xl border border-dashed border-slate-700 bg-[#0B1220] p-4 text-xs text-slate-400">
          <div className="flex items-start gap-2.5">
            <Bookmark className="mt-0.5 h-4 w-4 shrink-0 text-[#C5A46D]" />
            <p>
              No saved searches yet. Apply the filters above, then choose <strong className="text-slate-200">Save current search</strong> to
              start receiving matching alerts.
            </p>
          </div>
        </div>
      ) : (
        <ul className="grid grid-cols-1 gap-3 lg:grid-cols-2 xl:grid-cols-3">
          {searches.map((search) => {
            const busy = busyId === search.id;
            return (
              <li
                key={search.id}
                className="flex flex-col justify-between gap-3 rounded-xl border border-slate-800 bg-[#0B1220] p-3.5"
              >
                <div className="space-y-1.5">
                  <div className="flex items-start justify-between gap-2">
                    <h3 className="text-xs font-bold text-white line-clamp-2">{search.name}</h3>
                    <span
                      className={`shrink-0 rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider ${
                        search.alerts_enabled
                          ? 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/30'
                          : 'bg-slate-800 text-slate-400 border border-slate-700'
                      }`}
                    >
                      {search.alerts_enabled ? 'Alerts on' : 'Paused'}
                    </span>
                  </div>
                  <p className="line-clamp-2 text-[11px] leading-relaxed text-slate-400">{search.summary || 'All published listings'}</p>
                  <p className="inline-flex items-center gap-1 text-[10px] text-slate-500">
                    <Clock3 className="h-3 w-3" />
                    {frequencyLabel(search.frequency)}
                    {search.last_notified_at ? ' · last alert sent' : ' · awaiting first match'}
                  </p>
                </div>

                <div className="flex items-center justify-between gap-2 border-t border-slate-800 pt-2.5">
                  <button
                    type="button"
                    role="switch"
                    aria-checked={search.alerts_enabled}
                    aria-label={`${search.alerts_enabled ? 'Pause' : 'Resume'} email alerts for ${search.name}`}
                    disabled={busy}
                    onClick={() => onToggleAlerts(search)}
                    className="inline-flex items-center gap-1.5 text-[10px] font-semibold text-slate-400 transition hover:text-white disabled:opacity-50"
                  >
                    {busy ? (
                      <Loader2 className="h-3.5 w-3.5 animate-spin" />
                    ) : search.alerts_enabled ? (
                      <Bell className="h-3.5 w-3.5 text-[#C5A46D]" />
                    ) : (
                      <BellOff className="h-3.5 w-3.5" />
                    )}
                    {search.alerts_enabled ? 'Pause' : 'Resume'}
                  </button>

                  <div className="flex items-center gap-1.5">
                    <button
                      type="button"
                      onClick={() => onDelete(search)}
                      disabled={busy}
                      aria-label={`Delete saved search ${search.name}`}
                      className="rounded-lg border border-slate-800 p-1.5 text-slate-500 transition hover:border-rose-500/40 hover:text-rose-300 disabled:opacity-50"
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </button>
                    <button
                      type="button"
                      onClick={() => onApply(search)}
                      className="rounded-lg bg-[#C5A46D] px-3 py-1.5 text-[10px] font-bold text-[#101C2C] transition hover:bg-[#D4AF37]"
                    >
                      Apply filters
                    </button>
                  </div>
                </div>
              </li>
            );
          })}
        </ul>
      )}
    </section>
  );
};
