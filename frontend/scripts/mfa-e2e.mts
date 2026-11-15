/**
 * MFA end-to-end journey (Phase 11, journey #5).
 *
 * Proves in a real browser: enrol a second factor in the admin UI, sign out,
 * sign in again (the API now demands a code), complete the challenge with a
 * freshly computed TOTP, reach the dashboard, and finally remove the second
 * factor again so the environment is left as it was found.
 */
import { chromium, type Page } from '@playwright/test';
import { createHmac } from 'node:crypto';

const BASE = process.env.AUDIT_BASE || 'http://127.0.0.1:3000';
const EMAIL = process.env.MFA_EMAIL || 'superadmin@taisartownhub.com';
const PASSWORD = process.env.MFA_PASSWORD || 'Super@Admin2026!';

/** RFC 4648 base32 decode (no padding). */
function base32Decode(secret: string): Buffer {
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  const clean = secret.toUpperCase().replace(/=+$/, '');
  let bits = '';
  for (const char of clean) {
    const index = alphabet.indexOf(char);
    if (index === -1) continue;
    bits += index.toString(2).padStart(5, '0');
  }
  const bytes: number[] = [];
  for (let i = 0; i + 8 <= bits.length; i += 8) {
    bytes.push(parseInt(bits.slice(i, i + 8), 2));
  }
  return Buffer.from(bytes);
}

/** RFC 6238 TOTP (HMAC-SHA1, 6 digits, 30 second step). */
function totp(secret: string, timestamp = Date.now()): string {
  const counter = Math.floor(timestamp / 1000 / 30);
  const buffer = Buffer.alloc(8);
  buffer.writeUInt32BE(Math.floor(counter / 2 ** 32), 0);
  buffer.writeUInt32BE(counter >>> 0, 4);
  const digest = createHmac('sha1', base32Decode(secret)).update(buffer).digest();
  const offset = digest[digest.length - 1] & 0x0f;
  const value =
    ((digest[offset] & 0x7f) << 24) |
    ((digest[offset + 1] & 0xff) << 16) |
    ((digest[offset + 2] & 0xff) << 8) |
    (digest[offset + 3] & 0xff);
  return String(value % 1_000_000).padStart(6, '0');
}

