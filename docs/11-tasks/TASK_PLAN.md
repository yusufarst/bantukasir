# Task plan

| | |
| --- | --- |
| Status | CURRENT — written in RF-7 under D-090, 18 October 2026 (Asia/Jakarta); corrected under D-091 the same day, after the independent review of the baseline; approved by the Owner with every Task (gate GATE-P11), and corrected for review finding R-1, under D-092 the same day |
| Phase | P11 — Task planning, dependencies and execution specifications |
| Replaces | `docs/11-tasks/legacy/11-BUILD-PLAN.md`, `docs/11-tasks/legacy/P11-BUILD-UNITS.md` and `docs/11-tasks/legacy/build-units/` (LEGACY — SUPERSEDED, [LEGACY_INDEX](../00-governance/LEGACY_INDEX.md) section 16); decision D-012 |
| Decisions | D-012, D-031, D-066, D-088, D-090, D-091, D-092; FD-07, FD-08 |
| Related | [EXECUTION_CONTEXT](EXECUTION_CONTEXT.md), [DEPENDENCY_GRAPH](DEPENDENCY_GRAPH.md), [TRACEABILITY](TRACEABILITY.md), the Task contracts in [tasks/](tasks/), [TEST_STRATEGY](../08-testing/TEST_STRATEGY.md) |

The implementation baseline of BantuKasir V1: 50 Tasks, their dependencies, gates and status, the order in which to run them, and where every requirement, workflow, suite, route, table and control is built. Nothing here authorizes a Task: each one starts only on the Owner's explicit instruction ([EXECUTION_CONTEXT](EXECUTION_CONTEXT.md) EXC-10), and none starts before gate GATE-P11.

## 1. How to read

- **Identifiers** are `TASK-NNN`, permanent and never reused (FD-08, EXC-80). Their order is the recommended order for one executor and is also a valid dependency order: every Task depends only on Tasks with a smaller number.
- **Authority.** This plan is the record of the list of Tasks, their dependencies, gates and status. Each Task contract in [tasks/](tasks/) is the record of that Task's scope, acceptance criteria, tests, evidence and Definition of Done. [DEPENDENCY_GRAPH](DEPENDENCY_GRAPH.md) and [TRACEABILITY](TRACEABILITY.md) are generated from the same data; where a contract and this plan disagree, the plan prevails for dependencies, gates and status, the contract for scope, and the difference is a defect to correct.
- **Status** uses the vocabulary of PROJECT_RULES section 7 with the entry rules of EXECUTION_CONTEXT section 4. Section 4 below is the only place a status is recorded.
- **Size** — S, M, L or XL — is a relative planning judgement of the work, used to weigh the critical path; it is not an estimate of hours and promises no date.
- **Review class** — A, B, O or V — sets the reviews before a Task is done (EXECUTION_CONTEXT section 12).
- **Lanes** group Tasks that can run beside each other with separate executors (EXC-12): P platform trunk; D documents and change control; O operations and delivery; C catalogue; I inventory and costing; S cash and sales; R reports, audit views and release; V verification.

## 2. The baseline in numbers

| Count | Value |
| --- | --- |
| Tasks | 50 |
| Implementation Tasks (code, migrations, pages, operator tooling) | 41: TASK-001, TASK-003 to TASK-042 |
| Documentation and change-control Tasks | 2: TASK-002, TASK-047 |
| Verification and release Tasks (FD-07), the performance harness included | 7: TASK-043 to TASK-046, TASK-048 to TASK-050 |
| Phase 1 Foundation | 5 |
| Phase 2 Platform core | 6 |
| Phase 3 Delivery infrastructure | 3 |
| Phase 4 Catalogue | 6 |
| Phase 5 Inventory and costing | 6 |
| Phase 6 Cash and sales | 8 |
| Phase 7 Onboarding, reconciliation and operations | 5 |
| Phase 8 Reports and audit views | 3 |
| Phase 9 Verification | 6 |
| Phase 10 Release | 2 |
| By size | S 1, M 18, L 27, XL 4 |
| By review class | A 37, B 5, O 2, V 6 |
| Waves of parallel work (DEPENDENCY_GRAPH section 4) | 32 |

## 3. Gates

Gates that a Task waits for, besides its dependencies. GATE-P11 to GATE-CC2 are gates of this plan; the others are those of [OPEN_ITEMS](../00-governance/OPEN_ITEMS.md) section 3, unchanged.

