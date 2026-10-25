import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';
import { AnnouncerProvider } from '@/components/ui/Announcer';
import { TrendChart, yOf, type TrendPoint } from '@/components/ui/TrendChart';

// The vertical geometry of the trend chart (DESIGN_SYSTEM 8.13, UXA-19). The
// plot is 240 px high with 16 px above and below it, so the baseline, where
// zero is drawn, is at 224 (HEIGHT - PAD_BOTTOM) and the top at 16 (PAD_TOP).
// A period whose net sales are zero on every day lies on the baseline, never
// at the top (the regression of the zero range).
const BASELINE = '224';
const TOP = '16';

function render(values: readonly string[]): string {
    const points: readonly TrendPoint[] = values.map((value, index) => ({ date: `day ${String(index + 1)}`, value, valueText: value }));

    return renderToStaticMarkup(
        createElement(
            AnnouncerProvider,
            null,
            createElement(TrendChart, {
                title: 'chart',
                points,
                pointText: (point: TrendPoint) => point.valueText,
                showTableLabel: 'table',
                showChartLabel: 'chart',
                dateHeader: 'date',
                valueHeader: 'value',
            }),
        ),
    );
}

function geometry(markup: string): { line: string[]; points: string[]; area: string; grid: string[] } {
    const line = /<polyline class="bk-chart__line" points="([^"]*)"/.exec(markup)?.[1] ?? '';
    const area = /<path class="bk-chart__area" d="([^"]*)"/.exec(markup)?.[1] ?? '';

    return {
        line: line === '' ? [] : line.split(' ').map((pair) => pair.split(',')[1] ?? ''),
        points: [...markup.matchAll(/<circle class="bk-chart__pt" cx="[^"]*" cy="([^"]*)"/g)].map((match) => match[1] ?? ''),
        area,
        grid: [...markup.matchAll(/<line class="bk-chart__grid"[^>]* y1="([^"]*)"/g)].map((match) => match[1] ?? ''),
    };
}

describe('the vertical position of a value', () => {
    it('puts zero on the baseline when every value is zero', () => {
        expect(yOf(0n, 0n, 0n)).toBe(BASELINE);
    });

    it('keeps the range from the top to the baseline when the range is not empty', () => {
        expect(yOf(2_104_000n, 0n, 2_104_000n)).toBe(TOP);
        expect(yOf(0n, 0n, 2_104_000n)).toBe(BASELINE);
        expect(yOf(1_052_000n, 0n, 2_104_000n)).toBe('120');
    });

    it('computes exact whole rupiah beyond the safe range of a float', () => {
        const max = 9_007_199_254_740_993n;

        expect(yOf(max, 0n, max)).toBe(TOP);
        expect(yOf(0n, 0n, max)).toBe(BASELINE);
        expect(yOf(max - 1n, 0n, max)).toBe(TOP);
    });
});

describe('the trend chart', () => {
    it('draws a period of zero net sales on the baseline, not at the top', () => {
        const drawn = geometry(render(['0', '0', '0']));

        expect(drawn.grid.at(-1)).toBe(BASELINE);
        expect(drawn.line).toEqual([BASELINE, BASELINE, BASELINE]);
        expect(drawn.points).toEqual([BASELINE, BASELINE, BASELINE]);
        expect(drawn.area).toMatch(/^M[^,]+,224 L[^,]+,224 L[^,]+,224 L[^,]+,224 L[^,]+,224 Z$/);
    });

    it('draws a single point of zero on the baseline', () => {
        const drawn = geometry(render(['0']));

        expect(drawn.line).toEqual([BASELINE]);
        expect(drawn.points).toEqual([BASELINE]);
        expect(drawn.area).toMatch(/^M[^,]+,224 L[^,]+,224 L[^,]+,224 Z$/);
    });

    it('still draws the highest value at the top and zero on the baseline', () => {
        const drawn = geometry(render(['0', '1052000', '2104000']));

        expect(drawn.line).toEqual([BASELINE, '120', TOP]);
        expect(drawn.points).toEqual([BASELINE, '120', TOP]);
    });
});
