import { defineConfig, devices } from '@playwright/test';
import { SECURE_LOCAL_ORIGIN } from './tests/Browser/support/access';

// The browser steps of the restore rehearsal (TASK-037 scope item 7; BACKUP_RECOVERY RB-02 step 7), which
// scripts/checks/restore-rehearsal.sh runs, each against its own origin: tests/Rehearsal/source.spec.ts
// against the source, the local environment standing for production, and tests/Rehearsal/target.spec.ts
// against the restored target. They are not part of the browser suite of playwright.config.ts. A test is
// never retried until it passes (TS-08).
export default defineConfig({
    testDir: 'tests/Rehearsal',
    forbidOnly: true,
    retries: 0,
    workers: 1,
    reporter: [['list']],
    use: {
        ...devices['Desktop Chrome'],
        ...SECURE_LOCAL_ORIGIN,
        baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8080',
        trace: 'off',
    },
});
