import { retrySeconds } from '@/lib/answers';

// How the client reads the answer of a command and of a status check
// (INTERACTION_CONTRACTS sections 1.2, 1.3 and 2.1; ADR-007 section 5;
// ROUTE_CONTRACTS RT-07, RC-50; TECHNICAL_RULES section 4; TASK-009 scope
// item 1). A refusal's state is taken from its `code`, never from `outcome`,
// which RT-07 limits to REJECTED and HELD (OPEN_ITEMS T-32 (a)); the status
// is used only when no code can be read. Time alone never makes a request
// REJECTED: a request without an answer is UNCERTAIN.

/** What arrived: the status and the body read as JSON, or null when nothing arrived. */
export interface Arrived {
    readonly status: number;
    readonly body: unknown;
    readonly retryAfter: string | null;
}

/** The result of a committed command (INTERACTION_CONTRACTS section 1.2), null when the viewer may not see it. */
export interface CommandResult {
    readonly type: string;
    readonly id: number | string;
    readonly number?: string;
    readonly location?: string;
}

/** A refusal's error body (RT-07), with only its safe values. */
export interface Refusal {
    readonly code: string;
    readonly messageKey: string;
    readonly params: Readonly<Record<string, unknown>>;
    readonly fields: Readonly<Record<string, unknown>>;
    readonly lines: Readonly<Record<string, unknown>>;
}

export type HeldCode = 'IDEMPOTENCY_CONFLICT' | 'EPOCH_STALE';

/** A one-time link in the answer that committed it, and only there (SEC-ACC-05; RC-35, RC-41): held in page memory, never stored. */
export interface HandedLink {
    readonly url: string;
    readonly expiresAt: string;
}

/** Nothing ran: sign in, reload, confirm the password, or wait (INTERACTION_CONTRACTS section 1.3, "Not run"). */
export type NotRun = { readonly reason: 'signed_out' } | { readonly reason: 'reload' } | { readonly reason: 'recent_auth' } | { readonly reason: 'throttled'; readonly seconds: bigint };

export type CommandAnswer =
    | { readonly kind: 'committed'; readonly repeated: boolean; readonly result: CommandResult | null; readonly link?: HandedLink }
    | { readonly kind: 'rejected'; readonly refusal: Refusal }
    | { readonly kind: 'uncertain' }
    | { readonly kind: 'held'; readonly code: HeldCode }
    | ({ readonly kind: 'not_run' } & NotRun);

export type CheckAnswer =
    | { readonly kind: 'committed'; readonly result: CommandResult | null }
    | { readonly kind: 'in_progress' }
    | { readonly kind: 'not_committed' }
    | { readonly kind: 'epoch_stale' }
    | { readonly kind: 'no_answer' }
    | ({ readonly kind: 'not_run' } & NotRun);

/** The 32 codes of TECHNICAL_RULES section 4, as App\Domain\Commands\ErrorCode names them. */
export const ERROR_CODES = [
    'UNAUTHENTICATED', 'CSRF_FAILED', 'RECENT_AUTH_REQUIRED', 'FORBIDDEN', 'NOT_FOUND', 'IDEMPOTENCY_CONFLICT', 'EPOCH_STALE', 'INTENT_ABANDONED',
    'STALE_VERSION', 'PRICE_CHANGED', 'RECOUNT_REQUIRED', 'VALIDATION_FAILED', 'INSUFFICIENT_STOCK', 'PRODUCT_INACTIVE', 'CATEGORY_INACTIVE', 'NO_OPEN_SESSION',
    'SESSION_NOT_OPEN', 'ONBOARDING_FREEZE', 'POSTING_BLOCKED', 'METHOD_NOT_ALLOWED', 'TENDER_INSUFFICIENT', 'REFUND_BOUND_EXCEEDED', 'RETURN_BOUND_EXCEEDED',
    'DRAWER_CASH_INSUFFICIENT', 'BARCODE_NOT_ELIGIBLE', 'DUPLICATE_SKU', 'DUPLICATE_BARCODE', 'CSV_INVALID', 'REPLAY_REJECTED', 'LINK_INVALID', 'THROTTLED',
    'SERVICE_BUSY',
] as const;

function isRecord(value: unknown): value is Readonly<Record<string, unknown>> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function recordOf(value: unknown): Readonly<Record<string, unknown>> {
    return isRecord(value) ? value : {};
}

/** The result of a committed answer, when it has the fields of section 1.2. */
function resultOf(value: unknown): CommandResult | null {
    if (!isRecord(value) || typeof value.type !== 'string' || (typeof value.id !== 'number' && typeof value.id !== 'string')) {
        return null;
    }

    return {
        type: value.type,
        id: value.id,
        ...(typeof value.number === 'string' ? { number: value.number } : {}),
        ...(typeof value.location === 'string' && value.location.startsWith('/') && !value.location.startsWith('//') ? { location: value.location } : {}),
    };
}

