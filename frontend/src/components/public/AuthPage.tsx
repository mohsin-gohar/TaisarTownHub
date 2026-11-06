import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import {
  Building2,
  ArrowRight,
  ArrowLeft,
  CheckCircle2,
  Lock,
  Mail,
  Phone,
  CreditCard,
  Briefcase,
  Eye,
  EyeOff,
  MapPin,
  Check
} from 'lucide-react';

interface AuthPageProps {
  initialTab?: 'signin' | 'register';
}

export const AuthPage: React.FC<AuthPageProps> = ({ initialTab = 'signin' }) => {
  const {
    loginWithCredentials,
    registerDealerAccount,
    setCurrentView,
    currentTheme,
    showToast,
  } = useApp();

  const [tab, setTab] = useState<'signin' | 'register'>(initialTab);
  const [busy, setBusy] = useState(false);

  // Sign in state
  const [signInEmail, setSignInEmail] = useState('');
  const [signInPassword, setSignInPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);

  // Register state
  const [regFullName, setRegFullName] = useState('');
  const [regEmail, setRegEmail] = useState('');
  const [regPhone, setRegPhone] = useState('');
  const [regCnic, setRegCnic] = useState('');
  const [regAgency, setRegAgency] = useState('');
  const [regSector, setRegSector] = useState('Sector 63');
  const [regPassword, setRegPassword] = useState('');
  const [regConfirmPassword, setRegConfirmPassword] = useState('');
  const [regTermsAgreed, setRegTermsAgreed] = useState(false);

  const handleSignIn = async (e: React.FormEvent) => {
    e.preventDefault();
    if (busy) return;

    const email = signInEmail.trim();
    if (!email) {
      showToast('error', 'Please enter your account email.');
      return;
    }

    if (!signInPassword) {
      showToast('error', 'Please enter your account password.');
      return;
    }

    setBusy(true);
    try {
      const result = await loginWithCredentials(email, signInPassword);
      if (result.success) {
        setCurrentView('admin');
      }
    } finally {
      setBusy(false);
    }
  };

  const handleRegister = async (e: React.FormEvent) => {
    e.preventDefault();
    if (busy) return;

    if (!regTermsAgreed) {
      showToast('error', 'Please accept the dealer code of conduct and verification agreement.');
      return;
    }

    if (regPassword && regConfirmPassword && regPassword !== regConfirmPassword) {
      showToast('error', 'Passwords do not match.');
      return;
    }

    setBusy(true);
    try {
      const result = await registerDealerAccount({
        fullName: regFullName,
        email: regEmail,
        password: regPassword,
        phone: regPhone,
        cnic: regCnic,
        agencyOrBranch: regAgency,
        sectorFocus: regSector,
      });

      if (result.success) {
        setCurrentView('admin');
      }
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="min-h-screen bg-[#070B14] py-12 px-4 sm:px-6 lg:px-8 flex flex-col justify-center items-center">
      {/* Top Back Navigation */}
      <div className="w-full max-w-xl mb-6 flex items-center justify-between">
        <button
          onClick={() => setCurrentView('home')}
          className="inline-flex items-center gap-2 text-xs font-semibold text-slate-300 hover:text-[#C5A46D] transition-colors cursor-pointer"
        >
          <ArrowLeft className="w-4 h-4" />
          <span>Return to Public Website</span>
        </button>

        <span className="text-[11px] font-mono text-[#C5A46D] uppercase tracking-wider">
          Scheme 45, Karachi
        </span>
      </div>

      {/* Main Luxury Auth Card */}
      <div className="w-full max-w-xl bg-[#101C2C] border border-[#C5A46D]/40 rounded-3xl p-6 sm:p-10 shadow-2xl relative overflow-hidden space-y-6">
        {/* Decorative corner accent */}
        <div className="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-[#C5A46D]/15 to-transparent rounded-bl-full pointer-events-none" />

        {/* Brand Header */}
        <div className="text-center space-y-2">
          <div className="w-14 h-14 rounded-2xl bg-white/5 flex items-center justify-center mx-auto shadow-lg shadow-[#C5A46D]/20 border border-[#F9E7BA]/40 overflow-hidden">
            <img src="/logo.png" alt="Logo" className="w-full h-full object-contain" />
          </div>
          <h1 className="font-serif-luxury text-2xl sm:text-3xl font-bold text-white tracking-tight">
            {currentTheme.agencyName}
          </h1>
          <p className="text-xs text-slate-400 max-w-md mx-auto">
            Authorized Agency ERP & Scheme 45 Real Estate Management System
          </p>
        </div>

        {/* Tab Switcher: Sign In vs Register */}
        <div className="grid grid-cols-2 p-1.5 rounded-2xl bg-[#0B1220] border border-slate-800">
          <button
            type="button"
            onClick={() => setTab('signin')}
            className={`py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer ${
              tab === 'signin'
                ? 'bg-[#C5A46D] text-[#101C2C] shadow-md shadow-[#C5A46D]/20'
                : 'text-slate-400 hover:text-white'
            }`}
          >
            Portal Sign In
          </button>
          <button
            type="button"
            onClick={() => setTab('register')}
            className={`py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer ${
              tab === 'register'
                ? 'bg-[#C5A46D] text-[#101C2C] shadow-md shadow-[#C5A46D]/20'
                : 'text-slate-400 hover:text-white'
            }`}
          >
            Register as Dealer / Agent
          </button>
        </div>

        {/* TAB 1: SIGN IN */}
        {tab === 'signin' && (
          <form onSubmit={handleSignIn} className="space-y-4 animate-fadeIn">
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-slate-300 flex items-center gap-1.5">
                <Mail className="w-3.5 h-3.5 text-[#C5A46D]" />
                <span>Account Email</span>
              </label>
              <input
                type="email"
                required
                value={signInEmail}
                onChange={(e) => setSignInEmail(e.target.value)}
                placeholder="admin@taisartownhub.com (or dealer email)"
                className="w-full bg-[#0B1220] text-white text-xs sm:text-sm rounded-xl px-4 py-3 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
              />
            </div>

            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <label className="text-xs font-semibold text-slate-300 flex items-center gap-1.5">
                  <Lock className="w-3.5 h-3.5 text-[#C5A46D]" />
                  <span>Account Password</span>
                </label>
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="text-[11px] text-slate-400 hover:text-[#C5A46D] flex items-center gap-1 cursor-pointer"
                >
                  {showPassword ? <EyeOff className="w-3.5 h-3.5" /> : <Eye className="w-3.5 h-3.5" />}
                  <span>{showPassword ? 'Hide' : 'Show'}</span>
                </button>
              </div>
              <input
                type={showPassword ? 'text' : 'password'}
                required
                value={signInPassword}
                onChange={(e) => setSignInPassword(e.target.value)}
                placeholder="Enter password"
                className="w-full bg-[#0B1220] text-white text-xs sm:text-sm rounded-xl px-4 py-3 border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-mono"
              />
            </div>

            <button
              type="submit"
              disabled={busy}
              className="w-full py-3.5 px-4 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] active:scale-[0.99] text-[#101C2C] font-bold text-xs sm:text-sm transition-all flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-[#C5A46D]/20 disabled:opacity-60"
            >
              <span>{busy ? 'Authenticating…' : 'Sign In to Agency Dashboard'}</span>
              <ArrowRight className="w-4 h-4" />
            </button>
          </form>
        )}

        {/* TAB 2: REGISTER AS DEALER */}
        {tab === 'register' && (
          <form onSubmit={handleRegister} className="space-y-4 animate-fadeIn">
            <div className="bg-[#0B1220] border border-slate-800 rounded-2xl p-3.5 text-xs text-slate-300 space-y-1">
              <span className="font-bold text-[#C5A46D] flex items-center gap-1.5">
                <Briefcase className="w-3.5 h-3.5" />
                Real Estate Consultant & Dealer Registration
              </span>
              <p className="text-[11px] text-slate-400">
                Register as an affiliated dealer to inspect assigned plots, submit buyer requests, and track commission accruals in Taiser Town Scheme 45.
              </p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div className="space-y-1">
                <label className="text-[11px] font-semibold text-slate-300">Full Name *</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Asad Siddiqui"
                  value={regFullName}
                  onChange={(e) => setRegFullName(e.target.value)}
                  className="w-full bg-[#0B1220] text-white text-xs rounded-xl px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                />
              </div>

              <div className="space-y-1">
                <label className="text-[11px] font-semibold text-slate-300 flex items-center gap-1">
                  <Phone className="w-3 h-3 text-[#C5A46D]" />
                  <span>WhatsApp / Phone *</span>
                </label>
                <input
                  type="tel"
                  required
                  placeholder="0300-1234567"
                  value={regPhone}
                  onChange={(e) => setRegPhone(e.target.value)}
                  className="w-full bg-[#0B1220] text-white text-xs rounded-xl px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-mono"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div className="space-y-1">
                <label className="text-[11px] font-semibold text-slate-300 flex items-center gap-1">
                  <Mail className="w-3 h-3 text-[#C5A46D]" />
                  <span>Account Email *</span>
                </label>
                <input
                  type="email"
                  required
                  placeholder="consultant@agency.pk"
                  value={regEmail}
                  onChange={(e) => setRegEmail(e.target.value)}
                  className="w-full bg-[#0B1220] text-white text-xs rounded-xl px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                />
              </div>

              <div className="space-y-1">
                <label className="text-[11px] font-semibold text-slate-300 flex items-center gap-1">
                  <CreditCard className="w-3 h-3 text-[#C5A46D]" />
                  <span>NADRA CNIC (Masked in ERP)</span>
                </label>
                <input
                  type="text"
                  placeholder="42101-1234567-1"
                  value={regCnic}
                  onChange={(e) => setRegCnic(e.target.value)}
                  className="w-full bg-[#0B1220] text-white text-xs rounded-xl px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-mono"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div className="space-y-1">
                <label className="text-[11px] font-semibold text-slate-300">Agency / Brokerage Firm *</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Al-Madina Real Estate"
                  value={regAgency}
                  onChange={(e) => setRegAgency(e.target.value)}
                  className="w-full bg-[#0B1220] text-white text-xs rounded-xl px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                />
              </div>

              <div className="space-y-1">
                <label className="text-[11px] font-semibold text-slate-300 flex items-center gap-1">
                  <MapPin className="w-3 h-3 text-[#C5A46D]" />
                  <span>Primary Sector Focus</span>
                </label>
                <select
                  value={regSector}
                  onChange={(e) => setRegSector(e.target.value)}
                  className="w-full bg-[#0B1220] text-white text-xs rounded-xl px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none cursor-pointer"
                >
                  <option value="Sector 63">Sector 63 (100ft Boulevard)</option>
                  <option value="Sector 70-D">Sector 70-D (Prime Possession)</option>
                  <option value="Sector 71">Sector 71 (Park View & Villas)</option>
                  <option value="Sector 50">Sector 50 (Commercial Arcade)</option>
                  <option value="Sector 30-A">Sector 30-A (MDA Homes)</option>
                  <option value="Sector 84">Sector 84 (Green Farmhouses)</option>
                  <option value="All Sectors">All Scheme 45 Sectors</option>
                </select>
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div className="space-y-1">
                <label className="text-[11px] font-semibold text-slate-300">Create Password *</label>
                <input
                  type="password"
                  required
                  placeholder="Min 6 characters"
                  value={regPassword}
                  onChange={(e) => setRegPassword(e.target.value)}
                  className="w-full bg-[#0B1220] text-white text-xs rounded-xl px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-mono"
                />
              </div>

              <div className="space-y-1">
                <label className="text-[11px] font-semibold text-slate-300">Confirm Password *</label>
                <input
                  type="password"
                  required
                  placeholder="Repeat password"
                  value={regConfirmPassword}
                  onChange={(e) => setRegConfirmPassword(e.target.value)}
                  className="w-full bg-[#0B1220] text-white text-xs rounded-xl px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-mono"
                />
              </div>
            </div>

            {/* Terms checkbox */}
            <label className="flex items-start gap-2.5 pt-1 text-xs text-slate-300 cursor-pointer select-none">
              <input
                type="checkbox"
                checked={regTermsAgreed}
                onChange={(e) => setRegTermsAgreed(e.target.checked)}
                className="mt-0.5 rounded text-[#C5A46D] focus:ring-[#C5A46D] accent-[#C5A46D]"
              />
              <span className="text-[11px] text-slate-400">
                I agree to the TaisarTownHub Real Estate Code of Conduct and authorize on-ground MDA file verification for all submitted deals.
              </span>
            </label>

            <button
              type="submit"
              disabled={busy}
              className="w-full py-3 px-4 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] active:scale-[0.99] text-[#101C2C] font-bold text-xs transition-all flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-[#C5A46D]/20 disabled:opacity-60"
            >
              <span>{busy ? 'Registering Account…' : 'Register & Enter Portal'}</span>
              <ArrowRight className="w-4 h-4" />
            </button>
          </form>
        )}

        {/* Security / Verification footer */}
        <div className="pt-3 border-t border-slate-800 text-center text-[10px] text-slate-500 space-y-1">
          <p className="flex items-center justify-center gap-1.5">
            <Lock className="w-3 h-3 text-[#C5A46D]" />
            <span>256-bit encrypted Real Estate ERP session. Confidential records protected.</span>
          </p>
        </div>
      </div>
    </div>
  );
};
