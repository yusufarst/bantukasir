import { expect as baseExpect, test } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, newPassword, signIn } from '../Browser/support/access';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// The restored target renders (TASK-037 scope item 7; BACKUP_RECOVERY RB-02 step 7;
// scripts/checks/restore-rehearsal.sh, step 10): through the Owner recovery link that OP-02 issued in the
// drill copy, the Owner sets a new password, signs in and opens one recent receipt and one session report
// of the restored data. The rehearsal passes the link, the Owner's email and the newest Sale and closed
// session that the restored database holds; the link is never printed.

test.describe.configure({ timeout: ACCESS_TIMEOUT });

function given(name: string): string {
    const value = process.env[name] ?? '';

    expect(value, `the rehearsal passes ${name}`).not.toBe('');

    return value;
}

test('the restored target renders a receipt and a session report through the Owner recovery link', async ({ browser }) => {
    const link = given('REHEARSAL_LINK');
    const email = given('REHEARSAL_OWNER_EMAIL');
    const sale = { id: given('REHEARSAL_SALE_ID'), number: given('REHEARSAL_SALE_NUMBER') };
    const session = { id: given('REHEARSAL_SESSION_ID'), number: given('REHEARSAL_SESSION_NUMBER') };
    expect(link).toMatch(/\/set-password#u=[0-9]+&t=/);

    const context = await browser.newContext();
    const page = await context.newPage();
    const password = newPassword();

    await page.goto(link);
    await page.getByLabel('Kata sandi baru', { exact: true }).fill(password);
    await page.getByLabel('Ulangi kata sandi baru', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Simpan kata sandi' }).click();
    await expect(page.getByRole('status')).toContainText('Kata sandi tersimpan.');

    await signIn(page, { id: 0, email, password });

    await page.goto(`/sales/${sale.id}/receipt`);
    await expect(page.getByText(sale.number).first()).toBeVisible();

    await page.goto(`/cash-sessions/${session.id}`);
    await expect(page.getByText(session.number).first()).toBeVisible();

    await context.close();
});
