// Negative sample of the translation-key check: a key that the dictionary lacks.
declare function t(key: string): string;

export const label = t('sample.greeting.absent');
