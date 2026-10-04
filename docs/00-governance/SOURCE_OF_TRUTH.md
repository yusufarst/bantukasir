# Source of truth and document map

This document says which file is authoritative for each subject. If a subject has no current document yet, the map names the legacy document that still carries it.

## 1. Authority hierarchy

When sources disagree, the higher one prevails:

1. the latest Owner decision recorded in [DECISIONS](DECISIONS.md);
2. [AGENTS.md](../../AGENTS.md) and the documents in this folder;
3. the active Task contract (none exists yet);
4. current ADRs in `docs/adr/` (none exist yet);
5. the product, domain, workflow, architecture, security, design, testing, operations and release documents listed in section 3;
6. implementation and tests (none exist yet);
7. [CURRENT_HANDOFF](../handoff/CURRENT_HANDOFF.md);
8. chat and session context.

When a contradiction is found: determine whether one source supersedes the other; update the repository so the contradiction does not persist; if it cannot be resolved without an Owner decision, record it in [OPEN_ITEMS](OPEN_ITEMS.md) and stop the affected work. Never choose silently.

## 2. Status vocabulary for documents

| Status | Meaning |
| --- | --- |
| CURRENT | Authoritative. Written under the re-foundation. |
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
- While OPEN_ITEMS T-16 is open, any legacy statement, in force or superseded, that would settle T-16 (a) or (b) — for example that original issue-cost allocations are kept, versioned or immutable, or which allocation revision a return uses — carries no authority on that point. [COSTING](../02-domain/COSTING.md) section 10 lists those found in RF-2. The rest of the legacy rule each belongs to keeps its status.
- Where a later decision in [DECISIONS](DECISIONS.md) changes a rule written in a legacy document still in force, the decision prevails (section 1). The legacy text is not edited. Known cases:

