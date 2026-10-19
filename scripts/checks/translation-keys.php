<?php

// Translation-key check (TEST_STRATEGY section 6, REQ-NFR-01, ADR-005 item 5):
// every translation key that PHP, Blade, TypeScript or TSX code uses exists in
// lang/id. TASK-001 wires it into composer lint; TASK-003 completes it with the
// localization mechanism and the check of literal user-facing text in PHP and
// Blade (ESLint's react/jsx-no-literals already refuses literals in TSX).
//
// Usage: php scripts/checks/translation-keys.php [--lang=DIR] [--source=DIR ...]

$root = dirname(__DIR__, 2);
$lang = $root.'/lang/id';
$sources = [];

foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--lang=')) {
        $lang = substr($argument, strlen('--lang='));
    } elseif (str_starts_with($argument, '--source=')) {
        $sources[] = substr($argument, strlen('--source='));
    } else {
        fwrite(STDERR, "Unknown argument: {$argument}\n");
        exit(2);
    }
}

if ($sources === []) {
    $sources = [$root.'/app', $root.'/resources/js', $root.'/resources/views', $root.'/routes'];
}

// The calls that read a translation: __(), trans(), trans_choice(), @lang(),
// Lang::get() and Lang::choice() on the server; t() on the client (ADR-005).
$patterns = [
    'php' => '/(?<![\w$>\\\\])(?:__|trans|trans_choice|@lang|Lang::get|Lang::choice)\(\s*([\'"])((?:(?!\1).)*)\1/',
    'ts' => '/(?<![\w$.])t\(\s*([\'"`])((?:(?!\1).)*)\1/',
];

$dictionaries = [];
$used = 0;
$problems = [];

foreach ($sources as $source) {
    if (! is_dir($source)) {
        continue;
    }

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        $path = (string) $file;
        $kind = match (true) {
            str_ends_with($path, '.php') => 'php',
            str_ends_with($path, '.ts'), str_ends_with($path, '.tsx') => 'ts',
            default => null,
        };

        if ($kind === null) {
            continue;
        }

        foreach (file($path) ?: [] as $index => $line) {
            preg_match_all($patterns[$kind], $line, $matches);

            foreach ($matches[2] as $match => $key) {
                $used++;
                $where = sprintf('%s:%d', $path, $index + 1);

                if (preg_match('/[$`{}]/', $key) === 1 || $matches[1][$match] === '`') {
                    $problems[] = "NOT A CONSTANT KEY {$key} ({$where})";
                } elseif (! keyExists($key, $lang, $dictionaries)) {
                    $problems[] = "MISSING {$key} ({$where})";
                }
            }
        }
    }
}

if ($problems !== []) {
    fwrite(STDOUT, "Translation keys missing from {$lang}:\n  ".implode("\n  ", $problems)."\n");
    exit(1);
}

fwrite(STDOUT, "Translation keys: {$used} used, all present in {$lang}.\n");
exit(0);

/**
 * A key is "<file>.<path>": the file lang/id/<file>.php returns an array, and
 * the path leads to a string in it.
 *
 * @param  array<string, mixed>  $dictionaries
 */
function keyExists(string $key, string $lang, array &$dictionaries): bool
{
    $segments = explode('.', $key);

    if (count($segments) < 2 || preg_match('/^[a-z0-9_]+$/', $segments[0]) !== 1) {
        return false;
    }

    $file = array_shift($segments);

    if (! array_key_exists($file, $dictionaries)) {
        $path = "{$lang}/{$file}.php";
        $dictionaries[$file] = is_file($path) ? require $path : null;
    }

    $value = $dictionaries[$file];

    foreach ($segments as $segment) {
        if (! is_array($value) || ! array_key_exists($segment, $value)) {
            return false;
        }

        $value = $value[$segment];
    }

    return is_string($value);
}
