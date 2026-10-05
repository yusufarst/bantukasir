# Re-foundation plan

How the BantuKasir planning set is being rebuilt on AICWDF-4.3. The reasoning is in the [analysis report](records/REFOUNDATION_ANALYSIS_2026-10-03.md). The status of the step in progress is in [CURRENT_HANDOFF](../handoff/CURRENT_HANDOFF.md), which is the only place that states what is authorized now.

## 1. Principles

- Keep the substance that is sound: the money, stock, concurrency and security rules, and the stack.
- Rebuild what an executor and a shop need and the earlier planning lacked: a real requirement register, glossary and lifecycle rules, a logical schema, route and interaction contracts, a design built from user tasks and plain language, and bounded Tasks.
- One step at a time. Each step ends in a checkpoint and waits for the Owner. No step authorizes the next.
- Business meaning changes only through an Owner decision.
- When a subject is rebuilt, its legacy document stops being authoritative in the same step: it is removed, or marked superseded and removed in a later step (SOURCE_OF_TRUTH section 4).

## 2. Steps

| Step | Content | Framework phases | Needs from the Owner | Status |
| --- | --- | --- | --- | --- |
| RF-0 | Analysis report and Owner answers to the first questions | — | — | DONE — decisions D-019 to D-025 |
| RF-1 | Governance and structure: the new layout; rules, source of truth, decisions, open items, toolchain, cost and data-safety documents; handoff; legacy documents moved unchanged; legacy trees retired. No change to business content. | P0 | Authorization (D-026, D-029); acceptance of the result (D-030) | DONE — checkpoint `3c94f4f`, accepted by D-030 |
| RF-2 | Product and domain: merged product blueprint; requirement register with real requirement statements; glossary in Indonesian and English; entity lifecycle matrix; domain rules | P1, P2 | Answers to questions 8 to 15 and 19 (given: D-032 to D-040); authorization (D-041); answers to questions 21 to 24 raised during RF-2 (given: D-042 to D-045) | DONE — checkpoint `0c4945d`, accepted by the Owner on 4 October 2026 (recorded in D-055) |
| RF-3A | Architecture, security and technical rules: ARCHITECTURE and STACK_AND_MODULES; SECURITY_MODEL with the authentication profile, permission matrix and threat model; TECHNICAL_RULES with the lock order, race matrix and limits; ADR-001 to ADR-007, including the hosting topology | P4, P5, P6 | Answers to questions 25, 6, 5, 26, 31, 7 and 30 (given: D-048 to D-054); plan decisions P-1 to P-5; authorization (D-055) | AUTHORIZED (D-055) — its checkpoint is the commit that contains this version of this file; complete when the planning agent has reviewed the executor's report and the Owner has accepted the result |
| RF-3B | Logical database schema (`SCHEMA.md`); mapping of every persistent invariant to its constraint, lock, guard and test; the representation of issue-cost allocations (T-16 c) in its own ADR; items T-17 e, g and i | P4 | Acceptance of the RF-3A checkpoint; a separate authorization | NOT AUTHORIZED |
| RF-4 | P7 rebuild: tasks per role; information architecture; page inventory; navigation contracts; terminology and copy deck; design system; key screens in the BantuKasir design project | P7 | Authorization; review at gates P7-G1, P7-G2, P7-G3 | NOT AUTHORIZED |
| RF-5 | Workflows completed with route contracts and interaction contracts | P3 | Answer to question 29; authorization | NOT AUTHORIZED |
| RF-6 | Testing, operations and release documents adapted to the above; branch and environment model | P8, P9, P10 | Answers to questions 18 (plan decision P-3) and 20; authorization | NOT AUTHORIZED |
| RF-7 | Task baseline: execution context, Task plan with total count, Task contracts, dependency graph | P11 | Approval of the baseline | NOT AUTHORIZED |
| — | Implementation | — | A separate, explicit authorization after RF-7 | NOT AUTHORIZED |

Question numbers refer to [OPEN_ITEMS](OPEN_ITEMS.md). The order of RF-2 to RF-6 may be adjusted with the Owner's agreement; RF-4 depends on RF-2, and RF-5 depends on RF-4. RF-3 runs as two checkpoints, RF-3A and RF-3B (plan decision P-1, D-055); RF-3B starts only after the RF-3A checkpoint is accepted and a separate authorization is given.

## 3. P7 gates (D-025)

The rebuilt design is approved by the Owner in three separate gates, in this order. Each gate is recorded in [DECISIONS](DECISIONS.md) with the exact artifact approved.