| Gate | What must be true | Blocks | State today |
| --- | --- | --- | --- |
| GATE-P11 | The Owner approves this Task baseline (RF-7, D-090): the execution context, this plan, the dependency graph, the traceability and the 50 Task contracts. OPEN_ITEMS N-04 (whether the AICWDF-4.3 text joins the repository) has the deadline "RF-7 at the latest" and is decided with it. | Every Task | APPROVED — D-092, with N-04 decided |
| GATE-BRANCH | OPEN_ITEMS N-12: the Owner decides when and how `develop` is created and `main` is brought forward (ADR-011 item 3); each branch operation and push needs its own authorization. RF-7 itself needs no transition (T-24, resolved in RF-7). | TASK-001 and every later implementation Task (EXC-22); and the `DONE` of TASK-002, which changes documents only: TASK-002 may be written before this gate, on the branch the Owner names, and is merged into `develop` after TASK-001 (EXC-37) | OPEN — the branch model is decided (D-092); `develop` does not exist yet, and its creation is a branch operation that needs the Owner's own authorization |
| GATE-PUBLISH | Before the first push of implementation work: OPEN_ITEMS N-09 decided and the one-time audit N-10 completed; before every push, the publication guard of D-076 (EXC-31). A pull request, its CI run and a merge into `develop` need a push. | The `DONE` of TASK-001 — its first push, its pull request, CI and its merge into `develop` — and so the start of every later implementation Task, all of which depend on TASK-001; every push after it. TASK-001 itself may start and be built and verified locally before it (EXC-31) | OPEN — Owner |
| GATE-CC1 | The Owner approves, by the Git blob, size and SHA-256 of each changed artifact, the change-control package of TASK-002 (OPEN_ITEMS T-19, T-20). | TASK-005, TASK-009, TASK-010 and every interface Task that uses a key it adds | OPEN — after TASK-002 |
| GATE-CC2 | The Owner approves by hash the artifacts that TASK-047 changes, when gate DEVICES changes a shortcut or a breakpoint; no approval is needed when the values are confirmed. | TASK-047 | OPEN — after TASK-046 |
| EXECUTOR | The executor runs as Claude Opus 5.5 at effort Max (D-066; OPEN_ITEMS section 3). | Every Task | Checked at the start of each Task |
| TARGET | The real server, Cloudflare, access, TLS and capacity, recorded by the operator (ENVIRONMENTS section 17; OPEN_ITEMS section 3). The operator is named by the Owner (RELEASE_PROCESS section 13). | TASK-044 (staging part), TASK-045, TASK-046, TASK-048, TASK-049 | OPEN |
| DEVICES | The shop's real devices, printers and media, and the Owner's decision on DEVICE01 (DEVICE_VERIFICATION). | TASK-046, TASK-049 | OPEN |
| G1 | The tax and legal position and the commercial receipt policy (OPEN_ITEMS section 3). | TASK-049 | OPEN |
| G2 | The number of products and the product list; base units and precision; the opening stock volume (OPEN_ITEMS section 3; T-09). | TASK-048 (final dataset), TASK-049 | OPEN |
| G3 | The drawers, the opening-float practice, shared devices (OPEN_ITEMS section 3). | TASK-049 | OPEN |
| G4 | The store account, buckets and keys; the sealed copy; the first production backup and drill; the measured recovery point and time accepted (BACKUP_RECOVERY section 12). | TASK-045 (store), TASK-049 (sealed copy), TASK-050 (acceptance) | OPEN — destination class, cost and custody decided (D-088) |
| RELEASE | The Owner's authorization of the exact target, revision and operation; the operator named; the runbooks approved (RELEASE_PROCESS section 12). | TASK-050 | OPEN |
| TESTS | Every acceptance suite on the release candidate (OPEN_ITEMS section 3; TEST_STRATEGY section 9). | TASK-044 to TASK-050 | OPEN — nothing built |

## 4. Task register

The status of record of every Task. Contracts: [tasks/](tasks/).

