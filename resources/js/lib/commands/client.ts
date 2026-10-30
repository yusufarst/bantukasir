import { classifyCheck, classifyCommand, type Arrived, type CheckAnswer, type CommandAnswer } from '@/lib/commands/answers';
import type { Reference } from '@/lib/commands/references';
import { instantOf } from '@/lib/format';

// The command client (INTERACTION_CONTRACTS sections 1.1 to 1.3 and 2.1;
// ADR-007 sections 1 and 5; ROUTE_CONTRACTS RT-10; TASK-009 scope item 1):
// the final action freezes the operation, a new intent and key, the payload
// and the expected versions, with the recovery epoch of the shared props;
// the frozen request is sent, and repeated by "Kirim ulang" with the same
// intent and key; the client waits at least 65 seconds before it reads a
// missing answer as UNCERTAIN; "Cek status" calls RC-50 and, while the
// original is still running, repeats itself after 3, 6 and 12 seconds and
// then every 15 seconds as background requests.

/** The application limit of 60 seconds plus a margin (TECHNICAL_RULES section 9). */
export const CLIENT_WAIT_MS = 65_000;

/** "Masih diproses" after a few seconds of sending (UXS-06). */
export const STILL_AFTER_MS = 4_000;

/** The automatic repeats of a status check that answered IN_PROGRESS (INTERACTION_CONTRACTS section 2.1). */
export const RECHECK_AFTER_MS: readonly number[] = [3_000, 6_000, 12_000];

export const RECHECK_EVERY_MS = 15_000;

/** The wait before the automatic repeat after `done` repeats: 3, 6 and 12 seconds, then every 15 seconds. */
export function recheckDelay(done: number): number {
    return RECHECK_AFTER_MS[done] ?? RECHECK_EVERY_MS;
}

/** The record-check wait of TECHNICAL_RULES TR-TIME-03, in minutes (INTERACTION_CONTRACTS section 2.4). */
export const RECORD_CHECK_WAIT_MINUTES = 10n;

/** The command a page sends: its operation and family (families.ts). */
export interface CommandDefinition {
    readonly operation: string;
    readonly family: string;
}

/** A request frozen at the final action (ADR-007 section 5); its payload stays in page memory only. */
export interface FrozenRequest extends Reference {
    readonly payload: Readonly<Record<string, unknown>>;
    readonly expected: Readonly<Record<string, unknown>>;
}

/** A new random UUID (version 4) from the browser's secure random source. */
export function newUuid(): string {
    return crypto.randomUUID();
}

/** Freezes the request at the final action: a new intent and key, the epoch the page received, the time. */
export function freeze(command: CommandDefinition, payload: Readonly<Record<string, unknown>>, expected: Readonly<Record<string, unknown>>, recoveryEpoch: string): FrozenRequest {
    return {
        operation: command.operation,
        family: command.family,
        sourceIntentId: newUuid(),
        idempotencyKey: newUuid(),
        recoveryEpoch,
        sentAt: instantOf(Date.now()),
        payload,
        expected,
    };
}

/** The envelope of INTERACTION_CONTRACTS section 1.1, the same for every repeat of the frozen request. */
export function envelopeOf(frozen: FrozenRequest): Record<string, unknown> {
    return {
        operation: frozen.operation,
        version: 1,
        sourceIntentId: frozen.sourceIntentId,
        idempotencyKey: frozen.idempotencyKey,
        recoveryEpoch: frozen.recoveryEpoch,
        expected: frozen.expected,
        payload: frozen.payload,
    };
}

/**
 * The frozen request as a multipart form, when its payload holds a file — RC-25
 * and RC-32, whose envelope travels as form fields beside the file
 * (INTERACTION_CONTRACTS section 1.1) — otherwise null, and the body is JSON.
 * Each field is named by its path (`expected[revisionId]`, `payload[logo]`);
 * an absent value (null) is an empty field, which the server reads as null;
 * the same frozen request gives the same form, file included, on every repeat.
 */
export function formOf(frozen: FrozenRequest): FormData | null {
    if (!Object.values(frozen.payload).some((value) => value instanceof Blob)) {
        return null;
    }

    const form = new FormData();
    const add = (name: string, value: unknown): void => {
        if (value instanceof Blob) {
            form.append(name, value);
        } else if (typeof value === 'string' || typeof value === 'number' || typeof value === 'bigint' || typeof value === 'boolean') {
            form.append(name, String(value));
        } else if (value === null || value === undefined) {
            form.append(name, '');
        } else {
            throw new Error('A multipart command carries only texts, whole numbers, booleans and files.');
        }
    };

    add('operation', frozen.operation);
    add('version', 1);
    add('sourceIntentId', frozen.sourceIntentId);
    add('idempotencyKey', frozen.idempotencyKey);
    add('recoveryEpoch', frozen.recoveryEpoch);

    for (const [name, value] of Object.entries(frozen.expected)) {
        add(`expected[${name}]`, value);
    }

    for (const [name, value] of Object.entries(frozen.payload)) {
        add(`payload[${name}]`, value);
    }

    return form;
}

