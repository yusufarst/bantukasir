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
- Its identifiers (`BU-…`, `CORE-…`, acceptance suite names, `G1`–`G4`) remain valid references until the rebuilt documents replace them.

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
| [legacy/01-PRD.md](../01-product/legacy/01-PRD.md) | Product scope, roles, key flows, "Later" list | LEGACY — IN FORCE | RF-2 |
| [legacy/P1-PRODUCT.md](../01-product/legacy/P1-PRODUCT.md) | Phase summary of the above | LEGACY — IN FORCE | RF-2 |
| [legacy/requirements/core.json](../01-product/legacy/requirements/core.json), [CORE-MAP.md](../01-product/legacy/requirements/CORE-MAP.md) | 73 Core requirement entries and their mapping | LEGACY — INPUT ONLY | RF-2 |

### docs/02-domain — P2

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [legacy/P2-DOMAIN.md](../02-domain/legacy/P2-DOMAIN.md) | Domain boundaries and invariants | LEGACY — IN FORCE | RF-2 |
| [legacy/06-INVENTORY-SPEC.md](../02-domain/legacy/06-INVENTORY-SPEC.md) | Stock movements, opening stock, restock attention | LEGACY — IN FORCE | RF-2 |
| [legacy/12-BARCODE-SCANNER.md](../02-domain/legacy/12-BARCODE-SCANNER.md) | Product search, barcode tokens, scanner input | LEGACY — IN FORCE | RF-2 |
| [legacy/15-FINANCE-PROFITABILITY.md](../02-domain/legacy/15-FINANCE-PROFITABILITY.md) | Cost of goods and gross profit | LEGACY — IN FORCE | RF-2 |
| [legacy/17-POS-SALES.md](../02-domain/legacy/17-POS-SALES.md) | Sale, payment, receipts, cash sessions | LEGACY — IN FORCE | RF-2 |

The entity table of the domain model is inside `docs/04-architecture/legacy/02-ARCHITECTURE.md`.

### docs/03-workflows — P3

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [legacy/P3-CRITICAL-WORKFLOWS.md](../03-workflows/legacy/P3-CRITICAL-WORKFLOWS.md) | Critical workflows | LEGACY — IN FORCE | RF-5 |

Route contracts and interaction contracts do not exist yet (RF-5).

### docs/04-architecture — P4

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [legacy/02-ARCHITECTURE.md](../04-architecture/legacy/02-ARCHITECTURE.md) | Architecture, domain entities, transaction and locking contract | LEGACY — IN FORCE | RF-2 (domain part), RF-3 (architecture part) |
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
| `assets/brand/` (3 files) | Historical brand assets, including a logo of another brand. Its `README.md` predates the re-foundation and is not authoritative; RF-1 changed only its two links to moved documents (D-028, OPEN_ITEMS T-13). | Under review — OPEN_ITEMS question 14 |
| `.env.example`, `.gitignore` | Left over from an earlier stack | Stale — rebuilt when implementation is authorized (OPEN_ITEMS T-02) |

## 4. One document per subject

When a CURRENT document is written for a subject, the legacy document for that subject stops being authoritative in the same step. It is either removed in that step, or kept for comparison with the status LEGACY — SUPERSEDED and removed in a later step. This map and LEGACY_INDEX are updated in the same step. Two documents must never both claim authority for the same subject.
