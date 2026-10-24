<?php

namespace Tests\Harness\Race;

use App\Database\CommandPoints;
use RuntimeException;

/**
 * The command points of a worker process (TEST_STRATEGY TS-20, TS-21): at a
 * point that the test chose as a barrier, it tells the test with
 * "REACHED <point>" and waits until the test answers "GO", or "ABORT", which
 * fails the command so that its transaction rolls back; at any other point it
 * writes "PASSED <point>" and goes on. After write step k it can fail the
 * command as FailurePoints does. The protocol runs over the worker's standard
 * input and output (process.php).
 */
final class PipePoints implements CommandPoints
{
    private readonly FailurePoints $failure;

    /**
     * @param  list<string>  $barriers
     * @param  resource  $input
     * @param  resource  $output
     */
    public function __construct(
        private readonly array $barriers,
        ?int $failAfter,
        private $input,
        private $output,
    ) {
        $this->failure = new FailurePoints($failAfter);
    }

    public function afterIdentity(): void
    {
        $this->point('after_identity');
    }

    public function afterLock(string $step): void
    {
        $this->point(FailurePoints::lockPoint($step));
    }

    public function afterWrite(int $step): void
    {
        $this->point('after_write:'.$step);
        $this->failure->afterWrite($step);
    }

    public function beforeCommit(): void
    {
        $this->point('before_commit');
    }

    private function point(string $point): void
    {
        if (! in_array($point, $this->barriers, true)) {
            Protocol::send($this->output, 'PASSED', $point);

            return;
        }

        Protocol::send($this->output, 'REACHED', $point);
        $answer = fgets($this->input);

        if ($answer === "GO\n") {
            return;
        }

        if ($answer === "ABORT\n") {
            throw new RuntimeException(sprintf('Aborted by the test at %s.', $point));
        }

        throw new RuntimeException(sprintf('No answer from the test at %s.', $point));
    }
}
