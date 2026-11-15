<?php

namespace App\Domain\Inventory\Onboarding;

/**
 * An opening line as RC-48 reads it (SCHEMA section 5.7, `opening_stock_lines`; D-087; G-24): its
 * product and its OPENING, both facts, read without a lock to choose the product's guard (SCH-31); and,
 * read again under that guard, whether a reversal cancelled its OPENING and whether a later line
 * replaces it, since every writer of either holds the same guard.
 */
final readonly class OpeningLine
{
    public function __construct(
        public int $id,
        public int $productId,
        public int $movementId,
        public bool $reversed,
        public bool $replaced,
    ) {}
}
