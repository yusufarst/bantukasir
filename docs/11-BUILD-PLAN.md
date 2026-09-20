# 11 â€” Official build plan and progress

**Plan version 3.1 â€” 20 September 2026.**

This replaces plan 2.2 and the incomplete Astra plan-3.0 draft. Product scope now includes unified orders, DP/partial payment, Core reservation/partial fulfillment and Core service scheduling/progress.

Production implementation has not started.

## Status

| Status | Meaning |
| --- | --- |
| [ ] | NOT STARTED |
| [~] | IN PROGRESS |
| [T] | WAITING FOR TECHNICAL VERIFICATION |
| [V] | WAITING FOR OWNER VISUAL REVIEW |
| [x] | COMPLETE AND VERIFIED |
| [!] | BLOCKED |

[BE] backend/integration, [FE] prototype/frontend, [FS] full-stack/visual.

Exactly one [~] at a time. [V] receives no completion credit.

**47 tasks in 10 phases; 39 Core tasks in R00â€“R07.**
Overall progress: **0/47 = 0.00%**.
Core progress: **0/39 = 0.00%**.

Old CP01 remains historical/unapproved and contributes no completion credit.

## Plan history

- v1/v2.x: inventory-first LATANSA-specific plan, 65 tasks/34 Core.
- incomplete Astra v3.0: began retail/POS replanning but retained immediate/full-payment-only assumptions and stale v2.2 tracker.
- **v3.1**: owner-approved unified Order flow; POS fast path, DP/partial payments, reservation, partial fulfillment, service jobs/progress, shift reconciliation and finance separation.

## R00 â€” First visual contract

| ID | Status | Class | Deliverable | Dependencies |
| --- | --- | --- | --- | --- |
| R00.1 | [ ] | FE | **RV01** brand-neutral shell/auth/role workspaces + fast POS skeleton; desktop/mobile/keyboard states; Gate A | Planning 3.1 owner acceptance |

R00.1 does not authorize production implementation.

## R01 â€” Foundation, auth and business configuration

| ID | Status | Class | Deliverable | Dependencies |
| --- | --- | --- | --- | --- |
| R01.1 | [ ] | BE | Next.js/TS/Drizzle/Postgres scaffold, config validation, dev/test DB, base migrations, test scripts and CI skeleton | R00.1 Gate A + explicit implementation authorization |
| R01.2 | [ ] | BE | Better Auth, fixed roles/permissions, TOTP, sessions, backend guards, audit/security tests | R01.1 |
| R01.3 | [ ] | FS | BusinessProfile/BusinessPolicy revisions, neutral identity settings, runtime validation, owner-only configuration | R01.2 |
| R01.4 | [ ] | FS | Implement approved RV01 shell/auth/workspaces with real authorization; Gate B | R01.2, R01.3, RV01 |

## R02 â€” Goods/services, barcode and inventory foundation

| ID | Status | Class | Deliverable | Dependencies |
| --- | --- | --- | --- | --- |
| R02.1 | [ ] | FE | **RV02** goods/services, barcode/labels, receiving, stock/history and opening-import states; Gate A | R01.4 |
| R02.2 | [ ] | BE | Product GOODS/SERVICE, GoodsProfile, price revisions, units/categories, server search/pagination | R01.2 |
| R02.3 | [ ] | BE | Barcode registry, manufacturer aliases, internal code generation, labels and serialized identity rules | R02.2 |
| R02.4 | [ ] | BE | Ledger/balances/serials, receiving, non-sale issue, direct transfer, owner adjustment/reversal, idempotency/concurrency | R02.3 |
| R02.5 | [ ] | BE | Product import + separate quantity/serial opening, freeze, cost-evidence capture and recovery | R02.4 |
| R02.6 | [ ] | FS | Approved RV02 integrated UI, scanner/label/print checks, import/opening browser flow; Gate B | R02.1, R02.5 |

## R03 â€” Orders, payments, reservation and goods fulfillment

| ID | Status | Class | Deliverable | Dependencies |
| --- | --- | --- | --- | --- |
| R03.1 | [ ] | FE | **RV03** order/booking, customer, DP/partial payment, reservation, partial fulfillment and service-progress interaction; Gate A | R02.6 |
| R03.2 | [ ] | BE | Customer + Order/OrderLine/revision model, numbering, pricing snapshots and lifecycle invariants | R02.2 |
| R03.3 | [ ] | BE | Append-only PaymentRecord/refund facts, outstanding calculation, idempotency and allowed methods | R03.2 |
| R03.4 | [ ] | BE | Core InventoryReservation, release/consume rules, available-stock races and reconciliation | R03.2, R02.4 |
| R03.5 | [ ] | BE | Partial GoodsFulfillment linked atomically to reservation + ledger ISSUE; 4+3+3 and serial tests | R03.3, R03.4 |
| R03.6 | [ ] | FS | Approved RV03 order/booking/payment/reservation/fulfillment UI with real backend; Gate B | R03.1, R03.5 |

## R04 â€” Service jobs and mixed orders

