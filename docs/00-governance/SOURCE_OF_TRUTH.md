# Source of truth and document map

This document says which file is authoritative for each subject. If a subject has no current document yet, the map names the legacy document that still carries it.

## 1. Authority hierarchy

When sources disagree, the higher one prevails:

1. the latest Owner decision recorded in [DECISIONS](DECISIONS.md);
2. [AGENTS.md](../../AGENTS.md) and the documents in this folder;
3. the active Task contract: the contract in [`docs/11-tasks/tasks/`](../11-tasks/tasks/) of the Task in progress, which the Owner has authorized by name or which continuous execution has selected ([EXECUTION_CONTEXT](../11-tasks/EXECUTION_CONTEXT.md) section 19, D-101);
4. current ADRs in `docs/adr/` (index: [docs/adr/README.md](../adr/README.md));
5. the product, domain, workflow, architecture, security, design, testing, operations and release documents listed in section 3;
6. implementation and tests;
7. [CURRENT_HANDOFF](../handoff/CURRENT_HANDOFF.md);
8. chat and session context.

When a contradiction is found: determine whether one source supersedes the other; update the repository so the contradiction does not persist; if it cannot be resolved without an Owner decision, record it in [OPEN_ITEMS](OPEN_ITEMS.md) and stop the affected work. Never choose silently.

## 2. Status vocabulary for documents

| Status | Meaning |
| --- | --- |
| CURRENT | Authoritative. Written under the re-foundation. |
| IN PROGRESS | Work of an authorized step that is not complete. Not authoritative: it approves and decides nothing until its step's checkpoint is complete. |
| LEGACY — IN FORCE | A pre-re-foundation document that remains the authoritative content for its subject until the step named in the last column replaces it. Subject to the revalidation items in OPEN_ITEMS. |
| LEGACY — DRAFT | As above, but the document was already an unfinished draft before the re-foundation. |
| LEGACY — INPUT ONLY | Not authoritative. Kept as input for the step that rebuilds the subject. |
| LEGACY — SUPERSEDED | Replaced by a CURRENT document. Kept for comparison and removed in a later step. |
| RECORD | A historical record. Not a rule. |

Rules for every `legacy/` document:

- It is in Indonesian and is not edited. It is replaced as a whole when its subject is rebuilt.
- Links inside it are not maintained and may point at paths that no longer exist. Use [LEGACY_INDEX](LEGACY_INDEX.md) to find where a file went.
- Statements inside it about status, authorization or "next step" are historical. Current status and authorization are only in CURRENT_HANDOFF.
- Its identifiers (`BU-…`, acceptance suite names, `G1`–`G4`) remain valid references until the rebuilt documents replace them. `CORE-…` identifiers were replaced in RF-2 by the requirement register; [REQUIREMENTS](../01-product/REQUIREMENTS.md) section 21 maps each of them.
- A LEGACY — SUPERSEDED document has no authority at all. Its rules apply only as far as the CURRENT document that replaced it carries them.
- Where a CURRENT product or domain document (`docs/01-product/`, `docs/02-domain/`) and a legacy document still in force for another subject disagree on a product or domain rule, the CURRENT document prevails. The legacy text is not edited.
- Where a CURRENT architecture, security or technical-rule document or an ADR (`docs/04-architecture/`, `docs/05-security/`, `docs/06-api-performance/`, `docs/adr/`, written in RF-3A and RF-3B) and a legacy document still in force for another subject disagree on an architecture, security or technical rule, the CURRENT document prevails. Where a LEGACY — DRAFT operations or release document differed from ADR-002 or ADR-003, the ADR prevailed; since RF-6 (D-088) those drafts are LEGACY — SUPERSEDED. The legacy text is not edited.
- OPEN_ITEMS T-16 was resolved by D-060, D-061 and [ADR-008](../adr/ADR-008-cost-allocation-representation.md) (RF-3B). The legacy statements that would have settled T-16 (a) or (b), listed in RF-2 ([COSTING](../02-domain/COSTING.md) section 10), received their disposition in ADR-008: each is adopted or rejected there, with its reason. That disposition does not change the status of the document a statement belongs to.
- Where a later decision in [DECISIONS](DECISIONS.md) changes a rule written in a legacy document still in force, the decision prevails (section 1). The legacy text is not edited. Known cases: none since RF-6. The three drafts that this list still named before RF-6 — `docs/08-testing/legacy/08-TESTING-ACCEPTANCE.md` (suites AUTH02, RET01, SESSION04, UX01 and SEARCH01; D-024, D-025, D-033, D-035, D-051, D-052), `docs/09-operations/legacy/09-DEPLOYMENT-OPS.md` (Docker Compose, Nginx-managed certificates, an operating-system scheduler; D-053) and `docs/10-release/legacy/P10-RELEASE-UAT.md` (write-only receiving cost; D-033) — are LEGACY — SUPERSEDED by the RF-6 documents (D-088), and those changes are carried by them ([ACCEPTANCE_SUITES](../08-testing/ACCEPTANCE_SUITES.md) section 2; [LEGACY_INDEX](LEGACY_INDEX.md) section 15).

