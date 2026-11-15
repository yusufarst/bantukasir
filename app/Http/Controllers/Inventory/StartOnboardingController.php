<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\StartOnboarding;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Inventory\StartOnboardingRequest;

/**
 * RC-12 `onboarding.start` (ROUTE_CONTRACTS section 6; TASK-035 scope item 2).
 */
final class StartOnboardingController extends CommandController
{
    protected function request(): string
    {
        return StartOnboardingRequest::class;
    }

    protected function action(): string
    {
        return StartOnboarding::class;
    }
}
