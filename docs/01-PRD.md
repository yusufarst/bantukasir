# 01 — Product requirements

Owns business outcomes, Core scope and UX priorities.

## Positioning

A reusable, brand-neutral retail operations platform for office supplies, general merchandise and separately sold services. One company per deployment. LATANSA may be the first configured business, not the product identity.

Simple transactions must feel like a fast convenience-store checkout. The same system must also handle DP, booking, partial payments, stock reservation, staged goods handover and service progress without forcing those fields into every sale.

## Users

| Role | Primary outcome |
| --- | --- |
| SUPER_ADMIN / Pemilik | Understand the business, control sensitive actions, see private finance and manage configuration/recovery |
| OPERATIONS_ADMIN / Admin Operasional | Keep goods/services, stock, bookings, fulfillment, jobs and restock work moving |
| CASHIER / Kasir | Sell quickly, accept payments, create simple bookings/orders and issue receipts without inventory/accounting complexity |

Roles describe work, not family relationship. 04 owns permissions.

## Core

1. Individual accounts, backend RBAC, owner TOTP, session revocation and audit.
2. Runtime BusinessProfile for configurable business name/logo/contact/document identity/accent.
3. One Product Master with GOODS and SERVICE; goods support QUANTITY/SERIALIZED, services never become fake stock.
4. Selling-price revisions, categories, units, customer basics and server search/pagination.
5. Manufacturer barcode aliases plus generated internal barcodes/labels; HID/manual scanning.
6. Create-only product import and separate ledger-based opening stock import.
7. Immutable goods ledger: receiving, non-sale issue, direct transfer, reservation, fulfillment, adjustment/reversal and history.
8. LOW/OUT/NORMAL attention with deduplicated episodes and owner/operations inbox.
9. Unified Order engine for instant POS and deferred orders/bookings.
10. Append-only payments supporting unpaid, DP/partial and paid; optional due date; no mandatory gateway.
11. Goods reservation and partial fulfillment. Payment never implies stock issue.
12. Service jobs with schedule, milestones/progress and completion; no stock mutation.
13. Mixed goods + service orders.
14. Fast POS as a minimal-field path: scan/search → cart → pay → fulfill → receipt.
15. Cashier shifts/opening float/closing count/variance and cash events.
16. Automatic receipt/payment evidence and A4 representation; reprint without duplicate transaction.
17. Owner-authorized commercial refund/return; immutable original history.
18. Period reporting: Hari Ini, 7 Hari, Bulan Ini, Bulan Lalu, Rentang Tanggal.
19. Reporting separates order value, payments collected, outstanding balance, recognized revenue, HPP/direct service cost and gross profit.
20. Backup/restore, health, controlled pilot and production deployment on the client's VPS.

## UX success

### Kasir
Default screen is **Kasir**. Normal retail sale requires no customer, schedule or reservation form.

Primary path: `scan/search → quantity → bayar → selesai/cetak`.

Secondary **Pesanan / DP** reveals only customer, payment amount, schedule/due date, reservation and service-booking fields that are actually needed.

### Admin Operasional
Default workspace prioritizes:
- pesanan perlu disiapkan;
- fulfillment/pickup due;
- pekerjaan jasa hari ini/terlambat;
- stok rendah/habis;
- barang masuk;
- operational exceptions.

### Pemilik
Default dashboard prioritizes:
- critical exceptions;
- stock LOW/OUT;
- active/late orders/jobs;
- payment received/outstanding;
- cashier variance;
- recognized revenue/HPP/gross profit with completeness.

No decorative KPI duplication or fake charts.

## Independent status dimensions

An order may be `CONFIRMED + PARTIALLY_PAID + GOODS_PARTIAL + SERVICE_IN_PROGRESS`.

- Order: DRAFT / CONFIRMED / CANCELLED / COMPLETED
- Payment: UNPAID / PARTIALLY_PAID / PAID / PARTIALLY_REFUNDED / REFUNDED
- Goods: NONE / UNFULFILLED / PARTIAL / FULFILLED
- Service: NONE / UNSCHEDULED / SCHEDULED / IN_PROGRESS / COMPLETED

03/17 own exact derivation.

## Acceptance

| ID | Evidence |
| --- | --- |
| B01 | Cashier completes normal scan-to-receipt sale without owner credentials or duplicate inventory entry |
| B02 | Booking with DP leaves onHand unchanged and may reserve availability |
| B03 | 10 reserved goods can fulfill 4+3+3 with exactly 10 total stock OUT |
| B04 | Two users competing for final available stock cannot oversell |
| B05 | Mixed goods/service order posts stock only for fulfilled GOODS |
| B06 | Service progress/milestones never create stock movement |
| B07 | Multiple payments preserve prior records and derive correct outstanding balance |
| B08 | Duplicate/lost-response commands recover the original result |
| B09 | Printer failure/reprint never duplicates order/payment/stock |
| B10 | LOW/OUT notifications deduplicate until recovery |
| B11 | DP/cash collected is not automatically recognized revenue |
| B12 | Missing cost produces incomplete gross-profit state, never zero |
| B13 | Staff/public payloads never expose owner-only cost/margin/profit |
| B14 | Restore reconciles orders, payments, reservations, ledger, jobs, receipts and shifts |
| B15 | Cashier/operations flows pass keyboard/mobile progressive-disclosure review |

## Later unless explicitly promoted

Supplier PO/AP automation; full accounting/net profit; payment gateway settlement; complex promotions/loyalty/gift cards; pack conversion/batch/expiry/consignment; staged warehouse transit/full opname; QC/warranty/repair custody; camera scanning/offline mutation; public website/catalog/RFQ/leads/quotations; multi-company/multi-currency.

Formal tax/legal invoice requirements remain a pre-pilot policy gate.
