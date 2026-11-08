<?php

namespace App\Domain\Cash;

/**
 * A cash session as a Sale holds it under its update lock (TECHNICAL_RULES section 7.1 step 4; G-04):
 * its internal key, its number, which the receipt freezes, and whether it is OPEN.
 */
final readonly class GuardedSession
{
    public function __construct(
        public int $id,
        public string $number,
        public bool $open,
    ) {}
}
