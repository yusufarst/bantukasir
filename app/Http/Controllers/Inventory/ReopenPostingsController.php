<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\ReopenPostings;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Inventory\ReopenPostingsRequest;

/**
 * RC-16 `posting_block.reopen` (ROUTE_CONTRACTS section 6; TASK-036 scope item 3).
 */
final class ReopenPostingsController extends CommandController
{
    protected function request(): string
    {
        return ReopenPostingsRequest::class;
    }

    protected function action(): string
    {
        return ReopenPostings::class;
    }
}
