<?php

namespace Tests\Concurrency;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The smoke test of composer test:race (TASK-001): two independent connections
 * to real PostgreSQL are separate sessions, and a lock that one holds refuses
 * the other. TASK-004 adds the race harness of TS-20.
 */
final class IndependentConnectionsSmokeTest extends TestCase
{
    private const LOCK = 20261019;

    public function test_a_lock_held_by_one_connection_refuses_another(): void
    {
        config(['database.connections.pgsql_second' => config('database.connections.pgsql')]);
        $first = DB::connection('pgsql');
        $second = DB::connection('pgsql_second');

        $this->assertNotSame($first->scalar('select pg_backend_pid()'), $second->scalar('select pg_backend_pid()'));
        $this->assertTrue($first->scalar('select pg_try_advisory_lock(?)', [self::LOCK]));

        try {
            $this->assertFalse($second->scalar('select pg_try_advisory_lock(?)', [self::LOCK]));
        } finally {
            $this->assertTrue($first->scalar('select pg_advisory_unlock(?)', [self::LOCK]));
        }

        $this->assertTrue($second->scalar('select pg_try_advisory_lock(?)', [self::LOCK]));
        $this->assertTrue($second->scalar('select pg_advisory_unlock(?)', [self::LOCK]));
    }
}
