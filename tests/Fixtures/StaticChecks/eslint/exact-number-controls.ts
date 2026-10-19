// Compliant samples of the exact-number rules (TASK-003 finding A-03): the
// integer-only members of Number, JSON.stringify, the time of Date.now() for a
// timer, BigInt and text conversions that turn nothing into a float, and
// type positions. Nothing here is refused.
declare const price: string;
declare const count: number;
declare const settings: { parseInt: boolean; locale: string };

export const a = Number.isSafeInteger(count);
export const b = Number.isInteger(count);
export const c = Number.MAX_SAFE_INTEGER;
export const d = JSON.stringify({ price });
export const e = Date.now();
export const f = BigInt(price);
export const g = count.toString();
export const h = String(count);
export const i = `${price}`;
export const j = settings.parseInt;
export const k: Date | null = null;
export const l = price.toUpperCase();
export const m = Number.MIN_SAFE_INTEGER;
