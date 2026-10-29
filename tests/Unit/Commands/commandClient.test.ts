import { describe, expect, it } from 'vitest';
import { classifyCheck, classifyCommand, ERROR_CODES, type Arrived } from '@/lib/commands/answers';
import { CLIENT_WAIT_MS, envelopeOf, recheckDelay, RECORD_CHECK_WAIT_MINUTES, type FrozenRequest } from '@/lib/commands/client';
import { blockingRequest, EXEMPT_FAMILY, familyOf, isLabelledFamily, OPERATION_FAMILIES } from '@/lib/commands/families';
import { keepReference, parseReferences, readReferences, releaseReference, REFERENCES_PREFIX, serializeReferences, type Reference } from '@/lib/commands/references';
import { countOf, indicatorsOf, unionOf, type ReceiptRow } from '@/lib/commands/unresolved';
import { instantOf } from '@/lib/format';

// The command client's logic (AC-009-01 to AC-009-04; INTERACTION_CONTRACTS
// sections 1.3, 1.4 and 2.1; NAVIGATION_CONTRACTS NC-13; SECURITY_MODEL
// SEC-CH-05; TASK-009): every answer class to its client state, the answers
// of a status check, the wait and the repeats, the references in local
// storage per user, the block of a family with its exceptions, and the union
// and number of the unresolved requests.

function body(code: string, outcome = 'REJECTED'): unknown {
    return { outcome, code, messageKey: 'ux.state.validation', params: {}, fields: {}, lines: {} };
}

function arrived(status: number, value: unknown, retryAfter: string | null = null): Arrived {
    return { status, body: value, retryAfter };
}

// An intent and a key made at run time (EXECUTION_CONTEXT EXC-63).
const INTENT = crypto.randomUUID();
const KEY = crypto.randomUUID();
const EPOCH = 'ab'.repeat(32);

function reference(changes: Partial<Reference> = {}): Reference {
    return { operation: 'sale.checkout', family: 'sale', sourceIntentId: INTENT, idempotencyKey: KEY, recoveryEpoch: EPOCH, sentAt: '2026-10-28T03:00:00.000Z', ...changes };
}

/** A storage of the browser, in memory. */
class MemoryStorage implements Storage {
    private readonly items = new Map<string, string>();

    get length(): number {
        return this.items.size;
    }

    clear(): void {
        this.items.clear();
    }

    getItem(key: string): string | null {
        return this.items.get(key) ?? null;
    }

    key(index: number): string | null {
        return [...this.items.keys()][index] ?? null;
    }

    removeItem(key: string): void {
        this.items.delete(key);
    }

    setItem(key: string, value: string): void {
        this.items.set(key, value);
    }
}

