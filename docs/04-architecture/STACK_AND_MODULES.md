# Stack and modules

| | |
| --- | --- |
| Status | CURRENT — written in RF-3A under D-055, 4 October 2026 (Asia/Jakarta); amended in RF-6 under D-088, 18 October 2026: the Operations module and its dependencies; section 3 adapted by TASK-001 under STK-22, 19 October 2026, with `tests/Network/` added the same day after its review of class B |
| Phase | P4 — application architecture |
| Replaces | The stack, module and structure sections of `docs/04-architecture/legacy/P4-ARCHITECTURE.md` and the module paragraph of `docs/04-architecture/legacy/02-ARCHITECTURE.md` ([LEGACY_INDEX](../00-governance/LEGACY_INDEX.md) section 8) |
| Decisions | D-004, D-005, D-050; [ADR-001](../adr/ADR-001-stack-and-modular-monolith.md), [ADR-002](../adr/ADR-002-minimum-runtime-infrastructure.md), [ADR-004](../adr/ADR-004-authentication.md), [ADR-005](../adr/ADR-005-localization-architecture.md), [ADR-006](../adr/ADR-006-exact-numbers.md), [ADR-007](../adr/ADR-007-command-identity-and-idempotency.md) |
| Related | [ARCHITECTURE](ARCHITECTURE.md), [SCHEMA](SCHEMA.md), [TOOLCHAIN](../00-governance/TOOLCHAIN.md) |

The technology stack and its version policy, the module map with entity ownership and dependencies, the directory layout, and the rule against speculative abstraction. Rules are numbered `STK-nn`; module names are permanent.

## 1. Stack

| Component | Choice | Purpose | Source |
| --- | --- | --- | --- |
| Backend framework | Laravel | Routing, validation, sessions, CSRF, authorization, database access, scheduling, testing | D-004, ADR-001 |
| Authentication backend | Laravel Fortify, only the features of ADR-004 | Sign-in, sign-out, password confirmation, own password change | ADR-004, D-049 |
| Server–client bridge | Inertia (Laravel adapter and React adapter) | Server-routed pages with React | D-004 |
| Frontend | React with TypeScript | Interface | D-004 |
| Interface components | shadcn/ui with Tailwind CSS | Native components before custom primitives (TOOLCHAIN) | D-004, D-010 as adapted by D-024 |
| Build | Vite, at build time only | Compile frontend assets | D-005 |
| Database | PostgreSQL | Transactions, row locks, constraints, exact numbers | D-004, D-006 |
| Web server | Nginx with PHP-FPM, in the application container | Serve the application | D-005, ADR-003 |
| Exact arithmetic | Brick Math | Money, quantity and cost on the server | ADR-006 |
| Localization | Laravel translation files shared to React through Inertia | All user-facing copy | D-050, ADR-005 |
| Barcode labels | An open-source Code 128 library with a verified licence, chosen in its Task | Label SVG | BR-CAT-35 |
| Image processing | An open-source image library with a recorded licence and external delegates disabled, chosen by the foundation Task | Re-encode logos and photos, make thumbnails | SECURITY_MODEL section 13 |
| Quality tools | Pint, PHPStan with Larastan, Pest or PHPUnit, ESLint (including `react/jsx-no-literals`), TypeScript typecheck, Vitest where behaviour warrants it, Playwright | Tests and static checks | TOOLCHAIN, ADR-005 |

Not part of the stack: Redis or Valkey, queue workers, Horizon, Octane, server-side rendering, websockets, an external search engine, Sanctum, Passport, Socialite, the WorkOS starter-kit variant, a third-party internationalization runtime, a server PDF engine, a payment gateway SDK, a mail service, and any paid service (ADR-001, ADR-002, ADR-004, ADR-005; D-036; COST_POLICY).

| ID | Rule | Source |
| --- | --- | --- |
| STK-01 | **No version is pinned in planning documents.** The foundation Task checks support windows, security advisories, licences and compatibility of every component, pins exact versions, commits `composer.lock` and the npm lockfile, and records each tool's status in TOOLCHAIN. Production never uses a floating `latest` tag. | D-004, TOOLCHAIN, L-P4 §Pembekuan dan pin versi |
| STK-02 | Pinned versions do not reopen the stack choice. Changing a stack component needs evidence of incompatibility, a security or operational reason, and a new ADR. | L-P4 §Pembekuan dan pin versi, ADR-001 |
| STK-03 | A dependency is added only when a Task needs it, its licence is recorded and no existing capability covers it (COST_POLICY selection order). | COST_POLICY, AICWDF §4C.1, §6.4 |
| STK-04 | Version-sensitive APIs are checked against the current official documentation of the pinned version before use; remembered APIs are not relied on. | TOOLCHAIN, AICWDF §5.1 |

## 2. Modules

One application, one database. Modules are folders and namespaces with an owner for each entity, not deployable services. Actions in the application layer orchestrate several modules inside one transaction (ARCHITECTURE section 3); a module never opens its own transaction.

