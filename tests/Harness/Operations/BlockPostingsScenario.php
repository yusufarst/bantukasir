<?php

namespace Tests\Harness\Operations;

use App\Actions\Operations\BlockPostings;
use Tests\Harness\Race\Scenario;

/**
 * The reconciliation's `posting_block.block` of one product for one run (INTERACTION_CONTRACTS section 7;
 * RACE-24; TASK-036), in a worker process: its outcome by name.
 */
final class BlockPostingsScenario implements Scenario
{
    /**
     * @param  array<string, mixed>  $arguments  run, product
     * @return array<string, mixed>
     */
    public function run(array $arguments): array
    {
        $run = $arguments['run'] ?? null;
        $product = $arguments['product'] ?? null;

        return ['outcome' => app(BlockPostings::class)(is_string($run) ? $run : '', is_int($product) ? $product : 0)->name];
    }
}
