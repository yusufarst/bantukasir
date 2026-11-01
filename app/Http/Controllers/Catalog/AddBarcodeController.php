<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\AddBarcode;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Catalog\AddBarcodeRequest;

/**
 * RC-27 `barcode.add` (ROUTE_CONTRACTS section 6; TASK-016 scope item 3).
 */
final class AddBarcodeController extends CommandController
{
    protected function request(): string
    {
        return AddBarcodeRequest::class;
    }

    protected function action(): string
    {
        return AddBarcode::class;
    }
}
