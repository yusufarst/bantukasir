// The client's side of the localization of ADR-005: the interface dictionary
// that HandleInertiaRequests shares once per full page load (item 4) and the
// typed helper t(key, replacements) that reads it. The key type is generated
// from lang/id (item 5), so an unknown key or a missing placeholder fails the
// typecheck and the build. No third-party internationalization runtime.
import type { TranslationKey, TranslationPlaceholders } from '@/types/translation-keys';

/**
 * The replacements of a key: none for a text without placeholders. It is
 * distributive, so that a key missing from lang/id is reported as the wrong
 * key rather than as a wrong number of arguments.
 */
export type Replacements<K extends TranslationKey> = K extends TranslationKey
    ? [TranslationPlaceholders[K]] extends [never]
        ? []
        : [replacements: Readonly<Record<TranslationPlaceholders[K], string>>]
    : never;

let dictionary: ReadonlyMap<string, string> | null = null;

/**
 * Keeps the dictionary that the first page of a full page load carries, as
 * the once prop `dictionary`. Anything else than an object of texts is a
 * defect of the server.
 */
export function installDictionary(value: unknown): void {
    if (typeof value !== 'object' || value === null || Array.isArray(value)) {
        throw new Error('The interface dictionary is missing from the page (ADR-005 item 4).');
    }

    const entries = Object.entries(value);

    for (const [key, text] of entries) {
        if (typeof text !== 'string') {
            throw new Error(`The dictionary entry ${key} is not a text.`);
        }
    }

    dictionary = new Map(entries as [string, string][]);
}

/**
 * The Indonesian text of a key, with its `:name` placeholders replaced by
 * values already formatted (COPY_DECK section 1.2). A key the dictionary lacks
 * is a defect and throws; it is never shown as its key.
 */
export function t<K extends TranslationKey>(key: K, ...replacements: Replacements<K>): string;
export function t(key: TranslationKey, replacements?: Readonly<Record<string, string>>): string {
    const text = dictionary?.get(key);

    if (text === undefined) {
        throw new Error(`The translation key ${key} is missing from the dictionary (ADR-005 item 5).`);
    }

    return replacements === undefined ? text : replacePlaceholders(text, replacements);
}

/**
 * Laravel's replacement of placeholders (Translator::makeReplacements, then
 * strtr): `:name` takes the value, `:Name` the value with its first letter
 * upper-cased and `:NAME` the value upper-cased; at each position the longest
 * placeholder wins, and a replaced value is never searched again.
 */
export function replacePlaceholders(text: string, replacements: Readonly<Record<string, string>>): string {
    const pairs = new Map<string, string>();

    for (const [name, value] of Object.entries(replacements)) {
        pairs.set(`:${upperFirst(name)}`, upperFirst(value));
        pairs.set(`:${name.toUpperCase()}`, value.toUpperCase());
        pairs.set(`:${name}`, value);
    }

    const placeholders = [...pairs.keys()].sort((a, b) => b.length - a.length);
    let result = '';
    let position = 0;

    while (position < text.length) {
        const placeholder = placeholders.find((candidate) => text.startsWith(candidate, position));

        if (placeholder === undefined) {
            result += text.charAt(position);
            position += 1;
        } else {
            result += pairs.get(placeholder) ?? '';
            position += placeholder.length;
        }
    }

    return result;
}

function upperFirst(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}
