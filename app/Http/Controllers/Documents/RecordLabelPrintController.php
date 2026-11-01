<?php

namespace App\Http\Controllers\Documents;

use App\Actions\Documents\RecordLabelPrint;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Documents\RecordLabelPrintRequest;

/**
 * RC-47 `document.print_labels` (ROUTE_CONTRACTS section 6; TASK-018 scope item 3).
 */
final class RecordLabelPrintController extends CommandController
{
    protected function request(): string
    {
        return RecordLabelPrintRequest::class;
    }

    protected function action(): string
    {
        return RecordLabelPrint::class;
    }
}
