# 00 — Current state and handoff

Updated **21 September 2026**, Asia/Jakarta. Written LAST after substantive documentation changes for the **optimized BantuKasir Plan 1.1 delivery baseline**. This checkpoint is documentation/planning only.

| Item | Verified state |
| --- | --- |
| Repository / branch | yusufarst/bantukasir / plan/bantukasir-pos-v1 |
| Plan 1.0 baseline | 6e0c13d72cd5166f050fab9698ab71c59ca49b15 |
| Previous Plan 1.1 checkpoint | bd8a80719f3bf46166446fb70bb36e21b732e793 |
| Product | Configurable production-grade GOODS-only POS and stock control |
| Plan | Optimized BantuKasir Plan 1.1 delivery; 36 total tasks, all 36 Core, 0/36 complete |
| Schedule | 154 focused engineering hours / 19.25 eight-hour days; five-day weeks leave six focused hours inside 20 days, with no rework or waiting contingency |
| Active executor | None |
| Next task | **BK01 — UI01 Foundation + Shell**; NOT STARTED |
| Implementation | NOT STARTED; no app, dependency, migration, prototype or production UI added |
| Visual approval | No UI01–UI04 Gate A or integrated Gate B; Inter/palette/brand direction in DESIGN are PROPOSED and NOT APPROVED |
| BantuKasir logo | Exact source asset absent from repository; no import/review/color sampling claimed |
| Authorization | One local documentation commit only; no push/merge/deploy or next-task execution |
| Roles | SUPER_ADMIN / Pemilik; OPERATIONS_ADMIN / Admin Operasional; CASHIER / Kasir |

## Effective source of truth

Read [AGENTS](../AGENTS.md) → this file → [11 active/next task](11-BUILD-PLAN.md) → [01 PRD](01-PRD.md) → [02 Architecture](02-ARCHITECTURE.md) → [10 Decisions](10-DECISIONS.md) → [DESIGN](../DESIGN.md) for UI → task-specific docs → [16 handoff](16-EXECUTOR-HANDOFF.md) where relevant. Repository overrides chat memory; one active task, no unrelated refactor, update 11/real decisions then 00 last.

Core uses one stock pool, immutable StockMovement and synchronous balance. Full CASH/BANK_TRANSFER Sale atomically records lines/payment/SALE_ISSUE/cost facts/receipt/audit/durable result. Search is shared across workflows. Thermal and A4 reuse one immutable snapshot; printing follows commit. MWA HPP/gross profit is owner-only, unknown costs remain incomplete. Owner corrections append facts.

Cash accountability uses minimal CashRegister/CashSession: one OPEN session per logical drawer and responsible cashier, explicit opening cash (Rp0 allowed), then atomic CLOSED report. Sale and close share the session lock/activityVersion: a racing Sale is included once and forces recount or is rejected before facts; it never disappears. A cashier returning the same date opens a new session; handover closes A then opens B. No persistent CLOSING, shared active drawer, paid-in/out or attendance/payroll shift engine. Owner daily totals derive from source facts and list immutable session variance without double counting. G3 validates actual physical practice. [17](17-POS-SALES.md) controls behavior.

Services/deferred orders/DP/reservation/partial fulfillment/warehouses/complex shifts/shared drawer/paid-in-out/serials/Web Push/camera/enterprise features are Later. CSV/XLSX import is also Later; bounded manual opening is Core. Full list in 01. The upstream LATANSA repository is unchanged and remains the full-platform blueprint. Historical D01–D54 approvals have no effective authority over this replacement plan; preserved in Git history. Effective decision register: **BK-D01–BK-D21**. BK-D17 supersedes BK-D07; BK-D21 supersedes BK-D20's 166-hour estimate/sequencing while retaining its history.

## Optimized delivery sequencing

Plan 1.1 keeps all 36 IDs and safety requirements. Its honest floor is 154 focused hours: reuse UI/command/report primitives, prepare each gate once, and reserve the complete release-wide rerun for BK34. No RBAC, immutable stock, atomic Sale/idempotency, no-negative-stock, MWA/HPP, ReceiptSnapshot, CashSession race safety, audit, database protection, migration rehearsal, backup/restore, actual-device proof or owner review was removed.

