<?php

namespace App\Domain\Cash;

use App\Support\Numbers\Money;

/**
 * The drawer-funded refunds paid from a cash session, as its close, its report and its pages receive them
 * (STACK_AND_MODULES STK-10; SALES_CASH BR-CASH-07, BR-CASH-12, BR-CASH-20, BR-RFD-08; SCHEMA G-05 and
 * section 5.10, `drawer_refunds`; DOCUMENT_CONTRACTS DC-02 row 8; D-035, D-068; TASK-034): the Cash module
 * never reads the Sales module, so an Action or a page's controller reads the Sales module's query
 * (App\Domain\Sales\Corrections\SaleCorrections::drawerRefundsOf()) and passes them here. Their total,
 * which expected cash takes from the drawer; each refund separately as the report freezes it, in the order
 * they were recorded — its Sale's number, its payout's reference, its amount, its time as an instant in
 * UTC and the Owner's frozen name; and their internal keys, which the report keeps among its sources.
 */
final readonly class DrawerRefunds
{
    /**
     * @param  list<array{saleNumber: string, reference: string, amount: string, recordedAt: string, owner: string}>  $entries
     * @param  list<int>  $ids
     */
    private function __construct(
        public Money $total,
        public array $entries,
        public array $ids,
    ) {}

    /**
     * The refunds as the Sales module's query gives them.
     *
     * @param  list<array{id: int, saleNumber: string, reference: string, amount: Money, recordedAt: string, owner: string}>  $refunds
     */
    public static function of(array $refunds): self
    {
        $total = Money::zero();
        $entries = [];
        $ids = [];

        foreach ($refunds as $refund) {
            $total = $total->plus($refund['amount']);
            $entries[] = ['saleNumber' => $refund['saleNumber'], 'reference' => $refund['reference'], 'amount' => $refund['amount']->toCanonical(), 'recordedAt' => $refund['recordedAt'], 'owner' => $refund['owner']];
            $ids[] = $refund['id'];
        }

        return new self($total, $entries, $ids);
    }

    /**
     * The rows of a close preview (UXI-101, UXI-149; COPY_DECK `cash.close.drawer_refund_row`): each
     * refund's amount, its Sale's number and its time, the reminder of D-068 that its cash should no longer
     * be in the drawer standing above them.
     *
     * @return list<array{saleNumber: string, amount: string, recordedAt: string}>
     */
    public function previewRows(): array
    {
        return array_map(static fn (array $entry): array => ['saleNumber' => $entry['saleNumber'], 'amount' => $entry['amount'], 'recordedAt' => $entry['recordedAt']], $this->entries);
    }
}
