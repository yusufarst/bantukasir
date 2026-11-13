<?php

namespace App\Http\Controllers\Documents;

use App\Actions\Documents\RecordReceiptPrint;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Documents\RecordReceiptPrintRequest;

/**
 * RC-44 `document.print_receipt` (ROUTE_CONTRACTS section 6; TASK-031 scope item 2).
 */
final class RecordReceiptPrintController extends CommandController
{
    protected function request(): string
    {
        return RecordReceiptPrintRequest::class;
    }

    protected function action(): string
    {
        return RecordReceiptPrint::class;
    }
}
