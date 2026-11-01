<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\UpdateProduct;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Catalog\UpdateProductRequest;

/**
 * RC-18 `product.update` (ROUTE_CONTRACTS section 6; TASK-015 scope item 2).
 */
final class UpdateProductController extends CommandController
{
    protected function request(): string
    {
        return UpdateProductRequest::class;
    }

    protected function action(): string
    {
        return UpdateProduct::class;
    }
}
