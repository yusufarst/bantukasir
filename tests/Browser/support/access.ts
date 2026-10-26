import { expect as baseExpect, type APIRequestContext, type BrowserContext, type Page } from '@playwright/test';

// The browser fixtures of the Access pages (TASK-006): the people of Toko Contoh
// with new passwords and one-time links, made by the test harness on the
// running local environment (tests/Harness/Browser/FixturesController.php),
// which serves them only in the local and testing environments. Every value is
// a synthetic test value made at run time.

/**
 * The waits of the Access tests. A check there waits for a submission and the
 * page load that follows it; on the Owner's Windows machine the local
 * environment answered one page in 2.6 to 3.4 seconds (TASK-006 evidence,
 * finding F-5), so a check waits up to 15 seconds and a test up to 2 minutes.
 * In CI a page answers in milliseconds, and the waits change nothing there.
 */
export const ACCESS_WAIT = 15_000;

export const ACCESS_TIMEOUT = 120_000;

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

/**
 * The browser options of the Access tests, which each of their files passes to
 * test.use(). The signed-in pages encrypt their browser history, which needs a
 * secure context (SECURITY_MODEL SEC-CH-04); the local environment answers
 * over plain HTTP, and Chromium treats localhost as secure but not the name
 * the CI containers use. Chromium's full browser, which honours the switch,
 * treats that one origin of the local environment as secure, never another.
 */
export const SECURE_LOCAL_ORIGIN = {
    channel: 'chromium',
    launchOptions: { args: [`--unsafely-treat-insecure-origin-as-secure=${new URL(process.env.E2E_BASE_URL ?? 'http://localhost:8080').origin}`] },
};

/**
 * The problems the page watch recorded, without the answers that the browser
 * logs as failed loads and that are expected here: Inertia's location answer
 * of a full page load (409); and 404 for RN-01, the home, which TASK-007
 * delivers, and for the icon that Chromium's full browser asks for, which no
 * Task has added yet. A missing script or style breaks the page, which the
 * other checks see.
 */
export function unexpected(problems: readonly string[]): string[] {
    return problems.filter((problem) => !/^console error: Failed to load resource: the server responded with a status of (404 \(Not Found\)|409 \(Conflict\))$/.test(problem));
}

export type Person = 'Hendra' | 'Agus' | 'Rina' | 'Dewi';

export interface Account {
    readonly id: number;
    readonly email: string;
    readonly password: string;
}

export interface Link {
    readonly user: number;
    readonly token: string;
}

/**
 * A new password that meets the password rule (SECURITY_MODEL SEC-AUTH-05),
 * made at run time as TokoContoh::newPassword() makes it (EXECUTION_CONTEXT
 * EXC-63).
 */
export function newPassword(): string {
    const digits = Array.from(crypto.getRandomValues(new Uint8Array(10)), (byte) => byte.toString(16).padStart(2, '0'));

    return `Kc7#${digits.join('')}`;
}

/** The CSRF token of the context's session, from the cookie the framework sets. */
async function xsrf(context: BrowserContext, request: APIRequestContext): Promise<string> {
    let cookie = (await context.cookies()).find((candidate) => candidate.name === 'XSRF-TOKEN');

    if (cookie === undefined) {
        const answer = await request.get('/login');

        expect(answer.status()).toBe(200);
        cookie = (await context.cookies()).find((candidate) => candidate.name === 'XSRF-TOKEN');
    }

    expect(cookie, 'the framework sets the XSRF-TOKEN cookie').toBeDefined();

    return decodeURIComponent(cookie?.value ?? '');
}

/** New passwords for these people, their sign-in sessions revoked, the rate limiter emptied. */
export async function resetPeople(context: BrowserContext, people: readonly Person[]): Promise<Record<Person, Account>> {
    const answer = await context.request.post('/testing/browser/reset', {
        data: { people },
        headers: { 'X-XSRF-TOKEN': await xsrf(context, context.request), Accept: 'application/json' },
    });

    expect(answer.status(), await answer.text()).toBe(200);

    return ((await answer.json()) as { accounts: Record<Person, Account> }).accounts;
}

/** A new one-time link for one person. */
export async function issueLink(context: BrowserContext, person: Person): Promise<Link> {
    const answer = await context.request.post('/testing/browser/link', {
        data: { person },
        headers: { 'X-XSRF-TOKEN': await xsrf(context, context.request), Accept: 'application/json' },
    });

    expect(answer.status(), await answer.text()).toBe(200);

    return (await answer.json()) as Link;
}

/** The address of the link page, the account and token in the fragment (RP-002). */
export function linkAddress(link: Link): string {
    return `/set-password#u=${link.user}&t=${encodeURIComponent(link.token)}`;
}

/** Signs in on UXP-001 with the keyboard: the form, then Enter (UXA-10 exception). */
export async function signIn(page: Page, account: Account): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email').fill(account.email);
    await page.getByLabel('Kata sandi', { exact: true }).fill(account.password);
    await Promise.all([page.waitForURL((url) => url.pathname === '/'), page.getByLabel('Kata sandi', { exact: true }).press('Enter')]);
}

/**
 * Signs out as the account menu's "Keluar" will (UXI-026, TASK-007): RA-02 as an
 * Inertia visit, then the full page load of Inertia's location answer.
 */
export async function signOut(page: Page): Promise<void> {
    const location = await page.evaluate(async () => {
        const token = decodeURIComponent(document.cookie.split('; ').find((part) => part.startsWith('XSRF-TOKEN='))?.slice('XSRF-TOKEN='.length) ?? '');
        const answer = await fetch('/logout', { method: 'POST', headers: { 'X-XSRF-TOKEN': token, 'X-Inertia': 'true', 'X-Requested-With': 'XMLHttpRequest' } });

        return answer.status === 409 ? answer.headers.get('X-Inertia-Location') : null;
    });

    expect(location).not.toBeNull();
    await page.goto(location ?? '/login');
}
