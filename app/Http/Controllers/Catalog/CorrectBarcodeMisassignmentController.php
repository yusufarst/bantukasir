<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\CorrectBarcodeMisassignment;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Catalog\CorrectBarcodeMisassignmentRequest;

/**
 * RC-30 `barcode.correct_misassignment` (ROUTE_CONTRACTS section 6; TASK-020 scope item 2).
 */
final class CorrectBarcodeMisassignmentController extends CommandController
{
    protected function request(): string
    {
        return CorrectBarcodeMisassignmentRequest::class;
    }

    protected function action(): string
    {
        return CorrectBarcodeMisassignment::class;
    }
}
