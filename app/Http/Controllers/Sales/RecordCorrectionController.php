<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\RecordCorrection;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Sales\RecordCorrectionRequest;

/**
 * RC-06 `sale_correction.other` (ROUTE_CONTRACTS section 6; TASK-033 scope item 3).
 */
final class RecordCorrectionController extends CommandController
{
    protected function request(): string
    {
        return RecordCorrectionRequest::class;
    }

    protected function action(): string
    {
        return RecordCorrection::class;
    }
}
