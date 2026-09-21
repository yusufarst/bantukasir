# 15 — HPP and gross profit

Owner-only management reporting, not accounting/tax advice or Laba Bersih. Core uses perpetual **moving weighted average (MWA)** per interchangeable product in one stock pool. No latest-purchase-price substitute, no unknown cost treated as zero.

## Source facts and arithmetic

Acquisition evidence links to opening/receipt/positive adjustment: total landed acquisition amount, quantity, reference, actor, status KNOWN or UNKNOWN. Explicit zero needs owner-confirmed evidence/reason. Operations may submit new evidence write-only under [04](04-AUTH-RBAC-SECURITY.md); historical reads/corrections remain owner-only.

For known Q units with inventory value V, receive q with total cost c:
Q' = Q + q; V' = V + c; average = V'/Q'.
Issue q: allocation = q × pre-issue V/Q; remove that value. Both SALE_ISSUE and non-sale issues reduce inventory value, but only SALE_ISSUE allocation contributes sales HPP. Final issue consuming all stock takes remaining V to avoid rounding residue. Decimal six-place cost arithmetic; aggregate first, round report display to IDR only. No binary float.

Example: 10 units costing Rp100,000 + 10 costing Rp140,000 → 20 units/Rp240,000 → average Rp12,000. Sell 3 at Rp20,000 → sales Rp60,000, HPP Rp36,000, gross Rp24,000; remaining 17/Rp204,000. Manual issue 2 removes Rp24,000 inventory value, not sales HPP.

All source/cost allocations are captured in the inventory/Sale transaction using product posting sequence. A missing-cost receipt marks valuation UNKNOWN and affected subsequent HPP incomplete. Historical already-known issue allocations remain valid. If stock reaches zero, later fully known receipts can start a new known pool; missing earlier periods remain incomplete.

## Correcting evidence without rewriting history

Owner appends evidence revision with reason. Under product guard, bounded deterministic replay from the affected source computes a new valuation revision and downstream allocation versions; original source and allocations remain retained. Publish the new revision/current projection atomically only after complete replay and reconciliation. Concurrent posting waits or safely rejects busy product. BK26 measures replay capacity (reference 10,000 movements per product); if timeout/cap would be exceeded, stop correction and mark affected reporting incomplete pending a reviewed recovery procedure, never partly publish or guess cost. No general asynchronous valuation platform in V1.

Standalone reversal replays the logical cancellation of the eligible source; immutable reversal retains exact physical inverse and linked evidence, including dependent cost revisions. Reject until safely replayable. Do not apply a naive latest average to cancel an old receipt.

## Sale and corrections

Fully paid immediate verified goods handover establishes completed sales value and matched SALE_ISSUE HPP together. Cash tender is not sales value; applied payment is. Refund without return subtracts linked refund amount from sales value, does not reverse HPP or add stock. Verified saleable return restores original matched issue cost and reverses that quantity of sales HPP; unknown original cost keeps return valuation incomplete. Refund/return are bounded separately to prevent duplicate cost recovery. Damaged return is not saleable and adds no usable stock.

Reporting uses gross completed sales, executed refunds separately and net sales = gross sales - refunds; net HPP = sale allocations - cost of verified saleable returns; gross profit = net sales - net HPP. Original Sale/receipt remain immutable. Refunds/returns appear at their server posting date, with original reference, no hidden backdated rewrite. Non-sale losses/variance are separate operational facts, so this is sales gross profit, never net profit.

## Periods and completeness

Asia/Jakarta server boundaries converted to UTC half-open ranges. Hari Ini = local date; 7 Hari = today plus prior six dates; Bulan Ini = first of current month through now; Bulan Lalu = full previous calendar month; Custom Range = inclusive selected dates implemented as exclusive next-day end.

Metrics: gross sales/refunds/net sales, completed transaction count, applied cash/transfer and payouts separately, qty sold/returned, HPP/gross profit and optional margin only if complete and net sales >0. LOW/OUT is current, explicitly not period-filtered.

Consistent report read snapshot includes asOf/timezone/evidence revision/completeness and unknown line count. Revenue/payment totals can display while HPP/gross/margin show **Data biaya belum lengkap**; do not exclude unknown-cost or loss-making rows to manufacture profit. Query failure is an error, not zero. Completed report valuation may change only through disclosed evidence revisions; CashSession reports and receipt snapshots never change.

[17](17-POS-SALES.md) session cash control has no private cost/profit. Owner daily recap derives financial totals from source facts and shows session variance separately; it never treats opening cash or variance as sales/profit and never sums session totals on top of the same Sales. No AR/AP, net profit, expenses, payroll, service finance or tax accounting in Core.