RF-2 also listed known cases for `docs/05-security/legacy/04-AUTH-RBAC-SECURITY.md`, `docs/05-security/legacy/P5-SECURITY.md` and `docs/06-api-performance/legacy/P6-CONCURRENCY-API.md` (write-only cost, D-033; CASH and BANK_TRANSFER as the only methods, D-036), and for the parts of `02-ARCHITECTURE` and `P4-ARCHITECTURE` that carried an allocation version per cost revision (T-16), the two payment methods (D-036) and write-only cost (D-033). Since RF-3A (D-055) those documents and parts are LEGACY — SUPERSEDED and carry no authority at all, so they are no longer listed; SECURITY_MODEL, TECHNICAL_RULES and ARCHITECTURE carry the current rules. RF-3A also listed the barcode-token cases of `02-ARCHITECTURE` §Constraint database and `P4-ARCHITECTURE` §Schema, immutability dan constraints (D-051, D-052). Under plan decision P-6 (D-059) those sections are superseded by [SCHEMA](../04-architecture/SCHEMA.md) when the Owner accepts the RF-3B checkpoint, so they are no longer listed either; SCHEMA section 5.6 carries the barcode rules of D-051 and D-052. D-077 makes CASH and BANK_TRANSFER the only V1 payment methods again, so the rule "CASH or BANK_TRANSFER only" of `P3-CRITICAL-WORKFLOWS` and the "no QRIS" expectation of suites POS02 and PAYEXT01, which D-036 had overridden, agree with the current rules and are no longer listed.

## 3. Document map

### Root

| Path | Subject | Status |
| --- | --- | --- |
| [AGENTS.md](../../AGENTS.md) | Rules for every agent | CURRENT |
| [CLAUDE.md](../../CLAUDE.md) | Pointer to AGENTS.md | CURRENT |
| [README.md](../../README.md) | Project introduction and layout | CURRENT |

### docs/00-governance — P0

| Path | Subject | Status |
| --- | --- | --- |
| [PROJECT_RULES.md](PROJECT_RULES.md) | Governance rules in detail | CURRENT |
| SOURCE_OF_TRUTH.md | This document | CURRENT |
| [DECISIONS.md](DECISIONS.md) | Decision log, D-001 onward | CURRENT |
| [FRAMEWORK_DEVIATIONS.md](FRAMEWORK_DEVIATIONS.md) | Departures from AICWDF-4.3 | CURRENT |
| [OPEN_ITEMS.md](OPEN_ITEMS.md) | Open Owner questions, revalidation items, gates, technical items | CURRENT |
| [TOOLCHAIN.md](TOOLCHAIN.md) | Tool capabilities, the approved frontend reference (D-085) and MCP policy | CURRENT |
| [COST_POLICY.md](COST_POLICY.md) | Recurring-cost rule and inventory | CURRENT |
| [PRODUCTION_DATA_SAFETY.md](PRODUCTION_DATA_SAFETY.md) | Production data protection | CURRENT |
| [REFOUNDATION_PLAN.md](REFOUNDATION_PLAN.md) | Re-foundation steps RF-0 to RF-7 | CURRENT |
| [LEGACY_INDEX.md](LEGACY_INDEX.md) | Where older material went and how to recover it | CURRENT |
| [records/REFOUNDATION_ANALYSIS_2026-10-03.md](records/REFOUNDATION_ANALYSIS_2026-10-03.md) | Re-foundation analysis report, revision 2 | RECORD |
| [records/CONTINUOUS_EXECUTION_SETUP.md](records/CONTINUOUS_EXECUTION_SETUP.md) | The setup of continuous execution (D-101): the files added, their verification and the settings of the scheduled task, and the host session and watcher of D-104 | RECORD |
| [legacy/P0-GOVERNANCE.md](legacy/P0-GOVERNANCE.md) | Former governance document | LEGACY — SUPERSEDED by PROJECT_RULES |
| [legacy/10-DECISIONS.md](legacy/10-DECISIONS.md) | Original full text of D-001 to D-018 | LEGACY — SUPERSEDED by DECISIONS for status; kept as the original wording |
| [legacy/GAPS-CHANGES.md](legacy/GAPS-CHANGES.md) | Former gap and change register | LEGACY — SUPERSEDED by OPEN_ITEMS |
| [legacy/00-CURRENT-STATE.md](legacy/00-CURRENT-STATE.md) | Former state document | LEGACY — SUPERSEDED by CURRENT_HANDOFF |

