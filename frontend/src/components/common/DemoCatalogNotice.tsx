import React, { useState } from 'react';
import { Info, X } from 'lucide-react';
import { useApp } from '../../context/AppContext';
import { isStaticCatalogBuild } from '../../utils/demoContent';

/**
 * Honest-state banner for public listing surfaces.
 *
 * Rendered when the catalog on screen comes from the bundled demo seed
 * rather than the live Laravel API: static frontend-only builds always, or
 * API builds while the backend is unreachable yet seeded data is displayed.
 * Dismissal is in-memory and per page view; demo content is never silently
 * shown as live inventory.
 */
export const DemoCatalogNotice: React.FC = () => {
  const { backendConnected, properties } = useApp();
  const [dismissed, setDismissed] = useState(false);
  const catalogIsDemo = isStaticCatalogBuild || (!backendConnected && properties.length > 0);
  if (!catalogIsDemo || dismissed) return null;
  return (
    <div
      role="status"
      className="flex items-start sm:items-center gap-3 justify-between px-4 py-3 rounded-xl border border-amber-300/70 bg-amber-50 text-amber-900 text-xs sm:text-sm"
    >
      <p className="flex items-start sm:items-center gap-2 leading-relaxed">
        <Info className="w-4 h-4 shrink-0 mt-0.5 sm:mt-0" aria-hidden="true" />
        <span>
          <strong className="font-semibold">Demo catalog.</strong> Live listings are unavailable
          right now, so sample properties, photos and walkthrough videos are shown. Media is
          placeholder content, not real Scheme 45 inventory.
        </span>
      </p>
      <button
        type="button"
        onClick={() => setDismissed(true)}
        aria-label="Dismiss demo catalog notice"
        className="shrink-0 p-1.5 rounded-lg hover:bg-amber-100 text-amber-700 cursor-pointer"
      >
        <X className="w-4 h-4" aria-hidden="true" />
      </button>
    </div>
  );
};
