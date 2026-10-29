<?php

// Translation-key check (TEST_STRATEGY section 6, REQ-NFR-01, ADR-005 item 5):
// every translation key that PHP, Blade, TypeScript or TSX code uses exists in
// lang/id, and no user-facing text is a literal in a Blade view or in a PHP
// message to the user. TASK-001 wired it into composer lint with the key
// check; TASK-003 added the literal check of PHP and Blade (ESLint refuses
// literals in TSX) and, beside it, translation-types.php --check, which keeps
// the TypeScript key type equal to lang/id. After the class-A adversarial
// review of TASK-003 (findings A-06 and A-07, reading R-36) it reads:
//
// - Keys, in PHP by tokens: every entry point of Laravel's translator —
//   __(), \__(), trans(), trans_choice(), the Lang facade with or without its
//   namespace or under an alias, get() and choice() on app('translator') and
//   on trans() — with positional or named arguments; in Blade the same inside
//   echoes, directives, PHP blocks and bound attributes, and @lang and
//   @choice. A key that is not a string literal is refused, and so is a
//   literal key missing from lang/id. In TypeScript, every call of t(), also
//   as a member (i18n.t) and with space or a line break before its key.
// - Literals with a letter, outside translation calls, in the PHP sinks that
//   reach the user: the message of abort(), abort_if() and abort_unless(); a
//   validation rule's $fail(); withMessages() and withErrors(); the values of
//   messages() and attributes() of a FormRequest; Inertia::render(),
//   inertia() and Inertia::share() props; with() and flash() of a redirect or
//   the session; response()->json() and Response::json(); errors()->add();
//   the messages and attributes of Validator::make(), Validator::validate(),
//   validator() and validate(); and the messages of new CostRule(). A literal
//   that is an array key, an array offset, the operand of a comparison or an
//   argument of another call (a route name, a configuration key) is not text.
// - In a Blade view: text between tags; every attribute except the reviewed
//   allowlist of attributes that never hold text, NON_TEXT_ATTRIBUTES below,
//   and the content of the viewport meta tag; literals echoed by {{ }} and
//   {!! !!} and bound to a component attribute; the second argument of
//   @section and @yield; and the array values given to any directive.
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

// The attributes that never hold user-facing text, reviewed for TASK-003
// (finding A-07): every other attribute with a letter in a Blade view is a
// literal.
const NON_TEXT_ATTRIBUTES = [
    'accept', 'action', 'allow', 'as', 'autocomplete', 'autofocus', 'charset', 'checked', 'class', 'colspan', 'cols', 'crossorigin',
    'd', 'data-page', 'data-testid', 'decoding', 'dir', 'disabled', 'enctype', 'fill', 'for', 'form', 'headers', 'height', 'hidden',
    'href', 'http-equiv', 'id', 'inputmode', 'integrity', 'lang', 'list', 'loading', 'max', 'maxlength', 'media', 'method', 'min',
    'minlength', 'multiple', 'name', 'nonce', 'novalidate', 'open', 'pattern', 'readonly', 'referrerpolicy', 'rel', 'required', 'role',
    'rows', 'rowspan', 'sandbox', 'scope', 'selected', 'size', 'sizes', 'spellcheck', 'src', 'srcset', 'step', 'stroke', 'style',
    'tabindex', 'target', 'translate', 'type', 'viewbox', 'width', 'wrap', 'xmlns',
    'aria-activedescendant', 'aria-atomic', 'aria-autocomplete', 'aria-busy', 'aria-checked', 'aria-controls', 'aria-current',
    'aria-describedby', 'aria-details', 'aria-disabled', 'aria-errormessage', 'aria-expanded', 'aria-haspopup', 'aria-hidden',
    'aria-invalid', 'aria-labelledby', 'aria-level', 'aria-live', 'aria-modal', 'aria-multiline', 'aria-multiselectable',
    'aria-orientation', 'aria-owns', 'aria-pressed', 'aria-readonly', 'aria-relevant', 'aria-required', 'aria-selected', 'aria-sort',
];