| Gate | The Owner reviews | Artifact |
| --- | --- | --- |
| P7-G1 | Navigation and page structure: what each role can reach, in which order, and which pages exist | Information architecture, page inventory, navigation contracts |
| P7-G2 | Terminology and copy: the words the shop sees, in plain Indonesian | Glossary and copy deck |
| P7-G3 | Visual direction and key screens | Design system and a small set of key screens |

Useful visual tokens from the former candidate are carried forward as input (D-024). Nothing from the former candidate counts as approved.

## 4. Phase status

| Phase | Status | Authoritative content today |
| --- | --- | --- |
| P0 | Re-founded in RF-1 | `docs/00-governance/` (current) |
| P1 | Re-founded in RF-2 (D-041); complete — the Owner accepted the RF-2 checkpoint (D-055) | `docs/01-product/PRODUCT_BLUEPRINT.md`, `docs/01-product/REQUIREMENTS.md` (current); the legacy documents are superseded |
| P2 | Re-founded in RF-2 (D-041); complete — the Owner accepted the RF-2 checkpoint (D-055) | `docs/02-domain/` (current): GLOSSARY, DOMAIN_MODEL, CATALOG_BARCODE, INVENTORY, COSTING, SALES_CASH; the legacy documents are superseded |
| P3 | Legacy workflows in force, amended by D-035, D-036, D-037 and D-040; where they disagree with the CURRENT P1 and P2 documents, those prevail; route and interaction contracts do not exist | `docs/03-workflows/legacy/` |
| P4 | Re-founded in part in RF-3A (D-055): architecture, stack and modules, ADRs; the logical schema follows in RF-3B, and until then the legacy schema and constraint parts stay in force | `docs/04-architecture/ARCHITECTURE.md`, `docs/04-architecture/STACK_AND_MODULES.md`, `docs/adr/` (current); `02-ARCHITECTURE` §Constraint database and schema-delivery part, `P4-ARCHITECTURE` §Schema, immutability dan constraints (legacy, in force until RF-3B) |
| P5 | Re-founded in RF-3A (D-055); complete on the Owner's acceptance of the RF-3A checkpoint | `docs/05-security/SECURITY_MODEL.md` and ADR-004 (current); the legacy documents are superseded |
| P6 | Re-founded in RF-3A (D-055); complete on the Owner's acceptance of the RF-3A checkpoint | `docs/06-api-performance/TECHNICAL_RULES.md`, ADR-006 and ADR-007 (current); the legacy document is superseded |
| P7 | Rebuild decided (D-024); not started; no approved design; the localization architecture is decided (ADR-005) | none; `docs/07-ux-design/legacy/` is input only |
| P8 | Legacy draft | `docs/08-testing/legacy/` |
| P9 | Legacy draft; the hosting topology and minimum runtime are decided (ADR-002, ADR-003) | `docs/09-operations/legacy/`, amended by ADR-002 and ADR-003 |
| P10 | Legacy draft | `docs/10-release/legacy/` |
| P11 | To be rebuilt; no Task baseline; Build Units not executable | none; `docs/11-tasks/legacy/` is input only |

## 5. RF-1 in detail

RF-1 is carried out in two parts (D-021, D-026, D-028).

**Part A — planning architect, file operations only.** Rewrite `AGENTS.md`, `CLAUDE.md`, `README.md`; write the twelve new documents in `docs/00-governance/` and `docs/handoff/`; move 72 existing documents, unchanged, into `legacy/` subfolders; update the two link targets in `assets/brand/README.md` that those moves would otherwise break (D-028). No Git mutation, no deletion.

**Part B — implementation executor on Windows, one bounded instruction.** Confirm the baseline; create and verify the safety bundle (D-027); validate Part A independently; remove the 128 retired files with `git rm`; remove the `upstream` remote; stage exact paths; create one local commit under the Owner's identity; report and stop. No push, no merge, no change to `main`; the patch file and the stash are left untouched.

**RF-1 is complete** only when the checkpoint commit exists, the planning architect has checked the executor's report against the repository, and the Owner has accepted the result. All three conditions were met on 3 October 2026 (D-030).

**Not part of RF-1:** any change to product, domain, workflow, architecture, security, testing, operations or release content; translation of legacy documents; design work; route or interaction contracts; ADRs; Tasks; `.env.example`, `.gitignore`; any change under `assets/` other than the two link targets of D-028; tool installation; deleting the patch; dropping the stash; push, merge, or any change to `main`; application code.

## 6. RF-2 in detail

RF-2 is authorized by D-041 as one documentation checkpoint on baseline `4af59a5423298d25d672923279439951fc67ea4d`.

