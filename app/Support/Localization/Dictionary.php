<?php

namespace App\Support\Localization;

use LogicException;

/**
 * The interface dictionary of ADR-005: the texts of the translation files of
 * COPY_DECK section 1.1 in lang/id, by their full dot keys. HandleInertiaRequests
 * shares it once (item 4); scripts/checks/translation-types.php generates the
 * TypeScript key type from the same reading (item 5).
 */
final class Dictionary
{
    /**
     * The files of COPY_DECK section 1.1. Another file in lang/id, such as a
     * framework file, is never part of the interface dictionary.
     */
    public const array FILES = [
        'ux', 'access', 'commands', 'sales', 'cash', 'catalog',
        'inventory', 'costing', 'reporting', 'config', 'audit', 'documents',
    ];

    /**
     * The dictionary of the application's locale, `id` (ADR-005 item 2).
     *
     * @return array<string, string>
     */
    public static function forInterface(): array
    {
        return self::fromDirectory(lang_path('id'));
    }

    /**
     * Every text of the files in $directory, sorted by key. A file that is
     * absent contributes nothing; a key that is not snake_case, or a value
     * that is neither a text nor a group of texts, is a defect.
     *
     * @return array<string, string>
     */
    public static function fromDirectory(string $directory): array
    {
        $dictionary = [];

        foreach (self::FILES as $file) {
            $path = $directory.'/'.$file.'.php';

            if (! is_file($path)) {
                continue;
            }

            $texts = require $path;

            if (! is_array($texts)) {
                throw new LogicException(sprintf('The dictionary file %s does not return an array.', $file));
            }

            $nested = array_filter($texts, static fn (mixed $key): bool => ! is_string($key) || ! str_contains($key, '.'), ARRAY_FILTER_USE_KEY);
            self::flatten($file, $nested, $dictionary);
            self::childrenOfTexts($file, array_diff_key($texts, $nested), $dictionary);
        }

        ksort($dictionary, SORT_STRING);

        return $dictionary;
    }

    /**
     * @param  array<mixed>  $texts
     * @param  array<string, string>  $dictionary
     */
    private static function flatten(string $prefix, array $texts, array &$dictionary): void
    {
        foreach ($texts as $key => $value) {
            if (! is_string($key) || preg_match('/^[a-z0-9_]+$/D', $key) !== 1) {
                throw new LogicException(sprintf('The key %s.%s is not a snake_case key (COPY_DECK section 1.1).', $prefix, $key));
            }

            if (is_string($value)) {
                $dictionary[$prefix.'.'.$key] = $value;
            } elseif (is_array($value)) {
                self::flatten($prefix.'.'.$key, $value, $dictionary);
            } else {
                throw new LogicException(sprintf('The key %s.%s holds neither a text nor a group of texts.', $prefix, $key));
            }
        }
    }

    /**
     * The texts of a key that is itself a text (OPEN_ITEMS T-29; TASK-009):
     * COPY_DECK names `commands.family.<family>`, a label, and below it
     * `commands.family.<family>.must_not`. A file's tree holds a key either
     * as a text or as a group, so such a child is written at the top of its
     * file as one literal dotted key, `'family.sale.must_not'` — the form
     * that Laravel's translator looks up first (Arr::get reads the whole key
     * before it splits it). Each segment is snake_case, the value a text, its
     * parent a text of the same file, and no key is given twice; anything
     * else is a defect.
     *
     * @param  array<mixed>  $texts
     * @param  array<string, string>  $dictionary
     */
    private static function childrenOfTexts(string $file, array $texts, array &$dictionary): void
    {
        foreach ($texts as $key => $value) {
            $full = $file.'.'.$key;

            if (! is_string($key) || preg_match('/^[a-z0-9_]+(\.[a-z0-9_]+)+$/D', $key) !== 1 || ! is_string($value)) {
                throw new LogicException(sprintf('The key %s is not a snake_case dotted key of a text (COPY_DECK section 1.1).', $full));
            }

            if (array_key_exists($full, $dictionary)) {
                throw new LogicException(sprintf('The key %s is given twice.', $full));
            }

            if (! array_key_exists(substr($full, 0, (int) strrpos($full, '.')), $dictionary)) {
                throw new LogicException(sprintf('The dotted key %s is not the child of a text; write it in the tree (OPEN_ITEMS T-29).', $full));
            }

            $dictionary[$full] = $value;
        }
    }
}
