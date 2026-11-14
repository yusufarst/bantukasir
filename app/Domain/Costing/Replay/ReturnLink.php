<?php

namespace App\Domain\Costing\Replay;

use App\Support\Numbers\Cost;
use App\Support\Numbers\Quantity;

/**
 * The link of a RETURN_RECEIPT to its SALE_ISSUE allocation as the replay reads it (ADR-008 sections 3,
 * 4.3 and 5; D-061): the issue allocation's key, the quantity it issued and its current projection — what
 * the return's cost follows when the issue lies before the replay's start position and keeps it — and
 * R(k−1), the quantity that the earlier saleable returns linked to the same issue took back, in the order
 * of the ledger. Returned quantities are facts, so R(k−1) is read, never recomputed.
 */
final readonly class ReturnLink
{
    public function __construct(
        public int $issueAllocationId,
        public Quantity $issued,
        public Cost $issueProjection,
        public Quantity $returnedBefore,
    ) {}
}
