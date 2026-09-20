# Prototype registry

This registry tracks visual/interaction prototypes only. It is not the build-progress tracker; `docs/11-BUILD-PLAN.md` is authoritative for task status.

## Historical artifact

`cp01-shell-auth/` exists as an earlier LATANSA-specific prototype created before the retail/order replanning.

It is preserved for historical/reference purposes only:

- no owner approval is carried forward;
- it is not a production visual contract;
- its LATANSA-specific palette/identity must not become the universal product design;
- it contributes no Plan 3.1 completion credit;
- do not delete it unless explicitly authorized.

| Pattern ID | Status | Purpose | Artifact |
| --- | --- | --- | --- |
| CP01 | HISTORICAL / UNAPPROVED | Previous shell/auth exploration | Local untracked `cp01-shell-auth/REVIEW.md`; not included in this planning commit |

## Plan 3.1 visual contracts

`docs/05-DESIGN-SYSTEM.md` owns the current visual-contract requirements.

| Pattern ID | Status | Scope |
| --- | --- | --- |
| RV01 | PLANNED | Brand-neutral shell/auth/role workspaces, business identity settings + fast POS skeleton |
| RV02 | PLANNED | Goods/services, barcode/labels, receiving and inventory |
| RV03 | PLANNED | Orders/bookings, DP/partial payment, reservation, fulfillment and service progress |
| RV04 | PLANNED | Cashier shift, final fast checkout, payment, receipt/reprint and refund states |
| RV05 | PLANNED | Owner dashboard, stock attention, finance and system/recovery states |

**Plan 3.1 owner acceptance: APPROVED — 20 September 2026.** Next eligible visual task is `R00.1 / RV01`; its planning-acceptance prerequisite is satisfied. It remains unstarted and no RV01 work is performed in this cleanup.

RV01 must use brand-neutral product foundations with runtime `BusinessProfile`; LATANSA may be used only as a first-client configuration/demo identity.

Prototype Gate A approves interaction/visual direction only. Production implementation still requires separate authorization and later Gate B approval.
