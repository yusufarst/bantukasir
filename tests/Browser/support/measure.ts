import type { Page } from '@playwright/test';

// Measurements of the rendered page (DESIGN_SYSTEM sections 3.5 and 10;
// ADAPTIVE_AND_ACCESSIBILITY UXA-06, UXA-07, UXA-12, UXA-18): the contrast of
// every visible text against its real background, the smallest text, the
// targets below 44 x 44 px and sideways scrolling. Each function runs in the
// page and is self-contained.

export interface TextContrast {
    readonly text: string;
    readonly ratio: number;
    readonly large: boolean;
    readonly fontSize: number;
}

export interface PageMeasurements {
    readonly texts: TextContrast[];
    readonly smallText: string[];
    readonly smallTargets: string[];
    readonly overflow: { scrollWidth: number; innerWidth: number };
}

export async function measure(page: Page): Promise<PageMeasurements> {
    return page.evaluate(() => {
        type Rgba = [number, number, number, number];

        const parse = (value: string): Rgba => {
            const parts = value.match(/[0-9.]+/g)?.map((part) => parseFloat(part)) ?? [0, 0, 0, 0];

            return [parts[0] ?? 0, parts[1] ?? 0, parts[2] ?? 0, parts[3] ?? 1];
        };
        const over = (top: Rgba, bottom: Rgba): Rgba => {
            const alpha = top[3] + bottom[3] * (1 - top[3]);

            if (alpha === 0) {
                return [0, 0, 0, 0];
            }

            return [0, 1, 2].map((index) => ((top[index] ?? 0) * top[3] + (bottom[index] ?? 0) * bottom[3] * (1 - top[3])) / alpha).concat(alpha) as Rgba;
        };
        const luminance = (color: Rgba): number => {
            const [r, g, b] = [color[0], color[1], color[2]].map((channel) => {
                const value = channel / 255;

                return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
            });

            return 0.2126 * (r ?? 0) + 0.7152 * (g ?? 0) + 0.0722 * (b ?? 0);
        };
        const ratio = (a: Rgba, b: Rgba): number => {
            const [high, low] = [luminance(a), luminance(b)].sort((x, y) => y - x);

            return ((high ?? 0) + 0.05) / ((low ?? 0) + 0.05);
        };
        const background = (element: Element | null): Rgba => {
            const layers: Rgba[] = [];

            for (let node = element; node !== null; node = node.parentElement) {
                const color = parse(getComputedStyle(node).backgroundColor);

                if (color[3] > 0) {
                    layers.push(color);

                    if (color[3] >= 1) {
                        break;
                    }
                }
            }

            return layers.reduceRight<Rgba>((below, layer) => over(layer, below), [255, 255, 255, 1]);
        };
        const hidden = (element: Element): boolean => {
            for (let node: Element | null = element; node !== null; node = node.parentElement) {
                const style = getComputedStyle(node);

                if (style.visibility === 'hidden' || style.display === 'none' || style.clipPath === 'inset(50%)') {
                    return true;
                }
            }

            const box = element.getBoundingClientRect();

            return box.width === 0 || box.height === 0;
        };

        const texts: TextContrast[] = [];
        const smallText: string[] = [];
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);

        for (let node = walker.nextNode(); node !== null; node = walker.nextNode()) {
            const element = node.parentElement;
            const text = (node.textContent ?? '').trim();

            if (element === null || text === '' || hidden(element) || element.closest('[aria-disabled="true"], :disabled, output, script, style') !== null) {
                continue;
            }

            const style = getComputedStyle(element);
            const fontSize = parseFloat(style.fontSize);
            const weight = parseFloat(style.fontWeight);
            const large = fontSize >= 24 || (fontSize >= 18.66 && weight >= 700);
            const back = background(element);
            const fore = over(parse(style.color), back);

            texts.push({ text: text.slice(0, 60), ratio: Math.round(ratio(fore, back) * 100) / 100, large, fontSize });

            if (fontSize < 12) {
                smallText.push(`${text.slice(0, 40)} (${fontSize}px)`);
            }
        }

        const smallTargets: string[] = [];
        const selector = 'a[href], button, input:not([type="hidden"]), select, textarea, [role="radio"], [role="menuitem"], [tabindex]:not([tabindex="-1"])';

        for (const element of document.querySelectorAll<HTMLElement>(selector)) {
            if (hidden(element)) {
                continue;
            }

            const target = element.closest('.bk-input, .bk-stepper') ?? element;
            const box = target.getBoundingClientRect();
            const height = box.height;
            const width = target === element ? box.width : element.getBoundingClientRect().width;

            if (Math.round(width) < 44 || Math.round(height) < 44) {
                smallTargets.push(`${element.tagName.toLowerCase()} "${(element.getAttribute('aria-label') ?? element.textContent ?? '').trim().slice(0, 40)}" ${Math.round(width)}x${Math.round(height)}`);
            }
        }

        return {
            texts,
            smallText,
            smallTargets,
            overflow: { scrollWidth: document.documentElement.scrollWidth, innerWidth: window.innerWidth },
        };
    });
}

