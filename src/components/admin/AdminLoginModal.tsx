import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import { ArrowRight, X, KeyRound, Shield } from 'lucide-react';
import { UserRole } from '../../types';
import { APP_MODE } from '../../config/appMode';

export const AdminLoginModal: React.FC = () => {
  const { loginWithCredentials, loginAsDemo, verifyPresenterPin, setCurrentView, currentTheme, showToast } = useApp();
  const [password, setPassword] = useState('');
  const [selectedRole, setSelectedRole] = useState<UserRole>('admin');
  const [busy, setBusy] = useState(false);
  const [pinInput, setPinInput] = useState('');
  const [pinVerified, setPinVerified] = useState(false);

  // The instant grid exists ONLY in the demo build. Roles mirror erpSeed accounts.
  const demoAccounts: { role: UserRole; name: string; title: string; email: string }[] = [
    { role: 'super_admin', name: 'Syed Moazzam Ali', title: 'Super Admin', email: 'superadmin@taisartownhub.com' },
    { role: 'admin', name: 'Saleem Siddiqui', title: 'Agency Admin', email: 'admin@taisartownhub.com' },
    { role: 'manager', name: 'Kamran Haider', title: 'Manager', email: 'manager@taisartownhub.com' },
    { role: 'dealer', name: 'Bilal Farooqui', title: 'Property Dealer', email: 'dealer@taisartownhub.com' },
    { role: 'operator', name: 'Zainab Fatima', title: 'Data Operator', email: 'operator@taisartownhub.com' }
  ];

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    if (busy) return;
    const emailMap: Record<string, string> = {
      'super_admin': 'superadmin@taisartownhub.com',
      'admin': 'admin@taisartownhub.com',
      'manager': 'manager@taisartownhub.com',
      'dealer': 'dealer@taisartownhub.com',
      'operator': 'operator@taisartownhub.com',
    };
    setBusy(true);
    try {
      const result = await loginWithCredentials(emailMap[selectedRole as string] || 'admin@taisartownhub.com', password);
      if (result.success) {
        setCurrentView('admin');
      }
    } finally {
      setBusy(false);
    }
  };

  const handlePinVerify = (e: React.FormEvent) => {
    e.preventDefault();
    // Speed-bump presenter PIN (see config/appMode.ts). Demo build only.
    if (verifyPresenterPin(pinInput)) {
      setPinVerified(true);
    } else {
      showToast('error', 'Invalid presenter PIN.');
    }
  };

  const handleInstantRoleLogin = async (role: UserRole) => {
    if (busy) return;
    setBusy(true);
    try {
      const ok = await loginAsDemo(role, pinInput);
      if (ok) setCurrentView('admin');
    } finally {
      setBusy(false);
    }
  };

  const showDemoGrid = APP_MODE === 'demo';

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-fadeIn">
      <div className="relative w-full max-w-lg bg-[#101C2C] border border-[#C5A46D]/40 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-5">
        <button
          onClick={() => setCurrentView('home')}
          className="absolute top-4 right-4 text-slate-400 hover:text-white p-1 rounded-lg cursor-pointer"
        >
          <X className="w-5 h-5" />
        </button>

        <div className="text-center space-y-1.5">
          <div className="w-12 h-12 rounded-2xl bg-gradient-to-br from-[#C5A46D] to-[#8C6D37] flex items-center justify-center mx-auto shadow-lg shadow-[#C5A46D]/20 border border-[#F9E7BA]/40">
            <Shield className="w-6 h-6 text-[#101C2C]" />
          </div>
          <h2 className="font-serif-luxury text-xl font-bold text-white">
            Agency Management & ERP Portal
          </h2>
          <p className="text-xs text-slate-400">
            {currentTheme.agencyName} Back-Office Operations, Financial Ledger & Scheme 45 Registry
          </p>
        </div>

        {/* 1-Click Fast Role Sign-In Grid (demo build + unverified presenter PIN) */}
        {showDemoGrid && !pinVerified && (
          <form onSubmit={handlePinVerify} className="space-y-2 border border-amber-500/30 bg-amber-500/5 rounded-xl p-3">
            <span className="text-[11px] font-bold text-amber-300 uppercase tracking-wider flex items-center gap-1.5">
              <KeyRound className="w-3.5 h-3.5" />
              Presenter PIN required for instant demo sign-in
            </span>
            <div className="flex gap-2">
              <input
                type="password"
                inputMode="numeric"
                value={pinInput}
                onChange={(e) => setPinInput(e.target.value)}
                className="flex-1 bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-amber-400 focus:outline-none font-mono tracking-widest"
                placeholder="Presenter PIN"
              />
              <button
                type="submit"
                className="px-4 py-2 rounded-lg bg-amber-400 hover:bg-amber-300 text-[#101C2C] font-bold text-xs cursor-pointer"
              >
                Unlock
              </button>
            </div>
            <p className="text-[10px] text-slate-500 leading-snug">
              Speed bump only — demo deployments must also use platform password protection and fictional data.
            </p>
          </form>
        )}

        {showDemoGrid && pinVerified && (
          <div className="space-y-2">
            <span className="text-[11px] font-bold text-[#C5A46D] uppercase tracking-wider block">
              Instant Demo Sign-In (Select Role Profile):
            </span>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
              {demoAccounts.map((acc) => (
                <button
                  key={acc.role}
                  type="button"
                  disabled={busy}
                  onClick={() => handleInstantRoleLogin(acc.role)}
                  className="p-2.5 rounded-xl bg-[#0B1220] hover:bg-[#C5A46D]/15 border border-slate-800 hover:border-[#C5A46D]/60 text-left transition-all cursor-pointer group flex items-center justify-between disabled:opacity-50"
                >
                  <div>
                    <span className="text-xs font-bold text-white group-hover:text-[#C5A46D] block">
                      {acc.title}
                    </span>
                    <span className="text-[10px] text-slate-400 block truncate">
                      {acc.name}
                    </span>
                  </div>
                  <ArrowRight className="w-3.5 h-3.5 text-slate-500 group-hover:text-[#C5A46D] group-hover:translate-x-0.5 transition-transform" />
                </button>
              ))}
            </div>
          </div>
        )}

        {/* Manual Password Form */}
        <form onSubmit={handleLogin} className="pt-2 border-t border-slate-800 space-y-3">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className="text-[11px] font-semibold text-slate-300 block mb-1">
                Target Role
              </label>
              <select
                value={selectedRole}
                onChange={(e) => setSelectedRole(e.target.value as UserRole)}
                className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
              >
                <option value="super_admin">Super Admin</option>
                <option value="admin">Agency Director (Admin)</option>
                <option value="manager">Operations Manager</option>
                <option value="dealer">Property Dealer</option>
                <option value="operator">Data Entry Operator</option>
              </select>
            </div>

            <div>
              <label className="text-[11px] font-semibold text-slate-300 block mb-1">
                Password
              </label>
              <input
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-mono"
                placeholder="Enter demo password"
              />
            </div>
          </div>

          <button
            type="submit"
            disabled={busy}
            className="w-full py-2.5 px-4 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold text-xs transition-colors flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-[#C5A46D]/20 disabled:opacity-60"
          >
            <span>{busy ? 'Signing In…' : 'Sign In to Dashboard'}</span>
            <ArrowRight className="w-4 h-4" />
          </button>
        </form>

        <div className="text-center pt-1">
          <button
            onClick={() => setCurrentView('home')}
            className="text-xs text-slate-400 hover:text-white cursor-pointer"
          >
            Return to Public Website
          </button>
        </div>
      </div>
    </div>
  );
};
