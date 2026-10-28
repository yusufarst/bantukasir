<?php

namespace App\Actions\Commands;

/**
 * A committed command as its answer gives it (INTERACTION_CONTRACTS section
 * 1.2): committed now, or the original result of a repeated identity, with
 * the result rebuilt from the stored references under the current
 * authorization (ADR-007 section 3).
 */
final readonly class CommandOutcome
{
    /**
     * @param  array{type: string, id: int|null, number?: string, location: string}|null  $result  null when the viewer may not see it
     */
    public function __construct(
        public string $operation,
        public int $commandId,
        public bool $repeated,
        public ?array $result,
    ) {}
}
