<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\ReactivateCategory;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Catalog\ReactivateCategoryRequest;

/**
 * RC-24 `category.reactivate` (ROUTE_CONTRACTS section 6; TASK-015 scope item 2).
 */
final class ReactivateCategoryController extends CommandController
{
    protected function request(): string
    {
        return ReactivateCategoryRequest::class;
    }

    protected function action(): string
    {
        return ReactivateCategory::class;
    }
}
