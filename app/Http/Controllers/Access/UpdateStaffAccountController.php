<?php

namespace App\Http\Controllers\Access;

use App\Actions\Access\UpdateStaffAccount;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Access\UpdateStaffAccountRequest;

/**
 * RC-36 `user.update` (ROUTE_CONTRACTS section 6; TASK-011 scope item 1).
 */
final class UpdateStaffAccountController extends CommandController
{
    protected function request(): string
    {
        return UpdateStaffAccountRequest::class;
    }

    protected function action(): string
    {
        return UpdateStaffAccount::class;
    }
}
