<?php

namespace App\Http\Requests\Catalog;

/**
 * The FormRequest base of RC-23 and RC-24 (INTERACTION_CONTRACTS RC-21 to RC-24):
 * `categoryId`, against the expected `categoryVersion`.
 */
abstract class CategoryLifecycleRequest extends CatalogCommandRequest
{
    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return self::categoryTargetRules();
    }

    /**
     * @return array<string, mixed>
     */
    protected function expectedRules(): array
    {
        return ['categoryVersion' => ['required', 'integer', 'min:1']];
    }
}
