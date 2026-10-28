<?php

namespace Tests\Harness\Race;

use RuntimeException;

/**
 * The failure that the harness injects after write step k of a command
 * (TEST_STRATEGY TS-21). It exists only in the test harness.
 */
final class InjectedFailure extends RuntimeException
{
    public static function afterWrite(int $step): self
    {
        return new self(sprintf('Failure injected after write step %d (TS-21).', $step));
    }

    public static function afterOwnWrite(string $write): self
    {
        return new self(sprintf('Failure injected after the pipeline write %s (TS-21).', $write));
    }
}
