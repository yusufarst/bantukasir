import { defineConfig, devices } from '@playwright/test';
import { SECURE_LOCAL_ORIGIN } from './tests/Browser/support/access';

// L5 browser tests against the running local environment (TEST_STRATEGY
// section 2). A test is never retried until it passes (TS-08).
//
// Every page of the application sends the headers of SEC-HDR-02, among them
// Cross-Origin-Opener-Policy, which a browser honours only in a secure
// context; the signed-in pages also encrypt their history, which needs one.
// Every test therefore runs Chromium's full browser with the one origin of
// the local environment treated as secure (SECURE_LOCAL_ORIGIN; TASK-006
// finding F-4, TASK-007).
//
// The tests that sign in the people of Toko Contoh share four accounts, and
// the browser fixtures give a person a new password and end that person's
// sign-in sessions at the start of each test (tests/Harness/Browser/), so two
// of these tests at once would end each other's sessions: they run one at a
// time, in their own project, while the other tests run beside them (TASK-007).
// A new test that signs in a person belongs in one of these files or folders.
const PEOPLE = ['access/**', 'security/**', 'shell/shell.spec.ts', 'shell/session.spec.ts', 'commands/**', 'configuration/**'];

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
            use: { ...devices['Desktop Chrome'], ...SECURE_LOCAL_ORIGIN },
            testIgnore: PEOPLE,
        },
        {
            name: 'people',
            use: { ...devices['Desktop Chrome'], ...SECURE_LOCAL_ORIGIN },
            testMatch: PEOPLE,
            workers: 1,
        },
    ],
});
