import { useEffect, useSyncExternalStore } from 'react';
import { EXEMPT_FAMILY } from '@/lib/commands/families';
import { onReferencesChanged, readReferences, sameIntent } from '@/lib/commands/references';
import type { Arrived } from '@/lib/commands/answers';
import { requestJson, SETTLED_EVENT } from '@/lib/commands/client';

// The user's unresolved requests, as the shell, the block of NC-13 and UXP-006
// read them (INTERACTION_CONTRACTS sections 1.4, 2.2 and 3.3; ADR-009 section
// 2; ROUTE_CONTRACTS RD-03 and RD-04; TASK-009 scope items 3 and 7): the
// union of the references in this browser and the user's own request
// receipts on the server that are PENDING or HELD (RD-04, scope=own), one
// entry per operation and intent; print requests are left out, being exempt
// (NC-13). The shell reads RD-03 when the page opens, at least every 30
// seconds while the page is visible, when the tab regains focus and after
// every command, as background requests (RT-10), and RD-04 with it whenever
// RD-03 counts a receipt; a page of a family reads RD-04 when it opens and
// before its final action sends. A failed read keeps the last data with its
// time (UXS-21) and never shows zero.

/** An unresolved request of the user: a reference of this browser, a receipt of the server, or both. */
export interface UnresolvedRequest {
    readonly operation: string;
    readonly family: string;
    readonly sourceIntentId: string;
    readonly idempotencyKey: string;
    /** The epoch the reference kept; null for a request that only the server knows. */
    readonly recoveryEpoch: string | null;
    /** The instant, in UTC: the reference's sending time, else the receipt's received time. */
    readonly at: string;
    readonly local: boolean;
    /** The receipt's outcome and epoch marker, when the server listed it. */
    readonly receipt: { readonly outcome: 'PENDING' | 'HELD'; readonly epoch: 'current' | 'earlier' } | null;
}

/** A row of RD-04 (TASK-008 reading 11), as far as the client reads it. */
export interface ReceiptRow {
    readonly operation: string;
    readonly family: string;
    readonly sourceIntentId: string;
    readonly idempotencyKey: string;
    readonly epoch: 'current' | 'earlier';
    readonly receivedAt: string;
    readonly outcome: 'PENDING' | 'HELD';
    readonly user?: { readonly id: number; readonly displayName: string };
}

export interface ReceiptPage {
    readonly rows: readonly ReceiptRow[];
    readonly nextCursor: string | null;
}

/** The interval of the shell's refresh while the page is visible (BR-INV-41). */
export const SHELL_REFRESH_MS = 30_000;

function isRecord(value: unknown): value is Readonly<Record<string, unknown>> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function rowOf(value: unknown): ReceiptRow | null {
    if (!isRecord(value)) {
        return null;
    }

    const { operation, family, sourceIntentId, idempotencyKey, epoch, receivedAt, outcome, user } = value;

    if (
        typeof operation !== 'string' ||
        typeof family !== 'string' ||
        typeof sourceIntentId !== 'string' ||
        typeof idempotencyKey !== 'string' ||
        (epoch !== 'current' && epoch !== 'earlier') ||
        typeof receivedAt !== 'string' ||
        (outcome !== 'PENDING' && outcome !== 'HELD')
    ) {
        return null;
    }

    const owner = isRecord(user) && typeof user.id === 'number' && typeof user.displayName === 'string' ? { user: { id: user.id, displayName: user.displayName } } : {};

    return { operation, family, sourceIntentId, idempotencyKey, epoch, receivedAt, outcome, ...owner };
}

/** A page of RD-04, or null when it could not be read. */
export async function readReceipts(scope: 'own' | 'all', after: string | null = null, background = false, signal?: AbortSignal): Promise<ReceiptPage | null> {
    const query = new URLSearchParams({ scope, perPage: '100' });

    if (after !== null) {
        query.set('after', after);
    }

    const arrived = await requestJson('GET', `/data/command-requests?${query.toString()}`, undefined, { background, waitMs: 30_000, ...(signal === undefined ? {} : { signal }) });

    if (arrived === null || arrived.status !== 200 || !isRecord(arrived.body) || !Array.isArray(arrived.body.rows)) {
        return null;
    }

    const rows = arrived.body.rows.map(rowOf);

    if (rows.some((row) => row === null)) {
        return null;
    }

    return { rows: rows.filter((row): row is ReceiptRow => row !== null), nextCursor: typeof arrived.body.nextCursor === 'string' ? arrived.body.nextCursor : null };
}

