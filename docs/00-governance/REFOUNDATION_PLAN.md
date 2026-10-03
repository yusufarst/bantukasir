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
| RF-2 | Product and domain: merged product blueprint; requirement register with real requirement statements; glossary in Indonesian and English; entity lifecycle matrix; domain rules | P1, P2 | Answers to questions 8 to 15 and 19 (given: D-032 to D-040); authorization | NOT AUTHORIZED |
| RF-3 | Architecture, security and technical rules: merged documents; ADRs; logical database schema; authentication profile; hosting topology | P4, P5, P6 | Answers to questions 5, 6, 7 and 18; authorization | NOT AUTHORIZED |
| RF-4 | P7 rebuild: tasks per role; information architecture; page inventory; navigation contracts; terminology and copy deck; design system; key screens in the BantuKasir design project | P7 | Authorization; review at gates P7-G1, P7-G2, P7-G3 | NOT AUTHORIZED |
| RF-5 | Workflows completed with route contracts and interaction contracts | P3 | Authorization | NOT AUTHORIZED |
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
| P1 | Legacy content in force, amended by D-032 to D-040 (SOURCE_OF_TRUTH section 2) | `docs/01-product/legacy/` |
| P2 | Legacy content in force, amended by D-032 to D-040 (SOURCE_OF_TRUTH section 2) | `docs/02-domain/legacy/`, entity table in `docs/04-architecture/legacy/02-ARCHITECTURE.md` |
| P3 | Legacy workflows in force, amended by D-035, D-037 and D-040; route and interaction contracts do not exist | `docs/03-workflows/legacy/` |
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
