<?php

namespace App\Domain\Costing\Replay;

use App\Support\Numbers\Cost;

/**
 * What a replay publishes (ADR-008 section 4.3): the product's corrected value, KNOWN or UNKNOWN,
 * the issue projections whose status or amount it changed, in the order of the ledger, and the return
 * projections whose status or amount it changed, recomputed together with their issues (D-061;
 * TASK-033); and the number of logical movements it recomputed, within the movement cap.
 */
final readonly class ReplayResult
{
    /**
     * @param  list<ProjectionChange>  $changes  the issue projections
     * @param  list<ProjectionChange>  $returnChanges  the return projections
     */
    public function __construct(
        public Cost $value,
        public array $changes,
        public int $movements,
        public array $returnChanges = [],
    ) {}
}
