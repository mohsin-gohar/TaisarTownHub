import React, { useState, useEffect } from 'react';
import { useApp } from '../../context/AppContext';
import { getPendingMfaChallenge, clearPendingMfaChallenge, setAuthToken } from '../../services/api';
import { api } from '../../services/api';
import { MfaChallengeForm } from '../common/MfaChallengeForm';
import {
  Building2,
  ArrowRight,
  ArrowLeft,
  CheckCircle2,
  Lock,
  Mail,
  Phone,
  Briefcase,
  Eye,
  EyeOff,
  MapPin,
  Check
} from 'lucide-react';

interface AuthPageProps {
  initialTab?: 'signin' | 'register';
}

const GOOGLE_NONCE_KEY = 'taisar.google.oauth.nonce';
const GOOGLE_OAUTH_ENABLED = import.meta.env.VITE_GOOGLE_OAUTH_ENABLED === 'true';

/**
 * Per-tab CSRF binding for the Google OAuth round trip. The backend hashes
 * this value into its one-time state and echoes the hash back on the callback;
 * a callback started in another browser will not match this tab's value.
 */
const createGoogleNonce = (): string => {
  const bytes = new Uint8Array(24);
  if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
    crypto.getRandomValues(bytes);
    return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
  }
  return `${Date.now().toString(36)}${Math.random().toString(36).slice(2)}${Math.random().toString(36).slice(2)}`;
};

