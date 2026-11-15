<?php

namespace App\Domain\Operations\Reconciliation;

/**
 * What one run of `reconciliation:run` recorded (SCHEMA section 5.12): its row, its outcome and counts,
 * the Sales and tokens whose differences it reported (G-03), and whether it pinged the heartbeat.
 */
final readonly class RunSummary
{
    /**
     * @param  list<int>  $differingSales
     * @param  list<int>  $differingTokens
     */
    public function __construct(
        public int $runId,
        public string $outcome,
        public int $productsChecked,
        public int $mismatches,
        public int $blocksWritten,
        public array $differingSales,
        public array $differingTokens,
        public bool $pinged,
    ) {}
}
