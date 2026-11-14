<?php

namespace App\Actions\Sales;

use App\Domain\Sales\Corrections\CorrectedSale;
use App\Domain\Sales\Corrections\Payout;
use App\Support\Numbers\Money;
use LogicException;

/**
 * RC-06 `sale_correction.other` (INTERACTION_CONTRACTS RC-06; ROUTE_CONTRACTS section 6; TECHNICAL_RULES
 * section 7.2, "Owner-funded or BANK_TRANSFER refund or return"; SALES_CASH BR-RFD-01 to BR-RFD-14; D-035;
 * CAP-24; THR-19; WF-11; TASK-033 scope item 3): the Owner's refund paid from the Owner's own funds in cash
 * or by bank transfer, a return, or both, on the correction engine (CorrectionAction). The payout is the one
 * actually made, outside the transaction, with the reference the Owner typed (BR-RFD-05, BR-RFD-14): CASH is
 * the Owner's own funds and BANK_TRANSFER has no funding source; no rule ties it to the Sale's own method. A
 * return without money has no payout. No cash session is touched: no session's lock, expected cash,
 * activity version or report (D-035; BR-RFD-09).
 */
final class RecordCorrection extends CorrectionAction
{
    public const string OPERATION = 'sale_correction.other';

    public function operation(): string
    {
        return self::OPERATION;
    }

    protected function payout(CorrectionPayload $request, CorrectedSale $sale, Money $refund): ?Payout
    {
        if (! $refund->isPositive()) {
            return null;
        }

        $method = $request->payout['method'] ?? null;
        $reference = $request->payout['reference'] ?? null;

        if (! is_string($method) || ! is_string($reference)) {
            throw new LogicException('The payout of a refund was validated before the Action (RC-06).');
        }

        return Payout::made($method, $reference);
    }
}
