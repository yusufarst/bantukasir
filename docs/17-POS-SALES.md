# 17 — Orders, POS, payments, fulfillment, services, receipts and shifts

Owns the commercial lifecycle. 06 owns physical inventory; 12 scanning; 15 financial recognition/costing; 04 permissions.

## Core principle

There is one **Order engine**.

Two user experiences sit on it:

1. **Penjualan Cepat** — minimal-field instant POS.
2. **Pesanan / Booking** — deferred order with customer, DP/partial payments, reservation, schedule, fulfillment and service progress.

Do not build separate commercial truth for POS versus booking.

## Order and line model

OrderLine snapshots GOODS/SERVICE type, SKU/name, unit, quantity, selling price revision, authorized discount/tax policy and net line amount.

GOODS may reference fulfillment source/location and serial identities later. SERVICE never has stock location/serial/balance.

Server owns arithmetic with exact decimal values; never JS floating-point money.

After confirmation, material commercial changes use an append-only OrderRevision/ChangeEvent. Historical fulfilled/payment/progress facts are never edited away.

A change cannot:
- reduce ordered quantity below already fulfilled quantity;
- invalidate an already returned/refunded bound;
- silently create overpayment;
- silently erase an already completed service obligation.

If a revision creates customer credit/overpayment, require an explicit authorized disposition.

## Status dimensions

Do not use one giant status.

Order lifecycle:
- DRAFT
- CONFIRMED
- CANCELLED
- COMPLETED

Payment:
- UNPAID
- PARTIALLY_PAID
- PAID
- PARTIALLY_REFUNDED
- REFUNDED

Goods:
- NONE
- UNFULFILLED
- PARTIAL
- FULFILLED

Service:
- NONE
- UNSCHEDULED
- SCHEDULED
- IN_PROGRESS
- COMPLETED

An order can validly be:
`CONFIRMED + PARTIALLY_PAID + GOODS_PARTIAL + SERVICE_IN_PROGRESS`.

Order becomes COMPLETED only when all effective non-cancelled goods/service obligations are resolved and outstanding payment is zero.

## Payments

PaymentRecord is append-only and idempotent. Supports:
- DP;
- partial payment;
- final settlement;
- full instant payment.

Store actor, server time, amount, method, optional reference, applied amount and cash tender/change where relevant. Never store card PAN/CVV.

Deferred payment amount cannot exceed current outstanding unless the same authorized flow explicitly handles the resulting refund/change.

Cash payment requires the actor's own eligible open CashierShift/Register.

Payment does not mutate inventory and does not automatically create revenue.

## Order confirmation / booking

Confirm command may atomically:
- freeze the reviewed commercial revision;
- create initial PaymentRecord if entered;
- create InventoryReservation for selected GOODS;
- create ServiceJob/schedule for applicable SERVICE;
- create document/payment evidence snapshot;
- audit and durable command result.

It does **not** pretend goods were handed over or service completed.

## Reservation

Reservation:
- reduces `available`;
- leaves `onHand` unchanged;
- belongs to order/order-line/location;
- is concurrency-safe;
- supports partial release/consumption;
- never exceeds eligible available quantity.

Amendment/cancellation releases only remaining unused reservation.

## Goods fulfillment

GoodsFulfillment is an idempotent verified handover command.

Within one DB transaction:
- validate order/revision/remaining quantity;
- lock in canonical order;
- consume reservation if applicable;
- post ledger ISSUE;
- update balance/serial/attention;
- append fulfillment, its recognized-revenue source fact, audit and durable result; valuation remains a recoverable financial projection.

Partial fulfillment is normal. Order 10 may fulfill 4 + 3 + 3. Payment state does not cause stock movement.

## Service jobs

A SERVICE line may create ServiceJob:
`BOOKED → SCHEDULED → IN_PROGRESS → COMPLETED`.

ServiceMilestone and ServiceProgressEvent are operational/audit facts. They may include planned dates, notes and responsible context.

Progress/milestones:
- do not create stock movement;
- do not imply payment;
- do not automatically recognize revenue.

Verified ServiceCompletion and its recognized-revenue source fact commit atomically with audit and the command result. This completion is the Core service fulfillment evidence used by finance; payment remains independent.

If physical company goods/materials are consumed for the job, use a separate authorized GOODS issue with a service-cost reference.

## Instant POS fast path

Normal cashier UX:
`scan/search → cart → Bayar → confirm → receipt`.

No customer/schedule/reservation fields are mandatory.

The fast-path command may atomically:
1. validate actor/open shift/cart/prices/stock;
2. create + confirm Order;
3. record full PaymentRecord and CashEvent if cash;
4. immediately fulfill GOODS and post ISSUE;
5. mark a SERVICE line complete only if it was actually performed immediately;
6. create RevenueEvent facts from actual fulfillment/completion;
7. persist receipt snapshot/audit/idempotent result;
8. commit once.

If a service is for future work, use **Pesanan / DP**, not a fake completed service.

