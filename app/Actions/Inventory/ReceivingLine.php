<?php

namespace App\Actions\Inventory;

use App\Domain\Costing\AcquisitionCost;
use LogicException;

/**
 * A line of RC-07 as the Action reads it from a payload that its FormRequest validated
 * (INTERACTION_CONTRACTS RC-07): the product, the quantity as its canonical text, which is
 * judged against the product's precision only under its lock (TR-VAL-08), and the line's cost.
 */
final readonly class ReceivingLine
{
    private function __construct(
        public int $productId,
        public string $quantity,
        public AcquisitionCost $cost,
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
            $cost = $values['cost'] ?? null;

            if (! is_int($product) || ! is_string($quantity) || ! is_array($cost)) {
                throw new LogicException('A line of the payload was validated before the Action.');
            }

            $read[] = new self($product, $quantity, AcquisitionCost::fromPayload($cost));
        }

        return $read;
    }
}
