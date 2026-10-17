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
| RF-3A | Architecture, security and technical rules: ARCHITECTURE and STACK_AND_MODULES; SECURITY_MODEL with the authentication profile, permission matrix and threat model; TECHNICAL_RULES with the lock order, race matrix and limits; ADR-001 to ADR-007, including the hosting topology | P4, P5, P6 | Answers to questions 25, 6, 5, 26, 31, 7 and 30 (given: D-048 to D-054); plan decisions P-1 to P-5; authorization (D-055) | DONE — checkpoint `ee67b3a`, accepted by the Owner on 5 October 2026 (D-058) |
| RF-3B | Logical database schema (`SCHEMA.md`); mapping of every persistent invariant to its constraint, lock, guard and test; the representation of issue-cost allocations (T-16 c) in its own ADR; items T-17 e, g and i | P4 | Acceptance of the RF-3A checkpoint (D-058); answers to questions 33, 34 and 32 (given: D-060 to D-062); plan decisions P-6 to P-9; authorization (D-059) | DONE — checkpoint `b238fad`, accepted by the Owner on 6 October 2026 (D-064) |
| RF-4A | P7 structure: user tasks per role; information architecture and navigation per role; page inventory; navigation contracts; interaction inventory at capability and command level; UI states; adaptive and accessibility targets | P7 | Acceptance of the bookkeeping checkpoint (D-066); answers to questions 35 and 29 and to T-17 a, d, f and h (given: D-067 to D-069); plan decisions P-10 to P-15; authorization (D-070); the answer to review finding F-03 (given: D-071); the decisions on the independent final review (given: D-072 to D-075); the Owner's decision on repository visibility and its recording in RF-4A (given: D-076); then the Owner's separate approval of gate P7-G1 (given: D-082) | DONE — checkpoint `c8dc234`, accepted by the Owner on 17 October 2026 together with its SSD safety bundle (D-078); gate P7-G1 approved by the Owner on 17 October 2026, naming the Q38 checkpoint `d4c0594` and the hash of each of the six documents (D-082) |
| RF-4B | P7 terminology and copy: the glossary's shop terms finalized; the copy deck in plain Indonesian | P7 | Acceptance of the RF-4A checkpoint (given: D-078); the Q38 amendment (D-077, D-079; section 10; accepted: D-080); the approval of gate P7-G1 (given: D-082); authorization (given: D-083); then the approval of gate P7-G2 (given: D-086) | DONE — checkpoint `c4bf8e9`, accepted by the Owner on 17 October 2026 together with his authorization of one correction of COPY_DECK (D-084); the correction `551b4eb` accepted and gate P7-G2 approved on 17 October 2026, naming `551b4eb` and the hash of GLOSSARY and of COPY_DECK (D-086; section 11) |
| RF-4C | P7 visual design: design system and key screens, with the Golden Frontend Reference v1.0.0 as the approved reusable frontend reference and no design tool (D-085) | P7 | Acceptance of the RF-4B checkpoint and of its correction (given: D-084, D-086); authorization (given: D-086; P-14 as amended by D-085 sets no design-tool check); then the approval of gate P7-G3 | IN PROGRESS — authorized by D-086 on `6e73514`; paused by the Owner on 17 October 2026 with its work in progress committed in `3a7da19`; resumed on his instruction of 18 October 2026 and completed by the implementation executor as one local checkpoint (D-086; section 12; CURRENT_HANDOFF section 1 shows whether it is committed); gate P7-G3 is approved only by the Owner's separate decision |
| RF-5 | Workflows completed with route contracts and interaction contracts; the backend enforcement of D-067 to D-069, D-071 and D-072; the mechanisms of the server-authoritative recovery of D-073; the proposed backend mitigation of D-074 (OPEN_ITEMS N-07) | P3 | Decision on T-17 b and on the Owner replacement under D-069 (SEC-OP-03); acceptance of RF-4C; authorization (questions 29, 35 and 37 are answered: D-067, D-068, D-072) | NOT AUTHORIZED |
| RF-6 | Testing, operations and release documents adapted to the above, including the restore runbook's reconciliation of onboarding (D-072) and of requests from before a restore (D-073); branch and environment model | P8, P9, P10 | Answers to questions 18 (plan decision P-3) and 20; authorization | NOT AUTHORIZED |
| RF-7 | Task baseline: execution context, Task plan with total count, Task contracts, dependency graph | P11 | Approval of the baseline | NOT AUTHORIZED |
| — | Implementation | — | A separate, explicit authorization after RF-7 | NOT AUTHORIZED |