// Functions whose arguments are text that they pass on: a literal inside them
// in a sink is a literal of the sink.
const PASS_THROUGH = ['sprintf', 'vsprintf', 'ucfirst', 'lcfirst', 'ucwords', 'strtoupper', 'strtolower', 'mb_strtoupper', 'mb_strtolower', 'trim', 'e', 'nl2br', 'implode'];

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
        $code = (string) file_get_contents($path);

        [$keys, $literals] = match (true) {
            str_ends_with($path, '.blade.php') => bladeFindings($code),
            str_ends_with($path, '.php') => phpFindings($code, 1, true),
            preg_match('/\.(ts|tsx|mts|cts|js|jsx)$/', $path) === 1 => [typescriptKeys($code), []],
            default => [[], []],
        };

        foreach ($keys as [$line, $key, $written]) {
            $used++;
            $where = sprintf('%s:%d', $path, $line);

            if ($key === null) {
                $problems[] = "NOT A CONSTANT KEY {$written} ({$where})";
            } elseif (! keyExists($key, $lang, $dictionaries)) {
                $problems[] = "MISSING {$key} ({$where})";
            }
        }

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
 * the path leads to a string in it — or, as Laravel's translator reads it
 * first (Arr::get), the whole path is a literal key at the top of the file:
 * the child of a key that is itself a text (OPEN_ITEMS T-29; TASK-009).
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

    if (is_array($value) && array_key_exists(implode('.', $segments), $value)) {
        return is_string($value[implode('.', $segments)]);
    }

    foreach ($segments as $segment) {
        if (! is_array($value) || ! array_key_exists($segment, $value)) {
            return false;
        }

        $value = $value[$segment];
    }

    return is_string($value);
}

/**
 * The keys of the calls of t() in TypeScript, as [line, key or null, written]:
 * a template literal is not a constant key.
 *
 * @return list<array{int, string|null, string}>
 */
function typescriptKeys(string $code): array
{
    preg_match_all('/(?<![\w$])t\s*\(\s*(?:(?:\/\/[^\n]*\n|\/\*.*?\*\/)\s*)*([\'"`])((?:(?!\1)[^\n])*)\1/s', $code, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    $keys = [];

    foreach ($matches as $match) {
        $written = $match[2][0];
        $constant = $match[1][0] !== '`' && preg_match('/[$`{}\\\\]/', $written) !== 1;
        $keys[] = [lineAt($code, $match[0][1]), $constant ? $written : null, $written];
    }

    return $keys;
}

/**
 * The keys and the literal texts of PHP code, as [line, key or null, written]
 * and [line, text] pairs. $firstLine is the line of the code's first line in
 * its file, for a fragment of a Blade view; $sinks says whether to read the
 * sinks of a PHP file, which a Blade fragment does not have.
 *
 * @return array{list<array{int, string|null, string}>, list<array{int, string}>}
 */
function phpFindings(string $code, int $firstLine, bool $sinks): array
{
    $tokens = phpTokens($code, $firstLine);
    $facades = langFacadeNames($tokens);
    $keys = [];
    $literals = [];

    foreach ($tokens as $index => $token) {
        $call = translationCall($tokens, $index, $facades);

        if ($call !== null) {
            [$arguments, $function] = $call;
            $key = keyArgument($arguments, $function === 'trans_choice' || $function === 'choice');

            if ($key !== null) {
                $keys[] = [$token[2], ...constantKey($key)];
            }
        }

        if ($sinks) {
            array_push($literals, ...sinkLiterals($tokens, $index));
        }
    }

    return [$keys, $literals];
}

/**
 * The tokens of PHP code without whitespace and comments, as [id, text, line];
 * a one-character token takes the line of the token before it.
 *
 * @return list<array{int, string, int}>
 */
function phpTokens(string $code, int $firstLine): array
{
    $tokens = [];
    $line = $firstLine;

    foreach (token_get_all($code) as $token) {
        if (is_array($token)) {
            $line = $token[2] + $firstLine - 1;

            if (! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_CLOSE_TAG, T_INLINE_HTML], true)) {
                $tokens[] = [$token[0], $token[1], $line];
            }
        } else {
            $tokens[] = [0, $token, $line];
        }
    }

    return $tokens;
}

/**
 * The names under which this code reaches the Lang facade: the global alias,
 * its full name, and every alias a `use` statement gives it.
 *
 * @param  list<array{int, string, int}>  $tokens
 * @return list<string>
 */
