# Source of truth and document map

This document says which file is authoritative for each subject. If a subject has no current document yet, the map names the legacy document that still carries it.

## 1. Authority hierarchy

When sources disagree, the higher one prevails:

1. the latest Owner decision recorded in [DECISIONS](DECISIONS.md);
2. [AGENTS.md](../../AGENTS.md) and the documents in this folder;
3. the active Task contract (none exists yet);
4. current ADRs in `docs/adr/` (index: [docs/adr/README.md](../adr/README.md));
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
- Where a CURRENT architecture, security or technical-rule document or an ADR (`docs/04-architecture/`, `docs/05-security/`, `docs/06-api-performance/`, `docs/adr/`, written in RF-3A and RF-3B) and a legacy document still in force for another subject disagree on an architecture, security or technical rule, the CURRENT document prevails. Where a LEGACY — DRAFT operations or release document differs from ADR-002 or ADR-003, the ADR prevails. The legacy text is not edited.
- OPEN_ITEMS T-16 was resolved by D-060, D-061 and [ADR-008](../adr/ADR-008-cost-allocation-representation.md) (RF-3B). The legacy statements that would have settled T-16 (a) or (b), listed in RF-2 ([COSTING](../02-domain/COSTING.md) section 10), received their disposition in ADR-008: each is adopted or rejected there, with its reason. That disposition does not change the status of the document a statement belongs to.
- Where a later decision in [DECISIONS](DECISIONS.md) changes a rule written in a legacy document still in force, the decision prevails (section 1). The legacy text is not edited. Known cases:

| Legacy document still in force | Rule that no longer applies | Decision |
| --- | --- | --- |
| `docs/03-workflows/legacy/P3-CRITICAL-WORKFLOWS.md` | An ordinary sale passes a separate review step; no CSV import; refunds from the Owner's funds only and expected cash without refunds; executed payout evidence reviewed before a drawer-funded refund commits; Operations sees no receiving cost after submitting it | D-033, D-035, D-037, D-040, D-046, D-047 |
| `docs/08-testing/legacy/08-TESTING-ACCEPTANCE.md` (draft) | Suites AUTH02 (write-only cost), RET01 and SESSION04 (refunds from Owner funds only; expected cash without refunds), UX01 (approval of the former design revision), SEARCH01 (a retired token is never recycled) | D-024, D-025, D-033, D-035, D-051, D-052 |
| `docs/09-operations/legacy/09-DEPLOYMENT-OPS.md` (draft) | Docker Compose packaging; Nginx without automatic certificates, with operator-run certificate renewal; an operating-system scheduler | D-053 (ADR-002, ADR-003) |
| `docs/10-release/legacy/P10-RELEASE-UAT.md` (draft) | Receiving cost of Operations write-only | D-033 |

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
| [legacy/12-BARCODE-SCANNER.md](../02-domain/legacy/12-BARCODE-SCANNER.md) | Former search, barcode and scanner specification | LEGACY — SUPERSEDED by CATALOG_BARCODE for domain rules; its scanner screen behaviour is LEGACY — INPUT ONLY for RF-4 and RF-5 | RF-2; removal in a later authorized step, not before RF-5 |
| [legacy/15-FINANCE-PROFITABILITY.md](../02-domain/legacy/15-FINANCE-PROFITABILITY.md) | Former cost and gross profit specification | LEGACY — SUPERSEDED by COSTING | RF-2; removal in a later authorized step |
| [legacy/17-POS-SALES.md](../02-domain/legacy/17-POS-SALES.md) | Former sale, payment, receipt and cash session specification | LEGACY — SUPERSEDED by SALES_CASH for domain rules; its receipt and screen layouts are LEGACY — INPUT ONLY for RF-4 and RF-5 | RF-2; removal in a later authorized step, not before RF-5 |

The domain part of `docs/04-architecture/legacy/02-ARCHITECTURE.md` (its entity table and its numbers and time) is superseded by DOMAIN_MODEL.

### docs/03-workflows — P3

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [legacy/P3-CRITICAL-WORKFLOWS.md](../03-workflows/legacy/P3-CRITICAL-WORKFLOWS.md) | Critical workflows | LEGACY — IN FORCE | RF-5 |

Route contracts and interaction contracts do not exist yet (RF-5).

### docs/04-architecture — P4

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [ARCHITECTURE.md](../04-architecture/ARCHITECTURE.md) | Context, layers, units of work, read models, payment, media and document boundaries, runtime constraints, database privilege separation | CURRENT (RF-3A) | — |
| [STACK_AND_MODULES.md](../04-architecture/STACK_AND_MODULES.md) | Stack and version policy, module map with entity ownership and dependencies, directory layout, no speculative abstraction | CURRENT (RF-3A) | — |
| [SCHEMA.md](../04-architecture/SCHEMA.md) | Logical database schema: tables, keys, constraints, facts and projections, retention classes, document numbers, locks on rows, guards, the invariant-to-enforcement matrix, database privileges, indexes | CURRENT (RF-3B) | — |
| [legacy/02-ARCHITECTURE.md](../04-architecture/legacy/02-ARCHITECTURE.md) | Former architecture, transaction and locking contract; database constraints; domain part (entity table, numbers and time) | LEGACY — SUPERSEDED as a whole: the domain part by DOMAIN_MODEL (RF-2), the architecture parts by the RF-3A documents, §Constraint database and the schema-delivery part of §Payment extension boundary dan schema delivery by SCHEMA (RF-3B). Section by section in [LEGACY_INDEX](LEGACY_INDEX.md) sections 8 and 9 | RF-2; RF-3A; RF-3B (final status under plan decision P-6, effective when the Owner accepts the RF-3B checkpoint); removal in a later authorized step |
| [legacy/P4-ARCHITECTURE.md](../04-architecture/legacy/P4-ARCHITECTURE.md) | Former stack comparison and choice, modules, schema, testing and deployment implications | LEGACY — SUPERSEDED, except §Testing implications and §Deployment, operasi dan maintenance: LEGACY — INPUT ONLY for RF-6 (ADR-002 and ADR-003 prevail where the deployment section differs), and §Sumber primer yang diverifikasi saat planning: RECORD. §Schema, immutability dan constraints is superseded by SCHEMA (RF-3B); every other part by the RF-3A documents. Section by section in LEGACY_INDEX sections 8 and 9 | RF-3A; RF-3B (final status under P-6, effective when the Owner accepts the RF-3B checkpoint); RF-6 (testing and deployment input) |

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

