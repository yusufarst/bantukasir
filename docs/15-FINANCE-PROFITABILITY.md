# 15 — Inventory cost and profitability

Canonical costing, revenue matching, financial scope and completeness. This is a management-reporting design, not an implemented accounting ERP or a financial-statement compliance claim. [04](04-AUTH-RBAC-SECURITY.md) owns permissions, [06](06-INVENTORY-SPEC.md) physical inventory, [01](01-PRD.md) cockpit hierarchy.

## Stages and truthful labels

| Stage | Included | MUST NOT imply |
| --- | --- | --- |
| Core | Source references and owner-only versioned acquisition evidence/completeness; staff can post without prices | Implemented valuation, COGS, revenue or profit; no permanent empty finance cards |
| Sales P10 | Deal/quotation, fulfillment/acceptance, actual prices, discounts/returns, linked RevenueEvent facts | Quotation, WON, WhatsApp click or payment automatically equals revenue/profit |
| Initial finance P11 | Verified valuation basis, moving weighted average, allocations, revenue/returns and **Laba Kotor Penjualan Barang** | Net profit or whole-company profit when services/projects/unrecorded sales are excluded |
| Future accounting, outside this plan | Agreed expense/payroll/tax/interest/depreciation/journal/closing scope | **Laba Bersih** until supported and reconciled |

Never use an ambiguous **Laba** label alone. **Pendapatan Penjualan Terealisasi** means revenue recognized from verified delivery/control transfer, not cash collected. Missing values cannot be replaced with zero, catalog selling price, latest purchase price or guessed margin.

## Costing decision

Use **perpetual moving weighted average (MWA) per SKU across company locations** for interchangeable goods, including both QUANTITY and SERIALIZED items. Serial tracking alone does not make two otherwise interchangeable laptops economically unique. Actual serial acquisition cost is retained as evidence/purchasing trace, not automatically used as sale COGS.

| Option | Assessment | Decision |
| --- | --- | --- |
| MWA per SKU | One ordered pool; no layer choice during scanning; internal transfers do not change average | Selected for initial interchangeable goods |
| FIFO | Valid alternative, but adds receipt/return/layer complexity without a stated business need | Not selected initially |
| Actual-unit/specific identification | Useful for genuinely non-interchangeable/custom/project units | Future explicit eligibility/policy and project-cost design, not inferred from trackingMode |
| Latest purchase cost/manual margin | Simple but does not represent historical released inventory cost | Rejected as actual COGS |

