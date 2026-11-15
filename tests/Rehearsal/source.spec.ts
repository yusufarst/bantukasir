import { expect as baseExpect, test } from '@playwright/test';
import { ACCESS_TIMEOUT, ACCESS_WAIT, resetPeople, signIn } from '../Browser/support/access';
import { cashSession } from '../Browser/support/cash';

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

// The synthetic data of the restore rehearsal (TASK-037 scope item 7; scripts/checks/restore-rehearsal.sh,
// step 1), on the source, the local environment standing for production: the shop of Toko Contoh with
// its people, a logo of the business profile, which is a stored file of the backup, and a cash session of
// Rina with a Sale in cash and one by bank transfer, closed with its report. Every value is synthetic and
// made at run time.

test.describe.configure({ timeout: ACCESS_TIMEOUT });

/** A PNG of one pixel: a synthetic logo, as tests/Browser/configuration/profile.spec.ts sends it. */
const LOGO = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', 'base64');

test('the source holds a stored file and a closed cash session with its Sales', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    const { Hendra: hendra } = await resetPeople(context, ['Hendra', 'Rina']);

    const opened = await cashSession(context, 'Rina', { open: '100000', sales: ['CASH', 'BANK_TRANSFER'] });
    expect(opened.sales).toHaveLength(2);
    const closed = await cashSession(context, 'Rina');
    expect(closed.session).toBeNull();

    await signIn(page, hendra);
    await page.goto('/settings/business-profile/edit');
    await page.getByLabel(/^Logo/).setInputFiles({ name: 'logo.png', mimeType: 'image/png', buffer: LOGO });
    await page.getByRole('button', { name: 'Simpan sebagai versi baru' }).click();
    const dialog = page.getByRole('dialog', { name: 'Konfirmasi kata sandi' });
    await dialog.getByLabel('Kata sandi', { exact: true }).fill(hendra.password);
    await Promise.all([page.waitForURL((url) => url.pathname === '/settings/business-profile'), dialog.getByRole('button', { name: 'Konfirmasi' }).click()]);

    await context.close();
});
