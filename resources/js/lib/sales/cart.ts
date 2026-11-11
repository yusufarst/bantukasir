import { quantityFromEntry, type ProductRow } from '@/lib/catalog/products';
import { addExact, compareExact, compareMoney, isStoredMoney, lineTotal, parseExact, parseMoney, parseQuantity, subtractExact, subtractMoney, toCanonical, type Exact } from '@/lib/exact';

// The cart of the till and its payment draft (KEY_SCREENS KS-03, KS-04; INTERACTION_INVENTORY UXI-066 to
// UXI-083; INTERACTION_CONTRACTS RC-01; BR-SALE-05, BR-SALE-06, BR-SALE-11, BR-PAY-01 to BR-PAY-03,
// BR-CAT-29, BR-NUM-02; D-036, D-067, D-075; TASK-029 scope items 1 and 2). A draft of the page, held in
// its memory and its encrypted history state, never on the server and never in the browser's storage
// (BR-DOM-09, SEC-CH-05). One line per product (D-067): a scan or a pick of a product already in the
// cart adds one base unit to its line. A quantity is kept as typed, with a decimal comma, and checked
// against its product's precision; money is computed exactly from canonical strings (ADR-006), as a
// preview only: the server prices every line again and refuses a reviewed price that changed
// (PRICE_CHANGED), so that the total never changes silently.

/** A product as the cart holds it: what RD-01 in the context `till` gave, with the price revision reviewed. */
export interface CartProduct {
    readonly id: number;
    readonly name: string;
    readonly sku: string;
    readonly unit: string;
    readonly precision: number;
    /** The unit price, a canonical amount of whole rupiah. */
    readonly price: string;
    /** The price revision that RC-01 expects as reviewed. */
    readonly revisionId: number;
    readonly thumbnail: string | null;
}

/** Why a line was refused at checkout, kept at the line until it changes (NC-09). */
export type LineRefusal = 'inactive' | 'blocked';

export interface CartLine {
    readonly product: CartProduct;
    /** The quantity as shown and typed, with a decimal comma: "2", "2,5". */
    readonly quantity: string;
    /** The price before a change that the server or a newer search reported, until "Setuju harga baru" (UXS-18). */
    readonly previousPrice: string | null;
    /** The stock available that INSUFFICIENT_STOCK named for this line (UXS-31), until the line changes. */
    readonly available: string | null;
    readonly refusal: LineRefusal | null;
}

export const CASH = 'CASH';

export const BANK_TRANSFER = 'BANK_TRANSFER';

export type PaymentMethod = typeof CASH | typeof BANK_TRANSFER;

/** The payment screen's entries, kept while the user goes back to the cart and returns, until the cart changes (NAVIGATION_CONTRACTS UXP-102). */
export interface PaymentDraft {
    readonly method: PaymentMethod;
    /** Whole rupiah as a canonical string, or null while nothing is entered. */
    readonly tendered: string | null;
    readonly reference: string;
    readonly customerName: string;
}

/**
 * The cart and the payment screen's entries. The page keeps it in its memory; its history entry keeps it
 * too, except while a checkout of it is out or its outcome is not known: a history entry never holds a
 * cart that a committed checkout may have paid, so that no Back offers it for payment again (NC-13 (e)).
 */
export interface TillDraft {
    readonly lines: readonly CartLine[];
    readonly payment: PaymentDraft;
}

/** Tunai is chosen when the payment screen opens (D-075, G1-10). */
export const EMPTY_PAYMENT: PaymentDraft = { method: CASH, tendered: null, reference: '', customerName: '' };

export const EMPTY_DRAFT: TillDraft = { lines: [], payment: EMPTY_PAYMENT };

/** The largest quantity's whole digits (TECHNICAL_RULES section 11.1: 99.999.999,999). */
const QUANTITY_DIGITS_MAX = 8;

/** The most lines of a Sale (TECHNICAL_RULES section 11.2; D-067). */
export const MAX_LINES = 200;

/** A product of RD-01 as the cart holds it, or null for a row without a price revision. */
export function cartProductOf(row: ProductRow): CartProduct | null {
    const revisionId = row.price.revisionId;

    if (!Number.isSafeInteger(revisionId) || !Number.isSafeInteger(row.quantityPrecision)) {
        return null;
    }

    return { id: row.id, name: row.name, sku: row.sku, unit: row.baseUnit, precision: row.quantityPrecision, price: row.price.amount, revisionId, thumbnail: row.thumbnail };
}