| Legacy document still in force | Rule that no longer applies | Decision |
| --- | --- | --- |
| `docs/03-workflows/legacy/P3-CRITICAL-WORKFLOWS.md` | An ordinary sale passes a separate review step; CASH or BANK_TRANSFER only; no CSV import; refunds from the Owner's funds only and expected cash without refunds; executed payout evidence reviewed before a drawer-funded refund commits; Operations sees no receiving cost after submitting it | D-033, D-035, D-036, D-037, D-040, D-046, D-047 |
| `docs/04-architecture/legacy/02-ARCHITECTURE.md` (architecture part) | A new issue-cost allocation version kept for every cost revision, as a requirement (D-034 does not require keeping earlier derived values; whether an allocation is an original fact or a derived value is OPEN — T-16); CASH and BANK_TRANSFER as the only methods | D-034, D-036 |
| `docs/04-architecture/legacy/P4-ARCHITECTURE.md` | CASH or BANK_TRANSFER only; cost entered by Operations is write-only | D-033, D-036 |
| `docs/05-security/legacy/04-AUTH-RBAC-SECURITY.md` | Purchase cost entered by Operations is write-only | D-033 |
| `docs/05-security/legacy/P5-SECURITY.md` | Receiving cost of Operations handled as write-only | D-033 |
| `docs/06-api-performance/legacy/P6-CONCURRENCY-API.md` | CASH and BANK_TRANSFER as the only methods | D-036 |
| `docs/08-testing/legacy/08-TESTING-ACCEPTANCE.md` (draft) | Suites AUTH02 (write-only cost), RET01 and SESSION04 (refunds from Owner funds only; expected cash without refunds), POS02 and PAYEXT01 (no QRIS), UX01 (approval of the former design revision) | D-024, D-025, D-033, D-035, D-036 |
| `docs/10-release/legacy/P10-RELEASE-UAT.md` (draft) | Receiving cost of Operations write-only | D-033 |

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
| [TOOLCHAIN.md](TOOLCHAIN.md) | Tool capabilities and MCP policy | CURRENT |
| [COST_POLICY.md](COST_POLICY.md) | Recurring-cost rule and inventory | CURRENT |
| [PRODUCTION_DATA_SAFETY.md](PRODUCTION_DATA_SAFETY.md) | Production data protection | CURRENT |
| [REFOUNDATION_PLAN.md](REFOUNDATION_PLAN.md) | Re-foundation steps RF-0 to RF-7 | CURRENT |
| [LEGACY_INDEX.md](LEGACY_INDEX.md) | Where older material went and how to recover it | CURRENT |
| [records/REFOUNDATION_ANALYSIS_2026-10-03.md](records/REFOUNDATION_ANALYSIS_2026-10-03.md) | Re-foundation analysis report, revision 2 | RECORD |
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
| [GLOSSARY.md](../02-domain/GLOSSARY.md) | One English term and one provisional Indonesian shop term per concept | CURRENT (RF-2); Indonesian terms provisional until gate P7-G2 | — |
| [DOMAIN_MODEL.md](../02-domain/DOMAIN_MODEL.md) | Domain map: entities, relationships, states, global invariants, numbers and time, commands, users, audit, BusinessProfile, lifecycle matrix, retention | CURRENT (RF-2) | — |
| [CATALOG_BARCODE.md](../02-domain/CATALOG_BARCODE.md) | Products, categories, prices, barcodes, Product Search, scanning, labels, CSV product import, product photos | CURRENT (RF-2) | — |
| [INVENTORY.md](../02-domain/INVENTORY.md) | Stock movements and balance, receiving, manual issue, opening stock, adjustment, reversal, stock attention, reconciliation | CURRENT (RF-2) | — |
| [COSTING.md](../02-domain/COSTING.md) | Cost evidence and visibility, moving weighted average, corrections and replay, gross profit reporting; open design issue T-16 | CURRENT (RF-2) | — |
| [SALES_CASH.md](../02-domain/SALES_CASH.md) | Sale, payment and its confirmation, receipts, cash registers and sessions, expected cash, refunds and returns, daily summary | CURRENT (RF-2) | — |
| [legacy/P2-DOMAIN.md](../02-domain/legacy/P2-DOMAIN.md) | Former domain boundaries and invariants | LEGACY — SUPERSEDED by DOMAIN_MODEL | RF-2; removal in a later authorized step |
| [legacy/06-INVENTORY-SPEC.md](../02-domain/legacy/06-INVENTORY-SPEC.md) | Former stock specification | LEGACY — SUPERSEDED by INVENTORY | RF-2; removal in a later authorized step |
| [legacy/12-BARCODE-SCANNER.md](../02-domain/legacy/12-BARCODE-SCANNER.md) | Former search, barcode and scanner specification | LEGACY — SUPERSEDED by CATALOG_BARCODE for domain rules; its scanner screen behaviour is LEGACY — INPUT ONLY for RF-4 and RF-5 | RF-2; removal after RF-5 |
| [legacy/15-FINANCE-PROFITABILITY.md](../02-domain/legacy/15-FINANCE-PROFITABILITY.md) | Former cost and gross profit specification | LEGACY — SUPERSEDED by COSTING | RF-2; removal in a later authorized step |
| [legacy/17-POS-SALES.md](../02-domain/legacy/17-POS-SALES.md) | Former sale, payment, receipt and cash session specification | LEGACY — SUPERSEDED by SALES_CASH for domain rules; its receipt and screen layouts are LEGACY — INPUT ONLY for RF-4 and RF-5 | RF-2; removal after RF-5 |

The domain part of `docs/04-architecture/legacy/02-ARCHITECTURE.md` (its entity table and its numbers and time) is superseded by DOMAIN_MODEL.

### docs/03-workflows — P3

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [legacy/P3-CRITICAL-WORKFLOWS.md](../03-workflows/legacy/P3-CRITICAL-WORKFLOWS.md) | Critical workflows | LEGACY — IN FORCE | RF-5 |

Route contracts and interaction contracts do not exist yet (RF-5).

### docs/04-architecture — P4

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [legacy/02-ARCHITECTURE.md](../04-architecture/legacy/02-ARCHITECTURE.md) | Architecture, transaction and locking contract; its domain part (entity table, numbers and time) | Architecture part: LEGACY — IN FORCE. Domain part: LEGACY — SUPERSEDED by DOMAIN_MODEL (RF-2) | RF-3 (architecture part) |
| [legacy/P4-ARCHITECTURE.md](../04-architecture/legacy/P4-ARCHITECTURE.md) | Stack comparison and choice, modules, constraints | LEGACY — IN FORCE | RF-3 |

A logical database schema does not exist yet (RF-3).

### docs/05-security — P5

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [legacy/04-AUTH-RBAC-SECURITY.md](../05-security/legacy/04-AUTH-RBAC-SECURITY.md) | Authentication, roles, privacy | LEGACY — IN FORCE | RF-3 |
| [legacy/P5-SECURITY.md](../05-security/legacy/P5-SECURITY.md) | Threat model and security baseline | LEGACY — IN FORCE | RF-3 |

