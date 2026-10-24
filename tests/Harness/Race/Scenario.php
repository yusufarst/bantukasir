<?php

namespace Tests\Harness\Race;

/**
 * A command that a race test runs in a worker process (TS-20): it uses the
 * application's default connection, the runtime role, and calls the
 * CommandPoints that the container gives it. Its result must be JSON.
 */
interface Scenario
{
    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function run(array $arguments): array;
}
