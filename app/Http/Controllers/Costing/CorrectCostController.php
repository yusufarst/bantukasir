<?php

namespace App\Http\Controllers\Costing;

use App\Actions\Costing\CorrectCost;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Costing\CorrectCostRequest;

/**
 * RC-15 `cost_correction.confirm` (ROUTE_CONTRACTS section 6; TASK-025 scope item 2).
 */
final class CorrectCostController extends CommandController
{
    protected function request(): string
    {
        return CorrectCostRequest::class;
    }

    protected function action(): string
    {
        return CorrectCost::class;
    }
}
