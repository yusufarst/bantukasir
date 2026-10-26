<?php

namespace App\Domain\Audit;

use InvalidArgumentException;

/**
 * Who acted, as every record that names an actor stores it (SCHEMA sections
 * 3.4 and 3.5; DOMAIN_MODEL section 4): a user, with the display name copied
 * from the row the Action has locked, in the same transaction (guard G-16),
 * or a named operator procedure or scheduled command of the allowlist below,
 * whose display name is its name. A one-time link consumption is the user who
 * consumed it (SECURITY_MODEL section 14).
 */
final readonly class Actor
{
    /**
     * The operator procedures of SECURITY_MODEL SEC-OP-01 to SEC-OP-08 and
     * the scheduled commands, the allowlist of SCHEMA section 3.4.
     */
    public const array OPERATOR_PROCEDURES = [
        'bootstrap', 'owner_recovery', 'owner_replacement', 'after_restore', 'restore_reconciliation',
        'cost_recovery', 'projection_rebuild', 'restore_drill_record',
    ];

    public const array SCHEDULED_COMMANDS = ['reconciliation', 'housekeeping'];

    private function __construct(
        public string $kind,
        public ?int $userId,
        public ?string $process,
        public string $displayName,
    ) {}

    /**
     * A user, with the identity and display name of the account row the
     * caller has locked in its transaction. The Audit module depends on no
     * other module (STACK_AND_MODULES section 2.1), so the Access module
     * passes the two values, never its model.
     */
    public static function user(int $userId, string $displayName): self
    {
        if ($userId < 1 || $displayName === '') {
            throw new InvalidArgumentException('A user actor has an internal identity and a display name (SCHEMA sections 3.4 and 3.5).');
        }

        return new self('USER', $userId, null, $displayName);
    }

    public static function operatorProcedure(string $process): self
    {
        if (! in_array($process, self::OPERATOR_PROCEDURES, true)) {
            throw new InvalidArgumentException(sprintf('%s is not an operator procedure of SCHEMA section 3.4.', $process));
        }

        return new self('OPERATOR_PROCEDURE', null, $process, $process);
    }

    public static function scheduledCommand(string $process): self
    {
        if (! in_array($process, self::SCHEDULED_COMMANDS, true)) {
            throw new InvalidArgumentException(sprintf('%s is not a scheduled command of SCHEMA section 3.4.', $process));
        }

        return new self('SCHEDULED_COMMAND', null, $process, $process);
    }
}
