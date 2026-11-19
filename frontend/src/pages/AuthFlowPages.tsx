import React, { useEffect, useMemo, useState } from 'react';
import { API_BASE_URL } from '../services/api';

/**
 * G-14 — cold-load auth-flow pages for the backend's token-based mail links:
 *
 *   /reset-password?token=…&email=…   ← POST /api/auth/reset-password
 *   /verify-email?email=…&token=…     ← GET  /api/auth/email/verify
 *
 * They are rendered by the App.tsx bridge INSTEAD of the whole SPA shell
 * (these URLs are never part of the app's navigation state), keep the same
 * dark visual language, and are intentionally unindexed utility pages.
 */

const Shell: React.FC<{ children: React.ReactNode }> = ({ children }) => (
  <div className="min-h-screen bg-[#070B14] text-slate-100 flex items-center justify-center px-4 py-16">
    <main
      className="w-full max-w-md bg-[#07141E] border border-white/10 rounded-2xl shadow-2xl p-8"
      aria-live="polite"
    >
      {children}
    </main>
  </div>
);

const Brand: React.FC = () => (
  <div className="text-center mb-6">
    <p className="text-[#E7C878] font-black tracking-[0.3em] text-xs uppercase">TaisarTownHub</p>
    <h1 className="mt-2 text-2xl font-bold text-white">Scheme 45 · Karachi</h1>
  </div>
);

const Field: React.FC<{
  label: string;
  type?: string;
  value: string;
  onChange: (v: string) => void;
  autoComplete?: string;
}> = ({ label, type = 'text', value, onChange, autoComplete }) => (
  <label className="block mt-4">
    <span className="text-xs font-semibold uppercase tracking-wide text-slate-300">{label}</span>
    <input
      type={type}
      value={value}
      onChange={(e) => onChange(e.target.value)}
      autoComplete={autoComplete}
      required
      minLength={type === 'password' ? 8 : undefined}
      className="mt-1 w-full rounded-lg bg-[#070B14] border border-white/10 px-3 py-2.5 text-sm text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-[#E7C878]"
    />
  </label>
);

const DoneOrError: React.FC<{ ok: boolean; title: string; message: string }> = ({ ok, title, message }) => (
  <Shell>
    <Brand />
    <div
      className={`rounded-xl px-4 py-3 text-sm font-semibold ${ok ? 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/10 text-rose-300 border border-rose-500/30'}`}
      role="status"
    >
      {ok ? '✓ ' : '✕ '}
      {title}
    </div>
    <p className="mt-3 text-sm text-slate-300">{message}</p>
    <a
      href="/login"
      className="mt-6 block text-center rounded-lg bg-[#E7C878] text-[#0B1220] font-bold text-sm px-4 py-2.5 hover:bg-[#d4b67f] transition-colors"
    >
      Go to sign in
    </a>
  </Shell>
);

/* ------------------------------ reset ------------------------------ */

export const ResetPasswordPage: React.FC = () => {
  const params = useMemo(() => new URLSearchParams(window.location.search), []);
  const token = params.get('token') ?? '';
  const email = params.get('email') ?? '';

  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [state, setState] = useState<'form' | 'saving' | 'done' | 'error'>('form');
  const [message, setMessage] = useState('');

  if (!token || !email) {
    return (
      <DoneOrError
        ok={false}
        title="Invalid reset link"
        message="This link is missing its token. Request a new password-reset email from the sign-in page."
      />
    );
  }

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (password.length < 8) {
      setMessage('Password must be at least 8 characters.');
      return;
    }
    if (password !== confirmation) {
      setMessage('Passwords do not match.');
      return;
    }
    setState('saving');
    try {
      const res = await fetch(`${API_BASE_URL}/reset-password`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ token, email, password, password_confirmation: confirmation }),
      });
      const body = await res.json().catch(() => null);
      if (res.ok && body?.success) {
        setState('done');
        setMessage(body.message || 'Your password has been updated.');
      } else {
        setState('error');
        setMessage(body?.message || body?.errors?.token?.[0] || 'This reset link is invalid or has expired.');
      }
    } catch {
      setState('error');
      setMessage('Network error. Please try again.');
    }
  };

  if (state === 'done') {
    return <DoneOrError ok title="Password updated" message={message || 'Please sign in with your new password.'} />;
  }
  if (state === 'error') {
    return <DoneOrError ok={false} title="Reset failed" message={message} />;
  }

  return (
    <Shell>
      <Brand />
      <h2 className="text-lg font-bold text-white">Set a new password</h2>
      <p className="mt-1 text-sm text-slate-400">for {email}</p>
      <form onSubmit={submit} noValidate={false}>
        <Field label="New password" type="password" value={password} onChange={setPassword} autoComplete="new-password" />
        <Field label="Confirm password" type="password" value={confirmation} onChange={setConfirmation} autoComplete="new-password" />
        <button
          type="submit"
          disabled={state === 'saving'}
          className="mt-6 w-full rounded-lg bg-[#E7C878] text-[#0B1220] font-bold text-sm px-4 py-2.5 hover:bg-[#d4b67f] transition-colors disabled:opacity-60"
        >
          {state === 'saving' ? 'Updating…' : 'Update password'}
        </button>
        {message && (
          <p className="mt-3 text-sm text-rose-300" role="alert">
            {message}
          </p>
        )}
      </form>
    </Shell>
  );
};

/* ------------------------------ verify ------------------------------ */

export const VerifyEmailPage: React.FC = () => {
  const params = useMemo(() => new URLSearchParams(window.location.search), []);
  const email = params.get('email') ?? '';
  const token = params.get('token') ?? '';

  const [state, setState] = useState<'loading' | 'done' | 'error'>('loading');
  const [title, setTitle] = useState('');
  const [message, setMessage] = useState('');

  useEffect(() => {
    let cancelled = false;
    (async () => {
      if (!email || !token) {
        setState('error');
        setTitle('Invalid verification link');
        setMessage('This link is missing its token. Sign in and request a new verification email.');
        return;
      }
      try {
        const res = await fetch(`${API_BASE_URL}/auth/email/verify?email=${encodeURIComponent(email)}&token=${encodeURIComponent(token)}`, {
          headers: { Accept: 'application/json' },
        });
        const body = await res.json().catch(() => null);
        if (cancelled) return;
        if (res.ok && body?.success) {
          setState('done');
          setTitle(body.already_verified ? 'Email was already verified' : 'Email verified');
          setMessage(body.message || 'Your email address is confirmed.');
        } else {
          setState('error');
          setTitle('Verification failed');
          setMessage(body?.message || 'This verification link is invalid or has expired.');
        }
      } catch {
        if (!cancelled) {
          setState('error');
          setTitle('Network error');
          setMessage('Could not reach the server. Please try again.');
        }
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [email, token]);

  if (state === 'loading') {
    return (
      <Shell>
        <Brand />
        <p className="text-sm text-slate-400" role="status">
          Verifying your email…
        </p>
      </Shell>
    );
  }

  return <DoneOrError ok={state === 'done'} title={title} message={message} />;
};
