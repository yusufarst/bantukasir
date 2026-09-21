# 03 — Conceptual domain model

Business contracts, not migrations. **M = Core**, **L = later**. One business per deployment.

## Identity, business and catalog

| Entity | Core contract |
| --- | --- |
| User / Role / AuthSession (M) | Individual actor; SUPER_ADMIN, OPERATIONS_ADMIN, CASHIER; historical actor retained |
| BusinessProfileRevision (M) | Versioned business name/logo/contact/document/accent configuration |
| BusinessPolicyRevision (M) | IDR/WIB, payment methods, receipt/tax and operational policy |
| Product (M) | Unique SKU, `commercialType = GOODS \| SERVICE`, name/category/unit/precision, active/version |
| GoodsProfile (M) | GOODS only: QUANTITY/SERIALIZED, stock monitoring, minimum/reorder target, costing eligibility |
| SellingPriceRevision (M) | Product, amount, effective server time, actor/reason |
| Barcode (M) | GOODS product/item identity; globally unique token; multiple aliases; never reused |
| Customer (M) | Minimal reusable identity/contact for deferred orders; optional for instant POS |

SERVICE has no GoodsProfile, StockBalance, serial position, stock threshold or stock movement.

## Inventory

| Entity | Core contract |
| --- | --- |
| Warehouse / StockLocation (M) | Physical storage identity |
| StockMovement / StockMovementLine (M) | Immutable posted ledger with actor/time/source/reason and quantity/serial legs |
| StockBalance (M) | Projection per goods/location: onHand, reserved, version |
| InventoryReservation (M) | Order-line/location hold with remaining quantity; partial release/consume; auditable |
| SerializedItem (M) | Internal identity, optional manufacturer serial, lifecycle/position |
| CommandReceipt (M) | Durable idempotent result/fingerprint |

`available = onHand - reserved`.

Reservation is not a StockMovement. Fulfillment consumes reservation and posts ISSUE atomically.

## Commercial aggregate

| Entity | Core contract |
| --- | --- |
| Order (M) | Server ID/number, optional customer, lifecycle, current revision, totals, timestamps |
| OrderLine (M) | GOODS/SERVICE snapshot: SKU/name/type/unit/qty/price/discount/tax-policy snapshot |
| OrderRevision / ChangeEvent (M) | Append-only amendments after confirmation; never rewrite fulfilled/refunded facts |
| PaymentRecord (M) | Append-only amount/method/reference/actor/time; DP/partial/final |
| PaymentRefund (M) | Linked negative payment fact with authorization/execution evidence |
| OrderDocumentSnapshot (M) | Immutable order/payment/receipt representation and BusinessProfile/template revision |
| CommercialRefund / Return (M) | Bounded credit/refund and optional linked physical return |

Instant POS creates this same Order through a fast path.

## Goods fulfillment

| Entity | Core contract |
| --- | --- |
| GoodsFulfillment (M) | Order/source location, actor/time, command identity |
| GoodsFulfillmentLine (M) | OrderLine quantity, reservation consumed, serials, linked ISSUE legs |

Cumulative fulfillment cannot exceed effective ordered quantity. Partial fulfillment is first-class.

## Service work

| Entity | Core contract |
| --- | --- |
| ServiceJob (M) | Service-order-line job, booking/schedule, operational lifecycle |
| ServiceMilestone (M) | Ordered milestone label/state, optional planned date/notes |
| ServiceProgressEvent (M) | Append-only progress/status/note with actor/time |
| ServiceCompletion (M) | Verified completion/handover fact; no stock movement |
| ServiceCostEvidence (M) | Verified direct labor/subcontract/material/other cost evidence |

Milestone progress does not itself imply payment, revenue or stock movement.

## POS and cash

| Entity | Core contract |
| --- | --- |
| Register (M) | Logical cash drawer/counter |
| CashierShift (M) | User/register OPEN → CLOSING → CLOSED |
| CashEvent (M) | OPENING_FLOAT, PAYMENT_CASH, REFUND_CASH, PAID_IN, PAID_OUT |
| ShiftCount / ShiftClose (M) | Stable cutoff, blind count, expected amount, immutable variance and durable unique close; later review/reason linked separately |
| ShiftCloseReportSnapshot (M) | Unique close/shift document; frozen identity/template, source revisions/cutoff, commercial activity, method payments and cash reconciliation; created atomically with close; no private finance |

Any role executing cash payment uses their own eligible open shift/register. Commercial confirmations/changes, payments (including noncash) and refunds handled within a shift preserve source shift/actor/register/location attribution at commit. Activity outside a shift is explicitly separate, never inferred from date. [17](17-POS-SALES.md) owns attribution and snapshot content; later corrections link new facts without rewriting a closed report.

## Derived status dimensions

Order lifecycle: DRAFT / CONFIRMED / CANCELLED / COMPLETED

Payment: UNPAID / PARTIALLY_PAID / PAID / PARTIALLY_REFUNDED / REFUNDED

Goods: NONE / UNFULFILLED / PARTIAL / FULFILLED

Service: NONE / UNSCHEDULED / SCHEDULED / IN_PROGRESS / COMPLETED

Order COMPLETED requires all non-cancelled obligations resolved and no outstanding amount. Fully delivered but unpaid work remains commercially open.

## Finance and attention

| Entity | Core contract |
| --- | --- |
| AcquisitionCostEvidence | Versioned GOODS receipt/opening cost evidence |
| RevenueEvent | Fulfilled goods or completed service value/correction; never created merely because DP arrived |
| CostEvent / CostAllocation / ValuationRun | Goods MWA and matched HPP; immutable/versioned |
| FinancialReportSnapshot | Period scope, completeness, watermarks and owner-only result |
| StockHealth / AttentionEpisode | GOODS only NORMAL/LOW/OUT with deduplicated episode |
| AuditEvent | Append-only safe audit metadata |

## Later domains

Full purchasing/AP, advanced opname/transit/QC/warranty/repair custody, public CMS/catalog/RFQ/leads/quotations, payment gateways, multi-company and full accounting remain later.
