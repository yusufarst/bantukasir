// Negative sample of the exact-number rules in a .mts module (TASK-003 finding
// A-03): the rules apply to every script extension under resources/js.
declare const price: string;

export const a = Number(price); // N01 Number() in a .mts module