### docs/01-product — P1

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [PRODUCT_BLUEPRINT.md](../01-product/PRODUCT_BLUEPRINT.md) | Problem, goals, users, roles and capabilities, journeys, V1 scope and exclusions, non-functional summary, release boundary, unknown first-store facts | CURRENT (RF-2) | — |
| [REQUIREMENTS.md](../01-product/REQUIREMENTS.md) | Requirement register with acceptance criteria; CORE-001 to CORE-073 mapping; decision traceability | CURRENT (RF-2) | — |
| [legacy/01-PRD.md](../01-product/legacy/01-PRD.md) | Former product scope, roles, key flows, "Later" list | LEGACY — SUPERSEDED by PRODUCT_BLUEPRINT and REQUIREMENTS | RF-2; removal in a later authorized step |
| [legacy/P1-PRODUCT.md](../01-product/legacy/P1-PRODUCT.md) | Former phase summary | LEGACY — SUPERSEDED by PRODUCT_BLUEPRINT | RF-2; removal in a later authorized step |
| [legacy/requirements/core.json](../01-product/legacy/requirements/core.json), [CORE-MAP.md](../01-product/legacy/requirements/CORE-MAP.md) | Former 73 Core requirement entries and their mapping | LEGACY — SUPERSEDED by REQUIREMENTS (section 21 maps every entry) | RF-2; removal in a later authorized step |

### docs/02-domain — P2

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [GLOSSARY.md](../02-domain/GLOSSARY.md) | One English term and one Indonesian shop term per concept | CURRENT (RF-2); shop terms finalized in RF-4B (D-083); gate P7-G2 approved (D-086); change control batch 1 approved by hash (D-099) | — |
| [DOMAIN_MODEL.md](../02-domain/DOMAIN_MODEL.md) | Domain map: entities, relationships, states, global invariants, numbers and time, commands, users, audit, BusinessProfile, lifecycle matrix, retention | CURRENT (RF-2) | — |
| [CATALOG_BARCODE.md](../02-domain/CATALOG_BARCODE.md) | Products, categories, prices, barcodes, Product Search, scanning, labels, CSV product import, product photos | CURRENT (RF-2) | — |
| [INVENTORY.md](../02-domain/INVENTORY.md) | Stock movements and balance, receiving, manual issue, opening stock, adjustment, reversal, stock attention, reconciliation | CURRENT (RF-2) | — |
| [COSTING.md](../02-domain/COSTING.md) | Cost evidence and visibility, moving weighted average, corrections and replay, gross profit reporting; open design issue T-16 | CURRENT (RF-2) | — |
| [SALES_CASH.md](../02-domain/SALES_CASH.md) | Sale, payment and its confirmation, receipts, cash registers and sessions, expected cash, refunds and returns, daily summary | CURRENT (RF-2) | — |
| [legacy/P2-DOMAIN.md](../02-domain/legacy/P2-DOMAIN.md) | Former domain boundaries and invariants | LEGACY — SUPERSEDED by DOMAIN_MODEL | RF-2; removal in a later authorized step |
| [legacy/06-INVENTORY-SPEC.md](../02-domain/legacy/06-INVENTORY-SPEC.md) | Former stock specification | LEGACY — SUPERSEDED by INVENTORY | RF-2; removal in a later authorized step |
| [legacy/12-BARCODE-SCANNER.md](../02-domain/legacy/12-BARCODE-SCANNER.md) | Former search, barcode and scanner specification | LEGACY — SUPERSEDED by CATALOG_BARCODE for domain rules, and its scanner screen behaviour by the approved P7 documents and INTERACTION_CONTRACTS (D-087) | RF-2 and RF-5; removal in a later authorized step |
| [legacy/15-FINANCE-PROFITABILITY.md](../02-domain/legacy/15-FINANCE-PROFITABILITY.md) | Former cost and gross profit specification | LEGACY — SUPERSEDED by COSTING | RF-2; removal in a later authorized step |
| [legacy/17-POS-SALES.md](../02-domain/legacy/17-POS-SALES.md) | Former sale, payment, receipt and cash session specification | LEGACY — SUPERSEDED by SALES_CASH for domain rules, and its receipt and screen layouts by DOCUMENT_CONTRACTS and the approved P7 documents (D-087) | RF-2 and RF-5; removal in a later authorized step |

