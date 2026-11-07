<?php

namespace App\Domain\Costing;

use App\Support\Numbers\Cost;

/**
 * A cost correction as it was committed (SCHEMA section 5.8, `cost_corrections`): its internal key,
 * its number among the corrections of its evidence and the new value it recorded, always KNOWN,
 * with a zero's evidence or reason (BR-COST-04).
 */
final readonly class StoredCorrection
{
    public function __construct(
        public int $id,
        public int $correctionNo,
        public Cost $newAmount,
        public ?string $zeroNote,
    ) {}
}
