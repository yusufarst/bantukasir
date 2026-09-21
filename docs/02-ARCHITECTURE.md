# 02 — Architecture

Owns module boundaries, transaction boundaries and infrastructure.

## Style

Use a **modular monolith**: one Next.js/TypeScript codebase, one PostgreSQL database, same-image worker, Drizzle ORM, Zod, backend RBAC, Docker Compose and Caddy.

Do not introduce microservices, Redis, Elasticsearch, managed queues, paid auth, hosted CMS or managed databases without measured need and explicit approval.

## Modules

1. Identity & Access
2. Business Configuration
3. Catalog
4. Customers
5. Inventory
6. Commerce / Orders
7. Service Operations
8. POS & Cashier
9. Attention / Notifications
10. Finance & Reporting
11. Imports / Audit / Operations

Public CMS/catalog/RFQ remains later and uses allowlisted projections from the same Product Master.

## Command contract

Every critical mutation has authenticated actor, backend permission, validated input, idempotency where retry is possible, canonical server fingerprint, DB transaction, audit and durable result identity.

### Instant POS fast path

A normal immediate sale may atomically compose:
- create/confirm Order;
- full PaymentRecord;
- CashEvent when cash;
- immediate GOODS fulfillment + ledger ISSUE;
- immediate SERVICE completion only if actually performed;
- revenue facts;
- receipt snapshot;
- audit/command receipt.

Printing is after commit. Printer failure never rolls back the transaction.

### Deferred order / booking

Order confirmation is separate from later payment and fulfillment. It may create reservation and service schedule. Later commands append payments, goods fulfillments, service progress/completion, refunds/returns/corrections.

A payment command never mutates stock simply because money arrived.

### Goods fulfillment

Fulfillment and its ledger ISSUE commit together. Consumed reservation is reduced in the same transaction.

### Shift close and operational reporting

POS & Cashier owns the [D54 close/report contract](17-POS-SALES.md). CashierShift/CashEvent and existing commercial/payment/refund facts remain the sources; ShiftCloseReportSnapshot is their immutable as-closed document, not a new financial source. Closing facts, snapshot, audit and durable result commit atomically. Rendering and export run after commit over allowlisted data; failures cannot undo closing.

### Background work

Use PostgreSQL-backed outbox/job tables and the same-codebase worker for push delivery, finance valuation/report publication, bounded import work and health metadata. No external queue is required for Core.

## Canonical lock order

command/idempotency → actor/policy → commercial aggregate → shift/register when used → locations/products → balances/reservations → serials → append-only result/audit.

Task specs may refine but must not invert the global order. Every writer attributing commercial/payment/cash facts to a shift acquires its guard before appending and checks OPEN. Close uses the same guard to establish CLOSING and a stable cutoff; it reads committed immutable facts without acquiring commercial locks after the shift lock. No human counting or printer/network wait holds database locks. Uncertain original commands must resolve before finalization, as specified in 17.

## Sources of truth

- physical stock: StockMovement ledger;
- reserved quantity: reservation events/records;
- payment history: PaymentRecord/refund facts;
- service progress: ServiceProgressEvent/milestones;
- finance: RevenueEvent + cost allocations.

Balances/status/dashboard rows are projections and must be reconcilable.

## Storage and printing

Use configured private local storage on the VPS for imports/business assets. Store logical object keys, never machine-specific paths.

Receipt/A4 and shift-close reports use browser HTML/CSS printing and print-to-PDF. Shift reports also provide first-party UTF-8 CSV from the original snapshot, with per-request authorization and safe text escaping under 17. No paid PDF service is required.

## Branding boundary

Business identity is runtime data, not source-code identity. BusinessProfile may provide logo/name/document details and a contrast-validated accent. Semantic success/warning/danger/focus colors remain independent.

## Cost boundary

Core is designed for near-zero recurring software cost with PostgreSQL, Better Auth, browser printing, open-source barcode generation, Web Push, Caddy and the existing VPS. Reliable independent backup remains required even if it creates a small infrastructure cost.
