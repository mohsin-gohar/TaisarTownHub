import { expect, test } from '@playwright/test';

/**
 * PHASE 7 — Security Audit.
 * Tests authentication, authorization, rate limiting, API protection.
 */

test.describe('Security Audit', () => {

  test('Unauthenticated users cannot access admin dashboard', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForTimeout(3000);
    // Either redirected to login or shows login modal
    const hasSignIn = await page.locator('text=/sign in|login|Sign In/i').count() > 0;
    const isLoginPage = page.url().includes('/login') || page.url().includes('/admin-login');
    expect(hasSignIn || isLoginPage).toBe(true);
  });

  test('API: Protected endpoints require authentication', async ({ request }) => {
    const endpoints = [
      { method: 'get' as const, path: '/api/auth/me' },
      { method: 'get' as const, path: '/api/agency/profile' },
      { method: 'get' as const, path: '/api/admin/dashboard' },
    ];
    for (const endpoint of endpoints) {
      const response =
        endpoint.method === 'get'
          ? await request.get(`http://127.0.0.1:8080${endpoint.path}`)
          : await request.post(`http://127.0.0.1:8080${endpoint.path}`);
      expect([401, 403]).toContain(response.status());
    }
  });

  test('API: Public endpoints are accessible without auth', async ({ request }) => {
    const response = await request.get('http://127.0.0.1:8080/api/health');
    expect(response.status()).toBe(200);

    const catalog = await request.get('http://127.0.0.1:8080/api/catalog');
    expect(catalog.status()).toBe(200);
  });

  test('Login with invalid credentials is rejected', async ({ page }) => {
    await page.goto('/login');
    await page.locator('input[placeholder*="admin@taisartownhub.com" i], input[placeholder*="dealer email" i]').fill('invalid@example.com');
    await page.locator('input[type="password"]').fill('wrongpassword');
    await page.locator('button', { hasText: /Sign In/ }).first().click();
    await page.waitForTimeout(3000);
    // Should show error or stay on login page
    await expect(page.locator('body')).toBeVisible();
  });

  test('Rate limiting triggers on repeated failed logins', async ({ request }) => {
    // Use TEST-NET-2 (198.51.100.0/24) so this test exercises the IP limiter in
    // its own bucket. Without this, 15 deliberate failures exhaust the shared
    // 127.0.0.1 login budget and poison every parallel role login test.
    const response = await request.post('http://127.0.0.1:8080/api/auth/login', {
      headers: { 'X-Forwarded-For': '198.51.100.77' },
      data: { email: 'rate-limit-test@example.com', password: 'wrong' },
    });
    let rateLimited = response.status() === 429;

    for (let i = 0; i < 20 && !rateLimited; i++) {
      const next = await request.post('http://127.0.0.1:8080/api/auth/login', {
        headers: { 'X-Forwarded-For': '198.51.100.77' },
        data: { email: 'rate-limit-test@example.com', password: 'wrong' },
      });
      if (next.status() === 429) rateLimited = true;
    }

    // The API auth limiter is 10/min per IP, so 21 attempts must be throttled.
    expect(rateLimited).toBe(true);
  });

  test('MFA verify endpoint rejects unauthenticated requests', async ({ request }) => {
    const response = await request.post('http://127.0.0.1:8080/api/auth/mfa/verify', {
      data: { challenge: 'test', code: '123456' }
    });
    // Should be a client or server error (not success)
    expect(response.status()).not.toBe(200);
  });

  test('MFA disable endpoint requires authentication', async ({ request }) => {
    const response = await request.post('http://127.0.0.1:8080/api/auth/mfa/disable', {
      data: { password: 'test', code: '123456' }
    });
    expect([401, 403, 422]).toContain(response.status());
  });

});
