# Architecture

| | |
| --- | --- |
| Status | CURRENT — written in RF-3A under D-055, 4 October 2026 (Asia/Jakarta) |
| Phase | P4 — application architecture (the logical database schema is RF-3B) |
| Replaces | The architecture part of `docs/04-architecture/legacy/02-ARCHITECTURE.md` and of `docs/04-architecture/legacy/P4-ARCHITECTURE.md`, except their schema and database-constraint parts, which stay LEGACY — IN FORCE until RF-3B ([LEGACY_INDEX](../00-governance/LEGACY_INDEX.md) section 8) |
| Decisions | D-004, D-005, D-006, D-036, D-038, D-053, D-054; [ADR-001](../adr/ADR-001-stack-and-modular-monolith.md) to [ADR-007](../adr/ADR-007-command-identity-and-idempotency.md) |
| Related | [STACK_AND_MODULES](STACK_AND_MODULES.md), [SECURITY_MODEL](../05-security/SECURITY_MODEL.md), [TECHNICAL_RULES](../06-api-performance/TECHNICAL_RULES.md), [DOMAIN_MODEL](../02-domain/DOMAIN_MODEL.md) |

How BantuKasir is built: its context, layers, units of work, read models, the payment, media and document boundaries, runtime constraints and database privilege separation. It enforces the product and domain rules and adds no business meaning. Every rule names its source: a decision `D-…`, a CURRENT document, or a legacy source key from [DOMAIN_MODEL](../02-domain/DOMAIN_MODEL.md) section 2. Rules are numbered `ARC-nn`.

Not in this document: tables, columns, keys, constraints and the mapping of invariants to database enforcement (RF-3B, OPEN_ITEMS T-03); route and interaction contracts (RF-5); infrastructure runbooks, backup design and the branch model (RF-6).

## 1. System context

```text
Shop staff and Owner (PC, tablet; Owner on any supported device)
        │  HTTPS, same origin
        ▼
Cloudflare (DNS and proxy)                       — ADR-003
        ▼
VPS: Coolify-managed reverse proxy (TLS)         — ADR-003
        ▼
Application container per environment            — ADR-002, ADR-003
  Nginx + PHP-FPM + Laravel (Inertia, React build)
  ├── private file storage (logos, product photos, thumbnails)
  ├── private temporary area (CSV uploads; never backed up)
  └── scheduler, started once a minute (scheduled commands)
        ▼
PostgreSQL per environment (private network only)

Outside the server: the independent backup destination (question 18, before RF-6)
```

One installation serves one business with one stock pool; another shop has its own installation, database, storage and configuration (D-038, BR-DOM-01). Production and staging are separate environments on the same server (D-053).

## 2. Layers

| ID | Rule | Source |
| --- | --- | --- |
| ARC-01 | **Web layer.** Routes, middleware, thin controllers, FormRequests and Inertia responses or JSON responses. It authenticates, applies rate limits and CSRF protection, validates input against allowlists, checks the capability and object scope, calls one Action or one Query, and returns an allowlisted DTO. It holds no business rule. | AICWDF §15, L-P4 §Struktur modular minimal, TECHNICAL_RULES TR-HTTP-03 |
| ARC-02 | **Application layer.** One Action per command owns the unit of work (section 3). One Query per read model returns DTOs for one role and channel. Actions orchestrate the domain modules; modules do not orchestrate each other. | L-P4, L-02 |
| ARC-03 | **Domain modules.** Rules, value objects (money, quantity, cost, business date), domain services and policies, grouped by the modules of STACK_AND_MODULES. Domain code is plain PHP where practical and does not depend on HTTP or Inertia. | AICWDF §15, L-P4 |
| ARC-04 | **Infrastructure.** Persistence through Eloquent and the query builder, private file storage, the clock, the process API for the few operator and scheduled commands that need an external program, and logging. Eloquent models are persistence objects: no business side effect lives in a model, observer, event or listener. | L-P4 §Struktur modular minimal, TECHNICAL_RULES TR-TX-02 |
| ARC-05 | **Frontend.** React pages render server DTOs and hold drafts. The browser is never the authority for prices, totals, stock, cost or permissions (BR-DOM-06); it may compute previews from exact strings (ADR-006). All copy comes through translation keys (ADR-005). | BR-DOM-06, ADR-005, ADR-006 |
| ARC-06 | **Not used.** Microservices, a separate API service, server-side rendering, an event bus, CQRS, a repository class per table, a domain-driven-design framework, distributed locks and queues (ADR-001, ADR-002). | L-P4 §Struktur modular minimal, D-005 |

