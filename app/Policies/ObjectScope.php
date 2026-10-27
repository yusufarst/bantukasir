<?php

namespace App\Policies;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The object-scope pattern (SECURITY_MODEL SEC-AZ-03, SEC-AZ-06; ROUTE_CONTRACTS
 * RT-05; TECHNICAL_RULES TR-ERR-03; TASK-007). A Query or a FormRequest
 * reads an object through the actor's scope in the same query — the scope's
 * conditions and the identifier together — so that an object outside the
 * scope is not found, exactly like a missing one: one answer, NOT_FOUND, and
 * no read of the object before the scope is applied, so neither the answer
 * nor its cost tells whether it exists. An identifier that is not a positive
 * whole number of the key's range is not found either: a guessed or malformed
 * identifier is never a different answer. Knowing an identifier is never
 * permission (SEC-AZ-06).
 *
 * The scope itself is the module's: for example, a cashier's own Sales are
 * those whose actor or responsible user is the cashier (SEC-AZ-03), a
 * condition the Sales Query adds to the builder it passes here.
 */
final class ObjectScope
{
    /** The largest key of a bigint identity column (SCHEMA section 3.1). */
    private const string MAX_KEY = '9223372036854775807';

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $inScope  the actor's scope, already applied
     * @return TModel
     *
     * @throws NotFound
     */
    public static function find(Builder $inScope, mixed $identifier): Model
    {
        $key = self::key($identifier);

        if ($key === null) {
            throw new NotFound;
        }

        $found = $inScope->whereKey($key)->first();

        if ($found === null) {
            throw new NotFound;
        }

        return $found;
    }

    /**
     * The identifier as a key, or null when it cannot be one: only the
     * decimal digits of a positive number within the bigint range.
     */
    public static function key(mixed $identifier): ?int
    {
        if (is_int($identifier)) {
            return $identifier > 0 ? $identifier : null;
        }

        if (! is_string($identifier) || preg_match('/^[1-9][0-9]{0,18}$/D', $identifier) !== 1) {
            return null;
        }

        if (strlen($identifier) === strlen(self::MAX_KEY) && strcmp($identifier, self::MAX_KEY) > 0) {
            return null;
        }

        return (int) $identifier;
    }
}
