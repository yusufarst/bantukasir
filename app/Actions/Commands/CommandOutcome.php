<?php

namespace App\Actions\Commands;

/**
 * A committed command as its answer gives it (INTERACTION_CONTRACTS section
 * 1.2): committed now, or the original result of a repeated identity, with
 * the result rebuilt from the stored references under the current
 * authorization (ADR-007 section 3). A one-time link and the account whose
 * sign-in sessions the command ended belong to the answer committed now only:
 * neither is stored, so a repeat or a status check never has them
 * (SECURITY_MODEL SEC-ACC-05; TASK-011).
 */
final readonly class CommandOutcome
{
    /**
     * @param  array{type: string, id: int|null, number?: string, location: string}|null  $result  null when the viewer may not see it
     * @param  array{url: string, expiresAt: string}|null  $link  the link handed over once, in this answer only
     * @param  int|null  $sessionsEndedOf  the account whose sign-in sessions this commit ended, for the security log
     */
    public function __construct(
        public string $operation,
        public int $commandId,
        public bool $repeated,
        public ?array $result,
        public ?array $link = null,
        public ?int $sessionsEndedOf = null,
    ) {}
}