The domain part of `docs/04-architecture/legacy/02-ARCHITECTURE.md` (its entity table and its numbers and time) is superseded by DOMAIN_MODEL. Since RF-5 (D-087), `docs/03-workflows/legacy/P3-CRITICAL-WORKFLOWS.md` is LEGACY — SUPERSEDED by the P3 documents below; the cases RF-2 listed for it (D-033, D-035, D-037, D-040, D-046, D-047) are carried by CRITICAL_FLOWS.

### docs/03-workflows — P3

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [CRITICAL_FLOWS.md](../03-workflows/CRITICAL_FLOWS.md) | Workflow contracts WF-01 to WF-32, from entry to record, with the traceability of every requirement and user task | CURRENT (RF-5, D-087) | — |
| [ROUTE_CONTRACTS.md](../03-workflows/ROUTE_CONTRACTS.md) | Every HTTP route: method, path, handler, permission and expected result | CURRENT (RF-5, D-087) | — |
| [INTERACTION_CONTRACTS.md](../03-workflows/INTERACTION_CONTRACTS.md) | The common command contract, recovery, reads, authentication, every command's contract, audit codes and the route of each control of INTERACTION_INVENTORY | CURRENT (RF-5, D-087) | — |
| [DOCUMENT_CONTRACTS.md](../03-workflows/DOCUMENT_CONTRACTS.md) | Receipt, session report and its export, product import file and barcode label | CURRENT (RF-5, D-087) | — |
| [legacy/P3-CRITICAL-WORKFLOWS.md](../03-workflows/legacy/P3-CRITICAL-WORKFLOWS.md) | Critical workflows | LEGACY — SUPERSEDED by the four documents above (D-087) | RF-5; removal in a later authorized step |

### docs/04-architecture — P4

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [ARCHITECTURE.md](../04-architecture/ARCHITECTURE.md) | Context, layers, units of work, read models, payment, media and document boundaries, runtime constraints, database privilege separation | CURRENT (RF-3A) | — |
| [STACK_AND_MODULES.md](../04-architecture/STACK_AND_MODULES.md) | Stack and version policy, module map with entity ownership and dependencies, directory layout, no speculative abstraction | CURRENT (RF-3A) | — |
| [SCHEMA.md](../04-architecture/SCHEMA.md) | Logical database schema: tables, keys, constraints, facts and projections, retention classes, document numbers, locks on rows, guards, the invariant-to-enforcement matrix, database privileges, indexes | CURRENT (RF-3B) | — |
| [legacy/02-ARCHITECTURE.md](../04-architecture/legacy/02-ARCHITECTURE.md) | Former architecture, transaction and locking contract; database constraints; domain part (entity table, numbers and time) | LEGACY — SUPERSEDED as a whole: the domain part by DOMAIN_MODEL (RF-2), the architecture parts by the RF-3A documents, §Constraint database and the schema-delivery part of §Payment extension boundary dan schema delivery by SCHEMA (RF-3B). Section by section in [LEGACY_INDEX](LEGACY_INDEX.md) sections 8 and 9 | RF-2; RF-3A; RF-3B (final status under plan decision P-6, effective when the Owner accepts the RF-3B checkpoint); removal in a later authorized step |
| [legacy/P4-ARCHITECTURE.md](../04-architecture/legacy/P4-ARCHITECTURE.md) | Former stack comparison and choice, modules, schema, testing and deployment implications | LEGACY — SUPERSEDED, except §Sumber primer yang diverifikasi saat planning: RECORD. §Testing implications is superseded by TEST_STRATEGY and §Deployment, operasi dan maintenance by ENVIRONMENTS and ADR-011 (RF-6, D-088); §Schema, immutability dan constraints by SCHEMA (RF-3B); every other part by the RF-3A documents. Section by section in LEGACY_INDEX sections 8, 9 and 15 | RF-3A; RF-3B (final status under P-6, effective when the Owner accepts the RF-3B checkpoint); RF-6; removal in a later authorized step |

The logical database schema is SCHEMA (RF-3B, D-059; OPEN_ITEMS T-03 resolved). Under plan decision P-6 the final status of the two legacy P4 documents is written in the RF-3B checkpoint and takes effect when the Owner accepts it; until then their schema and constraint parts keep the status RF-3A gave them. No legacy document is shown as fully superseded while any of its parts is still the authority (plan decision P-5, D-055).