describe('the client state of a command answer (section 1.3)', () => {
    it('reads 200 and 201 with their body as COMMITTED, with the result only when it has its fields', () => {
        expect(classifyCommand(arrived(201, { outcome: 'COMMITTED', operation: 'x', commandId: 7, repeated: false, result: { type: 'sale', id: 3, location: '/sales/3' } }))).toEqual({
            kind: 'committed',
            repeated: false,
            result: { type: 'sale', id: 3, location: '/sales/3' },
        });
        expect(classifyCommand(arrived(200, { outcome: 'COMMITTED', repeated: true, result: null }))).toEqual({ kind: 'committed', repeated: true, result: null });
        // A location outside the application is never followed.
        expect(classifyCommand(arrived(201, { outcome: 'COMMITTED', result: { type: 'x', id: 1, location: '//elsewhere.test/' } }))).toEqual({ kind: 'committed', repeated: false, result: { type: 'x', id: 1 } });
    });

    it('takes a refusal state from its code, never from its outcome', () => {
        expect(classifyCommand(arrived(409, body('IDEMPOTENCY_CONFLICT', 'HELD')))).toEqual({ kind: 'held', code: 'IDEMPOTENCY_CONFLICT' });
        expect(classifyCommand(arrived(409, body('EPOCH_STALE', 'HELD')))).toEqual({ kind: 'held', code: 'EPOCH_STALE' });
        // SERVICE_BUSY's body says REJECTED (OPEN_ITEMS T-32 (a)); its state is UNCERTAIN.
        expect(classifyCommand(arrived(503, body('SERVICE_BUSY')))).toEqual({ kind: 'uncertain' });

        for (const code of ['FORBIDDEN', 'NOT_FOUND', 'STALE_VERSION', 'PRICE_CHANGED', 'RECOUNT_REQUIRED', 'INTENT_ABANDONED', 'VALIDATION_FAILED', 'INSUFFICIENT_STOCK']) {
            expect(classifyCommand(arrived(code === 'FORBIDDEN' ? 403 : code === 'NOT_FOUND' ? 404 : 409, body(code))).kind, code).toBe('rejected');
        }
    });

    it('reads no answer, a server error and anything unreadable as UNCERTAIN, never REJECTED', () => {
        expect(classifyCommand(null)).toEqual({ kind: 'uncertain' });
        expect(classifyCommand(arrived(500, null))).toEqual({ kind: 'uncertain' });
        expect(classifyCommand(arrived(502, '<html>'))).toEqual({ kind: 'uncertain' });
        expect(classifyCommand(arrived(201, 'garbled'))).toEqual({ kind: 'uncertain' });
        expect(classifyCommand(arrived(409, null))).toEqual({ kind: 'uncertain' });
        expect(classifyCommand(arrived(422, { code: 'NOT_A_CODE', messageKey: 'x' }))).toEqual({ kind: 'uncertain' });
    });

    it('reads 401, 419, 423 and 429 as not run, with the retry time', () => {
        expect(classifyCommand(arrived(401, body('UNAUTHENTICATED')))).toEqual({ kind: 'not_run', reason: 'signed_out' });
        expect(classifyCommand(arrived(401, null))).toEqual({ kind: 'not_run', reason: 'signed_out' });
        expect(classifyCommand(arrived(419, null))).toEqual({ kind: 'not_run', reason: 'reload' });
        expect(classifyCommand(arrived(423, body('RECENT_AUTH_REQUIRED')))).toEqual({ kind: 'not_run', reason: 'recent_auth' });
        expect(classifyCommand(arrived(429, body('THROTTLED'), '42'))).toEqual({ kind: 'not_run', reason: 'throttled', seconds: 42n });
    });

    it('knows the 32 codes of TECHNICAL_RULES section 4', () => {
        expect(new Set(ERROR_CODES).size).toBe(32);
    });
});

describe('the answer of a status check (section 2.1)', () => {
    it('reads COMMITTED, IN_PROGRESS, NOT_COMMITTED and EPOCH_STALE', () => {
        expect(classifyCheck(arrived(200, { outcome: 'COMMITTED', operation: 'x', commandId: 1, result: null }))).toEqual({ kind: 'committed', result: null });
        expect(classifyCheck(arrived(200, { outcome: 'IN_PROGRESS', operation: 'x' }))).toEqual({ kind: 'in_progress' });
        expect(classifyCheck(arrived(200, { outcome: 'NOT_COMMITTED', operation: 'x' }))).toEqual({ kind: 'not_committed' });
        expect(classifyCheck(arrived(409, body('EPOCH_STALE', 'HELD')))).toEqual({ kind: 'epoch_stale' });
    });

    it('leaves the state as it was for anything else (D-084)', () => {
        for (const answer of [null, arrived(500, null), arrived(503, body('SERVICE_BUSY')), arrived(404, body('NOT_FOUND')), arrived(422, body('VALIDATION_FAILED')), arrived(200, 'x')]) {
            expect(classifyCheck(answer)).toEqual({ kind: 'no_answer' });
        }

        expect(classifyCheck(arrived(401, null))).toEqual({ kind: 'not_run', reason: 'signed_out' });
        expect(classifyCheck(arrived(429, body('THROTTLED'), null))).toEqual({ kind: 'not_run', reason: 'throttled', seconds: 60n });
    });
});

