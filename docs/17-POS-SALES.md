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

## Cashier shift

Each physical/logical register has at most one eligible active shift. Staff accounts are individual.

Shift lifecycle:
`OPEN → CLOSING → CLOSED`.

Opening records counted float. CashEvent types:
- OPENING_FLOAT
- PAYMENT_CASH
- REFUND_CASH
- PAID_IN
- PAID_OUT

Expected closing cash derives from these events.

Closing blocks new cash operations, resolves uncertain attempts, accepts a blind physical count, then stores expected/count/difference. Non-zero variance remains visible and reviewed; never auto-balance it.

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