### docs/05-security — P5

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [SECURITY_MODEL.md](../05-security/SECURITY_MODEL.md) | Authentication profile, accounts and one-time links, sessions, authorization and permission matrix, sensitive channels, secrets, audit and security log, threat model, rate limits, CSRF, headers and CSP, uploads, operator procedures | CURRENT (RF-3A) | — |
| [legacy/04-AUTH-RBAC-SECURITY.md](../05-security/legacy/04-AUTH-RBAC-SECURITY.md) | Former authentication, roles, privacy | LEGACY — SUPERSEDED by SECURITY_MODEL | RF-3A; removal in a later authorized step |
| [legacy/P5-SECURITY.md](../05-security/legacy/P5-SECURITY.md) | Former threat model and security baseline | LEGACY — SUPERSEDED by SECURITY_MODEL | RF-3A; removal in a later authorized step |

### docs/06-api-performance — P6

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [TECHNICAL_RULES.md](../06-api-performance/TECHNICAL_RULES.md) | HTTP and command contracts, error mapping, validation, transaction boundaries, lock order, race matrix, retries and timeouts, limits, paging, performance budgets | CURRENT (RF-3A) | — |
| [legacy/P6-CONCURRENCY-API.md](../06-api-performance/legacy/P6-CONCURRENCY-API.md) | Former concurrency, idempotency, HTTP contract, performance targets | LEGACY — SUPERSEDED by TECHNICAL_RULES | RF-3A; removal in a later authorized step |

### docs/adr — architecture decision records

| Path | Subject | Status |
| --- | --- | --- |
| [README.md](../adr/README.md) | ADR policy, statuses, numbering and index | CURRENT (RF-3A) |
| [ADR-001](../adr/ADR-001-stack-and-modular-monolith.md) to [ADR-007](../adr/ADR-007-command-identity-and-idempotency.md) | Stack and modular monolith; minimum runtime infrastructure; hosting topology; authentication; localization architecture; exact numbers; command identity and idempotency | ACCEPTED (RF-3A) |
| [ADR-008](../adr/ADR-008-cost-allocation-representation.md) | Issue-cost allocation representation | ACCEPTED (RF-3B) |
| [ADR-009](../adr/ADR-009-command-receipts-and-reconciled-outcomes.md) | Command request receipts and reconciled outcomes | ACCEPTED (RF-5, D-087); section 6 decided by D-088; section 1 corrected by D-089 |
| [ADR-010](../adr/ADR-010-independent-backup-and-recovery-key-custody.md) | Independent backup and recovery-key custody | ACCEPTED (RF-6, D-088) |
| [ADR-011](../adr/ADR-011-release-pipeline-environments-and-production-operation.md) | Release pipeline, environments and production operation | ACCEPTED (RF-6, D-088) |

