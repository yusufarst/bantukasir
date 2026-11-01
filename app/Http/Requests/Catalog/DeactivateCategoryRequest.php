<?php

namespace App\Http\Requests\Catalog;

use App\Actions\Catalog\DeactivateCategory;

/**
 * The FormRequest of RC-23 `category.deactivate`.
 */
final class DeactivateCategoryRequest extends CategoryLifecycleRequest
{
    public function operation(): string
    {
        return DeactivateCategory::OPERATION;
    }
}
