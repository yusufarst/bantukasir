<?php

// The TypeScript key type of the interface dictionary (ADR-005 item 5,
// TASK-003): resources/js/types/translation-keys.ts, generated from the files
// of COPY_DECK section 1.1 in lang/id, with the names of the :name
// placeholders of each text, so that t() refuses an unknown key, a missing
// placeholder or an extra one in the typecheck and in the build. Run it after
// changing lang/id; composer lint runs it with --check, which fails when the
// committed file differs from what lang/id generates. Both modes fail on a
// capitalized placeholder (:Name, :NAME), which lang/id may not use.
//
// Usage: php scripts/checks/translation-types.php [--check] [--lang=DIR] [--output=FILE]

use App\Support\Localization\Dictionary;

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

$lang = $root.'/lang/id';
$output = $root.'/resources/js/types/translation-keys.ts';
$check = false;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--check') {
        $check = true;
    } elseif (str_starts_with($argument, '--lang=')) {
        $lang = substr($argument, strlen('--lang='));
    } elseif (str_starts_with($argument, '--output=')) {
        $output = substr($argument, strlen('--output='));
    } else {
        fwrite(STDERR, "Unknown argument: {$argument}\n");
        exit(2);
    }
}

$dictionary = Dictionary::fromDirectory($lang);

// A capitalized placeholder, :Name or :NAME, is refused (TASK-003 finding
// A-15, reading R-32): Laravel title-cases the first code point of such a
// value, which the client could only approximate, and no COPY_DECK text uses
// one, so refusing them keeps the server and the client equal by construction.
$capitalized = [];

foreach ($dictionary as $key => $text) {
    preg_match_all('/:(\p{Lu}[\p{L}\p{N}_]*)/u', $text, $matches);

    foreach ($matches[1] as $name) {
        $capitalized[] = "CAPITALIZED PLACEHOLDER :{$name} in {$key}";
    }
}

if ($capitalized !== []) {
    fwrite(STDOUT, "Translation types: {$lang} holds placeholders that only lower-case names may take:\n  ".implode("\n  ", $capitalized)."\n");
    exit(1);
}

$lines = [
    '// Generated from lang/id by scripts/checks/translation-types.php (ADR-005 item 5).',
    '// Do not edit: run that script after changing lang/id; composer lint fails',
    '// when this file differs from what lang/id generates.',
    '',
    '/** Every key of the interface dictionary, with the names of its placeholders. */',
    'export interface TranslationPlaceholders {',
];

foreach ($dictionary as $key => $text) {
    preg_match_all('/:([a-z][a-z0-9_]*)/', $text, $matches);
    $names = array_values(array_unique($matches[1]));
    sort($names, SORT_STRING);
    $type = $names === [] ? 'never' : implode(' | ', array_map(fn (string $name): string => "'{$name}'", $names));
    $lines[] = "    '{$key}': {$type};";
}

$lines[] = '}';
$lines[] = '';
$lines[] = 'export type TranslationKey = keyof TranslationPlaceholders;';
$generated = implode("\n", $lines)."\n";

if ($check) {
    if (! is_file($output) || file_get_contents($output) !== $generated) {
        fwrite(STDOUT, "Translation types: {$output} differs from what {$lang} generates; run php scripts/checks/translation-types.php.\n");
        exit(1);
    }

    fwrite(STDOUT, sprintf("Translation types: %s is current, %d keys.\n", $output, count($dictionary)));
    exit(0);
}

file_put_contents($output, $generated);
fwrite(STDOUT, sprintf("Translation types: wrote %s, %d keys.\n", $output, count($dictionary)));
exit(0);
