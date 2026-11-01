<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\ConfirmCsvImport;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Catalog\ConfirmCsvImportRequest;

/**
 * RC-31 `csv_import.confirm` (ROUTE_CONTRACTS section 6; TASK-019 scope item 4).
 */
final class ConfirmCsvImportController extends CommandController
{
    protected function request(): string
    {
        return ConfirmCsvImportRequest::class;
    }

    protected function action(): string
    {
        return ConfirmCsvImport::class;
    }
}
