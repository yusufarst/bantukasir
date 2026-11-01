<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\RetireBarcode;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Catalog\RetireBarcodeRequest;

/**
 * RC-29 `barcode.retire` (ROUTE_CONTRACTS section 6; TASK-016 scope item 3).
 */
final class RetireBarcodeController extends CommandController
{
    protected function request(): string
    {
        return RetireBarcodeRequest::class;
    }

    protected function action(): string
    {
        return RetireBarcode::class;
    }
}