## 3. Units of work

| ID | Rule | Source |
| --- | --- | --- |
| ARC-10 | One command is one Action and one PostgreSQL transaction. The Action opens the transaction, takes the locks in the order of TECHNICAL_RULES section 7, calls the domain services of every module the command touches, and commits the facts, projections, audit event and command result together, or nothing (BR-DOM-04). | D-006, L-02, L-P4 |
| ARC-11 | Domain services receive the open transaction; none opens a connection or commits on its own. A Sale therefore writes Sales, Inventory, Costing, Cash, Documents and Audit records in the same transaction (BR-SALE-17). A refund writes the refund, any return movement, any cost restoration, the session effect, audit and result in one transaction (BR-RFD-10). | L-P4 §Struktur modular minimal, D-035, D-040 |
| ARC-12 | Nothing that waits for a person, a printer or the network happens inside the unit of work. Payment by the customer, a bank transfer, counting cash, physical cash handling (including the drawer payout of a refund, after commit) and printing are outside it (BR-DOM-10; TECHNICAL_RULES TR-TX-03). | BR-DOM-10, D-046, L-02 |
| ARC-13 | After commit there is no further business effect: no event fan-out, no deferred job. The response tells the browser what to show or print. | ADR-002 |
| ARC-14 | Command identity, idempotency and recovery follow ADR-007 and are implemented once, in the Commands module (STACK_AND_MODULES). | D-006, D-040 |

## 4. Read models and projections

| ID | Read model | Rule | Source |
| --- | --- | --- | --- |
| ARC-20 | Stock balance | A synchronous projection of the stock movements, updated in the same transaction as each movement; never edited directly; rebuilt only by the controlled reconciliation procedure from immutable movements. | BR-INV-03, BR-DOM-03, L-06 |
| ARC-21 | Inventory valuation and corrected cost projection | The current moving-weighted-average valuation per product, changed by postings and by a replay that publishes all corrected values atomically. How issue-cost allocations and corrected values are represented is OPEN — T-16, RF-3B. | D-034, BR-COST-25, COSTING section 10 |
| ARC-22 | Stock attention | Derived from the current balance and minimum stock in one query; never stored as a second truth. | BR-INV-40, BR-INV-41 |
| ARC-23 | Product Search | Queries over products and barcode tokens with PostgreSQL's own indexes, server-paged, one matching logic shared by every workflow, results built per role. | BR-CAT-23 to BR-CAT-27, L-02 §Batas read |
| ARC-24 | Reports (finance report, daily summary) | Computed from source facts — Sales, payments, refunds, returns, cost allocations and corrections — in one read-only snapshot per report, with its as-of time and completeness; session snapshot totals are never added on top. How a report identifies the cost-evidence state it reflects is RF-3B. | BR-COST-34 to BR-COST-38, BR-RPT-01, TECHNICAL_RULES TR-TX-07 |
| ARC-25 | Receipt snapshot and cash session report snapshot | Immutable accountability documents written in the transaction of the Sale or the close; they are not a second source of totals. | BR-DOC-01, BR-CASH-20, BR-DOM-03 |
| ARC-26 | Daily reconciliation | A scheduled read-only comparison of movements with balances and cost projections. A mismatch is recorded as an incident and blocks the product's postings through a command; repair is a controlled rebuild from facts. | BR-INV-45, BR-INV-46 |
| ARC-27 | Expected cash | Derived from the session's committed facts (opening cash, applied CASH payments, drawer-funded refunds) under the session lock whenever a command depends on it. If RF-3B adds a maintained projection, it is updated in the same transaction and reconciled. | D-035, D-045, BR-CASH-12 |

## 5. Payment boundary

