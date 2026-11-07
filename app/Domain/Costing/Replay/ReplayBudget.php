<?php

namespace App\Domain\Costing\Replay;

use App\Support\Time\Clock;
use Carbon\CarbonImmutable;
use Closure;

/**
 * The time budget of a replay (TECHNICAL_RULES section 9 and TR-TIME-01; config/costing.php): a
 * replay runs in a bounded heavy command, whose transaction PostgreSQL ends with its session at 30
 * seconds. The recomputation stops, and the correction is refused TIME_BUDGET with nothing written,
 * once less than the publication's reserve remains of that budget, counted from the start of the
 * command's work, so that the publication, the audit, the result and the commit still fit.
 */
final readonly class ReplayBudget
{
    private function __construct(
        private Clock $clock,
        private CarbonImmutable $deadline,
    ) {}

    /**
     * The budget of a replay whose command's work started now.
     */
    public static function startingNow(Clock $clock): self
    {
        $budget = config()->string('database.limits.heavy_transaction_budget');
        $seconds = preg_match('/^([0-9]+)s$/D', $budget, $match) === 1 ? (int) $match[1] : 30;
        $reserve = config()->integer('costing.replay.publication_reserve');

        return new self($clock, $clock->now()->addSeconds(max(0, $seconds - $reserve)));
    }

    /**
     * Throws ReplayRefused TIME_BUDGET once the deadline has come.
     *
     * @throws ReplayRefused
     */
    public function check(): void
    {
        if ($this->clock->now()->greaterThanOrEqualTo($this->deadline)) {
            throw ReplayRefused::timeBudget();
        }
    }

    /**
     * The check, for the recomputation to call before every movement.
     *
     * @return Closure(): void
     */
    public function checker(): Closure
    {
        return function (): void {
            $this->check();
        };
    }
}
