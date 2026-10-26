import { expect, test } from '@playwright/test';

// The L5 step of TASK-001 (EXECUTION_CONTEXT EXC-34, AC-001-08): Playwright opens
// the running application, served by Nginx and PHP-FPM from the local
// environment. No route serves the address / until RN-01 (TASK-007), so the
// application answers its own not-found response. Since TASK-006 the L5 step
// also runs the sign-in path (tests/Browser/access/sign-in.spec.ts).
test('the running application answers through Nginx and PHP-FPM', async ({ page }) => {
    const response = await page.goto('/');

    expect(response).not.toBeNull();
    expect(response?.status()).toBe(404);
    expect(response?.headers()['server']).toBe('nginx');
    expect(response?.headers()['x-powered-by']).toBeUndefined();
    expect(response?.headers()['content-type']).toContain('text/html');
    await expect(page.locator('body')).toBeVisible();
});
