import JsBarcode from 'jsbarcode';
import { describe, expect, it } from 'vitest';
import { CODE128_OPTIONS, QUIET_ZONE_MODULES } from '@/lib/documents/code128';

// The symbol of a label (DC-05; TASK-018): the options of the page give JsBarcode's Code 128
// of the code as it was recorded, with its own text off. The renderer that takes an object
// gives the modules, which the print view draws as SVG; its browser test reads the SVG.

const START_A = '11010000100';

const START_B = '11010010000';

const START_C = '11010011100';

const STOP = '1100011101011';

function modulesOf(code: string): string {
    const target: { encodings?: readonly { readonly data: string }[] } = {};

    JsBarcode(target, code, { ...CODE128_OPTIONS });

    return target.encodings?.map((encoding) => encoding.data).join('') ?? '';
}

describe('the Code 128 of a label', () => {
    it('encodes a code by the standard: start, characters, check character and stop', () => {
        // Value 33 is A, and (104 + 33) mod 103 = 34 is its check character.
        expect(modulesOf('A')).toBe(`${START_B}1010001100010001011000${STOP}`);
    });

    it.each(['BK-P-0123456789ABCDEF', '0012345', 'aB-c.D/e_f+g', '~!@#$%^&*()', 'A', 't'.repeat(128)])('takes every printable ASCII code: %s', (code) => {
        const modules = modulesOf(code);

        expect(modules.slice(0, 11)).toMatch(new RegExp(`^(${START_A}|${START_B}|${START_C})$`));
        expect(modules.slice(-13)).toBe(STOP);
        expect((modules.length - 13) % 11).toBe(0);
    });

    it('asks for the quiet zones of DC-05, black on white, and no text of its own', () => {
        expect(QUIET_ZONE_MODULES).toBeGreaterThanOrEqual(10);
        expect(CODE128_OPTIONS).toMatchObject({ format: 'CODE128', width: 1, displayValue: false, marginLeft: QUIET_ZONE_MODULES, marginRight: QUIET_ZONE_MODULES, background: '#ffffff', lineColor: '#000000' });
    });
});
