<?php

namespace App\Http\Requests\Catalog;

use App\Actions\Catalog\RenameCategory;

/**
 * The FormRequest of RC-22 `category.rename`: `categoryId` and `name`, against the
 * expected `categoryVersion`.
 */
final class RenameCategoryRequest extends CatalogCommandRequest
{
    protected const int NAME_MAX = 100;

    public function operation(): string
    {
        return RenameCategory::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return [...self::categoryTargetRules(), ...self::categoryNameRules()];
    }

    /**
     * @return array<string, mixed>
     */
    protected function expectedRules(): array
    {
        return ['categoryVersion' => ['required', 'integer', 'min:1']];
    }
}
