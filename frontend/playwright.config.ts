import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:3000';
const basePort = new URL(baseURL).port || (baseURL.startsWith('https:') ? '443' : '80');

/**
 * G-13 — E2E smoke configuration.
 *
 * The dev server (package.json `dev` script) pins Vite to :3000 and is
 * normally already running in this workspace — `reuseExistingServer` reuses
 * it locally; CI starts its own.
 */
export default defineConfig({
  testDir: './e2e',
  timeout: 30_000,
  fullyParallel: true,
  retries: process.env.CI ? 2 : 0,
  reporter: [['list']],
  use: {
    baseURL,
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  webServer: {
    command: `npm run dev -- --port ${basePort}`,
    url: baseURL,
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
  },
});
