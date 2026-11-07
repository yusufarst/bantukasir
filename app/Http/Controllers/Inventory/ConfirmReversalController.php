<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\ConfirmReversal;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Inventory\ConfirmReversalRequest;

/**
 * RC-10 `reversal.confirm` (ROUTE_CONTRACTS section 6; TASK-026 scope item 2).
 */
final class ConfirmReversalController extends CommandController
{
    protected function request(): string
    {
        return ConfirmReversalRequest::class;
    }

    protected function action(): string
    {
        return ConfirmReversal::class;
    }
}