| ID | Rule | Source |
| --- | --- | --- |
| ARC-30 | The payment methods are an explicit server-side list: CASH, BANK_TRANSFER and STATIC_QRIS. One place in the Sales module defines each method's validation: tendered cash and change for CASH; the cashier's confirmation that the funds were received and an optional reference for BANK_TRANSFER and STATIC_QRIS. Any other value is refused. | D-036, D-003, BR-PAY-01, BR-PAY-07 |
| ARC-31 | There is no payment provider: no gateway adapter or stub, no provider table, controller, webhook, callback, polling or asynchronous or pending payment state. A completed Sale never becomes pending. | D-036, BR-PAY-04, BR-PAY-07 |
| ARC-32 | A future provider would be an additive, reviewed change behind this boundary: the method list and its validation are the only seam. No adapter interface is created now, because no real variability exists in V1 (AICWDF §15.1.8); this is the framework's own rule against speculative abstraction, not a deviation. | D-036, AICWDF §15.1.2, §15.1.8 |
| ARC-33 | Provider fees, settlement delay and net settlement are not modelled; a Sale is recorded at the gross amount paid. | D-036, BR-PAY-05 |

## 6. Media and document boundary

| ID | Rule | Source |
| --- | --- | --- |
| ARC-40 | Logos (each BusinessProfile revision) and product photos with their thumbnails are stored in private storage outside the web root, under random keys that never change. A stored file is never overwritten and never deleted; a replaced or removed photo's file stays and is no longer shown (D-042); a logo referenced by a document is kept for reproduction (BR-CFG-03). | D-037, D-038, D-042, L-02 §Runtime, asset |
| ARC-41 | An upload is validated, decoded and re-encoded, and written under a new key **before** the command's transaction; the transaction records the reference. An unreferenced file left by a rolled-back command is removed by bounded housekeeping, only after a grace period well above the longest request (TECHNICAL_RULES TR-TX-05). | TECHNICAL_RULES TR-TX-05, SECURITY_MODEL section 13 |
| ARC-42 | Media are delivered only through an authorized route; the web server never serves the storage directory. | SECURITY_MODEL SEC-UP-06, BR-DOC-06 |
| ARC-43 | A CSV import file is temporary working data of its command: kept in a private temporary area between upload, preview and confirmation, removed when the command reaches its final lifecycle outcome and in any case by housekeeping once it is older than 24 hours, never backed up and never served. The import result is kept in the audit. | D-054, D-037 |
| ARC-44 | Receipts and cash session reports are rendered from their immutable snapshots through browser-print templates for thermal 58 mm, thermal 80 mm and A4; a PDF is the browser's print to PDF. There is no server PDF engine. | D-039, BR-DOC-02, L-02 §Runtime, asset |
| ARC-45 | A template referenced by a snapshot is never changed in place. A template change is a new template version kept in the code base, so every earlier document is reproduced exactly. | BR-DOC-01, BR-DOC-05, D-038 |
| ARC-46 | CSV exports are generated on the server from per-role DTOs, formula-safe, and their requests are audited; they create and change no fact. | BR-CASH-22, BR-AUD-06 |
| ARC-47 | Barcode labels are Code 128 SVG rendered from the token by an open-source library with a verified licence, printed through the browser; a label never contains cost. | BR-CAT-35, BR-CAT-36 |

## 7. Time and numbers

| ID | Rule | Source |
| --- | --- | --- |
| ARC-50 | The application clock is UTC; every fact time is server UTC. The business date and report periods use Asia/Jakarta, computed by the server; the scheduler uses Asia/Jakarta as its schedule timezone. | BR-NUM-05, BR-NUM-06, ADR-002 |
| ARC-51 | Money, quantity and cost follow ADR-006 everywhere: storage, calculation, transport and display. | D-006, BR-NUM-01 to BR-NUM-04 |

## 8. Runtime constraints

