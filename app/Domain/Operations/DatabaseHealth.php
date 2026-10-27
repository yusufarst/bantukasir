<?php

namespace App\Domain\Operations;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;

/**
 * The listener of the framework's health event that checks the database
 * (ROUTE_CONTRACTS RH-01; ADR-011 item 11; TASK-007): the runtime connection
 * answers a trivial query, or the event fails and RH-01 answers an error
 * status. It reads nothing of the business and writes nothing.
 */
final class DatabaseHealth
{
    public function handle(DiagnosingHealth $event): void
    {
        DB::connection()->select('select 1');
    }
}
