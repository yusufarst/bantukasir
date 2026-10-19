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
     * The three English texts that Laravel's ValidationException::summarize()
     * looks up for the summary message of a validation response with several
     * errors (13.37). TASK-003 lets them through in tests (finding A-09 (a),
     * reading R-29); the production error body, which never carries that
     * summary, is TASK-008's (OPEN_ITEMS T-26).
     */
    private const array VALIDATION_SUMMARY = [
        '(and :count more error)',
        '(and :count more errors)',
        'The given data was invalid.',
    ];

    /**
     * Laravel's validator (13.37, Validation\Concerns\FormatsMessages) probes
     * four optional groups of the translation files and falls back silently
     * when they are absent: the attribute names, the custom messages, one
     * custom message and a displayable value. Such a probe never prints its
     * key, so it is not a missing text. The summary of a validation exception
     * is let through too (VALIDATION_SUMMARY). Every other lookup is a missing
     * text, including the default message `validation.<rule>` of a built-in
     * rule, which would be printed.
     */
    public static function isFrameworkProbe(string $key): bool
    {
        return in_array($key, ['validation.attributes', 'validation.custom', ...self::VALIDATION_SUMMARY], true)
            || str_starts_with($key, 'validation.custom.')
            || str_starts_with($key, 'validation.values.');
    }
}
