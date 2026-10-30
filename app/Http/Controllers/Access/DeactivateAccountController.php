<?php

namespace App\Http\Controllers\Access;

use App\Actions\Access\DeactivateAccount;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Access\DeactivateAccountRequest;

/**
 * RC-38 `user.deactivate` (ROUTE_CONTRACTS section 6; TASK-011 scope item 1).
 */
final class DeactivateAccountController extends CommandController
{
    protected function request(): string
    {
        return DeactivateAccountRequest::class;
    }

    protected function action(): string
    {
        return DeactivateAccount::class;
    }
}
