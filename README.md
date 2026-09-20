# LATANSA Platform

Inventory and warehouse operations for **CV. Latansa Jogjakarta**. Staff scan goods in and out. The owner monitors trustworthy stock, low-stock attention, exceptions, and later profitability. The public website and sales extend the same Product Master after inventory Core is reliable.

**Planning baseline complete — 20 September 2026. Next executor task: P00.1, Gemini Antigravity.** Owner approval of D01–D34 is recorded. The [official colored mark](assets/brand/README.md) is preserved unchanged and the logo-derived pastel system is documented. Build progress is 0%; no application, migrations, HTML prototypes, visual approval or production environment exists.

## Start here

1. Read [AGENTS.md](AGENTS.md) for working rules.
2. Read [current state](docs/00-CURRENT-STATE.md) for the verified handoff.
3. Follow the [executor handoff](docs/16-EXECUTOR-HANDOFF.md) and the next row in the [build plan](docs/11-BUILD-PLAN.md). Start P00.1 only in the next execution session; P00.2 waits for its Gate A approval. Production requires approved Core visual contracts and separate explicit authorization.
4. Read only relevant specifications and [decisions](docs/10-DECISIONS.md).

Documentation is English. All user-facing LATANSA UI is Bahasa Indonesia.

## Canonical ownership

| Document | Owns |
| --- | --- |
| [01 — PRD](docs/01-PRD.md) | Outcomes, users, scope, priorities, dashboard hierarchy |
| [02 — Architecture](docs/02-ARCHITECTURE.md) | Modules, services, public CMS, storage boundaries |
| [03 — Domain model](docs/03-DOMAIN-MODEL.md) | Entities, relationships, identity and lifecycle |
| [04 — Security](docs/04-AUTH-RBAC-SECURITY.md) | Accounts, permissions, privacy and security audit |
| [05 — Design system](docs/05-DESIGN-SYSTEM.md) | UI language, tokens, navigation, responsive behavior and visual gates |
| [06 — Inventory](docs/06-INVENTORY-SPEC.md) | Ledger, balances, concurrency, corrections and advanced inventory |
| [07 — Business flows](docs/07-BUSINESS-FLOWS.md) | Cross-module user workflows |
| [08 — Testing](docs/08-TESTING-ACCEPTANCE.md) | Acceptance evidence, risk scenarios and completion gates |
| [09 — Operations](docs/09-DEPLOYMENT-OPS.md) | Configuration, deployment, backup, retention and recovery |
| [10 — Decisions](docs/10-DECISIONS.md) | Accepted design choices, tradeoffs and assumptions |
| [11 — Build plan](docs/11-BUILD-PLAN.md) | Implementation order and the only official progress tracker |
| [12 — Barcode and scanner](docs/12-BARCODE-SCANNER.md) | Code identity, printing, HID input and scan sessions |
| [13 — Notifications](docs/13-NOTIFICATIONS.md) | Stock states, attention episodes, deduplication and delivery |
| [14 — Bulk import](docs/14-BULK-IMPORT.md) | Product/opening templates, staging, validation, atomic apply and bulk actions |
| [15 — Finance](docs/15-FINANCE-PROFITABILITY.md) | Cost evidence, valuation, revenue matching, gross profit and completeness |
| [16 — Executor handoff](docs/16-EXECUTOR-HANDOFF.md) | Session start, one-task execution, verification, stop conditions and reporting |

## Technology and cost

Modular monolith: Next.js, TypeScript, PostgreSQL, Drizzle, Zod, Tailwind CSS, shadcn/ui where useful, Lucide, Better Auth, Vitest, Playwright, Docker Compose and Caddy. Verify compatible stable versions and security advisories during P01, then commit a lockfile. No application installation or run command exists yet.

Use the client's existing VPS and current domain, `latansajogjakarta.com`. Keep deployment portable and centralize host configuration. No paid CMS, WhatsApp API, hosted search, or managed database is required. Responsible off-host backup remains mandatory.

This repository is public. Never commit secrets or real operational data. [.env.example](.env.example) contains empty values only; activation requirements belong to [operations](docs/09-DEPLOYMENT-OPS.md).

Remote: [yusufarst/latansa-inventory](https://github.com/yusufarst/latansa-inventory). After the planning merge, `main` is the default canonical execution branch. Use the repository files on the currently checked-out canonical branch as the source of truth. `codex/refine-product-ux-architecture` is historical planning work; do not switch back to it for execution.
