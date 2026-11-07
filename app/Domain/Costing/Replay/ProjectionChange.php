<?php

namespace App\Domain\Costing\Replay;

use App\Support\Numbers\Cost;

/**
 * An issue allocation's projection whose status or amount the replay changed (ADR-008 section
 * 4.3): only these are rewritten by the publication. A manual issue that a reversal cancelled
 * logically takes the status CANCELLED, with no amount: it reduces no value (ADR-008 section 2;
 * BR-COST-32).
 */
final readonly class ProjectionChange
{
    public function __construct(
        public int $allocationId,
        public Cost $amount,
        public bool $cancelled = false,
    ) {}

    /**
     * The projection of a manual issue that a reversal cancelled logically.
     */
    public static function cancelled(int $allocationId): self
    {
        return new self($allocationId, Cost::unknown(), true);
    }
}
