<?php

namespace App\Actions\Inventory;

use App\Domain\Costing\AcquisitionCost;
use LogicException;

/**
 * A line of RC-09 as the Action reads it from a payload that its FormRequest validated
 * (INTERACTION_CONTRACTS RC-09): the product; the direction, an increase or a decrease; the
 * quantity as its canonical positive text, which is judged against the product's precision only
 * under its lock (TR-VAL-08); and, for an increase alone, the cost, KNOWN or UNKNOWN (BR-INV-32).
 * A decrease carries no cost: its allocation is computed at commit (ADR-008 section 2).
 */
final readonly class AdjustmentLine
{
    public const string INCREASE = 'INCREASE';

    public const string DECREASE = 'DECREASE';

    private function __construct(
        public int $productId,
        public bool $increase,
        public string $quantity,
        public ?AcquisitionCost $cost,
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
            $direction = $values['direction'] ?? null;
            $quantity = $values['quantity'] ?? null;
            $cost = $values['cost'] ?? null;

            if (! is_int($product) || ! is_string($quantity) || ($direction !== self::INCREASE && $direction !== self::DECREASE)) {
                throw new LogicException('A line of the payload was validated before the Action.');
            }

            $increase = $direction === self::INCREASE;

            if ($increase !== is_array($cost)) {
                throw new LogicException('An increase, and only an increase, carries its cost (BR-INV-32).');
            }

            $read[] = new self($product, $increase, $quantity, is_array($cost) ? AcquisitionCost::fromPayload($cost) : null);
        }

        return $read;
    }
}
