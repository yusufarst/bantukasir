<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\DeactivateCategory;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Catalog\DeactivateCategoryRequest;

/**
 * RC-23 `category.deactivate` (ROUTE_CONTRACTS section 6; TASK-015 scope item 2).
 */
final class DeactivateCategoryController extends CommandController
{
    protected function request(): string
    {
        return DeactivateCategoryRequest::class;
    }

    protected function action(): string
    {
        return DeactivateCategory::class;
    }
}