/**
 * The union of the references and the receipts, one entry per operation and
 * intent, without print requests, the references first in their order.
 */
export function unionOf(references: readonly { operation: string; family: string; sourceIntentId: string; idempotencyKey: string; recoveryEpoch: string; sentAt: string }[], rows: readonly ReceiptRow[]): UnresolvedRequest[] {
    const union: UnresolvedRequest[] = [];

    for (const reference of references) {
        if (reference.family === EXEMPT_FAMILY) {
            continue;
        }

        const row = rows.find((candidate) => sameIntent(candidate, reference));
        union.push({
            operation: reference.operation,
            family: reference.family,
            sourceIntentId: reference.sourceIntentId,
            idempotencyKey: reference.idempotencyKey,
            recoveryEpoch: reference.recoveryEpoch,
            at: reference.sentAt,
            local: true,
            receipt: row === undefined ? null : { outcome: row.outcome, epoch: row.epoch },
        });
    }

    for (const row of rows) {
        if (row.family === EXEMPT_FAMILY || union.some((entry) => sameIntent(entry, row))) {
            continue;
        }

        union.push({
            operation: row.operation,
            family: row.family,
            sourceIntentId: row.sourceIntentId,
            idempotencyKey: row.idempotencyKey,
            recoveryEpoch: null,
            at: row.receivedAt,
            local: false,
            receipt: { outcome: row.outcome, epoch: row.epoch },
        });
    }

    return union;
}

/**
 * The number the shell shows: the server's count of the user's unresolved
 * receipts (RD-03) and each reference of this browser that no receipt of
 * the last RD-04 read stands for (a request still on its way, or refused
 * before its receipt). It is null while the server's count is unknown, so
 * that no zero is ever shown for a failed read.
 */
export function countOf(serverCount: number | null, union: readonly UnresolvedRequest[]): number | null {
    const localOnly = union.filter((entry) => entry.local && entry.receipt === null).length;

    if (serverCount === null) {
        return localOnly > 0 ? localOnly : null;
    }

    return serverCount + localOnly;
}

// ---------------------------------------------------------------------------
// The store of the signed-in user, one per document
// ---------------------------------------------------------------------------

export interface UnresolvedState {
    readonly userId: number | null;
    readonly union: readonly UnresolvedRequest[];
    /** RD-03's count of the last successful read, null before one. */
    readonly serverCount: number | null;
    /** The as-of time of the last successful read of RD-03, null before one. */
    readonly asOf: string | null;
    /** Whether the last read failed (UXS-21 with asOf, UXS-22 without). */
    readonly failed: boolean;
}

let state: UnresolvedState = { userId: null, union: [], serverCount: null, asOf: null, failed: false };
let rows: readonly ReceiptRow[] = [];
const listeners = new Set<() => void>();
let reading: Promise<void> | null = null;