/** A canonical quantity as the cart shows it: "2.5" is "2,5". */
export function quantityText(canonical: string): string {
    return canonical.replace('.', ',');
}

/** What is wrong with a quantity as typed, in its product's precision (COPY_DECK section 16.1), or null. */
export type QuantityProblem =
    | { readonly kind: 'required' }
    | { readonly kind: 'positive' }
    | { readonly kind: 'whole' }
    | { readonly kind: 'decimals'; readonly precision: number }
    | { readonly kind: 'max' };

/** The canonical quantity of a valid entry in its product's precision, or the problem. */
export function readQuantity(text: string, precision: number): { readonly quantity: string } | { readonly problem: QuantityProblem } {
    const entry = text.trim();

    if (entry === '') {
        return { problem: { kind: 'required' } };
    }

    const match = /^([0-9]+)(?:\.([0-9]+))?$/.exec(quantityFromEntry(entry));

    if (match === null || /^0+$/.test(`${match[1] ?? ''}${match[2] ?? ''}`)) {
        return { problem: { kind: 'positive' } };
    }

    const whole = (match[1] ?? '').replace(/^0+(?=[0-9])/, '');
    const fraction = (match[2] ?? '').replace(/0+$/, '');

    if (fraction.length > precision) {
        return { problem: precision === 0 ? { kind: 'whole' } : { kind: 'decimals', precision } };
    }

    if (whole.length > QUANTITY_DIGITS_MAX) {
        return { problem: { kind: 'max' } };
    }

    return { quantity: fraction === '' ? whole : `${whole}.${fraction}` };
}

/** The line total, quantity × unit price rounded half-up once (BR-SALE-06), or null while the quantity is not valid or the total is out of range. */
export function lineTotalOf(line: CartLine): string | null {
    const read = readQuantity(line.quantity, line.product.precision);

    if (!('quantity' in read)) {
        return null;
    }

    try {
        return lineTotal(read.quantity, line.product.precision, line.product.price);
    } catch {
        return null;
    }
}

/** The total of the valid lines, the sum of their line totals (BR-SALE-06). */
export function cartTotal(lines: readonly CartLine[]): string {
    return toCanonical(lines.reduce<Exact>((sum, line) => addExact(sum, parseMoney(lineTotalOf(line) ?? '0')), { units: 0n, scale: 0 }));
}

/** Whether every line holds a valid quantity, and the total a stored amount. */
export function linesValid(lines: readonly CartLine[]): boolean {
    return lines.every((line) => lineTotalOf(line) !== null) && isStoredMoney(parseMoney(cartTotal(lines)));
}

/** Whether a price change waits for "Setuju harga baru" (UXS-18). */
export function priceReviewPending(lines: readonly CartLine[]): boolean {
    return lines.some((line) => line.previousPrice !== null);
}

/** The draft with these lines; a change of the lines empties the payment screen's entries (NAVIGATION_CONTRACTS UXP-102). */
function withLines(draft: TillDraft, lines: readonly CartLine[]): TillDraft {
    return { ...draft, lines, payment: EMPTY_PAYMENT };
}

/** A quantity one base unit more: "2" is "3" and "1,5" is "2,5"; an entry that is not a quantity becomes "1". */
function plusOne(text: string): string {
    const match = /^([0-9]+)(?:\.([0-9]+))?$/.exec(quantityFromEntry(text.trim()));

    if (match === null) {
        return '1';
    }

    const whole = (BigInt(match[1] ?? '0') + 1n).toString();

    return match[2] === undefined ? whole : `${whole},${match[2]}`;
}

/** A product's price as a newer read gives it: a changed amount waits for the review (UXS-18); the same amount at another revision changes nothing shown. */
function repriced(line: CartLine, price: string, revisionId: number): CartLine {
    if (revisionId === line.product.revisionId) {
        return line;
    }

    const changed = compareMoney(price, line.product.price) !== 0;
    const previousPrice = changed ? (line.previousPrice ?? line.product.price) : line.previousPrice;

    return { ...line, product: { ...line.product, price, revisionId }, previousPrice: previousPrice !== null && compareMoney(previousPrice, price) === 0 ? null : previousPrice };
}

