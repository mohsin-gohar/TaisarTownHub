import React, { useState } from 'react';
import { KeyRound, ShieldCheck } from 'lucide-react';
import { api } from '../../services/api';
import { mfaChallengeMessage, mfaPayloadCode } from '../../domain/mfa';

interface MfaChallengeFormProps {
  /** Short-lived challenge returned by the sign-in request. */
  challenge: string;
  /** Remaining lifetime of the challenge in seconds. */
  expiresIn?: number;
  /** Called once the second factor was accepted (the token is already stored). */
  onVerified: () => void;
  /** Optional way back (e.g. "use a different account"). */
  onCancel?: () => void;
  /** Visual density: the admin modal uses the compact variant. */
  compact?: boolean;
}

/**
 * Second-factor prompt (Phase 6).
 *
 * The backend issues no session token until this step succeeds, so the code
 * is verified server-side against the stored TOTP secret (or a single-use
 * recovery code) before the account is unlocked.
 */
export const MfaChallengeForm: React.FC<MfaChallengeFormProps> = ({
  challenge,
  expiresIn,
  onVerified,
  onCancel,
  compact = false,
}) => {
  const [code, setCode] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (busy) return;

    const value = code.trim();
    if (!value) {
      setError('Enter the verification code from your authenticator app.');
      return;
    }

    setBusy(true);
    setError('');
    const result = await api.verifyMfa(challenge, mfaPayloadCode(value));
    setBusy(false);

    if (!result.success) {
      setError(result.error || 'That verification code is not valid.');
      return;
    }

    onVerified();
  };

  return (
    <form onSubmit={handleSubmit} className={compact ? 'space-y-3 animate-fadeIn' : 'space-y-4 animate-fadeIn'}>
      <div className="flex items-start gap-2.5">
        <span className="mt-0.5 inline-flex w-8 h-8 items-center justify-center rounded-lg bg-[#E7C878]/10 border border-[#E7C878]/30 shrink-0">
          <ShieldCheck className="w-4 h-4 text-[#E7C878]" />
        </span>
        <div>
          <p className="text-xs font-semibold text-white">Two-factor verification</p>
          <p className="text-[11px] text-slate-400 mt-0.5 leading-relaxed">
            {mfaChallengeMessage(expiresIn)}
          </p>
        </div>
      </div>

      <div className="space-y-1.5">
        <label className="text-[11px] font-semibold text-slate-300 flex items-center gap-1.5">
          <KeyRound className="w-3.5 h-3.5 text-[#E7C878]" />
          <span>Authenticator code or recovery code</span>
        </label>
        <input
          type="text"
          inputMode="numeric"
          autoComplete="one-time-code"
          required
          value={code}
          onChange={(e) => setCode(e.target.value)}
          placeholder="123456"
          aria-label="Two-factor verification code"
          className="w-full bg-[#07141E] text-white text-sm rounded-xl px-3.5 py-2.5 border border-slate-700 focus:border-[#E7C878] focus:outline-none font-mono tracking-widest"
        />
      </div>

      {error && (
        <p role="alert" className="rounded-xl border border-rose-500/40 bg-rose-500/10 px-3 py-2 text-[11px] text-rose-200">
          {error}
        </p>
      )}

      <div className="flex items-center gap-2">
        <button
          type="submit"
          disabled={busy}
          className="flex-1 py-2.5 px-4 rounded-xl bg-[#E7C878] hover:bg-[#F3C464] text-[#0B1220] font-bold text-xs transition-all cursor-pointer shadow-lg shadow-[#E7C878]/20 disabled:opacity-60"
        >
          <span>{busy ? 'Verifying…' : 'Verify & Sign In'}</span>
        </button>
        {onCancel && (
          <button
            type="button"
            onClick={onCancel}
            className="px-3 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold transition cursor-pointer"
          >
            Use another account
          </button>
        )}
      </div>
    </form>
  );
};
