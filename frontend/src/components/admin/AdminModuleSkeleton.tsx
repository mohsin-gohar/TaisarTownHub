import React from 'react';

/** Placeholder shown while a lazy admin module chunk loads. */
export const AdminModuleSkeleton: React.FC = () => {
  return (
    <div className="space-y-5 animate-pulse" aria-busy="true" aria-live="polite" aria-label="Loading module">
      <div className="space-y-2">
        <div className="h-8 w-56 max-w-full rounded-lg bg-slate-800" />
        <div className="h-3 w-80 max-w-full rounded bg-slate-800/70" />
      </div>
      <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
        {Array.from({ length: 4 }).map((_, index) => (
          <div key={index} className="h-24 rounded-2xl bg-slate-800/80 border border-slate-800" />
        ))}
      </div>
      <div className="h-72 rounded-2xl bg-slate-800/50 border border-slate-800" />
    </div>
  );
};
