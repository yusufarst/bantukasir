<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\RenameCategory;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Catalog\RenameCategoryRequest;

/**
 * RC-22 `category.rename` (ROUTE_CONTRACTS section 6; TASK-015 scope item 2).
 */
final class RenameCategoryController extends CommandController
{
    protected function request(): string
    {
        return RenameCategoryRequest::class;
    }

    protected function action(): string
    {
        return RenameCategory::class;
    }
}
