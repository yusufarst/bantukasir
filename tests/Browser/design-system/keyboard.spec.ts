import { expect, test, type Page } from '@playwright/test';
import { events, openSpecimen, watch } from '../support/specimen';

// AC-005-02: the keyboard and focus rules of UXA-08 and UXA-10 and the
// provisional shortcuts of DESIGN_SYSTEM section 11 (D-075), with real key
// events on the component specimen.

test.beforeEach(async ({ page }) => {
    await page.setViewportSize({ width: 1366, height: 768 });
    await openSpecimen(page);
});

test('the review dialog takes focus on "Kembali ubah", keeps Tab inside, closes with Esc and returns focus to its opener (UXA-08)', async ({ page }) => {
    const opener = page.getByTestId('open-review');

    await opener.focus();
    await page.keyboard.press('Space');

    const dialog = page.getByRole('dialog', { name: 'Tinjau barang masuk' });

    await expect(dialog).toBeVisible();
    await expect(page.getByRole('button', { name: 'Kembali ubah (Esc)' })).toBeFocused();

    for (let index = 0; index < 4; index += 1) {
        await page.keyboard.press('Tab');
        expect(await dialog.evaluate((element) => element.contains(document.activeElement))).toBe(true);
    }

    await page.keyboard.press('Shift+Tab');
    expect(await dialog.evaluate((element) => element.contains(document.activeElement))).toBe(true);

    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(opener).toBeFocused();
    expect(await events(page)).not.toContain('back');
});

test('Enter never activates the confirm button of a review dialog; Space does (UXA-10)', async ({ page }) => {
    await page.getByTestId('open-review').click();

    const confirm = page.getByRole('button', { name: 'Konfirmasi barang masuk (Ctrl+Enter)' });

    await confirm.focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('dialog')).toBeVisible();
    expect(await events(page)).not.toContain('confirm');

    await page.keyboard.press('Space');
    await expect(page.getByRole('dialog')).toBeHidden();
    expect(await events(page)).toContain('confirm');
    await expect(page.getByTestId('specimen-notices').getByRole('status').filter({ hasText: 'Barang masuk tersimpan.' })).toContainText('Berhasil disimpan');
});

test('Enter never activates the final action; pointer and Space do, and it is never the initial focus (8.2, UXA-10)', async ({ page }) => {
    const final = page.getByTestId('final-action');

    await expect(final).not.toBeFocused();
    await final.focus();
    await page.keyboard.press('Enter');
    expect(await events(page)).not.toContain('final');

    await page.keyboard.press('Space');
    expect(await events(page)).toEqual(['final']);

    await final.click();
    expect(await events(page)).toEqual(['final', 'final']);
});

test('Ctrl+Enter opens the review and a second, separate press confirms; a held or repeated press is ignored (UXA-09)', async ({ page }) => {
    await page.locator('body').click({ position: { x: 5, y: 5 } });

    await page.keyboard.down('Control');
    await page.keyboard.down('Enter');
    await expect(page.getByRole('dialog')).toBeVisible();

    // Still held: the browser repeats the key, and the repeat is ignored.
    await page.keyboard.down('Enter');
    await page.keyboard.down('Enter');
    await expect(page.getByRole('dialog')).toBeVisible();
    expect(await events(page)).toEqual(['review']);

    await page.keyboard.up('Enter');
    await page.keyboard.down('Enter');
    await page.keyboard.up('Enter');
    await page.keyboard.up('Control');

    await expect(page.getByRole('dialog')).toBeHidden();
    expect(await events(page)).toEqual(['review', 'confirm']);
});

test('F2 focuses and selects the scan field; Enter there runs the lookup and submits nothing (UXA-09, UXA-10, UXA-11)', async ({ page }) => {
    const field = page.getByRole('searchbox', { name: 'Cari produk (F2)' });

    await page.keyboard.press('F2');
    await expect(field).toBeFocused();

    await page.keyboard.type('8991234567890');
    await page.keyboard.press('Enter');

    expect(await events(page)).toEqual(['lookup']);
    await expect(field).toHaveValue('8991234567890');
    await expect(field).toBeFocused();
});

test('F8 activates "Bayar", whose accessible name carries the shortcut (UXA-09, COPY_DECK 5.1)', async ({ page }) => {
    await expect(page.getByRole('button', { name: 'Bayar (F8)' })).toHaveAttribute('aria-keyshortcuts', 'F8');

    await page.keyboard.press('F8');
    expect(await events(page)).toEqual(['pay']);
});

