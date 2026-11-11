<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\CheckoutSale;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Sales\CheckoutSaleRequest;

/**
 * RC-01 `sale.checkout` (ROUTE_CONTRACTS section 6; TASK-028 scope item 2).
 */
final class CheckoutSaleController extends CommandController
{
    protected function request(): string
    {
        return CheckoutSaleRequest::class;
    }

    protected function action(): string
    {
        return CheckoutSale::class;
    }
}
