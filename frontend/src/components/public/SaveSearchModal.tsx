import React, { useEffect, useRef, useState } from 'react';
import { Bell, BellOff, BookmarkPlus, Loader2, Mail, X } from 'lucide-react';

interface SaveSearchModalProps {
  open: boolean;
  summary: string;
  defaultName: string;
  saving: boolean;
  error?: string | null;
  onClose: () => void;
  onCreate: (payload: { name: string; frequency: 'instant' | 'daily' | 'weekly'; alerts_enabled: boolean }) => void;
}

export const SaveSearchModal: React.FC<SaveSearchModalProps> = ({
  open,
  summary,
  defaultName,
  saving,
  error,
  onClose,
  onCreate,
}) => {
  const [name, setName] = useState(defaultName);
  const [frequency, setFrequency] = useState<'instant' | 'daily' | 'weekly'>('daily');
  const [alertsEnabled, setAlertsEnabled] = useState(true);
  const nameRef = useRef<HTMLInputElement | null>(null);

  useEffect(() => {
    if (!open) return;
    setName(defaultName);
    setFrequency('daily');
    setAlertsEnabled(true);
    const timer = window.setTimeout(() => nameRef.current?.focus(), 30);
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', onKey);
    return () => {
      window.clearTimeout(timer);
      document.removeEventListener('keydown', onKey);
    };
  }, [open, defaultName, onClose]);

  if (!open) return null;

  const submit = (event: React.FormEvent) => {
    event.preventDefault();
    if (saving || name.trim().length < 2) return;
    onCreate({ name: name.trim(), frequency, alerts_enabled: alertsEnabled });
  };

  return (
    <div
      className="fixed inset-0 z-[60] flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) onClose();
      }}
    >
      <form
        onSubmit={submit}
        role="dialog"
        aria-modal="true"
        aria-labelledby="save-search-title"
        className="w-full max-w-lg overflow-hidden rounded-2xl border border-[#E7C878]/35 bg-[#0B1220] shadow-2xl"
      >
        <header className="flex items-start justify-between gap-3 border-b border-slate-800 px-5 py-4">
          <div className="flex items-center gap-2.5">
            <span className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#E7C878]/30 bg-[#E7C878]/10 text-[#E7C878]">
              <BookmarkPlus className="h-4 w-4" />
            </span>
            <div>
              <h2 id="save-search-title" className="font-serif-luxury text-lg font-bold text-white">
                Save this search
              </h2>
              <p className="text-[11px] text-slate-400">Get an email when a new listing matches these filters.</p>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close save search dialog"
            className="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-800 hover:text-white"
          >
            <X className="h-4 w-4" />
          </button>
        </header>

        <div className="space-y-4 px-5 py-4">
          <div className="rounded-xl border border-slate-800 bg-[#07141E] p-3">
            <p className="text-[9px] font-bold uppercase tracking-[0.18em] text-slate-500">Filters to save</p>
            <p className="mt-1 text-xs leading-relaxed text-slate-300">{summary}</p>
          </div>

          <label className="block space-y-1.5">
            <span className="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Search name</span>
            <input
              ref={nameRef}
              type="text"
              value={name}
              onChange={(event) => setName(event.target.value)}
              maxLength={120}
              minLength={2}
              required
              placeholder="e.g. Sector 63 plots under 1 crore"
              className="w-full rounded-lg border border-slate-700 bg-[#07141E] px-3 py-2.5 text-xs text-white placeholder:text-slate-500 focus:border-[#E7C878] focus:outline-none"
            />
          </label>

          <label className="block space-y-1.5">
            <span className="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Email alert cadence</span>
            <select
              value={frequency}
              onChange={(event) => setFrequency(event.target.value as 'instant' | 'daily' | 'weekly')}
              className="w-full rounded-lg border border-slate-700 bg-[#07141E] px-3 py-2.5 text-xs text-white focus:border-[#E7C878] focus:outline-none"
            >
              <option value="instant">Instant — on the next hourly sweep</option>
              <option value="daily">Daily digest</option>
              <option value="weekly">Weekly digest</option>
            </select>
          </label>

          <button
            type="button"
            role="switch"
            aria-checked={alertsEnabled}
            onClick={() => setAlertsEnabled((value) => !value)}
            className="flex w-full items-center justify-between gap-3 rounded-xl border border-slate-800 bg-[#07141E] px-3 py-2.5 text-left"
          >
            <span className="flex items-center gap-2 text-xs text-slate-300">
              {alertsEnabled ? <Bell className="h-4 w-4 text-[#E7C878]" /> : <BellOff className="h-4 w-4 text-slate-500" />}
              Email me when matching listings are published
            </span>
            <span
              className={`relative h-5 w-9 shrink-0 rounded-full transition ${
                alertsEnabled ? 'bg-[#E7C878]' : 'bg-slate-700'
              }`}
            >
              <span
                className={`absolute top-0.5 h-4 w-4 rounded-full bg-white transition-all ${
                  alertsEnabled ? 'left-[18px]' : 'left-0.5'
                }`}
              />
            </span>
          </button>

          <p className="flex items-start gap-1.5 text-[10px] leading-relaxed text-slate-500">
            <Mail className="mt-0.5 h-3 w-3 shrink-0" />
            Alerts are sent to the email address on your TaisarTownHub account. You can pause or delete this search at any
            time.
          </p>

          {error && (
            <p role="alert" className="rounded-lg border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-[11px] text-rose-300">
              {error}
            </p>
          )}
        </div>

        <footer className="flex items-center justify-end gap-2 border-t border-slate-800 bg-[#0E1724] px-5 py-3">
          <button
            type="button"
            onClick={onClose}
            className="rounded-xl border border-slate-700 px-4 py-2 text-[11px] font-semibold text-slate-300 transition hover:bg-slate-800"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={saving || name.trim().length < 2}
            className="inline-flex items-center gap-1.5 rounded-xl bg-[#E7C878] px-4 py-2 text-[11px] font-bold text-[#0B1220] transition hover:bg-[#F3C464] disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-400"
          >
            {saving && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
            {saving ? 'Saving…' : 'Save search'}
          </button>
        </footer>
      </form>
    </div>
  );
};
