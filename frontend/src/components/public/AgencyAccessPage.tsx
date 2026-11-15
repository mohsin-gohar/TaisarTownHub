import React from 'react';
import { ArrowLeft, Building2, LockKeyhole, RefreshCw } from 'lucide-react';
import { useApp } from '../../context/AppContext';

export const AgencyAccessPage: React.FC = () => {
  const { currentUser, refreshSession, logoutAdmin, setCurrentView, isSessionLoading } = useApp();
  const access = currentUser.workspaceAccess;
  const title = access?.status === 'pending' ? 'Your agency is awaiting approval'
    : access?.status === 'rejected' ? 'Your agency application was not approved'
    : access?.status === 'suspended' ? 'Workspace access is suspended'
    : 'Workspace access is unavailable';

  return (
    <main className="min-h-screen bg-[#070B14] text-slate-100 px-4 py-12 flex items-center justify-center">
      <section aria-labelledby="agency-access-title" className="w-full max-w-xl rounded-3xl border border-[#C5A46D]/30 bg-[#101C2C] p-6 sm:p-10 space-y-6 shadow-xl">
        <div className="flex items-center gap-3 text-[#C5A46D]">
          <Building2 className="w-6 h-6" aria-hidden="true" />
          <span className="font-semibold tracking-wide">TaisarTownHub · Agency application</span>
        </div>
        <div className="space-y-3">
          <h1 id="agency-access-title" className="font-serif-luxury text-2xl sm:text-3xl font-bold">{title}</h1>
          <p className="text-sm text-slate-300 leading-relaxed" role="status">{access?.message || 'Please check your session again.'}</p>
          <p className="text-xs text-slate-400 break-words">Signed in as {currentUser.email}</p>
        </div>
        <div className="flex gap-3 p-4 border-l-2 border-[#C5A46D] bg-[#0B1220] rounded-r-xl">
          <LockKeyhole className="w-5 h-5 shrink-0 text-[#C5A46D]" aria-hidden="true" />
          <p className="text-xs text-slate-300 leading-relaxed">The platform owner must approve your agency before you can open its dashboard, upload private files or manage property listings.</p>
        </div>
        <div className="flex flex-wrap gap-3">
          <button type="button" onClick={() => void refreshSession()} disabled={isSessionLoading} className="inline-flex items-center gap-2 px-4 py-3 rounded-xl bg-[#C5A46D] text-[#101C2C] text-sm font-bold disabled:opacity-60 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#C5A46D]">
            <RefreshCw className="w-4 h-4" aria-hidden="true" /> Check approval status
          </button>
          <button type="button" onClick={logoutAdmin} className="px-4 py-3 rounded-xl border border-slate-600 text-sm font-semibold hover:bg-slate-800 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#C5A46D]">Sign out</button>
        </div>
        <button type="button" onClick={() => setCurrentView('home')} className="flex items-center gap-2 text-sm text-slate-400 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#C5A46D]">
          <ArrowLeft className="w-4 h-4" aria-hidden="true" /> Browse the public website
        </button>
      </section>
    </main>
  );
};
