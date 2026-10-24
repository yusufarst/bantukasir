<?php

namespace App\Database;

use Illuminate\Container\Attributes\Bind;

/**
 * The named points of a command's transaction (TEST_STRATEGY TS-20, TS-21;
 * TASK-004): after the insert of its identity, after each lock step of
 * TECHNICAL_RULES section 7.1, after each write step k, and before commit.
 * Every command's Action calls them in that order.
 *
 * The application resolves NoCommandPoints, which does nothing, in every
 * environment and build. Only the test harness in tests/Harness, which the
 * release build does not contain (composer.json autoload-dev), binds an
 * implementation that stops at a barrier or fails after a write step, and it
 * does so only in a test process.
 */
#[Bind(NoCommandPoints::class)]
interface CommandPoints
{
    /**
     * After the command identity is inserted (TR §7.1 step 1).
     */
    public function afterIdentity(): void;

    /**
     * After the locks of one step of TR §7.1 are held: "2.1", "2.2", "2.3",
     * "2.4", "3", "4", "5", "6" or "7".
     */
    public function afterLock(string $step): void;

    /**
     * After write step k of the command, counted from 1 in the order of its
     * contract.
     */
    public function afterWrite(int $step): void;

    /**
     * Immediately before the transaction commits.
     */
    public function beforeCommit(): void;
}
