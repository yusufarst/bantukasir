<?php

namespace App\Http\Controllers\Access;

use App\Actions\Access\CreateStaffAccount;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Access\CreateStaffAccountRequest;

/**
 * RC-35 `user.create` (ROUTE_CONTRACTS section 6; TASK-011 scope item 1).
 */
final class CreateStaffAccountController extends CommandController
{
    protected function request(): string
    {
        return CreateStaffAccountRequest::class;
    }

    protected function action(): string
    {
        return CreateStaffAccount::class;
    }
}
