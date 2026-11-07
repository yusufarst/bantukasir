<?php

namespace App\Http\Controllers\Cash;

use App\Actions\Cash\CreateCashRegister;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Cash\CreateCashRegisterRequest;

/**
 * RC-33 `cash_register.create` (ROUTE_CONTRACTS section 6; TASK-027 scope item 2).
 */
final class CreateCashRegisterController extends CommandController
{
    protected function request(): string
    {
        return CreateCashRegisterRequest::class;
    }

    protected function action(): string
    {
        return CreateCashRegister::class;
    }
}
