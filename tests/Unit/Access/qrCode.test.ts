import { describe, expect, it } from 'vitest';
import { qrMatrix, qrPath, reedSolomon } from '@/lib/access/qrCode';

// The scannable code of UXP-606 (WF-02; TASK-011 scope item 4), made in the browser
// without a package: the error correction against the worked example of the standard's
// tutorials, and the whole symbol read back as a scanner reads it — the format bits, the
// mask, the zigzag, the blocks and their error correction — for addresses of every length.

const VERSIONS = [
    { ec: 10, groups: [[1, 16]] },
    { ec: 16, groups: [[1, 28]] },
    { ec: 26, groups: [[1, 44]] },
    { ec: 18, groups: [[2, 32]] },
    { ec: 24, groups: [[2, 43]] },
    { ec: 16, groups: [[4, 27]] },
    { ec: 18, groups: [[4, 31]] },
    { ec: 22, groups: [[2, 38], [2, 39]] },
    { ec: 22, groups: [[3, 36], [2, 37]] },
    { ec: 26, groups: [[4, 43], [1, 44]] },
];

const ALIGNMENT = [[], [6, 18], [6, 22], [6, 26], [6, 30], [6, 34], [6, 22, 38], [6, 24, 42], [6, 26, 46], [6, 28, 50]];

const MASKS: readonly ((row: number, column: number) => boolean)[] = [
    (row, column) => (row + column) % 2 === 0,
    (row) => row % 2 === 0,
    (_row, column) => column % 3 === 0,
    (row, column) => (row + column) % 3 === 0,
    (row, column) => (Math.floor(row / 2) + Math.floor(column / 3)) % 2 === 0,
    (row, column) => ((row * column) % 2) + ((row * column) % 3) === 0,
    (row, column) => (((row * column) % 2) + ((row * column) % 3)) % 2 === 0,
    (row, column) => (((row + column) % 2) + ((row * column) % 3)) % 2 === 0,
];

function isFunctionModule(row: number, column: number, size: number, alignment: readonly number[]): boolean {
    const inFinder = (row <= 8 && column <= 8) || (row <= 8 && column >= size - 8) || (row >= size - 8 && column <= 8);
    const timing = row === 6 || column === 6;
    const version = (size - 17) / 4;
    const versionBits = version >= 7 && ((row < 6 && column >= size - 11) || (column < 6 && row >= size - 11));
    const nearAlignment = alignment.some((y) =>
        alignment.some((x) => {
            const corner = (y === 6 && x === 6) || (y === 6 && x === size - 7) || (y === size - 7 && x === 6);

            return !corner && Math.abs(row - y) <= 2 && Math.abs(column - x) <= 2;
        }),
    );

    return inFinder || timing || versionBits || nearAlignment;
}