/**
 * Adds one base unit of an active product (BR-CAT-29): to its own line when the cart holds one (D-067),
 * otherwise as a new line at the end. A newer price of that product, read with it, is applied and marked
 * for review (UXS-18).
 */
export function addProduct(draft: TillDraft, product: CartProduct): TillDraft {
    const existing = draft.lines.find((line) => line.product.id === product.id);

    if (existing === undefined) {
        return withLines(draft, [...draft.lines, { product, quantity: '1', previousPrice: null, available: null, refusal: null }]);
    }

    return withLines(
        draft,
        draft.lines.map((line) => {
            if (line !== existing) {
                return line;
            }

            const updated = repriced(line, product.price, product.revisionId);

            return { ...updated, product: { ...updated.product, name: product.name, sku: product.sku, thumbnail: product.thumbnail }, quantity: plusOne(line.quantity), available: null, refusal: null };
        }),
    );
}

/** The quantity of a line as typed (UXI-070): fractions only by an explicit edit (BR-CAT-29). */
export function setQuantity(draft: TillDraft, productId: number, text: string): TillDraft {
    return withLines(
        draft,
        draft.lines.map((line) => (line.product.id === productId ? { ...line, quantity: text, available: null } : line)),
    );
}

/** The smallest valid quantity of a product: one unit of its last decimal (BR-NUM-02). */
function smallest(precision: number): Exact {
    return { units: 1n, scale: precision };
}

/** Whether "−" can take one base unit from the line: not at its smallest valid quantity (UXI-071, UXS-13). */
export function canDecrease(line: CartLine): boolean {
    const read = readQuantity(line.quantity, line.product.precision);

    return 'quantity' in read && compareExact(parseQuantity(read.quantity, line.product.precision), smallest(line.product.precision)) > 0;
}

/** "+" adds one base unit; "−" takes one, never below the smallest valid quantity (UXI-071). */
export function stepQuantity(draft: TillDraft, productId: number, direction: 1 | -1): TillDraft {
    return withLines(
        draft,
        draft.lines.map((line) => {
            if (line.product.id !== productId) {
                return line;
            }

            if (direction === 1) {
                return { ...line, quantity: plusOne(line.quantity), available: null };
            }

            const read = readQuantity(line.quantity, line.product.precision);

            if (!('quantity' in read) || !canDecrease(line)) {
                return line;
            }

            const less = subtractExact(parseQuantity(read.quantity, line.product.precision), { units: 1n, scale: 0 });
            const floor = smallest(line.product.precision);

            return { ...line, quantity: quantityText(toCanonical(compareExact(less, floor) < 0 ? floor : less)), available: null };
        }),
    );
}

export function removeLine(draft: TillDraft, productId: number): TillDraft {
    return withLines(
        draft,
        draft.lines.filter((line) => line.product.id !== productId),
    );
}

/** "Kosongkan keranjang" after its confirmation (UXI-073). */
export function clearCart(draft: TillDraft): TillDraft {
    return withLines(draft, []);
}

/** "Setuju harga baru" (UXI-074): the explicit review of BR-SALE-05; the total shown is the new one. */
export function acceptNewPrices(draft: TillDraft): TillDraft {
    return { ...draft, lines: draft.lines.map((line) => ({ ...line, previousPrice: null })) };
}

function isRecord(value: unknown): value is Readonly<Record<string, unknown>> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/** The safe values of a refusal for the line sent at this index (RT-07 `lines`). */
function refusalLines(lines: Readonly<Record<string, unknown>>, sent: readonly number[]): Map<number, Readonly<Record<string, unknown>>> {
    const byProduct = new Map<number, Readonly<Record<string, unknown>>>();
    const byIndex = new Map(sent.map((productId, index) => [String(index), productId] as const));

    for (const [index, value] of Object.entries(lines)) {
        const productId = byIndex.get(index);

        if (productId !== undefined && isRecord(value)) {
            byProduct.set(productId, value);
        }
    }

    return byProduct;
}

/**
 * A definite refusal of RC-01 applied to the cart at the lines it names, by the products sent at their
 * index (INTERACTION_CONTRACTS section 1.6; NC-09): PRICE_CHANGED gives each changed line its price in
 * force and marks it for review (UXS-18), INSUFFICIENT_STOCK the stock available (UXS-31),
 * PRODUCT_INACTIVE and POSTING_BLOCKED the line's reason (UXS-32, UXS-28). The total never changes
 * silently: a new price waits for "Setuju harga baru".
 */
