import { expect, test, type Locator, type Page } from '@playwright/test';
import { focusRings, measure } from '../support/measure';
import { ACCENTS, contextAt, openSpecimen, SPECIMEN, watch, WIDTHS } from '../support/specimen';

// The sizes and states of DESIGN_SYSTEM sections 8 and 9 (AC-005-02,
// AC-005-03), the content security policy and the fonts (AC-005-05,
// AC-005-06), the accent set with its rendered contrast and focus rings
// (AC-005-04), reduced motion (UXA-20) and the print base (UXA-23).

async function box(locator: Locator): Promise<{ width: number; height: number }> {
    const measured = await locator.boundingBox();

    if (measured === null) {
        throw new Error('The element has no box.');
    }

    return { width: Math.round(measured.width), height: Math.round(measured.height) };
}

async function style(locator: Locator, property: string): Promise<string> {
    return locator.evaluate((element, name) => getComputedStyle(element).getPropertyValue(name), property);
}

test.describe('sizes and anatomy (DESIGN_SYSTEM section 8)', () => {
    test.beforeEach(async ({ page }) => {
        await page.setViewportSize({ width: 1366, height: 768 });
        await openSpecimen(page);
    });

    test('buttons are 44, 48 and 56 px high, the final action 64 px across its panel, icon buttons 44 x 44 (8.1, 8.2)', async ({ page }) => {
        expect((await box(page.getByTestId('button-md'))).height).toBe(44);
        expect((await box(page.getByTestId('button-lg'))).height).toBe(48);
        expect((await box(page.getByTestId('button-xl'))).height).toBe(56);
        expect(await box(page.getByTestId('open-drawer'))).toEqual({ width: 44, height: 44 });

        const final = page.getByTestId('final-action');
        const panel = page.locator('.bk-final');

        expect((await box(final)).height).toBe(64);
        expect((await box(final)).width).toBe((await box(panel)).width);
        expect(await style(final, 'font-size')).toBe('18px');
        expect(await style(final, 'border-top-left-radius')).toBe('10px');
        // At least 24 px from the controls before it (UXA-07).
        expect(parseFloat(await style(panel, 'padding-top'))).toBeGreaterThanOrEqual(24);
    });

    test('a field is 44 px with its 3.55:1 boundary, the label above and the hint below (8.3)', async ({ page }) => {
        const control = page.getByTestId('field-product').locator('xpath=..');

        expect((await box(control)).height).toBe(44);
        expect(await style(control, 'border-top-color')).toBe('rgb(133, 136, 144)');
        await expect(page.getByLabel('Nama produk')).toHaveAccessibleDescription('Contoh: Pulpen gel hitam 0,5 mm');
        await expect(page.getByText('(opsional)')).toBeVisible();
    });

    test('an amount field groups thousands as typed and keeps the value exact (8.3)', async ({ page }) => {
        const amount = page.getByTestId('field-amount');

        await expect(amount).toHaveValue('200.000');
        await amount.fill('');
        await amount.pressSequentially('1234567');
        await expect(amount).toHaveValue('1.234.567');
        await expect(amount).toHaveAttribute('inputmode', 'numeric');
    });

    test('choices: the segmented control is 56 px at its xl size, a chip 44 px, a pill 24 px (8.4, 8.5)', async ({ page }) => {
        expect((await box(page.getByRole('radio', { name: 'Tunai (Alt+1)' }))).height).toBe(56);
        expect((await box(page.getByRole('button', { name: 'Habis', exact: true }))).height).toBe(44);
        expect((await box(page.locator('.bk-pill').first())).height).toBe(24);
    });

    test('a status is a word with a dot or an icon, never colour alone (8.5, UXA-18)', async ({ page }) => {
        const statuses = page.getByTestId('specimen-status').locator('.bk-status');

        for (const status of await statuses.all()) {
            expect((await status.innerText()).trim()).not.toBe('');
            expect(await status.locator('.bk-status__dot, svg').count()).toBe(1);
        }

        await expect(page.getByTestId('specimen-status').locator('.bk-status[data-tone="danger"] svg')).toHaveAttribute('data-icon', 'danger');
        await expect(page.getByTestId('specimen-status').locator('.bk-status[data-tone="warning"] svg').first()).toHaveAttribute('data-icon', 'warning');
    });

    test('cards are white with a 10 px radius and 20 px padding; dialogs have a 12 px radius (8.10, 8.8)', async ({ page }) => {
        const card = page.locator('.bk-card.bk-section').first();

        expect(await style(card, 'border-top-left-radius')).toBe('10px');
        expect(await style(card, 'padding-top')).toBe('20px');
        expect(await style(card, 'background-color')).toBe('rgb(255, 255, 255)');

        await page.getByTestId('open-review').click();
        expect(await style(page.getByRole('dialog'), 'border-top-left-radius')).toBe('12px');
    });

    test('a table has header cells with scope, right-aligned numbers, and becomes labelled cards below 640 px of container (8.11)', async ({ page }) => {
        const table = page.getByRole('table', { name: 'Stok produk' });

        await expect(table.locator('thead th[scope="col"]')).toHaveCount(6);
        await expect(table.locator('tbody th[scope="row"]')).toHaveCount(3);
        expect(await style(table.locator('tbody td.is-num').first(), 'text-align')).toBe('right');
        await expect(page.getByRole('button', { name: 'Tindakan lain Pulpen gel hitam 0,5 mm' })).toBeVisible();

        await page.setViewportSize({ width: 390, height: 844 });
        await expect(table.locator('thead')).toHaveCSS('position', 'absolute');
        await expect(table.locator('tbody tr').first()).toHaveCSS('display', 'block');
        await expect(table.locator('tbody .bk-table__label').first()).toBeVisible();
    });

    test('a wide table scrolls inside its own container with its first column kept visible (UXA-12)', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });

        const host = page.getByRole('region', { name: 'Riwayat stok' });

        await expect(host).toHaveCSS('overflow-x', 'auto');
        expect(await host.evaluate((element) => element.scrollWidth > element.clientWidth)).toBe(true);
        await expect(host.locator('tbody th').first()).toHaveCSS('position', 'sticky');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });

    test('link rows are at least 56 px (8.12) and the steps mark the current one by aria-current and weight (8.15)', async ({ page }) => {
        expect((await box(page.locator('.bk-row').first())).height).toBeGreaterThanOrEqual(56);

        const current = page.getByRole('list', { name: 'Langkah tutup sesi kas' }).locator('li[aria-current="step"]');

        await expect(current).toHaveText(/Hitung uang/);
        await expect(current).toHaveCSS('font-weight', '600');
    });

    test('receipt paper is black on white at 58 mm, 80 mm and A4, with the copy marker boxed in bold (8.16)', async ({ page }) => {
        const paper = page.getByRole('article', { name: 'Struk' });

        await expect(paper).toHaveCSS('background-color', 'rgb(255, 255, 255)');
        await expect(paper).toHaveCSS('color', 'rgb(0, 0, 0)');
        expect((await box(paper)).width).toBe(219);

        const marker = paper.getByText('Salinan');

        await expect(marker).toHaveCSS('font-weight', '700');
        // The 1.5 px box of the stylesheet; Chromium draws it on whole device pixels.
        await expect(marker).toHaveCSS('border-top-style', 'solid');
        expect(parseFloat(await style(marker, 'border-top-width'))).toBeGreaterThanOrEqual(1);

        await page.getByRole('radio', { name: '80 mm' }).click();
        expect((await box(paper)).width).toBe(302);
        await page.getByRole('radio', { name: 'A4' }).click();
        expect((await box(paper)).width).toBe(794);
        await expect(paper.getByRole('heading', { name: 'Nota Penjualan' })).toBeVisible();

        // A long name wraps in full, never cut (UXA-15).
        const long = paper.getByText('Buku tulis bergaris 58 lembar sampul plastik bening tebal isi ulang');

        await expect(long).toHaveCSS('overflow-wrap', 'anywhere');
        expect(await long.evaluate((element) => element.scrollWidth <= element.clientWidth)).toBe(true);
    });

    test('the cart is a labelled region and "Bayar" is fixed at the bottom below 760 px of container width (8.14, UXA-25)', async ({ page }) => {
        await expect(page.getByRole('region', { name: 'Keranjang' })).toBeVisible();
        await expect(page.locator('.bk-cart__foot')).toHaveCSS('position', 'static');

        await page.setViewportSize({ width: 390, height: 844 });
        await expect(page.locator('.bk-cart__foot')).toHaveCSS('position', 'fixed');
    });
});

