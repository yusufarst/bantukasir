# BantuKasir

**Configurable production-grade custom POS and stock-control platform.** V1 is a focused GOODS-only POS for one business: reusable product search, barcode, recorded stock receipts/issues, full cash/transfer checkout, immutable thermal/A4 sales documents, restock visibility, owner-only HPP/gross profit and lightweight cash-session reconciliation.

**BantuKasir Plan 1.1:** implementation not started, **0/36 Core tasks**, no active task. Next: **BK01 — UI01 Foundation + Shell**, in a subsequent authorized session. This documentation task starts no prototype or production work. Four prototype bundles and complete owner Gate A precede production frontend.

Copied from `yusufarst/latansa-inventory` at `f7cecfed1fcd0b183625e49f39dfeb736ab4e3a5`. That upstream remains the full Retail Operations Platform blueprint and must not be modified. Previous approvals do not approve BantuKasir visuals; Git history preserves superseded decisions.

Start with [AGENTS](AGENTS.md), [current state](docs/00-CURRENT-STATE.md), [build plan](docs/11-BUILD-PLAN.md) and [handoff](docs/16-EXECUTOR-HANDOFF.md).

| Canonical document | Owns |
| --- | --- |
| [01 PRD](docs/01-PRD.md) / [PRODUCT](PRODUCT.md) | Scope and concise design context |
| [02 Architecture](docs/02-ARCHITECTURE.md) / [03 Domain](docs/03-DOMAIN-MODEL.md) | Modules, transactions and entities |
| [04 Security](docs/04-AUTH-RBAC-SECURITY.md) | Three roles, privacy and auth |
| [05 UX](docs/05-DESIGN-SYSTEM.md) / [DESIGN](DESIGN.md) | Visual workflow and sole design baseline |
| [06 Inventory](docs/06-INVENTORY-SPEC.md) / [07 Flows](docs/07-BUSINESS-FLOWS.md) | Stock truth and operations |
| [08 Acceptance](docs/08-TESTING-ACCEPTANCE.md) / [09 Operations](docs/09-DEPLOYMENT-OPS.md) | Tests, protected DB and recovery |
| [10 Decisions](docs/10-DECISIONS.md) / [11 Plan](docs/11-BUILD-PLAN.md) | Decisions, gates, status and schedule |
| [12 Barcode](docs/12-BARCODE-SCANNER.md) / [13 Restock](docs/13-NOTIFICATIONS.md) | Identity, scans and LOW/OUT |
| [14 Onboarding](docs/14-BULK-IMPORT.md) / [15 Finance](docs/15-FINANCE-PROFITABILITY.md) | Opening stock, deferred import and HPP |
| [16 Handoff](docs/16-EXECUTOR-HANDOFF.md) / [17 POS](docs/17-POS-SALES.md) | Execution, Sale, documents, cash sessions and reports |

Preferred stack: Next.js, TypeScript, PostgreSQL, Drizzle, Zod, Tailwind, shadcn/ui, Better Auth, Vitest, Playwright, Docker Compose/Caddy. Versions/security are verified and pinned during implementation; no dependencies are installed by this plan. Additional recurring software target approximately Rp0. Existing VPS/domain, hardware and independent backup capacity remain real prerequisites/costs.

Runtime BusinessProfile configures name/logo/address/contact/receipt identity/accent without source changes. Documentation is English; UI is Bahasa Indonesia. No secrets or production data in Git. [Production destructive-operation prohibition](AGENTS.md) applies to every implementation and maintenance agent.
