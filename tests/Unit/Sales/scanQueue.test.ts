import { describe, expect, it } from 'vitest';
import { createScanQueue } from '@/lib/sales/scanQueue';

// The order of scans at the till (AC-029-01; BR-CAT-29, BR-CAT-30; UXA-11; TASK-029): every scan's answer
// is applied once, in the order the scans were made, whatever order the lookups answer in; a newer scan
// never drops an older one; the answers of scans forgotten by a reset are applied to nothing.

interface Pending {
    readonly promise: Promise<string>;
    readonly resolve: (answer: string) => void;
}

function pending(): Pending {
    let resolve: (answer: string) => void = () => undefined;
    const promise = new Promise<string>((done) => {
        resolve = done;
    });

    return { promise, resolve };
}

/** Lets every answer already given reach the queue. */
async function settle(): Promise<void> {
    for (let turn = 0; turn < 5; turn += 1) {
        await Promise.resolve();
    }
}

describe('the scan queue of the till', () => {
    it('applies the answers in the order of the scans, each once, whatever order they arrive in', async () => {
        const applied: string[] = [];
        const queue = createScanQueue<string>((answer, scan) => applied.push(`${String(scan)}:${answer}`));
        const first = pending();
        const second = pending();
        const third = pending();

        expect([queue.enqueue(first.promise), queue.enqueue(second.promise), queue.enqueue(third.promise)]).toEqual([0, 1, 2]);
        expect(queue.isLatest(1)).toBe(false);
        expect(queue.isLatest(2)).toBe(true);

        third.resolve('C');
        second.resolve('B');
        await settle();
        expect(applied).toEqual([]);

        first.resolve('A');
        await settle();
        expect(applied).toEqual(['0:A', '1:B', '2:C']);
    });

    it('applies the same code scanned twice twice, never one for the other (BR-CAT-29)', async () => {
        const applied: string[] = [];
        const queue = createScanQueue<string>((answer) => applied.push(answer));

        queue.enqueue(Promise.resolve('8991234500017'));
        queue.enqueue(Promise.resolve('8991234500017'));
        await settle();

        expect(applied).toEqual(['8991234500017', '8991234500017']);
    });

    it('applies nothing of the scans that a reset forgot, and goes on with the next ones', async () => {
        const applied: string[] = [];
        const queue = createScanQueue<string>((answer) => applied.push(answer));
        const late = pending();

        queue.enqueue(late.promise);
        queue.reset();
        queue.enqueue(Promise.resolve('B'));
        late.resolve('A');
        await settle();

        expect(applied).toEqual(['B']);
    });

    it('counts the scans still to apply, so that the till waits for them before the payment (review R-01)', async () => {
        const counts: number[] = [];
        const queue = createScanQueue<string>(() => undefined, (count) => counts.push(count));
        const first = pending();
        const second = pending();

        queue.enqueue(first.promise);
        queue.enqueue(second.promise);
        expect(queue.pending()).toBe(2);

        second.resolve('B');
        await settle();
        expect(queue.pending()).toBe(2);

        first.resolve('A');
        await settle();
        expect(queue.pending()).toBe(0);
        expect(counts[counts.length - 1]).toBe(0);

        // A reset forgets the scans still out: none is counted, and none is applied later.
        const late = pending();

        queue.enqueue(late.promise);
        expect(queue.pending()).toBe(1);
        queue.reset();
        expect(queue.pending()).toBe(0);
        expect(counts[counts.length - 1]).toBe(0);
    });
});
