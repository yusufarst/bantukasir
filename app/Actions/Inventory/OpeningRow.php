<?php

namespace App\Actions\Inventory;

use App\Domain\Costing\AcquisitionCost;
use LogicException;

/**
 * A row of RC-11 as the Action reads it from a payload that its FormRequest validated
 * (INTERACTION_CONTRACTS RC-11): the product; the quantity as its canonical text, "0" for a zero
 * count, which is judged against the product's precision only under its lock (TR-VAL-08); the count
 * reference, mandatory for a positive row and optional for a zero row; and, for a positive row alone,
 * its cost, KNOWN or UNKNOWN (BR-INV-25, BR-INV-27).
 */
final readonly class OpeningRow
{
    private function __construct(
        public int $productId,
        public string $quantity,
        public ?string $countReference,
        public ?AcquisitionCost $cost,
    ) {}

    /**
     * The rows of the payload, in its order.
     *
     * @return list<self>
     */
    public static function allOf(mixed $rows): array
    {
        if (! is_array($rows)) {
            throw new LogicException('The rows of the payload were validated before the Action.');
        }

        $read = [];

        foreach ($rows as $row) {
            $values = is_array($row) ? $row : [];
            $product = $values['productId'] ?? null;
            $quantity = $values['quantity'] ?? null;
            $reference = $values['countReference'] ?? null;
            $cost = $values['cost'] ?? null;

            if (! is_int($product) || ! is_string($quantity) || ($reference !== null && ! is_string($reference))) {
                throw new LogicException('A row of the payload was validated before the Action.');
            }

            $read[] = new self($product, $quantity, $reference, is_array($cost) ? AcquisitionCost::fromPayload($cost) : null);
        }

        return $read;
    }
}