test('Esc is the back action of the page, and only closes a dialog while one is open (DESIGN_SYSTEM section 11)', async ({ page }) => {
    await page.locator('body').click({ position: { x: 5, y: 5 } });
    await page.keyboard.press('Escape');
    expect(await events(page)).toEqual(['back']);

    await page.getByTestId('open-review').click();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog')).toBeHidden();
    expect(await events(page)).toEqual(['back', 'review']);
});

test('the segmented control has radio semantics: Alt+1 and Alt+2 choose, the arrow keys move (8.4, UXA-09)', async ({ page }) => {
    const cash = page.getByRole('radio', { name: 'Tunai (Alt+1)' });
    const transfer = page.getByRole('radio', { name: 'Transfer bank (Alt+2)' });

    await expect(page.getByRole('radiogroup', { name: 'Metode pembayaran' })).toBeVisible();
    await expect(cash).toHaveAttribute('aria-checked', 'true');

    await page.keyboard.press('Alt+2');
    await expect(transfer).toHaveAttribute('aria-checked', 'true');
    await expect(transfer).toBeFocused();

    // Held as a person holds it: Radix moves the focus after the key event and
    // chooses only while the arrow key is down.
    await page.keyboard.down('ArrowLeft');
    await expect(cash).toBeFocused();
    await page.keyboard.up('ArrowLeft');
    await expect(cash).toHaveAttribute('aria-checked', 'true');
    await expect(cash).toBeFocused();

    await page.keyboard.press('Alt+2');
    await page.keyboard.press('Alt+1');
    await expect(cash).toHaveAttribute('aria-checked', 'true');
});

test('a filter chip is a toggle button with aria-pressed (8.4)', async ({ page }) => {
    const chip = page.getByRole('button', { name: 'Menipis' });

    await expect(chip).toHaveAttribute('aria-pressed', 'false');
    await chip.focus();
    await page.keyboard.press('Space');
    await expect(chip).toHaveAttribute('aria-pressed', 'true');
});

test('a menu opens from its button, moves with the arrow keys, Home and End, and Esc returns focus to the button (8.9)', async ({ page }) => {
    const button = page.getByRole('button', { name: 'Tindakan lain Pulpen gel hitam', exact: true });

    await expect(button).toHaveAttribute('aria-expanded', 'false');
    await button.focus();
    await page.keyboard.press('Enter');
    await expect(button).toHaveAttribute('aria-expanded', 'true');

    const items = page.getByRole('menuitem');

    await expect(items).toHaveCount(3);
    await expect(items.nth(0)).toBeFocused();
    await page.keyboard.press('ArrowDown');
    await expect(items.nth(1)).toBeFocused();
    await page.keyboard.press('End');
    await expect(items.nth(2)).toBeFocused();
    await page.keyboard.press('Home');
    await expect(items.nth(0)).toBeFocused();

    await page.keyboard.press('Escape');
    await expect(page.getByRole('menu')).toBeHidden();
    await expect(button).toBeFocused();
    expect(await events(page)).not.toContain('back');
});

test('the drawer is modal: the page is inert, Esc closes it and focus returns to the menu button (8.9)', async ({ page }) => {
    const opener = page.getByTestId('open-drawer');

    await opener.focus();
    await page.keyboard.press('Enter');

    const drawer = page.getByRole('dialog', { name: 'Toko Contoh' });

    await expect(drawer).toBeVisible();
    await expect(page.getByRole('button', { name: 'Tutup menu' })).toBeVisible();
    expect(await drawer.evaluate((element) => element.contains(document.activeElement))).toBe(true);
    await expect(page.getByTestId('specimen')).toHaveAttribute('aria-hidden', 'true');

    await page.keyboard.press('Escape');
    await expect(drawer).toBeHidden();
    await expect(opener).toBeFocused();

    await opener.click();
    await page.getByRole('button', { name: 'Tutup menu' }).click();
    await expect(drawer).toBeHidden();
    await expect(opener).toBeFocused();
});

