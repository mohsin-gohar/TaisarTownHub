import { defineConfig, devices } from '@playwright/test';

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
    baseURL: 'http://127.0.0.1:3000',
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  webServer: {
    command: 'npm run dev',
    url: 'http://127.0.0.1:3000',
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
  },
});