function langFacadeNames(array $tokens): array
{
    $names = ['lang', 'illuminate\\support\\facades\\lang'];

    foreach ($tokens as $index => [$id]) {
        if ($id !== T_USE || ($tokens[$index + 1][1] ?? null) === '(') {
            continue;
        }

        $name = strtolower(ltrim($tokens[$index + 1][1] ?? '', '\\'));

        if ($name === 'illuminate\\support\\facades\\lang') {
            $alias = ($tokens[$index + 2][0] ?? null) === T_AS ? strtolower($tokens[$index + 3][1] ?? '') : 'lang';
            $names[] = $alias;
        } elseif (($tokens[$index + 1][1] ?? '') !== '' && str_ends_with($name, '\\lang') && ($tokens[$index + 2][0] ?? null) !== T_AS) {
            // Another class imported as Lang: Lang:: is not the facade here.
            $names = array_values(array_diff($names, ['lang']));
        }
    }

    return $names;
}

/**
 * The arguments of a translation lookup that starts at this token, with the
 * name of the function or method, or null: __(), trans() with arguments,
 * trans_choice(), Lang::get() and Lang::choice(), and get() or choice() on
 * app('translator') or on trans().
 *
 * @param  list<array{int, string, int}>  $tokens
 * @param  list<string>  $facades
 * @return array{list<list<array{int, string, int}>>, string}|null
 */
function translationCall(array $tokens, int $index, array $facades): ?array
{
    [$id, $text] = $tokens[$index];
    $next = $tokens[$index + 1][1] ?? null;
    $previous = $tokens[$index - 1][0] ?? null;
    $name = strtolower(ltrim($text, '\\'));

    // A function: __(), trans(), trans_choice().
    if (in_array($id, [T_STRING, T_NAME_FULLY_QUALIFIED], true) && $next === '('
        && ! in_array($previous, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_CONST], true)
        && in_array($name, ['__', 'trans', 'trans_choice'], true)) {
        [$arguments] = callArguments($tokens, $index + 1);

        return $name === 'trans' && $arguments === [] ? null : [$arguments, $name];
    }

    // The facade: Lang::get(), Lang::choice().
    if (in_array($id, [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true) && in_array($name, $facades, true)
        && ($tokens[$index + 1][0] ?? null) === T_DOUBLE_COLON
        && in_array(strtolower($tokens[$index + 2][1] ?? ''), ['get', 'choice'], true) && ($tokens[$index + 3][1] ?? null) === '(') {
        [$arguments] = callArguments($tokens, $index + 3);

        return [$arguments, strtolower($tokens[$index + 2][1])];
    }

    // The translator: app('translator')->get(), trans()->choice().
    if (in_array($id, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)
        && in_array(strtolower($tokens[$index + 1][1] ?? ''), ['get', 'choice'], true) && ($tokens[$index + 2][1] ?? null) === '('
        && isTranslatorReceiver($tokens, $index - 1)) {
        [$arguments] = callArguments($tokens, $index + 2);

        return [$arguments, strtolower($tokens[$index + 1][1])];
    }

    return null;
}

/**
 * Whether the call that ends at $close is app('translator') or trans().
 *
 * @param  list<array{int, string, int}>  $tokens
 */
function isTranslatorReceiver(array $tokens, int $close): bool
{
    if (($tokens[$close][1] ?? null) !== ')') {
        return false;
    }

    $open = openingParenthesis($tokens, $close);
    $name = strtolower(ltrim($tokens[$open - 1][1] ?? '', '\\'));
    [$arguments] = callArguments($tokens, $open);

    return match ($name) {
        'trans' => $arguments === [],
        'app', 'resolve' => count($arguments) === 1 && count($arguments[0]) === 1 && in_array($arguments[0][0][1], ["'translator'", '"translator"'], true),
        default => false,
    };
}

/**
 * The tokens of the key argument: the argument named `key`, or else the first
 * positional one.
 *
 * @param  list<list<array{int, string, int}>>  $arguments
 * @return list<array{int, string, int}>|null
 */
function keyArgument(array $arguments, bool $choice): ?array
{
    foreach ($arguments as $argument) {
        if (isNamed($argument) && strtolower($argument[0][1]) === 'key') {
            return array_slice($argument, 2);
        }
    }

    foreach ($arguments as $argument) {
        if (! isNamed($argument)) {
            return $argument;
        }
    }

    return null;
}

/**
 * @param  list<array{int, string, int}>  $argument
 */
function isNamed(array $argument): bool
{
    return count($argument) > 2 && $argument[0][0] === T_STRING && $argument[1][1] === ':';
}

/**
 * The key of a key argument, or null with its text when it is not a single
 * string literal without interpolation.
 *
 * @param  list<array{int, string, int}>  $argument
 * @return array{string|null, string}
 */
