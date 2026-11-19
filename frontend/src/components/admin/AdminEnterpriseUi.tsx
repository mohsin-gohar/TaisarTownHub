import React from 'react';
import { AlertTriangle, CheckCircle2, Inbox, Loader2 } from 'lucide-react';

/** Shared visual primitives for the Phase-7 enterprise admin screens. */

export const formatPKR = (value: unknown): string =>
  `PKR ${Number(value || 0).toLocaleString('en-PK')}`;

export const formatBytes = (value: unknown): string => {
  const bytes = Number(value || 0);
  if (!bytes) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB'];
  const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
  return `${(bytes / Math.pow(1024, index)).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
};

export const formatDate = (value: unknown): string => {
  if (!value) return '—';
  const date = new Date(String(value));
  return Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString('en-PK', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

export const titleCase = (value: unknown): string =>
  String(value ?? '').replace(/[_-]+/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

export const EnterprisePage: React.FC<{
  title: string;
  subtitle?: string;
  actions?: React.ReactNode;
  children: React.ReactNode;
}> = ({ title, subtitle, actions, children }) => (
  <div className="space-y-6 animate-fadeIn">
    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-6 rounded-2xl bg-gradient-to-r from-[#0B1220] to-[#1A2638] border border-[#E7C878]/30 shadow-xl">
      <div className="space-y-1 min-w-0">
        <h2 className="font-serif-luxury text-2xl font-bold text-white truncate">{title}</h2>
        {subtitle && <p className="text-xs text-slate-300">{subtitle}</p>}
      </div>
      {actions && <div className="flex flex-wrap items-center gap-2.5 shrink-0">{actions}</div>}
    </div>
    {children}
  </div>
);

export const EnterpriseCard: React.FC<{
  title?: string;
  subtitle?: string;
  actions?: React.ReactNode;
  className?: string;
  children: React.ReactNode;
}> = ({ title, subtitle, actions, className = '', children }) => (
  <div className={`bg-[#0B1220] p-5 sm:p-6 rounded-2xl border border-slate-800 shadow-lg ${className}`}>
    {(title || actions) && (
      <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
          {title && <h3 className="font-serif-luxury text-lg font-bold text-white">{title}</h3>}
          {subtitle && <p className="text-[11px] text-slate-400 mt-0.5">{subtitle}</p>}
        </div>
        {actions}
      </div>
    )}
    {children}
  </div>
);

export const EnterpriseStat: React.FC<{
  label: string;
  value: React.ReactNode;
  hint?: string;
  tone?: 'gold' | 'white' | 'green' | 'red';
}> = ({ label, value, hint, tone = 'white' }) => {
  const toneClass = {
    gold: 'text-[#E7C878]',
    white: 'text-white',
    green: 'text-emerald-400',
    red: 'text-rose-400',
  }[tone];

  return (
    <div className="bg-[#0B1220] p-5 rounded-2xl border border-slate-800 shadow-lg">
      <span className="text-[10px] text-slate-400 font-semibold uppercase tracking-wider">{label}</span>
      <div className={`font-serif-luxury text-2xl font-bold mt-1 ${toneClass}`}>{value}</div>
      {hint && <div className="text-[11px] text-slate-500 mt-1">{hint}</div>}
    </div>
  );
};

export const EnterpriseBadge: React.FC<{
  children: React.ReactNode;
  tone?: 'gold' | 'green' | 'red' | 'blue' | 'slate';
}> = ({ children, tone = 'slate' }) => {
  const tones = {
    gold: 'bg-[#E7C878]/15 text-[#F6E3B4] border-[#E7C878]/40',
    green: 'bg-emerald-500/10 text-emerald-300 border-emerald-500/30',
    red: 'bg-rose-500/10 text-rose-300 border-rose-500/30',
    blue: 'bg-sky-500/10 text-sky-300 border-sky-500/30',
    slate: 'bg-slate-800 text-slate-300 border-slate-700',
  }[tone];

  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border ${tones}`}>
      {children}
    </span>
  );
};

export const EnterpriseNote: React.FC<{
  tone?: 'error' | 'success' | 'info';
  children: React.ReactNode;
}> = ({ tone = 'info', children }) => {
  if (!children) return null;
  const styles = {
    error: 'bg-rose-500/10 border-rose-500/30 text-rose-200',
    success: 'bg-emerald-500/10 border-emerald-500/30 text-emerald-200',
    info: 'bg-sky-500/10 border-sky-500/30 text-sky-200',
  }[tone];
  const Icon = tone === 'error' ? AlertTriangle : tone === 'success' ? CheckCircle2 : Inbox;

  return (
    <div className={`flex items-start gap-2 px-3 py-2.5 rounded-xl border text-xs ${styles}`}>
      <Icon className="w-4 h-4 shrink-0 mt-0.5" />
      <div className="min-w-0">{children}</div>
    </div>
  );
};

export const EnterpriseEmpty: React.FC<{ title: string; hint?: string }> = ({ title, hint }) => (
  <div className="flex flex-col items-center justify-center py-10 text-center">
    <Inbox className="w-8 h-8 text-slate-600 mb-2" />
    <span className="text-sm font-semibold text-slate-300">{title}</span>
    {hint && <span className="text-[11px] text-slate-500 mt-1 max-w-md">{hint}</span>}
  </div>
);

export const EnterpriseSpinner: React.FC<{ label?: string }> = ({ label = 'Loading…' }) => (
  <div className="flex items-center justify-center gap-2 py-10 text-slate-400 text-xs">
    <Loader2 className="w-4 h-4 animate-spin" />
    <span>{label}</span>
  </div>
);

export const EnterpriseButton: React.FC<
  React.ButtonHTMLAttributes<HTMLButtonElement> & { variant?: 'primary' | 'secondary' | 'danger' }
> = ({ variant = 'primary', className = '', children, ...rest }) => {
  const variants = {
    primary: 'bg-[#E7C878] hover:bg-[#F3C464] text-[#0B1220] font-bold shadow',
    secondary: 'bg-slate-800 hover:bg-slate-700 text-white font-semibold border border-slate-700',
    danger: 'bg-rose-600/20 hover:bg-rose-600/30 text-rose-200 font-semibold border border-rose-500/30',
  }[variant];

  return (
    <button
      {...rest}
      className={`inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed ${variants} ${className}`}
    >
      {children}
    </button>
  );
};

export const enterpriseInputClass =
  'w-full px-3 py-2 rounded-lg bg-[#07141E] border border-slate-700 text-xs text-white placeholder:text-slate-500 focus:outline-none focus:border-[#E7C878]';
