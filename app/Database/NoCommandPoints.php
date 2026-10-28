<?php

namespace App\Database;

/**
 * The command points of the application: none of them stops, waits or fails
 * (TEST_STRATEGY TS-20, TS-21). The barriers and the failure injection exist
 * only in the test harness (CommandPoints).
 */
final class NoCommandPoints implements CommandPoints
{
    public function afterIdentity(): void {}

    public function afterLock(string $step): void {}

    public function afterWrite(int $step): void {}

    public function beforeCommit(): void {}

    public function afterOwnWrite(string $write): void {}
}
