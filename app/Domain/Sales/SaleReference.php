<?php

namespace App\Domain\Sales;

/**
 * What an answer of a committed Sale is rebuilt from (ADR-007 section 3): its internal key, its
 * number and its cashier, whose own Sale it is (CAP-21; BR-DOC-06).
 */
final readonly class SaleReference
{
    public function __construct(
        public int $id,
        public string $number,
        public int $actorUserId,
    ) {}
}