| Task | Title | Phase | Lane | Size | Review | Depends on | Gates | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| [TASK-001](tasks/TASK-001-toolchain-ci.md) | Toolchain, repository conventions and CI | 1 | P | L | B | — | GATE-P11, GATE-BRANCH; before `DONE`: GATE-PUBLISH | `TODO` |
| [TASK-002](tasks/TASK-002-p7-change-control-1.md) | P7 and copy change control, batch 1 | 1 | D | M | O | — | GATE-P11; before `DONE`: GATE-BRANCH, GATE-PUBLISH | `READY` |
| [TASK-003](tasks/TASK-003-kernel-localization.md) | Shared kernel, formatting and localization | 1 | P | M | A | TASK-001 | — | `TODO` |
| [TASK-004](tasks/TASK-004-database-foundation.md) | Database foundation, roles and test harness | 1 | P | L | A | TASK-001, TASK-003 | — | `TODO` |
| [TASK-005](tasks/TASK-005-design-system.md) | Design system and frontend foundation | 1 | P | L | B | TASK-002, TASK-003 | GATE-CC1 | `TODO` |
| [TASK-006](tasks/TASK-006-authentication.md) | Authentication, one-time links, audit and security log | 2 | P | L | A | TASK-004, TASK-005 | — | `TODO` |
| [TASK-007](tasks/TASK-007-authorization-shell.md) | Authorization framework, security baseline and application shell | 2 | P | L | A | TASK-006 | — | `TODO` |
| [TASK-008](tasks/TASK-008-command-platform.md) | Command platform (server) | 2 | P | L | A | TASK-007 | — | `TODO` |
| [TASK-009](tasks/TASK-009-command-client.md) | Command client, recovery and unresolved requests | 2 | P | M | A | TASK-008, TASK-002 | GATE-CC1 | `TODO` |
| [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | Installation bootstrap, BusinessProfile and media | 2 | P | L | A | TASK-008, TASK-002 | GATE-CC1 | `TODO` |
| [TASK-011](tasks/TASK-011-user-management.md) | User management and Owner procedures | 2 | P | L | A | TASK-009, TASK-010 | — | `TODO` |
| [TASK-012](tasks/TASK-012-application-image.md) | Application image and runtime configuration | 3 | O | M | B | TASK-004, TASK-007 | — | `TODO` |
| [TASK-013](tasks/TASK-013-independent-backup.md) | Independent backup | 3 | O | L | A | TASK-012, TASK-010 | — | `TODO` |
| [TASK-014](tasks/TASK-014-release-pipeline.md) | Release workflow, preflight and migration job | 3 | O | M | A | TASK-013 | — | `TODO` |
| [TASK-015](tasks/TASK-015-catalog-core.md) | Categories, products, prices and Product Search (server) | 4 | C | L | A | TASK-011 | — | `TODO` |
| [TASK-016](tasks/TASK-016-barcodes.md) | Barcodes and exact lookup (server) | 4 | C | M | A | TASK-015 | — | `TODO` |
| [TASK-017](tasks/TASK-017-catalog-pages.md) | Catalogue pages | 4 | C | L | B | TASK-016 | — | `TODO` |
| [TASK-018](tasks/TASK-018-photos-labels.md) | Product photos and barcode labels | 4 | C | M | A | TASK-017 | — | `TODO` |
| [TASK-019](tasks/TASK-019-csv-import.md) | CSV product import | 4 | C | M | A | TASK-017 | — | `TODO` |
| [TASK-020](tasks/TASK-020-barcode-correction.md) | Barcode misassignment correction | 4 | C | M | A | TASK-017 | — | `TODO` |
| [TASK-021](tasks/TASK-021-ledger-receiving.md) | Stock ledger, valuation and receiving (server) | 5 | I | XL | A | TASK-016 | — | `TODO` |
| [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | Receiving, stock and history pages | 5 | I | L | A | TASK-021, TASK-017 | — | `TODO` |
| [TASK-023](tasks/TASK-023-manual-issue.md) | Manual issue and issue-cost allocation | 5 | I | L | A | TASK-022 | — | `TODO` |
| [TASK-024](tasks/TASK-024-stock-adjustment.md) | Stock adjustment | 5 | I | M | A | TASK-023 | — | `TODO` |
| [TASK-025](tasks/TASK-025-cost-correction.md) | Cost correction, completion and bounded replay | 5 | I | XL | A | TASK-024 | — | `TODO` |
| [TASK-026](tasks/TASK-026-reversal.md) | Reversal of movements | 5 | I | L | A | TASK-025 | — | `TODO` |
| [TASK-027](tasks/TASK-027-registers-session-open.md) | Cash registers and cash session open | 6 | S | M | A | TASK-011, TASK-022 | — | `TODO` |
| [TASK-028](tasks/TASK-028-checkout.md) | Sale checkout (server) | 6 | S | XL | A | TASK-027, TASK-023, TASK-025, TASK-026, TASK-016 | — | `TODO` |
| [TASK-029](tasks/TASK-029-till-pages.md) | Till, payment and receipt pages | 6 | S | L | A | TASK-028, TASK-017 | — | `TODO` |
| [TASK-030](tasks/TASK-030-session-close.md) | Cash session close and session pages | 6 | S | L | A | TASK-029 | — | `TODO` |
| [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | Receipt and session documents, sales lookup, print and export | 6 | S | L | A | TASK-030 | — | `TODO` |
| [TASK-032](tasks/TASK-032-assisted-close-notes.md) | Owner-assisted close and session review notes | 6 | S | M | A | TASK-030, TASK-011 | — | `TODO` |
| [TASK-033](tasks/TASK-033-refunds-core.md) | Refunds and returns: correction core (server) | 6 | S | XL | A | TASK-031, TASK-025, TASK-028 | — | `TODO` |
| [TASK-034](tasks/TASK-034-drawer-refunds.md) | Drawer-funded refunds and the refund pages | 6 | S | L | A | TASK-033, TASK-032 | — | `TODO` |
| [TASK-035](tasks/TASK-035-onboarding.md) | Onboarding and opening stock | 7 | I | L | A | TASK-026, TASK-034 | — | `TODO` |
| [TASK-036](tasks/TASK-036-reconciliation-repair.md) | Reconciliation, incidents and data-repair procedures | 7 | O | L | A | TASK-034, TASK-020, TASK-013 | — | `TODO` |
| [TASK-037](tasks/TASK-037-restore-tooling.md) | Restore tooling and recovery procedures | 7 | O | L | A | TASK-013, TASK-035 | — | `TODO` |
| [TASK-038](tasks/TASK-038-housekeeping.md) | Housekeeping and retention | 7 | O | M | A | TASK-019, TASK-018, TASK-011 | — | `TODO` |
| [TASK-039](tasks/TASK-039-system-status.md) | System status and monitoring | 7 | O | M | A | TASK-037, TASK-036 | — | `TODO` |
| [TASK-040](tasks/TASK-040-finance-report.md) | Finance report | 8 | R | L | A | TASK-034, TASK-025 | — | `TODO` |
| [TASK-041](tasks/TASK-041-owner-home-daily.md) | Owner home and daily summary | 8 | R | L | A | TASK-039, TASK-040, TASK-032 | — | `TODO` |
| [TASK-042](tasks/TASK-042-audit-views.md) | Audit log and security log views | 8 | R | M | A | TASK-036, TASK-035 | — | `TODO` |
| [TASK-043](tasks/TASK-043-performance-harness.md) | Performance harness and provisional dataset | 9 | V | M | B | TASK-041, TASK-042, TASK-038, TASK-014 | — | `TODO` |
| [TASK-044](tasks/TASK-044-integrated-verification.md) | Release candidate and integrated verification | 9 | V | L | V | TASK-043 | TARGET, GATE-PUBLISH | `TODO` |
| [TASK-045](tasks/TASK-045-operations-verification.md) | Operations verification | 9 | V | L | V | TASK-044 | TARGET, G4, operator named | `TODO` |
| [TASK-046](tasks/TASK-046-device-verification.md) | Device verification | 9 | V | M | V | TASK-044 | DEVICES, TARGET, operator named | `TODO` |
| [TASK-047](tasks/TASK-047-p7-change-control-2.md) | P7 change control, batch 2: device results | 9 | D | S | O | TASK-046 | GATE-CC2 | `TODO` |
| [TASK-048](tasks/TASK-048-performance-verification.md) | Performance verification | 9 | V | M | V | TASK-044, TASK-043 | TARGET, G2, operator named | `TODO` |
| [TASK-049](tasks/TASK-049-release-readiness.md) | Release readiness: staging, UAT and first installation | 10 | R | L | V | TASK-045, TASK-047, TASK-048 | G1, G2, G3, G4, DEVICES, TARGET, GATE-PUBLISH | `TODO` |
| [TASK-050](tasks/TASK-050-production-pilot.md) | Production release, cutover and pilot acceptance | 10 | R | L | V | TASK-049 | RELEASE, GATE-PUBLISH, G4 | `TODO` |

Totals: `TODO` 49; `READY` 1; `IN_PROGRESS` 0; `BLOCKED` 0; `VERIFYING` 0; `DONE` 0; `SUPERSEDED` 0.

## 5. Implementation sequence

### 5.1 Recommended order

One executor runs the Tasks in the order of their numbers. The milestones below are points where a coherent part of V1 works end to end on `develop`; they authorize nothing.

| Milestone | Reached after | What works |
| --- | --- | --- |
| M1 — Platform ready | [TASK-011](tasks/TASK-011-user-management.md) | Sign-in, authorization, the security baseline, the command platform with recovery, the bootstrap, the shop identity and user management |
| M2 — Delivery path ready | [TASK-014](tasks/TASK-014-release-pipeline.md) | The release image, the independent backup and the release workflow; the operator can prepare staging and gate TARGET |
| M3 — Catalogue complete | [TASK-020](tasks/TASK-020-barcode-correction.md) | Products, prices, barcodes, photos, labels, CSV import and the misassignment correction |
| M4 — Stock and cost complete | [TASK-026](tasks/TASK-026-reversal.md) | Receiving, stock views, manual issue, adjustment, cost correction with replay, reversal |
| M5 — First Sale end to end | [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | Registers, session open, checkout, the till, the close and the documents |
| M6 — Corrections complete | [TASK-034](tasks/TASK-034-drawer-refunds.md) | Owner-assisted close, review notes, refunds and returns from every source |
| M7 — Operations complete | [TASK-039](tasks/TASK-039-system-status.md) | Onboarding, reconciliation and repair, restore tooling, housekeeping and the system status |
| M8 — Product complete | [TASK-042](tasks/TASK-042-audit-views.md) | The finance report, the Owner's home and daily summary, the audit views |
| M9 — Release candidate verified | [TASK-048](tasks/TASK-048-performance-verification.md) | Integrated, operations, device and performance verification at their final points |
| M10 — V1 in production | [TASK-050](tasks/TASK-050-production-pilot.md) | Release readiness, the authorized release, the cutover and the accepted pilot |

### 5.2 Why this order

- **The platform first.** Every command needs the envelope, receipts and recovery (TASK-008, TASK-009); every page needs the design system, the shell and the authorization framework (TASK-005, TASK-007); every fixture needs the bootstrap and the staff accounts (TASK-010, TASK-011, EXC-45).
- **The delivery path early.** The image, the backup and the release workflow (TASK-012 to TASK-014) come right after the platform, so that the operator can build staging, record gate TARGET and open the backup store (gate G4) while the product is built. These are the gates with the longest lead time, and they do not depend on business features.
- **Catalogue, then stock, then sales.** The stock ledger needs products and barcodes; checkout needs the ledger, the issue allocation of TASK-023, the replay of TASK-025, which it extends to Sale issues, the reversal of TASK-026, which a later Sale can make unsafe, and an OPEN cash session; refunds need Sales, sessions and the replay.
- **Each slice ends in a page.** A server Task is followed at once by the pages that use it, so every milestone is proven in the browser on the approved designs, not only by unit tests.
- **Onboarding late on purpose.** TASK-035 proves the onboarding freeze (G-10) against every posting family, so it runs after all of them exist; it is used only at the cutover.
- **Verification by final points.** TEST_STRATEGY section 12 and FD-07 keep dedicated verification Tasks for evidence that a feature Task cannot produce: the release candidate, staging, the drill target, real devices and the intended server.
- **Risk first within a lane.** The most demanding transactions — the ledger (TASK-021), the replay (TASK-025), checkout (TASK-028) and the correction engine (TASK-033) — are class A with an adversarial review, and nothing that depends on them is done before they are.

### 5.3 Parallel work

With more than one executor the Owner may run Tasks of different lanes at the same time (EXC-12). [DEPENDENCY_GRAPH](DEPENDENCY_GRAPH.md) section 4 lists, for each Task, the earliest wave in which its dependencies allow it; with enough executors the whole plan runs in 32 waves. The main parallel opportunities: TASK-002 beside TASK-001 to TASK-004, merged after TASK-001 (EXC-37); TASK-012 to TASK-014 beside the catalogue; TASK-027 beside TASK-023 to TASK-026; TASK-018 to TASK-020 beside TASK-021 to TASK-026; TASK-045, TASK-046 and TASK-048 beside each other.

### 5.4 Critical path

- By number of Tasks (32): TASK-001 → TASK-003 → TASK-004 → TASK-006 → TASK-007 → TASK-008 → TASK-009 → TASK-011 → TASK-015 → TASK-016 → TASK-021 → TASK-022 → TASK-023 → TASK-024 → TASK-025 → TASK-026 → TASK-028 → TASK-029 → TASK-030 → TASK-031 → TASK-033 → TASK-034 → TASK-035 → TASK-037 → TASK-039 → TASK-041 → TASK-043 → TASK-044 → TASK-046 → TASK-047 → TASK-049 → TASK-050.
- Weighted by size (S 1, M 2, L 3, XL 4; weight 92): TASK-001 → TASK-003 → TASK-004 → TASK-006 → TASK-007 → TASK-008 → TASK-010 → TASK-011 → TASK-015 → TASK-016 → TASK-021 → TASK-022 → TASK-023 → TASK-024 → TASK-025 → TASK-026 → TASK-028 → TASK-029 → TASK-030 → TASK-031 → TASK-033 → TASK-034 → TASK-035 → TASK-037 → TASK-039 → TASK-041 → TASK-043 → TASK-044 → TASK-045 → TASK-049 → TASK-050.
- Outside the Tasks, the Owner's approvals and decisions are on the critical path of the release: GATE-P11 and GATE-BRANCH before TASK-001; GATE-PUBLISH before TASK-001 is `DONE`, and so before every later implementation Task; GATE-CC1 before TASK-005; and the external gates TARGET, DEVICES, G1 to G4 and RELEASE. DEPENDENCY_GRAPH section 5 shows where they enter.

## 6. The first eligible Task

**[TASK-001](tasks/TASK-001-toolchain-ci.md) — Toolchain, repository conventions and CI** is the first eligible coding Task. It depends on no Task. Gate GATE-P11 is approved (D-092). It may start when gate GATE-BRANCH (`develop` exists, created by a branch operation the Owner authorizes) and gate EXECUTOR hold and the Owner's instruction names it; until then it stays `TODO`. It may be built and verified locally before gate GATE-PUBLISH, but it is `DONE` only when GATE-PUBLISH holds (N-09 decided, the N-10 audit completed and the publication guard passed), its branch is pushed on the Owner's authorization, its pull request is merged into `develop` and CI passes on the pull request and on the merge (EXC-31, EXC-32). Because every later implementation Task depends on TASK-001, GATE-PUBLISH is needed before any of them starts.

**[TASK-002](tasks/TASK-002-p7-change-control-1.md) — P7 and copy change control, batch 1** has no dependency either and changes documents only; with GATE-P11 approved (D-092) it is `READY` and may start on the Owner's instruction, on the branch the Owner names, before `develop` exists. It is merged into `develop` only after TASK-001, with `develop` merged into its branch first and the hashes of its package measured again on the commit to be merged (EXC-36, EXC-37); its `DONE` therefore needs GATE-BRANCH and GATE-PUBLISH too. It should start beside TASK-001, because its outcome, gate GATE-CC1, is a prerequisite of TASK-005, TASK-009 and TASK-010 on the platform trunk.

## 7. Open items and gates mapped

| Item | Mapped to | Note |
| --- | --- | --- |
| OPEN_ITEMS T-19 | TASK-002, gate GATE-CC1 | The copy proposals of DESIGN_SYSTEM section 13 |
| OPEN_ITEMS T-20 | TASK-002, gate GATE-CC1; TASK-047 for the shortcuts and breakpoints of gate DEVICES | INTERACTION_CONTRACTS sections 10 and 11, DC-04 headers, COPY_DECK findings F-01 to F-05, `PROJECTION_REBUILT`, the close-refusal message |
| OPEN_ITEMS T-21 | TASK-013 | A key rotation that can finish (BACKUP_RECOVERY BKP-22) |
| OPEN_ITEMS T-22 | TASK-013 | Capacity of the free allowance with staging counted |
| OPEN_ITEMS T-23 | TASK-006; its consequences in TASK-013 (the dump) and TASK-038 (the cleanup) | Database enforcement of the 365-day retention of the security log |
| OPEN_ITEMS T-24 | RESOLVED in RF-7 (D-090) | The deadline of N-12 now reads: before TASK-001 |
| OPEN_ITEMS N-12 | Gate GATE-BRANCH | RESOLVED as to the model (D-092): `develop` for implementation, `main` for releases; the creation of `develop` needs the Owner's own authorization, before TASK-001 |
| OPEN_ITEMS N-09, N-10 | Gate GATE-PUBLISH | Before the first push of implementation work, and so before TASK-001 is `DONE` |
| OPEN_ITEMS N-04 | Gate GATE-P11 | RESOLVED (D-092): the complete AICWDF-4.3 text is not added to the repository; the binding subset stays in `docs/00-governance` |
| OPEN_ITEMS N-08 | Recommended before the first merge into `develop` | Branch protection is the Owner's setting; not a gate |
| OPEN_ITEMS N-03, N-05, T-11, T-14, T-15 | Not mapped | The Owner's or housekeeping items, unchanged by this baseline |
| OPEN_ITEMS T-01, T-02 | TASK-001 | Line endings; `.env.example` and `.gitignore` |
| OPEN_ITEMS T-09 | TASK-043 (provisional dataset), TASK-048 (final dataset when G2 is known) | |
| Gates G1, G2, G3, G4, DEVICES, TARGET, RELEASE, TESTS, EXECUTOR | Section 3 | |
| Reading: the `labels` and `stock` contexts of RD-01 | TASK-015 | INTERACTION_CONTRACTS section 3.1 lists them; ROUTE_CONTRACTS RD-01 does not; no control of section 9 uses them |
| Reading: the backup store's usage on the system status | TASK-002 (the Owner's answer), TASK-039 | ADR-010 item 11 and OPERATIONS_RUNBOOK section 5 differ |
| Reading: the shop's name and accent in the shell | TASK-010 | SECURITY_MODEL SEC-CH-02 does not name them; DESIGN_SYSTEM section 7.2 shows them |
| Reading: the mechanism of T-23 | TASK-006 | SCHEMA SCH-06 excludes triggers, rules, stored procedures and `SECURITY DEFINER` functions |

## 8. Change control of the baseline

- Adding, splitting, merging or removing a Task changes the baseline; the Owner approves it, DECISIONS records it, and this plan, the graph and the traceability change in the same commit (EXC-80).
- A new Task takes the next free number, `TASK-051` today; a replaced Task becomes `SUPERSEDED` and names its replacement.
- A defect that a verification Task finds is fixed by a new Task, and the suites it touches run again (EXC-82, TS-32).
- A business fact of gates G1 to G3, DEVICES or TARGET that changes a rule goes to the Owner first (PROJECT_RULES section 3); the affected contracts change through change control.

## 9. Former Build Units

FD-08 replaces the 27 Build Units of D-012 by Tasks and requires this mapping. The Build Units were never started and carry no authority (LEGACY_INDEX section 16).

| Build Unit | Carried by | Not carried, or changed by a later decision |
| --- | --- | --- |
| BU-FOUND-001 | [TASK-001](tasks/TASK-001-toolchain-ci.md), [TASK-003](tasks/TASK-003-kernel-localization.md), [TASK-004](tasks/TASK-004-database-foundation.md), [TASK-005](tasks/TASK-005-design-system.md) | — |
| BU-IAM-001 | [TASK-006](tasks/TASK-006-authentication.md), [TASK-007](tasks/TASK-007-authorization-shell.md), [TASK-011](tasks/TASK-011-user-management.md) | — |
| BU-CMD-001 | [TASK-008](tasks/TASK-008-command-platform.md), [TASK-009](tasks/TASK-009-command-client.md) | Request receipts and reconciled outcomes are new (ADR-009) |
| BU-CFG-001 | [TASK-010](tasks/TASK-010-bootstrap-profile-media.md) | — |
| BU-CAT-001 | [TASK-015](tasks/TASK-015-catalog-core.md), [TASK-016](tasks/TASK-016-barcodes.md), [TASK-017](tasks/TASK-017-catalog-pages.md), [TASK-018](tasks/TASK-018-photos-labels.md), [TASK-019](tasks/TASK-019-csv-import.md), [TASK-020](tasks/TASK-020-barcode-correction.md) | CSV import, photos and the misassignment correction are new (D-037, D-051, D-052) |
| BU-INV-001 | [TASK-021](tasks/TASK-021-ledger-receiving.md), [TASK-023](tasks/TASK-023-manual-issue.md) | — |
| BU-INV-002 | [TASK-021](tasks/TASK-021-ledger-receiving.md), [TASK-023](tasks/TASK-023-manual-issue.md), [TASK-035](tasks/TASK-035-onboarding.md) | Onboarding at most once with restore holds (D-071, D-072, D-087) |
| BU-INV-003 | [TASK-024](tasks/TASK-024-stock-adjustment.md), [TASK-025](tasks/TASK-025-cost-correction.md), [TASK-026](tasks/TASK-026-reversal.md) | Cost corrections replace the versioned valuation (D-034, D-060, D-061) |
| BU-CASH-001 | [TASK-027](tasks/TASK-027-registers-session-open.md) | — |
| BU-SALE-001 | [TASK-028](tasks/TASK-028-checkout.md), [TASK-029](tasks/TASK-029-till-pages.md), [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | STATIC_QRIS is not carried (D-077) |
| BU-CASH-002 | [TASK-030](tasks/TASK-030-session-close.md) | The close refusal of G-27 is new (D-088) |
| BU-SALE-002 | [TASK-033](tasks/TASK-033-refunds-core.md), [TASK-034](tasks/TASK-034-drawer-refunds.md) | Refunds from the drawer are new (D-035, D-045, D-046, D-068) |
| BU-READ-001 | [TASK-022](tasks/TASK-022-receiving-stock-pages.md) | — |
| BU-READ-002 | [TASK-031](tasks/TASK-031-documents-sales-lookup.md), [TASK-032](tasks/TASK-032-assisted-close-notes.md), [TASK-040](tasks/TASK-040-finance-report.md), [TASK-041](tasks/TASK-041-owner-home-daily.md), [TASK-042](tasks/TASK-042-audit-views.md) | — |
| BU-OPS-001 | [TASK-004](tasks/TASK-004-database-foundation.md), [TASK-012](tasks/TASK-012-application-image.md), [TASK-014](tasks/TASK-014-release-pipeline.md) | Docker Compose, Nginx-managed certificates and an agent-run release are not carried (D-053, D-088) |
| BU-OPS-002 | [TASK-013](tasks/TASK-013-independent-backup.md), [TASK-036](tasks/TASK-036-reconciliation-repair.md), [TASK-037](tasks/TASK-037-restore-tooling.md), [TASK-038](tasks/TASK-038-housekeeping.md) | The backup method and custody are ADR-010 (D-088) |
| BU-UI-001 | [TASK-005](tasks/TASK-005-design-system.md), [TASK-006](tasks/TASK-006-authentication.md), [TASK-007](tasks/TASK-007-authorization-shell.md), [TASK-010](tasks/TASK-010-bootstrap-profile-media.md), [TASK-011](tasks/TASK-011-user-management.md) | The former design candidate is not carried (D-024, D-085) |
| BU-UI-002 | [TASK-017](tasks/TASK-017-catalog-pages.md), [TASK-022](tasks/TASK-022-receiving-stock-pages.md), [TASK-023](tasks/TASK-023-manual-issue.md) | — |
| BU-UI-003 | [TASK-027](tasks/TASK-027-registers-session-open.md), [TASK-029](tasks/TASK-029-till-pages.md), [TASK-030](tasks/TASK-030-session-close.md) | — |
| BU-UI-004 | [TASK-025](tasks/TASK-025-cost-correction.md), [TASK-032](tasks/TASK-032-assisted-close-notes.md), [TASK-034](tasks/TASK-034-drawer-refunds.md), [TASK-036](tasks/TASK-036-reconciliation-repair.md), [TASK-039](tasks/TASK-039-system-status.md), [TASK-040](tasks/TASK-040-finance-report.md), [TASK-041](tasks/TASK-041-owner-home-daily.md), [TASK-042](tasks/TASK-042-audit-views.md) | — |
| BU-DOC-001 | [TASK-018](tasks/TASK-018-photos-labels.md), [TASK-031](tasks/TASK-031-documents-sales-lookup.md) | — |
| BU-VERIFY-001 | [TASK-045](tasks/TASK-045-operations-verification.md) | — |
| BU-VERIFY-002 | [TASK-044](tasks/TASK-044-integrated-verification.md) | — |
| BU-VERIFY-003 | [TASK-046](tasks/TASK-046-device-verification.md) | — |
| BU-VERIFY-004 | [TASK-043](tasks/TASK-043-performance-harness.md), [TASK-048](tasks/TASK-048-performance-verification.md) | — |
| BU-RELEASE-001 | [TASK-049](tasks/TASK-049-release-readiness.md) | — |
| BU-RELEASE-002 | [TASK-050](tasks/TASK-050-production-pilot.md) | A production cutover carried out by an agent is not carried: the operator, a person, releases (D-088) |

## 10. Checks

Counted by the generator from the documents named, not copied.

| Check | Result |
| --- | --- |
| Tasks; identifiers `TASK-001` to `TASK-050` in order | 50 |
| Dependencies: every Task depends only on smaller numbers; the graph has no cycle | Yes |
| Requirements of REQUIREMENTS carried by exactly one Task | 134 of 134 |
| Workflows of CRITICAL_FLOWS carried by exactly one Task | 32 of 32 |
| User tasks of USER_TASKS in force (UXT-05 is retired by D-077) with a carrying workflow, Task and UAT script | 38 of 38 |
| Acceptance suites with exactly one owning Task and a final-point Task | 56 of 56 |
| Races of TECHNICAL_RULES section 8 owned by exactly one Task | 35 of 35 |
| Guards of SCHEMA section 8 owned by exactly one Task | 27 of 27 |
| Threats of SECURITY_MODEL section 10 owned by exactly one Task | 21 of 21 |
| Routes of ROUTE_CONTRACTS (pages, print views, reads, authentication, other requests, health, commands, scheduled commands, operator procedures) owned by exactly one Task | 142 of 142 |
| Tables of SCHEMA section 10 created by exactly one Task | 71 of 71 |
| Keys of SCHEMA section 5 from a table to one that a later Task creates, completed by that later Task (EXC-44) | 11 of 11 |
| Pages of INFORMATION_ARCHITECTURE section 5 with one owning Task | 61 of 61 |
| Controls of INTERACTION_CONTRACTS section 9 built by exactly one Task | 383 of 385; the other 2 are "Not provided" |
| Controls whose owning Task depends on every Task that delivers its page and routes | Yes |
| Document contracts DC-01 to DC-05 owned by exactly one Task | 5 of 5 |
| Former Build Units mapped | 27 of 27 |
| Errors found by the generator | 0 |
