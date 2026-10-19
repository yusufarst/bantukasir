import { defineConfig, devices } from '@playwright/test';

// L5 browser tests against the running local environment (TEST_STRATEGY
// section 2). A test is never retried until it passes (TS-08).
export default defineConfig({
    testDir: 'tests/Browser',
    forbidOnly: true,
    retries: 0,
    reporter: [['list']],
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8080',
        trace: 'off',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
