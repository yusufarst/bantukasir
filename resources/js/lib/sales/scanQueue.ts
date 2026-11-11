// The order of scans at the till (BR-CAT-30; ADAPTIVE_AND_ACCESSIBILITY UXA-11; TASK-029 scope item 1):
// each complete scan starts its own lookup at once, and the answers are applied in the order the scans
// were made, whatever order they arrive in, so that a late lookup never changes the draft after a newer
// one. A scan is never dropped for a newer one: each adds its own base unit (BR-CAT-29). The lookups never
// reject; a failed one is an answer like any other. Nothing waits for a connection: without one the
// lookup fails, and nothing is queued for later (BR-CAT-34).

export interface ScanQueue<Answer> {
    /** Starts applying this scan's answer in its turn; returns the scan's number. */
    readonly enqueue: (lookup: Promise<Answer>) => number;
    /** Whether no scan was made after this one. */
    readonly isLatest: (scan: number) => boolean;
    /** Forgets every scan not yet applied: their answers are applied to nothing. */
    readonly reset: () => void;
}

export function createScanQueue<Answer>(apply: (answer: Answer, scan: number) => void): ScanQueue<Answer> {
    let issued = 0;
    let next = 0;
    let generation = 0;
    const arrived = new Map<number, Answer>();

    const drain = (): void => {
        while (arrived.has(next)) {
            const answer = arrived.get(next) as Answer;

            arrived.delete(next);
            apply(answer, next);
            next += 1;
        }
    };

    return {
        enqueue: (lookup) => {
            const scan = issued;
            const mine = generation;

            issued += 1;
            void lookup.then((answer) => {
                if (mine === generation) {
                    arrived.set(scan, answer);
                    drain();
                }
            });

            return scan;
        },
        isLatest: (scan) => scan === issued - 1,
        reset: () => {
            generation += 1;
            arrived.clear();
            next = issued;
        },
    };
}
