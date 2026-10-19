<?php

namespace App\Support\Validation;

use Illuminate\Http\Request;

/**
 * The input paths of the current request whose values the global trimming
 * changed, as App\Http\Middleware\TrimStrings records them (TASK-003 finding
 * A-01, reading R-26). MoneyRule, QuantityRule and CostRule refuse a value at
 * such a path with their own message: a padded number such as " 75000" is not
 * canonical, and trimming it into "75000" would repair it by guessing
 * (ADR-006 item 2). A path is the dot path that the validator gives a rule,
 * for example `lines.1.qty`. Outside a request, nothing is recorded.
 */
final class TrimmedInput
{
    private const string ATTRIBUTE = 'bantukasir.trimmed_input';

    /**
     * @param  list<string>  $paths
     */
    public static function record(Request $request, array $paths): void
    {
        $request->attributes->set(self::ATTRIBUTE, $paths);
    }

    /**
     * Whether the value at this validated path reached the application with
     * padding that the global trimming removed.
     */
    public static function wasTrimmed(string $attribute): bool
    {
        $paths = request()->attributes->get(self::ATTRIBUTE);

        return is_array($paths) && in_array($attribute, $paths, true);
    }
}
