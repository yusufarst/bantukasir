<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\ReplaceOpeningStock;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Inventory\ReplaceOpeningStockRequest;

/**
 * RC-48 `opening_stock.replace` (ROUTE_CONTRACTS section 6; TASK-035 scope item 3).
 */
final class ReplaceOpeningStockController extends CommandController
{
    protected function request(): string
    {
        return ReplaceOpeningStockRequest::class;
    }

    protected function action(): string
    {
        return ReplaceOpeningStock::class;
    }
}