### docs/07-ux-design — P7

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [USER_TASKS.md](../07-ux-design/USER_TASKS.md) | User tasks per role, frequency, devices, step budgets | CURRENT (RF-4A); gate P7-G1 approved (D-082); change control batch 1 approved by hash (D-099) | — |
| [INFORMATION_ARCHITECTURE.md](../07-ux-design/INFORMATION_ARCHITECTURE.md) | Areas, navigation per role, page inventory with access classes, capability and requirement coverage, traceability of the former candidate and of P7-G01 to P7-G18 | CURRENT (RF-4A); gate P7-G1 approved (D-082); change control batch 1 approved by hash (D-099) | — |
| [NAVIGATION_CONTRACTS.md](../07-ux-design/NAVIGATION_CONTRACTS.md) | Global navigation rules and one navigation and back-action contract per page | CURRENT (RF-4A); gate P7-G1 approved (D-082); change control batch 1 approved by hash (D-099) | — |
| [INTERACTION_INVENTORY.md](../07-ux-design/INTERACTION_INVENTORY.md) | Every visible control per page, with capability, action, review, scanner safety and result state; zero dead interactions | CURRENT (RF-4A); gate P7-G1 approved (D-082); change control batch 1 approved by hash (D-099); routes in INTERACTION_CONTRACTS section 9 (RF-5) | — |
| [UI_STATES.md](../07-ux-design/UI_STATES.md) | State catalogue, error-code mapping, uncertain-outcome recovery, states per page | CURRENT (RF-4A); gate P7-G1 approved (D-082); change control batch 1 approved by hash (D-099); wording in COPY_DECK | — |
| [ADAPTIVE_AND_ACCESSIBILITY.md](../07-ux-design/ADAPTIVE_AND_ACCESSIBILITY.md) | Device classes, adaptive rules and the exact accessibility targets of REQ-UX-03 | CURRENT (RF-4A); gate P7-G1 approved (D-082); change control batch 1 approved by hash (D-099) | — |
| [COPY_DECK.md](../07-ux-design/COPY_DECK.md) | Every word the shop sees in V1: writing rules, formats, copy keys, page titles and navigation, the wording of every control, dialog, state, error message, recovery panel and disabled reason, validation messages, documents and announcements; the localization contract of ADR-005 item 7 | CURRENT (RF-4B); gate P7-G2 approved (D-086); change control batch 1 approved by hash (D-099) | — |
| [DESIGN_SYSTEM.md](../07-ux-design/DESIGN_SYSTEM.md) | Visual design system: the adaptation of the Golden Frontend Reference and the deviations from its defaults, colour with the accent set and measured contrast, typography, spacing, icons, layout, components, states, accessibility, keyboard, print and the copy proposals | CURRENT (RF-4C); gate P7-G3 approved (D-087) | — |
| [KEY_SCREENS.md](../07-ux-design/KEY_SCREENS.md) | The ten key screens with their previews and states, the design choices they show, the verification record and the fixtures | CURRENT (RF-4C); gate P7-G3 approved (D-087) | — |
| `previews/` | Renderable, nonproduction previews of the key screens, with the specimen, the gallery and the stylesheets and scripts that render DESIGN_SYSTEM; review material, not application code | CURRENT (RF-4C); gate P7-G3 approved (D-087) | — |
| [legacy/DESIGN.md](../07-ux-design/legacy/DESIGN.md) | Draft visual contract of the former design candidate | LEGACY — SUPERSEDED by DESIGN_SYSTEM (D-086); not used in RF-4C | RF-4C; removal in a later authorized step |
| [legacy/P7-UX-DESIGN.md](../07-ux-design/legacy/P7-UX-DESIGN.md) | Former P7 workflow | LEGACY — SUPERSEDED by the RF-4A documents and plan decisions P-10 and P-14 (D-070) | RF-4A; removal in a later authorized step |
| [legacy/P7-COVERAGE.md](../07-ux-design/legacy/P7-COVERAGE.md) | Capability coverage matrix G01 to G18 | LEGACY — SUPERSEDED by INFORMATION_ARCHITECTURE (section 12 maps every group) and INTERACTION_INVENTORY | RF-4A; removal in a later authorized step |
| `legacy/p7-correction/` (7 files), `legacy/p7-owner-r1/` (8 files) | Evidence for the former candidate | RECORD | removed after RF-4 |

P7 is being rebuilt (D-024) as three checkpoints, RF-4A, RF-4B and RF-4C, ending at gates P7-G1, P7-G2 and P7-G3 (D-025; plan decision P-10, D-070). RF-4A wrote the six structure documents above for gate P7-G1 (navigation and page structure); the Owner accepted its checkpoint `c8dc234` (D-078). Four of them were amended for the Owner's payment-method decision Q38 (D-077, D-079) in the accepted checkpoint `d4c0594` (D-080), and the Owner approved gate P7-G1 with the hash of each of the six documents at that checkpoint (D-082). The six documents are kept byte-identical; their own gate rows still read "NOT APPROVED", and D-082 prevails (section 1). RF-4B (D-083) wrote COPY_DECK and finalized the shop terms of GLOSSARY for gate P7-G2 (terminology and copy); the Owner accepted its checkpoint `c4bf8e9` and authorized one correction of COPY_DECK (D-084), then accepted the correction `551b4eb` and approved gate P7-G2 with the hash of both documents at that commit (D-086). The two documents are kept byte-identical; their own gate rows still read "NOT APPROVED", and D-086 prevails (section 1). For wording, COPY_DECK and GLOSSARY prevail over the working labels of the six structure documents and over Indonesian labels written in other CURRENT documents; the rules of those documents are unchanged (COPY_DECK section 1.5). The localization architecture is decided in [ADR-005](../adr/ADR-005-localization-architecture.md); the localization contract that its item 7 calls `LOCALIZATION.md` is COPY_DECK section 1, and no separate file exists. The former candidate, design revision `sha256-bef01d649ea91b2b`, was never approved and will not be. RF-4C, the visual design for gate P7-G3 (D-086), was paused by the Owner and resumed on his instruction; it wrote DESIGN_SYSTEM and KEY_SCREENS with the previews in `previews/`, and DESIGN_SYSTEM supersedes `legacy/DESIGN.md`. The Owner accepted the RF-4C commits `3a7da19` and `7318322` and approved gate P7-G3 with the hash of each artifact at `7318322` (D-087): DESIGN_SYSTEM, KEY_SCREENS and the previews are the approved visual design. They are kept byte-identical; their own gate rows still read "NOT APPROVED", and D-087 prevails (section 1). Their copy proposals stay proposals (OPEN_ITEMS T-19), and nothing under `legacy/` may be used as a design contract. The approved reusable frontend reference for RF-4C and later frontend work is the Golden Frontend Reference v1.0.0, held outside this repository; [TOOLCHAIN](TOOLCHAIN.md) section 3 states how it is used (D-085). It is not a BantuKasir design artifact, and no design tool is required.