async function signIn(page: Page) {
  // The API throttles sign-ins to 5/minute per IP; the journey signs in
  // several times, so pause long enough to stay inside that window.
  await page.waitForTimeout(13000);
  await page.goto(BASE + '/admin-login', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1500);
  const form = page
    .locator('form')
    .filter({ has: page.locator('input[placeholder="Enter password"]') })
    .first();
  await form.locator('input[type="email"]').fill(EMAIL);
  await form.locator('input[type="password"]').fill(PASSWORD);
  await form.locator('button[type="submit"]').click();
  await page.waitForTimeout(4000);
}

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  page.setDefaultTimeout(12000);
  page.setDefaultNavigationTimeout(20000);
  const errors: string[] = [];
  page.on('pageerror', (e) => errors.push('PAGEERROR ' + String(e).slice(0, 160)));
  page.on('response', async (res) => {
    if (res.url().includes('/auth/mfa/verify')) {
      let bodyText = '';
      try {
        bodyText = (await res.text()).slice(0, 200);
      } catch {
        bodyText = '(body could not be read)';
      }
      console.log('   [network] POST /auth/mfa/verify →', res.status(), bodyText);
    }
  });
  page.on('console', (m) => {
    if (m.type() === 'error') errors.push('CONSOLE ' + m.text().slice(0, 160));
  });

  const results: string[] = [];
  const step = (ok: boolean, label: string) => {
    results.push(`${ok ? 'PASS' : 'FAIL'} ${label}`);
    console.log(`${ok ? 'PASS' : 'FAIL'} ${label}`);
  };
  /** Run a step without letting one failure abort the whole journey. */
  const safe = async (label: string, fn: () => Promise<boolean>) => {
    try {
      step(await fn(), label);
      return true;
    } catch (error) {
      step(false, `${label} — ${String(error).split('\n')[0].slice(0, 160)}`);
      return false;
    }
  };

  let secret = '';

  // ---------- enrol ----------
  await safe('sign-in without MFA reaches the admin dashboard', async () => {
    await signIn(page);
    return new URL(page.url()).pathname === '/admin';
  });

  await safe('MFA enrolment control is visible in Security & Roles', async () => {
    await page.getByRole('button', { name: /^Security & Roles$/ }).first().click();
    await page.waitForTimeout(2000);
    const startButton = page.getByRole('button', { name: /Enable two-factor sign-in/i }).first();
    if (!(await startButton.isVisible())) return false;
    await startButton.click();
    await page.waitForTimeout(2500);
    const secretCode = page.locator('code').filter({ hasText: /^[A-Z2-7]{32}$/ }).first();
    secret = (await secretCode.innerText()).trim();
    return secret.length >= 16;
  });

  await safe('recovery codes are shown once', async () => {
    const recoveryText = await page.locator('pre').first().innerText();
    return recoveryText.split('\n').filter(Boolean).length >= 8;
  });

  await safe('MFA becomes ACTIVE after a valid confirmation code', async () => {
    await page.getByLabel('Authenticator code').fill(totp(secret));
    await page.getByRole('button', { name: /Activate two-factor sign-in/i }).click();
    await page.waitForTimeout(2500);
    return (await page.getByText(/two-factor sign-in is now active/i).count()) > 0;
  });

  // ---------- sign out and back in ----------
  await safe('sign out returns to the public portal', async () => {
    const signOut = page.getByRole('button', { name: /Sign Out/i }).first();
    await signOut.click();
    await page.waitForTimeout(1800);
    return !page.url().includes('/admin');
  });

  await safe('sign-in now asks for the second factor', async () => {
    await signIn(page);
    return (await page.getByText(/Two-factor verification/i).count()) > 0;
  });

  await safe('the MFA prompt exposes an accessible code field', async () =>
    (await page.getByLabel('Two-factor verification code').count()) > 0,
  );

  await safe('a valid code completes the sign-in and opens the dashboard', async () => {
    await page.getByLabel('Two-factor verification code').fill(totp(secret));
    await page.getByRole('button', { name: /Verify & Sign In/i }).click();
    await page.waitForTimeout(6000);
    return new URL(page.url()).pathname === '/admin';
  });

  // ---------- wrong code is rejected ----------
  await safe('an invalid code is rejected with a visible error', async () => {
    const signOut = page.getByRole('button', { name: /Sign Out/i }).first();
    await signOut.click();
    await page.waitForTimeout(1800);
    await signIn(page);
    if ((await page.getByLabel('Two-factor verification code').count()) === 0) {
      console.log('   (no MFA prompt visible — url:', page.url(), ')');
      return false;
    }
    await page.getByLabel('Two-factor verification code').fill('000000');
    await page.getByRole('button', { name: /Verify & Sign In/i }).click();
    // The dev API server is single threaded, so the answer can arrive after a
    // moment — wait for the alert instead of guessing with a fixed sleep.
    const alert = page.locator('[role="alert"]').first();
    const appeared = await alert
      .waitFor({ state: 'visible', timeout: 25000 })
      .then(() => true)
      .catch(() => false);
    const alerts = await page.locator('[role="alert"]').allInnerTexts();
    console.log('   (alerts:', JSON.stringify(alerts), ')');
    return appeared && alerts.some((text) => /not valid/i.test(text));
  });

  // ---------- clean up: remove the second factor ----------
  await safe('the second factor can be removed with password + code (environment restored)', async () => {
    const signOut = page.getByRole('button', { name: /Sign Out/i }).first();
    if (await signOut.isVisible().catch(() => false)) {
      await signOut.click();
      await page.waitForTimeout(1500);
    }
    await signIn(page);
    if ((await page.getByLabel('Two-factor verification code').count()) > 0) {
      await page.getByLabel('Two-factor verification code').fill(totp(secret));
      await page.getByRole('button', { name: /Verify & Sign In/i }).click();
      await page.waitForTimeout(6000);
    }
    await page.getByRole('button', { name: /^Security & Roles$/ }).first().click();
    await page.waitForTimeout(2500);
    await page.getByLabel('Current password').fill(PASSWORD);
    await page.getByLabel('Verification code').fill(totp(secret));
    await page.getByRole('button', { name: /Remove two-factor sign-in/i }).click();
    await page.waitForTimeout(3000);
    return (await page.getByText(/has been removed from this account/i).count()) > 0;
  });

  console.log('\nConsole/page errors:', JSON.stringify(errors.slice(0, 8)));
  const failed = results.filter((r) => r.startsWith('FAIL'));
  console.log(`\n${results.length - failed.length}/${results.length} MFA journey checks passed`);
  await browser.close();
  if (failed.length) process.exitCode = 1;
})();