Printer/network wait never occurs inside the DB transaction.

## Cashier shift and close report — D54

Each physical/logical register has at most one eligible active shift. Staff accounts are individual.

Shift lifecycle:
`OPEN → CLOSING → CLOSED`.

Opening records counted float. CashEvent types:
- OPENING_FLOAT
- PAYMENT_CASH
- REFUND_CASH
- PAID_IN
- PAID_OUT

This section owns reconciliation, attribution, immutable reporting and exports. [04](04-AUTH-RBAC-SECURITY.md) owns authorization; [15](15-FINANCE-PROFITABILITY.md) owns private profitability.

Reconciliation identity is **shift + register + cashier**, with location and server start/close timestamps, never calendar date alone. Two cashiers working 08:00–14:00 and 14:00–21:00 on 21 September have two independent reports. An overnight shift stays one shift. **Hari Ini** and **Riwayat Shift** are authorized convenience views over shift records; date filtering uses shift start date in Asia/Jakarta and never splits a report. Owner aggregation preserves individual shift identities.

Navigation: **Kasir → Shift Saya → Tutup Shift → Laporan Shift**. Keep this secondary to ordinary checkout. Use **Laporan Tutup Kasir**, **Ringkasan Shift** and **Rekonsiliasi Kas** for operational cash control, not an accounting/profit report.

### Close lifecycle and blind count

1. **Tutup Shift** establishes a durable CLOSING guard before physical counting. Serialize against writers using the canonical lock order in [02](02-ARCHITECTURE.md); do not hold a database transaction while the person counts.
2. Block new cash-affecting commands and new report activity attributed to this shift, including noncash payments and commercial confirmations/amendments. Resolve previously admitted uncertain commands by their original durable identity: include committed facts exactly once, confirm rejection/rollback, or keep closing pending. Commands already holding the guard finish before the cutoff; a command reaching it after CLOSING cannot append new facts. Status recovery of an already committed result remains allowed. Never assume failure or create a replacement payment.
3. After the source cutoff is stable and all uncertain outcomes are resolved, count physical cash, enter **Uang Tunai Fisik**, and confirm. A count taken before the cutoff is stable must be recounted before submission; never silently omit a racing event.
4. The server accepts the count for that cutoff, derives expected cash, compares and persists count/expected/variance, closes the shift and creates its immutable snapshot, audit and durable close result in one transaction. A unique close per shift plus idempotency prevents a second close even under different duplicate keys; conflicting payloads return the existing close or a conflict, never overwrite it.
5. Only after confirmed commit show **Kas Seharusnya**, **Uang Tunai Fisik**, **Selisih Kas** and **Laporan Shift**. Non-zero variance remains immutable; owner review/reason is linked separately, never auto-balanced.

Before count submission, cashier endpoints, DTOs, previews, print and exports must not reveal the server-derived expected cash aggregate or reconciliation result. Independently permitted own payment/event facts do not authorize a pre-count expected-cash report. Hiding a UI field is insufficient. Scanning is paused during counting/confirmation; scanner Enter cannot close the shift.

An unresolved command or interrupted close remains pending with a safe recovery action. Lost response/reload queries or retries the same close identity and returns the same durable snapshot. CLOSED never exists without its canonical snapshot. Render only after commit; printing/export failure never reopens, rolls back or duplicates the close.

### Three distinct report sections

**Ringkasan Penjualan** is commercial activity, not money received or recognized revenue:

- Identify shift number, cashier, register, location, start/close time and status.
- Count each Order confirmed in the shift once, with instant POS versus deferred order/booking counts and confirmed order value. Draft activity is not a completed transaction.
- Report amendments/cancellations handled in the shift separately as linked value deltas against identified revisions; do not count the full order again. Optional goods/service quantities are operational context.
- A later payment on an earlier order belongs to this shift's payments, not another full order value/count. Relevant outstanding is an as-of-close contextual snapshot for referenced orders, explicitly non-additive across shifts; never sum repeated outstanding snapshots as a daily total.
- Persist source shift attribution on each relevant confirmation/change/payment/refund at command commit, with actor/register/location context. Do not infer attribution from calendar date or current order owner. Operations outside a cashier shift remain outside that shift's report and are separately identifiable in owner views.

**Pembayaran Diterima** reports applied customer payments committed in the shift, grouped by configured method with snapshotted labels: **Tunai**, **QRIS**, **Transfer**, **Kartu** or other configured methods. Include DP, partial, final and full instant payments. Show gross received totals and executed refunds separately by method; any net figure is explicitly labeled. A Rp1,000,000 order paid Rp300,000 has Rp700,000 outstanding, not Rp1,000,000 received.

**Rekonsiliasi Kas** uses only actual cash-control facts:

```text
expected cash = opening float + applied cash customer payments + paid-in
                - executed cash refunds - paid-out
variance = physical count - expected cash
```