test.describe('states never show zero, blank or "Normal" for an unknown, failed or stale value (AC-005-03)', () => {
    test.beforeEach(async ({ page }) => {
        await page.setViewportSize({ width: 1366, height: 768 });
        await openSpecimen(page);
    });

    test('each figure shows its value or its state in words', async ({ page }) => {
        const figures = page.locator('.bk-metric');
        const values = await figures.locator('.bk-metric__value').allInnerTexts();

        expect(values[0]).toBe('Rp1.275.500');
        // Loading: a skeleton and, for assistive technology, "Memuat data"; no number.
        expect(values[1]).toBe('Memuat data');
        await expect(figures.nth(1).locator('.bk-skel')).toBeVisible();
        expect(values[2]).toBe('Gagal memuat');
        expect(values[3]).toBe('Rp310.000 Data belum diperbarui');
        expect(values[4]).toBe('Biaya perlu dilengkapi pemilik');

        for (const value of values.slice(1)) {
            expect(value).not.toMatch(/(^|\s)Rp0(\s|$)|^0$|^\s*$|Normal/);
        }
    });

    test('an UNKNOWN cost in a table and in a summary is its words, never Rp0 or blank (UXS-24)', async ({ page }) => {
        const row = page.getByRole('table', { name: 'Stok produk' }).getByRole('row').nth(2);

        await expect(row).toContainText('Biaya perlu dilengkapi pemilik');
        await expect(row).not.toContainText('Rp0');
        await expect(page.getByTestId('specimen-states').locator('.bk-kv')).toContainText('Biaya perlu dilengkapi pemilik');
    });

    test('the marks of UXS-24, UXS-26, UXS-32 and UXS-43 are words', async ({ page }) => {
        await expect(page.getByTestId('marks')).toHaveText(/Biaya perlu dilengkapi pemilik\s*Dikoreksi Pemilik\s*Nonaktif\s*Belum Terverifikasi/);
        await expect(page.getByTestId('marks').locator('[data-state="unverified"]')).not.toHaveAttribute('data-tone', 'success');
    });

    test('the uncertain and held panels are a warning, never a failure, and offer only their recovery (UXS-14, UXS-15)', async ({ page }) => {
        const panels = page.getByTestId('specimen-notices').getByRole('alert').filter({ hasText: 'Status belum pasti' });

        await expect(panels).toHaveCount(3);
        await expect(panels.nth(0).getByRole('button')).toHaveText(['Kirim ulang', 'Cek status']);
        await expect(panels.nth(1).getByRole('button')).toHaveText(['Cek status']);
        await expect(panels.nth(2).getByRole('button')).toHaveText(['Cek status']);

        for (const panel of await panels.all()) {
            // DESIGN_SYSTEM 3.2 and 9: "Status belum pasti" takes the warning tone.
            await expect(panel).toHaveAttribute('data-tone', 'warning');
            await expect(panel).toContainText('Jangan minta pelanggan membayar atau mentransfer lagi.');
            await expect(panel).not.toContainText(/gagal/i);
        }
    });

    test('the incomplete cost takes the pending tone, as a notice and as the mark of a figure (UXS-25; review finding R-17)', async ({ page }) => {
        // DESIGN_SYSTEM section 9: UXS-24 and UXS-25 take the pending tone.
        const notice = page.getByTestId('specimen-notices').locator('.bk-notice').filter({ hasText: 'Data biaya belum lengkap' });

        await expect(notice).toHaveCount(1);
        await expect(notice).toHaveAttribute('data-tone', 'pending');
        await expect(page.locator('.bk-metric .bk-mark').filter({ hasText: 'Data biaya belum lengkap' })).toHaveAttribute('data-tone', 'pending');
    });

    test('a failed read says so with "Coba lagi"; a stale read keeps its time with "Muat ulang" (UXS-22, UXS-21)', async ({ page }) => {
        const notices = page.getByTestId('specimen-notices');

        await expect(notices.getByText('Bagian ini tidak dapat dimuat. Bagian lain tetap bisa dipakai.')).toBeVisible();
        await expect(notices.getByRole('button', { name: 'Coba lagi' })).toBeVisible();
        await expect(notices.getByText('Data terakhir diperbarui 17 Oktober 2026, 13.40.00 dan mungkin sudah berubah.')).toBeVisible();
        await expect(notices.getByRole('button', { name: 'Muat ulang' })).toBeVisible();
    });

    test('every notice state of the catalogue is presented with its title (UI_STATES section 2)', async ({ page }) => {
        const states = page.locator('[data-state-id]');

        expect(await states.count()).toBeGreaterThanOrEqual(40);

        for (const state of await states.all()) {
            expect((await state.locator('.bk-notice__title').innerText()).trim()).not.toBe('');
        }
    });

    test('no results offers "Hapus filter"; the empty state says what will appear (UXS-03, UXS-02)', async ({ page }) => {
        const states = page.getByTestId('specimen-states');

        await expect(states.getByText('Tidak ada hasil')).toBeVisible();
        await expect(states.getByRole('button', { name: 'Hapus filter' })).toBeVisible();
        await expect(states.getByText('Belum ada data')).toBeVisible();
    });
});