test('the trend chart is reached with Tab, moves with the arrow keys, Home and End, and shows and announces each point (8.13, UXA-19)', async ({ page }) => {
    const chart = page.getByTestId('trend-chart');
    const tip = page.getByTestId('trend-tip');
    const polite = page.getByTestId('live-polite');

    await page.getByRole('button', { name: 'Tampilkan sebagai tabel' }).focus();
    await page.keyboard.press('Tab');
    await expect(chart).toBeFocused();
    await expect(tip).toHaveText('07 Oktober 2026: Rp2.104.000');
    await expect(polite).toHaveText('07 Oktober 2026: Rp2.104.000');

    await page.keyboard.press('ArrowLeft');
    await expect(tip).toHaveText('06 Oktober 2026: Rp1.890.000');
    await page.keyboard.press('Home');
    await expect(tip).toHaveText('01 Oktober 2026: Rp1.180.000');
    await expect(polite).toHaveText('01 Oktober 2026: Rp1.180.000');
    await page.keyboard.press('End');
    await expect(tip).toHaveText('07 Oktober 2026: Rp2.104.000');

    await page.getByRole('button', { name: 'Tampilkan sebagai tabel' }).click();
    const table = page.getByRole('table', { name: 'Penjualan neto per hari' });

    await expect(table).toBeVisible();
    await expect(table.getByRole('row')).toHaveCount(8);
    await expect(page.getByRole('button', { name: 'Tampilkan sebagai grafik' })).toHaveAttribute('aria-pressed', 'true');
});

test('the summary of a form\'s errors takes focus and leads to its field, whose error is tied to it (UXS-05, UXA-13)', async ({ page }) => {
    await page.getByTestId('show-errors').click();

    const summary = page.locator('.bk-error-summary');

    await expect(summary).toBeFocused();
    await expect(summary).toContainText('Periksa isian');
    await summary.getByRole('link', { name: 'Jumlah' }).click();

    const field = page.locator('#specimen-quantity');

    await expect(field).toBeFocused();
    await expect(field).toHaveAttribute('aria-invalid', 'true');
    await expect(field).toHaveAccessibleDescription('Isi jumlah lebih dari 0.');
});

test('Enter in a field of a command form submits nothing (UXA-10)', async ({ page }) => {
    const field = page.getByTestId('field-product');
    const url = page.url();

    await field.fill('Pulpen');
    await page.keyboard.press('Enter');
    await expect(field).toHaveValue('Pulpen');
    expect(page.url()).toBe(url);
});

test('a control disabled with a reason stays focusable, exposes the reason and does nothing (UXS-13, 8.7)', async ({ page }) => {
    const button = page.getByTestId('button-disabled');

    await expect(button).toHaveAttribute('aria-disabled', 'true');
    await expect(button).toHaveAccessibleDescription('Lengkapi isian yang wajib dulu.');
    await button.focus();
    await expect(button).toBeFocused();
});

test('while sending, the button shows "Sedang diproses", its name follows, and repeats are blocked (UXS-06, 8.1)', async ({ page }) => {
    const button = page.getByTestId('button-busy');

    await button.click();
    await expect(button).toHaveAttribute('aria-busy', 'true');
    await expect(button).toHaveAttribute('aria-disabled', 'true');
    await expect(button).toHaveAccessibleName('Sedang diproses');
    await expect(button.locator('.bk-spinner')).toBeVisible();
});

test('the till: arrow keys move in the results, a pick adds to the cart and returns focus to the search (UXA-09, 8.14)', async ({ page }) => {
    const results = page.getByRole('list', { name: 'Hasil pencarian' }).getByRole('button');

    await results.nth(0).focus();
    await page.keyboard.press('ArrowDown');
    await expect(results.nth(1)).toBeFocused();
    await page.keyboard.press('ArrowUp');
    await expect(results.nth(0)).toBeFocused();

    await expect(page.getByRole('region', { name: 'Keranjang' })).toBeVisible();
    await page.getByRole('button', { name: 'Hapus baris Pulpen gel hitam 0,5 mm' }).click();
    await expect(page.getByRole('region', { name: 'Keranjang' }).getByText('Pulpen gel hitam 0,5 mm')).toHaveCount(0);

    await results.nth(0).focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('searchbox', { name: 'Cari produk (F2)' })).toBeFocused();
    await expect(page.getByRole('region', { name: 'Keranjang' }).getByText('Pulpen gel hitam 0,5 mm')).toHaveCount(1);
});

test('no console error, page error or CSP violation while every overlay opens and closes (AC-005-05)', async ({ page }) => {
    const watched = await watch(page);

    await openSpecimen(page);
    await page.getByTestId('open-review').click();
    await page.keyboard.press('Escape');
    await page.getByTestId('open-drawer').click();
    await page.keyboard.press('Escape');
    await page.getByRole('button', { name: 'Tindakan lain Pulpen gel hitam', exact: true }).click();
    await page.keyboard.press('Escape');

    expect(watched.problems).toEqual([]);
});

