<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The smoke test of composer test:postgres (TASK-001): the application reaches
 * real PostgreSQL of the pinned major version through its own configuration.
 * TASK-004 adds the roles, the grants and the database test harness.
 */
final class PostgresSmokeTest extends TestCase
{
    public function test_the_application_reaches_postgresql_18(): void
    {
        $version = DB::scalar('show server_version_num');

        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertIsString($version);
        $this->assertSame(18, intdiv((int) $version, 10000));
    }
}
