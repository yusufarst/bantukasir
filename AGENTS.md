# Agent rules — BantuKasir

Repository documentation is the source of truth for **BantuKasir Plan 1.1**. This independent GOODS-only POS supersedes the inherited retail-platform plan. Do not modify the upstream LATANSA repository. Preserve unrelated local work.

## Recover context first

Before substantial work inspect `git status --short`, branch and recent commits. Read, in order: this file; [00](docs/00-CURRENT-STATE.md); active/next task in [11](docs/11-BUILD-PLAN.md); [01](docs/01-PRD.md); [02](docs/02-ARCHITECTURE.md); [10](docs/10-DECISIONS.md); [DESIGN](DESIGN.md) for UI; task-specific canonical docs; [16](docs/16-EXECUTOR-HANDOFF.md) where relevant. Never guess through a conflict or rely on chat memory. Record changed requirements before execution.

## Product and integrity

- GOODS only, one business-wide stock pool, full-payment instant Sale. No Order/booking/DP, services, reservations, warehouses or attendance/payroll/complex shift engine in V1.
- Exactly SUPER_ADMIN / Pemilik, OPERATIONS_ADMIN / Admin Operasional, CASHIER / Kasir. Backend role and object authorization is mandatory.
- Immutable StockMovement is stock truth; StockBalance is its synchronous projection. Never directly edit balances or allow negative stock.
- Completed Sale, lines, full Payment, SALE_ISSUE, balance/cost facts, ReceiptSnapshot, audit and durable command result commit atomically. Printing follows commit.
- Scan edits a draft. A beep never posts stock. Unknown results recover the original command, never replacement sales.
- Payments/history are append-only. Corrections/refunds preserve originals. Refund without physical return cannot increase stock.
- Missing cost is unknown, never zero. Laba Kotor is never Laba Bersih. Acquisition cost/HPP/profit and sensitive audit/security data are owner-only; staff DTOs use allowlists.
- Cash reconciliation uses a lightweight `CashSession`: one active logical drawer, one responsible cashier, OPEN → CLOSED, explicit opening cash (including Rp0), race-safe immutable close under [17](docs/17-POS-SALES.md). This is not attendance, scheduling, payroll or a complex shift engine.
- Runtime BusinessProfile owns client identity and constrained accent. LATANSA assets are historical/first-client assets only.

## Protected production database — applies to ALL agents

Production contains valuable live business records. Without explicit owner approval for the **EXACT destructive operation**, NEVER DROP DATABASE/SCHEMA or populated production tables; TRUNCATE; mass DELETE business records; reset/recreate/seed demo data into production; execute destructive reset scripts/migrations; delete PostgreSQL/Docker production volumes or persistent DB directories; run `docker compose down -v` against production; delete StockMovement, completed Sales, Payments or AuditEvent history; silently rewrite historical stock/financial facts.

Generic “fix”, “deploy” or “run migration” is NOT destructive approval. Default to additive/forward migrations, expand → migrate/backfill → verify → contract, populated non-production rehearsal, backup before high-risk changes, restore readiness, transactional migration where possible and post-migration reconciliation. If destruction appears necessary, STOP and report why, data at risk, safer alternatives, migration/backfill plan, backup/restore prerequisites and rollback/recovery plan. Non-production reset requires a clearly identified target and task authorization. Full runbook: [09](docs/09-DEPLOYMENT-OPS.md).

## Design and execution

ChatGPT is the planning/review/decision partner; Antigravity is the selected-task coding executor. Neither conversation memory nor an executor report is source of truth. Changed requirements must enter canonical docs before execution; repository status/evidence and commits carry continuity across new chats or agents.

One active executor task. No unrelated refactors or hidden scope expansion. Four compact UI bundles establish a complete owner-approved Gate A before production frontend. [DESIGN](DESIGN.md) is the sole token/pattern authority; freeze its approved revision. Verify actual 21st.dev and Impeccable availability before every meaningful UI task; missing integration stops that UI task. Follow [05](docs/05-DESIGN-SYSTEM.md), not invented tool use. No paid credits/templates/services without explicit approval.

After Gate A, implement vertical slices: DB → validation → service → RBAC → audit → UI → tests → browser/device verification. Gate B reviews real integration. Bahasa Indonesia labels; cashier path Scan/Search → Keranjang → Bayar → Struk.

Target additional software/SaaS cost approximately Rp0 using existing VPS/domain, PostgreSQL, self-hosted auth, browser print/PDF and open-source barcode. Independent backup is mandatory; document real storage cost rather than weakening recovery.

Completion requires applicable migrations, validation, RBAC, audit, tests, lint/typecheck/build, browser/mobile/device checks and docs evidence. Update 11, then 10 only for real decisions, and **00 LAST**. Commit each substantial completed task; stage only intended files. Never push, merge, deploy, publish or buy services without explicit authorization. This replanning authorizes one local docs commit only; do not start its next task.
