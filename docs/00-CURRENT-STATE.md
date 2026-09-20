# 00 â€” Verified current state and handoff

Updated **20 September 2026**, Asia/Jakarta. This file is updated last by planning 3.1.

| Item | Verified state |
| --- | --- |
| Product direction | Brand-neutral retail operations: GOODS + SERVICES |
| Owner flow approval | **APPROVED**: unified Order model with instant POS fast path, DP/partial payment, booking, reservation, partial fulfillment and service progress |
| Roles | SUPER_ADMIN, OPERATIONS_ADMIN, CASHIER |
| Branding | Runtime-configurable; LATANSA is not universal identity |
| Cost target | Approximately Rp0 recurring software/SaaS; infrastructure/hardware costs remain real |
| Planning version | **3.1** |
| Production implementation | **NOT STARTED** |
| Active build task | None |
| Next eligible task | **R00.1 â€” RV01 visual contract**, after owner accepts planning 3.1 |
| Old CP01 | Historical/unapproved; no completion credit |

## What changed

The earlier inventory-first plan and the interrupted Astra immediate/full-payment retail draft are superseded where they conflict with planning 3.1.

Core now includes:
- unified Order engine;
- instant POS;
- deferred orders/bookings;
- DP/partial/later payments;
- stock reservation;
- partial goods fulfillment;
- service scheduling/milestones/progress/completion;
- cashier shift reconciliation;
- payment/revenue/profit separation;
- brand-neutral BusinessProfile;
- near-zero recurring software-cost architecture.

Inventory ledger, barcode identity, low-stock episodes, audit, backup/recovery, MWA costing and public/private data protections are preserved/refined.

## Repository state known at replanning

Partial Astra work was preserved in commit `5c1af71` and branch `plan/retail-order-replan`.

Previously reported local untracked items include `.agents/`, `prototypes/cp01-shell-auth/`, `scratch_img/` and `skills-lock.json`. They must not be silently deleted.

No Next.js production scaffold, migrations or deployed environment are credited complete by this planning work.

## Remaining policy gates

- **Q01 tax/legal documents:** confirm tax registration/treatment, mandatory buyer identity and whether formal tax/legal invoice is required before enabling that behavior.
- **Q04 recovery:** accept measured RPO/RTO and real independent backup destination before pilot.
- **Q05 inventory assumptions:** validate actual units, serial normalization and any pack/batch/expiry/consignment need before importing affected real stock.

DP/booking/staged work and cashier shift questions are resolved by the owner-approved flow and D46â€“D53.

## Next execution

Read AGENTS, this file, 01/02/10, then R00.1 in 11 and 05.

R00.1 produces RV01 only and stops at owner Gate A. Production R01.1 requires separate explicit implementation authorization.

Do not merge to `main`, deploy or resume old CP01 automatically.