describe('the wait, the repeats and the envelope', () => {
    it('waits 65 seconds and repeats a check after 3, 6 and 12 seconds, then every 15', () => {
        expect(CLIENT_WAIT_MS).toBe(65_000);
        expect([0, 1, 2, 3, 4, 9].map(recheckDelay)).toEqual([3_000, 6_000, 12_000, 15_000, 15_000, 15_000]);
        expect(RECORD_CHECK_WAIT_MINUTES).toBe(10n);
    });

    it('sends the frozen request with the same intent, key and epoch every time', () => {
        const frozen: FrozenRequest = { ...reference({ operation: 'testing.probe', family: 'review_note' }), payload: { note: 'x' }, expected: {} };

        expect(envelopeOf(frozen)).toEqual({ operation: 'testing.probe', version: 1, sourceIntentId: INTENT, idempotencyKey: KEY, recoveryEpoch: EPOCH, expected: {}, payload: { note: 'x' } });
        expect(envelopeOf(frozen)).toEqual(envelopeOf(frozen));
    });

    it('writes the time of a reference as the server writes an instant', () => {
        expect(instantOf(0)).toBe('1970-01-01T00:00:00.000Z');
        expect(instantOf(1793180027123)).toBe('2026-10-28T09:33:47.123Z');
        expect(() => instantOf(-1)).toThrow(RangeError);
        expect(() => instantOf(1.5)).toThrow(RangeError);
    });
});

describe('the references in local storage (SEC-CH-05)', () => {
    it('keeps only operation, family, intent, key, epoch and time, per user, and drops a damaged line', () => {
        const store = new MemoryStorage();

        keepReference(7, reference(), store);
        keepReference(7, reference({ operation: 'user.create', family: 'users', sourceIntentId: KEY }), store);
        keepReference(8, reference({ sourceIntentId: KEY }), store);

        expect(store.getItem(`${REFERENCES_PREFIX}7`)).toBe(`sale.checkout sale ${INTENT} ${KEY} ${EPOCH} 2026-10-28T03:00:00.000Z\nuser.create users ${KEY} ${KEY} ${EPOCH} 2026-10-28T03:00:00.000Z`);
        expect(readReferences(7, store).map((kept) => kept.operation)).toEqual(['sale.checkout', 'user.create']);
        // Another user's references are never read.
        expect(readReferences(8, store)).toEqual([reference({ sourceIntentId: KEY })]);
        expect(readReferences(9, store)).toEqual([]);

        // The same intent replaces its earlier reference; release removes it.
        keepReference(7, reference({ sentAt: '2026-10-28T03:01:00.000Z' }), store);
        expect(readReferences(7, store).map((kept) => kept.sentAt)).toEqual(['2026-10-28T03:00:00.000Z', '2026-10-28T03:01:00.000Z']);
        releaseReference(7, reference(), store);
        releaseReference(7, { operation: 'user.create', sourceIntentId: KEY }, store);
        expect(store.getItem(`${REFERENCES_PREFIX}7`)).toBeNull();

        expect(parseReferences(`garbage\nsale.checkout sale ${INTENT} ${KEY} ${EPOCH} 2026-10-28T03:00:00.000Z\nsale.checkout sale not-a-uuid ${KEY} ${EPOCH} 2026-10-28T03:00:00.000Z`)).toEqual([reference()]);
        expect(serializeReferences([reference({ recoveryEpoch: 'zz' })])).toBe('');
    });
});

