<?php

namespace App\Domain\Cash;

use App\Support\Numbers\Money;
use App\Support\Numbers\NumberOutOfRange;

/**
 * The cash arithmetic of a session (SALES_CASH section 6.1, BR-CASH-12; SCHEMA G-05; D-035, D-036,
 * D-077): expected cash is the opening cash plus the applied CASH payments of the session minus the
 * drawer-funded CASH refunds paid from it; the variance is the physical cash minus expected cash.
 * BANK_TRANSFER never changes expected cash, and the opening cash and the variance are never sales
 * or profit: they appear here only. There is no hidden balancing adjustment. Every amount is whole
 * rupiah on Money, never a floating point (ADR-006).
 */
final readonly class CashCount
{
    private function __construct(
        public Money $expected,
        public Money $physical,
        public Money $variance,
    ) {}

    /**
     * Expected cash of a session from its facts (G-05), which is never below zero: a drawer-funded
     * refund is at most the session's expected cash (D-045).
     *
     * @throws NumberOutOfRange when it is outside the range of a stored amount (TECHNICAL_RULES
     *                          section 11.1), which refuses the command
     */
    public static function expected(Money $openingCash, Money $cashApplied, Money $drawerRefunds): Money
    {
        return self::due($openingCash, $cashApplied, $drawerRefunds)->assertStored();
    }

    /**
     * The same arithmetic, exact and unchecked, as a page shows the running figures of an OPEN
     * session (BR-CASH-13 step 1), which records nothing.
     */
    public static function due(Money $openingCash, Money $cashApplied, Money $drawerRefunds): Money
    {
        return $openingCash->plus($cashApplied)->minus($drawerRefunds);
    }

    /**
     * A count of the drawer against expected cash: the variance with its sign, negative when cash
     * is short (BR-CASH-12).
     *
     * @throws NumberOutOfRange when the physical cash is outside the range of a stored amount
     */
    public static function of(Money $expected, Money $physical): self
    {
        $counted = $physical->assertStored();
        $due = $expected->assertStored();

        return new self($due, $counted, $counted->minus($due)->assertVariance());
    }
}
