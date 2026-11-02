<?php

namespace App\DTO\Catalog;

use App\DTO\PageData;

/**
 * The answer of RD-05 (INTERACTION_CONTRACTS section 3.5; D-051, D-052): the token as read; its
 * kind, null for a token that was never mapped; the product it is active on; its retirement
 * records, the newest first; and, when it is correctable, the mapping that RC-30 expects.
 */
final readonly class TokenMappingData implements PageData
{
    /**
     * @param  array{id: int, name: string}|null  $activeProduct
     * @param  list<array{productId: int, productName: string, reasonClass: string, retiredAt: string, actorDisplayName: string}>  $retirements
     * @param  array{productId: int, productName: string, state: string}|null  $mapping
     */
    public function __construct(
        public string $token,
        public ?string $kind,
        public ?array $activeProduct,
        public array $retirements,
        public ?array $mapping,
    ) {}

    /**
     * @return array{token: string, kind: string|null, activeProduct: array{id: int, name: string}|null, retirements: list<array{productId: int, productName: string, reasonClass: string, retiredAt: string, actorDisplayName: string}>, mapping: array{productId: int, productName: string, state: string}|null, correctable: bool}
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'kind' => $this->kind,
            'activeProduct' => $this->activeProduct,
            'retirements' => $this->retirements,
            'mapping' => $this->mapping,
            'correctable' => $this->mapping !== null,
        ];
    }
}
