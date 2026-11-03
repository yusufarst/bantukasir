<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\ConfirmAdjustment;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Inventory\ConfirmAdjustmentRequest;

/**
 * RC-09 `adjustment.confirm` (ROUTE_CONTRACTS section 6; TASK-024 scope item 2).
 */
final class ConfirmAdjustmentController extends CommandController
{
    protected function request(): string
    {
        return ConfirmAdjustmentRequest::class;
    }

    protected function action(): string
    {
        return ConfirmAdjustment::class;
    }
}
