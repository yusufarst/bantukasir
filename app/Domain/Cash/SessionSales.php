<?php

namespace App\Domain\Cash;

use App\Support\Numbers\Money;

/**
 * The completed Sales of a cash session as its close and its pages receive them (STACK_AND_MODULES
 * STK-10; BR-CASH-13, BR-CASH-20; G-05): the Cash module never reads the Sales module, so an Action
 * or a page's controller reads the Sales module's query and passes its totals here. Their number and
 * value, the applied CASH and BANK_TRANSFER separately (BR-PAY-06), and the internal keys of the
 * Sales and Payments they come from, which the report keeps as its sources.
 */
final readonly class SessionSales
{
    /**
     * @param  list<int>  $saleIds
     * @param  list<int>  $paymentIds
     */
    public function __construct(
        public int $count,
        public Money $value,
        public Money $cashApplied,
        public Money $transferApplied,
        public array $saleIds,
        public array $paymentIds,
    ) {}

    /**
     * The totals as the Sales module's query gives them (App\Domain\Sales\Sales::ofSession()).
     *
     * @param  array{count: int, value: Money, cash: Money, transfer: Money, saleIds: list<int>, paymentIds: list<int>}  $totals
     */
    public static function of(array $totals): self
    {
        return new self($totals['count'], $totals['value'], $totals['cash'], $totals['transfer'], $totals['saleIds'], $totals['paymentIds']);
    }

    /**
     * The running figures of an OPEN session as its close preview and its detail page show them
     * (BR-CASH-13 step 1; COPY_DECK section 7.5): the opening cash, the number of Sales, the applied
     * CASH and BANK_TRANSFER, the total of the drawer-funded refunds paid from it (BR-RFD-08; TASK-034)
     * and expected cash (G-05), every amount a canonical string of whole rupiah. Never a cost (BR-CASH-20).
     *
     * @return array{openingCash: string, saleCount: int, saleValue: string, cashPayments: string, transferPayments: string, drawerRefunds: string, expectedCash: string}
     */
    public function figures(Money $openingCash, DrawerRefunds $refunds): array
    {
        return [
            'openingCash' => $openingCash->toCanonical(),
            'saleCount' => $this->count,
            'saleValue' => $this->value->toCanonical(),
            'cashPayments' => $this->cashApplied->toCanonical(),
            'transferPayments' => $this->transferApplied->toCanonical(),
            'drawerRefunds' => $refunds->total->toCanonical(),
            'expectedCash' => CashCount::due($openingCash, $this->cashApplied, $refunds->total)->toCanonical(),
        ];
    }
}
