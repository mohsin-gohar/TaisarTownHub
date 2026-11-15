/**
 * Pure helpers for the multi-factor sign-in flow (Phase 6).
 *
 * Kept free of React and network concerns so the rules can be unit tested:
 * an authenticator code is exactly six digits, a recovery code is an
 * upper-case `XXXXX-XXXXX` token.
 */

export const MFA_CODE_LENGTH = 6;
export const RECOVERY_CODE_PATTERN = /^[A-Z0-9]{4,8}-[A-Z0-9]{4,8}$/;

/** Strip spaces/dashes the user may paste and upper-case recovery codes. */
export function normalizeMfaCode(raw: string): string {
  return (raw || '').replace(/\s+/g, '');
}

export function normalizeRecoveryCode(raw: string): string {
  return (raw || '').trim().toUpperCase();
}

/** True when the input can be an authenticator (TOTP) code. */
export function isTotpCode(raw: string): boolean {
  return new RegExp(`^\\d{${MFA_CODE_LENGTH}}$`).test(normalizeMfaCode(raw));
}

/** True when the input looks like a recovery code. */
export function isRecoveryCode(raw: string): boolean {
  return RECOVERY_CODE_PATTERN.test(normalizeRecoveryCode(raw));
}

/**
 * The backend accepts either a 6-digit authenticator code or a recovery
 * code; this returns the value that should be sent.
 */
export function mfaPayloadCode(raw: string): string {
  const compact = normalizeMfaCode(raw);
  if (isTotpCode(compact)) return compact;
  return normalizeRecoveryCode(raw);
}

/** Human-readable label for the challenge screen. */
export function mfaChallengeMessage(expiresInSeconds?: number): string {
  if (!expiresInSeconds || expiresInSeconds <= 0) {
    return 'Enter the 6-digit code from your authenticator app, or one of your recovery codes.';
  }
  const minutes = Math.max(1, Math.round(expiresInSeconds / 60));
  return `Enter the 6-digit code from your authenticator app, or one of your recovery codes. This request expires in about ${minutes} minute${minutes === 1 ? '' : 's'}.`;
}

/** Format the one-time recovery codes for copy/paste into a password manager. */
export function formatRecoveryCodes(codes: string[]): string {
  return codes.map((code) => code.trim().toUpperCase()).join('\n');
}
