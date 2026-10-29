// The minimal command references of the browser (SECURITY_MODEL SEC-CH-05;
// ADR-007 section 5; INTERACTION_CONTRACTS section 1.4; TASK-009 scope item
// 2): for each request whose outcome is not known yet, its operation, intent,
// key and time, with the two values that the request itself carried and that
// its status check needs — its family, read from the operation, and the
// recovery epoch it was sent under (RC-50 sends it back; D-073) — and never a
// payload, a secret or a cost. They live in local storage under the user's
// internal identifier, so that they survive a reload, a closed tab, a
// sign-out and a change of user until the server reports their intent
// resolved; another user's references are never read. Every other entry of
// the application's browser storage is cleared at sign-out and on a change of
// user (browserSession.ts keeps these keys).
//
// Each reference is one line of six fields separated by a space, each field
// checked against its form when it is read, so that nothing parses a value as
// a number and a damaged entry is dropped, never shown.

/** The prefix of every key of the references; browserSession.ts keeps these keys at sign-out. */
export const REFERENCES_PREFIX = 'bk.commands.references.';

/** The in-tab notice of a change, beside the storage event of the other tabs. */
const CHANGED = 'bk:command-references';

export interface Reference {
    readonly operation: string;
    readonly family: string;
    readonly sourceIntentId: string;
    readonly idempotencyKey: string;
    readonly recoveryEpoch: string;
    /** The instant the request was sent, in UTC (format.ts instantOf). */
    readonly sentAt: string;
}

const FORMS: Readonly<Record<keyof Reference, RegExp>> = {
    operation: /^[a-z][a-z0-9_]{0,40}\.[a-z][a-z0-9_]{0,40}$/,
    family: /^[a-z][a-z_]{0,40}$/,
    sourceIntentId: /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/,
    idempotencyKey: /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/,
    recoveryEpoch: /^(?:[0-9a-f]{2}){16,64}$/,
    sentAt: /^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{3}Z$/,
};

const FIELDS: readonly (keyof Reference)[] = ['operation', 'family', 'sourceIntentId', 'idempotencyKey', 'recoveryEpoch', 'sentAt'];

/** The browser's local storage, or null where it is refused. */
export function localStore(): Storage | null {
    try {
        return typeof window === 'undefined' ? null : window.localStorage;
    } catch {
        return null;
    }
}

function keyOf(userId: number): string {
    if (!Number.isSafeInteger(userId) || userId < 1) {
        throw new RangeError('A user is named by the internal identifier of the account.');
    }

    return `${REFERENCES_PREFIX}${userId.toString()}`;
}

/** The references of the text, each line checked; a damaged line is dropped. */
export function parseReferences(text: string | null): Reference[] {
    if (text === null || text === '') {
        return [];
    }

    const references: Reference[] = [];

    for (const line of text.split('\n')) {
        const parts = line.split(' ');

        if (parts.length !== FIELDS.length || !FIELDS.every((field, index) => FORMS[field].test(parts[index] ?? ''))) {
            continue;
        }

        const [operation, family, sourceIntentId, idempotencyKey, recoveryEpoch, sentAt] = parts as [string, string, string, string, string, string];
        references.push({ operation, family, sourceIntentId, idempotencyKey, recoveryEpoch, sentAt });
    }

    return references;
}

export function serializeReferences(references: readonly Reference[]): string {
    return references
        .filter((reference) => FIELDS.every((field) => FORMS[field].test(reference[field])))
        .map((reference) => FIELDS.map((field) => reference[field]).join(' '))
        .join('\n');
}

/** Whether two references name the same request of the user: its operation and intent. */
export function sameIntent(a: { operation: string; sourceIntentId: string }, b: { operation: string; sourceIntentId: string }): boolean {
    return a.operation === b.operation && a.sourceIntentId === b.sourceIntentId;
}

/** This user's references in this browser, oldest first. */
export function readReferences(userId: number, store: Storage | null = localStore()): Reference[] {
    if (store === null) {
        return [];
    }

    try {
        return parseReferences(store.getItem(keyOf(userId)));
    } catch {
        return [];
    }
}

function write(userId: number, references: readonly Reference[], store: Storage | null): void {
    if (store === null) {
        return;
    }

    try {
        if (references.length === 0) {
            store.removeItem(keyOf(userId));
        } else {
            store.setItem(keyOf(userId), serializeReferences(references));
        }
    } catch {
        // A storage the browser refuses keeps nothing; the receipt on the
        // server still lists the request (ADR-009 section 2).
    }

    if (typeof window !== 'undefined' && typeof window.dispatchEvent === 'function') {
        window.dispatchEvent(new Event(CHANGED));
    }
}

/** Keeps the reference of a request before it is sent, replacing an earlier one of the same intent. */
export function keepReference(userId: number, reference: Reference, store: Storage | null = localStore()): void {
    write(userId, [...readReferences(userId, store).filter((kept) => !sameIntent(kept, reference)), reference], store);
}

/** Removes the reference once the server reports its intent resolved, or nothing ran. */
export function releaseReference(userId: number, request: { operation: string; sourceIntentId: string }, store: Storage | null = localStore()): void {
    const kept = readReferences(userId, store);
    const left = kept.filter((reference) => !sameIntent(reference, request));

    if (left.length !== kept.length) {
        write(userId, left, store);
    }
}

/** Calls the listener whenever the references change, in this tab or another. */
export function onReferencesChanged(listener: () => void): () => void {
    const storage = (event: StorageEvent): void => {
        if (event.key === null || event.key.startsWith(REFERENCES_PREFIX)) {
            listener();
        }
    };

    window.addEventListener(CHANGED, listener);
    window.addEventListener('storage', storage);

    return () => {
        window.removeEventListener(CHANGED, listener);
        window.removeEventListener('storage', storage);
    };
}
