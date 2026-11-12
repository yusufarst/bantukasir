import { expect as baseExpect, type BrowserContext } from '@playwright/test';
import { ACCESS_WAIT, type Person } from './access';

// The cash sessions of the browser suite (TASK-030; tests/Harness/Browser/FixturesController.php,
// `session`): a person's OPEN session closed through RC-03's Action, so that their home is Buka sesi kas
// again; a new register to open one on; or a new session with its opening cash and a Sale in each
// method. Every code and name is new and synthetic, made at run time.

const expect = baseExpect.configure({ timeout: ACCESS_WAIT });

/** A register as the fixture names it. */
export interface FixtureRegister {
    readonly code: string;
    readonly label: string;
}

/** A session that the fixture opened. */
export interface FixtureSession {
    readonly id: number;
    readonly number: string;
}

/** What the fixture is asked: a new register, or a new session with its opening cash and Sales. */
export interface SessionRequest {
    readonly register?: boolean;
    readonly open?: string;
    readonly sales?: readonly ('CASH' | 'BANK_TRANSFER')[];
}

async function token(context: BrowserContext): Promise<string> {
    let cookie = (await context.cookies()).find((candidate) => candidate.name === 'XSRF-TOKEN');

    if (cookie === undefined) {
        await context.request.get('/login');
        cookie = (await context.cookies()).find((candidate) => candidate.name === 'XSRF-TOKEN');
    }

    return decodeURIComponent(cookie?.value ?? '');
}

/** The person's unresolved requests settled and their OPEN session closed, then what the request asks. */
export async function cashSession(context: BrowserContext, person: Person, request: SessionRequest = {}): Promise<{ session: FixtureSession | null; register: FixtureRegister | null }> {
    const answer = await context.request.post('/testing/browser/session', { data: { person, ...request }, headers: { 'X-XSRF-TOKEN': await token(context), Accept: 'application/json' } });

    expect(answer.status(), await answer.text()).toBe(200);

    return (await answer.json()) as { session: FixtureSession | null; register: FixtureRegister | null };
}
