# BantuKasir

BantuKasir is the internal codename of a goods-only point-of-sale system for one business with one stock pool: product catalogue and barcode search, receiving and stock control, a cashier till with cash sessions, receipts, corrections and refunds, owner reporting, and backup and recovery. The client's public name and branding are not "BantuKasir".

**There is no application yet.** This repository contains planning documents only. It is being re-founded on the AICWDF-4.3 delivery framework; the earlier planning documents are kept under `legacy/` folders until each subject is rebuilt.

## Where to start

| If you want to know | Read |
| --- | --- |
| The rules for working here | [AGENTS.md](AGENTS.md) |
| The current state and the next authorized action | [docs/handoff/CURRENT_HANDOFF.md](docs/handoff/CURRENT_HANDOFF.md) |
| Which document is authoritative for a subject | [docs/00-governance/SOURCE_OF_TRUTH.md](docs/00-governance/SOURCE_OF_TRUTH.md) |
| What has been decided | [docs/00-governance/DECISIONS.md](docs/00-governance/DECISIONS.md) |
| What is still open | [docs/00-governance/OPEN_ITEMS.md](docs/00-governance/OPEN_ITEMS.md) |
| The re-foundation steps | [docs/00-governance/REFOUNDATION_PLAN.md](docs/00-governance/REFOUNDATION_PLAN.md) |
| Where older material went | [docs/00-governance/LEGACY_INDEX.md](docs/00-governance/LEGACY_INDEX.md) |

## Layout

```text
AGENTS.md, CLAUDE.md, README.md
docs/
  00-governance/       rules, source of truth, decisions, open items, toolchain, cost, data safety
  01-product/          product scope and requirements
  02-domain/           domain model and business rules
  03-workflows/        critical workflows, route and interaction contracts
  04-architecture/     architecture, stack, schema
  05-security/         security model
  06-api-performance/  concurrency, idempotency, API and performance rules
  07-ux-design/        information architecture, copy, design system
  08-testing/          test strategy and acceptance
  09-operations/       infrastructure, backup and restore
  10-release/          release and rollback
  11-tasks/            execution context, Task plan and Tasks
  handoff/             current handoff
assets/brand/          historical brand assets (under review)
```

Folders `01` to `11` currently contain only a `legacy/` subfolder holding the earlier documents for that subject, in Indonesian. New documents are added as each re-foundation step is authorized and completed.

## Stack (planned, nothing installed)

Laravel, Inertia, React with TypeScript, shadcn/ui with Tailwind CSS, PostgreSQL and Nginx, as a modular monolith. Versions are pinned when implementation is authorized.