/** The CSRF token of the session, from the cookie that the framework sets for scripts. */
function xsrfToken(): string {
    const prefix = 'XSRF-TOKEN=';
    const cookie = document.cookie.split('; ').find((part) => part.startsWith(prefix));

    return cookie === undefined ? '' : decodeURIComponent(cookie.slice(prefix.length));
}

/**
 * The event that the application answered a request the user started, which
 * counted as user activity on the server: the shell's idle limit counts from
 * its answer, as from a user-activity request (SEC-SESS-02; pull request #15's
 * review). A background request never sends it (RT-10).
 */
export const USER_REQUEST_EVENT = 'bk:user-request-answered';

/**
 * Whether an answer of a signed-in route shows that the server counted its
 * request as user activity: the sign-in session's check, which restarts the
 * idle limit, runs right after authentication and before every other check
 * of RT-04, so every answer that the application routed to an existing
 * signed-in route, but 401, passed it. The client sends only small,
 * same-origin requests to such routes; a 400, 404 or 413 of the global
 * checks before routing, which touch nothing, would be a programming error.
 * A 5xx answer may come from the proxy or the maintenance page, before the
 * application, and is not counted: not counting it can only make the shell
 * leave earlier than counting it would, never later.
 */
export function countsAsActivity(status: number): boolean {
    return status !== 401 && status >= 200 && status < 500;
}

/**
 * Sends a JSON request and reads its answer, or null when none arrived within
 * the wait: a network error, a timeout or a body that cannot be read as JSON
 * where a body was expected. A background request carries the marker of
 * RT-10, so that it never counts as user activity; any other request that the
 * application answered tells the shell (USER_REQUEST_EVENT).
 */
export async function requestJson(method: 'GET' | 'POST', url: string, body: unknown, options: { background?: boolean; waitMs?: number; signal?: AbortSignal } = {}): Promise<Arrived | null> {
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), options.waitMs ?? CLIENT_WAIT_MS);

    // A caller's signal ends the request as the wait does.
    if (options.signal?.aborted === true) {
        controller.abort();
    } else {
        options.signal?.addEventListener('abort', () => controller.abort(), { once: true });
    }
    const headers: Record<string, string> = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrfToken() };
    const form = body instanceof FormData;

    // A form sets its own multipart type with its boundary.
    if (body !== undefined && !form) {
        headers['Content-Type'] = 'application/json';
    }

    if (options.background === true) {
        headers['X-Background-Request'] = '1';
    }

    try {
        const answer = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers,
            signal: controller.signal,
            ...(body === undefined ? {} : { body: form ? body : JSON.stringify(body) }),
        });

        if (options.background !== true && countsAsActivity(answer.status)) {
            window.dispatchEvent(new Event(USER_REQUEST_EVENT));
        }

        let parsed: unknown = null;

        try {
            parsed = answer.status === 204 ? null : await answer.json();
        } catch {
            parsed = null;
        }

        return { status: answer.status, body: parsed, retryAfter: answer.headers.get('Retry-After') };
    } catch {
        return null;
    } finally {
        window.clearTimeout(timer);
    }
}

/** Sends the frozen request to its RC route and reads the client state of its answer. */
export async function sendCommand(frozen: FrozenRequest): Promise<CommandAnswer> {
    return classifyCommand(await requestJson('POST', `/commands/${frozen.operation}`, formOf(frozen) ?? envelopeOf(frozen)));
}

/**
 * The status check of an intent (RC-50): its operation and intent, and the
 * epoch it was sent under, which the reference keeps; a request that only the
 * server knows is checked with the page's epoch, and the server decides from
 * its own receipts (TASK-008 reading 9a).
 */
export async function checkStatus(request: { operation: string; sourceIntentId: string; recoveryEpoch: string }, background = false): Promise<CheckAnswer> {
    return classifyCheck(
        await requestJson('POST', '/commands/resolve', { operation: request.operation, sourceIntentId: request.sourceIntentId, recoveryEpoch: request.recoveryEpoch }, { background }),
    );
}

/** The event that a command or a status check reached a conclusion, after which the shell reads its indicators again (INTERACTION_CONTRACTS section 3.3). */
export const SETTLED_EVENT = 'bk:command-settled';

export function announceSettled(): void {
    window.dispatchEvent(new Event(SETTLED_EVENT));
}
