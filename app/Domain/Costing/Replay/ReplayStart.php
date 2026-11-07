<?php

namespace App\Domain\Costing\Replay;

use App\Support\Numbers\Cost;
use App\Support\Numbers\Quantity;

/**
 * The state of a product's logical ledger before a replay's start position (ADR-008 section 4.3):
 * the logical quantity Q and the value V, KNOWN or UNKNOWN, of the movements since the last point
 * before the start at which Q became zero, or since the product's first movement.
 */
final readonly class ReplayStart
{
    public function __construct(
        public Quantity $quantity,
        public Cost $value,
    ) {}
}