function constantKey(array $argument): array
{
    $written = implode('', array_map(fn (array $token): string => $token[1], $argument));

    if (count($argument) !== 1 || $argument[0][0] !== T_CONSTANT_ENCAPSED_STRING) {
        return [null, $written];
    }

    return [unquote($argument[0][1]), $written];
}

function unquote(string $literal): string
{
    $body = substr($literal, 1, -1);

    return $literal[0] === "'" ? str_replace(['\\\\', "\\'"], ['\\', "'"], $body) : stripcslashes($body);
}

/**
 * The arguments of the call whose opening parenthesis is at $open, each a
 * list of tokens, and the index of its closing parenthesis.
 *
 * @param  list<array{int, string, int}>  $tokens
 * @return array{list<list<array{int, string, int}>>, int}
 */
function callArguments(array $tokens, int $open): array
{
    $arguments = [];
    $current = [];
    $depth = 0;

    for ($at = $open; $at < count($tokens); $at++) {
        [$id, $text] = $tokens[$at];

        if (in_array($text, ['(', '[', '{'], true) || in_array($id, [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) {
            if ($depth++ > 0) {
                $current[] = $tokens[$at];
            }
        } elseif (in_array($text, [')', ']', '}'], true)) {
            if (--$depth === 0) {
                if ($current !== []) {
                    $arguments[] = $current;
                }

                return [$arguments, $at];
            }

            $current[] = $tokens[$at];
        } elseif ($text === ',' && $depth === 1) {
            $arguments[] = $current;
            $current = [];
        } else {
            $current[] = $tokens[$at];
        }
    }

    return [$arguments, count($tokens) - 1];
}

/**
 * @param  list<array{int, string, int}>  $tokens
 */
function openingParenthesis(array $tokens, int $close): int
{
    $depth = 0;

    for ($at = $close; $at >= 0; $at--) {
        if ($tokens[$at][1] === ')') {
            $depth++;
        } elseif ($tokens[$at][1] === '(' && --$depth === 0) {
            return $at;
        }
    }

    return 0;
}

/**
 * The literals of the sink that starts at this token, if one does.
 *
 * @param  list<array{int, string, int}>  $tokens
 * @return list<array{int, string}>
 */
function sinkLiterals(array $tokens, int $index): array
{
    [$id, $text] = $tokens[$index];
    $name = strtolower(ltrim($text, '\\'));
    $previous = $tokens[$index - 1] ?? [0, '', 0];
    $member = in_array($previous[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true);
    $static = $previous[0] === T_DOUBLE_COLON;
    $class = $static ? strtolower(basename(str_replace('\\', '/', ltrim($tokens[$index - 2][1] ?? '', '\\')))) : '';

    // A method declaration: messages() and attributes() of a FormRequest.
    if ($id === T_STRING && $previous[0] === T_FUNCTION && in_array($name, ['messages', 'attributes'], true)) {
        return literalsIn(methodBody($tokens, $index), false);
    }

    if (($tokens[$index + 1][1] ?? null) !== '(') {
        return [];
    }

    // The positions of the arguments that carry text: 'all', a list of
    // positions, or 'with', the arguments of with() and flash(): the values of
    // an array first argument and every later argument.
    $positions = match (true) {
        $id === T_VARIABLE && $text === '$fail' => [0],
        ! in_array($id, [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED], true) => null,
        $previous[0] === T_NEW => str_ends_with($name, 'costrule') ? [0, 1] : null,
        $member => match ($name) {
            'withmessages', 'witherrors' => 'all',
            'with', 'flash' => 'with',
            'add' => receiverName($tokens, $index - 2) === 'errors' ? [1] : null,
            'json' => receiverName($tokens, $index - 2) === 'response' ? [0] : null,
            'validate' => ($tokens[$index - 2][1] ?? null) === '$this' ? [2, 3] : [1, 2],
            default => null,
        },
        $static => match ([$class, $name]) {
            ['validationexception', 'withmessages'] => 'all',
            ['inertia', 'render'] => 'later',
            ['inertia', 'share'], ['session', 'flash'] => 'with',
            ['validator', 'make'], ['validator', 'validate'] => [2, 3],
            ['response', 'json'] => [0],
            default => null,
        },
        $previous[0] === T_FUNCTION => null,
        default => match ($name) {
            'abort' => [1],
            'abort_if', 'abort_unless' => [2],
            'inertia' => 'later',
            'validator' => [2, 3],
            default => null,
        },
    };

    if ($positions === null) {
        return [];
    }

    [$arguments] = callArguments($tokens, $index + 1);
    $literals = [];

    foreach ($arguments as $position => $argument) {
        $argument = isNamed($argument) ? array_slice($argument, 2) : $argument;
        $read = match (true) {
            $positions === 'all' => 'all',
            $positions === 'later' => $position >= 1 ? 'all' : null,
            $positions === 'with' => $position === 0 ? 'values' : 'all',
            default => in_array($position, $positions, true) ? 'all' : null,
        };

        if ($read !== null) {
            array_push($literals, ...literalsIn($argument, $read === 'values'));
        }
    }

    return $literals;
}

/**
 * The name of the call that ends at $close, such as `errors` for
 * `$validator->errors()`, or null.
 *
 * @param  list<array{int, string, int}>  $tokens
 */
function receiverName(array $tokens, int $close): ?string
{
    if (($tokens[$close][1] ?? null) !== ')') {
        return null;
    }

    $name = $tokens[openingParenthesis($tokens, $close) - 1][1] ?? null;

    return $name === null ? null : strtolower(ltrim($name, '\\'));
}

/**
 * The tokens of the body of the method whose name is at $name.
 *
 * @param  list<array{int, string, int}>  $tokens
 * @return list<array{int, string, int}>
 */
function methodBody(array $tokens, int $name): array
{
    for ($at = $name; $at < count($tokens); $at++) {
        if ($tokens[$at][1] === ';') {
            return [];
        }

        if ($tokens[$at][1] === '{') {
            $depth = 0;

            for ($end = $at; $end < count($tokens); $end++) {
                if (in_array($tokens[$end][1], ['{'], true) || in_array($tokens[$end][0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) {
                    $depth++;
                } elseif ($tokens[$end][1] === '}' && --$depth === 0) {
                    return array_slice($tokens, $at + 1, $end - $at - 1);
                }
            }

            return array_slice($tokens, $at + 1);
        }
    }

    return [];
}

/**
 * The literals with a letter that a sink receives as text, as [line, text]:
 * outside translation calls and the arguments of other calls, except the
 * functions that pass text on; never an array key, an array offset or the
 * operand of a comparison. With $valuesOnly, only array values count.
 *
 * @param  list<array{int, string, int}>  $tokens
 * @return list<array{int, string}>
 */
function literalsIn(array $tokens, bool $valuesOnly): array
{
    $literals = [];

    for ($at = 0; $at < count($tokens); $at++) {
        [$id, $text, $line] = $tokens[$at];
        $previous = $tokens[$at - 1] ?? [0, '', 0];
        $next = $tokens[$at + 1] ?? [0, '', 0];

        // A call: a translation is never a literal, nor are the arguments of
        // a call that does not pass text on.
        if (in_array($id, [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_VARIABLE], true) && $next[1] === '(') {
            $function = strtolower(ltrim($text, '\\'));
            $method = in_array($previous[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true);

            if ($method || $previous[0] === T_NEW || ! in_array($function, PASS_THROUGH, true)) {
                [, $close] = callArguments($tokens, $at + 1);
                $at = $close;
            }

            continue;
        }

        if (! in_array($id, [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true) || preg_match('/\p{L}/u', $text) !== 1) {
            continue;
        }

        $key = $next[0] === T_DOUBLE_ARROW;
        $offset = $previous[1] === '[' && in_array($tokens[$at - 2][1] ?? '', [']', ')', '}'], true) || ($previous[1] === '[' && in_array($tokens[$at - 2][0] ?? 0, [T_VARIABLE, T_STRING], true));
        $comparison = in_array($previous[0], [T_IS_IDENTICAL, T_IS_NOT_IDENTICAL, T_IS_EQUAL, T_IS_NOT_EQUAL], true)
            || in_array($next[0], [T_IS_IDENTICAL, T_IS_NOT_IDENTICAL, T_IS_EQUAL, T_IS_NOT_EQUAL], true);

        if ($key || $offset || $comparison || ($valuesOnly && $previous[0] !== T_DOUBLE_ARROW)) {
            continue;
        }

        $literals[] = [$line, trim($id === T_CONSTANT_ENCAPSED_STRING ? unquote($text) : $text)];
    }

    return $literals;
}

/**
 * The keys and literal text of a Blade view.
 *
 * @return array{list<array{int, string|null, string}>, list<array{int, string}>}
 */
function bladeFindings(string $code): array
{
    $keys = [];
    $literals = [];

    // The PHP of the view: echoes, raw echoes, PHP blocks and directives,
    // outside Blade comments.
    $code = (string) preg_replace_callback('/\{\{--.*?--\}\}/s', fn (array $match): string => (string) preg_replace('/[^\n]/', ' ', $match[0]), $code);

    foreach ([
        ['/\{!!(.*?)!!\}/s', 'echo'],
        ['/(?<!@)\{\{(.*?)\}\}/s', 'echo'],
        ['/@php\b(.*?)@endphp/s', 'php'],
        ['/<\?php(.*?)\?>/s', 'php'],
    ] as [$pattern, $kind]) {
        preg_match_all($pattern, $code, $fragments, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($fragments as $fragment) {
            $line = lineAt($code, $fragment[1][1]);
            [$found] = phpFindings('<?php '.$fragment[1][0].';', $line, false);
            array_push($keys, ...$found);

            if ($kind === 'echo') {
                array_push($literals, ...literalsIn(phpTokens('<?php '.$fragment[1][0].';', $line), false));
            }
        }
    }

    preg_match_all('/@([A-Za-z_]+)\s*(\((?:[^()\'"]++|\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"|(?2))*\))/', $code, $directives, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

    foreach ($directives as $directive) {
        $name = $directive[1][0];
        $line = lineAt($code, $directive[0][1]);
        $function = match ($name) {
            'lang' => '__',
            'choice' => 'trans_choice',
            default => 'directive',
        };
        $php = '<?php '.$function.$directive[2][0].';';
        [$found] = phpFindings($php, $line, false);
        array_push($keys, ...$found);

        $tokens = phpTokens($php, $line);
        [$arguments] = callArguments($tokens, 1);

        foreach ($arguments as $position => $argument) {
            $text = in_array($name, ['section', 'yield'], true) && $position === 1;
            array_push($literals, ...literalsIn($argument, ! $text));
        }
    }

    // The markup, with every piece of PHP blanked out.
    $masked = $code;

    foreach ([
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

    preg_match_all('/<[A-Za-z][^>]*>/s', $masked, $tags, PREG_OFFSET_CAPTURE);

    foreach ($tags[0] as [$tag, $offset]) {
        preg_match_all('/\s([^\s=\/>"\']+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/s', $tag, $attributes, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($attributes as $attribute) {
            $name = strtolower($attribute[1][0]);
            [$value, $at] = ($attribute[2][1] ?? -1) >= 0 ? $attribute[2] : $attribute[3];
            $line = lineAt($code, $offset + $at);

            if (str_starts_with($name, ':')) {
                // A bound component attribute is a PHP expression.
                $original = substr($code, $offset + $at, strlen($value));
                [$found] = phpFindings('<?php '.$original.';', $line, false);
                array_push($keys, ...$found);
                array_push($literals, ...literalsIn(phpTokens('<?php '.$original.';', $line), false));
            } elseif (! in_array($name, NON_TEXT_ATTRIBUTES, true) && ! isViewportContent($tag, $name) && preg_match('/\p{L}/u', $value) === 1) {
                $literals[] = [$line, trim($value)];
            }
        }
    }

    // Text between tags: what is left once every tag is blanked out.
    $text = (string) preg_replace_callback('/<[^>]*>/s', fn (array $match): string => (string) preg_replace('/[^\n]/', ' ', $match[0]), $masked);
    preg_match_all('/[^\s<>]*\p{L}[^<>\n]*/u', $text, $words, PREG_OFFSET_CAPTURE);

    foreach ($words[0] as [$word, $offset]) {
        $literals[] = [lineAt($code, $offset), trim($word)];
    }

    usort($literals, fn (array $a, array $b): int => $a[0] <=> $b[0]);

    return [$keys, $literals];
}

/**
 * The content of `<meta name="viewport">`, reviewed: it holds settings, not text.
 */
function isViewportContent(string $tag, string $attribute): bool
{
    return $attribute === 'content' && preg_match('/^<meta\b[^>]*\bname\s*=\s*["\']viewport["\']/i', $tag) === 1;
}

function lineAt(string $code, int $offset): int
{
    return substr_count(substr($code, 0, $offset), "\n") + 1;
}
