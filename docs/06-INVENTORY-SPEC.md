# 06 — Inventory specification

Owns physical GOODS truth. SERVICES are excluded from physical stock.

## Quantity truth

StockMovement ledger is source of truth. StockBalance is a synchronous transactional projection.

`available = onHand - reserved`

For stock attention, `eligible(p)` is the sum of available quantity for goods product p across active, issue-eligible STORAGE locations. Reservation changes this aggregate without changing physical onHand; 13 owns its LOW/OUT evaluation.

Constraints:
- onHand cannot go negative where prohibited;
- reserved cannot be negative or exceed eligible onHand;
- only inventory command services write balances/reservation projections;
- no direct balance edit endpoint;
- no destructive ledger deletion.

## Core movement types

OPENING, RECEIPT, ISSUE, order/FULFILLMENT ISSUE, verified saleable RETURN RECEIPT, TRANSFER, ADJUSTMENT and eligible standalone REVERSAL.

Commercial refund does not automatically imply physical return.

## Receiving

Operations chooses destination/source/reference, scans/searches GOODS, reviews a draft and confirms once. Manufacturer barcode may resolve existing product. Unknown code never auto-creates product/stock. Missing acquisition cost does not block lawful receipt.

Serialized receipt validates identity and prevents duplicates.

## Reservation — Core

Reservation is a commercial hold, not physical movement.

Order confirmation/change may reserve goods. Reservation:
- reduces available;
- leaves onHand unchanged;
- links order/order-line/location;
- supports partial release/consumption;
- is concurrency-safe;
- cannot exceed current eligible available quantity.

Cancellation/amendment releases only remaining unneeded reservation.

## Fulfillment — Core

Verified handover creates order-linked ISSUE.

Fulfillment command:
1. claims idempotency;
2. validates order/revision and remaining quantity;
3. locks commercial/inventory records in canonical order;
4. consumes reservation when applicable;
5. posts immutable ISSUE;
6. updates balance/serial/health;
7. appends audit/result;
8. commits once.

An order for 10 may fulfill 4 + 3 + 3. Cumulative fulfillment cannot exceed effective ordered quantity.

Payment state does not physically change quantity. Receiving money never posts stock.

## Transfer/non-sale issue/corrections

Transfer posts paired legs atomically. Non-sale issue is for genuine consumption/loss/authorized reasons, never to bypass sales.

Standalone mistakes use eligible reversal or count-based adjustment. Order-linked fulfillment mistakes use linked commercial/fulfillment correction; do not independently reverse them to evade order history.

## Serialized goods

A serialized item has one authoritative internal identity, optional manufacturer serial/aliases, one position at a time, cannot be issued twice, and verified return reuses the original identity.

## Attention, concurrency and recovery

Only monitored GOODS participate in NORMAL/LOW/OUT. Core reservation affects available and therefore stock attention under 13.

Use PostgreSQL transactions, ordered locks and durable idempotency receipts. Competing users for final available stock produce one success and one safe rejection.

Unknown network result is not failure. Retry/status recovery returns the original result. Reconciliation can recompute balances from ledger and reservations; mismatch blocks affected posting until investigated.
