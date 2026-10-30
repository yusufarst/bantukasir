<?php

namespace App\Http\Controllers\Access;

use App\Actions\Access\AnonymizeAccount;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Access\AnonymizeAccountRequest;

/**
 * RC-40 `user.anonymize` (ROUTE_CONTRACTS section 6; TASK-011 scope item 1).
 */
final class AnonymizeAccountController extends CommandController
{
    protected function request(): string
    {
        return AnonymizeAccountRequest::class;
    }

    protected function action(): string
    {
        return AnonymizeAccount::class;
    }
}
