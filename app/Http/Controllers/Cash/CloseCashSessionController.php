<?php

namespace App\Http\Controllers\Cash;

use App\Actions\Cash\CloseCashSession;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Cash\CloseCashSessionRequest;

/**
 * RC-03 `cash_session.close` (ROUTE_CONTRACTS section 6; TASK-030 scope item 2).
 */
final class CloseCashSessionController extends CommandController
{
    protected function request(): string
    {
        return CloseCashSessionRequest::class;
    }

    protected function action(): string
    {
        return CloseCashSession::class;
    }
}
