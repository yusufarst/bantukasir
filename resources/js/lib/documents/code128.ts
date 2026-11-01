import JsBarcode from 'jsbarcode';

// The symbol of a barcode label (DOCUMENT_CONTRACTS DC-05; ARC-47; BR-CAT-35): Code 128 as
// SVG, black on white, with quiet zones of ten modules on each side, drawn by JsBarcode
// (MIT; TOOLCHAIN section 1.1) from the code as it was recorded. Its automatic Code 128
// takes every printable ASCII character, which is what a token may hold (BR-CAT-17). Its
// SVG renderer writes attributes only, so the content security policy of SEC-HDR-03 holds.
// One module is one unit of the drawing, and the label's stylesheet gives the drawing
// its real size in millimetres, stretched across the label, never kept in proportion.

/** The modules of each quiet zone (DC-05). */
export const QUIET_ZONE_MODULES = 10;

/** What JsBarcode is asked for: its own text is off, because the label prints the code itself. */
export const CODE128_OPTIONS = {
    format: 'CODE128',
    width: 1,
    height: 100,
    displayValue: false,
    margin: 0,
    marginLeft: QUIET_ZONE_MODULES,
    marginRight: QUIET_ZONE_MODULES,
    background: '#ffffff',
    lineColor: '#000000',
} as const;

/** Draws the code on this element; its size comes from the stylesheet. */
export function drawCode128(svg: SVGSVGElement, code: string): void {
    JsBarcode(svg, code, { ...CODE128_OPTIONS });
    svg.removeAttribute('width');
    svg.removeAttribute('height');
    svg.setAttribute('preserveAspectRatio', 'none');
}
