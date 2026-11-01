<?php

namespace App\Http\Requests\Catalog;

/**
 * The FormRequest base of RC-19 and RC-20 (INTERACTION_CONTRACTS RC-19, RC-20):
 * `productId`, against the expected `productVersion`.
 */
abstract class ProductLifecycleRequest extends CatalogCommandRequest
{
    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return self::productTargetRules();
    }

    /**
     * @return array<string, mixed>
     */
    protected function expectedRules(): array
    {
        return ['productVersion' => ['required', 'integer', 'min:1']];
    }
}
