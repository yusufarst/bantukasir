// The scannable code of a one-time link on UXP-606 (WF-02; NAVIGATION_CONTRACTS
// UXP-606; TASK-011 scope item 4). A QR code of ISO/IEC 18004, byte mode, error correction level M,
// versions 1 to 10: up to 213 bytes, far more than the address of a link. It is
// made in the browser, so the token never leaves page memory for it, and needs
// no package: no request, no storage, no canvas, only a matrix that the page draws
// as one SVG path (SEC-ACC-05, SEC-HDR-03).

/** Error correction of level M per version: codewords per block, then the blocks of each group as [count, data codewords]. */
const VERSIONS: readonly { readonly ec: number; readonly groups: readonly (readonly [number, number])[]; readonly alignment: readonly number[] }[] = [
    { ec: 10, groups: [[1, 16]], alignment: [] },
    { ec: 16, groups: [[1, 28]], alignment: [6, 18] },
    { ec: 26, groups: [[1, 44]], alignment: [6, 22] },
    { ec: 18, groups: [[2, 32]], alignment: [6, 26] },
    { ec: 24, groups: [[2, 43]], alignment: [6, 30] },
    { ec: 16, groups: [[4, 27]], alignment: [6, 34] },
    { ec: 18, groups: [[4, 31]], alignment: [6, 22, 38] },
    { ec: 22, groups: [[2, 38], [2, 39]], alignment: [6, 24, 42] },
    { ec: 22, groups: [[3, 36], [2, 37]], alignment: [6, 26, 46] },
    { ec: 26, groups: [[4, 43], [1, 44]], alignment: [6, 28, 50] },
];

/** The field GF(256) of the standard, with the polynomial 0x11D. */
const EXP: number[] = [];
const LOG: number[] = new Array<number>(256).fill(0);

for (let index = 0, value = 1; index < 255; index += 1) {
    EXP[index] = value;
    LOG[value] = index;
    value <<= 1;

    if (value > 255) {
        value ^= 0x11d;
    }
}

function at(values: readonly number[], index: number): number {
    const value = values[index];

    if (value === undefined) {
        throw new RangeError('An index of the QR tables is out of range.');
    }

    return value;
}

function multiply(left: number, right: number): number {
    return left === 0 || right === 0 ? 0 : at(EXP, (at(LOG, left) + at(LOG, right)) % 255);
}

/** The error correction codewords of a block (ISO/IEC 18004, section 7.5.2). */
export function reedSolomon(data: readonly number[], ecLength: number): number[] {
    let generator = [1];

    for (let degree = 0; degree < ecLength; degree += 1) {
        const next = new Array<number>(generator.length + 1).fill(0);
        const root = at(EXP, degree);

        generator.forEach((coefficient, index) => {
            next[index] = at(next, index) ^ coefficient;
            next[index + 1] = at(next, index + 1) ^ multiply(coefficient, root);
        });
        generator = next;
    }

    const remainder = new Array<number>(ecLength).fill(0);

    for (const codeword of data) {
        const factor = codeword ^ at(remainder, 0);

        remainder.shift();
        remainder.push(0);

        for (let index = 0; index < ecLength; index += 1) {
            remainder[index] = at(remainder, index) ^ multiply(at(generator, index + 1), factor);
        }
    }

    return remainder;
}

/** The data codewords: mode byte, the length, the bytes, the terminator and the pad codewords. */
function dataCodewords(bytes: Uint8Array, capacity: number, lengthBits: number): number[] {
    const bits: number[] = [];
    const push = (value: number, length: number): void => {
        for (let bit = length - 1; bit >= 0; bit -= 1) {
            bits.push((value >>> bit) & 1);
        }
    };

    push(0b0100, 4);
    push(bytes.length, lengthBits);
    bytes.forEach((byte) => push(byte, 8));
    push(0, Math.min(4, capacity * 8 - bits.length));

    while (bits.length % 8 !== 0) {
        bits.push(0);
    }

    const codewords: number[] = [];

    for (let index = 0; index < bits.length; index += 8) {
        let codeword = 0;

        for (let bit = 0; bit < 8; bit += 1) {
            codeword = (codeword << 1) | at(bits, index + bit);
        }

        codewords.push(codeword);
    }

    for (let pad = 0xec; codewords.length < capacity; pad = pad === 0xec ? 0x11 : 0xec) {
        codewords.push(pad);
    }

    return codewords;
}