export function applyRefusal(draft: TillDraft, code: string, lines: Readonly<Record<string, unknown>>, sent: readonly number[]): TillDraft {
    const named = refusalLines(lines, sent);

    if (named.size === 0) {
        return draft;
    }

    const marked = draft.lines.map((line) => {
        const values = named.get(line.product.id);

        if (values === undefined) {
            return line;
        }

        switch (code) {
            case 'PRICE_CHANGED': {
                const price = isRecord(values.price) ? values.price : null;
                const amount = price !== null && typeof price.amount === 'string' ? price.amount : null;
                const revisionId = price !== null && typeof price.revisionId === 'number' && Number.isSafeInteger(price.revisionId) ? price.revisionId : null;

                return amount === null || revisionId === null ? line : repriced(line, amount, revisionId);
            }
            case 'INSUFFICIENT_STOCK':
                return typeof values.available === 'string' ? { ...line, available: values.available } : line;
            case 'PRODUCT_INACTIVE':
                return { ...line, refusal: 'inactive' as const };
            case 'POSTING_BLOCKED':
                return { ...line, refusal: 'blocked' as const };
            default:
                return line;
        }
    });

    return code === 'PRICE_CHANGED' ? withLines(draft, marked) : { ...draft, lines: marked };
}

/** What the payment screen shows for cash: the change, or the amount still missing (BR-SALE-11, BR-PAY-02). */
export type Tender = { readonly kind: 'none' } | { readonly kind: 'short'; readonly missing: string } | { readonly kind: 'enough'; readonly change: string };

export function tenderOf(total: string, tendered: string | null): Tender {
    if (tendered === null) {
        return { kind: 'none' };
    }

    let amount: Exact;

    try {
        amount = parseExact(tendered, 0, false, 'money amount');
    } catch {
        return { kind: 'none' };
    }

    if (!isStoredMoney(amount)) {
        return { kind: 'none' };
    }

    const canonical = toCanonical(amount);

    return compareMoney(canonical, total) < 0 ? { kind: 'short', missing: subtractMoney(total, canonical) } : { kind: 'enough', change: subtractMoney(canonical, total) };
}

/** The payload and expected revisions of RC-01 for this cart in this session (INTERACTION_CONTRACTS RC-01). */
export function checkoutRequest(draft: TillDraft, sessionId: number): { readonly payload: Readonly<Record<string, unknown>>; readonly expected: Readonly<Record<string, unknown>>; readonly products: readonly number[] } {
    const payment = draft.payment;
    const reference = payment.reference.trim();
    const customerName = payment.customerName.trim();
    const lines = draft.lines.map((line) => {
        const read = readQuantity(line.quantity, line.product.precision);

        return { productId: line.product.id, quantity: 'quantity' in read ? read.quantity : line.quantity.trim() };
    });

    return {
        payload: {
            sessionId,
            lines,
            payment:
                payment.method === CASH
                    ? { method: CASH, tendered: payment.tendered, customerName: customerName === '' ? null : customerName }
                    : { method: BANK_TRANSFER, reference: reference === '' ? null : reference, customerName: customerName === '' ? null : customerName },
        },
        expected: { lines: draft.lines.map((line) => ({ priceRevisionId: line.product.revisionId })) },
        products: draft.lines.map((line) => line.product.id),
    };
}

/** Whether a value read from the history state is a draft of this page; anything else starts an empty cart. */
export function draftOf(value: unknown): TillDraft {
    if (!isRecord(value) || !Array.isArray(value.lines) || !isRecord(value.payment)) {
        return EMPTY_DRAFT;
    }

    const lines = value.lines.filter((line): line is CartLine => isRecord(line) && isRecord(line.product) && typeof line.quantity === 'string');
    const payment = value.payment;
    const method = payment.method === BANK_TRANSFER ? BANK_TRANSFER : CASH;

    return {
        lines,
        payment: {
            method,
            tendered: typeof payment.tendered === 'string' ? payment.tendered : null,
            reference: typeof payment.reference === 'string' ? payment.reference : '',
            customerName: typeof payment.customerName === 'string' ? payment.customerName : '',
        },
    };
}
