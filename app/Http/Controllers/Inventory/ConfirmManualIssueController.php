<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\ConfirmManualIssue;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Inventory\ConfirmManualIssueRequest;

/**
 * RC-08 `manual_issue.confirm` (ROUTE_CONTRACTS section 6; TASK-023 scope item 3).
 */
final class ConfirmManualIssueController extends CommandController
{
    protected function request(): string
    {
        return ConfirmManualIssueRequest::class;
    }

    protected function action(): string
    {
        return ConfirmManualIssue::class;
    }
}