Question numbers refer to [OPEN_ITEMS](OPEN_ITEMS.md). The order of RF-2 to RF-6 may be adjusted with the Owner's agreement; RF-4 depends on RF-2, and RF-5 depends on RF-4. RF-3 runs as two checkpoints, RF-3A and RF-3B (plan decision P-1, D-055); RF-3B starts only after the RF-3A checkpoint is accepted and a separate authorization is given. RF-4 runs as three checkpoints, RF-4A, RF-4B and RF-4C, each ending at its P7 gate (plan decision P-10, D-070); each needs the acceptance of the previous checkpoint and its own authorization.

## 3. P7 gates (D-025)

The rebuilt design is approved by the Owner in three separate gates, in this order. Each gate is recorded in [DECISIONS](DECISIONS.md) with the exact artifact approved. A gate is not approved by its checkpoint: the Owner's second review of the checkpoint report is the substantive review, and the approval is a later Owner decision that names the checkpoint commit and the hash of each artifact (plan decision P-10, D-070).

| Gate | The Owner reviews | Artifact |
| --- | --- | --- |
| P7-G1 | Navigation and page structure: what each role can reach, in which order, and which pages exist | The six RF-4A documents of `docs/07-ux-design/`: user tasks, information architecture with the page inventory, navigation contracts, interaction inventory, UI states, adaptive and accessibility rules (D-070), as amended for Q38 (D-079) |
| P7-G2 | Terminology and copy: the words the shop sees, in plain Indonesian | Glossary and copy deck |
| P7-G3 | Visual direction and key screens | Design system and a small set of key screens |

P7-G1 is approved (D-082). P7-G2 is approved (D-086): GLOSSARY and COPY_DECK as committed in the RF-4B correction `551b4eb`, each named by its hash. P7-G3 is in progress in RF-4C (D-086), which the Owner paused on 17 October 2026 and resumed on 18 October 2026, and which has written DESIGN_SYSTEM, KEY_SCREENS and the previews for his review; P7-G3 is not approved.

Useful visual tokens from the former candidate are carried forward as input (D-024). Nothing from the former candidate counts as approved. For P7-G3 the approved reusable frontend reference is the Golden Frontend Reference v1.0.0 (D-085; [TOOLCHAIN](TOOLCHAIN.md) section 3); it approves no BantuKasir artifact, and gate P7-G3 is approved only by the Owner's separate decision.

## 4. Phase status

