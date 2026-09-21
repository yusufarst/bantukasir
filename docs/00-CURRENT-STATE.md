# 00 — Current state and handoff

Updated **21 September 2026**, Asia/Jakarta. Written LAST after substantive documentation changes for **BantuKasir Plan 1.0**. This checkpoint is documentation/planning only.

| Item | Verified state |
| --- | --- |
| Repository / branch | yusufarst/bantukasir / plan/bantukasir-pos-v1 |
| Starting baseline | f7cecfed1fcd0b183625e49f39dfeb736ab4e3a5 |
| Product | Configurable production-grade GOODS-only POS and stock control |
| Plan | BantuKasir Plan 1.0; 36 total tasks, all 36 Core, 0/36 complete |
| Schedule | Target 20 working days / 160 focused hours; conditional on approvals/data/tools/devices/recovery, no spare engineering capacity assumed |
| Active executor | None |
| Next task | **BK01 — UI01 Foundation + Shell**; NOT STARTED |
| Implementation | NOT STARTED; no app, dependency, migration, prototype or production UI added |
| Visual approval | No UI01–UI04 Gate A or integrated Gate B; DESIGN candidate is NOT APPROVED |
| Authorization | One local documentation commit only; no push/merge/deploy or next-task execution |
| Roles | SUPER_ADMIN / Pemilik; OPERATIONS_ADMIN / Admin Operasional; CASHIER / Kasir |

## Effective source of truth

Read [AGENTS](../AGENTS.md) → this file → [11 active/next task](11-BUILD-PLAN.md) → [01 PRD](01-PRD.md) → [02 Architecture](02-ARCHITECTURE.md) → [10 Decisions](10-DECISIONS.md) → task-specific docs. [16](16-EXECUTOR-HANDOFF.md) supplies the short Antigravity prompt and continuity procedure. Repository overrides chat memory; one active task, no unrelated refactor, update 11/real decisions then 00 last.

Core uses one stock pool, immutable StockMovement and synchronous balance. Full CASH/BANK_TRANSFER Sale atomically records lines/payment/SALE_ISSUE/cost facts/receipt/audit/durable result. Search is shared across workflows. Thermal and A4 reuse one immutable snapshot; printing follows commit. MWA HPP/gross profit is owner-only, unknown costs remain incomplete. Owner corrections append facts.

Daily reconciliation identity is cashier + Jakarta businessDate. Activity-version validation requires recount after racing sales; finalization atomically freezes report and blocks later same-day Sales. No shift engine. G3 must validate isolated sales cash and separate owner-funded refunds. [17](17-POS-SALES.md) controls behavior.

Services/deferred orders/DP/reservation/partial fulfillment/warehouses/shifts/serials/Web Push/camera/enterprise features are Later. CSV/XLSX import is also Later; bounded manual opening is Core. Full list in 01. The upstream LATANSA repository is unchanged and remains the full-platform blueprint. Historical D01–D54 approvals have no effective authority over this replacement plan; preserved in baseline Git history. Effective decisions: **BK-D01–BK-D16**.

## Design and tool readiness

Four compact bundles: UI01 Foundation/Shell, UI02 Product Operations, UI03 POS, UI04 Reports. BK05 records complete owner Gate A before any production frontend and freezes [DESIGN](../DESIGN.md). [PRODUCT](../PRODUCT.md) is design context. Later slices require integrated Gate B.

[05](05-DESIGN-SYSTEM.md) requires actual free 21st.dev MCP/catalog plus supported Impeccable skill/CLI/integration; executor verifies installations and stops UI work if unavailable. No matching callable tools were exposed in this planning session; executor setup remains unverified. No tool use or visual approval is fabricated. Planning itself needed neither UI execution nor paid access.

## Verification and protected scope

- Documentation diff/scope and rewritten contracts reviewed; all changed paths are intended Markdown planning files.
- Strict UTF-8/mojibake, local Markdown links, table/fence structure, secret-pattern and conflict-marker checks passed before this final-state write; final pass is required before commit.
- Tracker: 36 unique IDs, all NOT STARTED, complete execution cards, resolved dependencies, acyclic graph, 160-hour sum. All prototype/backend/integration/test/deployment tasks count toward the 50-task maximum.
- README/AGENTS/PRD/architecture/plan agree on GOODS-only full-payment V1. Old full-platform features no longer effective Core.
- Production destructive-operation prohibition is prominent in AGENTS and 09, including exact-operation approval, populated migration rehearsal and verified recovery.
- Official historical LATANSA image SHA-256 remains 964e1a8644d75de8b5dbd37b4fae0e5af5f8c961f0acb60000e963ef4356f4ef. Asset bytes, .env.example and all non-documentation files remain untouched.
- Initial working tree was clean. Historical cp01-shell-auth directory was absent in this checkout; no old prototype completion credit.
- No application tests/build/browser/device tests run: docs-only task; no runtime success claimed.
- One intended local commit: **docs: replan BantuKasir as focused POS v1**. Verify its identity with Git history; no push, upstream changes, merge, deployment or prototype execution.

## Remaining gates / exact next action

| Gate | Still needed |
| --- | --- |
| G1 OPEN | Actual tax/legal invoice/buyer policy; commercial nota makes no formal-tax claim |
| G2 OPEN | Units/opening volume/barcodes/cost availability/serialized need before affected real onboarding |
| G3 OPEN | One reconciliation per cashier/day and separate cash/refund model operationally sufficient |
| G4 OPEN | Independent backup destination/access/cost and accepted measured RPO/RTO before production |

None blocks completion of this documentation replan. They block the corresponding execution/pilot gates in 11. Next is **BK01 — UI01 Foundation + Shell** in a subsequent authorized session, beginning with actual tool availability verification. **DO NOT START IT as part of this checkpoint.**
