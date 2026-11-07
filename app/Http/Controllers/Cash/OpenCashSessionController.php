<?php

namespace App\Http\Controllers\Cash;

use App\Actions\Cash\OpenCashSession;
use App\Http\Controllers\Commands\CommandController;
use App\Http\Requests\Cash\OpenCashSessionRequest;

/**
 * RC-02 `cash_session.open` (ROUTE_CONTRACTS section 6; TASK-027 scope item 3).
 */
final class OpenCashSessionController extends CommandController
{
    protected function request(): string
    {
        return OpenCashSessionRequest::class;
    }

    protected function action(): string
    {
        return OpenCashSession::class;
    }
}