| Phase | Status | Authoritative content today |
| --- | --- | --- |
| P0 | Re-founded in RF-1 | `docs/00-governance/` (current) |
| P1 | Re-founded in RF-2 (D-041); complete — the Owner accepted the RF-2 checkpoint (D-055) | `docs/01-product/PRODUCT_BLUEPRINT.md`, `docs/01-product/REQUIREMENTS.md` (current); the legacy documents are superseded |
| P2 | Re-founded in RF-2 (D-041); complete — the Owner accepted the RF-2 checkpoint (D-055) | `docs/02-domain/` (current): GLOSSARY, DOMAIN_MODEL, CATALOG_BARCODE, INVENTORY, COSTING, SALES_CASH; the legacy documents are superseded |
| P3 | Legacy workflows in force, amended by D-035, D-037 and D-040 (the payment-method amendment of D-036 was reverted by D-077); where they disagree with the CURRENT P1 and P2 documents, those prevail; route and interaction contracts do not exist | `docs/03-workflows/legacy/` |
| P4 | Re-founded in RF-3A (D-055) — architecture, stack and modules, ADR-001 to ADR-007 — and in RF-3B (D-059) — the logical schema and ADR-008; complete — the Owner accepted the RF-3B checkpoint (D-064), and the legacy P4 documents are superseded as plan decision P-6 states | `docs/04-architecture/ARCHITECTURE.md`, `docs/04-architecture/STACK_AND_MODULES.md`, `docs/04-architecture/SCHEMA.md`, `docs/adr/` (current); the testing and deployment sections of `P4-ARCHITECTURE` are input only for RF-6 |
| P5 | Re-founded in RF-3A (D-055); complete — the Owner accepted the RF-3A checkpoint (D-058) | `docs/05-security/SECURITY_MODEL.md` and ADR-004 (current); the legacy documents are superseded |
| P6 | Re-founded in RF-3A (D-055); complete — the Owner accepted the RF-3A checkpoint (D-058) | `docs/06-api-performance/TECHNICAL_RULES.md`, ADR-006 and ADR-007 (current); the legacy document is superseded |
| P7 | Rebuild decided (D-024) as RF-4A, RF-4B and RF-4C (D-070). RF-4A wrote the structure documents for gate P7-G1 and corrected them after the independent final review and after the targeted re-verification of that remediation (D-075); its checkpoint `c8dc234` is accepted (D-078); four of the six documents are amended for Q38 (D-077, D-079; checkpoint `d4c0594` accepted, D-080); gate P7-G1 is approved (D-082). RF-4B wrote the copy deck and finalized the glossary's shop terms for gate P7-G2 (D-083; its checkpoint `c4bf8e9` and its correction `551b4eb` are accepted, D-084, D-086), which is approved (D-086); RF-4C, the visual design for gate P7-G3, is authorized (D-086), was paused and resumed by the Owner, and has written DESIGN_SYSTEM, KEY_SCREENS and the previews (section 12); no approved visual design; the localization architecture is decided (ADR-005), and its localization contract is COPY_DECK section 1 | `docs/07-ux-design/`: the six CURRENT RF-4A documents (gate P7-G1 approved) and COPY_DECK, with `docs/02-domain/GLOSSARY.md` (gate P7-G2 approved); DESIGN_SYSTEM and KEY_SCREENS with `previews/` (RF-4C; gate P7-G3 not approved); `legacy/DESIGN.md` is superseded by DESIGN_SYSTEM; `legacy/P7-UX-DESIGN.md` and `legacy/P7-COVERAGE.md` are superseded |
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

**RF-3A is complete** only when its checkpoint commit exists, the planning agent has checked the executor's report against the repository, and the Owner has accepted the result. RF-3B then needs a separate, explicit authorization. All three conditions were met on 5 October 2026 (D-058): the checkpoint is `ee67b3aac58f24198fd8f617410e824bc7ca1119`.

## 8. RF-3B in detail

RF-3B is authorized by D-059 as the second of the two RF-3 checkpoints (plan decision P-1), on baseline `ee67b3aac58f24198fd8f617410e824bc7ca1119`, with the RF-3B pre-authorization package (revision 2) and the Owner's answers to questions 33, 34 and 32 (D-060, D-061, D-062) as its controlling scope.

**Planning agent, working tree only.** Write two CURRENT files — `docs/04-architecture/SCHEMA.md` and `docs/adr/ADR-008-cost-allocation-representation.md` — and change, only in the parts that concern RF-3B, DECISIONS (D-058 to D-062), OPEN_ITEMS, SOURCE_OF_TRUTH, LEGACY_INDEX, this plan, CURRENT_HANDOFF, `docs/adr/README.md`, ARCHITECTURE, STACK_AND_MODULES, TECHNICAL_RULES, SECURITY_MODEL, DOMAIN_MODEL, COSTING, CATALOG_BARCODE, ADR-006, ADR-007, REQUIREMENTS and the root README; INVENTORY, SALES_CASH, GLOSSARY and PRODUCT_BLUEPRINT only where a statement becomes false or a recorded decision must be traced. Verify version-sensitive PostgreSQL and Laravel facts against current official documentation and record them in SCHEMA and ADR-008. Set the final status of `02-ARCHITECTURE` and `P4-ARCHITECTURE` under plan decision P-6. DDL appears only as specification text (P-7). No Git mutation, no deletion, no change under any `legacy/` folder, no migration, no executed SQL. Self-review, a fresh independent adversarial review of SCHEMA and ADR-008, and a report to the Owner for a second review; only then one executor instruction. The Owner's second review passed with clarifications (D-063), which the working tree records.

