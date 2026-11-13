<?php

namespace App\Http\Controllers\Documents;

use App\Actions\Documents\RecordSessionReportPrint;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Documents\RecordSessionReportPrintRequest;

/**
 * RC-45 `document.print_session_report` (ROUTE_CONTRACTS section 6; TASK-031 scope item 2).
 */
final class RecordSessionReportPrintController extends CommandController
{
    protected function request(): string
    {
        return RecordSessionReportPrintRequest::class;
    }

    protected function action(): string
    {
        return RecordSessionReportPrint::class;
    }
}
