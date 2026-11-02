<?php

namespace App\Domain\Catalog\Barcodes;

/**
 * The last mapping of a token, with the facts that decide a misassignment correction
 * (INTERACTION_CONTRACTS RC-30, section 3.5; BR-CAT-19, BR-CAT-50, BR-CAT-51; D-051, D-052).
 * A token's mappings form one chain, the original and then each correction of the one before
 * it, so only the last says what the token is now: an earlier retirement was already
 * corrected, and each is corrected once (SCHEMA section 5.6).
 */
final readonly class CurrentMapping
{
    /** The states that RD-05 shows and RC-30 expects. */
    public const string ACTIVE = 'ACTIVE';

    public const string RETIRED_AS_MISASSIGNMENT = 'RETIRED_MISASSIGNMENT';

    public function __construct(
        public int $tokenId,
        public string $kind,
        public int $mappingId,
        public int $productId,
        public bool $active,
        public ?string $reasonClass,
    ) {}

    /**
     * The state of a token that can be corrected, or null: a manufacturer token that is
     * active, or retired as a misassignment. A code `BK-P-…` and a token retired for another
     * reason never can be (BR-CAT-19).
     *
     * @return self::ACTIVE|self::RETIRED_AS_MISASSIGNMENT|null
     */
    public function correctableState(): ?string
    {
        if ($this->kind !== Barcodes::MANUFACTURER) {
            return null;
        }

        if ($this->active) {
            return self::ACTIVE;
        }

        return $this->reasonClass === Barcodes::MISASSIGNMENT ? self::RETIRED_AS_MISASSIGNMENT : null;
    }

    /**
     * Whether RC-30 may attach the token to this product: a correction moves the token to a
     * different product, also when it was retired, since going back to the product it left
     * would reuse a retired token and correct nothing (G-09; BR-CAT-19, BR-CAT-51).
     */
    public function correctableTo(int $productId): bool
    {
        return $this->correctableState() !== null && $this->productId !== $productId;
    }
}
