// Compliant sample of the exact-number rules (TASK-003): canonical strings,
// BigInt and integer checks that turn nothing into a float.
declare const price: string;
declare const count: number;

export const units = BigInt(price);
export const whole = Number.isSafeInteger(count);
export const text = String(count);
export const doubled = units * 2n;
export const negative = -units;
