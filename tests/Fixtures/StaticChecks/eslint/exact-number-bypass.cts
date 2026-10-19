// Negative sample of the exact-number rules in a .cts module (TASK-003 finding
// A-03): the rules apply to every script extension under resources/js.
declare const price: string;

export const a = parseFloat(price); // N01 parseFloat() in a .cts module
