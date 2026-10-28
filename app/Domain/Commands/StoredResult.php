<?php

namespace App\Domain\Commands;

/**
 * The durable result of a committed command (SCHEMA section 5.3,
 * `command_results`): references only — the type and internal key of the
 * primary record and, in the detail, other internal keys and document
 * numbers; never cost or personal data. The answer is rebuilt from it under
 * the viewer's current authorization (ADR-007 section 3, SEC-AZ-06).
 */
final readonly class StoredResult
{
    /**
     * @param  array<string, string|int|null>  $detail
     */
    public function __construct(
        public string $type,
        public ?int $ref,
        public array $detail = [],
    ) {}
}
