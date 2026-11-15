/**
 * Password-reset mail flow, end to end.
 *
 * registers a probe account -> requests a reset -> waits for the SMTP sink to
 * capture the message -> extracts the real token from the delivered mail ->
 * resets the password with it -> signs in with the new password.
 *
 * Requires the mail sink (scripts/mail-sink.mts) to be running.
 */
import { readdirSync, readFileSync } from 'node:fs';
import path from 'node:path';

const API = process.env.PROBE_API || 'http://127.0.0.1:8090/api';
const MAIL_DIR = process.env.MAIL_DIR || path.join(process.env.LOCALAPPDATA || '', 'Temp', 'opencode', 'th-mail');
const stamp = Date.now();

async function call(path_: string, options: any = {}) {
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (options.body) headers['Content-Type'] = 'application/json';
  const res = await fetch(`${API}${path_}`, {
    method: options.method || 'GET',
    headers,
    body: options.body ? JSON.stringify(options.body) : undefined,
  });
  const text = await res.text();
  let json: any = null;
  try {
    json = JSON.parse(text);
  } catch {
    json = null;
  }
  return { status: res.status, json, text };
}

async function callWithBackoff(path_: string, options: any = {}, attempts = 4) {
  let response = await call(path_, options);
  for (let i = 0; i < attempts && response.status === 429; i++) {
    console.log(`   (429 on ${path_} — waiting for the throttle window)`);
    await new Promise((r) => setTimeout(r, 22000));
    response = await call(path_, options);
  }
  return response;
}

const results: Array<{ check: string; ok: boolean; evidence: string }> = [];
function record(check: string, ok: boolean, evidence: string) {
  results.push({ check, ok, evidence });
  console.log(`${ok ? 'PASS' : 'FAIL'} ${check} — ${evidence}`);
}

/**
 * The mail body is quoted-printable encoded: "=" becomes "=3D" and a soft
 * line break is a trailing "=". Decode before looking for the token.
 */
function decodeQuotedPrintable(raw: string): string {
  return raw
    .replace(/=\r?\n/g, '')
    .replace(/=([0-9A-Fa-f]{2})/g, (_match, hex: string) => String.fromCharCode(parseInt(hex, 16)));
}

(async () => {
  const email = `mailflow.${stamp}@audit.test`;
  const password = 'MailFlow123!';
  const newPassword = 'MailFlowRotated123!';

  const before = readdirSync(MAIL_DIR).length;

  const reg = await callWithBackoff('/agency/register', {
    method: 'POST',
    body: {
      name: `Mail Flow Probe ${stamp}`,
      contactName: 'Mail Flow Owner',
      email,
      password,
      phone: '03001234567',
    },
  });
  record('probe account registered', reg.status === 201 || reg.status === 200, `status=${reg.status}`);

  const forgot = await callWithBackoff('/auth/forgot-password', {
    method: 'POST',
    body: { email },
  });
  record(
    'reset requested (enumeration-safe response)',
    forgot.status === 200 && /exists for that email/i.test(forgot.text),
    `status=${forgot.status}`,
  );

  // Wait for the queued mail to reach the sink.
  let token = '';
  let attempts = 0;
  while (attempts < 20 && !token) {
    await new Promise((r) => setTimeout(r, 1500));
    attempts += 1;
    const files = readdirSync(MAIL_DIR).filter((f) => f.endsWith('.eml')).sort();
    for (const file of files.slice(before)) {
      const raw = readFileSync(path.join(MAIL_DIR, file), 'utf8');
      if (!raw.includes(email)) continue;
      const decoded = decodeQuotedPrintable(raw);
      const match = decoded.match(/reset-password\?token=([A-Za-z0-9]+)/);
      if (match) token = match[1];
    }
  }
  record('SMTP delivered the reset mail (captured by the sink)', Boolean(token), token ? `token captured after ${attempts} poll(s)` : 'no message captured');

  if (token) {
    const reset = await call('/auth/reset-password', {
      method: 'POST',
      body: { email, token, password: newPassword, password_confirmation: newPassword },
    });
    record('password reset with the delivered token', reset.status === 200, `status=${reset.status} ${reset.text.slice(0, 100)}`);

    const login = await callWithBackoff('/auth/login', {
      method: 'POST',
      body: { email, password: newPassword },
    });
    record(
      'sign-in with the rotated password issues a token',
      login.status === 200 && Boolean(login.json?.token),
      `status=${login.status} token=${Boolean(login.json?.token)}`,
    );

    const oldLogin = await callWithBackoff('/auth/login', {
      method: 'POST',
      body: { email, password },
    });
    record(
      'the old password no longer works',
      oldLogin.status === 422,
      `status=${oldLogin.status}`,
    );
  }

  const failed = results.filter((r) => !r.ok);
  console.log(`\n${results.length - failed.length}/${results.length} mail-flow checks passed`);
  if (failed.length) process.exitCode = 1;
})();