function publish(next: Partial<UnresolvedState>): void {
    const userId = next.userId ?? state.userId;
    const union = userId === null ? [] : unionOf(readReferences(userId), rows);
    state = { ...state, ...next, union };
    listeners.forEach((listener) => listener());
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

/** The user whose requests the store holds; another user's are never kept. */
export function setUser(userId: number): void {
    if (state.userId !== userId) {
        rows = [];
        state = { userId, union: [], serverCount: null, asOf: null, failed: false };
    }

    publish({ userId });
}

/** Reads the user's own receipts again (RD-04); a failed read keeps the last ones. */
export async function refreshReceipts(background = true, signal?: AbortSignal): Promise<boolean> {
    const page = await readReceipts('own', null, background, signal);

    if (page !== null && signal?.aborted !== true) {
        rows = page.rows;
        publish({});
    }

    return page !== null;
}

/**
 * A request the server just reported resolved: its receipt is no longer
 * listed, so that nothing blocks or shows it until the next read confirms it
 * (INTERACTION_CONTRACTS section 2.1).
 */
export function forgetReceipt(request: { operation: string; sourceIntentId: string }): void {
    const left = rows.filter((row) => !sameIntent(row, request));
    const gone = rows.length - left.length;
    rows = left;
    publish({ serverCount: state.serverCount === null ? null : Math.max(0, state.serverCount - gone) });
}

/** What an answer of RD-03 means for the shell. */
export type IndicatorsAnswer = { readonly kind: 'read'; readonly count: number; readonly asOf: string } | { readonly kind: 'ended' } | { readonly kind: 'failed' };

/**
 * Reads an answer of RD-03: 401 means the sign-in session has ended (UXS-09,
 * INTERACTION_CONTRACTS section 1.3), so the page leaves rather than showing
 * stale data (TASK-009); anything else that is
 * not a readable count and time is a failed read (UXS-21, UXS-22).
 */
export function indicatorsOf(arrived: Arrived | null): IndicatorsAnswer {
    if (arrived?.status === 401) {
        return { kind: 'ended' };
    }

    const body = arrived?.status === 200 && isRecord(arrived.body) ? arrived.body : null;
    const count = body !== null && typeof body.unresolvedRequests === 'number' && Number.isSafeInteger(body.unresolvedRequests) && body.unresolvedRequests >= 0 ? body.unresolvedRequests : null;
    const asOf = body !== null && typeof body.asOf === 'string' ? body.asOf : null;

    return count === null || asOf === null ? { kind: 'failed' } : { kind: 'read', count, asOf };
}

/** What the shell does when a read of its indicators finds the sign-in session ended. */
let onEnded: (() => void) | null = null;

/** Aborted once the page leaves the user's sign-in session; it ends the shell's read in flight. */
const leaving = new AbortController();

/**
 * Stops the shell's refresh for the rest of this document, because the page
 * is leaving the user's sign-in session — a sign-out, its end, another tab's:
 * the read in flight is cancelled and none starts, so that nothing waits for
 * or reads an answer for a sign-in session that is ending.
 */
export function stopIndicators(): void {
    leaving.abort();
}

/** The shell's refresh: RD-03, and RD-04 whenever it counts a receipt. */
export function refreshIndicators(): Promise<void> {
    if (leaving.signal.aborted) {
        return Promise.resolve();
    }

    if (reading !== null) {
        return reading;
    }

    reading = (async () => {
        const answer = indicatorsOf(await requestJson('GET', '/data/shell', undefined, { background: true, waitMs: 30_000, signal: leaving.signal }));

        if (leaving.signal.aborted) {
            return;
        }

        if (answer.kind === 'ended') {
            onEnded?.();

            return;
        }

        if (answer.kind === 'failed') {
            publish({ failed: true });

            return;
        }

        const { count, asOf } = answer;

        if (count === 0) {
            rows = [];
        } else if (!(await refreshReceipts(true, leaving.signal))) {
            if (!leaving.signal.aborted) {
                publish({ failed: true });
            }

            return;
        }

        publish({ serverCount: count, asOf, failed: false });
    })().finally(() => {
        reading = null;
    });

    return reading;
}

/** The store's state now, with the references as this browser holds them. */
export function currentUnresolved(): UnresolvedState {
    publish({});

    return state;
}

/** The store's state for a component, kept current with the references of every tab. */
export function useUnresolved(): UnresolvedState {
    useEffect(() => onReferencesChanged(() => publish({})), []);

    return useSyncExternalStore(subscribe, () => state);
}

/**
 * Runs the shell's refresh while the page is open: now, every 30 seconds while
 * the page is visible, when the tab regains focus and after each command;
 * `ended` runs when a read finds the sign-in session ended (401).
 */
export function useShellRefresh(userId: number, ended: () => void): void {
    useEffect(() => {
        onEnded = ended;

        return () => {
            if (onEnded === ended) {
                onEnded = null;
            }
        };
    }, [ended]);

    useEffect(() => {
        setUser(userId);
        void refreshIndicators();

        const refreshVisible = (): void => {
            if (document.visibilityState === 'visible') {
                void refreshIndicators();
            }
        };
        const timer = window.setInterval(refreshVisible, SHELL_REFRESH_MS);
        const settled = (): void => void refreshIndicators();

        window.addEventListener('focus', refreshVisible);
        document.addEventListener('visibilitychange', refreshVisible);
        window.addEventListener(SETTLED_EVENT, settled);

        return () => {
            window.clearInterval(timer);
            window.removeEventListener('focus', refreshVisible);
            document.removeEventListener('visibilitychange', refreshVisible);
            window.removeEventListener(SETTLED_EVENT, settled);
        };
    }, [userId]);
}