### docs/08-testing — P8

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [TEST_STRATEGY.md](../08-testing/TEST_STRATEGY.md) | Test levels, environments and data for tests, harness, test families, static checks, CI gates, evidence, acceptance gates, Definition of Done, reviews, final verification | CURRENT (RF-6, D-088); sections 6 and 7 amended by D-091 | — |
| [ACCEPTANCE_SUITES.md](../08-testing/ACCEPTANCE_SUITES.md) | The 56 acceptance suites with their cases, and the traceability of every requirement, race, guard, threat, workflow and procedure | CURRENT (RF-6, D-088) | — |
| [DEVICE_VERIFICATION.md](../08-testing/DEVICE_VERIFICATION.md) | The protocol of gate DEVICES on the shop's real devices | CURRENT (RF-6, D-088) | — |
| [legacy/08-TESTING-ACCEPTANCE.md](../08-testing/legacy/08-TESTING-ACCEPTANCE.md) | 39 acceptance suites and Definition of Done | LEGACY — SUPERSEDED by the three documents above; its suite identifiers are carried (ACCEPTANCE_SUITES section 2) | RF-6; removal in a later authorized step |
| [legacy/P8-QUALITY.md](../08-testing/legacy/P8-QUALITY.md) | Phase summary | LEGACY — SUPERSEDED by TEST_STRATEGY | RF-6; removal in a later authorized step |

### docs/09-operations — P9

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [ENVIRONMENTS.md](../09-operations/ENVIRONMENTS.md) | Environments, access, the server, Coolify, Cloudflare, certificates, network exposure, client address, configuration, database setup, scheduled tasks, logs and retention, the free-tier record, secrets and rotation, gate TARGET | CURRENT (RF-6, D-088) | — |
| [BACKUP_RECOVERY.md](../09-operations/BACKUP_RECOVERY.md) | Backup, the store, verification, restore drills, recovery-key custody, the restore runbook, the gap after a restore, recovery objectives | CURRENT (RF-6, D-088) | — |
| [OPERATIONS_RUNBOOK.md](../09-operations/OPERATIONS_RUNBOOK.md) | Operator procedures, scheduled commands, monitoring and alerts, the evidence of the system status, incident response and runbooks, maintenance, the routine calendar | CURRENT (RF-6, D-088) | — |
| [legacy/09-DEPLOYMENT-OPS.md](../09-operations/legacy/09-DEPLOYMENT-OPS.md) | Operations, production data protection, backup and recovery | LEGACY — SUPERSEDED by the three documents above, PRODUCTION_DATA_SAFETY and RELEASE_PROCESS | RF-6; removal in a later authorized step |
| [legacy/P9-OPERATIONS.md](../09-operations/legacy/P9-OPERATIONS.md) | Phase summary | LEGACY — SUPERSEDED by the three documents above | RF-6; removal in a later authorized step |

Production data protection is CURRENT in [PRODUCTION_DATA_SAFETY](PRODUCTION_DATA_SAFETY.md); who operates production is decided there in section 5 (D-088, question 20).

### docs/10-release — P10

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [RELEASE_PROCESS.md](../10-release/RELEASE_PROCESS.md) | Controlled releases, migration safety, deployment, checks, rollback, release records, the first installation, the cutover, gate RELEASE | CURRENT (RF-6, D-088) | — |
| [UAT_PILOT.md](../10-release/UAT_PILOT.md) | UAT scripts per role, the pilot and its acceptance, training, the shop's guidance | CURRENT (RF-6, D-088) | — |
| [legacy/P10-RELEASE-UAT.md](../10-release/legacy/P10-RELEASE-UAT.md) | Release readiness, cutover, pilot | LEGACY — SUPERSEDED by the two documents above | RF-6; removal in a later authorized step |

