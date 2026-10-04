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
| RF-2 | Product and domain: merged product blueprint; requirement register with real requirement statements; glossary in Indonesian and English; entity lifecycle matrix; domain rules | P1, P2 | Answers to questions 8 to 15 and 19 (given: D-032 to D-040); authorization (D-041); answers to questions 21 to 24 raised during RF-2 (given: D-042 to D-045) | AUTHORIZED (D-041) — its checkpoint is the commit that contains this version of this file; complete when the planning agent has reviewed the executor's report and the Owner has accepted the result |
| RF-3 | Architecture, security and technical rules: merged documents; ADRs; logical database schema; authentication profile; hosting topology | P4, P5, P6 | Answers to questions 5, 6, 7, 18, 25 and 26; authorization | NOT AUTHORIZED |
| RF-4 | P7 rebuild: tasks per role; information architecture; page inventory; navigation contracts; terminology and copy deck; design system; key screens in the BantuKasir design project | P7 | Authorization; review at gates P7-G1, P7-G2, P7-G3 | NOT AUTHORIZED |
| RF-5 | Workflows completed with route contracts and interaction contracts | P3 | Answer to question 29; authorization | NOT AUTHORIZED |
| RF-6 | Testing, operations and release documents adapted to the above; branch and environment model | P8, P9, P10 | Answer to question 20; authorization | NOT AUTHORIZED |
| RF-7 | Task baseline: execution context, Task plan with total count, Task contracts, dependency graph | P11 | Approval of the baseline | NOT AUTHORIZED |
| — | Implementation | — | A separate, explicit authorization after RF-7 | NOT AUTHORIZED |

Question numbers refer to [OPEN_ITEMS](OPEN_ITEMS.md). The order of RF-2 to RF-6 may be adjusted with the Owner's agreement; RF-4 depends on RF-2, and RF-5 depends on RF-4.

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
| P1 | Re-founded in RF-2 (D-041); complete on the Owner's acceptance of the RF-2 checkpoint | `docs/01-product/PRODUCT_BLUEPRINT.md`, `docs/01-product/REQUIREMENTS.md` (current); the legacy documents are superseded |
| P2 | Re-founded in RF-2 (D-041); complete on the Owner's acceptance of the RF-2 checkpoint | `docs/02-domain/` (current): GLOSSARY, DOMAIN_MODEL, CATALOG_BARCODE, INVENTORY, COSTING, SALES_CASH; the legacy documents are superseded |
| P3 | Legacy workflows in force, amended by D-035, D-036, D-037 and D-040; where they disagree with the CURRENT P1 and P2 documents, those prevail; route and interaction contracts do not exist | `docs/03-workflows/legacy/` |
| P4 | Legacy content in force; no logical schema | `docs/04-architecture/legacy/` |
| P5 | Legacy content in force; no authentication profile | `docs/05-security/legacy/` |
| P6 | Legacy content in force | `docs/06-api-performance/legacy/` |
| P7 | Rebuild decided (D-024); not started; no approved design | none; `docs/07-ux-design/legacy/` is input only |
| P8 | Legacy draft | `docs/08-testing/legacy/` |
| P9 | Legacy draft | `docs/09-operations/legacy/` |
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

**Questions raised during RF-2.** Questions 21 to 24 in OPEN_ITEMS concern product and domain rules; they were asked while drafting and answered by D-042 to D-045. Questions 27 and 28 came from the independent adversarial review and were answered by D-046 and D-047. Questions 25 and 26 concern authorization and catalogue correction and are needed before RF-3. Question 29 (a drawer-funded refund not yet paid out at close) came from the verification of the review fixes; no RF-2 rule depends on it and it is needed before RF-5.

**Not part of RF-2:** application code; database schemas or migrations; RF-3 or any later step; authentication redesign; page flows or interface layouts; the P7 replacement; rewriting the test suite; repartitioning Build Units or Tasks; deployment or production operations; push, merge, rebase or history rewrite; updating the safety bundle; deleting the superseded legacy documents.

**RF-2 is complete** only when its checkpoint commit exists, the planning agent has checked the executor's report against the repository, and the Owner has accepted the result.
