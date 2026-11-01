<?php

namespace App\Domain\Catalog\Barcodes;

/**
 * A row of barcode_mappings with its token's text: a fact that is never changed
 * (SCHEMA section 5.6).
 */
final readonly class MappingRecord
{
    public function __construct(
        public int $id,
        public int $tokenId,
        public string $token,
        public int $productId,
    ) {}
}
