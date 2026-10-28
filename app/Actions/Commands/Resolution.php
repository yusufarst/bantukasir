<?php

namespace App\Actions\Commands;

/**
 * The answer of a status check (RC-50; INTERACTION_CONTRACTS section 2.1):
 * COMMITTED with the result under the checker's current authorization —
 * null when the checker may not see it — IN_PROGRESS, or NOT_COMMITTED.
 */
final readonly class Resolution
{
    public const string COMMITTED = 'COMMITTED';

    public const string IN_PROGRESS = 'IN_PROGRESS';

    public const string NOT_COMMITTED = 'NOT_COMMITTED';

    /**
     * @param  array{type: string, id: int|null, number?: string, location: string}|null  $result
     */
    public function __construct(
        public string $outcome,
        public string $operation,
        public ?int $commandId = null,
        public ?array $result = null,
    ) {}
}
