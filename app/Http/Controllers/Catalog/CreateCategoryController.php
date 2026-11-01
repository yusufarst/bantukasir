<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\CreateCategory;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Catalog\CreateCategoryRequest;

/**
 * RC-21 `category.create` (ROUTE_CONTRACTS section 6; TASK-015 scope item 2).
 */
final class CreateCategoryController extends CommandController
{
    protected function request(): string
    {
        return CreateCategoryRequest::class;
    }

    protected function action(): string
    {
        return CreateCategory::class;
    }
}