/** The one-time link of a committed answer, when it has its two fields. */
function linkOf(value: unknown): HandedLink | null {
    return isRecord(value) && typeof value.url === 'string' && typeof value.expiresAt === 'string' ? { url: value.url, expiresAt: value.expiresAt } : null;
}

/** The error body of RT-07, when it names one of the 32 codes. */
export function refusalOf(body: unknown): Refusal | null {
    if (!isRecord(body) || typeof body.code !== 'string' || !(ERROR_CODES as readonly string[]).includes(body.code) || typeof body.messageKey !== 'string') {
        return null;
    }

    return { code: body.code, messageKey: body.messageKey, params: recordOf(body.params), fields: recordOf(body.fields), lines: recordOf(body.lines) };
}

/** The answers that ran nothing, by their code or, without a readable code, their status. */
function notRun(code: string | null, status: number, retryAfter: string | null): NotRun | null {
    if (code === 'UNAUTHENTICATED' || (code === null && status === 401)) {
        return { reason: 'signed_out' };
    }

    // 419 is the framework's own answer, read as CSRF_FAILED (section 1.2).
    if (code === 'CSRF_FAILED' || status === 419) {
        return { reason: 'reload' };
    }

    if (code === 'RECENT_AUTH_REQUIRED' || (code === null && status === 423)) {
        return { reason: 'recent_auth' };
    }

    if (code === 'THROTTLED' || (code === null && status === 429)) {
        return { reason: 'throttled', seconds: retrySeconds(retryAfter) };
    }

    return null;
}

/**
 * The client state of a command's answer (INTERACTION_CONTRACTS section 1.3):
 * COMMITTED for 200 or 201 with its body; HELD for IDEMPOTENCY_CONFLICT and
 * EPOCH_STALE; UNCERTAIN for no answer, SERVICE_BUSY, any 5xx and anything
 * unreadable; not run for 401, 419, 423 and 429; REJECTED for every other
 * code — 403, 404, the other 409 codes, INTENT_ABANDONED and 422.
 */
export function classifyCommand(arrived: Arrived | null): CommandAnswer {
    if (arrived === null) {
        return { kind: 'uncertain' };
    }

    const { status, body } = arrived;

    if ((status === 200 || status === 201) && isRecord(body) && body.outcome === 'COMMITTED') {
        const link = linkOf(body.link);

        return { kind: 'committed', repeated: body.repeated === true, result: resultOf(body.result), ...(link === null ? {} : { link }) };
    }

    const refusal = refusalOf(body);
    const stopped = notRun(refusal?.code ?? null, status, arrived.retryAfter);

    if (stopped !== null) {
        return { kind: 'not_run', ...stopped };
    }

    if (refusal === null || status >= 500 || status < 400) {
        return { kind: 'uncertain' };
    }

    if (refusal.code === 'IDEMPOTENCY_CONFLICT' || refusal.code === 'EPOCH_STALE') {
        return { kind: 'held', code: refusal.code };
    }

    if (refusal.code === 'SERVICE_BUSY') {
        return { kind: 'uncertain' };
    }

    return { kind: 'rejected', refusal };
}

/**
 * The answer of a status check (INTERACTION_CONTRACTS section 2.1): COMMITTED,
 * IN_PROGRESS or NOT_COMMITTED; EPOCH_STALE, which keeps the request held;
 * not run for 401, 419 and 429; anything else — no answer, a server error,
 * NOT_FOUND, an unreadable body — is no answer, which leaves the request in
 * the state it was in (D-084).
 */
export function classifyCheck(arrived: Arrived | null): CheckAnswer {
    if (arrived === null) {
        return { kind: 'no_answer' };
    }

    const { status, body } = arrived;

    if (status === 200 && isRecord(body)) {
        switch (body.outcome) {
            case 'COMMITTED':
                return { kind: 'committed', result: resultOf(body.result) };
            case 'IN_PROGRESS':
                return { kind: 'in_progress' };
            case 'NOT_COMMITTED':
                return { kind: 'not_committed' };
            default:
                return { kind: 'no_answer' };
        }
    }

    const refusal = refusalOf(body);

    if (status === 409 && refusal?.code === 'EPOCH_STALE') {
        return { kind: 'epoch_stale' };
    }

    const stopped = notRun(refusal?.code ?? null, status, arrived.retryAfter);

    if (stopped !== null && stopped.reason !== 'recent_auth') {
        return { kind: 'not_run', ...stopped };
    }

    return { kind: 'no_answer' };
}