const googleErrorMessage = (code: string): string => {
  switch (code) {
    case 'access_denied':
      return 'Google sign-in was cancelled.';
    case 'account_disabled':
      return 'This account is suspended or inactive. Contact support.';
    case 'account_locked':
      return 'This account is temporarily locked. Please try again later.';
    case 'email_unverified':
      return 'Google did not confirm a verified email address for this account.';
    case 'invalid_state':
      return 'Your Google sign-in session expired. Please try again.';
    case 'oauth_failed':
      return 'Google sign-in could not be completed. Please try again.';
    default:
      return 'Google sign-in failed. Please try again.';
  }
};

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
  const [formError, setFormError] = useState('');

  // Sign in state
  const [signInEmail, setSignInEmail] = useState('');
  const [signInPassword, setSignInPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  // Phase 6: second-factor challenge (set when the API asks for a code).
  const [mfaChallenge, setMfaChallenge] = useState<{ challenge: string; expiresIn: number } | null>(null);

  // Register state
  const [regFullName, setRegFullName] = useState('');
  const [regEmail, setRegEmail] = useState('');
  const [regPhone, setRegPhone] = useState('');
  const [officeAddress, setOfficeAddress] = useState('');
  const [regAgency, setRegAgency] = useState('');
  const [regSector, setRegSector] = useState('Sector 63');
  const [regPassword, setRegPassword] = useState('');
  const [regConfirmPassword, setRegConfirmPassword] = useState('');
  const [regTermsAgreed, setRegTermsAgreed] = useState(false);
  
  useEffect(() => {
    setTab(initialTab);
    setFormError('');
  }, [initialTab]);

  // Handle Google OAuth callback parameters.
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const code = params.get('google_code');
    const challenge = params.get('google_challenge');
    const expires = params.get('expires');
    const googleError = params.get('google_error');
    const nonceFromUrl = params.get('google_nonce');

    const expectedNonceHash = (() => {
      try {
        return sessionStorage.getItem(GOOGLE_NONCE_KEY);
      } catch {
        return null;
      }
    })();
    const clearNonce = () => {
      try {
        sessionStorage.removeItem(GOOGLE_NONCE_KEY);
      } catch {
        /* sessionStorage can be unavailable in hardened browsers */
      }
    };
    const clearParams = () => window.history.replaceState({}, '', window.location.pathname);
    const nonceMatches = Boolean(nonceFromUrl && expectedNonceHash && nonceFromUrl === expectedNonceHash);

    if (googleError) {
      clearNonce();
      clearParams();
      setFormError(nonceMatches ? googleErrorMessage(googleError) : googleErrorMessage('invalid_state'));
      return;
    }

    if (!code && !challenge) return;

    // A callback that was not started in this tab is a login-CSRF attempt:
    // never show its MFA form and never exchange its code.
    if (!nonceMatches) {
      clearNonce();
      clearParams();
      setFormError('Google sign-in could not be verified in this browser. Please try again.');
      return;
    }

    clearNonce();

    if (challenge) {
      clearParams();
      setMfaChallenge({ challenge, expiresIn: Number(expires || 300) });
      return;
    }

    if (code) {
      // Remove the one-time code from the address bar before the network call
      // so it cannot leak through history, screenshots or referrers.
      clearParams();
      setBusy(true);
      void api.exchangeGoogleCode(code)
        .then((result: any) => {
          if (result.success && result.token) {
            setAuthToken(result.token);
            setCurrentView('admin');
          } else {
            setFormError(result.error || 'Google sign-in failed. Please try again.');
          }
          setBusy(false);
        })
        .catch(() => {
          setFormError('Google sign-in failed. Please try again.');
          setBusy(false);
        });
    }
  }, [initialTab, setCurrentView]);

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

    setFormError('');
    setBusy(true);
    try {
      const result = await loginWithCredentials(email, signInPassword);
      if (result.success) {
        setCurrentView('admin');
      } else {
        // The API answered with a second-factor challenge instead of an error.
        const pending = getPendingMfaChallenge();
        if (pending?.challenge) {
          setMfaChallenge(pending);
          setFormError('');
        } else {
          setFormError(result.error || 'The request could not be completed. Please try again.');
        }
      }
    } finally {
      setBusy(false);
    }
  };

  const handleMfaVerified = () => {
    clearPendingMfaChallenge();
    setMfaChallenge(null);
    // The token is already stored by the API layer — reload so the app boots
    // the authenticated workspace from the stored session.
    window.location.assign('/admin');
  };

  const handleRegister = async (e: React.FormEvent) => {
    e.preventDefault();
    if (busy) return;

    if (!regTermsAgreed) {
      showToast('error', 'Please accept the dealer code of conduct and verification agreement.');
      return;
    }

    if (regPassword.length < 8) {
      showToast('error', 'Password must be at least 8 characters.');
      return;
    }

    if (regPassword !== regConfirmPassword) {
      showToast('error', 'Passwords do not match.');
      return;
    }

    setFormError('');
    setBusy(true);
    try {
      const result = await registerDealerAccount({
        fullName: regFullName,
        email: regEmail,
        password: regPassword,
        phone: regPhone,
        officeAddress,
        agencyOrBranch: regAgency,
        sectorFocus: regSector,
      });

      if (result.success) {
        setCurrentView('admin');
      } else {
        setFormError(result.error || 'The request could not be completed. Please try again.');
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
          className="inline-flex items-center gap-2 text-xs font-semibold text-slate-300 hover:text-[#E7C878] transition-colors cursor-pointer"
        >
          <ArrowLeft className="w-4 h-4" />
          <span>Return to Public Website</span>
        </button>

        <span className="text-[11px] font-mono text-[#E7C878] uppercase tracking-wider">
          Scheme 45, Karachi
        </span>
      </div>

      {/* Main Luxury Auth Card */}
      <div className="w-full max-w-xl bg-[#0B1220] border border-[#E7C878]/40 rounded-3xl p-6 sm:p-10 shadow-2xl relative overflow-hidden space-y-6">
        {/* Decorative corner accent */}
        <div className="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-[#E7C878]/15 to-transparent rounded-bl-full pointer-events-none" />

        {/* Brand Header */}
        <div className="text-center space-y-2">
          <div className="w-14 h-14 rounded-2xl bg-white/5 flex items-center justify-center mx-auto shadow-lg shadow-[#E7C878]/20 border border-[#F6E3B4]/40 overflow-hidden">
            <img src="/logo.jpg" alt="Logo" className="w-full h-full object-contain" />
          </div>
          <h1 className="font-serif-luxury text-2xl sm:text-3xl font-bold text-white tracking-tight">
            {currentTheme.agencyName}
          </h1>
          <p className="text-xs text-slate-400 max-w-md mx-auto">
            Authorized Agency ERP & Scheme 45 Real Estate Management System
          </p>
        </div>

        {/* Tab Switcher: Sign In vs Register */}
        <div className="grid grid-cols-2 p-1.5 rounded-2xl bg-[#07141E] border border-slate-800">
          <button
            type="button"
            onClick={() => setTab('signin')}
            className={`py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer ${
              tab === 'signin'
                ? 'bg-[#E7C878] text-[#0B1220] shadow-md shadow-[#E7C878]/20'
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
                ? 'bg-[#E7C878] text-[#0B1220] shadow-md shadow-[#E7C878]/20'
                : 'text-slate-400 hover:text-white'
            }`}
          >
            Register an agency
          </button>
        </div>

        {formError && <p role="alert" className="rounded-xl border border-rose-500/40 bg-rose-500/10 p-3 text-sm text-rose-200">{formError}</p>}

        {/* TAB 1: SIGN IN */}
        {tab === 'signin' && mfaChallenge && (
          <MfaChallengeForm
            challenge={mfaChallenge.challenge}
            expiresIn={mfaChallenge.expiresIn}
            onVerified={handleMfaVerified}
            onCancel={() => {
              clearPendingMfaChallenge();
              setMfaChallenge(null);
            }}
          />
        )}

        {tab === 'signin' && !mfaChallenge && (
          <form onSubmit={handleSignIn} className="space-y-4 animate-fadeIn">
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-slate-300 flex items-center gap-1.5">
                <Mail className="w-3.5 h-3.5 text-[#E7C878]" />
                <span>Account Email</span>
              </label>
              <input
                type="email"
                aria-label="Account email"
                autoComplete="username"
                required
                value={signInEmail}
                onChange={(e) => setSignInEmail(e.target.value)}
                placeholder="admin@taisartownhub.com (or dealer email)"
                className="w-full bg-[#07141E] text-white text-xs sm:text-sm rounded-xl px-4 py-3 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
              />
            </div>

            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <label className="text-xs font-semibold text-slate-300 flex items-center gap-1.5">
                  <Lock className="w-3.5 h-3.5 text-[#E7C878]" />
                  <span>Account Password</span>
                </label>
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="text-[11px] text-slate-400 hover:text-[#E7C878] flex items-center gap-1 cursor-pointer"
                >
                  {showPassword ? <EyeOff className="w-3.5 h-3.5" /> : <Eye className="w-3.5 h-3.5" />}
                  <span>{showPassword ? 'Hide' : 'Show'}</span>
                </button>
              </div>
              <input
                type={showPassword ? 'text' : 'password'}
                aria-label="Account password"
                autoComplete="current-password"
                required
                value={signInPassword}
                onChange={(e) => setSignInPassword(e.target.value)}
                placeholder="Enter password"
                className="w-full bg-[#07141E] text-white text-xs sm:text-sm rounded-xl px-4 py-3 border border-slate-700 focus:border-[#E7C878] focus:outline-none font-mono"
              />
            </div>

            <button
              type="submit"
              disabled={busy}
              className="w-full py-3.5 px-4 rounded-xl bg-[#E7C878] hover:bg-[#F3C464] active:scale-[0.99] text-[#0B1220] font-bold text-xs sm:text-sm transition-all flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-[#E7C878]/20 disabled:opacity-60"
            >
              <span>{busy ? 'Authenticating…' : 'Sign In to Agency Dashboard'}</span>
              <ArrowRight className="w-4 h-4" />
            </button>

            {GOOGLE_OAUTH_ENABLED && (
              <>
              <div className="flex items-center gap-3">
                <div className="h-px flex-1 bg-slate-800" />
                <span className="text-[10px] uppercase tracking-wider text-slate-500">or</span>
                <div className="h-px flex-1 bg-slate-800" />
              </div>
  
              <button
                type="button"
                disabled={busy}
                onClick={() => {
                  setFormError('');
                  setBusy(true);
                  const nonce = createGoogleNonce();
                  void api.getGoogleRedirectUrl(nonce)
                    .then((result: any) => {
                      if (result.success && result.url && result.nonce_hash) {
                        try {
                          sessionStorage.setItem(GOOGLE_NONCE_KEY, String(result.nonce_hash));
                        } catch {
                          setFormError('Your browser does not allow the secure Google sign-in flow. Enable site storage and try again.');
                          setBusy(false);
                          return;
                        }
                        window.location.href = result.url;
                      } else {
                        setFormError(result.error || 'Google Sign-In is not available.');
                        setBusy(false);
                      }
                    })
                    .catch(() => {
                      setFormError('Cannot reach the API. Please try again.');
                      setBusy(false);
                    });
                }}
                className="w-full py-3 px-4 rounded-xl bg-white hover:bg-slate-100 active:scale-[0.99] text-[#1f1f1f] font-semibold text-xs sm:text-sm transition-all flex items-center justify-center gap-2 cursor-pointer border border-slate-300 disabled:opacity-60"
              >
                <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
                  <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z" />
                  <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z" />
                  <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z" />
                  <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z" />
                  <path fill="none" d="M0 0h48v48H0z" />
                </svg>
                <span>Continue with Google</span>
              </button>
  
              </>
            )}          </form>
        )}

        {/* TAB 2: REGISTER AS DEALER */}
        {tab === 'register' && (
          <form onSubmit={handleRegister} className="space-y-4 animate-fadeIn">
            <div className="bg-[#07141E] border border-slate-800 rounded-2xl p-3.5 text-xs text-slate-300 space-y-1">
              <span className="font-bold text-[#E7C878] flex items-center gap-1.5">
                <Briefcase className="w-3.5 h-3.5" />
                Agency registration & approval
              </span>
              <p className="text-[11px] text-slate-400">
                Apply for your agency workspace. The platform owner must approve your agency before you can upload documents, manage listings or access the dashboard.
              </p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div className="space-y-1">
                <label className="text-[11px] font-semibold text-slate-300">Full Name *</label>
                <input
                  type="text"
                  required
                  aria-label="Full name"
                  autoComplete="name"
                  minLength={3}
                  maxLength={120}
                  placeholder="e.g. Asad Siddiqui"
                  value={regFullName}
                  onChange={(e) => setRegFullName(e.target.value)}
                  className="w-full bg-[#07141E] text-white text-xs rounded-xl px-3 py-2 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                />
              </div>

              <div className="space-y-1">
                <label className="text-[11px] font-semibold text-slate-300 flex items-center gap-1">
                  <Phone className="w-3 h-3 text-[#E7C878]" />
                  <span>WhatsApp / Phone *</span>
                </label>
                <input
                  type="tel"
                  required
                  aria-label="WhatsApp or phone"
                  autoComplete="tel"
                  maxLength={30}
                  placeholder="0300-1234567"
                  value={regPhone}
                  onChange={(e) => setRegPhone(e.target.value)}
                  className="w-full bg-[#07141E] text-white text-xs rounded-xl px-3 py-2 border border-slate-700 focus:border-[#E7C878] focus:outline-none font-mono"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div className="space-y-1">
                <label className="text-[11px] font-semibold text-slate-300 flex items-center gap-1">
                  <Mail className="w-3 h-3 text-[#E7C878]" />
                  <span>Account Email *</span>
                </label>
                <input
                  type="email"
                  required
                  aria-label="Account email"
                  autoComplete="email"
                  placeholder="consultant@agency.pk"
                  value={regEmail}
                  onChange={(e) => setRegEmail(e.target.value)}
                  className="w-full bg-[#07141E] text-white text-xs rounded-xl px-3 py-2 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                />
              </div>

              <div className="space-y-1">
                <label htmlFor="agency-office-address" className="text-[11px] font-semibold text-slate-300">Office address (optional)</label>
                <input
                  id="agency-office-address"
                  type="text"
                  autoComplete="street-address"
                  maxLength={255}
                  placeholder="Office, street and sector"
                  value={officeAddress}
                  onChange={(e) => setOfficeAddress(e.target.value)}
                  className="w-full bg-[#07141E] text-white text-xs rounded-xl px-3 py-2 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div className="space-y-1">
                <label className="text-[11px] font-semibold text-slate-300">Agency / Brokerage Firm *</label>
                <input
                  type="text"
                  required
                  aria-label="Agency or brokerage firm"
                  autoComplete="organization"
                  minLength={3}
                  maxLength={120}
                  placeholder="e.g. Al-Madina Real Estate"
                  value={regAgency}
                  onChange={(e) => setRegAgency(e.target.value)}
                  className="w-full bg-[#07141E] text-white text-xs rounded-xl px-3 py-2 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                />
              </div>

              <div className="space-y-1">
                <label className="text-[11px] font-semibold text-slate-300 flex items-center gap-1">
                  <MapPin className="w-3 h-3 text-[#E7C878]" />
                  <span>Primary Sector Focus</span>
                </label>
                <select
                  aria-label="Primary sector focus"
                  value={regSector}
                  onChange={(e) => setRegSector(e.target.value)}
                  className="w-full bg-[#07141E] text-white text-xs rounded-xl px-3 py-2 border border-slate-700 focus:border-[#E7C878] focus:outline-none cursor-pointer"
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
                  aria-label="Create password"
                  autoComplete="new-password"
                  minLength={8}
                  placeholder="Min 8 characters"
                  value={regPassword}
                  onChange={(e) => setRegPassword(e.target.value)}
                  className="w-full bg-[#07141E] text-white text-xs rounded-xl px-3 py-2 border border-slate-700 focus:border-[#E7C878] focus:outline-none font-mono"
                />
              </div>

              <div className="space-y-1">
                <label className="text-[11px] font-semibold text-slate-300">Confirm Password *</label>
                <input
                  type="password"
                  required
                  aria-label="Confirm password"
                  autoComplete="new-password"
                  minLength={8}
                  placeholder="Repeat password"
                  value={regConfirmPassword}
                  onChange={(e) => setRegConfirmPassword(e.target.value)}
                  className="w-full bg-[#07141E] text-white text-xs rounded-xl px-3 py-2 border border-slate-700 focus:border-[#E7C878] focus:outline-none font-mono"
                />
              </div>
            </div>

            {/* Terms checkbox */}
            <label className="flex items-start gap-2.5 pt-1 text-xs text-slate-300 cursor-pointer select-none">
              <input
                type="checkbox"
                checked={regTermsAgreed}
                onChange={(e) => setRegTermsAgreed(e.target.checked)}
                className="mt-0.5 rounded text-[#E7C878] focus:ring-[#E7C878] accent-[#E7C878]"
              />
              <span className="text-[11px] text-slate-400">
                I agree to the TaisarTownHub Real Estate Code of Conduct and authorize on-ground MDA file verification for all submitted deals.
              </span>
            </label>

            <button
              type="submit"
              disabled={busy}
              className="w-full py-3 px-4 rounded-xl bg-[#E7C878] hover:bg-[#F3C464] active:scale-[0.99] text-[#0B1220] font-bold text-xs transition-all flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-[#E7C878]/20 disabled:opacity-60"
            >
              <span>{busy ? 'Submitting application…' : 'Submit agency application'}</span>
              <ArrowRight className="w-4 h-4" />
            </button>
          </form>
        )}

        {/* Security / Verification footer */}
        <div className="pt-3 border-t border-slate-800 text-center text-[10px] text-slate-500 space-y-1">
          <p className="flex items-center justify-center gap-1.5">
            <Lock className="w-3 h-3 text-[#E7C878]" />
            <span>Private workspace access requires an approved agency and an authorized account.</span>
          </p>
        </div>
      </div>
    </div>
  );
};
