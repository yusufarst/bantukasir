<?php

namespace App\Http\Requests\Catalog;

use App\Actions\Catalog\ReactivateCategory;

/**
 * The FormRequest of RC-24 `category.reactivate`.
 */
final class ReactivateCategoryRequest extends CategoryLifecycleRequest
{
    public function operation(): string
    {
        return ReactivateCategory::OPERATION;
    }
}
