<?php

namespace Tests\Harness\Commands;

use App\Http\Controllers\Commands\CommandController;

/**
 * The invokable controller of the probe operation, on the base of every
 * command's controller.
 */
final class ProbeController extends CommandController
{
    protected function request(): string
    {
        return ProbeRequest::class;
    }

    protected function action(): string
    {
        return ProbeAction::class;
    }
}
