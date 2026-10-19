// Exact numbers in the browser (ADR-006 items 2 and 6): money, quantity and
// cost arrive as canonical strings and are computed with scaled BigInt, never
// as JavaScript numbers. A browser computation is a preview only; the server
// recomputes every price, total and change and refuses a mismatch
// (BR-DOM-06, BR-SALE-05). app/Support/Numbers is the authoritative twin,
// proven equal on one table of cases.

/** An exact decimal: units / 10^scale. */
export interface Exact {
    readonly units: bigint;
    readonly scale: number;
}

/** The ranges of TECHNICAL_RULES section 11.1 (BR-NUM-04). */
export const LIMITS = {
    moneyMax: '999999999999',
    quantityMax: '99999999.999',
    stockMax: '9999999999.999',
    costMax: '999999999999.999999',
    valueMax: '999999999999999.999999',
} as const;

/** The scale of a cost value (BR-NUM-03). */
export const COST_SCALE = 6;

/** The explicit UNKNOWN marker of a cost (ADR-006 item 1). */
export const UNKNOWN_COST = 'UNKNOWN';

/** The longest canonical string that is parsed (app/Support/Numbers/CanonicalNumber). */
export const MAX_LENGTH = 40;

const CANONICAL = /^(-?)(0|[1-9][0-9]*)(?:\.([0-9]+))?$/;

export class NonCanonicalNumberError extends Error {
    constructor(kind: string) {
        super(`The ${kind} is not a canonical number string (ADR-006 item 2).`);
        this.name = 'NonCanonicalNumberError';
    }
}

export class NumberOutOfRangeError extends Error {
    constructor(kind: string) {
        super(`The ${kind} is outside its supported range (TECHNICAL_RULES section 11.1).`);
        this.name = 'NumberOutOfRangeError';
    }
}

/**
 * Parses a canonical string (ADR-006 item 2) with at most maxScale fractional
 * digits, as written, and a sign only when signed. Anything else is refused,
 * never repaired — a value that is not a string first of all, such as a JSON
 * number, which RegExp.exec would turn into a string (TASK-003 finding A-04).
 */
export function parseExact(value: string, maxScale: number, signed: boolean, kind: string): Exact {
    if (typeof value !== 'string') {
        throw new NonCanonicalNumberError(kind);
    }

    const match = CANONICAL.exec(value);
    const integer = match?.[2];

    if (match === null || integer === undefined) {
        throw new NonCanonicalNumberError(kind);
    }

    const negative = match[1] === '-';
    const fraction = match[3] ?? '';

    if ((negative && /^0*$/.test(integer + fraction)) || fraction.length > maxScale || (negative && !signed)) {
        throw new NonCanonicalNumberError(kind);
    }

    if (value.length > MAX_LENGTH) {
        throw new NumberOutOfRangeError(kind);
    }

    const units = BigInt(integer + fraction);

    return { units: negative ? -units : units, scale: fraction.length };
}

/** The canonical string of an exact value in its shortest form. */
export function toCanonical(value: Exact): string {
    let { units, scale } = value;

    while (scale > 0 && units % 10n === 0n) {
        units /= 10n;
        scale -= 1;
    }

    const negative = units < 0n;
    const digits = (negative ? -units : units).toString().padStart(scale + 1, '0');
    const integer = digits.slice(0, digits.length - scale);
    const text = scale === 0 ? integer : `${integer}.${digits.slice(digits.length - scale)}`;

    return negative ? `-${text}` : text;
}

/**
 * A scale is a whole number of 0 or more, as Brick Math requires on the
 * server (TASK-003 finding A-17).
 */
function checkScale(scale: number): void {
    if (!Number.isSafeInteger(scale) || scale < 0) {
        throw new RangeError('A scale is a whole number of 0 or more.');
    }
}

