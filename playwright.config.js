// @ts-check
import { defineConfig, devices } from '@playwright/test';

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8000';

/**
 * Sundal end-to-end suite.
 *
 * Runs against a real (dev/staging) server + database — there is no mocking
 * layer. `e2e/fixtures.js` enforces the "never delete/remove/truncate
 * anything" constraint at the network + click level, so this config just
 * wires up auth state per role via a `setup` project dependency.
 */
export default defineConfig({
  testDir: './e2e',
  // Shared dev database + a single shared "Test Workspace" across all
  // role accounts — parallel workers would race on the same records.
  fullyParallel: false,
  workers: 1,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: [['html', { open: 'never' }], ['list']],
  timeout: 30_000,
  expect: { timeout: 8_000 },
  use: {
    baseURL: BASE_URL,
    ignoreHTTPSErrors: true,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    {
      name: 'setup',
      testMatch: /auth\.setup\.js/,
    },
    {
      name: 'chromium',
      testDir: './e2e/tests',
      use: { ...devices['Desktop Chrome'] },
      dependencies: ['setup'],
    },
  ],
});