/** The codewords of the symbol: the blocks with their error correction, interleaved (section 7.6). */
function interleaved(data: readonly number[], version: (typeof VERSIONS)[number]): number[] {
    const blocks: number[][] = [];
    const corrections: number[][] = [];
    let start = 0;

    for (const [count, length] of version.groups) {
        for (let block = 0; block < count; block += 1) {
            const part = data.slice(start, start + length);

            blocks.push(part);
            corrections.push(reedSolomon(part, version.ec));
            start += length;
        }
    }

    const all: number[] = [];
    const longest = Math.max(...blocks.map((block) => block.length));

    for (let index = 0; index < longest; index += 1) {
        for (const block of blocks) {
            const codeword = block[index];

            if (codeword !== undefined) {
                all.push(codeword);
            }
        }
    }

    for (let index = 0; index < version.ec; index += 1) {
        for (const correction of corrections) {
            all.push(at(correction, index));
        }
    }

    return all;
}

/** The remainder of a BCH code, for the format and version information (annexes C and D). */
function bch(value: number, generator: number, bits: number): number {
    let remainder = value << bits;
    const degree = 31 - Math.clz32(generator);

    for (let bit = 31 - Math.clz32(remainder); bit >= degree; bit -= 1) {
        if ((remainder >>> bit) & 1) {
            remainder ^= generator << (bit - degree);
        }
    }

    return remainder;
}

type Grid = (boolean | null)[][];

function make(size: number): Grid {
    return Array.from({ length: size }, () => new Array<boolean | null>(size).fill(null));
}

function setModule(grid: Grid, row: number, column: number, dark: boolean): void {
    const line = grid[row];

    if (line !== undefined && column >= 0 && column < line.length) {
        line[column] = dark;
    }
}

function moduleAt(grid: Grid, row: number, column: number): boolean | null {
    return grid[row]?.[column] ?? null;
}

/** The finder, timing, alignment and dark modules, and the reserved format and version areas. */
function functionPatterns(version: number, alignment: readonly number[]): Grid {
    const size = version * 4 + 17;
    const grid = make(size);

    for (const [row, column] of [[0, 0], [0, size - 7], [size - 7, 0]] as const) {
        for (let y = -1; y <= 7; y += 1) {
            for (let x = -1; x <= 7; x += 1) {
                const inside = x >= 0 && x <= 6 && y >= 0 && y <= 6;
                const ring = x === 0 || x === 6 || y === 0 || y === 6;
                const centre = x >= 2 && x <= 4 && y >= 2 && y <= 4;

                setModule(grid, row + y, column + x, inside && (ring || centre));
            }
        }
    }

    for (const row of alignment) {
        for (const column of alignment) {
            if ((row === 6 && column === 6) || (row === 6 && column === size - 7) || (row === size - 7 && column === 6)) {
                continue;
            }

            for (let y = -2; y <= 2; y += 1) {
                for (let x = -2; x <= 2; x += 1) {
                    setModule(grid, row + y, column + x, Math.max(Math.abs(x), Math.abs(y)) !== 1);
                }
            }
        }
    }

    for (let index = 8; index < size - 8; index += 1) {
        setModule(grid, 6, index, index % 2 === 0);
        setModule(grid, index, 6, index % 2 === 0);
    }

    setModule(grid, size - 8, 8, true);
    placeFormat(grid, 0);

    if (version >= 7) {
        const bits = (version << 12) | bch(version, 0x1f25, 12);

        for (let index = 0; index < 18; index += 1) {
            const dark = ((bits >>> index) & 1) === 1;
            const near = Math.floor(index / 3);
            const far = size - 11 + (index % 3);

            setModule(grid, near, far, dark);
            setModule(grid, far, near, dark);
        }
    }

    return grid;
}

/** The two copies of the 15 format bits of level M and this mask (section 7.9). */
function placeFormat(grid: Grid, mask: number): void {
    const size = grid.length;
    // Level M's two bits are zero, so the five data bits are the mask's.
    const bits = ((mask << 10) | bch(mask, 0x537, 10)) ^ 0x5412;
    const bitAt = (index: number): boolean => ((bits >>> index) & 1) === 1;

    for (let index = 0; index <= 5; index += 1) {
        setModule(grid, index, 8, bitAt(index));
    }

    setModule(grid, 7, 8, bitAt(6));
    setModule(grid, 8, 8, bitAt(7));
    setModule(grid, 8, 7, bitAt(8));

    for (let index = 9; index < 15; index += 1) {
        setModule(grid, 8, 14 - index, bitAt(index));
    }

    for (let index = 0; index < 8; index += 1) {
        setModule(grid, 8, size - 1 - index, bitAt(index));
    }

    for (let index = 8; index < 15; index += 1) {
        setModule(grid, size - 15 + index, 8, bitAt(index));
    }
}

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