**Planning agent, working tree only.** Write eight CURRENT documents — `docs/01-product/PRODUCT_BLUEPRINT.md`, `REQUIREMENTS.md`; `docs/02-domain/GLOSSARY.md`, `DOMAIN_MODEL.md`, `CATALOG_BARCODE.md`, `INVENTORY.md`, `COSTING.md`, `SALES_CASH.md` — and change seven existing files: SOURCE_OF_TRUTH, LEGACY_INDEX, OPEN_ITEMS, DECISIONS, this plan, CURRENT_HANDOFF and the root README. No Git mutation, no deletion, no change under any `legacy/` folder. Ask the Owner one question at a time where a rule needs business meaning that no decision or valid legacy text gives; record each answer as a decision. Self-review, an independent adversarial review of COSTING, INVENTORY and SALES_CASH, a report to the Owner, and one executor instruction.

**Executor on Windows, one bounded instruction.** Validate the working tree independently (scope, hashes, legacy files unchanged, line endings, links), stage exact paths and create one local commit under the Owner's identity, with no attribution trailer. No push.

**Questions raised during RF-2.** Questions 21 to 24 in OPEN_ITEMS concern product and domain rules; they were asked while drafting and answered by D-042 to D-045. Questions 27 and 28 came from the independent adversarial review and were answered by D-046 and D-047. Questions 25 and 26 concern authorization and catalogue correction; they were answered before RF-3A by D-048 and D-051. Question 29 (a drawer-funded refund not yet paid out at close) came from the verification of the review fixes; no RF-2 rule depends on it and it is needed before RF-5.

**Not part of RF-2:** application code; database schemas or migrations; RF-3 or any later step; authentication redesign; page flows or interface layouts; the P7 replacement; rewriting the test suite; repartitioning Build Units or Tasks; deployment or production operations; push, merge, rebase or history rewrite; updating the safety bundle; deleting the superseded legacy documents.

**RF-2 is complete** only when its checkpoint commit exists, the planning agent has checked the executor's report against the repository, and the Owner has accepted the result. All three conditions were met on 4 October 2026 (D-055).

## 7. RF-3A in detail

RF-3A is authorized by D-055 as the first of two RF-3 checkpoints (plan decision P-1), on baseline `0c4945d001daf76723568f015e69b6f6c43342fb`.

**Planning agent, working tree only.** Write twelve CURRENT files — `docs/04-architecture/ARCHITECTURE.md`, `docs/04-architecture/STACK_AND_MODULES.md`, `docs/05-security/SECURITY_MODEL.md`, `docs/06-api-performance/TECHNICAL_RULES.md`, `docs/adr/README.md` and ADR-001 to ADR-007 — and change, only in the parts that concern RF-3A, SOURCE_OF_TRUTH, LEGACY_INDEX, OPEN_ITEMS, DECISIONS, this plan, FRAMEWORK_DEVIATIONS, COST_POLICY, CURRENT_HANDOFF, the root README, PRODUCT_BLUEPRINT, REQUIREMENTS, DOMAIN_MODEL, CATALOG_BARCODE, INVENTORY and COSTING (not COSTING section 10), and GLOSSARY and SALES_CASH only where a statement becomes false. Verify version-sensitive facts against current official documentation and record them in the ADRs. Mark legacy documents per section (P-5): `04-AUTH-RBAC-SECURITY`, `P5-SECURITY` and `P6-CONCURRENCY-API` are superseded; `02-ARCHITECTURE` and `P4-ARCHITECTURE` keep their schema and constraint parts in force until RF-3B ([LEGACY_INDEX](LEGACY_INDEX.md) section 8). No Git mutation, no deletion, no change under any `legacy/` folder. Self-review, an independent adversarial review of SECURITY_MODEL and TECHNICAL_RULES, and a report to the Owner for a second review; only then one executor instruction. After that review, D-057 added `AGENTS.md` and `docs/00-governance/PROJECT_RULES.md` to this scope, only to correct their stale localization wording (T-18), and asked for a final second review of the amended report before the executor instruction.

**Executor on Windows, one bounded instruction.** Validate the working tree independently, stage exact paths and create one local commit under the Owner's identity, with no attribution trailer. No push.

**Not part of RF-3A:** `SCHEMA.md`; any ADR beyond ADR-007; deciding T-16 or T-17 e, g or i; mapping invariants to schema; table, column or constraint design; application code, migrations or version pins; P7 work; route or interaction contracts; rewriting the test suite; repartitioning Tasks; accounts, purchases or deployment; push, merge, rebase or history rewrite; refreshing the safety bundle; deleting any legacy document; any real IP address, domain, provider identity, credential or backup location in the repository.

**RF-3A is complete** only when its checkpoint commit exists, the planning agent has checked the executor's report against the repository, and the Owner has accepted the result. RF-3B then needs a separate, explicit authorization.
