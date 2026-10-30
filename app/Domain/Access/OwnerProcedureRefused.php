<?php

namespace App\Domain\Access;

use RuntimeException;

/**
 * A refusal of the Owner recovery or Owner replacement procedure
 * (SECURITY_MODEL SEC-OP-02, SEC-OP-03; TECHNICAL_RULES section 7.2): nothing
 * is written. Its text is for the operator, in English like the runbooks.
 */
final class OwnerProcedureRefused extends RuntimeException
{
    public const string NO_ACTIVE_OWNER = 'NO_ACTIVE_OWNER';

    public const string NOT_THE_ACTIVE_OWNER = 'NOT_THE_ACTIVE_OWNER';

    public const string EMAIL_HELD = 'EMAIL_HELD';

    public function __construct(public readonly string $reason)
    {
        parent::__construct(match ($reason) {
            self::NO_ACTIVE_OWNER => 'There is no active Owner account in this installation.',
            self::NOT_THE_ACTIVE_OWNER => 'The named account is not the active Owner account; a repeated replacement finds it deactivated.',
            self::EMAIL_HELD => 'The new Owner\'s email is held by an existing account. Use another email, or anonymize that account first.',
            default => 'The procedure was refused.',
        });
    }
}
