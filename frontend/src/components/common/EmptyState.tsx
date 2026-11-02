import React from 'react';
import { CheckCircle2, ArrowRight } from 'lucide-react';

interface EmptyStateProps {
  icon: React.ReactNode;
  title: string;
  description: string;
  actionLabel?: string;
  onAction?: () => void;
  chip?: string;
}

/**
 * Shared premium empty state: soft icon medallion, headline,
 * supporting copy and an optional call-to-action. Used when
 * listings / inquiries / transactions have no matching records.
 */
export const EmptyState: React.FC<EmptyStateProps> = ({
  icon,
  title,
  description,
  actionLabel,
  onAction,
  chip,
}) => {
  return (
    <div className="flex flex-col items-center justify-center text-center py-16 px-6 rounded-2xl border border-slate-800/80 bg-[#0B1220]/70 animate-fadeInUp">
      <div className="relative mb-5">
        <div className="absolute inset-0 rounded-full bg-[#C5A46D]/10 blur-xl scale-150" aria-hidden="true" />
        <div className="relative w-20 h-20 rounded-full bg-[#101C2C] border border-[#C5A46D]/25 flex items-center justify-center text-[#C5A46D] shadow-xl">
          {icon}
        </div>
        <div className="absolute -bottom-1 -right-1 w-7 h-7 rounded-full bg-[#101C2C] border border-[#C5A46D]/30 flex items-center justify-center">
          <CheckCircle2 className="w-3.5 h-3.5 text-slate-500" />
        </div>
      </div>

      {chip && (
        <span className="mb-3 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest bg-[#C5A46D]/10 text-[#C5A46D] border border-[#C5A46D]/25">
          {chip}
        </span>
      )}

      <h3 className="font-serif-luxury text-xl sm:text-2xl font-bold text-white">{title}</h3>
      <p className="mt-2 max-w-md text-xs sm:text-sm text-slate-400 leading-relaxed">{description}</p>

      {actionLabel && onAction && (
        <button
          onClick={onAction}
          className="mt-6 px-6 py-2.5 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold text-xs transition-all flex items-center gap-2 cursor-pointer shadow-lg shadow-[#C5A46D]/25"
        >
          <span>{actionLabel}</span>
          <ArrowRight className="w-3.5 h-3.5" />
        </button>
      )}
    </div>
  );
};