test.describe('content security policy and fonts (AC-005-05, AC-005-06)', () => {
    test('the specimen carries the policy of SEC-HDR-03 with a fresh nonce on its tags, and no violation is reported', async ({ page }) => {
        const watched = await watch(page);
        const first = await page.goto(SPECIMEN);
        const policy = first?.headers()['content-security-policy'] ?? '';
        const nonce = /'nonce-([^']+)'/.exec(policy)?.[1] ?? '';

        expect(policy).toBe(
            `default-src 'self'; script-src 'self' 'nonce-${nonce}'; style-src 'self' 'nonce-${nonce}'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'`,
        );
        expect(nonce.length).toBeGreaterThanOrEqual(16);
        expect(policy).not.toContain('unsafe-inline');
        expect(policy).not.toContain('unsafe-eval');

        // The styles that libraries inject — the scroll lock of the dialog —
        // carry the nonce, and no inline style without it exists.
        await page.getByTestId('open-review').click();
        await expect(page.getByRole('dialog')).toBeVisible();
        const styles = await page.evaluate(() => [...document.querySelectorAll('style')].map((element) => element.nonce));

        expect(styles.length).toBeGreaterThan(0);
        expect(styles.every((value) => value === nonce)).toBe(true);
        await page.keyboard.press('Escape');

        const second = await page.goto(SPECIMEN);
        const again = /'nonce-([^']+)'/.exec(second?.headers()['content-security-policy'] ?? '')?.[1];

        expect(again).not.toBe(nonce);
        expect(watched.problems).toEqual([]);
    });

    test('Inter is self-hosted with its licence file, and no request leaves the origin', async ({ page }) => {
        const watched = await watch(page);
        const fonts: string[] = [];

        page.on('response', (response) => {
            if (response.url().includes('/fonts/')) {
                fonts.push(`${new URL(response.url()).pathname} ${response.status()}`);
            }
        });

        await page.setViewportSize({ width: 1366, height: 768 });
        await openSpecimen(page);

        expect(fonts).toContain('/fonts/inter/inter-latin-opsz-normal.woff2 200');
        expect(await page.evaluate(() => document.fonts.check('600 16px Inter'))).toBe(true);
        expect(await page.evaluate(() => [...document.fonts].some((face) => face.family.replace(/"/g, '') === 'Inter' && face.status === 'loaded'))).toBe(true);

        const licence = await page.request.get('/fonts/inter/OFL.txt');

        expect(licence.status()).toBe(200);
        expect(await licence.text()).toContain('SIL Open Font License, Version 1.1');
        expect(watched.problems).toEqual([]);
    });
});

async function tokens(page: Page): Promise<Record<string, string>> {
    return page.evaluate(() => {
        const root = getComputedStyle(document.documentElement);

        return Object.fromEntries(['--brand-primary', '--brand-accent', '--brand-accent-strong', '--brand-tint-150'].map((name) => [name, root.getPropertyValue(name).trim()]));
    });
}

test.describe('the accent set (DESIGN_SYSTEM 3.3 and 3.5)', () => {
    const expected: Record<(typeof ACCENTS)[number], string> = {
        green: '#116949',
        teal: '#0f5f6c',
        blue: '#215497',
        purple: '#583a88',
        graphite: '#34383d',
    };

    for (const accent of ACCENTS) {
        test(`${accent}: one attribute changes the brand slots; every text and focus ring keeps its contrast`, async ({ page }) => {
            await page.setViewportSize({ width: 1366, height: 768 });
            await openSpecimen(page, accent);

            await expect(page.locator('html')).toHaveAttribute('data-accent', accent);
            expect((await tokens(page))['--brand-primary']).toBe(expected[accent]);

            const measured = await measure(page);
            const weakest = measured.texts.reduce((low, text) => (text.ratio < low ? text.ratio : low), 99);

            expect(measured.texts.filter((text) => text.ratio < (text.large ? 3 : 4.5))).toEqual([]);

            // Without transitions, so that each ring is measured in its final colour.
            await page.emulateMedia({ reducedMotion: 'reduce' });

            // Every Tab stop of the specimen: 200 presses, more than it has stops.
            const stops = await page.evaluate(() => [...document.querySelectorAll<HTMLElement>('a[href], button, input, select, textarea, [tabindex]')].filter((element) => element.tabIndex >= 0 && !element.hasAttribute('disabled') && element.offsetParent !== null).length);

            expect(stops).toBeLessThan(200);
            test.setTimeout(180_000);

            const rings = await focusRings(page, 200);
            const distinct = new Set(rings.map((ring) => ring.control)).size;
            expect(rings.filter((ring) => ring.width < 2)).toEqual([]);
            expect(rings.filter((ring) => ring.ratio < 5.91).map((ring) => `${ring.control} ${ring.ratio}`)).toEqual([]);

            // The inset ring of a highlighted menu item, against the item's own background.
            await page.getByRole('button', { name: 'Tindakan lain Pulpen gel hitam', exact: true }).focus();
            await page.keyboard.press('Enter');
            const item = page.getByRole('menuitem').first();

            await expect(item).toBeFocused();
            const menuRing = await item.evaluate((element) => {
                const parse = (value: string): number[] => (value.match(/[0-9.]+/g) ?? []).map((part) => parseFloat(part));
                const luminance = (color: number[]): number =>
                    [0, 1, 2]
                        .map((index) => {
                            const value = (color[index] ?? 0) / 255;

                            return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
                        })
                        .reduce((sum, value, index) => sum + value * ([0.2126, 0.7152, 0.0722][index] ?? 0), 0);
                const style = getComputedStyle(element);
                const [high, low] = [luminance(parse(style.outlineColor)), luminance(parse(style.backgroundColor))].sort((a, b) => b - a);

                return { width: parseFloat(style.outlineWidth), ratio: Math.round((((high ?? 0) + 0.05) / ((low ?? 0) + 0.05)) * 100) / 100 };
            });

            expect(menuRing.width).toBeGreaterThanOrEqual(2);
            expect(menuRing.ratio).toBeGreaterThanOrEqual(5.91);
            await page.keyboard.press('Escape');

            console.log(
                `contrast ${accent}: texts ${measured.texts.length}, lowest text ${weakest}, tab stops ${stops}, distinct control names ${distinct} (${rings.length} presses), lowest ring ${rings.reduce((low, ring) => (ring.ratio < low ? ring.ratio : low), 99)}, menu item ring ${menuRing.ratio}`,
            );
        });
    }

    test('an accent outside the allowlist falls back to green, the default', async ({ page }) => {
        await openSpecimen(page, 'lime');
        await expect(page.locator('html')).toHaveAttribute('data-accent', 'green');
        expect((await tokens(page))['--brand-primary']).toBe('#116949');
    });

    test('the compact width class takes its token values below 600 px (DESIGN_SYSTEM 4, 5)', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await openSpecimen(page);
        await expect(page.getByRole('heading', { level: 1 })).toHaveCSS('font-size', '24px');
        await expect(page.locator('.bk-card.bk-section').first()).toHaveCSS('padding-top', '16px');
    });
});

test('reduced motion removes transitions and animations (UXA-20, DV-06)', async ({ browser }) => {
    const width = WIDTHS[1];

    if (width === undefined) {
        throw new Error('A width is missing.');
    }

    const context = await contextAt(browser, width, { reducedMotion: 'reduce' });
    const page = await context.newPage();

    await openSpecimen(page);
    await expect(page.getByTestId('button-md')).toHaveCSS('transition-duration', '0s');
    await expect(page.locator('.bk-skel').first()).toHaveCSS('animation-name', 'none');

    await context.close();

    const moving = await contextAt(browser, width);
    const other = await moving.newPage();

    await openSpecimen(other);
    await expect(other.getByTestId('button-md')).not.toHaveCSS('transition-duration', '0s');
    await moving.close();
});

test('print shows only the paper (UXA-23, DESIGN_SYSTEM section 12)', async ({ page }) => {
    await page.setViewportSize({ width: 1366, height: 768 });
    await openSpecimen(page);
    await page.emulateMedia({ media: 'print' });

    await expect(page.getByRole('article', { name: 'Struk' })).toHaveCSS('visibility', 'visible');
    await expect(page.getByTestId('open-review')).toHaveCSS('visibility', 'hidden');
    await expect(page.locator('h1')).toHaveCSS('visibility', 'hidden');
});

test('the archetypes keep their containers: data 1440 and focused 640 px (DESIGN_SYSTEM 7.1, 7.3)', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await openSpecimen(page);

    const archetypes = page.getByTestId('archetypes');

    await expect(archetypes.locator('.bk-container--focused')).toHaveCSS('max-width', '640px');
    await expect(archetypes.locator('.bk-container').first()).toHaveCSS('max-width', '1440px');
    expect((await archetypes.locator('.bk-container--focused').boundingBox())?.width ?? 0).toBeLessThanOrEqual(640);
    await expect(archetypes.getByText('Gagal memuat')).toBeVisible();
    await expect(archetypes.getByText('Belum ada data')).toBeVisible();
});
