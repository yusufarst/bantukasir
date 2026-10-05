# ADR-001 — Stack and modular monolith

```text
ID:               ADR-001
TITLE:            Stack and modular monolith
STATUS:           ACCEPTED
DATE:             4 October 2026 (Asia/Jakarta), written in RF-3A (D-055)
DECISION SOURCE:  D-004 (Planner), in force; AICWDF-4.3 adopted by the Owner (D-019)
```

## Context

BantuKasir is a private, goods-only point-of-sale system: one business and one stock pool per installation (D-038), three fixed roles (D-002, D-032), atomic money and stock transactions, an immutable stock ledger, moving-weighted-average cost, cash accountability, documents and recovery. The interface is a React application for PC and tablet cashier work and for Owner and Operations screens (D-039). There is no application code yet, and no production system to migrate.

The earlier planning compared two stacks from first principles (legacy P4-ARCHITECTURE §Perbandingan minimum A dan B) and chose the Laravel stack (D-004). AICWDF-4.3 §4 names the same stack as its default.

## Decision

1. **Stack.** Laravel (backend), Inertia with React and TypeScript (frontend), shadcn/ui with Tailwind CSS (interface components), PostgreSQL (database), Nginx with PHP-FPM inside the application container (web server; ADR-003). Vite runs at build time only.
2. **Architecture.** One modular monolith: one deployable application and one PostgreSQL database per installation, organized as an MVC web layer, an application layer of Actions and read Queries, domain-oriented modules and an infrastructure layer (AICWDF §15). The module map, entity ownership, dependencies and directory layout are in [STACK_AND_MODULES](../04-architecture/STACK_AND_MODULES.md); layers and unit-of-work boundaries are in [ARCHITECTURE](../04-architecture/ARCHITECTURE.md).
3. **Not used by default.** Microservices, a separate API service, server-side rendering on Node, Redis or Valkey, queue workers, Octane, websockets, an external search engine, an event bus, CQRS, and a generic repository layer per table (ADR-002; legacy P4 §Struktur modular minimal).
4. **Authentication** is Laravel-native (ADR-004). **Localization** uses Laravel translation files (ADR-005). **Exact numbers** use PostgreSQL exact types and an arbitrary-precision decimal library (ADR-006).
5. **Versions** are not pinned here. The foundation Task checks support windows, security advisories and compatibility, then pins versions and commits the lockfiles (D-004, TOOLCHAIN). Production never uses a floating `latest` tag.

## Alternatives considered

| Alternative | Why not chosen |
| --- | --- |
| Full TypeScript: Next.js, Drizzle, a self-hosted TypeScript auth library, Caddy (legacy P4 option A) | Viable and not unsafe. For a private transactional application built from zero, the integrated Laravel backend (routing, validation, sessions, CSRF, authorization, database testing) needs less assembly. Server components and SSR bring no product benefit here. One language is a real advantage that was weighed and not judged decisive (legacy P4). |
| Microservices or a separate API backend | No requirement justifies them; they would split atomic money and stock transactions across services (AICWDF §4: not the default). |

## Consequences

- Two languages (PHP and TypeScript) and two lockfiles. Response data crosses the boundary as explicit DTOs; Eloquent models are never serialized to the browser ([SECURITY_MODEL](../05-security/SECURITY_MODEL.md)).
- PHP floats and JavaScript numbers are never the authority for money, cost or quantity (ADR-006).
- The framework gives tools, not guarantees: it does not by itself prevent a double Sale, a cost leak or oversold stock (legacy P4). Those are prevented by the rules in [TECHNICAL_RULES](../06-api-performance/TECHNICAL_RULES.md) and SECURITY_MODEL, proven on real PostgreSQL.
- Estimates of hours saved in the legacy comparison are not carried; effort is estimated when the Task baseline is planned (RF-7).

## Framework conformance

```text
ADR REQUIRED:      YES (records the stack decision once, AICWDF §4A.1 and §15)
DEVIATION:         NONE — identical to the AICWDF-4.3 §4 default stack and architecture
OWNER APPROVAL:    NOT REQUIRED — no deviation; D-004 is a planner decision in force, and the Owner adopted AICWDF-4.3 (D-019)
REASON:            the default stack fits a private transactional web application
RISK:              executor unfamiliarity with PHP or Inertia; dependency conflicts at pinning time
MIGRATION IMPACT:  none — no application exists
ROLLBACK IMPACT:   none before implementation; after implementation a stack change needs a new ADR and Owner approval
```

## Verified sources

| Source | Checked | Fact used |
| --- | --- | --- |
| https://laravel.com/docs/starter-kits | 4 October 2026 | The current documentation is Laravel 13.x. All official starter kits use Laravel Fortify for authentication. The React starter kit is built with Inertia 3, React 19, TypeScript, Tailwind 4 and shadcn/ui. These numbers are observed facts, not pins. |

## Related

- Superseded legacy content: `docs/04-architecture/legacy/P4-ARCHITECTURE.md` §Konteks dan kriteria pemilihan, §Perbandingan minimum A dan B, §Dampak kecepatan dan biaya tradeoff, §Pembekuan dan pin versi; the stack paragraph of `docs/04-architecture/legacy/02-ARCHITECTURE.md` ([LEGACY_INDEX](../00-governance/LEGACY_INDEX.md) section 8).
- [ADR-002](ADR-002-minimum-runtime-infrastructure.md), [ADR-003](ADR-003-hosting-topology.md), [ADR-004](ADR-004-authentication.md), [ADR-005](ADR-005-localization-architecture.md), [ADR-006](ADR-006-exact-numbers.md).
