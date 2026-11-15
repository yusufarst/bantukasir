<?php

namespace App\Domain\Operations\Reconciliation;

use RuntimeException;

/**
 * Ends the transaction of `posting_block.block` with nothing written, its identity included, when the
 * check under the product's guard finds no block to write (BlockOutcome).
 */
final class NoBlock extends RuntimeException
{
    public function __construct(public readonly BlockOutcome $outcome)
    {
        parent::__construct('No posting block is written.');
    }
}
