<?php

namespace App\Domain\Costing\Replay;

use App\Domain\Costing\CostIncompleteMarks;
use RuntimeException;

/**
 * A replay that cannot be done safely (ADR-008 section 4.3; BR-COST-24, BR-COST-27; TR-TIME-01;
 * G-07): its correction is refused REPLAY_REJECTED with no change, and the cause names the mark that
 * D-034 then writes (SCHEMA section 5.8) — the movement cap, the time budget, or an unsafe history,
 * which also covers a recomputed value outside the ranges of TECHNICAL_RULES section 11.1.
 */
final class ReplayRefused extends RuntimeException
{
    private function __construct(public readonly string $rejection)
    {
        parent::__construct(sprintf('The replay was refused: %s.', $rejection));
    }

    public static function movementCap(): self
    {
        return new self(CostIncompleteMarks::MOVEMENT_CAP);
    }

    public static function timeBudget(): self
    {
        return new self(CostIncompleteMarks::TIME_BUDGET);
    }

    public static function unsafeHistory(): self
    {
        return new self(CostIncompleteMarks::UNSAFE_HISTORY);
    }
}
