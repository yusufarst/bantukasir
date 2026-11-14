<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\RecordDrawerFundedCorrection;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Sales\RecordDrawerFundedCorrectionRequest;

/**
 * RC-05 `sale_correction.drawer_funded` (ROUTE_CONTRACTS section 6; TASK-034 scope item 1).
 */
final class RecordDrawerFundedCorrectionController extends CommandController
{
    protected function request(): string
    {
        return RecordDrawerFundedCorrectionRequest::class;
    }

    protected function action(): string
    {
        return RecordDrawerFundedCorrection::class;
    }
}
