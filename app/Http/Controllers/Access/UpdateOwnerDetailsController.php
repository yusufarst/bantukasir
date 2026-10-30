<?php

namespace App\Http\Controllers\Access;

use App\Actions\Access\UpdateOwnerDetails;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Access\UpdateOwnerDetailsRequest;

/**
 * RC-37 `owner.update_details` (ROUTE_CONTRACTS section 6; TASK-011 scope item 2).
 */
final class UpdateOwnerDetailsController extends CommandController
{
    protected function request(): string
    {
        return UpdateOwnerDetailsRequest::class;
    }

    protected function action(): string
    {
        return UpdateOwnerDetails::class;
    }
}