| ID | Status | Class | Deliverable | Dependencies |
| --- | --- | --- | --- | --- |
| R04.1 | [ ] | BE | ServiceJob scheduling and service-line linkage; no inventory entities | R03.2 |
| R04.2 | [ ] | BE | Milestones/progress/completion events, versioning and audit | R04.1 |
| R04.3 | [ ] | BE | ServiceCostEvidence and optional referenced goods-consumption cost path without double counting | R04.2, R02.4 |
| R04.4 | [ ] | BE | Mixed GOODS/SERVICE order invariants, completion derivation and change/cancellation rules | R03.5, R04.2 |
| R04.5 | [ ] | FS | Service operations/mobile views using approved RV03 patterns; real schedule/progress/completion; Gate B | R03.6, R04.4 |

## R05 â€” Fast POS, shifts, receipts and refunds

| ID | Status | Class | Deliverable | Dependencies |
| --- | --- | --- | --- | --- |
| R05.1 | [ ] | FE | **RV04** final fast checkout, shift, payment, receipt/reprint, refund/return and uncertain states; Gate A | R04.5 |
| R05.2 | [ ] | BE | POS fast-path command through Order engine: full payment + immediate fulfillment + receipt facts atomically | R03.5, R04.4 |
| R05.3 | [ ] | BE | Register/CashierShift/CashEvent/opening float/blind closing/variance and race rules | R03.3 |
| R05.4 | [ ] | BE | Receipt/payment-evidence snapshots, thermal/A4 browser rendering, numbering and reprint audit | R05.2 |
| R05.5 | [ ] | BE | Owner-authorized commercial refund/return, bounded partials, linked stock/finance corrections | R05.2, R05.3 |
| R05.6 | [ ] | FS | Approved RV04 integrated cashier UI; keyboard/HID/actual printer and recovery checks; Gate B | R05.1, R05.5 |

## R06 â€” Stock attention, finance and owner cockpit

| ID | Status | Class | Deliverable | Dependencies |
| --- | --- | --- | --- | --- |
| R06.1 | [ ] | FE | **RV05** owner dashboard, restock attention, order/job attention, finance completeness and system states; Gate A | R05.6 |
| R06.2 | [ ] | BE | StockHealth/AttentionEpisode/inbox/outbox/Web Push using available quantity including reservations | R03.4, R02.4 |
| R06.3 | [ ] | BE | MWA goods costing, service direct-cost evidence, RevenueEvents from fulfillment/completion, report snapshots/completeness | R05.5, R04.3 |
| R06.4 | [ ] | BE | Owner dashboard/report queries: order value, payments, outstanding, revenue, HPP, gross, jobs, LOW/OUT, shift variance | R06.2, R06.3 |
| R06.5 | [ ] | FS | Approved RV05 real owner/operations views, periods/drill-down/incomplete/error states; Gate B | R06.1, R06.4 |
| R06.6 | [ ] | BE | Golden finance/refund/return/cost-revision tests + owner-only no-leak matrix | R06.5 |

## R07 â€” Deployment, recovery and Core pilot

| ID | Status | Class | Deliverable | Dependencies |
| --- | --- | --- | --- | --- |
| R07.1 | [ ] | BE | Production Compose/Caddy, environment guards, worker supervision, storage, health and release procedure | R06.6 |
| R07.2 | [ ] | BE | Encrypted off-VPS backup, isolated restore, reconciliation of orders/payments/reservations/ledger/jobs/shifts/docs; measured RPO/RTO | R07.1 |
| R07.3 | [ ] | FS | System/backup/notification operational views and real owner-device notification acceptance or documented limitation | R07.2 |
| R07.4 | [ ] | FS | Full Core E2E/concurrency/load/mobile/scanner/printer/restore regression | R07.3 |
| R07.5 | [ ] | FS | Cutover, opening reconciliation, SOP, real-role pilot and explicit owner production acceptance | R07.4 |

Core requires all R00â€“R07 tasks [x] and no unresolved safety blocker.

## R08 â€” Later operational extensions

| ID | Status | Class | Deliverable | Dependencies |
| --- | --- | --- | --- | --- |
| R08.1 | [ ] | FS | Guided stock opname, advanced approval and staged warehouse transit | R07.5 |
| R08.2 | [ ] | FS | Purchasing/PO/supplier-payable workflow if client needs it | R07.5 |
| R08.3 | [ ] | FS | QC, warranty and repair/customer-custody service workflow | R07.5 |
| R08.4 | [ ] | FS | Camera barcode variant with manual/HID fallback; no offline mutation | R07.5 |

## R09 â€” Later commercial/public/accounting extensions

| ID | Status | Class | Deliverable | Dependencies |
| --- | --- | --- | --- | --- |
| R09.1 | [ ] | FS | Public corporate site/catalog and first-party structured CMS | R07.5 |
| R09.2 | [ ] | FS | RFQ/leads/quotations and conversion attribution | R09.1 |
| R09.3 | [ ] | FS | Advanced promotions/loyalty/payment-provider integration if justified | R07.5 |
| R09.4 | [ ] | BE | Full accounting/net-profit/multi-company scope only after separate business/accounting decision | R07.5 |

## Tracker rules

Update only real status/evidence. Scope changes require a new plan version and corrected denominator. Never award completion credit for documentation or historical prototype artifacts.

Visual tasks stop at Gate A/Gate B as applicable. Tests/screenshots do not self-approve owner visual gates. Production deployment/merge/push requires explicit authorization.
