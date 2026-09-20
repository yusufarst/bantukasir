# 15 — Goods and service profitability

Canonical management-reporting semantics, evidence, costing and completeness. This is not a full accounting/financial-statement compliance implementation. [17](17-POS-SALES.md) owns sale/payment/return facts; [06](06-INVENTORY-SPEC.md) physical stock; [04](04-AUTH-RBAC-SECURITY.md) owner-only financial access.

## Core scope and labels

Owner confirmed completed/lun paid services and initial gross-profit reporting. Core includes actual net sales revenue, goods MWA/HPP, actual direct service costs, refunds/returns, gross results and period filters. Unknown costs never become zero. Cash balances/payment totals are separate from revenue; opening float and paid-in/out are neither revenue nor profit.

| UI metric | Definition |
| --- | --- |
| Penjualan Sebelum Diskon | Completed line base value before authorized selling discounts, excluding configured tax collected for others |
| Diskon / Pengembalian Dana | Explicit authorized discounts / linked credit amounts; do not subtract a discount twice |
| Pendapatan Bersih Barang / Jasa | Completed fulfilled net line revenue plus linked revenue credits for that type in period |
| HPP Barang | Matched goods issue cost less recognized historical-cost recovery for eligible sale returns |
| Laba Kotor Barang | Net goods revenue − matched goods HPP |
| Biaya Langsung Jasa | Verified actual direct costs for fulfilled service lines, net only of evidenced cost recoveries |
| Laba Kotor Jasa | Net service revenue − verified direct service costs |
| Laba Kotor Penjualan Tercatat | Goods gross + service gross, only when both selected scopes are complete |
| Margin Kotor | Combined gross / combined net revenue ×100%, only for positive net revenue |
| Data biaya belum lengkap | Missing evidence or unprocessed valuation; complete gross/margin unavailable |

