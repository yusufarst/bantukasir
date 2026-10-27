import { expect, test } from '@playwright/test';

// The L5 step of TASK-001 (EXECUTION_CONTEXT EXC-34, AC-001-08): Playwright opens
// the running application, served by Nginx and PHP-FPM from the local
// environment. Since TASK-007 the address / is RN-01, which sends a visitor
// without a sign-in session to the sign-in page. Since TASK-006 the L5 step
// also runs the sign-in path (tests/Browser/access/sign-in.spec.ts).
test('the running application answers through Nginx and PHP-FPM', async ({ page }) => {
    const response = await page.goto('/');

    expect(response).not.toBeNull();
    expect(response?.status()).toBe(200);
    expect(new URL(page.url()).pathname).toBe('/login');
    expect(response?.headers()['server']).toBe('nginx');
    expect(response?.headers()['x-powered-by']).toBeUndefined();
    expect(response?.headers()['content-type']).toContain('text/html');
    await expect(page.locator('body')).toBeVisible();
});