### docs/07-ux-design — P7

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [USER_TASKS.md](../07-ux-design/USER_TASKS.md) | User tasks per role, frequency, devices, step budgets | CURRENT (RF-4A); gate P7-G1 not yet approved | — |
| [INFORMATION_ARCHITECTURE.md](../07-ux-design/INFORMATION_ARCHITECTURE.md) | Areas, navigation per role, page inventory with access classes, capability and requirement coverage, traceability of the former candidate and of P7-G01 to P7-G18 | CURRENT (RF-4A); gate P7-G1 not yet approved | — |
| [NAVIGATION_CONTRACTS.md](../07-ux-design/NAVIGATION_CONTRACTS.md) | Global navigation rules and one navigation and back-action contract per page | CURRENT (RF-4A); gate P7-G1 not yet approved | — |
| [INTERACTION_INVENTORY.md](../07-ux-design/INTERACTION_INVENTORY.md) | Every visible control per page, with capability, action, review, scanner safety and result state; zero dead interactions | CURRENT (RF-4A); gate P7-G1 not yet approved; routes in RF-5 | — |
| [UI_STATES.md](../07-ux-design/UI_STATES.md) | State catalogue, error-code mapping, uncertain-outcome recovery, states per page | CURRENT (RF-4A); gate P7-G1 not yet approved; wording in RF-4B | — |
| [ADAPTIVE_AND_ACCESSIBILITY.md](../07-ux-design/ADAPTIVE_AND_ACCESSIBILITY.md) | Device classes, adaptive rules and the exact accessibility targets of REQ-UX-03 | CURRENT (RF-4A); gate P7-G1 not yet approved | — |
| [legacy/DESIGN.md](../07-ux-design/legacy/DESIGN.md) | Draft visual contract of the former design candidate | LEGACY — INPUT ONLY | RF-4C |
| [legacy/P7-UX-DESIGN.md](../07-ux-design/legacy/P7-UX-DESIGN.md) | Former P7 workflow | LEGACY — SUPERSEDED by the RF-4A documents and plan decisions P-10 and P-14 (D-070) | RF-4A; removal in a later authorized step |
| [legacy/P7-COVERAGE.md](../07-ux-design/legacy/P7-COVERAGE.md) | Capability coverage matrix G01 to G18 | LEGACY — SUPERSEDED by INFORMATION_ARCHITECTURE (section 12 maps every group) and INTERACTION_INVENTORY | RF-4A; removal in a later authorized step |
| `legacy/p7-correction/` (7 files), `legacy/p7-owner-r1/` (8 files) | Evidence for the former candidate | RECORD | removed after RF-4 |

P7 is being rebuilt (D-024) as three checkpoints, RF-4A, RF-4B and RF-4C, ending at gates P7-G1, P7-G2 and P7-G3 (D-025; plan decision P-10, D-070). RF-4A wrote the six CURRENT documents above for gate P7-G1 (navigation and page structure); the Owner accepted its checkpoint `c8dc234` (D-078). Four of them are amended for the Owner's payment-method decision Q38 (D-077, D-079): USER_TASKS, INFORMATION_ARCHITECTURE, INTERACTION_INVENTORY and ADAPTIVE_AND_ACCESSIBILITY; CURRENT_HANDOFF section 1 shows whether that amendment is committed. The gate is approved only by a separate Owner decision. Their Indonesian labels are working labels until P7-G2. The former candidate, design revision `sha256-bef01d649ea91b2b`, was never approved and will not be. No approved visual design exists. Nothing in this folder may be used as an approved design contract; colour and spacing tokens are carried forward only as input. The localization architecture is decided in [ADR-005](../adr/ADR-005-localization-architecture.md) and is input to `LOCALIZATION.md` in RF-4.

### docs/08-testing — P8

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [legacy/08-TESTING-ACCEPTANCE.md](../08-testing/legacy/08-TESTING-ACCEPTANCE.md) | 39 acceptance suites and Definition of Done | LEGACY — DRAFT | RF-6 |
| [legacy/P8-QUALITY.md](../08-testing/legacy/P8-QUALITY.md) | Phase summary | LEGACY — DRAFT | RF-6 |

### docs/09-operations — P9

| Path | Subject | Status | Replaced in |
| --- | --- | --- | --- |
| [legacy/09-DEPLOYMENT-OPS.md](../09-operations/legacy/09-DEPLOYMENT-OPS.md) | Operations, production data protection, backup and recovery | LEGACY — DRAFT; ADR-002 and ADR-003 prevail where it differs | RF-6 |
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