Cash tender minus change equals the applied cash amount; do not count tender and applied payment twice. Payment-linked CashEvents reconcile one-to-one to payment/refund facts without summing both sources twice. QRIS/transfer/card and other noncash methods never increase expected physical cash. A cancellation/credit without executed cash payout is not a cash refund.

Example: Rp500,000 float + Rp4,250,000 cash payments + Rp100,000 paid-in − Rp50,000 cash refunds − Rp200,000 paid-out = Rp4,600,000 expected. Rp4,590,000 physical gives **Selisih Kas −Rp10,000**. Float, paid-in/out and variance are not sales or profit.

### Immutable close snapshot

**ShiftCloseReportSnapshot** is the canonical as-closed document derived from CashierShift, CashEvent, PaymentRecord, Order/revisions and executed refunds/corrections. It is not a second payment, accounting or inventory source of truth.

Freeze enough allowlisted data to reproduce the as-closed report:

- report ID/number, unique shift/close identity, schema/template revision and immutable business identity/logo reference;
- cashier/register/location IDs and labels as closed, server start/cutoff/count/close timestamps with Asia/Jakarta presentation timezone, CLOSED status;
- source IDs/revisions and cutoff/watermarks identifying every included commercial/payment/cash fact;
- the three sections above, configured payment-method labels, operational transaction references, permitted notes, expected amount, physical count and variance.

Create closing facts, snapshot, audit and idempotency receipt atomically. R05.3 establishes snapshot schema and close-time creation; R05.4 adds document serializers/renderers/export over that contract. R05.3 does not wait for R05.4 and must never defer snapshot creation to a best-effort print job.

Later refund/reversal/correction preserves the original snapshot. Link current correction facts separately; show them as later context, never silently replace original totals. A later cash refund belongs to the authorized executor's then-open shift and does not reopen the original shift. Refund execution remains owner-only; report access grants no refund authority.

### Print, PDF, CSV and access

Core supports **Cetak**, browser **Simpan sebagai PDF**, **Ekspor CSV**, and authorized own **Riwayat Shift** reprint/re-export. Use browser HTML/CSS print-to-PDF and first-party UTF-8 CSV; no paid PDF SaaS. Output reuses original report identity, frozen facts, labels and template revision, with any copy/export metadata clearly separate. Determinism means the same business content, not identical PDF bytes across browsers.

All formats use explicit cashier-safe allowlists under [04](04-AUTH-RBAC-SECURITY.md). Recheck current actor/role/own-shift scope on read, history, export and reprint, including object/file access. No public file URLs, shared response caches or arbitrary report-ID access. CSV uses stable Indonesian headers, explicit IDR/timezone/section context, proper quoting/escaping and neutralizes spreadsheet formula/control prefixes in text fields. Keep numeric values numeric, including negative variance; minimize customer PII and never export hidden internal entities.

No acquisition/purchase cost, HPP, cost evidence, margin, gross/net profit, unrestricted company finance or security-audit administration data is included. Owner may inspect all authorized shifts and variance/status drill-down; ordinary staff report access stays own-shift only.

Print/export request audit is separate from financial mutations and records actor/report/format/time/result safely. Browser print-dialog completion does not prove printing. On PDF/CSV/printer failure, offer retry against the same snapshot; retain CLOSED and the original command result. Exports are not backups; preserve snapshots and referenced rendering assets through [09](09-DEPLOYMENT-OPS.md) recovery.

## Documents

Persist immutable snapshots sufficient to reproduce:
- order/receipt number;
- business identity revision;
- date/time/actor;
- customer when applicable;
- goods/service lines;
- quantity/unit/price/discount/tax treatment;
- payments and outstanding where appropriate;
- fulfillment/service status relevant to the document;
- original/refund references.

For instant paid retail, provide thermal **Struk Penjualan** and A4 paid-copy rendering of the same commercial facts.

For DP/partial payment, issue truthful **Bukti Pembayaran / Uang Muka** and order summary; do not label unpaid balance as lunas.

Formal tax/legal invoice behavior remains Q01 until confirmed.

Reprint uses original snapshot/number and adds a copy marker; never creates another transaction.

## Cancellation, refund and return

Draft cancellation creates no financial/stock fact.

Confirmed cancellation only affects remaining cancellable obligation. Performed facts stay.

Core refund execution is owner-authorized with recent authentication/reason.

Money refund and physical return are independent:
- refund without return → no stock movement;
- verified saleable goods return → linked RETURN RECEIPT;
- service refund → no stock movement;
- price concession → bounded revenue/payment correction only.

Cumulative refund/return cannot exceed eligible original quantities/values.

## Recovery and concurrency

Every critical command uses durable idempotency and status recovery.

Required behavior:
- duplicate submit returns same result;
- lost response freezes/retries original command;
- competing final-unit reservations/fulfillments cannot oversell;
- stale order revision rejects for review;
- shift close serializes against cash payment/refund;
- printer failure does not affect committed order;
- restore never assumes a missing receipt means transaction never occurred.

A stale browser must never generate a replacement payment/order/fulfillment while original outcome is uncertain.