export interface FocusRing {
    readonly control: string;
    readonly width: number;
    readonly offset: number;
    readonly ratio: number;
}

/**
 * Moves the focus with real Tab presses through the first `count` stops and
 * measures each focus ring: its width, its offset and its contrast against
 * the background that surrounds it (UXA-08, DESIGN_SYSTEM 3.5).
 */
export async function focusRings(page: Page, count: number): Promise<FocusRing[]> {
    const rings: FocusRing[] = [];

    await page.locator('body').focus();

    for (let index = 0; index < count; index += 1) {
        await page.keyboard.press('Tab');
        const ring = await page.evaluate(() => {
            const element = document.activeElement;

            if (!(element instanceof HTMLElement) || element === document.body) {
                return null;
            }

            const parse = (value: string): number[] => (value.match(/[0-9.]+/g) ?? []).map((part) => parseFloat(part));
            const luminance = (color: number[]): number =>
                [0, 1, 2]
                    .map((index) => {
                        const value = (color[index] ?? 0) / 255;

                        return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
                    })
                    .reduce((sum, value, index) => sum + value * ([0.2126, 0.7152, 0.0722][index] ?? 0), 0);
            // A text field shows its focus on its 44 px box: the accent border and a
            // 1 px ring of the accent around it (DESIGN_SYSTEM 8.3).
            const field = element.closest<HTMLElement>(".bk-input");
            const host = field ?? element;
            const style = getComputedStyle(host);
            const shadow = parse(style.boxShadow);
            const color = field === null ? style.outlineColor : style.borderTopColor;
            const width = field === null ? (style.outlineStyle === "none" ? 0 : parseFloat(style.outlineWidth)) : parseFloat(style.borderTopWidth) + (style.boxShadow === "none" ? 0 : (shadow[shadow.length - 1] ?? 0));
            let surround = [255, 255, 255];

            for (let node = host.parentElement; node !== null; node = node.parentElement) {
                const color = parse(getComputedStyle(node).backgroundColor);

                if ((color[3] ?? 1) > 0 && color.length >= 3) {
                    surround = color;
                    break;
                }
            }

            const [high, low] = [luminance(parse(color)), luminance(surround)].sort((a, b) => b - a);
            const name = `${element.tagName.toLowerCase()}.${element.className} ${(element.getAttribute("aria-label") ?? element.textContent ?? "").trim().slice(0, 40)}`;

            return {
                control: name,
                width,
                offset: field === null ? parseFloat(style.outlineOffset) : 0,
                ratio: Math.round((((high ?? 0) + 0.05) / ((low ?? 0) + 0.05)) * 100) / 100,
            };
        });

        if (ring !== null) {
            rings.push(ring);
        }
    }

    return rings;
}
