// Negative sample of the exact-number rules (ADR-006 items 5 and 6, ADR-005
// item 6, TASK-003): conversions to floats and locale-dependent formatting.
declare const price: string;
declare const when: Date;

export const a = Number(price);
export const b = parseFloat(price);
export const c = Number.parseFloat(price);
export const d = +price;
export const e = new Intl.NumberFormat('id-ID').format(1);
export const f = when.toLocaleDateString();
export const g = parseInt(price, 10);
export const h = window.Number(price);