**Executor on Windows, one bounded instruction.** Validate the working tree independently, stage exact paths and create one local commit under the Owner's identity, with no attribution trailer. No push.

**Not part of RF-3B:** application code, migrations, executed SQL, version pins or installation; any ADR other than ADR-008; P7 work; route or interaction contracts; rewriting the test suite; repartitioning Tasks; accounts, purchases, deployment or production operations; push, merge, rebase, pull request or history rewrite; refreshing the safety bundle; deleting or editing any legacy document; answering questions 18, 20 or 29, item N-01 or any other deferred item; any real IP address, domain, provider identity, credential, key or backup location in the repository.

**RF-3B is complete** only when its checkpoint commit exists, the planning agent has checked the executor's report against the repository, and the Owner has accepted the result. RF-4 or any later step then needs a separate, explicit authorization. All three conditions were met on 6 October 2026 (D-064): the checkpoint is `b238fad4a9fde9f363124b365d088367a56d8168`.

## 9. RF-4A in detail

RF-4A is authorized by D-070 as the first of the three RF-4 checkpoints (plan decision P-10), on baseline `86822d5620dd031c6998cf0f0be24b7715b3b887`, with the RF-4A pre-authorization package (revision 3) and the Owner's decisions D-066 to D-069 as its controlling scope.

**Stop and resumption.** On 11 October 2026 the planning agent stopped RF-4A under section 14, condition 9, of the package: finding F-03 of the adversarial review, which a subagent of the planning session ran — whether the onboarding freeze may be set again after its release — carried business meaning. It asked the Owner one question. The question is number 36 in OPEN_ITEMS. The Owner answered with D-071 (at most one onboarding period per business-data lifecycle; its backend enforcement is an RF-5 obligation) and resumed RF-4A, which records D-071 and applies it within the files of the package's sections 5.1 and 5.2; in the rule documents it changes no rule text and records the RF-5 obligation in the open items of TECHNICAL_RULES section 14. Question 37, on business data restored from a backup taken before the first release, arose during the resumed RF-4A. The planning agent did not stop for it, which the Owner recorded as a documented noncompliance (D-075), and the Owner answered it with D-072.

**Independent final review and remediation.** A separate Claude session opened by the Owner reviewed the working tree read-only and recommended HOLD, with two MAJOR, six MINOR and ten NIT findings. The Owner decided D-072 to D-075 and resumed RF-4A for the remediation only (D-075): the planning agent corrects the findings within the package's sections 5.1 and 5.2 and the corresponding review outputs in the Owner's Claude Project, and updates CURRENT_HANDOFF last. A targeted re-verification of the corrections in a separate Claude Opus 5.5 session at effort Max comes before the planning agent may recommend that the working tree is ready for its checkpoint. The first one, on 12 October 2026, found PASS WITH NITS; on the Owner's instruction of the same day the planning agent corrected its twelve NIT findings within the same files (recorded under D-075). The second, on the same day, re-verified those corrections and found PASS WITH NITS with four new NIT findings; on the Owner's instruction the planning agent corrected them within the same files (recorded under D-075), and those corrections are re-verified in the same way. A checkpoint follows only a passing re-verification and the Owner's request for it; neither D-075 nor its continuations authorize one.

**Visibility extension (D-076).** The targeted re-verification of the last corrections found PASS on 12 October 2026. The same day the Owner decided that the repository stays public and chose to record that decision in this working tree before the checkpoint (option B1). RF-4A is resumed for that extension only: the decision, the accepted risks and the publication guard in DECISIONS; N-01, N-04 and the new N-08 and N-09 in OPEN_ITEMS; the decision trace and its counts in REQUIREMENTS; the list of decisions in LEGACY_INDEX; the RF-4A status in this plan; and CURRENT_HANDOFF, updated last. A targeted re-verification of these changes in a separate session and the Owner's second review come before the checkpoint instruction. That re-verification found PASS WITH NITS, and the re-verification of its corrections found PASS on 16 October 2026; the Owner completed his second review the same day and deferred his comprehensive review of the UX documents until after the Q38 amendment and before gate P7-G1. Both are recorded under D-076, together with open item N-10, and these changes are re-verified in the same way before the checkpoint instruction. D-076 authorizes no checkpoint.

