<?php

namespace App\Domain\Catalog;

use App\Support\Numbers\Money;
use App\Support\Numbers\Quantity;
use LogicException;

/**
 * The fields of a product that a user enters, as RC-17 and RC-18 carry them
 * (INTERACTION_CONTRACTS RC-17, RC-18), read from a payload that its FormRequest
 * validated: every text trimmed, the amounts exact, an absent brand null.
 */
final readonly class ProductFields
{
    public function __construct(
        public string $name,
        public int $categoryId,
        public ?string $brand,
        public string $baseUnit,
        public int $quantityPrecision,
        public Money $sellingPrice,
        public Quantity $minimumStock,
    ) {}

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $precision = self::whole($payload['quantityPrecision'] ?? null);
        $brand = $payload['brand'] ?? null;

        return new self(
            self::text($payload['name'] ?? null),
            self::whole($payload['categoryId'] ?? null),
            is_string($brand) ? $brand : null,
            self::text($payload['baseUnit'] ?? null),
            $precision,
            Money::of(self::text($payload['sellingPrice'] ?? null))->assertStored(),
            Quantity::of(self::text($payload['minimumStock'] ?? null), $precision)->assertStock(),
        );
    }

    /**
     * The values of the named fields as entered, for the audit (BR-AUD-02). The
     * selling price is PRICE_CHANGE's.
     *
     * @param  list<string>  $names
     * @return array<string, string|int|null>
     */
    public function auditValues(array $names): array
    {
        return array_intersect_key([
            'name' => $this->name,
            'categoryId' => $this->categoryId,
            'brand' => $this->brand,
            'baseUnit' => $this->baseUnit,
            'quantityPrecision' => $this->quantityPrecision,
            'minimumStock' => $this->minimumStock->toCanonical(),
        ], array_flip($names));
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : throw new LogicException('A text of the payload was validated before the Action.');
    }

    private static function whole(mixed $value): int
    {
        return is_int($value) ? $value : throw new LogicException('A whole number of the payload was validated before the Action.');
    }
}
