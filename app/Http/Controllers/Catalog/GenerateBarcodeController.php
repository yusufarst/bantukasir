<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\GenerateBarcode;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Catalog\GenerateBarcodeRequest;

/**
 * RC-28 `barcode.generate` (ROUTE_CONTRACTS section 6; TASK-016 scope item 3).
 */
final class GenerateBarcodeController extends CommandController
{
    protected function request(): string
    {
        return GenerateBarcodeRequest::class;
    }

    protected function action(): string
    {
        return GenerateBarcode::class;
    }
}