[IAS 2](https://www.ifrs.org/issued-standards/list-of-standards/ias-2-inventories/) distinguishes specific identification for non-interchangeable items from FIFO/weighted average for interchangeable inventory and matches inventory expense with related revenue. LATANSA's MWA choice and limited scope are product decisions.

Custom manufacturing, assembly/BOM, installation services, consignment and staged project revenue are excluded from the first report. Define labor/overhead and recognition before including them. costingEligibility is separate from physical trackingMode. Lock policy after first valuation; changes require a recorded decision and controlled cutover.

## Cost evidence from Core

AcquisitionCostEvidence links a receipt/opening line or received serialized unit to IDR acquisition value, source document/reference, direct-cost components, tax treatment, completeness, enteredBy/verifiedBy and revision. Do not overwrite history through one product.purchasePrice field. A source uses either one verified line total or a complete set of unit allocations that reconciles to that total; never add both representations into valuation. Enforce one effective evidence revision per source and prevent overlapping quantity/value coverage.

States: MISSING → DRAFT → VERIFIED; correction adds a superseding revision. Blank is not zero. Zero requires verified source/reason and an appropriate basis; do not assume every free item has zero book value. Warehouse staff neither see cost amounts/files nor wait for their entry. Owner reviews a private receipt/completeness flow.

Basis: purchase amount after confirmed purchase discounts, plus nonrecoverable duties/taxes and direct costs necessary to reach intended location/condition. Exclude recoverable taxes under a verified policy. Customer delivery/operating costs are not automatically acquisition costs. No initial landed-cost allocation engine: owner supplies a reviewed final allocation per line, with source calculation reference.

For relevant components explicitly distinguish absent, included, allocated and unknown. Unknown makes valuation incomplete, never zero. Supplier invoices/terms remain private. Core can store references and verified values without invoice-upload capability. Any later bulk cost import requires a separate private format, not product/warehouse opening templates. Opening value comes from verified evidence, never selling price.

## MWA and precision

Warehouse pool Q/V per SKU includes company inventory in STORAGE and later internal TRANSIT/QUARANTINE. Issued goods awaiting control-transfer acceptance move into separate dispatch clearing, excluded from later warehouse-average denominators. Total still-owned carrying value is warehouse pool plus clearing. Neither is an editable physical-balance source.

```text
Receipt q with total verified cost c: Q' = Q + q; V' = V + c
Average unit cost:                    A = V / Q, only when Q > 0
Issue q:                              allocation = q × A
                                      Q' = Q - q; V' = V - allocation
Internal location transfer:           no change to global Q/V or average
```

Sales issue transfers quantity/value into clearing before acceptance. Acceptance releases the matching clearing amount to COGS alongside revenue; it does not subtract warehouse Q/V again. Internal consumption/loss is a separate non-sale cost event. Reservation changes neither value nor physical quantity, expense or revenue. Process physical source order, not worker completion order.

Physical precision remains numeric(18,3). Financial values/unit costs use exact six-place decimals, initial numeric(24,6), JSON strings. Invoice-total input supports two decimal places in IDR; unit cost may have six. Compute with exact/high-precision decimal division, then half-even round allocation to six places. Final quantity leaving the pool consumes all remaining V so Q=0 implies warehouse V=0. Sum stored amounts before UI currency formatting. Profit may be negative; incorrect arithmetic cannot create negative inventory value.

**Demo:** receive 10 at Rp100,000 and 10 at Rp140,000 → Q20, V Rp2,400,000, A Rp120,000. Issue/accept five with net revenue Rp900,000 → COGS Rp600,000, gross profit Rp300,000, margin 33.33%. Actual UI formats these in id-ID. Internal transfer changes neither average nor profit.

## Edge cases and correction

| Event | Required treatment |
| --- | --- |
| Finance starts after Core | Replay complete verified history, or establish a verified quantity/value financial cutoff. Never create a second physical OPENING. Earlier unverified report periods remain unavailable |
| Receipt cost unknown | Physical receipt remains valid; valuation stops at that source and downstream cost is PENDING. No silent use of old average |
| Customer physical return before credit | RETURN references original issue and restores historical returned allocation to the warehouse pool. Revenue/credit correction is separate; pending counterpart prevents a complete profit claim |
| Return before acceptance | Move historical quantity/value from dispatch clearing back to warehouse; no revenue/COGS reversal where neither was recognized |
| Refund without returned goods | Credit revenue as appropriate; no stock addition or automatic COGS reversal |
| Supplier return | Physical issue with supplier/source reference; remove current average carrying value. Difference from supplier credit is purchase variance, not fabricated sales profit |
| Loss/negative adjustment | Release current average value to non-sale loss, not sales COGS/revenue |
| Found/positive adjustment | Owner verifies valuation basis; unknown basis blocks costing. Do not silently assume the old average |
| Serial return | Reuse identity under 06 and original financial allocation, not a new item/latest purchase price |
| Administrative reversal | Process inverse at reversal sequence; keep original physical sequence. Outgoing inverse, e.g. receipt reversal, releases current carrying average; source-cancellation difference is variance. Incoming inverse restores original released allocation. Link separate recognized-revenue correction and prevent double COGS reversal. Transfer inverse remains net zero |
| Late evidence correction | Add revision; replay financial values from earliest affected source into a new run; publish report version atomically. Retain earlier report/export lineage |
| Zero warehouse stock | Warehouse Q/V are zero; pending clearing may remain. Next receipt starts a new average |

Partial returns cannot exceed unreturned original quantity/value. Allocate residual rounding to the final returned portion. Order discounts/credits allocate proportionally to pre-discount line value with deterministic residual on the final line ID; totals must equal header amounts. Unknown discounts, variable contracts or locked-period corrections need review/versioning, never guessed values.

For the demo, return and credit two accepted units: revenue correction −Rp360,000, COGS correction −Rp240,000 → net revenue Rp540,000, net COGS Rp360,000 and gross Rp180,000.

## Revenue and COGS matching

Initial goods revenue requires verified handover/acceptance establishing control transfer under the agreed deal terms. Issue reference and accepted quantity are mandatory; cumulative accepted allocations cannot exceed shipped eligible quantity after cancellations/pre-acceptance returns. Already accepted returns use linked correction events, not deletion of acceptance. Invoice, deposit, WON or ISSUE alone is insufficient.

The concept follows control transfer in [IFRS 15](https://www.ifrs.org/issued-standards/list-of-standards/ifrs-15-revenue-from-contracts-with-customers/). This product covers simple goods sales, not automated implementation of the full standard.

RevenueEvent contains fulfillment/acceptance or credit source, line allocation, amount net of discounts/returns, currency, actor/revision and recognizedAt. Exclude amounts collected on behalf of another party under a verified tax policy; no hardcoded tax rates. Partial acceptances have unique allocations. Cash receipts, if later added, are separate and do not move gross profit merely because a customer has not paid.

recordedAt is immutable server UTC. recognizedAt reflects the evidenced control-transfer/correction date, never a browser claim without validation. A late entry retains both dates and creates an auditable affected-period revision; it never backdates the physical ledger or silently changes a published report. Business periods use WIB.

Only accepted matched issue allocations become COGS. Query revenue and its COGS in the same recognition period. Unknown COGS makes the whole selected scope/period incomplete; do not quietly drop unknown-cost or loss-making orders.

```text
Revenue      = accepted net revenue + linked revenue corrections in period
COGS         = matched accepted allocations + linked COGS corrections in period
Gross profit = Revenue - COGS
Gross margin = Gross profit / Revenue × 100%, only when Revenue > 0
```

At zero/negative revenue, margin is **Tidak Berlaku**, not 0% or infinity. Negative results remain visible as a negative amount/**Rugi Kotor**. Label report coverage **Penjualan barang yang tercatat di LATANSA** and its period; never claim all business profit. Product/category/order drill-down uses the same allocations; category is snapshotted at recognition so later reclassification does not rewrite history.

## Valuation consistency and report completeness

Core physical posting records monotonic inventorySequence under the product guard, once per product/movement with lineNo ordering legs. Gaps are acceptable. Worker uses this order and evidence revisions, not browser timestamps, global document numbers or arrival order. Transfer has two legs but zero net economic movement.

Runs process each SKU in order with lease/cursor, unique run/source-line-or-allocation/eventKind and publication guard. Store scope/watermark, policy/evidence versions and replay reason. States: PENDING/BLOCKED/READY/PUBLISHED/SUPERSEDED. Publish an atomic pointer to completed run(s) for a consistent report snapshot; never mix old/new values. Published events/reports are immutable, with source lineage and revision differences.

If sources change during replay, keep the run's fixed watermark and expose unprocessed data. Completeness checks all relevant revenue, costs, returns and corrections, not only successfully processed records.

| Presentation state | Behavior |
| --- | --- |
| Not enabled | Hide dashboard finance section |
| Enabled, incomplete | **Data biaya belum lengkap**, pending count and owner-only drill-down; no misleading zero/complete subtotal |
| Complete | Actual amounts, scope, version and **Dihitung sampai …** |

True zero requires a complete scope with no qualifying activity. Default period **Bulan Ini**; alternatives **Hari Ini**, **7 Hari**, **Bulan Lalu**, **Rentang Tanggal**. Inclusive selected end date becomes exclusive next-day start. Comparisons need equal-length prior intervals and equivalent complete coverage/basis; no percentage change against zero. These filters never alter current low/out stock. Charts require a distinct decision question and adequate complete series.

Net profit needs sufficient reconciled expense/accounting scope, tax, relevant payroll/interest/depreciation/logistics, discounts/returns and period review/closing. Adding one **Biaya Lain** field does not justify **Laba Bersih**. Such implementation is outside this build plan and requires a separate decision.
