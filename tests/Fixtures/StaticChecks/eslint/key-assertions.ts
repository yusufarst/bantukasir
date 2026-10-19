// Negative samples of the rule against asserted translation keys (ADR-005
// item 5, AC-003-06) from the class-A adversarial review of TASK-003, finding
// A-06: a type assertion makes any string pass the typecheck of t(), so lint
// refuses every assertion to TranslationKey outside the generated key file and
// resources/js/lib/i18n.ts. Each line that ends with a tag is refused. Never
// run.
import { t } from '@/lib/i18n';
import type { TranslationKey, TranslationPlaceholders } from '@/types/translation-keys';

declare const name: string;
declare const fromServer: Record<string, string>;

export const a = t(('ux.format.' + 'nope') as TranslationKey); // T01 a key built by concatenation
export const b = t(`ux.format.month.${name}` as TranslationKey); // T02 a template literal with an assertion
const key = 'ux.format.nope' as string as TranslationKey; // T03 a key through string
export const c = t(key);
export const d = t(fromServer['state'] as TranslationKey); // T04 a key from data
export const e = t(<TranslationKey>name); // T05 an angle-bracket assertion
export const f = [name] as TranslationKey[]; // T06 an array of asserted keys
export const g = name as keyof TranslationPlaceholders; // T07 the keys through keyof
export const h = name as Parameters<typeof t>[0]; // T08 the key through the type of t()
type Key = TranslationKey; // T09 an alias of the key type
export const i = name as Key;
type MonthKey = Extract<TranslationKey, `ux.format.month.${string}`>; // T10 a narrowed alias of the key type
export const j = name as MonthKey;
export const k = name as unknown as readonly TranslationKey[]; // T11 a double assertion to keys
export const l = name as TranslationKey | undefined; // T12 a key or nothing
export const m = name satisfies string as TranslationKey; // T13 satisfies, then an assertion