Display scope, period, currency, included/excluded types and calculated-through watermark. A goods-only complete view may show its result while services are incomplete, but it cannot appear as total business profit. No hidden exclusion of missing-cost or loss-making sales. No ambiguous **Laba** alone. Negative gross remains negative/**Rugi Kotor**; zero/negative net revenue makes margin **Tidak Berlaku**.

## Goods policy

Perpetual moving weighted average per interchangeable GOODS SKU across company locations, both QUANTITY and SERIALIZED. Serial tracking is physical traceability, not automatically specific-identification costing. Actual serial acquisition evidence is retained. FIFO adds layer/return complexity without stated need; latest purchase price/manual margin is not historical COGS. Non-interchangeable/custom manufacture requires a separate policy before inclusion.

[IAS 2](https://www.ifrs.org/issued-standards/list-of-standards/ias-2-inventories/) identifies weighted average/FIFO for interchangeable inventory and specific identification for non-interchangeable items. MWA is this product's selected method, not a claim that the application implements all accounting standards.

AcquisitionCostEvidence links opening/receipt/positive adjustment source to total IDR value, source/reference, acquisition discounts, direct-cost components, tax treatment, completeness, actor/verification and immutable revisions. One effective revision/source; either line total or complete unit allocations reconciling to it, never both counted. Blank and missing differ from explicit verified zero. Owner alone enters/verifies; receipt can commit without it.

Owner supplies evidenced final allocations; no landed-cost automation. Nonrecoverable acquisition taxes/direct bringing-to-condition costs may belong in basis only under confirmed policy; recoverable tax is excluded where verified. Unknown tax/component treatment makes the cost incomplete. Supplier invoices remain private; references suffice initially, attachments later.

## Exact pool calculation

Q/V is the per-SKU on-hand company goods pool across locations. All sources process product inventorySequence (once per product/movement, lineNo within it), never worker completion order. Transfers change neither Q/V nor average.

```text
Receipt q, verified total c: Q' = Q + q; V' = V + c
Average A = V/Q when Q > 0
Issue q: allocation = q*A; Q' = Q-q; V' = V-allocation
Counter sale: match that issue allocation to fulfilled revenue in the same sale
```

Stock numeric(18,3); cost values/unit costs numeric(24,6), exact decimal strings/high-precision division, half-even allocation to six places. Final quantity out consumes residual V so Q=0 implies V=0. Sum stored values before presentation formatting. POS price/discount/tender rounding is separately fixed in 17. Overflow or unexplained negative pool value blocks valuation and raises incident, never silently clamps.

Demo: receive 10 × Rp100,000 and 10 × Rp140,000 → average Rp120,000; sell five for net Rp900,000 → HPP Rp600,000, gross Rp300,000, margin 33.33%. Internal transfers do not change it. Interchangeable serial units acquired for Rp10m/Rp12m use Rp11m average, retaining source evidence.

Unknown cost at a source blocks that SKU's downstream valuation. Do not continue using the old average. Physical sale remains valid with pending HPP. Process cost revisions by replay from earliest affected source into a new report version; physical history never changes.

## Services: minimum actual-cost evidence

ServiceCostEvidence belongs to a completed SERVICE sale line and fulfilled quantity. Record actual direct labor, subcontracting, job materials and other direct costs only with source/reference, amount/currency, allocation basis, explicit completeness and owner verification. Manual final totals per line with supporting reference are sufficient; no project ERP/timesheet/payroll engine is required. A tree-cutting service cannot inherit zero cost simply because it has no stock.

A component must be known included, separately allocated, not applicable or unknown. Explicit verified no-direct-cost declaration needs a reason/source; blank never means zero. Allocate shared invoice/job costs once across named sale lines with reconciled total; prevent overlap. Company-stock materials consumed for a service use a real non-sale goods issue and a unique service-cost allocation reference; their cost is reclassified into service direct costs, not simultaneously goods sales HPP and another manual material cost.

Service price is commercial revenue, not evidence of cost. Service refund does not automatically erase wages/material already incurred. Verified supplier/cost recovery adds linked evidence correction; otherwise original direct costs remain and gross result can be negative. Cost corrections are versioned and private.

Example: service revenue Rp500,000, verified labor Rp200,000 and materials Rp50,000 → gross Rp250,000. Missing labor makes service/combined profit incomplete. A Rp100,000 goodwill refund without cost recovery reduces service gross to Rp150,000 and never creates goods stock.

## Fulfillment, credits and financial dates

Core recognizes goods at attested immediate handover and services already completed, with full recorded payment under 17. Payment alone is insufficient for unperformed service; such sales are outside Core. This simplified boundary follows the transfer/satisfied-obligation concept in [IFRS 15](https://www.ifrs.org/issued-standards/list-of-standards/ifrs-15-revenue-from-contracts-with-customers/), not a local compliance conclusion.

Completed Sale creates immutable RevenueEvents and goods issue-allocation references transactionally. recordedAt and Core recognizedAt use server completion time. Core refunds/returns recognize adjustments at their current server posting time, not an edit to the original period. Late cost evidence may restate the historical matched cost via a clearly revised financial report; preserve old versions. Backdated sales, deposits, receivables, staged acceptance and formal closing are excluded.

Revenue excludes confirmed taxes collected for others; no hardcoded tax rate or assumed tax-exempt status. Before pilot owner validates policy with appropriate accounting advice; required tax/invoice behavior must be specified/tested before activation.

| Correction | Treatment |
| --- | --- |
| Goods return plus refund | Credit original discounted revenue and recover original returned cost; add goods to pool at that historical cost |
| Physical return before money refund | Record goods return and pending commercial counterpart; affected result incomplete until disposition reconciles |
| Refund without return / goodwill price credit | Reduce revenue; no added goods and no automatic HPP reversal |
| Service refund | Reduce service revenue; incurred cost persists unless explicit evidenced recovery |
| Supplier return | Remove current average carrying value; supplier credit difference is purchase variance, not sales profit |
| Loss / negative stock adjustment | Release average value to separate non-sale loss; excluded from sales gross, disclosed as excluded non-sale effects |
| Found / positive adjustment | Owner verifies value; missing basis blocks cost, no guessed old average |
| Standalone inventory reversal | At new sequence: incoming inverse restores original released allocation; outgoing inverse releases current carrying value with source-cancellation variance; no physical rewrite |
| Sale-linked stock correction | Must pass 17 commercial return/refund command; no separate reversal bypass |
| Zero goods pool | Consume rounding residual; next receipt starts new average |

Partial returns/credits have independent cumulative quantity/value caps under 17. Goods returned at original cost may alter current average; never use newest purchase price. Final returned portion absorbs residual rounding. Example above: return/refund two → revenue −Rp360,000, HPP −Rp240,000, net revenue Rp540,000, HPP Rp360,000, gross Rp180,000.

Later shipment-before-acceptance requires DispatchClearing: issue removes warehouse Q/V into clearing, acceptance releases to HPP without removing warehouse value twice; pre-acceptance return restores clearing value. Do not introduce clearing into normal completed counter-sale UX.

## Reports, periods and completeness

```text
NetRevenue = completed net fulfilled revenue + signed credits in period
GoodsGross = NetGoodsRevenue − MatchedGoodsCOGS
ServiceGross = NetServiceRevenue − VerifiedDirectServiceCosts
CombinedGross = GoodsGross + ServiceGross
```

WIB boundaries converted server-side to UTC, start inclusive/end exclusive:
- **Hari Ini**: today midnight through next midnight.
- **7 Hari**: today and previous six calendar days, through next midnight.
- **Bulan Ini**: first of this month through first of next month (only committed data exists).
- **Bulan Lalu**: first of prior month through first of current month.
- **Rentang Tanggal**: chosen start midnight through day after chosen end.

Include snapshot/current partial-day indication; future data is never fabricated. Owner default Bulan Ini. Equal-length comparisons require equally complete coverage; no percentage comparison against zero. Current stock attention stays current regardless of filter.

ValuationRun fixes source watermark/policy/evidence revisions, ordered cursor and unique run/source/event. PENDING/BLOCKED/READY/PUBLISHED/SUPERSEDED states; publish one consistent pointer atomically, not mixed runs. Report includes goods/service coverage counts, missing evidence, unresolved return/refund counterparts, unprocessed sources, versions and calculatedAt. New sales after watermark are visibly pending, not quietly omitted while claiming complete current totals.

Revenue can be displayed from complete sale facts while HPP/profit are pending, explicitly separated. A valid empty complete period yields zero revenue/cost/gross and not-applicable margin. Failed/stale query is never zero. Each drill-down/export uses same scope/allocations and owner authorization. Category/type/unit snapshot prevents later master edits rewriting historical grouping.

## Net profit and scope limits

Net profit is outside the accepted initial scope. It would require reconciled operating expenses, payroll, rent/utilities, depreciation, financing/interest, tax, non-sale inventory losses/variances, liabilities/accruals, accounting periods/journals and closing policy. One miscellaneous expense field or drawer cash variance does not justify **Laba Bersih**.

Published gross reports explicitly exclude those costs. Owner reports may flag non-sale losses/shift variance separately without subtracting arbitrary cash movements from gross profit. If net profit becomes required, document and approve a new accounting scope first.

