<?php

namespace App\Domain\Operations\Reconciliation;

/**
 * What `posting_block.block` did for one product of a run (INTERACTION_CONTRACTS section 7; RACE-24):
 * it wrote the block; the product was already blocked, by an earlier run or by a repeat of this one,
 * so no second block was written; or, under the product's guard, its facts and projections agreed
 * after all — a posting that took the guard first is seen by this comparison — and nothing was written.
 */
enum BlockOutcome
{
    case Written;
    case AlreadyBlocked;
    case Agrees;
}
