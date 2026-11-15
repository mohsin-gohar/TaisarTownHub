import React, { useCallback, useEffect, useState } from 'react';
import { KeyRound, ShieldCheck, ShieldOff, Copy, RefreshCw, AlertTriangle } from 'lucide-react';
import { api } from '../../services/api';
import { formatRecoveryCodes, mfaPayloadCode } from '../../domain/mfa';
import { useApp } from '../../context/AppContext';

type LoginRow = {
  id: number;
  ip_address: string | null;
  user_agent: string | null;
  success: boolean;
  mfa_used: boolean;
  suspicious: boolean;
  reason: string | null;
  created_at: string | null;
};

/**
 * Phase 6 — account security: two-factor enrolment and the sign-in trail.
 *
 * Enrolment is a real TOTP handshake: the API returns the shared secret and
 * the single-use recovery codes, the account only becomes protected after a
 * live 6-digit code is confirmed.
 */
export const AdminMfaSettings: React.FC = () => {
  const { showToast } = useApp();
  const [enabled, setEnabled] = useState<boolean | null>(null);
  const [recoveryRemaining, setRecoveryRemaining] = useState(0);
  const [enrolment, setEnrolment] = useState<{ secret: string; otpauthUri: string; recoveryCodes: string[] } | null>(null);
  const [confirmCode, setConfirmCode] = useState('');
  const [disablePassword, setDisablePassword] = useState('');
  const [disableCode, setDisableCode] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [history, setHistory] = useState<LoginRow[]>([]);

  const loadStatus = useCallback(async () => {
    const status = await api.mfaStatus();
    setEnabled(status.enabled);
    setRecoveryRemaining(status.recoveryCodesRemaining);
  }, []);

  const loadHistory = useCallback(async () => {
    setHistory(await api.loginHistory());
  }, []);

  useEffect(() => {
    void loadStatus();
    void loadHistory();
  }, [loadStatus, loadHistory]);

  const handleStart = async () => {
    setBusy(true);
    setError('');
    setNotice('');
    const result = await api.mfaSetup();
    setBusy(false);
    if (!result.success || !result.secret) {
      setError(result.error || 'Two-factor enrolment could not be started.');
      return;
    }
    setEnrolment({
      secret: result.secret,
      otpauthUri: result.otpauthUri || '',
      recoveryCodes: result.recoveryCodes || [],
    });
    setNotice('Add the account to your authenticator app, then confirm with a 6-digit code.');
  };

  const handleEnable = async () => {
    if (!confirmCode.trim()) {
      setError('Enter the 6-digit code shown by your authenticator app.');
      return;
    }
    setBusy(true);
    setError('');
    const result = await api.mfaEnable(mfaPayloadCode(confirmCode));
    setBusy(false);
    if (!result.success) {
      setError(result.error || 'That code is not valid.');
      return;
    }
    setEnrolment(null);
    setConfirmCode('');
    setNotice('Two-factor sign-in is now active on this account.');
    await loadStatus();
    await loadHistory();
  };

  const handleDisable = async () => {
    setBusy(true);
    setError('');
    const result = await api.mfaDisable(disablePassword, mfaPayloadCode(disableCode));
    setBusy(false);
    if (!result.success) {
      setError(result.error || 'Two-factor sign-in could not be removed.');
      return;
    }
    setDisablePassword('');
    setDisableCode('');
    setNotice('Two-factor sign-in has been removed from this account.');
    await loadStatus();
    await loadHistory();
  };

  const copy = async (text: string, label: string) => {
    try {
      await navigator.clipboard.writeText(text);
      showToast('success', `${label} copied to clipboard.`);
    } catch {
      showToast('error', 'Copy is unavailable — select the text and copy it manually.');
    }
  };

  return (
    <div className="bg-[#101C2C] p-6 rounded-2xl border border-slate-800 shadow-xl space-y-5">
      <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
          <h3 className="font-serif-luxury text-lg font-bold text-white flex items-center gap-2">
            <KeyRound className="w-5 h-5 text-[#C5A46D]" />
            Two-Factor Sign-In (MFA)
          </h3>
          <p className="text-xs text-slate-400 mt-1">
            Add a time-based one-time password from any authenticator app. A recovery code is
            issued once and can be used if the device is lost.
          </p>
        </div>
        <span
          className={`px-2.5 py-1 rounded-full text-[10px] font-bold border shrink-0 ${
            enabled
              ? 'bg-emerald-500/15 text-emerald-300 border-emerald-500/40'
              : 'bg-slate-800 text-slate-300 border-slate-700'
          }`}
        >
          {enabled === null ? 'Checking…' : enabled ? 'ACTIVE' : 'NOT ENROLLED'}
        </span>
      </div>

      {notice && (
        <p className="rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-3 py-2 text-[11px] text-emerald-200">
          {notice}
        </p>
      )}
      {error && (
        <p role="alert" className="rounded-xl border border-rose-500/40 bg-rose-500/10 px-3 py-2 text-[11px] text-rose-200">
          {error}
        </p>
      )}

      {!enrolment && !enabled && (
        <button
          type="button"
          onClick={handleStart}
          disabled={busy}
          className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] text-xs font-bold transition cursor-pointer disabled:opacity-60"
        >
          <ShieldCheck className="w-4 h-4" />
          {busy ? 'Preparing…' : 'Enable two-factor sign-in'}
        </button>
      )}

      {enrolment && (
        <div className="space-y-4">
          <div className="rounded-xl border border-slate-700 bg-[#0B1220] p-4 space-y-2">
            <p className="text-[11px] font-semibold text-slate-200">Step 1 — add the account to your app</p>
            <p className="text-[10px] text-slate-400 leading-relaxed">
              Open your authenticator app, choose “Add account → Enter secret manually” and paste the
              secret below (or import the otpauth link).
            </p>
            <div className="flex items-center gap-2">
              <code className="flex-1 px-3 py-2 rounded-lg bg-[#101C2C] border border-slate-700 text-[11px] text-[#F9E7BA] font-mono tracking-wider break-all">
                {enrolment.secret}
              </code>
              <button
                type="button"
                onClick={() => copy(enrolment.secret, 'Secret')}
                className="p-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white cursor-pointer"
                title="Copy secret"
              >
                <Copy className="w-4 h-4" />
              </button>
            </div>
            <details className="text-[10px] text-slate-400">
              <summary className="cursor-pointer hover:text-slate-200">Show otpauth link</summary>
              <code className="block mt-1 px-2 py-1.5 rounded bg-[#101C2C] break-all">{enrolment.otpauthUri}</code>
            </details>
          </div>

          <div className="rounded-xl border border-amber-500/30 bg-amber-500/5 p-4 space-y-2">
            <p className="text-[11px] font-semibold text-amber-200 flex items-center gap-1.5">
              <AlertTriangle className="w-3.5 h-3.5" />
              Step 2 — store your recovery codes (shown only once)
            </p>
            <pre className="text-[11px] font-mono text-[#F9E7BA] whitespace-pre-wrap break-all bg-[#0B1220] border border-slate-700 rounded-lg p-3">
              {formatRecoveryCodes(enrolment.recoveryCodes)}
            </pre>
            <button
              type="button"
              onClick={() => copy(formatRecoveryCodes(enrolment.recoveryCodes), 'Recovery codes')}
              className="inline-flex items-center gap-1.5 text-[11px] text-slate-300 hover:text-white cursor-pointer"
            >
              <Copy className="w-3.5 h-3.5" /> Copy recovery codes
            </button>
          </div>

          <div className="rounded-xl border border-slate-700 bg-[#0B1220] p-4 space-y-2">
            <p className="text-[11px] font-semibold text-slate-200">Step 3 — confirm with a live code</p>
            <input
              type="text"
              inputMode="numeric"
              value={confirmCode}
              onChange={(e) => setConfirmCode(e.target.value)}
              placeholder="123456"
              aria-label="Authenticator code"
              className="w-full bg-[#101C2C] text-white text-sm rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-mono tracking-widest"
            />
            <button
              type="button"
              onClick={handleEnable}
              disabled={busy}
              className="px-4 py-2.5 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] text-xs font-bold transition cursor-pointer disabled:opacity-60"
            >
              {busy ? 'Verifying…' : 'Activate two-factor sign-in'}
            </button>
          </div>
        </div>
      )}

      {enabled && (
        <div className="rounded-xl border border-slate-700 bg-[#0B1220] p-4 space-y-3">
          <p className="text-[11px] text-slate-300">
            Two-factor sign-in is active on this account
            {recoveryRemaining > 0 ? ` — ${recoveryRemaining} recovery code(s) left.` : ' — no recovery codes left.'}
          </p>
          <div className="grid sm:grid-cols-2 gap-2">
            <input
              type="password"
              value={disablePassword}
              onChange={(e) => setDisablePassword(e.target.value)}
              placeholder="Current password"
              aria-label="Current password"
              className="bg-[#101C2C] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
            />
            <input
              type="text"
              inputMode="numeric"
              value={disableCode}
              onChange={(e) => setDisableCode(e.target.value)}
              placeholder="Code or recovery code"
              aria-label="Verification code"
              className="bg-[#101C2C] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-mono"
            />
          </div>
          <button
            type="button"
            onClick={handleDisable}
            disabled={busy}
            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold transition cursor-pointer disabled:opacity-60"
          >
            <ShieldOff className="w-4 h-4" />
            {busy ? 'Working…' : 'Remove two-factor sign-in'}
          </button>
        </div>
      )}

      <div className="space-y-2">
        <div className="flex items-center justify-between">
          <p className="text-[11px] font-semibold text-slate-200">Recent sign-ins</p>
          <button
            type="button"
            onClick={() => void loadHistory()}
            className="inline-flex items-center gap-1 text-[11px] text-slate-400 hover:text-white cursor-pointer"
          >
            <RefreshCw className="w-3.5 h-3.5" /> Refresh
          </button>
        </div>
        {history.length === 0 ? (
          <p className="text-[11px] text-slate-500">No sign-in history recorded yet.</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-[11px]">
              <thead className="text-slate-500">
                <tr>
                  <th className="py-1.5 pr-3 font-semibold">When</th>
                  <th className="py-1.5 pr-3 font-semibold">IP</th>
                  <th className="py-1.5 pr-3 font-semibold">Outcome</th>
                  <th className="py-1.5 font-semibold">Details</th>
                </tr>
              </thead>
              <tbody className="text-slate-300">
                {history.map((row) => (
                  <tr key={row.id} className="border-t border-slate-800">
                    <td className="py-1.5 pr-3 whitespace-nowrap">
                      {row.created_at ? new Date(row.created_at).toLocaleString() : '—'}
                    </td>
                    <td className="py-1.5 pr-3 font-mono whitespace-nowrap">{row.ip_address || '—'}</td>
                    <td className="py-1.5 pr-3">
                      <span
                        className={`px-1.5 py-0.5 rounded text-[10px] font-bold ${
                          row.success ? 'bg-emerald-500/15 text-emerald-300' : 'bg-rose-500/15 text-rose-300'
                        }`}
                      >
                        {row.success ? 'SUCCESS' : 'FAILED'}
                      </span>
                    </td>
                    <td className="py-1.5 text-slate-400">
                      {row.suspicious ? '⚠ New IP/device' : row.reason || '—'}
                      {row.mfa_used ? ' · MFA used' : ''}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
};