/** The units of an exact value at a scale at least its own, exactly. */
export function unitsAt(value: Exact, scale: number): bigint {
    checkScale(scale);

    if (scale < value.scale) {
        throw new RangeError('Rescaling to fewer decimals needs a rounding rule: use roundHalfUp.');
    }

    return value.units * 10n ** BigInt(scale - value.scale);
}

/**
 * Rounds to a scale half-up, ties away from zero, once (ADR-006 item 4,
 * ADR-008 section 5).
 */
export function roundHalfUp(value: Exact, scale: number): Exact {
    checkScale(scale);

    if (scale >= value.scale) {
        return { units: unitsAt(value, scale), scale };
    }

    const divisor = 10n ** BigInt(value.scale - scale);
    const negative = value.units < 0n;
    const magnitude = negative ? -value.units : value.units;
    const quotient = magnitude / divisor;
    const rounded = (magnitude % divisor) * 2n >= divisor ? quotient + 1n : quotient;

    return { units: negative ? -rounded : rounded, scale };
}

export function compareExact(a: Exact, b: Exact): -1 | 0 | 1 {
    const scale = Math.max(a.scale, b.scale);
    const left = unitsAt(a, scale);
    const right = unitsAt(b, scale);

    return left < right ? -1 : left > right ? 1 : 0;
}

export function addExact(a: Exact, b: Exact): Exact {
    const scale = Math.max(a.scale, b.scale);

    return { units: unitsAt(a, scale) + unitsAt(b, scale), scale };
}

export function subtractExact(a: Exact, b: Exact): Exact {
    const scale = Math.max(a.scale, b.scale);

    return { units: unitsAt(a, scale) - unitsAt(b, scale), scale };
}

export function multiplyExact(a: Exact, b: Exact): Exact {
    return { units: a.units * b.units, scale: a.scale + b.scale };
}

/** A money amount: a canonical signed integer string (BR-NUM-01). */
export function parseMoney(value: string): Exact {
    return parseExact(value, 0, true, 'money amount');
}

/** A quantity at the product's precision of 0 to 3 decimals (BR-NUM-02). */
export function parseQuantity(value: string, precision: number): Exact {
    if (!Number.isInteger(precision) || precision < 0 || precision > 3) {
        throw new RangeError('A quantity precision is 0 to 3 decimals (BR-NUM-02).');
    }

    return parseExact(value, precision, true, 'quantity');
}

/**
 * A known cost: a canonical non-negative string with at most six decimals
 * (BR-NUM-03). The UNKNOWN marker is refused here: an UNKNOWN cost is never a
 * number (BR-COST-03).
 */
export function parseCost(value: string): Exact {
    return parseExact(value, COST_SCALE, false, 'cost');
}

/** Whether a stored money amount is within 0 to LIMITS.moneyMax. */
export function isStoredMoney(value: Exact): boolean {
    return value.units >= 0n && compareExact(value, parseExact(LIMITS.moneyMax, 0, false, 'limit')) <= 0;
}

/**
 * A line total preview: quantity × unit price, rounded half-up once to whole
 * rupiah (ADR-006 item 4, BR-NUM-01); an overflow of the stored range is
 * refused, never truncated.
 */
export function lineTotal(quantity: string, precision: number, unitPrice: string): string {
    const total = roundHalfUp(multiplyExact(parseQuantity(quantity, precision), parseMoney(unitPrice)), 0);

    if (!isStoredMoney(total)) {
        throw new NumberOutOfRangeError('money amount');
    }

    return toCanonical(total);
}

/** The exact sum of money amounts, for example a cart total of its line totals. */
export function sumMoney(...amounts: string[]): string {
    return toCanonical(amounts.map(parseMoney).reduce(addExact, { units: 0n, scale: 0 }));
}

/** The exact difference a − b, for example the change of a cash payment. */
export function subtractMoney(a: string, b: string): string {
    return toCanonical(subtractExact(parseMoney(a), parseMoney(b)));
}

export function compareMoney(a: string, b: string): -1 | 0 | 1 {
    return compareExact(parseMoney(a), parseMoney(b));
}
