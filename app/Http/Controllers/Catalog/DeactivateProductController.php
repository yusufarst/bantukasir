<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\DeactivateProduct;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Catalog\DeactivateProductRequest;

/**
 * RC-19 `product.deactivate` (ROUTE_CONTRACTS section 6; TASK-015 scope item 2).
 */
final class DeactivateProductController extends CommandController
{
    protected function request(): string
    {
        return DeactivateProductRequest::class;
    }

    protected function action(): string
    {
        return DeactivateProduct::class;
    }
}