### docs/11-tasks — P11

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [EXECUTION_CONTEXT.md](../11-tasks/EXECUTION_CONTEXT.md) | The rules every Task executor follows: reading, authorization, statuses, the start of a Task, Git, engineering, the database, the interface, standing test obligations, evidence, reviews, Definition of Done, stop conditions, closing, change control, continuous execution | CURRENT (RF-7, D-090; corrected by D-091); approved by the Owner (D-092); amended by D-095 (EXC-11) and D-101 (section 19, continuous execution) | — |
| [`scripts/executor/`](../../scripts/executor/), [`.claude/settings.json`](../../.claude/settings.json) and [`.claude/agents/`](../../.claude/agents/) | The implementation of continuous execution (EXECUTION_CONTEXT section 19): the dispatcher's tick, the executor state and lock, the snapshot of the working tree, the publication guard, the commit, push and pull-request scripts, `watch.sh`, the watcher of the host session, the permission rules, and the executor and reviewer agents | CURRENT (D-101, D-104); set up and verified as [records/CONTINUOUS_EXECUTION_SETUP.md](records/CONTINUOUS_EXECUTION_SETUP.md) records | — |
| [TASK_PLAN.md](../11-tasks/TASK_PLAN.md) | The 50 Tasks: gates, the register of their status (the only record of a Task's status), the implementation sequence, the first eligible Task, the open items mapped, the former Build Units | CURRENT (RF-7, D-090; corrected by D-091 and D-092); approved by the Owner (D-092) | — |
| [`tasks/`](../11-tasks/tasks/) (50 files `TASK-NNN-<name>.md`) | One contract per Task: goal, prerequisites, reading, scope and ownership, traceability, acceptance criteria, tests, evidence, Definition of Done, stop conditions, expected files | CURRENT (RF-7, D-090; eleven contracts corrected by D-091, two by D-092); approved by the Owner (D-092) | — |
| [DEPENDENCY_GRAPH.md](../11-tasks/DEPENDENCY_GRAPH.md) | The dependencies of the Tasks, lanes and waves, the critical path | CURRENT (RF-7, D-090; corrected by D-091 and D-092); approved by the Owner (D-092); generated from TASK_PLAN, which prevails | — |
| [TRACEABILITY.md](../11-tasks/TRACEABILITY.md) | Every requirement, workflow, user task, suite, race, guard, threat, route, table, page, control and document mapped to its Task | CURRENT (RF-7, D-090; corrected by D-091 and D-092); approved by the Owner (D-092); generated with TASK_PLAN, which prevails | — |
| [legacy/11-BUILD-PLAN.md](../11-tasks/legacy/11-BUILD-PLAN.md), [legacy/P11-BUILD-UNITS.md](../11-tasks/legacy/P11-BUILD-UNITS.md) | Former build plan, dependency graph and estimates | LEGACY — SUPERSEDED by TASK_PLAN and DEPENDENCY_GRAPH (D-090) | RF-7; removal in a later authorized step |
| `legacy/build-units/` (27 cards and `registry.json`) | Former Build Units | LEGACY — SUPERSEDED by the Task contracts; each Build Unit is mapped in TASK_PLAN section 9 (FD-08, D-090) | RF-7; removal in a later authorized step |

No Build Unit may be executed; the Tasks replace them (FD-08). The Task baseline was written in RF-7 (D-090), corrected (D-091, D-092) and approved by the Owner (D-092; gate GATE-P11 of TASK_PLAN section 3). Each Task starts only on an instruction of the Owner that names it (EXECUTION_CONTEXT EXC-10) or under continuous execution (EXECUTION_CONTEXT section 19, D-101). A Task's evidence is kept in `docs/11-tasks/evidence/`, created by the first Task that starts (EXECUTION_CONTEXT section 11).

### docs/handoff

| Path | Subject | Status |
| --- | --- | --- |
| [CURRENT_HANDOFF.md](../handoff/CURRENT_HANDOFF.md) | Current operational state | CURRENT |

### Other tracked files

| Path | Subject | Status |
| --- | --- | --- |
| `assets/brand/` (2 files) | `bantukasir-logo.svg`, a codename prototype asset, and a `README.md` that describes it. No client identity is kept in the repository; each installation's shop identity comes from its BusinessProfile (D-038). The LATANSA mark was removed from the current tree under D-038 and remains only in Git history (last in commit `77f6676`). | CURRENT (README.md); historical asset (logo) |
| `.env.example`, `.gitignore` | Left over from an earlier stack | Stale — rebuilt by TASK-001 when it is authorized (OPEN_ITEMS T-02) |

## 4. One document per subject

When a CURRENT document is written for a subject, the legacy document for that subject stops being authoritative in the same step. It is either removed in that step, or kept for comparison with the status LEGACY — SUPERSEDED and removed in a later step. This map and LEGACY_INDEX are updated in the same step. Two documents must never both claim authority for the same subject.
