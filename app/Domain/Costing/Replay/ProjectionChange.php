<?php

namespace App\Domain\Costing\Replay;

use App\Support\Numbers\Cost;

/**
 * An issue allocation's projection whose status or amount the replay changed (ADR-008 section
 * 4.3): only these are rewritten by the publication.
 */
final readonly class ProjectionChange
{
    public function __construct(
        public int $allocationId,
        public Cost $amount,
    ) {}
}
