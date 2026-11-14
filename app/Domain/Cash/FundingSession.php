<?php

namespace App\Domain\Cash;

use App\Support\Numbers\Money;

/**
 * A cash session as a drawer-funded refund holds it under its update lock, the session selected as the
 * source of the money (TECHNICAL_RULES section 7.1 step 4; G-04, G-05; BR-RFD-07; TASK-034): its internal
 * key, its number, whether it is OPEN, its opening cash, from which its expected cash is derived, and its
 * register, whose code and label do not change while the session is OPEN (BR-CASH-02).
 */
final readonly class FundingSession
{
    public function __construct(
        public int $id,
        public string $number,
        public bool $open,
        public Money $openingCash,
        public int $registerId,
    ) {}
}
