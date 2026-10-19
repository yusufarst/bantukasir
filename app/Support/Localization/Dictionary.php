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

            self::flatten($file, $texts, $dictionary);
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
}
