<?php

namespace App\Domain\Access;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The password profile AICWDF-COMPAT-8 (ADR-004 item 6; SECURITY_MODEL
 * SEC-AUTH-05; D-049), applied to every path that sets a password — the
 * one-time link (RA-06), the own password change (RA-05) and, later, the
 * operator procedures: at least 8 and at most 128 characters (TECHNICAL_RULES
 * section 11.3), with an uppercase letter, a lowercase letter, a number and a
 * symbol. Characters are those of Laravel's Password rule: letters by their
 * Unicode case, numbers \p{N}, symbols \p{Z}, \p{S} or \p{P}. Paste and
 * password managers are allowed, no rotation is forced and no external breach
 * service is called. It is an Owner-selected usability profile, not a claim
 * of NIST compliance. A password is never trimmed or altered (TR-VAL-05).
 */
final class PasswordProfile implements ValidationRule
{
    public const int MIN = 8;

    public const int MAX = 128;

    public static function satisfiedBy(#[\SensitiveParameter] mixed $value): bool
    {
        if (! is_string($value) || preg_match('//u', $value) !== 1) {
            return false;
        }

        $length = mb_strlen($value, 'UTF-8');

        return $length >= self::MIN
            && $length <= self::MAX
            && preg_match('/\p{Lu}/u', $value) === 1
            && preg_match('/\p{Ll}/u', $value) === 1
            && preg_match('/\p{N}/u', $value) === 1
            && preg_match('/\p{Z}|\p{S}|\p{P}/u', $value) === 1;
    }

    public function validate(string $attribute, #[\SensitiveParameter] mixed $value, Closure $fail): void
    {
        if (is_string($value) && preg_match('//u', $value) === 1 && mb_strlen($value, 'UTF-8') > self::MAX) {
            $fail(__('ux.validation.password_max'));
        } elseif (! self::satisfiedBy($value)) {
            $fail(__('ux.validation.password'));
        }
    }
}
