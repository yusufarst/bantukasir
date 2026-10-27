import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { measure } from '../support/measure';
import { contextAt, watch, WIDTHS } from '../support/specimen';

// The frame of UXP-000 on the shell's specimen, with every destination of a
// role (AC-007-06; DESIGN_SYSTEM 7.2; INFORMATION_ARCHITECTURE section 4;
// NAVIGATION_CONTRACTS UXP-000; INTERACTION_INVENTORY UXI-022, UXI-023;
// ADAPTIVE_AND_ACCESSIBILITY UXA-03, UXA-13; suites UX01, UX02; TASK-007 scope
// item 8): the side navigation in the order of each role and of the combined
// account, the icon rail of the till with its tooltips, the compact top bar
// and its drawer, and the sub-navigation, at every width, with axe and the
// measurements. No person signs in: the specimen is a page of the local and
// testing environments, and the application's own pages in the shell are
// shell.spec.ts's.

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

const SHELL_SPECIMEN = '/testing/shell-specimen';

const ORDER = {
    owner: ['Beranda', 'Kasir', 'Penjualan', 'Stok', 'Produk', 'Laporan', 'Pengaturan'],
    ops: ['Stok', 'Produk'],
    cashier: ['Kasir', 'Penjualan', 'Sesi kas'],
    combined: ['Kasir', 'Penjualan', 'Sesi kas', 'Stok', 'Produk'],
} as const;

type Persona = keyof typeof ORDER;

const NAMES: Readonly<Record<Persona, string>> = { owner: 'Hendra', ops: 'Agus', cashier: 'Rina', combined: 'Dewi' };

/** The labels of a navigation; the stock destination carries its badge after its label. */
function labelsOf(labels: readonly string[]): (string | RegExp)[] {
    return labels.map((label) => (label === 'Stok' ? /^Stok/ : label));
}

async function checked(page: Page): Promise<void> {
    const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();

    expect(results.violations.map((violation) => `${violation.id}: ${violation.nodes.map((node) => node.target.join(' ')).join(', ')}`)).toEqual([]);

    const measured = await measure(page);

    expect(measured.overflow.scrollWidth, 'no sideways scrolling of the page').toBeLessThanOrEqual(measured.overflow.innerWidth);
    expect(measured.smallText).toEqual([]);
    expect(measured.smallTargets).toEqual([]);
    expect(measured.texts.filter((text) => text.ratio < (text.large ? 3 : 4.5))).toEqual([]);
}

for (const width of WIDTHS) {
    for (const persona of Object.keys(ORDER) as Persona[]) {
        test(`the frame of the ${persona} at ${width.name}: navigation, account menu, landmarks`, async ({ browser }) => {
            const context = await contextAt(browser, width);
            const page = await context.newPage();
            const watched = await watch(page);
            const labels = ORDER[persona];

            await page.goto(`${SHELL_SPECIMEN}?peran=${persona}`);
            await expect(page.getByRole('main')).toBeVisible();
            await expect(page.getByRole('banner')).toHaveCount(1);
            await expect(page).toHaveTitle('Spesimen kerangka');

            if (width.width < 600) {
                // UXI-022: the menu button opens the drawer, which Esc closes,
                // returning the focus to the button.
                const menu = page.getByRole('button', { name: 'Buka menu' });

                await expect(page.getByRole('navigation', { name: 'Menu utama' })).toHaveCount(0);
                await menu.click();
                const drawer = page.getByRole('dialog', { name: 'Menu utama' });

                await expect(drawer.getByRole('link')).toHaveText(labelsOf(labels));
                await checked(page);
                await page.keyboard.press('Escape');
                await expect(drawer).toBeHidden();
                await expect(menu).toBeFocused();
            } else {
                const navigation = page.getByRole('navigation', { name: 'Menu utama' });

                await expect(navigation.getByRole('link')).toHaveText(labelsOf(labels));
                await expect(navigation.getByRole('link', { name: labels[0] })).toHaveAttribute('aria-current', 'page');
                await expect(page.getByRole('button', { name: 'Buka menu' })).toHaveCount(0);
                await checked(page);
            }

            // UXI-023: the account menu names its user; "Akun saya" and "Keluar".
            const account = page.getByRole('button', { name: `Menu akun ${NAMES[persona]}` });

            await account.click();
            await expect(page.getByRole('menuitem')).toHaveText(['Akun saya', 'Keluar']);
            await page.keyboard.press('Escape');
            await expect(account).toBeFocused();

            expect(watched.problems).toEqual([]);
            await context.close();
        });
    }
}

test('the icon rail of the till names each destination and shows its tooltip', async ({ browser }) => {
    const context = await contextAt(browser, { name: '1366x768', width: 1366, height: 768 });
    const page = await context.newPage();

    await page.goto(`${SHELL_SPECIMEN}?peran=cashier&layout=rail`);
    const navigation = page.getByRole('navigation', { name: 'Menu utama' });

    await expect(navigation.getByRole('link')).toHaveText(['Kasir', 'Penjualan', 'Sesi kas']);
    const till = navigation.getByRole('link', { name: 'Kasir' });

    await till.focus();
    await expect(till.locator('.bk-tip')).toHaveCSS('opacity', '1');
    expect(await till.evaluate((element) => element.getBoundingClientRect().width)).toBeLessThan(76);
    await checked(page);
    await context.close();
});

for (const width of [WIDTHS[0], WIDTHS[6]]) {
    test(`the sub-navigation at ${width?.name ?? ''} is tabs, and a menu at compact width, in the order of each role`, async ({ browser }) => {
        const context = await contextAt(browser, width ?? WIDTHS[0]!);
        const page = await context.newPage();

        for (const [persona, items] of [
            ['owner', ['Stok', 'Riwayat stok', 'Barang masuk', 'Barang keluar', 'Stok awal', 'Insiden rekonsiliasi']],
            ['ops', ['Stok', 'Riwayat stok', 'Barang masuk', 'Barang keluar']],
        ] as const) {
            await page.goto(`${SHELL_SPECIMEN}?peran=${persona}&area=stock`);
            const sub = page.getByRole('navigation', { name: 'Submenu Stok' });

            if ((width?.width ?? 1440) >= 600) {
                await expect(sub.getByRole('link')).toHaveText([...items]);
                await expect(sub.getByRole('link', { name: 'Stok', exact: true })).toHaveAttribute('aria-current', 'page');
            } else {
                await sub.getByRole('button', { name: 'Stok' }).click();
                await expect(page.getByRole('menuitem')).toHaveText([...items]);
                await page.keyboard.press('Escape');
            }

            await checked(page);
        }

        await context.close();
    });
}
