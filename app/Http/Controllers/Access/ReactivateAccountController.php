<?php

namespace App\Http\Controllers\Access;

use App\Actions\Access\ReactivateAccount;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Access\ReactivateAccountRequest;

/**
 * RC-39 `user.reactivate` (ROUTE_CONTRACTS section 6; TASK-011 scope item 1).
 */
final class ReactivateAccountController extends CommandController
{
    protected function request(): string
    {
        return ReactivateAccountRequest::class;
    }

    protected function action(): string
    {
        return ReactivateAccount::class;
    }
}
