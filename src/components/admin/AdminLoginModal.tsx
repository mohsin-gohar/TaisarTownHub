import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import {
  ArrowRight,
  X,
  Shield,
  Briefcase,
  Mail,
  Lock,
  Phone,
  CreditCard,
  MapPin,
  Eye,
  EyeOff
} from 'lucide-react';

interface AdminLoginModalProps {
  initialTab?: 'signin' | 'register';
}

export const AdminLoginModal: React.FC<AdminLoginModalProps> = ({ initialTab = 'signin' }) => {
  const {
    loginWithCredentials,
    registerDealerAccount,
    setCurrentView,
    currentTheme,
    showToast,
  } = useApp();

  const [tab, setTab] = useState<'signin' | 'register'>(initialTab);
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [emailInput, setEmailInput] = useState('');
  const [busy, setBusy] = useState(false);

  // Registration state
  const [regFullName, setRegFullName] = useState('');
  const [regEmail, setRegEmail] = useState('');
  const [regPhone, setRegPhone] = useState('');
  const [regCnic, setRegCnic] = useState('');
  const [regAgency, setRegAgency] = useState('');
  const [regSector, setRegSector] = useState('Sector 63');
  const [regPassword, setRegPassword] = useState('');
  const [regConfirmPassword, setRegConfirmPassword] = useState('');

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    if (busy) return;
    const email = emailInput.trim();
    if (!email) {
      showToast('error', 'Please enter your account email.');
      return;
    }
    if (!password) {
      showToast('error', 'Please enter your account password.');
      return;
    }
    setBusy(true);
    try {
      const result = await loginWithCredentials(email, password);
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
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-fadeIn overflow-y-auto">
      <div className="relative w-full max-w-lg bg-[#101C2C] border border-[#C5A46D]/40 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-4 my-auto">
        <button
          onClick={() => setCurrentView('home')}
          className="absolute top-4 right-4 text-slate-400 hover:text-white p-1 rounded-lg cursor-pointer"
          title="Close Modal"
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

        {/* Tab Switcher: Sign In vs Register */}
        <div className="grid grid-cols-2 p-1 rounded-xl bg-[#0B1220] border border-slate-800 text-xs">
          <button
            type="button"
            onClick={() => setTab('signin')}
            className={`py-2 rounded-lg font-bold transition-all cursor-pointer ${
              tab === 'signin'
                ? 'bg-[#C5A46D] text-[#101C2C] shadow'
                : 'text-slate-400 hover:text-white'
            }`}
          >
            Portal Sign In
          </button>
          <button
            type="button"
            onClick={() => setTab('register')}
            className={`py-2 rounded-lg font-bold transition-all cursor-pointer ${
              tab === 'register'
                ? 'bg-[#C5A46D] text-[#101C2C] shadow'
                : 'text-slate-400 hover:text-white'
            }`}
          >
            Register as Dealer
          </button>
        </div>

        {/* TAB 1: SIGN IN */}
        {tab === 'signin' && (
          <form onSubmit={handleLogin} className="space-y-4 animate-fadeIn">
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-slate-300 flex items-center gap-1.5">
                <Mail className="w-3.5 h-3.5 text-[#C5A46D]" />
                <span>Account Email</span>
              </label>
              <input
                type="email"
                required
                value={emailInput}
                onChange={(e) => setEmailInput(e.target.value)}
                placeholder="admin@taisartownhub.com (or dealer email)"
                className="w-full bg-[#0B1220] text-white text-xs rounded-xl px-3.5 py-2.5 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
              />
            </div>

            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <label className="text-xs font-semibold text-slate-300 flex items-center gap-1.5">
                  <Lock className="w-3.5 h-3.5 text-[#C5A46D]" />
                  <span>Password</span>
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
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="Enter password"
                className="w-full bg-[#0B1220] text-white text-xs rounded-xl px-3.5 py-2.5 border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-mono"
              />
            </div>

            <button
              type="submit"
              disabled={busy}
              className="w-full py-3 px-4 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold text-xs transition-all flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-[#C5A46D]/20 disabled:opacity-60"
            >
              <span>{busy ? 'Authenticating…' : 'Sign In to Dashboard'}</span>
              <ArrowRight className="w-4 h-4" />
            </button>
          </form>
        )}

        {/* TAB 2: REGISTER */}
        {tab === 'register' && (
          <form onSubmit={handleRegister} className="space-y-3 pt-1 animate-fadeIn">
            <div className="bg-[#0B1220] border border-slate-800 rounded-xl p-3 text-xs text-slate-300">
              <span className="font-bold text-[#C5A46D] block mb-0.5">
                Join Taiser Town Scheme 45 Dealer Network
              </span>
              <p className="text-[10px] text-slate-400">
                Register as an associate consultant to track inventory files, submit buyers, and view commissions.
              </p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              <div>
                <label className="text-[10px] font-semibold text-slate-300 block mb-1">Full Name *</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Asad Siddiqui"
                  value={regFullName}
                  onChange={(e) => setRegFullName(e.target.value)}
                  className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                />
              </div>

              <div>
                <label className="text-[10px] font-semibold text-slate-300 block mb-1">WhatsApp / Phone *</label>
                <input
                  type="tel"
                  required
                  placeholder="0300-1234567"
                  value={regPhone}
                  onChange={(e) => setRegPhone(e.target.value)}
                  className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-mono"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              <div>
                <label className="text-[10px] font-semibold text-slate-300 block mb-1">Email *</label>
                <input
                  type="email"
                  required
                  placeholder="consultant@agency.pk"
                  value={regEmail}
                  onChange={(e) => setRegEmail(e.target.value)}
                  className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                />
              </div>

              <div>
                <label className="text-[10px] font-semibold text-slate-300 block mb-1">NADRA CNIC</label>
                <input
                  type="text"
                  placeholder="42101-1234567-1"
                  value={regCnic}
                  onChange={(e) => setRegCnic(e.target.value)}
                  className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-mono"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              <div>
                <label className="text-[10px] font-semibold text-slate-300 block mb-1">Agency / Brokerage *</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Al-Madina Realty"
                  value={regAgency}
                  onChange={(e) => setRegAgency(e.target.value)}
                  className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                />
              </div>

              <div>
                <label className="text-[10px] font-semibold text-slate-300 block mb-1">Sector Focus</label>
                <select
                  value={regSector}
                  onChange={(e) => setRegSector(e.target.value)}
                  className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none cursor-pointer"
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

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              <div>
                <label className="text-[10px] font-semibold text-slate-300 block mb-1">Password</label>
                <input
                  type="password"
                  placeholder="Min 6 characters"
                  value={regPassword}
                  onChange={(e) => setRegPassword(e.target.value)}
                  className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-mono"
                />
              </div>

              <div>
                <label className="text-[10px] font-semibold text-slate-300 block mb-1">Confirm Password</label>
                <input
                  type="password"
                  placeholder="Repeat password"
                  value={regConfirmPassword}
                  onChange={(e) => setRegConfirmPassword(e.target.value)}
                  className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-mono"
                />
              </div>
            </div>

            <button
              type="submit"
              disabled={busy}
              className="w-full py-2.5 px-4 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold text-xs transition-colors flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-[#C5A46D]/20 disabled:opacity-60"
            >
              <span>{busy ? 'Registering…' : 'Register & Enter Dashboard'}</span>
              <ArrowRight className="w-4 h-4" />
            </button>
          </form>
        )}

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
