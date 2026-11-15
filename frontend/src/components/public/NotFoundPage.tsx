import React from 'react';
import { ArrowRight, Compass, Home, PhoneCall } from 'lucide-react';
import { useApp } from '../../context/AppContext';

/**
 * Premium 404 screen for unknown public URLs.
 *
 * The legacy router silently fell back to the homepage for every unmatched
 * path, which is a soft-404 for crawlers and confusing for visitors. This page
 * gives the visitor a clear recovery path while App.tsx marks the route
 * noindex,follow.
 */
export const NotFoundPage: React.FC = () => {
  const { setCurrentView, setSelectedProjectId, setSelectedPropertyId } = useApp();

  // Real anchors (crawlable, middle-click / open-in-new-tab) with an SPA
  // transition on plain left-click — same contract as the public navbar.
  const go = (view: string, path: string) => (e: React.MouseEvent<HTMLAnchorElement>) => {
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
    e.preventDefault();
    setSelectedProjectId(null);
    setSelectedPropertyId(null);
    setCurrentView(view);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  return (
    <div className="min-h-[70vh] flex items-center justify-center px-4 py-16 bg-[#0B1220]">
      <div className="w-full max-w-2xl text-center">
        <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-[#C5A46D]/10 border border-[#C5A46D]/30 mb-6">
          <Compass className="w-8 h-8 text-[#C5A46D]" />
        </div>

        <p className="text-[11px] uppercase tracking-[0.35em] font-semibold text-[#C5A46D]">
          Error 404
        </p>
        <h1 className="mt-3 font-serif-luxury text-4xl sm:text-5xl font-bold text-white">
          This page has moved off the map.
        </h1>
        <p className="mt-4 text-sm sm:text-base text-slate-400 leading-relaxed max-w-xl mx-auto">
          The address you followed doesn&apos;t exist or may have been retired. Every live listing,
          project and advisory service is still one click away.
        </p>

        <div className="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
          <a
            href="/"
            onClick={go('home', '/')}
            className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] text-sm font-bold transition-colors cursor-pointer shadow-lg shadow-[#C5A46D]/20"
          >
            <Home className="w-4 h-4" />
            Back to Home
          </a>
          <a
            href="/properties"
            onClick={go('properties', '/properties')}
            className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold border border-slate-700 transition-colors cursor-pointer"
          >
            Browse Properties
            <ArrowRight className="w-4 h-4 text-[#C5A46D]" />
          </a>
          <a
            href="/contact"
            onClick={go('contact', '/contact')}
            className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl text-slate-300 hover:text-white hover:bg-slate-800 text-sm font-semibold transition-colors cursor-pointer"
          >
            <PhoneCall className="w-4 h-4 text-[#C5A46D]" />
            Advisory Desk
          </a>
        </div>

        <p className="mt-8 text-xs text-slate-500">
          Looking for a specific file or plot? Search the live inventory or call our Scheme 45 desk.
        </p>
      </div>
    </div>
  );
};
