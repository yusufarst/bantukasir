# Retail Operations Platform

Production-grade retail operations platform for one configurable business per deployment. The product is **not LATANSA-locked**: LATANSA may be the first configured business identity, but business name, logo, accent, document identity and contact details are runtime configuration.

Core combines fast counter POS with real-world order operations: booking, DP/partial payment, stock reservation, partial goods fulfillment, scheduled service work, progress/milestones, later settlement, barcode inventory, stock attention, cashier reconciliation and truthful gross-profit reporting.

The convenience-store comparison describes operational speed only. Do not copy another retailer's branding or UI.

## Current state

Planning version **3.1** is finalized on `plan/retail-order-replan`. Production implementation has **not started**. The old LATANSA-specific CP01 is historical/unapproved and is not the visual contract for this product direction.

**Plan 3.1 owner acceptance: APPROVED — 20 September 2026** (“setuju Plan 3.1”). Progress remains **0/47 overall, 0/39 Core**. Next eligible task is **R00.1 / RV01**, which stops at owner Gate A. R01.1 still needs that gate and separate explicit implementation authorization.

Start with [AGENTS.md](AGENTS.md), [00 — Current state](docs/00-CURRENT-STATE.md), [11 — Build plan](docs/11-BUILD-PLAN.md), then task-specific specifications and [10 — Decisions](docs/10-DECISIONS.md).

Documentation is English. User-facing UI is Bahasa Indonesia.

## Canonical ownership

| Document | Owns |
| --- | --- |
| [01 — PRD](docs/01-PRD.md) | Outcomes, Core scope, users and UX priorities |
| [02 — Architecture](docs/02-ARCHITECTURE.md) | Modules, transaction boundaries and infrastructure |
| [03 — Domain model](docs/03-DOMAIN-MODEL.md) | Entities, status dimensions and lifecycle |
| [04 — Auth/RBAC/Security](docs/04-AUTH-RBAC-SECURITY.md) | Roles, permissions, privacy and security |
| [05 — Design system](docs/05-DESIGN-SYSTEM.md) | Brand-neutral UI, workspaces and visual gates |
| [06 — Inventory](docs/06-INVENTORY-SPEC.md) | Ledger, reservation, fulfillment and stock safety |
| [07 — Business flows](docs/07-BUSINESS-FLOWS.md) | Cross-module user workflows |
| [08 — Testing](docs/08-TESTING-ACCEPTANCE.md) | Acceptance scenarios and release gates |
| [09 — Deployment/Ops](docs/09-DEPLOYMENT-OPS.md) | Runtime, backup/recovery and operations |
| [10 — Decisions](docs/10-DECISIONS.md) | Decision history and policy gates |
| [11 — Build plan](docs/11-BUILD-PLAN.md) | Official sequence and progress |
| [12 — Barcode/Scanner](docs/12-BARCODE-SCANNER.md) | Barcode identity, labels and scanning |
| [13 — Notifications](docs/13-NOTIFICATIONS.md) | Stock attention and delivery |
| [14 — Bulk import](docs/14-BULK-IMPORT.md) | Master/opening import |
| [15 — Finance](docs/15-FINANCE-PROFITABILITY.md) | Payment vs revenue, costing, HPP and gross profit |
| [16 — Executor handoff](docs/16-EXECUTOR-HANDOFF.md) | Execution rules |
| [17 — Orders/POS/Services](docs/17-POS-SALES.md) | Orders, POS, payments, fulfillment, services, receipts and shifts |

## Technology and cost

Preferred stack remains Next.js, TypeScript, PostgreSQL, Drizzle ORM, Zod, Tailwind CSS, shadcn/ui where useful, Lucide, Better Auth, Vitest, Playwright, Docker Compose and Caddy. Default architecture is a modular monolith with one PostgreSQL database.

Target recurring **software/SaaS cost is approximately Rp0** through open-source/self-hosted components and the client's existing VPS. VPS/domain, electricity/network, scanner/printer hardware and a reliable independent backup destination are real costs and must not be presented as free.

This repository is public. Never commit secrets, credentials, production data, customer data or private backup material.