### docs/06-api-performance — P6

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [legacy/P6-CONCURRENCY-API.md](../06-api-performance/legacy/P6-CONCURRENCY-API.md) | Concurrency, idempotency, HTTP contract, performance targets | LEGACY — IN FORCE | RF-3 |

### docs/07-ux-design — P7

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [legacy/DESIGN.md](../07-ux-design/legacy/DESIGN.md) | Draft visual contract of the former design candidate | LEGACY — INPUT ONLY | RF-4 |
| [legacy/P7-UX-DESIGN.md](../07-ux-design/legacy/P7-UX-DESIGN.md) | Former P7 workflow | LEGACY — INPUT ONLY | RF-4 |
| [legacy/P7-COVERAGE.md](../07-ux-design/legacy/P7-COVERAGE.md) | Capability coverage matrix G01 to G18 | LEGACY — INPUT ONLY | RF-4 |
| `legacy/p7-correction/` (7 files), `legacy/p7-owner-r1/` (8 files) | Evidence for the former candidate | RECORD | removed after RF-4 |

P7 is being rebuilt (D-024). The former candidate, design revision `sha256-bef01d649ea91b2b`, was never approved and will not be. No approved design exists. Nothing in this folder may be used as an approved design contract; colour and spacing tokens are carried forward only as input.

### docs/08-testing — P8

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [legacy/08-TESTING-ACCEPTANCE.md](../08-testing/legacy/08-TESTING-ACCEPTANCE.md) | 39 acceptance suites and Definition of Done | LEGACY — DRAFT | RF-6 |
| [legacy/P8-QUALITY.md](../08-testing/legacy/P8-QUALITY.md) | Phase summary | LEGACY — DRAFT | RF-6 |

### docs/09-operations — P9

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [legacy/09-DEPLOYMENT-OPS.md](../09-operations/legacy/09-DEPLOYMENT-OPS.md) | Operations, production data protection, backup and recovery | LEGACY — DRAFT | RF-6 |
| [legacy/P9-OPERATIONS.md](../09-operations/legacy/P9-OPERATIONS.md) | Phase summary | LEGACY — DRAFT | RF-6 |

Production data protection is already CURRENT in [PRODUCTION_DATA_SAFETY](PRODUCTION_DATA_SAFETY.md). Who may operate on production is an open Owner decision (question 20).

### docs/10-release — P10

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [legacy/P10-RELEASE-UAT.md](../10-release/legacy/P10-RELEASE-UAT.md) | Release readiness, cutover, pilot | LEGACY — DRAFT | RF-6 |

### docs/11-tasks — P11

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [legacy/11-BUILD-PLAN.md](../11-tasks/legacy/11-BUILD-PLAN.md), [legacy/P11-BUILD-UNITS.md](../11-tasks/legacy/P11-BUILD-UNITS.md) | Former build plan, dependency graph and estimates | LEGACY — INPUT ONLY | RF-7 |
| `legacy/build-units/` (27 cards and `registry.json`) | Former Build Units | LEGACY — INPUT ONLY | RF-7 |

No Build Unit may be executed. There is no Task baseline. `EXECUTION_CONTEXT.md`, `TASK_PLAN.md` and Task files are created in RF-7.

### docs/handoff

| Path | Subject | Status |
| --- | --- | --- |
| [CURRENT_HANDOFF.md](../handoff/CURRENT_HANDOFF.md) | Current operational state | CURRENT |

### Other tracked files

| Path | Subject | Status |
| --- | --- | --- |
| `assets/brand/` (2 files) | `bantukasir-logo.svg`, a codename prototype asset, and a `README.md` that describes it. No client identity is kept in the repository; each installation's shop identity comes from its BusinessProfile (D-038). The LATANSA mark was removed from the current tree under D-038 and remains only in Git history (last in commit `77f6676`). | CURRENT (README.md); historical asset (logo) |
| `.env.example`, `.gitignore` | Left over from an earlier stack | Stale — rebuilt when implementation is authorized (OPEN_ITEMS T-02) |

## 4. One document per subject

When a CURRENT document is written for a subject, the legacy document for that subject stops being authoritative in the same step. It is either removed in that step, or kept for comparison with the status LEGACY — SUPERSEDED and removed in a later step. This map and LEGACY_INDEX are updated in the same step. Two documents must never both claim authority for the same subject.
