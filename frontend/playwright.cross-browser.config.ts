import { defineConfig, devices } from '@playwright/test';

/**
 * Cross-browser smoke gate (Firefox + WebKit) for public marketplace routes.
 * Additive to the main Chromium suite; does not weaken or replace any test.
 */
const baseURL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:4173';
const basePort = new URL(baseURL).port || (baseURL.startsWith('https:') ? '443' : '80');

export default defineConfig({
  testDir: './e2e',
  timeout: 60_000,
  fullyParallel: false,
  retries: 0,
  reporter: [['list']],
  use: { baseURL, trace: 'retain-on-failure' },
  projects: [
    { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
    { name: 'webkit', use: { ...devices['Desktop Safari'] } },
  ],
  webServer: {
    command: `npm run dev:e2e -- --port ${basePort}`,
    url: baseURL,
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
  },
});