/** The data bits in the zigzag of section 7.7.3, each with the mask applied. */
function placeData(functions: Grid, codewords: readonly number[], mask: number): boolean[][] {
    const size = functions.length;
    const rule = MASKS[mask];

    if (rule === undefined) {
        throw new RangeError('A QR code has eight masks.');
    }

    const grid = functions.map((line) => line.map((value) => value === true));
    const total = codewords.length * 8;
    let bitIndex = 0;
    let column = size - 1;

    while (column >= 1) {
        // The column of the timing pattern is skipped.
        if (column === 6) {
            column = 5;
        }

        const upward = ((column + 1) & 2) === 0;

        for (let step = 0; step < size; step += 1) {
            for (const offset of [0, 1]) {
                const x = column - offset;
                const y = upward ? size - 1 - step : step;

                if (moduleAt(functions, y, x) !== null) {
                    continue;
                }

                // The remainder bits after the last codeword are light before the mask.
                const bit = bitIndex < total ? ((at(codewords, Math.floor(bitIndex / 8)) >>> (7 - (bitIndex % 8))) & 1) === 1 : false;

                bitIndex += 1;
                const line = grid[y];

                if (line !== undefined) {
                    line[x] = bit !== rule(y, x);
                }
            }
        }

        column -= 2;
    }

    return grid;
}

/** The penalty of a candidate symbol (section 7.8.3): the lowest is the one to use. */
function penalty(grid: readonly (readonly boolean[])[]): number {
    const size = grid.length;
    const dark = (row: number, column: number): boolean => grid[row]?.[column] === true;
    let score = 0;
    let darkCount = 0;

    for (let first = 0; first < size; first += 1) {
        for (const horizontal of [true, false]) {
            let run = 1;

            for (let second = 1; second < size; second += 1) {
                const current = horizontal ? dark(first, second) : dark(second, first);
                const previous = horizontal ? dark(first, second - 1) : dark(second - 1, first);

                if (current === previous) {
                    run += 1;
                    score += run === 5 ? 3 : run > 5 ? 1 : 0;
                } else {
                    run = 1;
                }
            }

            for (let second = 0; second + 10 < size; second += 1) {
                const modules = Array.from({ length: 11 }, (_unused, index) => (horizontal ? dark(first, second + index) : dark(second + index, first)));
                const pattern = modules.map((value) => (value ? '1' : '0')).join('');

                if (pattern === '10111010000' || pattern === '00001011101') {
                    score += 40;
                }
            }
        }
    }

    for (let row = 0; row < size; row += 1) {
        for (let column = 0; column < size; column += 1) {
            darkCount += dark(row, column) ? 1 : 0;

            if (row + 1 < size && column + 1 < size && dark(row, column) === dark(row + 1, column) && dark(row, column) === dark(row, column + 1) && dark(row, column) === dark(row + 1, column + 1)) {
                score += 3;
            }
        }
    }

    const total = size * size;

    return score + Math.floor(Math.abs(darkCount * 20 - total * 10) / total) * 10;
}

/**
 * The modules of the code of this text, true for dark, rows first, without the
 * quiet zone; null when the text is longer than version 10 holds, and the page
 * then shows only the text and its copy action.
 */
export function qrMatrix(text: string): boolean[][] | null {
    const bytes = new TextEncoder().encode(text);

    for (const [index, version] of VERSIONS.entries()) {
        const capacity = version.groups.reduce((sum, [count, length]) => sum + count * length, 0);
        const lengthBits = index + 1 < 10 ? 8 : 16;

        if (4 + lengthBits + bytes.length * 8 > capacity * 8) {
            continue;
        }

        const codewords = interleaved(dataCodewords(bytes, capacity, lengthBits), version);
        const functions = functionPatterns(index + 1, version.alignment);
        let best: boolean[][] | null = null;
        let bestScore = 0;

        for (let mask = 0; mask < MASKS.length; mask += 1) {
            placeFormat(functions, mask);
            const candidate = placeData(functions, codewords, mask);
            const score = penalty(candidate);

            if (best === null || score < bestScore) {
                best = candidate;
                bestScore = score;
            }
        }

        return best;
    }

    return null;
}

/** The dark modules as the path of an SVG whose viewBox has a quiet zone of four modules (section 7.3.7). */
export function qrPath(matrix: readonly (readonly boolean[])[]): string {
    const parts: string[] = [];

    matrix.forEach((line, row) => {
        line.forEach((dark, column) => {
            if (dark) {
                parts.push(`M${column + 4} ${row + 4}h1v1h-1z`);
            }
        });
    });

    return parts.join('');
}
