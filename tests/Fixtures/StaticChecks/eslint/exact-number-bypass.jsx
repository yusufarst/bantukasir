// Negative sample of the exact-number rules in a .jsx module (TASK-003 finding
// A-03): the rules apply to every script extension under resources/js.
export function Total({ price }) {
    return <span>{Number(price)}</span>; // N01 Number() in a .jsx module
}