| Module | Owns (entities and rules) | Main responsibilities | Domain source |
| --- | --- | --- | --- |
| Access | User, role assignment, sign-in session, one-time link, recent authentication, capability map | Authentication boundary (ADR-004); policies and object scope; actor guard; user lifecycle including anonymization; operator procedures (bootstrap, Owner recovery, Owner replacement) | DOMAIN_MODEL section 8; SECURITY_MODEL |
| Configuration | BusinessProfile, BusinessProfileRevision | Shop identity with immutable revisions and the current pointer | DOMAIN_MODEL section 10 |
| Catalog | Category, Product, SellingPriceRevision, Barcode and its mapping history, product photo reference, CSV product import | Master data, prices, barcode tokens and their correction (D-051, D-052), Product Search, label data, CSV import | CATALOG_BARCODE |
| Inventory | StockMovement, StockBalance, receiving document, manual issue, opening stock and onboarding state, adjustment, reversal, posting block | Every physical stock change, onboarding freeze, stock attention, reconciliation | INVENTORY |
| Costing | AcquisitionCostEvidence, cost correction, inventory valuation, issue-cost allocation facts and projections, cost-incomplete marks | Moving weighted average, UNKNOWN handling, replay, cost visibility | COSTING; representation of allocations [ADR-008](../adr/ADR-008-cost-allocation-representation.md) |
| Sales | Sale, SaleLine, Payment, ReceiptSnapshot, Refund, Return, and the running totals of refunds and returns | Checkout, payment methods (ARCHITECTURE section 5), refunds and returns with their bounds | SALES_CASH sections 1 to 5 and 8 |
| Cash | CashRegister, CashSession, CashSessionReportSnapshot, Owner review note | Session open and close, activity version, expected cash, session reports | SALES_CASH sections 6 and 7 |
| Documents | Template versions | Rendering receipts, session reports and labels from snapshots; CSV exports; print and export audit | SALES_CASH section 5, BR-AUD-06 |
| Reporting | — (read only) | Finance report, daily summary, stock history, audit log views, per-role DTOs | COSTING section 9, SALES_CASH section 9 |
| Audit | AuditEvent; security log entries | In-transaction audit writer and reader; separate security log channel | DOMAIN_MODEL section 9; SECURITY_MODEL section 9 |
| Commands | Command result and fence records; request receipts, stale-intent reports and their reconciliations (ADR-009); recovery epoch | Envelope validation, fingerprint, identity, outcomes, resolution (ADR-007); request receipts and the reconciled outcomes of requests from before a restore (ADR-009) | DOMAIN_MODEL section 7 |
| Media | Stored files and temporary uploads | Upload validation and re-encoding, private storage, authorized delivery, temporary CSV area, housekeeping | SECURITY_MODEL section 13; D-037, D-054 |
| Operations | Backup runs, restore drills and reconciliation runs (SCHEMA section 5.12, D-088) | Scheduled commands (reconciliation trigger, housekeeping, backup, heartbeat), the health route, the system status, and the operator procedures of restore and recovery (SEC-OP-04 to SEC-OP-08), each calling the owning module's services | REQ-OPS-04, ADR-002, ADR-010, ADR-011 |

A shared kernel holds the value objects every module uses: money, quantity, cost (ADR-006), business date and clock, and the formatting helpers of ADR-005. It depends on no module.

### 2.1 Dependencies

A module may read from the modules listed for it; writes across modules happen only through an Action that calls each module's own services. The graph has no cycle.

| Module | May depend on |
| --- | --- |
| Shared kernel | — |
| Audit, Commands, Media | Shared kernel |
| Access | Audit, Commands, shared kernel |
| Configuration | Media, Audit, Commands, Access (actor), shared kernel |
| Catalog | Media, Audit, Commands, Access, shared kernel |
| Inventory | Catalog, Audit, Commands, Access, shared kernel |
| Costing | Inventory (ledger, read), Catalog, Audit, Access, shared kernel |
| Cash | Configuration (snapshot revision), Audit, Commands, Access, shared kernel |
| Sales | Catalog, Inventory, Costing, Cash, Configuration, Audit, Commands, Access, shared kernel |
| Documents | Sales, Cash, Catalog, Configuration (all read), Audit, shared kernel |
| Reporting | Every domain module, read only |
| Operations | Inventory, Costing (reconciliation reads; the recovery and rebuild procedures call their services), Catalog (active mappings), Sales (running totals), Media, Commands, Access (actor), Audit |

