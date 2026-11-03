<?php

namespace App\Domain\Costing\Replay;

use App\Support\Numbers\Cost;

/**
 * What a replay publishes (ADR-008 section 4.3): the product's corrected value, KNOWN or UNKNOWN,
 * and the projections whose status or amount it changed, in the order of the ledger; and the number
 * of logical movements it recomputed, within the movement cap.
 */
final readonly class ReplayResult
{
    /**
     * @param  list<ProjectionChange>  $changes
     */
    public function __construct(
        public Cost $value,
        public array $changes,
        public int $movements,
    ) {}
}
