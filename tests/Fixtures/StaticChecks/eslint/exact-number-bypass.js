// Negative sample of the exact-number rules in a .js module (TASK-003 finding
// A-03): the rules apply to every script extension under resources/js.
export function float(price) {
    return +price; // N01 unary + in a .js module
}
