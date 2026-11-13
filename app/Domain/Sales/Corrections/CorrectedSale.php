<?php

namespace App\Domain\Sales\Corrections;

/**
 * The Sale that a correction names (SCHEMA section 5.9): its internal key and its number, a fact read
 * without a lock (SCH-31).
 */
final readonly class CorrectedSale
{
    public function __construct(
        public int $id,
        public string $number,
    ) {}
}
