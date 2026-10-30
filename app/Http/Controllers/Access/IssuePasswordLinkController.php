<?php

namespace App\Http\Controllers\Access;

use App\Actions\Access\IssuePasswordLink;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Access\IssuePasswordLinkRequest;

/**
 * RC-41 `user.issue_link` (ROUTE_CONTRACTS section 6; TASK-011 scope item 1).
 */
final class IssuePasswordLinkController extends CommandController
{
    protected function request(): string
    {
        return IssuePasswordLinkRequest::class;
    }

    protected function action(): string
    {
        return IssuePasswordLink::class;
    }
}
