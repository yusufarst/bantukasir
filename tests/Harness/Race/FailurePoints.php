<?php

namespace Tests\Harness\Race;

use App\Database\CommandPoints;
use InvalidArgumentException;

/**
 * Failure injection in the test process (TEST_STRATEGY TS-21): the command
 * fails right after write step k. It records every point it passes, so that a
 * test proves the command reached the step. A test binds it with
 * $this->app->instance(CommandPoints::class, new FailurePoints($k)).
 */
final class FailurePoints implements CommandPoints
{
    /**
     * The lock steps of TECHNICAL_RULES section 7.1 that a command may name.
     */
    public const array LOCK_STEPS = ['2.1', '2.2', '2.3', '2.4', '3', '4', '5', '6', '7'];

    /**
     * The writes of the command pipeline in transactions of their own
     * (CommandPoints::afterOwnWrite; TASK-008).
     */
    public const array OWN_WRITES = ['receipt', 'marking', 'settlement', 'report'];

    /** @var list<string> */
    public array $passed = [];

    public function __construct(private readonly ?int $failAfter = null, private readonly ?string $failAtOwnWrite = null)
    {
        if ($failAfter !== null && $failAfter < 1) {
            throw new InvalidArgumentException('Write steps are counted from 1.');
        }

        if ($failAtOwnWrite !== null) {
            self::ownWritePoint($failAtOwnWrite);
        }
    }

    public function afterIdentity(): void
    {
        $this->passed[] = 'after_identity';
    }

    public function afterLock(string $step): void
    {
        $this->passed[] = self::lockPoint($step);
    }

    public function afterWrite(int $step): void
    {
        $this->passed[] = 'after_write:'.$step;

        if ($step === $this->failAfter) {
            throw InjectedFailure::afterWrite($step);
        }
    }

    public function beforeCommit(): void
    {
        $this->passed[] = 'before_commit';
    }

    public function afterOwnWrite(string $write): void
    {
        $this->passed[] = self::ownWritePoint($write);

        if ($write === $this->failAtOwnWrite) {
            throw InjectedFailure::afterOwnWrite($write);
        }
    }

    public static function ownWritePoint(string $write): string
    {
        if (! in_array($write, self::OWN_WRITES, true)) {
            throw new InvalidArgumentException(sprintf('%s is no write of the command pipeline.', $write));
        }

        return 'own_write:'.$write;
    }

    public static function lockPoint(string $step): string
    {
        if (! in_array($step, self::LOCK_STEPS, true)) {
            throw new InvalidArgumentException(sprintf('%s is no lock step of TECHNICAL_RULES section 7.1.', $step));
        }

        return 'after_lock:'.$step;
    }
}
