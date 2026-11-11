<?php

namespace App\Domain\Cash;

use App\Support\Numbers\Money;

/**
 * A cash session as its close holds it under the close's update lock (TECHNICAL_RULES section 7.1
 * step 4; SCHEMA sections 5.10 and 7; BR-CASH-13): its internal key and number, whether it is OPEN,
 * the activity version the close compares with the one reviewed, and what its report freezes from
 * it — the opening cash, the opened time as it is stored, the responsible user's frozen name and
 * the register.
 */
final readonly class ClosingSession
{
    public function __construct(
        public int $id,
        public string $number,
        public bool $open,
        public int $activityVersion,
        public Money $openingCash,
        public string $openedAt,
        public string $responsibleDisplayName,
        public int $registerId,
    ) {}
}
