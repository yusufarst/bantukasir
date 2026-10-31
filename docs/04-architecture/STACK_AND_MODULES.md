# Stack and modules

| | |
| --- | --- |
| Status | CURRENT — written in RF-3A under D-055, 4 October 2026 (Asia/Jakarta); amended in RF-6 under D-088, 18 October 2026: the Operations module and its dependencies; section 3 adapted by TASK-001 under STK-22, 19 October 2026, with `tests/Network/` added the same day after its review of class B; and by TASK-004, 24 October 2026: the database infrastructure in sections 2 and 2.1, the command points in STK-31, and the folders of the provider, the setup and the test harness in section 3; and by TASK-006, 26 October 2026: the folders of authentication and of its test harness in section 3; and by TASK-007, 27 October 2026: the folders of authorization, the security baseline and the shell, with their test harness, in section 3; and by TASK-008, 28 October 2026: the folders of the command platform, with its test harness, in section 3; and by TASK-009, 28 October 2026: the folders of the command client in section 3 |
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

The database infrastructure, `app/Database/` (TASK-004, its contract's module "Infrastructure (database)"), holds what every module, migration and operator command uses of the database's roles and rights: the role marks, the environment marker (ENVIRONMENTS ENV-04), the privilege matrix's reader, the privilege check, the grants procedure, quoted identifiers and the command points of the test harness. It owns no entity and depends on no module; like the shared kernel, every module may use it.

### 2.1 Dependencies

A module may read from the modules listed for it; writes across modules happen only through an Action that calls each module's own services. The graph has no cycle.

| Module | May depend on |
| --- | --- |
| Shared kernel | — |
| Database infrastructure | — (every module may depend on it, as on the shared kernel) |
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

Folders added by TASK-004 for the database foundation; the module boundaries do not change:

- `app/Database/` is the database infrastructure of section 2. It is not part of the shared kernel of `app/Support/`, so the exact-value rule of PHPStan does not apply to it; it handles no money, quantity or cost.
- `app/Providers/` holds `DatabaseServiceProvider`, registered in `bootstrap/providers.php`: the migration discipline (EXECUTION_CONTEXT EXC-41). The framework needs a service provider for it (STK-22).
- `database/setup/` holds the setup script of ENVIRONMENTS ENV-30 and the local and CI runner and self-test that the service `db-setup` of `compose.yaml` runs; `database/privileges.php` is the machine-readable privilege matrix of SCHEMA section 10, which each table's Task extends (EXC-43).
- `tests/Harness/` holds the test harness — the test database, the helpers of the test families, the race and failure-injection harness and the fixture builder of "Toko Contoh" — which `tests/Feature/` and `tests/Concurrency/` use; `tests/Fixtures/Migrations/` holds test-only migrations.

Folders added by TASK-005 for the design system; the module boundaries do not change:

- `resources/js/components/ui/` holds the design system's components (DESIGN_SYSTEM section 8), the shortcut registry and the catalogue of the states; `resources/js/layouts/` the layout primitives and page archetypes; `resources/css/` the tokens, the font faces, the base layer and the components' stylesheet; `public/fonts/inter/` the self-hosted Inter files with their licence.
- `app/Http/ContentSecurityPolicy.php` builds the policy of SECURITY_MODEL SEC-HDR-03 for one request's nonce; the middleware of TASK-007 applies it to every response.
- `app/Http/Controllers/Testing/` and `resources/js/pages/Testing/` hold the component specimen, which `routes/web.php` registers only in the local and testing environments; it is not a route of ROUTE_CONTRACTS. `tests/Browser/support/` holds the browser harness that every interface Task reuses: the widths, the watch of the page (CSP, console, origin) and the measurements.

Folders added by TASK-006 for authentication; the module boundaries do not change:

- `app/Http/Auth/` holds the Access module's side of Fortify: the stages of the sign-in pipeline, the responses that Fortify's contracts ask for, the times of the sign-in session, the rate limits, and the answers to a signed-out request and to an invalid link. `app/Providers/AccessServiceProvider.php`, registered in `bootstrap/providers.php`, binds them; the framework needs a service provider for it (STK-22).
- `tests/Harness/Http/` holds the harness of several browsers' sessions in one test; `tests/Harness/Access/` the race scenarios of the one-time link; `tests/Harness/Browser/` the fixtures of the browser tests of the Access pages, whose routes `routes/web.php` loads only where the specimen is enabled and the file exists.

Folders added by TASK-007 for authorization, the security baseline and the shell; the module boundaries do not change:

- `app/Domain/Access/` holds the capability map (`Capability`, `Grant`, `Capabilities`), the actor guard of SEC-AZ-04 and the homes of NC-12; `app/Policies/` the object-scope pattern (`ObjectScope`, `NotFound`); `app/Http/Errors/` the refusals the application answers itself, with the error body of ROUTE_CONTRACTS RT-07 or the error page; `app/Http/Middleware/` the capability gate, the input allowlist, the security headers, trusted hosts and proxies, HTTPS and recent authentication; `config/security.php` the settings of the baseline that differ per environment; `app/DTO/PageData.php` the base of every DTO.
- `app/Domain/Operations/` holds the database check of the health route RH-01, a listener of the framework's health event; `app/Providers/OperationsServiceProvider.php`, registered in `bootstrap/providers.php`, registers it, because the framework discovers listeners only in `app/Listeners` (STK-22). RH-01 itself is registered in `bootstrap/app.php`, outside the web group, so that it runs no session middleware. `app/Domain/Operations/Backup/` holds `backup:run` of TASK-013, and `app/Domain/Operations/Heartbeat.php` and `OutboundHttp.php` the ping client and the one outbound HTTP client; PHP measures free disk space as a float, which this module does not hold (ADR-006 item 5), so `app/Console/LocalDiskSpace.php` measures it behind the module's interface `DiskSpace` (STK-22).
- `resources/js/layouts/AppShell.tsx` is the shell UXP-000 with its timers; `resources/js/lib/` holds its navigation registry, which each page Task extends, the timers' arithmetic, the browser's end of a sign-in session and the states of the shared answers; `resources/js/pages/shell/` the error page; `resources/js/pages/Testing/ShellSpecimen.tsx` the specimen of the shell, local and testing only.
- `tests/Harness/Channels/` holds the payload inspection of the sensitive channels with their allowlists per role; `tests/Harness/Http/RouteInventory.php` the rules of the route inventory; `tests/Harness/Access/PermissionMatrix.php` reads the permission matrix of SECURITY_MODEL section 6, which the capability map and the gates are compared with. Every later Task extends the inventory, the matrix test and the allowlists for what it adds (EXECUTION_CONTEXT section 10).

Folders added by TASK-008 for the command platform; the module boundaries do not change:

- `app/Domain/Commands/` holds the server's fixed list of operations, the error codes of TECHNICAL_RULES section 4, the envelope and its fingerprint, the identities and results, the request receipts, the stale-intent reports and the recovery epoch; `app/Actions/Commands/` the Action base of every command (`CommandAction`), its transaction (`CommandTransaction`) and the status check RC-50 (`ResolveIntent`); `app/Http/Requests/Commands/` the FormRequest base of every command and the requests of RC-50 and RD-04; `app/Http/Controllers/Commands/` the base of every command's controller and the controllers of RC-50 and RD-04; `app/Queries/Commands/` and `app/DTO/Commands/` the read RD-04 and the allowlisted answers.
- `app/Http/Commands/` holds the pipeline of a command request after its middleware — the request receipt, the FormRequest, the Action, the marking, the security log and the answer — and the received time of TR-TIME-03, which the web server passes as the server parameter `REQUEST_RECEIVED_AT` (`docker/app/nginx.conf`). The pipeline resolves the FormRequest itself, after the receipt, because a FormRequest type-hinted on the controller would validate before the receipt is written (STK-22). `app/Database/SqlState.php` names the PostgreSQL error classes the pipeline answers.
- The Query of RD-04 reads the display names of the Access module's accounts for the Owner's `scope=all`; it is a read of the application layer, like an Action's orchestration, and adds no dependency of the Commands module (section 2.1).
- `tests/Harness/Commands/` holds the probe operation `testing.probe`, registered only in the testing environment, with its race scenarios and the deadlock injection; every command Task proves the platform again for itself (EXECUTION_CONTEXT section 10).

Folders added by TASK-009 for the command client; the module boundaries do not change:

- `resources/js/lib/commands/` holds the command client of every page: the families of INTERACTION_CONTRACTS section 1.4 with the block of NC-13, the reading of answers into client states, the frozen request and its sending, the references of SECURITY_MODEL SEC-CH-05, the user's unresolved requests with the shell's refresh, the hook `useCommand` of a page's command, and its shared panels (`Panels.tsx`, a component beside the logic it renders, as `AppShell.tsx` is beside its timers); `resources/js/pages/commands/` the page UXP-006; `resources/js/pages/Testing/CommandSpecimen.tsx` the command client's specimen, local and testing only.
- `app/Queries/Access/ShellIndicators.php` and `app/DTO/Access/ShellIndicatorsData.php` are RD-03, which reads the count of the Commands module (`app/Queries/Commands/UnresolvedRequestCount.php`), a dependency that section 2.1 already lists.
- The probe of `tests/Harness/Commands/` is also registered in the local environment, which the browser suite runs against (`Operations::PROBE_ENVIRONMENTS`), and `tests/Harness/Browser/` gives the browser tests the command platform's fixtures.

| ID | Rule | Source |
| --- | --- | --- |
| STK-20 | Concurrency, constraint and rollback tests run against real PostgreSQL with committed fixtures and independent connections, never inside one wrapping transaction and never on SQLite or mocks. | TOOLCHAIN, L-P4 §Testing implications (input), L-P6 |
| STK-21 | Pages receive DTOs only; TypeScript types describe those DTOs. | SECURITY_MODEL SEC-CH-01 |
| STK-22 | The layout may be adapted by the foundation Task where the pinned framework version requires it; the module boundaries do not change without an update of this document. | AICWDF §1.2, §41 |

## 4. No speculative abstraction

| ID | Rule | Source |
| --- | --- | --- |
| STK-30 | No interface, factory, plugin system or extra layer is created "just in case". A boundary is created only when AICWDF §15.1.8 gives a reason: an external service is involved, the technology may change independently of business rules, several implementations are plausible now, the roadmap names a future integration, or test isolation materially benefits. | AICWDF §15.1.8, L-P4 §Struktur modular minimal |
| STK-31 | Boundaries that do exist, and why: the authentication boundary (AICWDF §4A.9); the payment method list without a provider adapter (ARCHITECTURE section 5); file storage through the framework's storage abstraction; the clock, for testing time rules; the command points of a command's transaction (`App\Database\CommandPoints`, TASK-004), for the race and failure-injection tests of TEST_STRATEGY TS-20 and TS-21, bound in the application to points that do nothing. | AICWDF §4A.9, §15.1, D-036, TS-20, TS-21 |
| STK-32 | Not created: a repository class per table, an event bus, CQRS, a domain-driven-design framework, a generic workflow engine, a tenant layer, a provider adapter for payments, a notification abstraction. | L-P4, D-038, D-036, ADR-002 |
| STK-33 | Standard Laravel patterns are used where they are sufficient. | AICWDF §15 |