| ID | Rule | Source |
| --- | --- | --- |
| ARC-60 | The runtime is the minimum of ADR-002: Nginx and PHP-FPM serving Laravel, private PostgreSQL, database sessions, a database cache used only for rate limiting and scheduler locks, and scheduled commands. No queue, Redis, server-side rendering or websocket. | D-005, ADR-002 |
| ARC-61 | The hosting topology is ADR-003: Cloudflare, a VPS, Coolify; production and staging as separate environments on the same server with separate databases, storage and secrets. | D-053, ADR-003 |
| ARC-62 | Every command finishes inside its request within the limits of TECHNICAL_RULES section 9; a bounded heavy command that cannot finish is rejected with no partial change. | ADR-002, BR-COST-27 |
| ARC-63 | Each environment's files live on a persistent volume of that environment; the temporary CSV area is separate and excluded from backups. | ADR-003, D-054 |
| ARC-64 | Migrations are forward and additive, rehearsed on a populated isolated target, and applied as a controlled job, never at start-up. How an approved migration reaches production, and by whom, is RF-6 with question 20. | PRODUCTION_DATA_SAFETY section 2, L-P4 §Schema (procedure), D-053 |
| ARC-65 | The independent encrypted backup of database and files is mandatory; its destination is question 18 (before RF-6). | D-005, ADR-002, REQ-OPS-01 |

## 9. Database privilege separation

| ID | Rule | Source |
| --- | --- | --- |
| ARC-70 | The application connects with a **runtime role** that cannot change the schema and cannot update or delete immutable business facts (stock movements, Sales and their lines, payments, receipt snapshots, refunds, returns, cost evidence and corrections, session report snapshots, audit events, command results). It may change only mutable projections and master data, and append facts. | PRODUCTION_DATA_SAFETY section 3, L-P4 §Schema, immutability dan constraints, L-09 |
| ARC-71 | A separate **migration role** is available only to the controlled migration job; a separate **backup role** has the minimum read access the backup needs. | PRODUCTION_DATA_SAFETY section 3, L-09 |
| ARC-72 | The exact grants per table are designed in RF-3B, and the separation is proven by tests in which the runtime role is refused schema changes and changes to history. | PRODUCTION_DATA_SAFETY section 3, REQ-OPS-02 |

## 10. Configuration and identity

| ID | Rule | Source |
| --- | --- | --- |
| ARC-80 | The code is client-neutral. The shop's identity — name, logo, address, contact, document header and footer, document identity, constrained accent — comes only from the BusinessProfile and its revisions; documents freeze the revision in force. | D-038, BR-DOM-11, BR-CFG-01 to BR-CFG-05 |
| ARC-81 | Environment settings and secrets come from the platform's per-environment configuration and are validated at start-up without printing values (SECURITY_MODEL section 8). | L-04 §Konfigurasi, ADR-003 |
| ARC-82 | Only the configuration needed for one business per installation exists: no tenant, outlet or multi-company setting. | D-038, BR-CFG-05 |

## 11. Observability

| ID | Rule | Source |
| --- | --- | --- |
| ARC-90 | Application logs are written to the container's standard output and collected by the platform; the security log is a separate channel, stored in the database after the business transaction ends and also written to standard output (SECURITY_MODEL SEC-LOG-03). Every log line carries a request identifier; no line contains a sensitive value (SECURITY_MODEL SEC-CH-08). | L-09, L-04 |
| ARC-91 | The Owner's system status shows the last backup and restore, their age, failures, disk space and reconciliation, and "Belum Terverifikasi" for anything unproven. | REQ-OPS-04, L-09 |
| ARC-92 | Uptime checking from outside the server, error tracking and monitoring are designed in RF-6 at zero additional cost unless the Owner approves otherwise. | COST_POLICY, AICWDF §20 |

## 12. Boundary with RF-3B and later steps

- RF-3B designs the logical schema, the mapping of invariants to constraints, locks, guards and tests, the representation of issue-cost allocations (T-16) and items T-17 e, g and i. Until it is accepted, the schema and database-constraint parts of `02-ARCHITECTURE` §Constraint database and of `P4-ARCHITECTURE` §Schema, immutability dan constraints remain LEGACY — IN FORCE.
- RF-4 builds on ADR-005 (localization) and on the capabilities of SECURITY_MODEL section 6 for navigation per role.
- RF-5 writes route and interaction contracts within TECHNICAL_RULES.
- RF-6 writes infrastructure, backup and release documents within ADR-002 and ADR-003, and rebuilds the test strategy; the testing and deployment sections of `P4-ARCHITECTURE` are its input only.