| ID | Rule | Source |
| --- | --- | --- |
| STK-10 | Cash does not depend on Sales. A close derives Sale and payment totals through an Action that reads the Sales module's query and passes the totals to the Cash module, keeping the graph acyclic. | this document; ARCHITECTURE ARC-02 |
| STK-11 | Inventory does not call Costing; a posting Action calls both in one transaction. Costing reads the ledger for replay. | this document; L-P4 §Struktur modular minimal |
| STK-12 | Only the owning module writes its entities. Reporting and Documents never write business entities (Documents writes only the audit of print and export requests). | AICWDF §15, BR-AUD-06 |
| STK-13 | A new dependency between modules is recorded in this table in the same change; a dependency that would create a cycle needs a redesign, not an exception. | AICWDF §15 ("record inter-module dependencies") |

## 3. Directory layout

The layout of the application. TASK-001 created it with the empty application; a folder that holds no file yet keeps an empty `.gitkeep` until the Task that adds its first file.

```text
app/
  Domain/<Module>/            rules, value objects and domain services of one module
  Actions/<Module>/           one class per command; owns the transaction
  Queries/<Module>/           read models returning DTOs
  DTO/<Module>/               response allowlists for pages and JSON
  Http/Controllers/<Module>/  thin controllers
  Http/Requests/<Module>/     FormRequests: validation allowlists and authorization
  Http/Middleware/            sign-in limits, recent authentication, headers, CSP nonce
  Policies/                   capability and object-scope policies
  Models/                     Eloquent persistence models; no business side effects
  Console/Commands/           operator procedures and scheduled commands
  Support/                    shared kernel: money, quantity, cost, business date, clock, formatting
lang/id/                      translation files, one PHP file per module (ADR-005)
resources/js/
  pages/<module>/             Inertia pages
  components/                 shadcn/ui components and application components
  layouts/
  lib/                        t(), formatters, exact-number helpers, command client (ADR-005, ADR-006, ADR-007)
  types/                      DTO and translation-key types
database/migrations/          forward-only migrations (schema: docs/04-architecture/SCHEMA.md)
tests/
  Unit/  Feature/  Concurrency/  Browser/
```

Adaptations recorded by TASK-001 for Laravel 13 and the command contract (STK-22); the module boundaries do not change:

- Laravel 13 has no HTTP or console kernel class: routing, middleware and exception handling are configured in `bootstrap/app.php`, and `app/Console/Commands/` is discovered automatically; the schedule goes in `routes/console.php`, registered in `bootstrap/app.php` by the Task that adds the first scheduled command.
- The test folders map to the command contract of TEST_STRATEGY section 7: `tests/Unit/` is `composer test:unit` and, for TypeScript, `npm run test:unit`; `tests/Feature/` is `composer test:postgres` (L2 and L4 on real PostgreSQL); `tests/Concurrency/` is `composer test:race`; `tests/Browser/` is `npm run test:e2e`.
- `tests/Network/` holds the negative proofs of the dependency audit that need the advisory services over the network, so that the L1 suites run without network (TS-08): `composer lint` runs the PHPUnit suite `Network` after `composer audit --locked`, and `npm run lint` runs `vitest.network.config.ts` after the npm audit.
- `tests/Fixtures/` holds the negative samples of the static checks, which the build, the linters and the analysis exclude; `tests/PHPStan/` holds the project's PHPStan rules.
- `scripts/checks/` holds the static checks of TEST_STRATEGY section 6 that are not a tool's own command; `docker/` holds the files of the local container environment, which TASK-012 replaces with the release image.

| ID | Rule | Source |
| --- | --- | --- |
| STK-20 | Concurrency, constraint and rollback tests run against real PostgreSQL with committed fixtures and independent connections, never inside one wrapping transaction and never on SQLite or mocks. | TOOLCHAIN, L-P4 §Testing implications (input), L-P6 |
| STK-21 | Pages receive DTOs only; TypeScript types describe those DTOs. | SECURITY_MODEL SEC-CH-01 |
| STK-22 | The layout may be adapted by the foundation Task where the pinned framework version requires it; the module boundaries do not change without an update of this document. | AICWDF §1.2, §41 |

## 4. No speculative abstraction

| ID | Rule | Source |
| --- | --- | --- |
| STK-30 | No interface, factory, plugin system or extra layer is created "just in case". A boundary is created only when AICWDF §15.1.8 gives a reason: an external service is involved, the technology may change independently of business rules, several implementations are plausible now, the roadmap names a future integration, or test isolation materially benefits. | AICWDF §15.1.8, L-P4 §Struktur modular minimal |
| STK-31 | Boundaries that do exist, and why: the authentication boundary (AICWDF §4A.9); the payment method list without a provider adapter (ARCHITECTURE section 5); file storage through the framework's storage abstraction; the clock, for testing time rules. | AICWDF §4A.9, §15.1, D-036 |
| STK-32 | Not created: a repository class per table, an event bus, CQRS, a domain-driven-design framework, a generic workflow engine, a tenant layer, a provider adapter for payments, a notification abstraction. | L-P4, D-038, D-036, ADR-002 |
| STK-33 | Standard Laravel patterns are used where they are sufficient. | AICWDF §15 |
