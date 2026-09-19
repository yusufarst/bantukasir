# LATANSA Platform

Inventory and warehouse operations for **CV. Latansa Jogjakarta**. Staff scan goods in and out. The owner monitors trustworthy stock, low-stock attention, exceptions, and later profitability. The public website and sales extend the same Product Master after inventory Core is reliable.

**Documentation refinement: 20 September 2026. Production implementation has not started.** No application, migrations, HTML prototypes, or production environment exist. Official brand assets and owner review remain pending.

## Start here

1. Read [AGENTS.md](AGENTS.md) for working rules.
2. Read [current state](docs/00-CURRENT-STATE.md) for the verified handoff.
3. Follow the [build plan](docs/11-BUILD-PLAN.md): owner review, official assets, then four Core prototype bundles when instructed. Production requires approved visual contracts and a separate instruction to implement.
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

## Technology and cost

Modular monolith: Next.js, TypeScript, PostgreSQL, Drizzle, Zod, Tailwind CSS, shadcn/ui where useful, Lucide, Better Auth, Vitest, Playwright, Docker Compose and Caddy. Verify compatible stable versions and security advisories during P01, then commit a lockfile. No application installation or run command exists yet.

Use the client's existing VPS and current domain, `latansajogjakarta.com`. Keep deployment portable and centralize host configuration. No paid CMS, WhatsApp API, hosted search, or managed database is required. Responsible off-host backup remains mandatory.

This repository is public. Never commit secrets or real operational data. [.env.example](.env.example) contains empty values only; activation requirements belong to [operations](docs/09-DEPLOYMENT-OPS.md).

Remote: [yusufarst/latansa-inventory](https://github.com/yusufarst/latansa-inventory). The initial baseline is on `main`; refinement uses a reviewable `codex/` branch. A local commit does not imply a push or merge.
