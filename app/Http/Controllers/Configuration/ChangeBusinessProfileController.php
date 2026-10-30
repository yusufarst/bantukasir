<?php

namespace App\Http\Controllers\Configuration;

use App\Actions\Configuration\ChangeBusinessProfile;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Configuration\ChangeBusinessProfileRequest;

/**
 * RC-32 `business_profile.change` (ROUTE_CONTRACTS section 6; TASK-010 scope
 * item 4): the Owner (CAP-30) with recent authentication, in the bucket of
 * every command and, with a logo, the bucket `logo`; the pipeline of the
 * command platform runs its FormRequest and Action.
 */
final class ChangeBusinessProfileController extends CommandController
{
    protected function request(): string
    {
        return ChangeBusinessProfileRequest::class;
    }

    protected function action(): string
    {
        return ChangeBusinessProfile::class;
    }
}
