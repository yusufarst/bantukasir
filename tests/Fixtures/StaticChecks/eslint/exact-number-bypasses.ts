// Negative samples of the exact-number rules (ADR-006 item 5, ADR-005 item 6,
// R-19) from the class-A adversarial review of TASK-003, finding A-03:
// ordinary conversions to floats and locale or local-time formatting that the
// rules let through. Each line that ends with a tag is refused. Never run.
declare const price: string;
declare const prices: string[];
declare const when: Date;

export const a = prices.map(Number); // N01 Number as a callback
const toNumber = Number; // N02 Number under an alias
export const b = toNumber(price);
export const c = globalThis['Number'](price); // N03 Number by computed access
export const d = Number.call(null, price); // N04 Number.call
export const e = parseFloat.call(null, price); // N05 parseFloat.call
export const f: number = JSON.parse(price); // N06 JSON.parse of a canonical string
const { NumberFormat } = Intl; // N07 Intl.NumberFormat by destructuring
export const g = new NumberFormat('id-ID').format(1.5);
const intl = Intl; // N08 Intl under an alias
export const h = new intl.NumberFormat('id-ID').format(1.5);
export const i = new Intl['NumberFormat']('id-ID').format(1.5); // N09 Intl.NumberFormat by computed access
export const j = new Intl.RelativeTimeFormat('id').format(-1.5, 'day'); // N10 Intl.RelativeTimeFormat
export const k = when['toLocaleString'](); // N11 toLocaleString by computed access
export const l = when.getHours(); // N12 local time of the browser
export const m = prices.map(parseInt); // N13 parseInt as a callback
export const n = window['parseFloat'](price); // N14 parseFloat by computed access on window
export const o = Number?.(price); // N15 an optional call of Number
export const p = new Intl.Collator('id').compare(price, price); // N16 Intl.Collator
export const q = new Intl.PluralRules('id').select(1); // N17 Intl.PluralRules
export const r = globalThis.Intl; // N18 Intl through globalThis
export const s = JSON['parse'](price); // N19 JSON.parse by computed access
const { parse } = JSON; // N20 JSON.parse by destructuring
export const u = parse(price);
export const v = when.getMinutes(); // N21 local minutes of the browser
export const w = when.getTimezoneOffset(); // N22 the browser's time zone
export const x = when.toDateString(); // N23 a local date text
export const y = when.toTimeString(); // N24 a local time text
export const z = new Date(); // N25 a Date made in the browser
export const aa = Date(); // N26 Date() returns a local time text
export const ab = Date.parse(price); // N27 Date.parse reads local time
export const ac = Date.UTC(50, 0, 1); // N28 Date.UTC maps two-digit years
export const ad = when['getHours'](); // N29 local time by computed access
export const ae = price.toLocaleUpperCase(); // N30 locale case mapping
export const af = Number.parseInt(price); // N31 Number.parseInt
