# 08 â€” Testing and acceptance

Tests prove behavior; they do not grant owner visual approval.

## Core risk scenarios

### Auth/RBAC
- AUTH01 unauthorized API is rejected even if UI route is known.
- AUTH02 disabled/revoked user cannot continue privileged writes.
- AUTH03 staff DTO/export cannot reveal acquisition cost/HPP/margin/profit.
- AUTH04 cashier cannot execute receiving/adjustment/user-admin/owner-refund actions.

### Inventory/reservation
- INV01 every physical change has ledger source, actor, time and location.
- INV02 two users competing for final available unit cannot oversell.
- INV03 reservation changes reserved/available but not onHand.
- INV04 fulfillment consumes reservation and posts ISSUE atomically.
- INV05 10 ordered may fulfill 4+3+3 but never 11.
- INV06 serialized item cannot duplicate, issue twice or occupy two locations.
- INV07 rollback leaves ledger/balance/reservation/serial state unchanged.

### Orders/payments
- ORD01 instant POS produces one Order, one payment, correct fulfillment and one document identity.
- ORD02 DP changes payment/outstanding only; no automatic stock issue.
- ORD03 repeated payment submit returns the same PaymentRecord.
- ORD04 multiple payments remain append-only and outstanding is correct.
- ORD05 mixed GOODS/SERVICE posts stock only for fulfilled GOODS.
- ORD06 confirmed change preserves prior revision and cannot rewrite performed facts.
- ORD07 lost response recovers original command, not a duplicate.
- ORD08 overpayment/price reduction requiring refund cannot silently create negative outstanding.

### Service jobs
- SRV01 schedule/progress/milestones never change stock.
- SRV02 service completion does not imply payment.
- SRV03 service payment does not imply completion.
- SRV04 missing service cost remains incomplete, not zero.

### POS/shifts/documents
- POS01 repeated quantity barcode scans increment draft predictably; serials deduplicate.
- POS02 printer failure leaves committed order intact; reprint uses original number.
- POS03 cash payment requires an eligible open shift and one CashEvent.
- POS04 shift close correctly serializes against racing cash transaction.
- POS05 blind count preserves variance; no silent balancing.
- POS06 normal scan-to-receipt flow stays usable without deferred-order fields.

### Refund/return
- RET01 cumulative refund cannot exceed eligible amount.
- RET02 returned quantity cannot exceed fulfilled quantity.
- RET03 refund without goods return creates no stock movement.
- RET04 saleable return posts linked receipt exactly once.
- RET05 service refund creates no stock movement and does not erase incurred cost.

### Attention
- NOT01 minimum 5: 6â†’5 opens LOW once; 5â†’4 does not repeat.
- NOT02 LOWâ†’OUT emits OUT once in same episode.
- NOT03 recovery to NORMAL resolves; later decline opens a new episode.
- NOT04 reservation-induced LOW/OUT uses available quantity consistently.
- NOT05 read/unread never resolves stock attention.

### Finance
- FIN01 payment/DP totals are separate from recognized revenue.
- FIN02 GOODS revenue recognizes on verified fulfillment and matches MWA HPP.
- FIN03 SERVICE revenue recognizes on verified completion, not booking/progress/payment.
- FIN04 unknown goods/service cost makes gross-profit scope incomplete.
- FIN05 no staff endpoint/export leaks owner-only finance.
- FIN06 returns/refunds correct revenue/HPP according to linked physical facts without rewriting originals.

### Recovery
- REC01 restored system reconciles orders, payments, documents, ledger, reservations, serials, jobs and shifts.
- REC02 commands possibly executed after backup cutoff are not blindly replayed.
- REC03 recovery epoch/status flow prevents stale browser envelopes from creating duplicates.
- REC04 real restore drill measures RPO/RTO before pilot.

## UX/visual acceptance

Each RV prototype records purpose/action/fact inventory and anti-slop/redundancy review. Verify relevant desktop/mobile widths, keyboard/focus, reduced motion and 200% zoom.

Cashier acceptance emphasizes:
- scanner/keyboard speed;
- totals always visible;
- one obvious primary checkout action;
- deferred-order fields hidden until requested;
- unknown-result recovery does not encourage a second transaction.

Operations acceptance emphasizes:
- work due today/late first;
- direct route to prepare/fulfill/progress/restock;
- no owner-finance leakage.

Owner acceptance emphasizes:
- attention before decoration;
- payments/outstanding distinct from revenue/profit;
- incomplete finance clearly labeled;
- no repeated KPI/chart/list facts.

Gate A = owner approves prototype interaction/visual direction. Gate B = owner approves real integrated UI after technical/browser evidence.

## Performance targets

Initial reference dataset: 5,000 products, 20,000 serials, 100,000 ledger legs, two concurrent operators.

Targets to measure on chosen host/network:
- local typing/scan feedback â‰¤100 ms;
- barcode resolve p95 â‰¤500 ms;
- ordinary 100-line command p95 â‰¤2 s where applicable;
- owner cockpit p95 â‰¤2 s;
- active inbox refresh within 20 s on healthy network.

Never weaken locks/validation to hit a target.

## Completion gates

Before completing relevant production tasks:
- migrations reviewed/applied in test;
- validation and backend RBAC tests;
- audit behavior checked;
- targeted concurrency/idempotency/rollback tests;
- lint/typecheck;
- relevant unit/integration/E2E;
- production build;
- affected browser/mobile/device verification;
- secret/diff review.

Core pilot additionally requires restore evidence, scanner/printer checks, owner-device notification acceptance or documented limitation, real-role workflow tests and owner acceptance. No production stock before R07.5 is accepted.
