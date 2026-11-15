<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\ReleaseOnboarding;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Inventory\ReleaseOnboardingRequest;

/**
 * RC-13 `onboarding.release` (ROUTE_CONTRACTS section 6; TASK-035 scope item 2).
 */
final class ReleaseOnboardingController extends CommandController
{
    protected function request(): string
    {
        return ReleaseOnboardingRequest::class;
    }

    protected function action(): string
    {
        return ReleaseOnboarding::class;
    }
}
