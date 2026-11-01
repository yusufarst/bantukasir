<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\ReactivateProduct;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Catalog\ReactivateProductRequest;

/**
 * RC-20 `product.reactivate` (ROUTE_CONTRACTS section 6; TASK-015 scope item 2).
 */
final class ReactivateProductController extends CommandController
{
    protected function request(): string
    {
        return ReactivateProductRequest::class;
    }

    protected function action(): string
    {
        return ReactivateProduct::class;
    }
}
