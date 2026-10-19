<?php

namespace App\Support\Numbers;

/**
 * The supported ranges of TECHNICAL_RULES section 11.1 (BR-NUM-04), as
 * canonical strings. A value outside its range is refused, never truncated;
 * report aggregates have no fixed cap and are not checked against these.
 */
final class Limits
{
    /** A selling price and any single stored money amount; a cash variance is within −MONEY_MAX to MONEY_MAX. */
    public const string MONEY_MAX = '999999999999';

    /** The quantity of one line or movement, above 0. */
    public const string QUANTITY_MAX = '99999999.999';

    /** A stock balance and a minimum stock, from 0. */
    public const string STOCK_MAX = '9999999999.999';

    /** The acquisition cost of one evidence, a unit cost and an average cost, from 0. */
    public const string COST_MAX = '999999999999.999999';

    /** An issue or return allocation and the inventory value of one product, from 0. */
    public const string VALUE_MAX = '999999999999999.999999';
}
