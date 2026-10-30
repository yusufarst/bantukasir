<?php

namespace App\Http\Controllers\Access;

use App\Actions\Access\RevokePasswordLink;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Access\RevokePasswordLinkRequest;

/**
 * RC-42 `user.revoke_link` (ROUTE_CONTRACTS section 6; TASK-011 scope item 1).
 */
final class RevokePasswordLinkController extends CommandController
{
    protected function request(): string
    {
        return RevokePasswordLinkRequest::class;
    }

    protected function action(): string
    {
        return RevokePasswordLink::class;
    }
}