// Review round 1 of TASK-005, findings R-02, R-03 and R-04.
for (const [where, focus] of [
    ['a radio of the segmented control', 'radio'],
    ['the quantity field of a cart line', 'stepper'],
    ['a text field of a command form', 'product'],
    ['an amount field', 'amount'],
] as const) {
    test(`Ctrl+Enter still confirms from ${where}, which blocks Enter (UXA-09)`, async ({ page }) => {
        const target = {
            radio: page.getByRole('radio', { name: 'Tunai (Alt+1)' }),
            stepper: page.getByRole('textbox', { name: 'Jumlah Pulpen gel hitam 0,5 mm' }),
            product: page.getByTestId('field-product'),
            amount: page.getByTestId('field-amount'),
        }[focus];

        await target.focus();
        await page.keyboard.press('Enter');
        await expect(page.getByRole('dialog')).toBeHidden();

        await page.keyboard.press('Control+Enter');
        await expect(page.getByRole('dialog', { name: 'Tinjau barang masuk' })).toBeVisible();
        expect(await events(page)).toEqual(['review']);
    });
}

test('Enter in the one field of a command form submits nothing, though the form would submit (UXA-10)', async ({ page }) => {
    const amount = page.getByTestId('field-amount');

    await amount.focus();
    await page.keyboard.press('Enter');
    expect(await events(page)).toEqual([]);

    // The control: the same form does submit when asked to, so the test can fail.
    await amount.evaluate((element) => element.closest('form')?.requestSubmit());
    expect(await events(page)).toEqual(['submit']);
});

test('the unsaved-changes dialog starts on "Tetap di halaman ini"; Esc stays and returns the focus (UXS-45)', async ({ page }) => {
    const opener = page.getByTestId('open-unsaved');

    await opener.click();
    await expect(page.getByRole('dialog', { name: 'Perubahan belum disimpan' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Tetap di halaman ini' })).toBeFocused();
    await expect(page.getByRole('dialog')).toContainText('Isian di halaman ini akan hilang jika Anda meninggalkannya.');

    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog')).toBeHidden();
    await expect(opener).toBeFocused();
    expect(await events(page)).toEqual([]);

    await opener.click();
    await page.getByRole('dialog').getByRole('button', { name: 'Tinggalkan' }).click();
    expect(await events(page)).toEqual(['leave']);
});

test('a polite and an assertive announcement made together both reach their regions (UXA-17)', async ({ page }) => {
    await page.getByTestId('announce-both').click();

    await expect(page.getByTestId('live-assertive')).toHaveText('Tidak disimpan. Barang masuk tersimpan.');
    await expect(page.getByTestId('live-polite')).toHaveText('Data selesai dimuat.');
});

// Review round 2 of TASK-005, finding R-15: a control that acts on Enter and
// prevents its default has taken the key, Ctrl+Enter included, and the page's
// confirm binding yields to it, so that one press does one thing (UXA-09).
test('Ctrl+Enter on a menu button opens its menu and confirms nothing (UXA-09)', async ({ page }) => {
    const button = page.getByRole('button', { name: 'Tindakan lain Pulpen gel hitam', exact: true });

    await button.focus();
    await page.keyboard.press('Control+Enter');
    await expect(button).toHaveAttribute('aria-expanded', 'true');
    await expect(page.getByRole('menu')).toBeVisible();
    await expect(page.getByRole('dialog')).toBeHidden();
    expect(await events(page)).toEqual([]);
});

test('Ctrl+Enter on a menu item runs that item and nothing else (UXA-09)', async ({ page }) => {
    await page.getByRole('button', { name: 'Tindakan lain Pulpen gel hitam', exact: true }).focus();
    await page.keyboard.press('Enter');

    const item = page.getByRole('menuitem', { name: 'Ubah produk' });

    await expect(item).toBeFocused();
    await page.keyboard.press('Control+Enter');
    await expect(page.getByRole('menu')).toBeHidden();
    await expect(page.getByRole('dialog')).toBeHidden();
    expect(await events(page)).toEqual(['menu']);
});

test('Ctrl+Enter on a menu item that opens the review only opens it; a second, separate press confirms (UXA-09)', async ({ page }) => {
    await page.getByRole('button', { name: 'Tindakan lain Pulpen gel hitam 0,5 mm', exact: true }).focus();
    await page.keyboard.press('Enter');
    await page.keyboard.press('ArrowDown');

    const item = page.getByRole('menuitem', { name: 'Tinjau' });

    await expect(item).toBeFocused();
    await page.keyboard.press('Control+Enter');

    const dialog = page.getByRole('dialog', { name: 'Tinjau barang masuk' });

    await expect(dialog).toBeVisible();
    expect(await events(page)).toEqual(['review']);

    await page.keyboard.press('Control+Enter');
    await expect(dialog).toBeHidden();
    expect(await events(page)).toEqual(['review', 'confirm']);
});

// Review round 2, finding R-16: which controls prevent the default of
// Ctrl+Enter before the registry sees it. A radio item and a field of a
// command form do, so the cases above prove that Ctrl+Enter confirms from them
// although their default was prevented; the stepper's field does not, so a
// test blocks it here as a radio does, to prove the same for the stepper.
async function preventedBeforeRegistry(page: Page): Promise<void> {
    // React handles key events on its root, below the document where the
    // registry listens; a listener added on the root after React's sees what
    // the components did, and nothing of the registry.
    await page.evaluate(() => {
        const root = document.getElementById('app');

        if (root === null) {
            throw new Error('No root element');
        }

        root.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                document.body.dataset['enterPrevented'] = String(event.defaultPrevented);
            }
        });
    });
}

