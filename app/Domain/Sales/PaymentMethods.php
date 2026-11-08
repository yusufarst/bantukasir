<?php

namespace App\Domain\Sales;

use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Support\Numbers\Money;
use LogicException;

/**
 * The payment methods of a Sale and their validation, in one place (ARCHITECTURE ARC-30 to ARC-33;
 * SALES_CASH BR-PAY-01 to BR-PAY-07; D-036, D-077): CASH and BANK_TRANSFER, an explicit list that
 * the server checks; any other value, STATIC_QRIS included, is refused METHOD_NOT_ALLOWED. A Sale
 * has exactly one full payment, whose applied amount is the Sale total:
 *
 * - CASH: the tendered amount is at least the total, else TENDER_INSUFFICIENT; the change is the
 *   tendered amount less the total, and the tendered amount is not sales value (BR-PAY-02);
 * - BANK_TRANSFER: the exact total, with an optional reference; the request itself is the cashier's
 *   confirmation that the funds were received (BR-PAY-03, BR-SALE-12), so no pending state exists.
 *
 * There is no payment provider: no gateway, adapter, callback, webhook, polling or asynchronous
 * state, and a completed Sale never becomes pending (ARC-31). A future provider would be a reviewed
 * change of this list and its validation, the only seam (ARC-32); fees and settlement are not
 * modelled, and a Sale is recorded at the gross amount paid (ARC-33, BR-PAY-05).
 */
final class PaymentMethods
{
    public const string CASH = 'CASH';

    public const string BANK_TRANSFER = 'BANK_TRANSFER';

    /** The server's list (BR-PAY-07). */
    public const array ALL = [self::CASH, self::BANK_TRANSFER];

    /**
     * Whether the value is a method of the server's list, compared exactly.
     */
    public static function allows(mixed $method): bool
    {
        return in_array($method, self::ALL, true);
    }

    /**
     * The one payment of a Sale of this total. The request's shape was validated before the
     * Action: CASH carries its tendered amount and no reference, BANK_TRANSFER no tendered amount.
     *
     * @throws CommandRefused METHOD_NOT_ALLOWED or TENDER_INSUFFICIENT
     */
    public static function pay(string $method, Money $total, ?Money $tendered, ?string $reference): SalePayment
    {
        if (! self::allows($method)) {
            throw CommandRefused::of(ErrorCode::MethodNotAllowed);
        }

        if ($method === self::BANK_TRANSFER) {
            if ($tendered !== null) {
                throw new LogicException('A transfer takes no tendered cash, which the request refuses (BR-PAY-03).');
            }

            return new SalePayment(self::BANK_TRANSFER, $total, null, null, $reference);
        }

        if ($tendered === null || $reference !== null) {
            throw new LogicException('Cash takes its tendered amount and no reference, which the request holds (BR-PAY-02).');
        }

        if ($tendered->compareTo($total) < 0) {
            throw CommandRefused::of(ErrorCode::TenderInsufficient);
        }

        return new SalePayment(self::CASH, $total, $tendered, $tendered->minus($total), null);
    }
}
