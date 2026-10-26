<?php

namespace App\Domain\Audit;

use InvalidArgumentException;

/**
 * The actor of a security log entry (SCHEMA sections 3.4 and 5.4): a user by
 * internal identity; nobody yet signed in (ANONYMOUS); or a named operator
 * procedure or scheduled command of Actor's allowlist.
 */
final readonly class SecurityActor
{
    private function __construct(
        public string $kind,
        public ?int $userId,
        public ?string $process,
    ) {}

    public static function user(int $userId): self
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('A user actor has an internal identity (SCHEMA section 3.4).');
        }

        return new self('USER', $userId, null);
    }

    public static function anonymous(): self
    {
        return new self('ANONYMOUS', null, null);
    }

    public static function operatorProcedure(string $process): self
    {
        return new self('OPERATOR_PROCEDURE', null, Actor::operatorProcedure($process)->process);
    }

    public static function scheduledCommand(string $process): self
    {
        return new self('SCHEDULED_COMMAND', null, Actor::scheduledCommand($process)->process);
    }
}
