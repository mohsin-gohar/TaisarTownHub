import React from 'react';
import { Film } from 'lucide-react';

/**
 * Small amber pill that marks bundled placeholder media as demo content.
 *
 * Placed next to video surfaces that use the shared sample walkthrough video
 * so no placeholder can pose as real Scheme 45 footage.
 */
export const DemoBadge: React.FC<{ label?: string; className?: string }> = ({
  label = 'Demo video',
  className = '',
}) => (
  <span
    className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide bg-amber-100/95 text-amber-800 border border-amber-300 ${className}`}
    title="Placeholder footage — not real Scheme 45 property video. Will be replaced by licensed walkthroughs."
  >
    <Film className="w-3 h-3" aria-hidden="true" />
    {label}
    <span className="sr-only">— placeholder footage, not real property video</span>
  </span>
);
