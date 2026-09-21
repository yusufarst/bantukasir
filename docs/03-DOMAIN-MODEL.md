# 03 — Conceptual domain model

Plan 1.1 specification, not migrations. One business/pool, interchangeable GOODS only.

| Entity | Contract / constraint |
| --- | --- |
| User / AuthSession | Three roles, individual actor; disable rather than delete referenced actor |
| BusinessProfileRevision | Immutable identity/logo/contact/header/footer/accent revision, current pointer |
| Product / Category | Unique normalized SKU, name/category/optional brand/base unit/precision/selling price/minimumStock/active/version |
| SellingPriceRevision | Amount, actor/time; SaleLine source; no cashier override |
| Barcode | Unique token across manufacturer/internal aliases; one product; never recycled |
| StockMovement / Line | Signed quantity/product/type/source/reason/actor/server time/per-product posting sequence; immutable |
| StockBalance | One row/product, nonnegative onHand, version; synchronous projection |
| AcquisitionCostEvidence | Receipt/opening/positive adjustment source, known amount or explicit unknown, reference; owner-only revisions |
| InventoryValuation / IssueCost | MWA projection and immutable/versioned issue allocation or UNKNOWN; evidence revision |
| Sale / SaleLine | Unique number/source intent, actor/cashier, required CashSession, UTC time + Jakarta businessDate; immutable item/unit/qty/price/total |
| Payment | Exactly one full payment per completed Sale; CASH or BANK_TRANSFER; cash tender/change |
| ReceiptSnapshot | Unique Sale, original number, identity/template revision, safe lines/payment; no private cost |
| Refund / Return | Original Sale/lines, bounded qty/amount, actor/reason, executed payout method/reference and optional return movement |
| CashRegister | Minimal logical physical drawer/counter identity: unique code/label, active flag; not a warehouse, HR roster or complex register engine |
| CashSession | Unique number, register, responsible cashier, OPEN/CLOSED, opened/closed timestamps, explicit nonnegative openingCash, activityVersion and close result; one OPEN session per register and per cashier |
| CashSessionReportSnapshot | Unique closed session; immutable identity/template, source IDs/version/cutoff, count/sales/cash/transfer/opening/expected/physical/variance and close actor/reason |
| CommandReceipt | Unique scoped key/fingerprint, source intent, durable result, recovery epoch |
| AuditEvent | Append-only actor/time/action/source/command/reason/safe changes; cost details owner-only |

Base-unit quantity precision 0–3 decimals requires G2 confirmation. Decimal arithmetic only. Selling prices/payable totals use integer IDR rupiah: round qty × unit price half-up once per line, then sum lines. Costs retain six decimal places with residual handling under [15](15-FINANCE-PROFITABILITY.md). Validate bounds/overflow.

Draft is an editable client envelope, not stock/payment truth. Successful checkout creates COMPLETED directly: conceptual DRAFT → COMPLETED. Refund/return totals derive from append-only corrections; no partially paid or deferred state, no overwriting completed facts.

DB constraints: SKU/barcode/source-intent uniqueness, one Payment/receipt per Sale, movement-source uniqueness, one CashSession per Sale, one report per CLOSED session, partial uniqueness for OPEN register/cashier sessions, nonnegative opening/physical cash and balance, and valid quantities/amounts. Locked aggregate checks enforce cumulative refund/return bounds. No Warehouse, StockLocation, Order, ServiceJob, Reservation, attendance Shift or payroll scheduling in V1. CashRegister/CashSession exist only for drawer accountability.
