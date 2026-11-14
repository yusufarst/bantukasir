<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\WithdrawZeroCount;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Inventory\WithdrawZeroCountRequest;

/**
 * RC-14 `onboarding.withdraw_zero_count` (ROUTE_CONTRACTS section 6; TASK-035 scope item 3).
 */
final class WithdrawZeroCountController extends CommandController
{
    protected function request(): string
    {
        return WithdrawZeroCountRequest::class;
    }

    protected function action(): string
    {
        return WithdrawZeroCount::class;
    }
}
