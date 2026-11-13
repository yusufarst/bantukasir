<?php

namespace App\Actions\Sales;

use App\Domain\Catalog\GuardedProduct;
use App\Domain\Costing\Allocations\LinkedIssue;
use App\Domain\Costing\Allocations\ReturnAllocation;
use App\Domain\Inventory\StockPosition;
use App\Domain\Sales\Corrections\CorrectedLine;

/**
 * The RETURN_RECEIPT of one saleable return line as the correction computed it under its product's guard
 * before writing anything (ADR-008 sections 3 and 4.2): the line, its guarded product, the product's
 * position after the return — its stock and the movement's place in its sequence — the SALE_ISSUE
 * allocation it is linked to, and its return allocation with the valuation after it.
 */
final readonly class ReturnPosting
{
    public function __construct(
        public CorrectedLine $line,
        public GuardedProduct $product,
        public StockPosition $position,
        public LinkedIssue $issue,
        public ReturnAllocation $allocation,
    ) {}
}
