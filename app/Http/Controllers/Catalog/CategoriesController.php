<?php

namespace App\Http\Controllers\Catalog;

use App\DTO\Catalog\CategoryRowData;
use App\Http\Requests\Catalog\CategoriesPageRequest;
use App\Queries\Catalog\CategoryRows;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-307, the categories with their versions (ROUTE_CONTRACTS section 2; COPY_DECK
 * section 8.8; TASK-017 scope item 1), by the filter `status`.
 */
final class CategoriesController
{
    public function index(CategoriesPageRequest $request, CategoryRows $categories): Response
    {
        $status = $request->status();

        return Inertia::render('catalog/Categories', [
            'categories' => array_map(static fn (CategoryRowData $row): array => $row->toArray(), $categories->list($status)),
            'filters' => ['status' => $status],
        ]);
    }
}
