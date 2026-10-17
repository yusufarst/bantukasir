# BantuKasir

BantuKasir is the internal codename of a goods-only point-of-sale system for one business with one stock pool: product catalogue and barcode search, receiving and stock control, a cashier till with cash sessions, receipts, corrections and refunds, owner reporting, and backup and recovery. The client's public name and branding are not "BantuKasir".

**There is no application yet.** This repository contains planning documents only. It is being re-founded on the AICWDF-4.3 delivery framework; the earlier planning documents are kept under `legacy/` folders until each subject is rebuilt.

## Where to start

| If you want to know | Read |
| --- | --- |
| The rules for working here | [AGENTS.md](AGENTS.md) |
| The current state and the next authorized action | [docs/handoff/CURRENT_HANDOFF.md](docs/handoff/CURRENT_HANDOFF.md) |
| What the product is and must do | [docs/01-product/PRODUCT_BLUEPRINT.md](docs/01-product/PRODUCT_BLUEPRINT.md), [docs/01-product/REQUIREMENTS.md](docs/01-product/REQUIREMENTS.md) |
| The business rules and terms | [docs/02-domain/DOMAIN_MODEL.md](docs/02-domain/DOMAIN_MODEL.md), [docs/02-domain/GLOSSARY.md](docs/02-domain/GLOSSARY.md) |
| How it is built and secured | [docs/04-architecture/ARCHITECTURE.md](docs/04-architecture/ARCHITECTURE.md), [docs/04-architecture/SCHEMA.md](docs/04-architecture/SCHEMA.md), [docs/05-security/SECURITY_MODEL.md](docs/05-security/SECURITY_MODEL.md), [docs/06-api-performance/TECHNICAL_RULES.md](docs/06-api-performance/TECHNICAL_RULES.md), [docs/adr/README.md](docs/adr/README.md) |
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
  adr/                 architecture decision records
  handoff/             current handoff
assets/brand/          codename prototype logo and its README; no client identity (D-038)
```

Folders `01` and `02` hold the current product and domain documents written in RF-2, and folders `04`, `05` and `06` the current architecture, security and technical-rule documents written in RF-3A, with the logical database schema (`docs/04-architecture/SCHEMA.md`) written in RF-3B. Each sits next to a `legacy/` subfolder with the earlier documents for that subject, in Indonesian; they are superseded, apart from the inputs kept for later steps, and the schema and constraint parts of the legacy architecture documents become superseded when the Owner accepts the RF-3B checkpoint. Folder `07` holds the six current user-experience structure documents written in RF-4A — user tasks, information architecture, navigation contracts, interaction inventory, UI states, adaptive and accessibility rules — whose gate P7-G1 the Owner approved on 17 October 2026, and the copy deck written in RF-4B, which with the glossary forms gate P7-G2, approved by the Owner on 17 October 2026 (D-086), next to its `legacy/` subfolder; RF-4C, the visual design for gate P7-G3, wrote the design system and the key screens — `DESIGN_SYSTEM.md` and `KEY_SCREENS.md`, with renderable previews in `docs/07-ux-design/previews/` — which await the Owner's decision on that gate. Folders `03` and `08` to `11` still contain only a `legacy/` subfolder. `docs/adr/` holds the architecture decision records. New documents are added as each re-foundation step is authorized and completed.

## Stack (planned, nothing installed)

Laravel, Inertia, React with TypeScript, shadcn/ui with Tailwind CSS, PostgreSQL and Nginx, as a modular monolith (ADR-001). Hosting: a VPS behind Cloudflare, managed by Coolify, with a separate staging environment on the same server (ADR-003). Versions are pinned when implementation is authorized.
