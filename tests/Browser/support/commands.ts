import { expect as baseExpect, type BrowserContext, type Page, type Request, type Route } from '@playwright/test';
import { ACCESS_WAIT, type Person } from './access';

// The fixtures and helpers of the command client's browser tests (TASK-009;
// TEST_STRATEGY TS-22, TS-23): the command platform prepared on the running
// local environment — the probe's tables, the recovery epoch, and no
// unresolved request left for the named people — and a receipt of another
// device (tests/Harness/Browser/FixturesController.php), every value a
// synthetic test value made at run time.

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

/** The command client's specimen, whose command is the probe (Testing/CommandSpecimen). */
export const COMMAND_SPECIMEN = '/testing/command-specimen';

/** The probe's route and RC-50's (tests/Harness/Commands/Probe.php; ROUTE_CONTRACTS RC-50). */
export const PROBE_ROUTE = '**/commands/testing.probe';

export const RESOLVE_ROUTE = '**/commands/resolve';

/** RP-006 (ROUTE_CONTRACTS section 2). */
export const REQUESTS_PAGE = '/requests';

async function token(context: BrowserContext): Promise<string> {
    let cookie = (await context.cookies()).find((candidate) => candidate.name === 'XSRF-TOKEN');

    if (cookie === undefined) {
        await context.request.get('/login');
        cookie = (await context.cookies()).find((candidate) => candidate.name === 'XSRF-TOKEN');
    }

    return decodeURIComponent(cookie?.value ?? '');
}

/** The command platform ready, and the named people without an unresolved request; the epoch. */
export async function prepareCommands(context: BrowserContext, people: readonly Person[]): Promise<string> {
    const answer = await context.request.post('/testing/browser/commands', { data: { people }, headers: { 'X-XSRF-TOKEN': await token(context), Accept: 'application/json' } });

    expect(answer.status(), await answer.text()).toBe(200);

    return ((await answer.json()) as { epoch: string }).epoch;
}

/** A PENDING receipt of another device for the person, of a real operation. */
export async function receiptOf(context: BrowserContext, person: Person, operation: string): Promise<{ sourceIntentId: string; receivedAt: string }> {
    const answer = await context.request.post('/testing/browser/receipt', { data: { person, operation }, headers: { 'X-XSRF-TOKEN': await token(context), Accept: 'application/json' } });

    expect(answer.status(), await answer.text()).toBe(200);

    return (await answer.json()) as { sourceIntentId: string; receivedAt: string };
}

/** The references the browser keeps for each user (SEC-CH-05), by storage key. */
export async function storedReferences(page: Page): Promise<Record<string, string>> {
    return page.evaluate(() => {
        const found: Record<string, string> = {};

        for (let index = 0; index < window.localStorage.length; index += 1) {
            const key = window.localStorage.key(index);

            if (key !== null && key.startsWith('bk.commands.references.')) {
                found[key] = window.localStorage.getItem(key) ?? '';
            }
        }

        return found;
    });
}

/** The envelope of a request to the probe. */
export function envelopeOf(request: Request): { sourceIntentId: string; idempotencyKey: string; recoveryEpoch: string; payload: Record<string, unknown> } {
    return request.postDataJSON() as { sourceIntentId: string; idempotencyKey: string; recoveryEpoch: string; payload: Record<string, unknown> };
}

/** The error body of RT-07 for a refusal with this code. */
export function refusal(code: string, outcome: 'REJECTED' | 'HELD' = 'REJECTED'): Record<string, unknown> {
    const keys: Record<string, string> = {
        IDEMPOTENCY_CONFLICT: 'ux.state.held.idempotency_conflict',
        EPOCH_STALE: 'ux.state.held.epoch_stale',
        SERVICE_BUSY: 'ux.state.uncertain',
        RECENT_AUTH_REQUIRED: 'ux.state.recent_auth',
        THROTTLED: 'ux.state.throttled',
        CSRF_FAILED: 'ux.state.reload',
    };

    return { outcome, code, messageKey: keys[code] ?? 'ux.state.validation', params: {}, fields: {}, lines: {} };
}

/** Answers a route with a refusal of this status and code, without reaching the server. */
export async function refuse(route: Route, status: number, code: string, outcome: 'REJECTED' | 'HELD' = 'REJECTED', headers: Record<string, string> = {}): Promise<void> {
    await route.fulfill({ status, json: refusal(code, outcome), headers });
}

/**
 * The problems of the page watch that these tests cause on purpose: the
 * failed loads that the browser logs for the refusals and dropped answers
 * they inject (TS-22).
 */
export function injected(problems: readonly string[]): string[] {
    return problems.filter(
        (problem) =>
            !/^console error: Failed to load resource: (the server responded with a status of (401|404|409|419|422|423|429|503) \([A-Za-z ]+\)|net::ERR_FAILED)$/.test(problem),
    );
}
