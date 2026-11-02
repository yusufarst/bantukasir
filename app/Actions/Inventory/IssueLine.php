<?php

namespace App\Actions\Inventory;

use LogicException;

/**
 * A line of RC-08 as the Action reads it from a payload that its FormRequest validated
 * (INTERACTION_CONTRACTS RC-08): the product and the quantity as its canonical text, which is
 * judged against the product's precision only under its lock (TR-VAL-08). A line carries no cost:
 * its allocation is computed at commit (ADR-008 section 2).
 */
final readonly class IssueLine
{
    private function __construct(
        public int $productId,
        public string $quantity,
    ) {}

    /**
     * The lines of the payload, in its order.
     *
     * @return list<self>
     */
    public static function allOf(mixed $lines): array
    {
        if (! is_array($lines)) {
            throw new LogicException('The lines of the payload were validated before the Action.');
        }

        $read = [];

        foreach ($lines as $line) {
            $values = is_array($line) ? $line : [];
            $product = $values['productId'] ?? null;
            $quantity = $values['quantity'] ?? null;

            if (! is_int($product) || ! is_string($quantity)) {
                throw new LogicException('A line of the payload was validated before the Action.');
            }

            $read[] = new self($product, $quantity);
        }

        return $read;
    }
}