Dependencies in [11](11-BUILD-PLAN.md) now distinguish engineering prerequisites from external completion holds. At most one task is IN PROGRESS; a task waiting for owner/device evidence can coexist without consuming the executor slot. BK06–BK08 may build backend/test foundations while BK05 waits, but Gate A remains mandatory before BK09 or any production frontend. Recorded technical evidence from BK10/BK20/BK25/BK31 may unblock only the explicitly listed independent work; all four Gate B tasks, BK33 and their actual evidence must be COMPLETE before BK34.

Owner/external preparation runs beside engineering: real logo and verified UI tools/review slots; G1/G2 policy/data; G3 drawer/float/handover/refund facts; scanners and label/receipt printers; G4 backup destination/RPO/RTO; VPS/domain/deployment access. Their waiting time is excluded from 154 hours and remains the largest threat to the one-month calendar.

## Design and tool readiness

Four compact bundles: UI01 Foundation/Shell, UI02 Product Operations, UI03 POS/session open-handover, UI04 session reports/daily aggregation. BK05 records complete owner Gate A before any production frontend and freezes [DESIGN](../DESIGN.md). Proposed direction: clean navy/charcoal-blue + teal/aqua identity, quiet pastel neutrals, independent semantic colors and Inter. The real logo is required before logo-specific extraction/approval. [PRODUCT](../PRODUCT.md) is design context. Later slices require integrated Gate B.

[05](05-DESIGN-SYSTEM.md) requires actual free 21st.dev MCP/catalog plus supported Impeccable skill/CLI/integration; executor verifies installations and stops UI work if unavailable. No matching callable tools were exposed in this planning session; executor setup remains unverified. No tool use or visual approval is fabricated. Planning itself needed neither UI execution nor paid access.

ChatGPT is the planning/review/decision partner; Antigravity executes one selected coding task. Neither depends on prior conversation for truth. Requirement changes enter canonical docs first; task status/evidence and commits in the repository carry continuity. Short executor prompts remain approximately 120–180 words using task cards.

## Verification and protected scope

- Documentation diff/scope and rewritten contracts reviewed; all changed paths are intended Markdown planning files.
- Final UTF-8/mojibake, local Markdown-link, table/card, dependency and conflict-marker audit passed after all optimized-plan edits; staged diff checks remain required before commit.
- Tracker: 36 unique IDs, all NOT STARTED, complete execution cards, separated technical/hold semantics, resolved dependencies, acyclic graph and 154-hour task/phase sum. Stable IDs retained; no task or safety acceptance removed. All prototype/backend/integration/test/deployment tasks count toward the 50-task maximum.
- README/AGENTS/PRD/architecture/plan agree on GOODS-only full-payment V1. Old full-platform features no longer effective Core.
- Production destructive-operation prohibition is prominent in AGENTS and 09, including exact-operation approval, populated migration rehearsal and verified recovery.
- Official historical LATANSA image SHA-256 remains 964e1a8644d75de8b5dbd37b4fae0e5af5f8c961f0acb60000e963ef4356f4ef. Asset bytes, .env.example and all non-documentation files remain untouched.
- Initial working tree was clean. Historical cp01-shell-auth directory was absent in this checkout; no old prototype completion credit.
- No application tests/build/browser/device tests run: docs-only task; no runtime success claimed.
- One intended local commit: **docs: optimize BantuKasir v1 delivery plan**. Verify its identity with Git history; no push, upstream changes, merge, deployment or prototype execution.

## Remaining gates / exact next action

| Gate | Still needed |
| --- | --- |
| G1 OPEN | Actual tax/legal invoice/buyer policy; commercial nota makes no formal-tax claim |
| G2 OPEN | Units/opening volume/barcodes/cost availability/serialized need before affected real onboarding |
| G3 OPEN | Actual drawer identities/labels, opening-float practice, one responsible cashier/no simultaneous sharing, owner cash-refund funding; replan if extra cash movements are required |
| G4 OPEN | Independent backup destination/access/cost and accepted measured RPO/RTO before production |

None blocks completion of this documentation replan. They block the corresponding execution/pilot gates in 11. Next is **BK01 — UI01 Foundation + Shell** in a subsequent authorized session, beginning with actual tool and real-logo availability verification. **DO NOT START IT as part of this checkpoint.**
