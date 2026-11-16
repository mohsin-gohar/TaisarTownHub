import React from 'react';
import { ArrowRight, Scale, Share2, Trash2, X } from 'lucide-react';
import { PublicProperty } from '../../types';
import { ImageWithFallback } from '../common/ImageWithFallback';

interface CompareTrayProps {
  items: PublicProperty[];
  onRemove: (id: string) => void;
  onClear: () => void;
  onCompare: () => void;
  onShare: () => void;
}

/** Floating shortlist tray shown while at least one property is selected. */
export const CompareTray: React.FC<CompareTrayProps> = ({
  items,
  onRemove,
  onClear,
  onCompare,
  onShare,
}) => {
  if (items.length === 0) return null;

  const canCompare = items.length >= 2;

  return (
    <div className="fixed inset-x-0 bottom-0 z-40 px-3 pb-3 sm:px-6 sm:pb-5 pointer-events-none">
      <div className="pointer-events-auto mx-auto max-w-5xl rounded-2xl border border-[#C5A46D]/40 bg-[#0E1724]/95 backdrop-blur-xl shadow-2xl shadow-black/50 animate-fadeInUp">
        <div className="flex flex-col gap-3 p-3 sm:p-4">
          <div className="flex items-center justify-between gap-3">
            <div className="flex items-center gap-2 text-xs font-bold text-white">
              <span className="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-[#C5A46D]/15 text-[#C5A46D] border border-[#C5A46D]/30">
                <Scale className="h-4 w-4" />
              </span>
              <span>Compare shortlist</span>
              <span className="rounded-full bg-[#C5A46D] px-2 py-0.5 text-[10px] font-bold text-[#101C2C]">
                {items.length}/4
              </span>
            </div>
            <div className="flex items-center gap-1.5">
              <button
                type="button"
                onClick={onShare}
                className="inline-flex items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800/80 px-2.5 py-1.5 text-[11px] font-semibold text-slate-200 transition hover:border-[#C5A46D]/50 hover:text-white"
              >
                <Share2 className="h-3.5 w-3.5" />
                <span className="hidden sm:inline">Share</span>
              </button>
              <button
                type="button"
                onClick={onClear}
                className="inline-flex items-center gap-1.5 rounded-lg border border-slate-800 px-2.5 py-1.5 text-[11px] font-semibold text-slate-400 transition hover:text-rose-300 hover:border-rose-500/40"
              >
                <Trash2 className="h-3.5 w-3.5" />
                <span className="hidden sm:inline">Clear</span>
              </button>
            </div>
          </div>

          <div className="flex items-stretch gap-2 overflow-x-auto pb-0.5">
            {items.map((item) => (
              <div
                key={item.id}
                className="group relative flex min-w-[150px] max-w-[190px] flex-1 items-center gap-2 rounded-xl border border-slate-800 bg-[#0B1220] p-1.5"
              >
                <ImageWithFallback
                  src={item.images?.[0]}
                  alt={item.title}
                  className="h-12 w-14 shrink-0 rounded-lg object-cover"
                />
                <div className="min-w-0 pr-4">
                  <p className="truncate text-[11px] font-semibold text-white">{item.title}</p>
                  <p className="truncate text-[10px] text-[#C5A46D]">{item.formattedPrice}</p>
                </div>
                <button
                  type="button"
                  onClick={() => onRemove(item.id)}
                  aria-label={`Remove ${item.title} from comparison`}
                  className="absolute right-1 top-1 rounded-md p-1 text-slate-500 transition hover:bg-slate-800 hover:text-white"
                >
                  <X className="h-3 w-3" />
                </button>
              </div>
            ))}
            {items.length < 2 && (
              <div className="hidden flex-1 items-center rounded-xl border border-dashed border-slate-800 px-3 text-[11px] text-slate-500 sm:flex">
                Select one more property to compare side by side.
              </div>
            )}
          </div>

          <button
            type="button"
            onClick={onCompare}
            disabled={!canCompare}
            className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#C5A46D] px-4 py-2.5 text-xs font-bold text-[#101C2C] shadow-lg shadow-[#C5A46D]/20 transition hover:bg-[#D4AF37] disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-400 disabled:shadow-none"
          >
            <Scale className="h-4 w-4" />
            {canCompare ? `Compare ${items.length} properties` : 'Select at least 2 properties'}
            <ArrowRight className="h-3.5 w-3.5" />
          </button>
        </div>
      </div>
    </div>
  );
};
