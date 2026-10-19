<?php

namespace Tests;

use LogicException;

/**
 * Thrown in tests when the translator is asked for a key that lang/id lacks
 * and would print the key instead of a text (ADR-005 item 5).
 */
final class MissingTranslationKey extends LogicException
{
    public function __construct(public readonly string $key)
    {
        parent::__construct(sprintf('The translation key %s is missing from lang/id.', $key));
    }

    /**
     * Laravel's validator (13.37, Validation\Concerns\FormatsMessages) probes
     * four optional groups of the translation files and falls back silently
     * when they are absent: the attribute names, the custom messages, one
     * custom message and a displayable value. Such a probe never prints its
     * key, so it is not a missing text. Every other lookup is, including the
     * default message `validation.<rule>` of a built-in rule, which would be
     * printed.
     */
    public static function isFrameworkProbe(string $key): bool
    {
        return in_array($key, ['validation.attributes', 'validation.custom'], true)
            || str_starts_with($key, 'validation.custom.')
            || str_starts_with($key, 'validation.values.');
    }
}
