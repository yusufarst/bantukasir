// Negative samples of the translation-key check in TypeScript, from the class-A
// adversarial review of TASK-003, finding A-06: the check reads every call of
// t(), also as a member and with space before the parenthesis. A line tagged K
// is reported; a line tagged C is a control and is not. Never executed.
declare function t(key: string): string;
declare const i18n: { t: (key: string) => string };

export const a = i18n.t('sample.greeting.nope_member'); // K41 t() called as a member
export const b = t ('sample.greeting.nope_space'); // K42 a space before the parenthesis
export const c = t( // K43 the key on the next line
    'sample.greeting.nope_next_line',
);
export const d = t('sample.greeting.nope_cast' as string); // K44 a literal key with an assertion
export const e = t(`sample.greeting.title`); // K45 a template literal
export const f = t('sample.greeting.title'); // C01 a present key
export const g = i18n.t('sample.greeting.title'); // C02 a present key as a member
export const h = format('not a key'); // C03 another function
declare function format(text: string): string;
