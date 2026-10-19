<?php

// Translation-key check (TEST_STRATEGY section 6, REQ-NFR-01, ADR-005 item 5):
// every translation key that PHP, Blade, TypeScript or TSX code uses exists in
// lang/id, and no user-facing text is a literal in a Blade view or in a PHP
// message to the user. TASK-001 wired it into composer lint with the key
// check; TASK-003 added the literal check of PHP and Blade (ESLint's
// react/jsx-no-literals refuses literals in TSX) and, beside it,
// translation-types.php --check, which keeps the TypeScript key type equal to
// lang/id.
//
// The literal check reads:
// - in a Blade view, text between tags and the user-facing attributes title,
//   alt, placeholder, label, aria-label, aria-description, aria-placeholder,
//   aria-roledescription and aria-valuetext: outside {{ }}, {!! !!},
//   directives, comments, PHP blocks, scripts and styles, a letter there is a
//   literal;
// - in PHP, the messages that reach the user: the message of abort(),
//   abort_if() and abort_unless(), the message of a validation rule's $fail(),
//   and the values given to withMessages() and withErrors(); a string with a
//   letter there, outside __(), trans(), trans_choice(), Lang::get() and
//   Lang::choice(), is a literal.
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

        $code = (string) file_get_contents($path);

        foreach (preg_split('/\n/', $code) ?: [] as $index => $line) {
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

        $literals = match (true) {
            str_ends_with($path, '.blade.php') => bladeLiterals($code),
            $kind === 'php' => phpLiterals($code),
            default => [],
        };

        foreach ($literals as [$line, $text]) {
            $problems[] = sprintf('LITERAL %s (%s:%d)', $text, $path, $line);
        }
    }
}

if ($problems !== []) {
    fwrite(STDOUT, "Translation keys missing from {$lang}, or literal user-facing text:\n  ".implode("\n  ", $problems)."\n");
    exit(1);
}

fwrite(STDOUT, "Translation keys: {$used} used, all present in {$lang}; no literal user-facing text.\n");
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

/**
 * Literal text in a Blade view, as [line, text] pairs.
 *
 * @return list<array{int, string}>
 */
function bladeLiterals(string $code): array
{
    // Blank out, keeping the line breaks, everything that is not literal
    // markup: comments, PHP, echoes, scripts, styles and directives.
    $masked = $code;

    foreach ([
        '/\{\{--.*?--\}\}/s',
        '/<!--.*?-->/s',
        '/@php\b.*?@endphp/s',
        '/<\?php.*?\?>/s',
        '/\{!!.*?!!\}/s',
        '/\{\{.*?\}\}/s',
        '/<(script|style)\b[^>]*>.*?<\/\1\s*>/si',
        '/@[A-Za-z_]+(?:\s*(\((?:[^()]++|(?1))*\)))?/',
    ] as $pattern) {
        $masked = (string) preg_replace_callback($pattern, fn (array $match): string => (string) preg_replace('/[^\n]/', ' ', $match[0]), $masked);
    }

    $literals = [];
    $attribute = '/\s(title|alt|placeholder|label|aria-(?:label|description|placeholder|roledescription|valuetext))\s*=\s*(["\'])(.*?)\2/si';

    preg_match_all('/<[^>]*>/s', $masked, $tags, PREG_OFFSET_CAPTURE);

    foreach ($tags[0] as [$tag, $offset]) {
        preg_match_all($attribute, $tag, $values, PREG_OFFSET_CAPTURE);

        foreach ($values[3] as [$value, $at]) {
            if (preg_match('/\p{L}/u', $value) === 1) {
                $literals[] = [lineAt($code, $offset + $at), trim($value)];
            }
        }
    }

    // Text between tags: what is left once every tag is blanked out.
    $text = (string) preg_replace_callback('/<[^>]*>/s', fn (array $match): string => (string) preg_replace('/[^\n]/', ' ', $match[0]), $masked);
    preg_match_all('/[^\s<>]*\p{L}[^<>\n]*/u', $text, $words, PREG_OFFSET_CAPTURE);

    foreach ($words[0] ?? [] as [$word, $offset]) {
        $literals[] = [lineAt($code, $offset), trim($word)];
    }

    return $literals;
}

/**
 * Literal text in the PHP messages that reach the user, as [line, text] pairs.
 *
 * @return list<array{int, string}>
 */
function phpLiterals(string $code): array
{
    $tokens = array_values(array_filter(
        array_map(fn (array|string $token): array => is_array($token) ? $token : [0, $token, 0], token_get_all($code)),
        fn (array $token): bool => ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
    ));
    $translations = ['__', 'trans', 'trans_choice', 'get', 'choice'];
    $literals = [];

    foreach ($tokens as $index => [$id, $text]) {
        if (($tokens[$index + 1][1] ?? null) !== '(') {
            continue;
        }

        $name = ltrim($text, '\\');
        $previous = $tokens[$index - 1][0] ?? null;
        $member = in_array($previous, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true);

        // The arguments that carry a message: null means every argument.
        $messages = match (true) {
            in_array($id, [T_STRING, T_NAME_FULLY_QUALIFIED], true) && ! $member && $previous !== T_FUNCTION => match ($name) {
                'abort' => [1],
                'abort_if', 'abort_unless' => [2],
                default => false,
            },
            $id === T_VARIABLE && $text === '$fail' => [0],
            $id === T_STRING && $member && in_array($name, ['withMessages', 'withErrors'], true) => null,
            default => false,
        };

        if ($messages === false) {
            continue;
        }

        $argument = 0;
        $depth = 0;

        for ($at = $index + 1; $at < count($tokens); $at++) {
            [$tokenId, $tokenText, $line] = $tokens[$at];

            if (in_array($tokenText, ['(', '[', '{'], true) || in_array($tokenId, [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) {
                $depth++;
            } elseif (in_array($tokenText, [')', ']', '}'], true)) {
                if (--$depth === 0) {
                    break;
                }
            } elseif ($tokenText === ',' && $depth === 1) {
                $argument++;
            } elseif ($tokenId === T_STRING && in_array($tokenText, $translations, true) && ($tokens[$at + 1][1] ?? null) === '(') {
                $at = closingParenthesis($tokens, $at + 1);
            } elseif (in_array($tokenId, [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)
                && ($messages === null || in_array($argument, $messages, true))
                && ($tokens[$at + 1][0] ?? null) !== T_DOUBLE_ARROW
                && preg_match('/\p{L}/u', $tokenText) === 1) {
                $literals[] = [$line, trim($tokenText, '\'"')];
            }
        }
    }

    return $literals;
}

/**
 * The index of the parenthesis that closes the one at $open.
 *
 * @param  list<array{int, string, int}>  $tokens
 */
function closingParenthesis(array $tokens, int $open): int
{
    $depth = 0;

    for ($at = $open; $at < count($tokens); $at++) {
        if ($tokens[$at][1] === '(') {
            $depth++;
        } elseif ($tokens[$at][1] === ')' && --$depth === 0) {
            return $at;
        }
    }

    return count($tokens) - 1;
}

function lineAt(string $code, int $offset): int
{
    return substr_count(substr($code, 0, $offset), "\n") + 1;
}