**Planning agent, working tree only.** Write six CURRENT files in `docs/07-ux-design/` — USER_TASKS, INFORMATION_ARCHITECTURE, NAVIGATION_CONTRACTS, INTERACTION_INVENTORY, UI_STATES and ADAPTIVE_AND_ACCESSIBILITY — and change, only as section 5.2 of the package lists, DECISIONS (D-066 to D-070 under section 5.2; D-071 to D-076 under the Owner's later decisions), OPEN_ITEMS, SOURCE_OF_TRUTH, LEGACY_INDEX, this plan, CURRENT_HANDOFF, REQUIREMENTS, `AGENTS.md` section 6 and the root README; and, only to replace a statement that question 35, question 29 or T-17 a, d, f or h is open with a reference to its decision, PRODUCT_BLUEPRINT, SALES_CASH, INVENTORY, CATALOG_BARCODE, DOMAIN_MODEL, TECHNICAL_RULES, SCHEMA and SECURITY_MODEL. Statements about T-17 b stay OPEN. Enforcement mechanisms and the clarified rule texts of D-068 are written in RF-5, not here. Pre-authentication pages are distinguished from capability-protected pages, and T-17 h is an Owner-approved validation rule whose backend enforcement RF-5 specifies. No Git mutation, no deletion, no change under any `legacy/` folder, no design tool, no ADR. Self-review against the criteria G1-01 to G1-15 of the package, an adversarial review (run as subagents of the planning session, D-075), an independent final review in a separate session with a targeted re-verification of its corrections (D-075), and a report to the Owner with non-normative site maps and wireflows as review aids in the Owner's Claude Project; the Owner's second review is the substantive review of gate P7-G1. Only then one executor instruction.

**Executor on Windows, one bounded instruction.** Validate the working tree independently, stage exact paths and create one local commit under the Owner's identity, with no attribution trailer, running as Claude Opus 5.5 at effort Max (D-066). Whether the SSD bundle and a push belong to the same operation is decided with that instruction (D-065); any push is preceded by the publication guard of D-076.

**Not part of RF-4A:** final copy and the finalization of the glossary's shop terms (RF-4B); the design system, type scale, colours, the accent set and key screens (RF-4C); HTTP routes, the full interaction contracts and the P3 workflow contracts (RF-5); the test strategy, backup and runbooks (RF-6); the Task baseline (RF-7); any ADR; any rule change in the P2, P4, P5 or P6 documents; the design tool and the CRM template pages (T-08); application code, migrations, executed SQL, installation, accounts, purchases or production operations; push, merge, rebase, pull request or history rewrite; deleting or editing any legacy document; answering T-17 b, question 18 or question 20.

**RF-4A is complete** only when its checkpoint commit exists, the planning agent has checked the executor's report against the repository, and the Owner has accepted the result. Gate P7-G1 is then approved by a separate Owner decision. RF-4B needs its own explicit authorization.

**Completion.** The executor made checkpoint `c8dc234c891228ec5a7f1d808f4231d2b8d89620` on 16 October 2026, and the planning agent reviewed its report against the repository. On 17 October 2026 the Owner accepted the checkpoint and then, after a further review, its SSD safety bundle (D-078). RF-4A is DONE. Gate P7-G1 is not approved.

## 10. Q38 amendment in detail

Authorized by D-079 on the accepted RF-4A checkpoint `c8dc234c891228ec5a7f1d808f4231d2b8d89620`: one bounded, documentation-only change of the working tree by the planning agent (D-021).

**Content.**
- It records D-077 (the Owner's payment-method decision Q38: CASH and BANK_TRANSFER only), D-078 and D-079.
- It removes STATIC_QRIS from the CURRENT requirements, specifications and UX contracts:
  - product and domain: PRODUCT_BLUEPRINT, REQUIREMENTS, SALES_CASH, COSTING, DOMAIN_MODEL and GLOSSARY;
  - architecture and technical rules: ARCHITECTURE, SCHEMA and TECHNICAL_RULES;
  - four of the six RF-4A documents: USER_TASKS, INFORMATION_ARCHITECTURE, INTERACTION_INVENTORY and ADAPTIVE_AND_ACCESSIBILITY.
- It updates the governance documents: DECISIONS, OPEN_ITEMS (with N-11), SOURCE_OF_TRUTH, LEGACY_INDEX, this plan, and CURRENT_HANDOFF last.
- It subtracts only. No new mechanism; no money, stock or concurrency rule changes. BR-PAY-04, UXT-05 and the GLOSSARY term STATIC_QRIS are retired in place, and their identifiers are not reused.

**Not part of it:** staging, commit, push, access to the SSD, the N-10 audit, gate P7-G1, RF-4B and every later step; any change under a `legacy/` folder; the "Golden Frontend Reference" (N-11).

**Then, each separately** (CURRENT_HANDOFF section 1 shows which step has been reached).
1. A targeted verification of the amendment in a separate session.
2. Its checkpoint, which needs the Owner's own authorization; a new SSD bundle follows its acceptance (D-065).
3. The Owner's comprehensive UX review, then his decision on gate P7-G1, which names that checkpoint commit and the hash of each of the six documents (D-070, D-076).

The checkpoint is `d4c0594f7b190677fa70b920ddc749c0bf8d980e`, accepted with its SSD bundle (D-080); its bookkeeping commit `cdb8361` is accepted (D-081). The Owner approved gate P7-G1 on 17 October 2026 with the hash of each of the six documents at that checkpoint (D-082).

## 11. RF-4B in detail

Authorized by D-083 as the second of the three RF-4 checkpoints (plan decision P-10), on baseline `cdb8361f8c8258af447abe7c1e5f04ae031e72f1`, the accepted D-080 bookkeeping commit (D-081), after the approval of gate P7-G1 (D-082).

**Content.**
- [GLOSSARY](../02-domain/GLOSSARY.md): every shop term confirmed or replaced, and the terms the interface needs added; its words-not-to-use list extended.
- [COPY_DECK](../07-ux-design/COPY_DECK.md), new: the writing rules and formats; the localization contract of ADR-005 item 7 (its section 1); the wording of every page title and navigation item, every control of the 61 pages, every dialog, the 50 states, the 32 error codes, the recovery panels, the disabled reasons, the validation messages, the documents and the screen-reader announcements; the record of every working label it changes; its coverage checks; the findings it carries forward.
- The records of D-081 to D-083 in DECISIONS, the decision trace of REQUIREMENTS, the P7 gates in OPEN_ITEMS, SOURCE_OF_TRUTH, LEGACY_INDEX, this plan, the root README, and CURRENT_HANDOFF last.
- No rule changes. The six P7-G1 documents are not changed (D-082).

**Method.** The implementation executor writes the change and commits it as one local commit under the Owner's identity, with no trailer, running as Claude Opus 5.5 at effort Max (D-066, D-083). The Owner's review of the checkpoint is the substantive review before his decision on gate P7-G2 (P-10).

**Not part of RF-4B:** the design system, key screens and any access to the design tool (RF-4C, P-14); HTTP routes, the full interaction contracts, the P3 workflow contracts and the document contracts (RF-5); the test strategy, runbooks and user guidance (RF-6); the Task baseline (RF-7); any rule change in the P1 to P6 documents; any change to the six P7-G1 documents; any change under a `legacy/` folder; application code, migrations, executed SQL, installation, accounts or purchases; push, merge, rebase, pull request or history rewrite; any SSD access; the N-10 audit; the "Golden Frontend Reference" (N-11); deciding T-17 b.

**Correction (D-084).** On 17 October 2026 the Owner accepted the checkpoint `c4bf8e9b7c23ddffe3ebf2fc3f63978fbcb9d21c` and authorized one narrow correction of COPY_DECK before gate P7-G2. A status check that gets no answer leaves the request in the state it was in, so a held request stays UXS-15 with "Cek status" only and is never offered "Kirim ulang" (ADR-007 section 5, UI_STATES section 1); `commands.resolution.no_answer` gets the Owner's text; finding F-05 of COPY_DECK records for RF-5 the rows of UXI-086 and UXP-006 that lead such a check to UXS-14; the retired-barcode message (BR-CAT-19, D-052) and the category text of UXI-208 are corrected. The six P7-G1 documents are not changed. The implementation executor commits it as one local commit on the checkpoint, with the record of D-084 in DECISIONS, the decision trace of REQUIREMENTS, SOURCE_OF_TRUTH, OPEN_ITEMS, this plan and CURRENT_HANDOFF last.

**RF-4B is complete** only when its checkpoint commit and the commit of its correction exist, the planning agent has checked the executor's reports against the repository, and the Owner has accepted the result. Gate P7-G2 is then approved by a separate Owner decision that names the commit that contains the correction and the hash of GLOSSARY and of COPY_DECK. RF-4C needs its own explicit authorization.

**Completion.** On 17 October 2026 the Owner accepted the correction commit `551b4eb69f17adff461eb894266031a6f9b0c6df` and approved gate P7-G2 with the hash of GLOSSARY and of COPY_DECK at that commit (D-086). RF-4B is DONE.

## 12. RF-4C in detail

Authorized by D-086 as the third of the three RF-4 checkpoints (plan decision P-10), on baseline `6e73514564382b26bb441efaa58351b1da5290ea`, the accepted D-085 commit, after the approval of gate P7-G2.

**Content.**
- The design system: typography, colour with the constrained accent set of the BusinessProfile (SCHEMA `accent_token`), spacing, components, responsive layouts and accessibility, adapted from the generic layer of the Golden Frontend Reference v1.0.0 at commit `14497d9` (D-085; TOOLCHAIN section 3), with every deviation from its defaults recorded in DECISIONS.
- Renderable, nonproduction previews of key Owner, Operations, Cashier, till, stock and cash-session screens, whose every text is a COPY_DECK key.
- The items that COPY_DECK section 21.2 and ADAPTIVE_AND_ACCESSIBILITY section 5 leave to RF-4C: the look of every text, the figures of the Beranda panel, the series of the trend chart, the 58 mm receipt layout of long names, the names of the accent colours, the verification of the provisional shortcuts and breakpoints, the full rendered contrast and the visual focus style.
- The canonical documents brought in line, and CURRENT_HANDOFF last.
- No rule changes. The six P7-G1 documents, GLOSSARY and COPY_DECK are not changed (D-082, D-086).

**Method.** The implementation executor writes the change and commits it as one local commit under the Owner's identity, with no trailer, running as Claude Opus 5.5 at effort Max (D-066, D-086). The Owner's review of the checkpoint is the substantive review before his decision on gate P7-G3 (P-10).

**Pause (D-086).** On 17 October 2026 the Owner paused RF-4C while it was in progress and asked for one local work-in-progress checkpoint commit. That commit holds D-086, the registers brought in line and the preview files written so far; CURRENT_HANDOFF section 1 lists what is done, what is not and how the work resumes. RF-4C stays IN PROGRESS until its checkpoint is complete; it resumes only on the Owner's instruction.

**Resumption (D-086).** On 18 October 2026 the Owner instructed the executor to resume and complete RF-4C from the work-in-progress checkpoint `3a7da19`, without repeating completed work. The resumed work finished the previews, wrote [DESIGN_SYSTEM](../07-ux-design/DESIGN_SYSTEM.md) and [KEY_SCREENS](../07-ux-design/KEY_SCREENS.md), recorded the deviations from the reference's defaults and the executor's design choices in the terms of D-086, and ends in one local checkpoint commit whose parent is `3a7da19`. RF-4C spans both commits.

**Not part of RF-4C:** any change to the six P7-G1 documents, GLOSSARY or COPY_DECK; HTTP routes, the full interaction contracts, the P3 workflow contracts and the document contracts (RF-5); the test strategy, runbooks and user guidance (RF-6); the Task baseline (RF-7); application code, migrations, executed SQL, installation, accounts or purchases; DesainPakeAI or any other design-generation tool; MULTIPLECORP material; push, merge, rebase, pull request or history rewrite; any SSD access; the approval of gate P7-G3, which is the Owner's separate decision.

**RF-4C is complete** only when its checkpoint commit exists, the planning agent has checked the executor's report against the repository, and the Owner has accepted the result. Gate P7-G3 is then approved by a separate Owner decision that names the checkpoint commit and the hash of each artifact (P-10). RF-5 needs its own explicit authorization.