for (const [where, focus, prevented] of [
    ['a radio of the segmented control', 'radio', 'true'],
    ['a text field of a command form', 'product', 'true'],
    ['an amount field', 'amount', 'true'],
    ['the quantity field of a cart line', 'stepper', 'false'],
] as const) {
    test(`from ${where}, Ctrl+Enter's default is ${prevented === 'true' ? 'prevented by the control' : 'left alone'} and the review opens (UXA-09)`, async ({ page }) => {
        const target = {
            radio: page.getByRole('radio', { name: 'Tunai (Alt+1)' }),
            stepper: page.getByRole('textbox', { name: 'Jumlah Pulpen gel hitam 0,5 mm' }),
            product: page.getByTestId('field-product'),
            amount: page.getByTestId('field-amount'),
        }[focus];

        await preventedBeforeRegistry(page);
        await target.focus();
        await page.keyboard.press('Control+Enter');
        await expect(page.locator('body')).toHaveAttribute('data-enter-prevented', prevented);
        await expect(page.getByRole('dialog', { name: 'Tinjau barang masuk' })).toBeVisible();
        expect(await events(page)).toEqual(['review']);
    });
}

test('Ctrl+Enter confirms from the stepper\'s field even when its default was prevented (UXA-09)', async ({ page }) => {
    const field = page.getByRole('textbox', { name: 'Jumlah Pulpen gel hitam 0,5 mm' });

    await field.evaluate((element) => {
        element.addEventListener('keydown', (event) => {
            if (event instanceof KeyboardEvent && event.key === 'Enter') {
                event.preventDefault();
            }
        });
    });
    await field.focus();
    await page.keyboard.press('Control+Enter');
    await expect(page.getByRole('dialog', { name: 'Tinjau barang masuk' })).toBeVisible();
    expect(await events(page)).toEqual(['review']);
});

// Review round 2, finding R-21: with no usable confirm binding on the page,
// Ctrl+Enter in the one field of a command form still submits nothing.
test('Ctrl+Enter in the one field of a command form submits nothing when the page has no confirm binding (UXA-10)', async ({ page }) => {
    await page.getByTestId('open-review').evaluate((element) => {
        if (element instanceof HTMLElement) {
            element.style.display = 'none';
        }
    });

    const amount = page.getByTestId('field-amount');

    await amount.focus();
    await page.keyboard.press('Control+Enter');
    await expect(page.getByRole('dialog')).toBeHidden();
    expect(await events(page)).toEqual([]);
});

// Review round 3 of TASK-005, finding R-22: a dialog opened from a menu item
// gives the focus back to that menu's button when it closes (UXA-08).
for (const how of ['Esc', 'Kembali ubah'] as const) {
    test(`the review opened from a row's menu item returns the focus to the menu's button when closed with ${how} (UXA-08)`, async ({ page }) => {
        const button = page.getByRole('button', { name: 'Tindakan lain Pulpen gel hitam 0,5 mm', exact: true });

        await button.focus();
        await page.keyboard.press('Enter');
        await page.keyboard.press('ArrowDown');
        await expect(page.getByRole('menuitem', { name: 'Tinjau' })).toBeFocused();
        await page.keyboard.press('Enter');

        const dialog = page.getByRole('dialog', { name: 'Tinjau barang masuk' });

        await expect(dialog).toBeVisible();
        await expect(page.getByRole('button', { name: 'Kembali ubah (Esc)' })).toBeFocused();

        if (how === 'Esc') {
            await page.keyboard.press('Escape');
        } else {
            await page.getByRole('button', { name: 'Kembali ubah (Esc)' }).click();
        }

        await expect(dialog).toBeHidden();
        await expect(button).toBeFocused();
    });
}
