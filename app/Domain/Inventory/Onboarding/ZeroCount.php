<?php

namespace App\Domain\Inventory\Onboarding;

/**
 * A zero count as RC-14 reads it (SCHEMA section 5.7, `onboarding_zero_counts`; BR-INV-27; D-087): its
 * product, a fact read without a lock to choose the product's guard (SCH-31), and, read again under
 * that guard, whether it is withdrawn, which a withdrawal of it changes only under the same guard.
 */
final readonly class ZeroCount
{
    public function __construct(
        public int $id,
        public int $productId,
        public bool $withdrawn,
    ) {}
}
