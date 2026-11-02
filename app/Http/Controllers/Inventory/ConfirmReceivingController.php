<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\ConfirmReceiving;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Inventory\ConfirmReceivingRequest;

/**
 * RC-07 `receiving.confirm` (ROUTE_CONTRACTS section 6; TASK-021 scope item 4).
 */
final class ConfirmReceivingController extends CommandController
{
    protected function request(): string
    {
        return ConfirmReceivingRequest::class;
    }

    protected function action(): string
    {
        return ConfirmReceiving::class;
    }
}
