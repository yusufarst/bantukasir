import { expect, type Browser, type BrowserContext, type ConsoleMessage, type Page } from '@playwright/test';

// The browser harness of the design system (TASK-005 scope item 8): the
// component specimen, which the application serves only in the local and
// testing environments, the widths of KEY_SCREENS section 4 and 1440 px, and
// the checks that every component Task reuses.

export const SPECIMEN = '/testing/specimen';

export interface Width {
    readonly name: string;
    readonly width: number;
    readonly height: number;
    /** 2 for the 200 % zoom of a 1280 x 800 window (UXA-12). */
    readonly scale?: number;
}

/** KEY_SCREENS section 4, with 1440 px (EXECUTION_CONTEXT EXC-53). */
export const WIDTHS: readonly Width[] = [
    { name: '1440x900', width: 1440, height: 900 },
    { name: '1366x768', width: 1366, height: 768 },
    { name: '1024x768', width: 1024, height: 768 },
    { name: '768x1024', width: 768, height: 1024 },
    { name: '640x400 (200 % zoom of 1280x800)', width: 640, height: 400, scale: 2 },
    { name: '390x844', width: 390, height: 844 },
    { name: '360x740', width: 360, height: 740 },
    { name: '320x640', width: 320, height: 640 },
];

export const ACCENTS = ['green', 'teal', 'blue', 'purple', 'graphite'] as const;

export async function contextAt(browser: Browser, width: Width, options: { reducedMotion?: 'reduce' | 'no-preference' } = {}): Promise<BrowserContext> {
    return browser.newContext({
        viewport: { width: width.width, height: width.height },
        deviceScaleFactor: width.scale ?? 1,
        reducedMotion: options.reducedMotion ?? 'no-preference',
    });
}

export interface PageWatch {
    readonly problems: string[];
}

/**
 * Whether a console message is the browser's own report that the site icon is
 * missing: Chromium's full browser, which every test runs (playwright.config.ts,
 * TASK-007), asks for /favicon.ico by itself, and no Task has added an icon
 * yet. The message names the icon's address as its location; a missing script,
 * style or font of the page is another address and stays a problem.
 */
function missingSiteIcon(message: ConsoleMessage): boolean {
    const location = message.location().url;

    return message.type() === 'error' && location !== '' && new URL(location).pathname === '/favicon.ico' && message.text().includes('status of 404');
}

/**
 * Records, from before the first script of the page: every violation of the
 * content security policy, every console error and page error, and every
 * request that leaves the application's origin (AC-005-05, AC-005-06). The
 * browser's own request for the site icon is not the page's (missingSiteIcon).
 */
export async function watch(page: Page): Promise<PageWatch> {
    const problems: string[] = [];

    await page.addInitScript(() => {
        document.addEventListener('securitypolicyviolation', (event) => {
            console.error(`CSP violation: ${event.violatedDirective} ${event.blockedURI}`);
        });
    });
    page.on('console', (message) => {
        if ((message.type() === 'error' || message.type() === 'warning') && !missingSiteIcon(message)) {
            problems.push(`console ${message.type()}: ${message.text()}`);
        }
    });
    page.on('pageerror', (error) => problems.push(`page error: ${error.message}`));
    page.on('request', (request) => {
        const url = new URL(request.url());
        const base = new URL(page.url() === 'about:blank' ? request.url() : page.url());

        if (!['data:', 'blob:'].includes(url.protocol) && url.origin !== base.origin) {
            problems.push(`request outside the origin: ${request.url()}`);
        }
    });

    return { problems };
}

export async function openSpecimen(page: Page, accent?: string): Promise<void> {
    const response = await page.goto(accent === undefined ? SPECIMEN : `${SPECIMEN}?aksen=${accent}`);

    expect(response?.status()).toBe(200);
    await expect(page.getByTestId('specimen')).toBeVisible();
    await page.evaluate(async () => {
        await document.fonts.ready;
    });
}

/** The events the specimen recorded, in its hidden output. */
export async function events(page: Page): Promise<string[]> {
    const text = (await page.getByTestId('events').textContent()) ?? '';

    return text.split(' ').filter((event) => event !== '');
}