/** The text a scanner reads from the matrix. */
function read(matrix: readonly (readonly boolean[])[]): string {
    const size = matrix.length;
    const version = (size - 17) / 4;
    const entry = VERSIONS[version - 1];
    const alignment = ALIGNMENT[version - 1];

    if (entry === undefined || alignment === undefined) {
        throw new Error(`Version ${String(version)} is not used.`);
    }

    const dark = (row: number, column: number): boolean => matrix[row]?.[column] === true;
    let format = 0;

    // The first copy of the format bits, from bit 14 to bit 0.
    for (const [row, column] of [[8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8], [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8]] as const) {
        format = (format << 1) | (dark(row, column) ? 1 : 0);
    }

    format ^= 0x5412;
    expect(format >>> 13, 'error correction level M').toBe(0);
    const mask = MASKS[(format >>> 10) & 7];

    if (mask === undefined) {
        throw new Error('A mask of eight.');
    }

    const bits: boolean[] = [];
    let right = size - 1;

    while (right >= 1) {
        if (right === 6) {
            right = 5;
        }

        for (let step = 0; step < size; step += 1) {
            for (const offset of [0, 1]) {
                const column = right - offset;
                const row = ((right + 1) & 2) === 0 ? size - 1 - step : step;

                if (!isFunctionModule(row, column, size, alignment)) {
                    bits.push(dark(row, column) !== mask(row, column));
                }
            }
        }

        right -= 2;
    }

    const codewords: number[] = [];

    for (let index = 0; index + 8 <= bits.length; index += 8) {
        codewords.push(bits.slice(index, index + 8).reduce((value, bit) => (value << 1) | (bit ? 1 : 0), 0));
    }

    // De-interleave, and check each block's error correction.
    const lengths = entry.groups.flatMap(([count, length]) => new Array<number>(count ?? 0).fill(length ?? 0));
    const blocks: number[][] = lengths.map(() => []);
    let position = 0;

    for (let index = 0; index < Math.max(...lengths); index += 1) {
        lengths.forEach((length, block) => {
            if (index < length) {
                blocks[block]?.push(codewords[position] ?? 0);
                position += 1;
            }
        });
    }

    const corrections: number[][] = lengths.map(() => []);

    for (let index = 0; index < entry.ec; index += 1) {
        corrections.forEach((correction) => {
            correction.push(codewords[position] ?? 0);
            position += 1;
        });
    }

    blocks.forEach((block, index) => {
        expect(reedSolomon(block, entry.ec)).toEqual(corrections[index]);
    });

    const data = blocks.flat();
    const lengthBits = version < 10 ? 8 : 16;
    const stream = data.flatMap((codeword) => Array.from({ length: 8 }, (_unused, bit) => ((codeword >>> (7 - bit)) & 1) === 1));
    const take = (start: number, length: number): number => stream.slice(start, start + length).reduce((value, bit) => (value << 1) | (bit ? 1 : 0), 0);

    expect(take(0, 4), 'byte mode').toBe(0b0100);
    const count = take(4, lengthBits);
    const bytes = Uint8Array.from({ length: count }, (_unused, index) => take(4 + lengthBits + index * 8, 8));

    return new TextDecoder().decode(bytes);
}

describe('the QR code of a one-time link', () => {
    it('computes the error correction of the worked example', () => {
        // "HELLO WORLD", version 1-M, as the widely published tutorial works it.
        const data = [32, 91, 11, 120, 209, 114, 220, 77, 67, 64, 236, 17, 236, 17, 236, 17];

        expect(reedSolomon(data, 10)).toEqual([196, 35, 39, 119, 235, 215, 231, 226, 93, 23]);
    });

    it('draws the finder patterns and the dark module', () => {
        const matrix = qrMatrix('https://toko.test/set-password#u=7&t=abc');

        if (matrix === null) {
            throw new Error('The text fits.');
        }

        const size = matrix.length;

        expect(matrix[0]?.slice(0, 7)).toEqual([true, true, true, true, true, true, true]);
        expect(matrix[1]?.slice(0, 7)).toEqual([true, false, false, false, false, false, true]);
        expect(matrix[3]?.slice(0, 7)).toEqual([true, false, true, true, true, false, true]);
        expect(matrix[0]?.slice(size - 7)).toEqual([true, true, true, true, true, true, true]);
        expect(matrix[size - 1]?.slice(0, 7)).toEqual([true, true, true, true, true, true, true]);
        expect(matrix[size - 8]?.[8]).toBe(true);
    });

    it('is read back as the text, at every version it uses', () => {
        for (const length of [1, 10, 14, 15, 26, 40, 60, 62, 78, 100, 106, 122, 150, 152, 180, 213]) {
            const text = `https://${'a'.repeat(Math.max(0, length - 8))}`.slice(0, Math.max(length, 1));
            const matrix = qrMatrix(text);

            expect(matrix, `${String(length)} bytes`).not.toBeNull();

            if (matrix !== null) {
                expect(read(matrix)).toBe(text);
            }
        }
    });

    it('reads a real address of a link', () => {
        const url = `https://bantukasir.test/set-password#u=1234&t=${'f'.repeat(64)}`;
        const matrix = qrMatrix(url);

        expect(matrix).not.toBeNull();

        if (matrix !== null) {
            expect(read(matrix)).toBe(url);
            expect(qrPath(matrix)).toMatch(/^M\d+ \d+h1v1h-1z/);
        }
    });

    it('holds nothing longer than version 10 of level M', () => {
        expect(qrMatrix('a'.repeat(213))).not.toBeNull();
        expect(qrMatrix('a'.repeat(214))).toBeNull();
    });
});
