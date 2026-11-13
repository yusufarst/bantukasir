<?php

namespace App\Http\Controllers\Cash;

use App\Actions\Cash\CloseCashSessionAssisted;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Cash\CloseCashSessionAssistedRequest;

/**
 * RC-04 `cash_session.close_assisted` (ROUTE_CONTRACTS section 6; TASK-032 scope item 2).
 */
final class CloseCashSessionAssistedController extends CommandController
{
    protected function request(): string
    {
        return CloseCashSessionAssistedRequest::class;
    }

    protected function action(): string
    {
        return CloseCashSessionAssisted::class;
    }
}
