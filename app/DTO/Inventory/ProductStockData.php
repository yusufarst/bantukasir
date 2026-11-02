<?php

namespace App\DTO\Inventory;

use App\Domain\Inventory\StockState;
use App\DTO\PageData;

/**
 * The stock of a product in a result of RD-01, as the role may see it (INTERACTION_CONTRACTS
 * section 3.1; CAP-14; BR-INV-43): the Owner and OPERATIONS_ADMIN, the combined account among
 * them, the quantity on hand, the minimum and the attention state, which only an active product
 * has (INVENTORY section 10); a cashier, at checkout, the quantity on hand alone. Never a cost.
 */
final readonly class ProductStockData implements PageData
{
    private function __construct(
        public string $onHand,
        public bool $full,
        public ?string $minimum,
        public ?StockState $state,
    ) {}

    public static function withAttention(string $onHand, string $minimum, ?StockState $state): self
    {
        return new self($onHand, true, $minimum, $state);
    }

    public static function atCheckout(string $onHand): self
    {
        return new self($onHand, false, null, null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if (! $this->full) {
            return ['onHand' => $this->onHand];
        }

        return ['onHand' => $this->onHand, 'minimum' => $this->minimum, 'state' => $this->state?->value];
    }
}
