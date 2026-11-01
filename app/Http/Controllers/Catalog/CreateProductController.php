<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\CreateProduct;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Catalog\CreateProductRequest;

/**
 * RC-17 `product.create` (ROUTE_CONTRACTS section 6; TASK-015 scope item 2).
 */
final class CreateProductController extends CommandController
{
    protected function request(): string
    {
        return CreateProductRequest::class;
    }

    protected function action(): string
    {
        return CreateProduct::class;
    }
}
