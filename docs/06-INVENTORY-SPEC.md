# 06 — Inventory specification

One business-wide stock pool, no Warehouse/StockLocation. Immutable StockMovement is physical truth. StockBalance is synchronously updated in the same transaction; onHand equals signed ledger sum. No reserved quantity in V1. Negative stock prohibited.

## Commands

| Type | Physical effect / required evidence |
| --- | --- |
| OPENING | Owner verified initial positive count, only product without prior ledger; cost evidence or UNKNOWN |
| RECEIPT | Positive qty, acquisition cost evidence or UNKNOWN, optional source/reference |
| SALE_ISSUE | Negative qty, exclusively from atomic completed Sale |
| MANUAL_ISSUE | Negative qty; damaged/internal use/lost/sample/other, mandatory reason; never customer sale substitute |
| ADJUSTMENT | Owner signed verified correction with reason/count/reference, cost evidence for positive qty |
| REVERSAL | Owner linked inverse of eligible standalone opening/receipt/manual issue; original retained |
| RETURN_RECEIPT | Verified saleable physical return linked to original SaleLine/issue, bounded qty, historical cost recovery |

Receiving: scan/search → existing product or authorized search-first creation → qty + cost evidence → review → confirm. New product does not create stock. Operations cost submission is write-only under [04](04-AUTH-RBAC-SECURITY.md). Missing cost is explicit UNKNOWN, not zero.

Every command validates active product, unit precision, qty/range, actor, reason, source eligibility and versions under ordered product locks. Aggregate duplicate product lines before stock checks. Append movement/cost facts, update balance, audit and durable result atomically. Concurrency on last unit yields one success and one insufficient-stock rejection. No historical backdating; keep optional document date separately from UTC server posting time/sequence.

One full reversal per eligible original; prohibit direct reversal of SALE_ISSUE/RETURN_RECEIPT, use commercial correction. Reject reversal if it makes stock negative or dependent valuation cannot be safely recomputed under [15](15-FINANCE-PROFITABILITY.md). Do not silently substitute latest cost. Adjustment is a separately evidenced physical correction, never a way to erase the original.

Opening is manual bounded entry first, under [14](14-BULK-IMPORT.md). G2 verifies unit/barcode/cost/serial assumptions before real data; unsupported serial/pack/batch requirements stop affected onboarding. No transfers, reservations, advanced opname or serialized identity.

Read stock/history server-side with pagination. Operations history omits private cost; owner drill-down includes authorized evidence. Daily scheduled read-only reconciliation compares ledger and balance/cost projections; mismatch blocks affected product posting and raises owner incident. Repair requires controlled replay/rebuild from immutable facts and audit, never direct business balance editing. Lost response queries/retries original command identity.
