<?php

namespace App\Domain\Operations\Reconciliation;

use App\Domain\Costing\Replay\ProjectionChange;
use App\Support\Numbers\Cost;
use App\Support\Numbers\Quantity;

/**
 * What the immutable facts of one product give for its projections (SCHEMA guard G-03; ADR-008
 * sections 4.1 and 5): its stock on hand, the sum of its movements, and its last place; its
 * valuation, replayed from its first movement by the application's own arithmetic; and the issue
 * and return projections whose stored status or amount differ from that replay, with the manual
 * issues that a reversal cancelled but whose projection is not CANCELLED. A product with no
 * movement has no balance and no valuation.
 */
final readonly class ProductRecomputation
{
    /**
     * @param  list<ProjectionChange>  $issueChanges
     * @param  list<ProjectionChange>  $returnChanges
     */
    public function __construct(
        public int $productId,
        public int $movements,
        public ?Quantity $onHand,
        public ?int $lastSequence,
        public ?Cost $value,
        public array $issueChanges,
        public array $returnChanges,
    ) {}

    public static function none(int $productId): self
    {
        return new self($productId, 0, null, null, null, [], []);
    }
}
