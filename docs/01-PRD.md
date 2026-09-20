# 01 — Retail operations product requirements

Owns business outcomes, Core scope and dashboard hierarchy. [10](10-DECISIONS.md) records the gap analysis, supersessions and unresolved owner policies.

## Positioning and success

A reusable, brand-neutral retail operations application for office supplies, general merchandise and separately sold services. One company per deployment; LATANSA may be the first configured business. The convenience-store comparison describes scan-to-checkout speed, not another retailer's brand or interface.

Success means operators can receive goods, sell a mixed goods/service basket, record payment and automatically produce a receipt and the correct goods issue without duplicate entry; the owner can trace every transaction, act on stock attention and inspect truthful period gross profit.

| Role | Outcome |
| --- | --- |
| SUPER_ADMIN / Pemilik | Business control, private cost/profit, exceptions, corrections, accounts/configuration and recovery oversight |
| OPERATIONS_ADMIN / Admin Operasional | Maintain goods/services and selling prices, register/print codes, receive/issue/transfer goods, monitor stock and restock attention |
| CASHIER / Kasir | Fast scan/search, basket, payment recording, receipt and own sale history, without stock adjustment or finance access |

[04](04-AUTH-RBAC-SECURITY.md) owns actual permissions. Roles describe work, not family relationship or account count.

## Core

1. Individual authentication, backend RBAC, owner TOTP and audit from inception.
2. Configurable business identity and receipt header/contact/footer; neutral visual system and Indonesian UI.
3. One goods/service master, exact base units, selling-price revisions, goods QUANTITY/SERIALIZED tracking, category/brand/location and server search for thousands of SKUs.
4. Manufacturer aliases plus generated internal barcodes and browser labels; HID/manual scanning into drafts.
5. Create-only master import and separate goods quantity/serial opening with freeze and atomic apply.
6. Immutable goods ledger, receipts, non-sale issues, direct transfers, owner adjustments/reversals and cost evidence.
7. POS mixed baskets, authoritative pricing, full payment recording, automatic immutable retail receipt/A4 copy and atomic goods issue. Baseline assumes immediate goods handover and completed services; Q01–Q03 must be resolved before its visual contract.
8. Owner-authorized linked partial refunds/verified sale returns, including refund without restocking; immutable original history.
9. Goods-only LOW/OUT/NORMAL attention, owner/operations inbox, opt-in Web Push and useful restock list.
10. Owner period sales, goods COGS/gross profit and service revenue/direct-cost gross result with completeness controls; operations receives safe operational totals only.
11. Desktop cashier keyboard flow, responsive mobile operations, five visual bundles and both owner review gates.
12. Least-privilege deployment, encrypted off-host backups, measured restore, devices/printing validation and controlled cutover/pilot.

Core financial capability must exist before pilot. Incomplete evidence may block complete profit values, never be shown as zero or used to block lawful receipt solely for missing cost.

## Explicit boundaries

| Capability | Disposition and reason |
| --- | --- |
| Purchasing | Receiving source/reference and optional restock target in Core; POs, supplier debt, landed-cost automation later |
| Payment | Cash recorded first; verified manual non-cash only after method policy; gateways, split tenders, deposits, credit and settlement reconciliation excluded from baseline |
| Services | Completed work sold at checkout; no fake stock. Scheduling, estimates, milestones, project/job costing and repair custody later |
| Cash drawer/session | Per-user sale/payment summaries Core; opening float/count variance/shift close provisional later pending Q03 |
| Discounts | Cashier uses posted prices; owner reasoned line price reduction only. Promotions/coupons/order discount engine later |
| Returns | Owner-only bounded sale-linked refunds and saleable physical returns Core; damaged-return custody/QC requires later quarantine capability before use |
| Tax/legal invoices | No rates or compliance assumptions. Q01 determines required scope before affected execution; retail receipt is not a tax invoice |
| Net profit/accounting | Outside baseline: expenses, payroll, depreciation, interest, tax, liabilities, journals and closing need a separate decision |
| Public company/catalog/CMS | Later; structured DB content and published allowlist, no public checkout in Core |
| RFQ/leads/quotations/reservations | Later commercial extension; not prerequisite to counter sales |
| Opname/transit/general approvals | Later; Core has opening freeze, count-based owner correction and direct transfer |
| QC/warranty/repair service | Later; separate from selling a SERVICE line |
| Camera/offline | HID/manual and online writes Core; camera later; no offline financial/stock mutations |
| Multi-company/currency | One configured business, IDR/WIB; no SaaS tenancy, currency conversion or pack conversion |

If Q01–Q03 require excluded behavior, revise the affected scope and plan before implementing it.

## Business acceptance

| ID | Required evidence |
| --- | --- |
| B01 | Staff performs authorized routine receipt or sale without owner credentials |
| B02 | Every goods change traces to immutable ledger, actor/time/product/location/reason/source |
| B03 | Two cashiers competing for the last unit yield exactly one completed sale |
| B04 | Repeated quantity scans add one each; serial scan deduplicates; scans never commit |
| B05 | Mixed sale creates only GOODS legs; service-only sale creates none |
| B06 | Duplicate submit/lost response recovers the same sale/payment/receipt number |
| B07 | Printer failure/reprint never creates another sale or movement |
| B08 | Refund, return and correction retain original facts and enforce cumulative limits |
| B09 | Minimum 5: 6→5 alerts once, 5→4 does not spam, 20 resolves, next 5 opens a new episode |
| B10 | Period reports expose missing goods/service costs; no net-profit or guessed zero |
| B11 | Staff/public payloads and exports contain no acquisition cost/COGS/profit |
| B12 | Restore reconciles sale, payment, receipt, stock, costs and attention; hardware and visual gates pass |
| B13 | 5,000-row validation/atomic import and useful server pagination work within measured limits |

[08](08-TESTING-ACCEPTANCE.md) owns test IDs and targets; none is a measured production result yet.

## Role workspaces and owner dashboard

Cashiers land on **Kasir**; operations on **Stok & Restok**; owner on **Dasbor**. Show only implemented/authorized navigation. Catalog, stock history and receipt details use drill-down rather than repeated shortcuts.

Owner priority: critical integrity/recovery exceptions → current OUT/LOW attention → compact selected-period sales/gross result/completeness → receipt/non-sale issue activity → recent meaningful documents. No welcome hero, decorative chart or duplicate metric cards. Operations sees current attention and operational documents without cost/profit. Cashier sees basket, total, payment and recoverable status.

One **Periode** selector: **Hari Ini**, **7 Hari**, **Bulan Ini** (owner default), **Bulan Lalu**, **Rentang Tanggal**. [15](15-FINANCE-PROFITABILITY.md) owns exact date and financial definitions. Current stock attention never changes with a historical period. Sales count comes from Sale, never by counting all ISSUE movements; show refunds separately. Receipt/non-sale issue counts exclude opening, transfer and reversal, with original/correction links.

Stock rows show goods SKU/name, quantity/unit/minimum, location and episode age; OUT before LOW, then severity ratio, oldest episode, SKU. No mixed-unit sum. Reading attention is not recovery. Restock target suggests max(target − eligible, 0), not automatic procurement; without target show the shortage and require operational judgment.

Distinguish loading, empty, incomplete, stale, denied and failed queries. Zero requires a successful complete query. Prototypes show **Data Demo**. Production contains no fabricated charts, finance or company claims.
