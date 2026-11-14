<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\ConfirmOpeningStock;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Inventory\ConfirmOpeningStockRequest;

/**
 * RC-11 `opening_stock.confirm` (ROUTE_CONTRACTS section 6; TASK-035 scope item 3).
 */
final class ConfirmOpeningStockController extends CommandController
{
    protected function request(): string
    {
        return ConfirmOpeningStockRequest::class;
    }

    protected function action(): string
    {
        return ConfirmOpeningStock::class;
    }
}
