<?php

namespace App\Domain\Costing;

use App\Support\Numbers\Cost;

/**
 * One cost source as it was posted (SCHEMA section 5.8, `acquisition_cost_evidence`; BR-COST-02):
 * the acquisition cost evidence of an OPENING, a RECEIPT or a positive ADJUSTMENT, with its product,
 * its movement and that movement's place in the product's sequence, the replay's start position for
 * a correction of it (ADR-008 section 4.3), and its original value, KNOWN or UNKNOWN, with a zero's
 * evidence or reason. A fact: it never changes, so a command may read it without a lock (SCH-31).
 */
final readonly class CostSource
{
    public function __construct(
        public int $evidenceId,
        public int $productId,
        public int $movementId,
        public int $productSequence,
        public string $movementType,
        public Cost $original,
        public ?string $originalZeroNote,
    ) {}
}
