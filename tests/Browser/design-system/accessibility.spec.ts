import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { measure } from '../support/measure';
import { contextAt, openSpecimen, SPECIMEN, watch, WIDTHS } from '../support/specimen';

// AC-005-04 (TS-25, UX02 owned by TASK-005): the automated accessibility
// checks pass for every component at every width of KEY_SCREENS section 4 and
// 1440 px, the 200 % zoom included; no page scrolls sideways (UXA-12); no text
// is below 12 px (UXA-06); every target is at least 44 x 44 px (UXA-07); and
// every rendered text reaches 4.5:1, or 3:1 when large, against its real
// background (UXA-18, DESIGN_SYSTEM 3.5).

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

async function axe(page: Page): Promise<string[]> {
    const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();

    return results.violations.map((violation) => `${violation.id}: ${violation.nodes.map((node) => node.target.join(' ')).join(', ')}`);
}

for (const width of WIDTHS) {
    test(`the specimen at ${width.name} passes the accessibility checks and the measurements`, async ({ browser }) => {
        const context = await contextAt(browser, width);
        const page = await context.newPage();
        const watched = await watch(page);

        await openSpecimen(page);

        expect(await axe(page)).toEqual([]);

        const measured = await measure(page);

        expect(measured.overflow.scrollWidth, 'no sideways scrolling of the page').toBeLessThanOrEqual(measured.overflow.innerWidth);
        expect(measured.smallText).toEqual([]);
        expect(measured.smallTargets).toEqual([]);
        expect(measured.texts.length).toBeGreaterThan(100);
        expect(measured.texts.filter((text) => text.ratio < (text.large ? 3 : 4.5))).toEqual([]);
        expect(watched.problems).toEqual([]);

        await context.close();
    });
}

for (const width of WIDTHS) {
    test(`the dialogs, the menu, the drawer and the error summary pass the accessibility checks at ${width.name}`, async ({ browser }) => {
        test.setTimeout(120_000);
        const context = await contextAt(browser, width);
        const page = await context.newPage();
        const watched = await watch(page);

        await openSpecimen(page);

        await page.getByTestId('open-review').click();
        await expect(page.getByRole('dialog')).toBeVisible();
        expect(await axe(page)).toEqual([]);
        await page.keyboard.press('Escape');
        await expect(page.getByRole('dialog')).toBeHidden();

        await page.getByRole('button', { name: 'Tindakan lain Pulpen gel hitam', exact: true }).click();
        await expect(page.getByRole('menu')).toBeVisible();
        expect(await axe(page)).toEqual([]);
        await page.keyboard.press('Escape');

        await page.getByTestId('open-drawer').click();
        await expect(page.getByRole('dialog')).toBeVisible();
        expect(await axe(page)).toEqual([]);
        await page.keyboard.press('Escape');

        await page.getByTestId('open-unsaved').click();
        await expect(page.getByRole('dialog', { name: 'Perubahan belum disimpan' })).toBeVisible();
        expect(await axe(page)).toEqual([]);
        await page.keyboard.press('Escape');

        await page.getByTestId('show-errors').click();
        expect(await axe(page)).toEqual([]);

        expect(watched.problems).toEqual([]);
        await context.close();
    });
}

for (const width of WIDTHS) {
    test(`the pre-authentication archetype at ${width.name} passes the accessibility checks and the measurements`, async ({ browser }) => {
        const context = await contextAt(browser, width);
        const page = await context.newPage();
        const watched = await watch(page);

        const response = await page.goto(`${SPECIMEN}?tampilan=masuk`);

        expect(response?.status()).toBe(200);
        await expect(page.getByRole('heading', { level: 1, name: 'Masuk' })).toBeVisible();
        expect(await axe(page)).toEqual([]);

        const measured = await measure(page);

        expect(measured.overflow.scrollWidth).toBeLessThanOrEqual(measured.overflow.innerWidth);
        expect(measured.smallText).toEqual([]);
        expect(measured.smallTargets).toEqual([]);
        expect(measured.texts.filter((text) => text.ratio < (text.large ? 3 : 4.5))).toEqual([]);
        // One centred card of at most 420 px (DESIGN_SYSTEM 7.1, 7.3).
        expect((await page.locator('.bk-auth__card').boundingBox())?.width ?? 0).toBeLessThanOrEqual(420);
        expect(watched.problems).toEqual([]);

        await context.close();
    });
}