describe('the block of NC-13', () => {
    const unresolved = [{ operation: 'sale.checkout', family: 'sale' }];

    it('blocks a new command of the family and no other', () => {
        expect(blockingRequest(unresolved, 'sale.checkout', 'sale')).toEqual(unresolved[0]);
        expect(blockingRequest(unresolved, 'receiving.confirm', 'stock_posting')).toBeNull();
        expect(blockingRequest([], 'sale.checkout', 'sale')).toBeNull();
    });

    it('never blocks print, reprint or export, and a print request blocks nothing', () => {
        expect(blockingRequest([{ operation: 'document.print_receipt', family: EXEMPT_FAMILY }], 'document.print_receipt', EXEMPT_FAMILY)).toBeNull();
        expect(blockingRequest(unresolved, 'document.print_receipt', EXEMPT_FAMILY)).toBeNull();
    });

    it('keeps deactivation and link revocation available unless the unresolved request is that same operation (NC-13 (d))', () => {
        const create = [{ operation: 'user.create', family: 'users' }];

        expect(blockingRequest(create, 'user.update', 'users')).toEqual(create[0]);
        expect(blockingRequest(create, 'user.deactivate', 'users')).toBeNull();
        expect(blockingRequest(create, 'user.revoke_link', 'users')).toBeNull();
        expect(blockingRequest([{ operation: 'user.deactivate', family: 'users' }], 'user.deactivate', 'users')).not.toBeNull();
        expect(blockingRequest([{ operation: 'user.revoke_link', family: 'users' }], 'user.revoke_link', 'users')).not.toBeNull();
        expect(blockingRequest([{ operation: 'user.revoke_link', family: 'users' }], 'user.deactivate', 'users')).toBeNull();
    });

    it('knows the family of every operation and labels all but print', () => {
        expect(Object.keys(OPERATION_FAMILIES)).toHaveLength(49);
        expect(familyOf('sale.checkout')).toBe('sale');
        expect(familyOf('testing.probe')).toBeNull();
        expect(isLabelledFamily('print')).toBe(false);
        expect(isLabelledFamily('review_note')).toBe(true);
    });
});

describe('the unresolved requests and their number', () => {
    const row = (changes: Partial<ReceiptRow> = {}): ReceiptRow => ({
        operation: 'sale.checkout',
        family: 'sale',
        sourceIntentId: INTENT,
        idempotencyKey: KEY,
        epoch: 'current',
        receivedAt: '2026-10-28T03:00:01.000000Z',
        outcome: 'PENDING',
        ...changes,
    });

    it('is the union of the references and the receipts, one per operation and intent, without print', () => {
        const union = unionOf(
            [reference(), reference({ operation: 'document.print_receipt', family: 'print', sourceIntentId: KEY })],
            [row({ outcome: 'HELD' }), row({ operation: 'user.create', family: 'users', sourceIntentId: KEY })],
        );

        expect(union.map((entry) => [entry.operation, entry.local, entry.receipt?.outcome ?? null, entry.recoveryEpoch])).toEqual([
            ['sale.checkout', true, 'HELD', EPOCH],
            ['user.create', false, 'PENDING', null],
        ]);
    });

    it('counts the server receipts and the references that no receipt stands for, and is never zero for a failed read', () => {
        const union = unionOf([reference(), reference({ sourceIntentId: KEY })], [row()]);

        expect(countOf(1, union)).toBe(2);
        expect(countOf(0, [])).toBe(0);
        expect(countOf(null, [])).toBeNull();
        expect(countOf(null, union)).toBe(1);
    });

    it("reads RD-03's answer: 401 ends the sign-in session, anything unreadable is a failed read, never a zero", () => {
        const arrived = (status: number, body: unknown): Arrived => ({ status, body, retryAfter: null });

        expect(indicatorsOf(arrived(200, { unresolvedRequests: 2, asOf: '2026-10-28T03:00:00.000000Z' }))).toEqual({ kind: 'read', count: 2, asOf: '2026-10-28T03:00:00.000000Z' });
        expect(indicatorsOf(arrived(401, { code: 'UNAUTHENTICATED' }))).toEqual({ kind: 'ended' });
        expect(indicatorsOf(arrived(419, null))).toEqual({ kind: 'failed' });
        expect(indicatorsOf(arrived(503, null))).toEqual({ kind: 'failed' });
        expect(indicatorsOf(arrived(200, { unresolvedRequests: -1, asOf: '2026-10-28T03:00:00.000000Z' }))).toEqual({ kind: 'failed' });
        expect(indicatorsOf(arrived(200, { unresolvedRequests: 1 }))).toEqual({ kind: 'failed' });
        expect(indicatorsOf(null)).toEqual({ kind: 'failed' });
    });
});
