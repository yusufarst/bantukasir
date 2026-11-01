<?php

namespace App\Http\Requests\Catalog;

use App\Actions\Catalog\CreateCategory;

/**
 * The FormRequest of RC-21 `category.create`: `name`, 1 to 100 characters.
 */
final class CreateCategoryRequest extends CatalogCommandRequest
{
    protected const int NAME_MAX = 100;

    public function operation(): string
    {
        return CreateCategory::OPERATION;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return self::categoryNameRules();
    }
}
