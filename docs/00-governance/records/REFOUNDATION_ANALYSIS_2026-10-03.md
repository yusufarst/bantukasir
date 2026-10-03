# BantuKasir — AICWDF-4.3 Re-Foundation Analysis Report

**Date:** 3 October 2026 (Asia/Jakarta)
**Role:** Planning Agent (analysis only)
**Repository inspected:** `C:\Projects\bantukasir`, branch `plan/bantukasir-pos-v1`, HEAD `604d9254f6a88958a1f1e36d54beabff54efdb96`
**Framework baseline:** AICWDF-4.3 (read in full this session)
**Status of this report:** revision 2, sections 1–41. It replaces the earlier partial report.
**Revision 2 (3 October 2026):** records the Owner's RF-0 decisions in section 41 and restates the RF-1 scope in section 40. Sections 1–39 are the analysis as delivered; where they describe a question as open and section 41 records an answer, section 41 governs. RF-1 has not started.

Nothing in the repository was created, edited, moved, deleted, staged or committed. One side effect inside `.git` is disclosed in section 4.

## How to read this report

Every material statement carries one of four labels so you can tell what kind of claim it is.

| Label | Meaning |
| --- | --- |
| **[FACT]** | Verified this session by reading the repository, running read-only Git commands, or rendering the P7 prototype in a browser. |
| **[AICWDF]** | A requirement of AICWDF-4.3, with the section number. |
| **[ANALYSIS]** | My judgment or recommendation. It can be wrong and you can overrule it. |
| **[OWNER]** | A decision only you can make. Collected in sections 38 and 39. |

## Summary of conclusions

The repository is exactly where the executor said it was, and its substance is better than its structure. The money, stock, concurrency and security thinking is careful and should be kept. What does not hold up against AICWDF-4.3 is the layer that turns that thinking into something an executor can build and a shop can use: there are no route contracts, no interaction contracts, no page inventory, no navigation contracts, no localization design, no toolchain registry, and the 27 Build Units are too large and too generic to be Tasks.

The P7 prototype is technically complete and visually calm, and it is still confusing, for reasons I could measure. Half of all on-screen text on desktop is smaller than 12 px, and two thirds on mobile. The copy speaks the language of the specification instead of the language of a shop: a cashier is shown "UNCERTAIN dipulihkan dari original", an owner is shown "Recovery epoch" and "Menunggu G4". Every sale needs two dialogs and a mandatory checkbox. My recommendation is to rebuild P7 from its information architecture and wording, keeping the visual tokens, instead of correcting the current candidate screen by screen.

Several older decisions deserve your re-confirmation before they are carried forward, because they were made by a planning agent on its own authority and they shape real shop behaviour: who may see purchase cost, who may refund and from which money, whether a person can hold two roles, and whether products can be imported. They are listed in section 38.

The next step I recommend is small: answer the four questions marked "needed first" in section 39, then authorize one bounded governance step that creates the AICWDF structure and retires the stale material. Nothing else should start before that.

---

# Part A — Verified current state

## 1. Scope and method

**[FACT]** I read these repository documents in full: `AGENTS.md`, `CLAUDE.md`, `README.md`, `DESIGN.md`, `docs/00-CURRENT-STATE.md`, `01-PRD`, `02-ARCHITECTURE`, `04-AUTH-RBAC-SECURITY`, `06-INVENTORY-SPEC`, `08-TESTING-ACCEPTANCE`, `09-DEPLOYMENT-OPS`, `10-DECISIONS`, `11-BUILD-PLAN`, `12-BARCODE-SCANNER`, `15-FINANCE-PROFITABILITY`, `17-POS-SALES`, all fourteen files in `docs/planning/`, the three evidence reviews, `prototypes/README.md`, the Plan 2.0 archive note, and two Build Unit cards in full (`BU-SALE-001`, `BU-UI-003`). I parsed `registry.json` and `core.json` programmatically and sampled the head of `CORE-MAP.md`. I did not read the 40 archived Plan 2.0 task cards or the historical UI01/UI02 prototype sources; they are declared historical by the repository itself.

**[FACT]** I read AICWDF-4.3 in full from the Project (sections 0–43).

**[FACT]** For P7 I did three things: read the repository's P7 planning and evidence documents; confirmed that the HTML file in the Project is the same candidate the repository records; and rendered that file in Chromium at 1440×900 and 390×844 for all three roles, walked the cashier sale flow end to end, clicked every first-level control, and measured text sizes and touch targets. External fonts and the two sample product photos could not load in my sandbox, so the screenshots I judged use a fallback typeface; font sizes are set in CSS and are unaffected.

## 2. Corrections to the earlier partial report

I cannot see the earlier partial report: chat history does not carry between sessions and it was not saved to the Project. I therefore cannot quote and correct individual sentences. Instead, the table below lists the points that could only have been provisional without repository access, with what is now verified. Wherever the earlier text disagrees with this table or with the rest of this report, the earlier text is superseded.

| Topic | Verified position |
| --- | --- |
| Checkpoint SHA | **[FACT]** Correct. HEAD is `604d9254f6a88958a1f1e36d54beabff54efdb96`, "docs: checkpoint P7 owner-review candidate (not approved)", 3 Oct 2026 04:47 +0700. |
| Remote lag | **[FACT]** Local is 7 commits ahead of `origin/plan/bantukasir-pos-v1` (which sits at `d41543d`) and 0 behind. No divergence. |
| Whether application code exists | **[FACT]** None. No `composer.json`, `package.json`, `app/`, migrations or tests. The repository is documents, two historical HTML prototypes and evidence. |
| P7 artifact identity | **[FACT]** The Project file "kandidat lengkap (6)" contains the repository's recorded candidate `sha256-bef01d649ea91b2b`. It also contains eight unrelated CRM template pages, which I ignored as instructed. |
| P7 status | **[FACT]** WAITING OWNER VISUAL REVIEW, not approved, `approvedRevision = null`. P8–P11 are REOPENED_DRAFT. |
| Framework lineage | **[FACT]** The repository never mentions AICWDF (0 occurrences). Its governance says it adopts "the MULTIPLECORP governance framework from the attached discussion" (`AGENTS.md`, `P0`, D-001). The P0–P11 shape is therefore inherited from an earlier form of the framework, not from 4.3. |
| Stack | **[FACT]** Laravel + Inertia + React/TypeScript + PostgreSQL + Nginx, shadcn/ui, Tailwind, no Redis. This is identical to the AICWDF-4.3 default stack (§4). |
| Counts | **[FACT]** Re-derived, not copied: 27 Build Unit cards, 73 Core rows, 39 acceptance suites, 18 decisions (D-001 to D-018), 1,452 relative links with 0 broken. |
| "Frozen" P0–P6 | **[FACT]** Frozen by the planning agent after agent review. The Owner authorized the package as a whole and one commit; ten of the eighteen decisions are classed AUTONOMOUS. This matters for section 8. |

## 3. Repository identity and Git baseline

**[FACT]**

| Item | Value |
| --- | --- |
| Root | `C:\Projects\bantukasir` |
| Branch | `plan/bantukasir-pos-v1` |
| HEAD | `604d9254f6a88958a1f1e36d54beabff54efdb96` |
| Upstream of branch | `origin/plan/bantukasir-pos-v1` at `d41543d` — ahead 7, behind 0 |
| `main` | `f7cecfe` locally and on origin ("add cashier shift close reporting to plan 3.1.1"). HEAD is 11 commits ahead of `main`. |
| Remotes | `origin` → `github.com/yusufarst/bantukasir`; `upstream` → `github.com/yusufarst/latansa-inventory` |
| Total commits | 19, all authored and committed as Yusuf Arif Setiawan (GitHub no-reply address) |
| AI attribution trailers in history | None found in any commit |
| Tags | None |
| Tracked files | 208 |

## 4. Working tree, index, untracked files and stash

**[FACT]** Nothing is staged. There is one untracked file, `plan-p0-p11-review.patch` (1,639,792 bytes). It is a UTF-16 `git format-patch` export of commit `5e100aa`, and its SHA-256 (`a13fe0f2…f03e`) matches the value recorded in `docs/00-CURRENT-STATE.md`. It duplicates a commit that already exists in history.

**[FACT]** From my Linux view, 36 tracked files show as modified. Every one of them differs only by line endings: the index holds LF, the working copy holds CRLF, and `git diff --ignore-cr-at-eol` is empty. There is no `.gitattributes`. **[ANALYSIS]** On your Windows Git with `core.autocrlf` enabled these should read as clean; I could not run Windows Git to confirm, so treat that as an inference.

**[FACT]** Two directories are ignored and present: `.local/` (design authoring helpers, the candidate page source, validators, a "before" snapshot of the repository) and `.agents/` (one skill folder, `impeccable`). `.local/desainpakeai/setup.json` holds project identifiers and a path, no credential fields.

**[FACT]** There is one stash, `stash@{0}` "pre-T01 preserve legacy BK03 and historical evidence" (2 Oct 2026 00:40 +0700), holding six files of the abandoned UI03 prototype.

**Disclosure. [FACT]** One of my early read-only commands (`git diff --numstat`) caused Git to refresh its stat cache, which rewrote `.git/index` at 05:33:50 UTC. This updated cached file metadata only. No tracked file, staged content, commit, branch, ref or stash changed; the index is the same size and holds the same blob IDs. The practical effect is that your next `git status` on Windows re-checks file contents once. After noticing this I used only commands that cannot write.

## 5. Planning history

**[FACT]** The 19 commits span 19 September to 3 October 2026 and contain at least five successive planning baselines and no application code:

| Dates | Baseline | Evidence |
| --- | --- | --- |
| 19–20 Sep | LATANSA Platform architecture | `3deb8e8`, `183cea6`, `1d993c1` |
| 20–21 Sep | Unified retail order Plan 3.1 / 3.1.1 | `44997a0`, `acb4063`, `f7cecfe` (this is still `main`) |
| 21 Sep | Focused POS v1 | `6e0c13d`, `bd8a807`, `e8011cf` |
| 22–25 Sep | UI01/UI02 HTML prototypes "approved" | `3e30fce`, `1f62e98`, `f6c9c58` |
| 2 Oct | Plan 2.0 with 40 task cards | `c4a145d` |
| 2 Oct | P0–P11 with 28, then 27 Build Units | `5e100aa` |
| 3 Oct | P7 moved to actual design; candidate checkpoint | `f7c02df`, `604d925` |

**[ANALYSIS]** The pattern is repeated re-planning, each time preserving the previous layer as "historical input". The result is a repository in which the live plan is surrounded by four superseded ones. This is the main reason a clean re-foundation is justified here, where AICWDF would normally advise against restructuring.

## 6. Document inventory and authority map

**[FACT]** The active planning corpus is about 516 KB (roughly 57,000 words): four root files, twelve `docs/NN-*.md` canonical specifications, fourteen `docs/planning/P*.md` phase documents, `CORE-MAP.md` with `core.json`, 27 Build Unit cards with `registry.json`, and three evidence folders. Historical material sits in `docs/archive/plan2/` (40 cards), `prototypes/ui01`, `prototypes/ui02`, `.impeccable/review/`, and `assets/brand/` (including a 6.7 MB LATANSA logo).

**[FACT]** The repository has two parallel document families for the same subjects. `docs/01-PRD.md` and `docs/planning/P1-PRODUCT.md` both define product scope; `02-ARCHITECTURE.md` and `P4-ARCHITECTURE.md` both define the stack and transactions; `04` and `P5` both define security; `08` and `P8`, `09` and `P9`, `11` and `P11` likewise. The phase documents mostly point at the numbered ones, but several restate rules.

**[AICWDF §1, §3]** There must not be duplicate authoritative documents for the same subject.

## 7. Project state classification

**[AICWDF §1]** A project must be classified as NEW, PARTIALLY_PLANNED, PARTIALLY_IMPLEMENTED, PRODUCTION or LEGACY.

**[FACT]** No production code, schema, tests or deployment exist. **[ANALYSIS]** BantuKasir is **PARTIALLY_PLANNED**.

## 8. Recorded phase status and what "frozen" means here

**[FACT]** As recorded in `P11-BUILD-UNITS.md` and `00-CURRENT-STATE.md`: P0 corrected; P1–P6 FROZEN_INPUT; P7 WAITING OWNER VISUAL REVIEW, NOT COMPLETE; P8–P11 REOPENED_DRAFT / PENDING_P7_APPROVAL; all 27 Build Units NOT STARTED; 0 of 39 suites at FINAL PASS.

**[AICWDF §1.1]** If earlier phases are already APPROVED, do not re-plan them merely because the framework is newer.

**[FACT]** The freeze of P1–P6 was performed by the planning agent after review by other agents in a single session on 2 October (`docs/evidence/rebaseline-p0-p11/REVIEW.md` states this plainly and says it is "not a claim of 12 separate independent sessions"). Decisions D-004 to D-009, D-012 to D-014 and D-016 are classed AUTONOMOUS.

**[ANALYSIS]** P1–P6 are therefore reviewed drafts, not Owner-approved phases in the sense of §1.1. The preservation rule does not bind here, and your project instructions say the same thing more directly. I treat the business content of P1–P6 as valuable input to be re-confirmed, and the technical content as retained unless I give a reason.

## 9. Authorization recorded in the repository

**[FACT]** `AGENTS.md`, `CLAUDE.md`, `README.md`, `P0-GOVERNANCE.md`, `P8-QUALITY.md`, `P11-BUILD-UNITS.md`, `10-DECISIONS.md` and `11-BUILD-PLAN.md` all still say that "this correction session authorizes one local docs/design-planning commit". That commit has been made (`604d925`).

**[ANALYSIS]** A consumed, session-specific authorization is now written into the permanent rules file. A new agent reading `AGENTS.md` cold could take it as a live permission to commit. This is a small but real continuity hazard and is the kind of thing AICWDF's separation of `AGENTS.md` (stable rules) from `CURRENT_HANDOFF.md` (current state) is designed to prevent.

**[FACT]** What is not authorized, consistently across all documents: application code, any Build Unit, push, merge, deploy, purchases, infrastructure or database operations.

## 10. Open gates recorded in the repository

**[FACT]** From `GAPS-CHANGES.md`: DESIGN (owner visual approval), P7-VALIDATION (zoom 200 %, screen reader, software keyboard, full contrast audit not verified), G1 (tax and receipt policy), G2 (units, catalogue volume, opening stock, barcodes, cost evidence, serial numbers), G3 (cash drawer practice, float, handover, refund funding), G4 (independent backup destination, key, cost, measured RPO/RTO), DEVICES (real scanner and printers), TARGET (the actual VPS), EXECUTOR (model availability), RELEASE (not authorized).

**[ANALYSIS]** G1 to G4 are facts about the real shop that only you can supply. They have been open since at least 21 September and several of my revalidation items in section 38 are the same questions seen from the design side. Planning cannot become coherent while they stay open.

## 11. Executor instructions currently in force

**[FACT]** `CLAUDE.md` (11.8 KB) is a second rules file addressed to the coding executor. It restates scope, money and stock rules, privacy rules, the design pipeline, verification and closeout. `AGENTS.md` (9.5 KB) covers much of the same ground.

**[AICWDF §34]** Tool-specific agent files should point back to repository rules instead of duplicating them.

**[FACT]** The executor label in `AGENTS.md`, `CLAUDE.md` and D-011 is "Claude Opus 5.5, effort xhigh". Your Project instructions say "Opus 5.5 at high reasoning". **[OWNER]** A minor inconsistency; see question 4.

---

# Part B — Audit of P0–P11 against AICWDF-4.3

Each section states what the framework asks for, what the repository has, my assessment, and a disposition using your vocabulary (RETAINED, REVALIDATED, ADAPTED, MERGED, SUPERSEDED, ARCHIVED, DELETED, REBUILT, CREATED).

## 12. P0 — Governance, foundation and agent continuity

**[AICWDF §11]** P0 defines project identity, authority map, document map, branch strategy, environments, agent and handoff rules, ADR policy, stack baseline, production-data policy, toolchain policy, Context7, Graphify and E2E policy, design-reference policy and token-efficiency rules. A new agent must be able to answer six questions: what is authoritative, what is the current state, what Task is active, what can I change, what must I not change, what should I read next.

**[FACT]** The repository is strong on authority hierarchy, change control, the three decision classes, production database protection, Git staging discipline and the difference between "specification frozen" and "proven at runtime". It has no toolchain registry, no Context7 or Graphify policy, no MCP policy, no branch or environment strategy beyond the single planning branch, no ADRs (a decision table is used instead), and no token-efficiency design.

**[ANALYSIS]** A new agent can answer five of the six questions, but only after reading about 40 KB of dense prose, and the answer to "what is the current state" is hard to extract. `00-CURRENT-STATE.md` contains run-together text such as "27 BU implementasi,0/27 COMPLETE,semua NOT STARTED" and "BU-FOUND-0018h(I4/V3/E1)". The safety rules are worth keeping almost word for word. The structure should be rebuilt.

**Disposition:** rules RETAINED and ADAPTED into `docs/00-governance/`; `AGENTS.md` REBUILT as a short stable contract; `TOOLCHAIN.md`, `COST_POLICY.md`, `SOURCE_OF_TRUTH.md` CREATED; `00-CURRENT-STATE.md` SUPERSEDED by a concise `CURRENT_HANDOFF.md`.

## 13. P1 — Product definition, scope and acceptance

**[AICWDF §12]** P1 defines problem statement, goals, non-goals, users, roles, primary journeys, in and out of scope, functional and non-functional requirements, acceptance criteria, release boundaries, success criteria, default language and localization, and recurring-cost constraints.

**[FACT]** `01-PRD.md` (6 KB) and `P1-PRODUCT.md` (2 KB) give a clear outcome statement, a role table, a list of key flows, an explicit "Later" list and the open policy gates. There is no problem statement, no user description beyond role names, no success criteria, no non-functional requirement list, and no language requirement. Requirements live in `core.json`: 73 entries, of which **39 are word-for-word copies of the 39 acceptance suites** (CORE-001 to CORE-039 each equal one suite and cite `08-TESTING-ACCEPTANCE.md` as their source), and the remaining 34 are one-line summaries.

**[ANALYSIS]** The claim "73 requirements mapped to Build Units and acceptance" is true as bookkeeping and weak as traceability, because more than half of the requirements are their own tests. A requirement register should state what the product must do in shop terms and then point to how it is tested. The scope fence itself (goods only, one stock pool, three roles, one full payment) is clear and was set by you (D-002, D-003).

**Disposition:** scope RETAINED subject to the revalidation items in section 38; `01-PRD` and `P1` MERGED into one `PRODUCT_BLUEPRINT.md`; requirement register REBUILT with real requirement statements; `core.json` and `CORE-MAP.md` SUPERSEDED.

## 14. P2 — Domain model and business rules

**[AICWDF §13]** P2 defines glossary, entities, ownership, relationships, states, transitions, invariants, validation, lifecycle, retention, archive, deletion, anonymization, restore and audit. Every important entity must state whether it can be created, edited, archived, restored, soft-deleted, hard-deleted or anonymized.

**[FACT]** The entity table in `02-ARCHITECTURE.md` and the rules in `06`, `15` and `17` are precise about invariants: immutable stock movements, synchronous non-negative balance, moving weighted average with explicit UNKNOWN, integer rupiah with half-up rounding once per line, six-decimal cost, UTC facts with Jakarta business date, one payment per sale, one open session per register and per cashier. There is no glossary, no per-entity lifecycle matrix, and no stated rule for deactivating versus deleting products, categories, users or registers beyond "disable, do not delete the actor".

**[ANALYSIS]** This is the best part of the repository. The missing lifecycle matrix is a real gap, because AICWDF forbids executors from inventing lifecycle rules and several entities (Category, Brand, Barcode alias, CashRegister) will need them on day one. A glossary is also the right place to fix the terminology problem found in P7: each domain term gets one Indonesian shop word and one English word, and the UI uses only those.

**Disposition:** invariants RETAINED; domain content MERGED from `02`, `P2`, `06`, `12`, `15`, `17` into `docs/02-domain/`; glossary and lifecycle matrix CREATED.

## 15. P3 — Critical workflows, routes and interactions

**[AICWDF §14]** P3 contains three things: workflow contracts, a route contract matrix (ID, UI action, method, route, handler, permission, expected result), and interaction contracts for important buttons, links, forms, menus, tabs, pagination and filters. A visible clickable element that is not implemented is forbidden.

**[FACT]** `P3-CRITICAL-WORKFLOWS.md` (26 KB) describes each workflow as entry, draft, review, confirm, result, failure and recovery, per role, with honest holds. There is no route matrix and there are no interaction contracts anywhere in the repository. The nearest thing is a 13-row "logical route" table in `DESIGN.md` that maps URL families to component names.

**[ANALYSIS]** Two of the three P3 deliverables do not exist. This is the largest structural gap in the planning set, and it cannot be closed until the P7 information architecture is settled, because routes and interactions follow from pages. The workflow descriptions are good and should be kept, but one rule in them should be revisited: every workflow, including an ordinary cash sale, is forced through "draft → review → explicit confirm". See section 20 and revalidation item R-07.

**Disposition:** workflows ADAPTED into `CRITICAL_FLOWS.md`; `ROUTE_CONTRACTS.md` and `INTERACTION_CONTRACTS.md` CREATED after P7.

## 16. P4 — Database and application architecture

**[AICWDF §4, §15]** Default stack Laravel, Inertia, React, TypeScript, shadcn/ui, Tailwind, PostgreSQL, Nginx; Valkey or Redis only when required; modular monolith; thin controllers; Actions and Services; ADRs for major choices; external vendors behind explicit boundaries without speculative abstraction.

**[FACT]** The chosen stack is identical to the framework default, was reached by an explicit comparison with a Next.js alternative, and excludes Redis, queues and SSR. The payment design keeps a method boundary for a future provider without stub tables or controllers, which is what §15.1.2 asks for. Module boundaries, lock ordering, exact-number handling and the Inertia privacy risks are documented. Versions are deliberately not pinned until the foundation unit.

**[FACT]** There is no logical database schema. Each Build Unit card names its "data/tables" in one sentence and leaves columns, keys and constraints to the executor ("Schema BU mem-pin storage precision…").

**[ANALYSIS]** The architecture decisions are sound and should be kept. The absence of a schema is a risk for a system whose whole value is in its constraints: if nine different Tasks each design their own tables, the shared lock order and the uniqueness rules will be discovered to conflict during implementation. A table-level schema (tables, keys, unique and check constraints, which tables are append-only) belongs in P4 before P11.

**Disposition:** decisions RETAINED; `02` (architecture part) and `P4` MERGED; D-004 and D-005 recorded as ADRs; logical schema CREATED.

## 17. P5 — Security, authentication and authorization

**[AICWDF §4A, §16]** One recorded authentication profile; Laravel-native authentication for Laravel; email and password, sessions, account management, password reset, throttling, framework-native hashing; Google sign-in on by default when suitable and never implying open registration; password profile AICWDF-COMPAT-8 (minimum 8 characters with upper, lower, number and symbol), which supersedes a stricter planner-chosen length; advanced features off unless needed; authentication and authorization kept separate; frontend visibility and backend authorization must agree.

**[FACT]** `04` and `P5` give a role-by-capability matrix, a threat-path table with a negative test for each path, default-deny server authorization, object scope from the session, CSRF, rate limits, security headers, private logo upload rules and a careful treatment of Inertia shared props and browser history. Public sign-up, 2FA and teams are disabled. There is no recorded authentication profile in the framework's sense: no password rule in the canonical documents (the prototype shows "minimal 12 karakter"), no decision on Google sign-in (0 mentions), no statement of how reset and invite links reach the user without an email service, and no statement on whether one person may hold more than one role.

**[ANALYSIS]** The authorization model is thorough. Three points need attention. First, the password length shown in the prototype conflicts with AICWDF-COMPAT-8; under the framework's precedence rule the project normalizes to COMPAT-8, and no Owner decision is needed unless an external requirement applies. Second, Google sign-in is a framework default that fits a closed counter system poorly; I recommend recording it as OFF, which the framework allows (§4A.3), and I ask you to confirm in question 6. Third, the "write-only purchase cost" rule for the Operations role drives a large share of the security engineering in `04`, `P4` and `P5`; I question whether it is worth it in R-03.

**Disposition:** model RETAINED; `04` and `P5` MERGED into `SECURITY_MODEL.md`; authentication profile CREATED; items R-02 and R-03 REVALIDATED.

## 18. P6 — Concurrency, idempotency, API and performance

**[AICWDF §17]** Request contracts, validation, errors, transaction boundaries, race conditions, idempotency, retries, timeouts, queue and cache strategy, rate limits, performance budgets, load expectations.

**[FACT]** `P6` and `02` define a single lock order, a race matrix with eleven named races and their required outcomes, an HTTP status mapping, idempotency by key, fingerprint and source intent, a recovery epoch after restore, bounded retries, pagination limits and four performance targets on a stated dataset (5,000 products, 100,000 movement lines, two cashiers).

**[ANALYSIS]** This meets the framework. Two observations. The benchmark dataset of 5,000 products sits oddly beside a product scope that forbids import and requires manual entry of every product (see R-05). And the command-recovery machinery (source intent, key, fingerprint, durable result, recovery epoch, UNCERTAIN state) is the right protection against double-charging, but it has leaked into the user interface as vocabulary; the mechanism should stay and the words should not.

**Disposition:** RETAINED; ADAPTED into `TECHNICAL_RULES.md`.

## 19. P7 — what the repository planned

**[AICWDF §18]** P7 produces information architecture, page inventory, role-based navigation, interaction inventory, route-to-UI mapping, navigation contracts, back-action contracts, design system, shadcn rules, mobile-first and responsive rules, Indonesian and English localization architecture, plain Indonesian copy rules, loading, empty and error states, authorization-visible rules, stale and conflict UI, search, filter and pagination behaviour, and design references. It sets explicit quality gates for visual breathing room, content density, readability, action hierarchy and contextual back actions.

**[FACT]** The repository's P7 consists of a workflow document (`P7-UX-DESIGN.md`), a coverage matrix of 18 capability groups (`P7-COVERAGE.md`), a draft token contract (`DESIGN.md`) and two evidence folders. D-017, an Owner decision, makes P7 an actual design in DesainPakeAI whose exact revision you must approve before P8–P11 can be finalized.

**[FACT]** Measured against §18, the repository has a design-system draft, responsive targets, a states list and a design-reference record. It does not have a page inventory, an interaction inventory, navigation contracts, back-action contracts, a localization architecture, copy rules, or the breathing-room and density gates. The coverage matrix is organized by backend capability (G01 authentication … G18 incident recovery), not by page or by user task.

**[ANALYSIS]** The P7 planning asked "is every capability represented somewhere on a screen?" and answered it honestly. It never asked "what is each person trying to do, in what order, and in what words?" That is why a candidate can pass its own coverage check and still confuse you.

## 20. P7 — what the prototype actually is

Everything in this section is **[FACT]** from rendering the candidate, unless marked otherwise.

**Identity.** The Project file is 740,546 bytes and contains nine pages: eight CRM template pages (dashboard, transaksi, leads, target, laporan, tim, pengaturan, masuk) and `p7-v1`. I compared 200 random 120-character samples of the repository's ignored candidate page source and 200 of its script against the Project file; all 400 were present. The repository's own context record for that source gives revision `sha256-bef01d649ea91b2b`.

**Structure.** The candidate is one page driven by a script. It has 14 destinations: eleven for the Owner (Dashboard, Katalog barang, Stok & riwayat, Barang masuk, Barang keluar, Kasir POS, Penjualan, Keuangan, Sesi kas, Audit & sistem, Pengaturan), five of those for Operations, three for the Cashier (Kasir POS, Transaksi saya, Sesi saya), plus account, opening stock and sign-in. Everything else, including product detail, product create and edit, receipt, refund, cost correction and session close, is a modal dialog; I found 19 distinct dialog types. There are no child pages, so there are no back actions to judge.

**Dead interactions.** I clicked all 389 first-level controls on desktop across the 19 role-and-screen combinations. Twenty produced no change, and all twenty were the navigation item for the screen already open. At this level the prototype has no dead buttons. I did not test controls inside dialogs or the mobile layout this way.

**Text size.** Across all 19 screens, 50 % of visible characters on desktop are smaller than 12 px and 84 % are 12 px or smaller; the smallest is 8 px. On mobile it is worse, not better: 66 % are smaller than 12 px. Only 14–16 % of text is at the stated 14 px body size.

**Touch targets.** On the 390 px layout, 83 of 504 visible controls (16 %) are smaller than 44 px in at least one dimension.

**Language.** In about 53,000 characters of visible copy I counted these specification terms: "original" 62 times, "non-sale" 48, "opening" 41, "token" 41, "workspace" 39, "snapshot" 22, "stock pool" 21, "revision" 21, "UNKNOWN" 14, "responsible" 12, "immutable" 11, "movement" 8, "applied" 8, "as-closed" 6, "replay" 6, "ledger" 6. Examples shown to staff:

| Role | Screen | Copy shown |
| --- | --- | --- |
| Cashier | Payment review | "Confirm membekukan intent contoh. UNCERTAIN dipulihkan dari original; tidak ada replacement atau tagih ulang." |
| Cashier | Payment review | "Metode tunggal · Total penuh / applied · Sesi / responsible" |
| Cashier | Open session | "Satu OPEN per register DAN per cashier. Tidak ada shared drawer / responsibility overwrite." |
| Cashier | Session list | "Snapshot as-closed yang immutable" |
| Operations | Receiving | "Input biaya baru bersifat write-only. Nilai tidak diulang pada review, hasil, riwayat, error atau recovery." |
| Operations | Stock | "Satu stock pool. Saldo berasal dari pergerakan immutable, bukan angka yang dapat diedit." |
| Owner | Audit | Tiles titled "Recovery epoch" and "Backup independen — Menunggu G4"; log rows titled "SALE_COMPLETED", "MANUAL_ISSUE" |
| Owner | Finance | "Evidence baru membuat revision. Replay bounded dan all-or-nothing; original tetap tersimpan." |

Some of this is reviewer annotation ("Prototype bukan bukti transaksi"), but annotation and product copy are mixed in the same places with the same styling, so a reader cannot tell which words would ship.

**Cashier speed.** A cash sale takes: choose items, press Bayar, enter the amount received, tick a mandatory checkbox ("Barang diserahkan langsung dan satu metode pembayaran penuh digunakan"), press Tinjau pembayaran, read a second dialog, press Konfirmasi, then a third dialog shows the receipt. Opening a session has the same two-dialog shape plus its own mandatory checkbox.

**Product discovery.** The point-of-sale screen is a grid of photo cards, six per desktop screen and about three per phone screen. Four of the six sample products show a "Foto belum tersedia" placeholder. Product photo upload is outside V1 scope (`01-PRD.md`, "Later"), so in the real product every card would be a placeholder.

**Naming.** The same destination has different names depending on where you look: the Owner's home is "Dashboard" in the sidebar and "Workspace" in the mobile bottom bar; the Cashier's history is "Transaksi saya" in the sidebar, "Riwayat penjualan" as the page title, "Riwayat saya" on a button and "Transaksi" in the bottom bar.

**Navigation.** The Owner has eleven top-level items. Stock actions appear three times: as sidebar items (Barang masuk, Barang keluar), as buttons on the stock page (Barang keluar, Barang masuk, Opening awal), and as "Tinjau" shortcuts on the dashboard. Cost correction lives under Keuangan, reversal under both Stok and Keuangan, refunds inside the receipt dialog under Penjualan, backup status under Audit.

**Forms.** Receiving shows a search box labelled "Cari nama, SKU, atau barcode dulu", then a link "Cari / buat melalui Katalog", then a separate dropdown that lists every product. It receives one product at a time.

**States.** Loading, empty, error, denied and stale are demonstrated by a reviewer switch that replaces the whole content area or adds a banner. They are not designed per screen.

**Visual quality. [ANALYSIS]** The palette, spacing between panels, card treatment and the sidebar are calm and consistent, and worth keeping. The breathing room that exists between sections is undone inside them by the small type.

## 21. P7 — reconciliation and verdict

**[FACT]** My findings and the repository's own evidence agree on what was checked and differ on what was asked. The repository's reviews verified layout overflow at five widths, focus trapping, form behaviour in the sandbox, fixture consistency, ten colour-contrast pairs, and the four Owner notes of D-018. They record, honestly, that zoom at 200 %, screen readers, the on-screen keyboard and long content were not verified. `DESIGN.md` itself records "Metadata 9–12px" and adds that readability at small sizes "masih hold review/validasi". No repository review examined wording, the number of steps in a sale, navigation structure or naming consistency.

**[AICWDF §4B.1, §18.2, §18.3, §18.4, §27]** User-facing copy must be natural Indonesian that a non-technical user understands; readability, breathing room and content density are pass-or-fail gates; one primary action per context without unnecessary duplicates; child flows need contextual back actions; English must be available.

**[ANALYSIS]** Assessment of the candidate against the areas you listed:

| Area | Finding |
| --- | --- |
| Information architecture, role navigation | Organized by system capability, not by task. Owner menu too long; stock and correction actions scattered and duplicated. |
| Page inventory, route mapping, navigation and back contracts | Do not exist. Detail, create and edit are all dialogs. |
| User mental model, plain language, Indonesian terminology | Fails. Specification vocabulary throughout; reviewer notes mixed with product copy. |
| Action hierarchy | Mostly one primary action per screen. Duplicated stock actions on the stock page. |
| Cashier speed | Fails. Two confirmation dialogs and a mandatory checkbox on every sale. |
| Owner and Operations comprehension | Owner screens expose internal gate names and audit codes. Operations screens explain system rules instead of the task. |
| Product discovery | Photo grid with no photos available in scope; search exists but is not the primary pattern. |
| Stock, receiving, manual issue | Functional; receiving has two competing product pickers and one product per entry. |
| Cash session, cash and transfer checkout | Functional and correct to the rules. Over-explained. |
| Receipts and history | Functional. Receipt dialog titled "Snapshot receipt original". |
| Corrections, refunds, returns | Present but hard to find; reached from four different places. |
| Reporting, settings | Present. Finance mixes reports with correction actions. |
| Loading, empty, success, error, denied, stale, retry | Demonstrated globally by a switch, not designed per screen. |
| Keyboard, touch, software keyboard, long content | Partly verified by the repository; 16 % of mobile controls under 44 px; software keyboard and long content unverified. |
| Responsive adaptation | No horizontal overflow at the widths tested. Type shrinks further on mobile. |
| Accessibility | Focus handling verified by the repository; readability fails on size; zoom and screen reader unverified. |
| Visual hierarchy, breathing room, density | Good between sections, poor within them because of type size. |
| Zero dead interactions | Passes at first level on desktop. |
| Localization | Indonesian only, copy written into the script. |

**Verdict. [ANALYSIS]** I recommend rebuilding P7 from a cleaner foundation and keeping the visual tokens. Incremental correction would mean rewriting nearly all copy, changing type sizes across every component, restructuring navigation and replacing most dialogs with pages, which is a rebuild done the slow way. The order should be: tasks and information architecture per role, page inventory, navigation contracts, a terminology and copy deck in plain Indonesian with English counterparts, then the design system with a 14 px minimum for body text and 12 px for secondary text, then design of key screens. **[OWNER]** The choice of path is yours (question 16), and so is the form of your approval (question 17).

## 22. P8 — Testing, quality and Definition of Done

**[AICWDF §19, §25–§28]** Testing is inside each Task. Layers include lint, typecheck, unit, feature, integration, authorization, route integrity, interaction, browser E2E, localization, responsive, accessibility, build, security, performance and migration. Task statuses are TODO, READY, IN_PROGRESS, BLOCKED, VERIFYING, DONE, SUPERSEDED. Zero dead interactions and a UI consistency gate apply to every UI Task. Regression runs in three layers.

**[FACT]** `08` defines 39 acceptance suites with measurable text, a surface matrix from initial state to final proof, an eight-dimension Definition of Done, a command contract (`composer test:postgres`, `test:race`, `npm run test:e2e` and so on), and a firm rule that real PostgreSQL is required for constraints and races. It uses its own status vocabularies (NOT STARTED, BUKTI AWAL, PENDING, FAIL, FINAL PASS for evidence; seven different statuses for Build Units). It has no localization tests, no route-integrity or dead-interaction gate, and no breathing-room gate. Final proof of 33 of the 39 suites is assigned to a single unit, BU-VERIFY-002, estimated at nine hours.

**[ANALYSIS]** The acceptance content is valuable and should be kept. The status vocabularies should be replaced by the framework's. Concentrating 33 final proofs in one late unit means most integration defects surface at the very end; the framework's three-layer regression spreads that load.

**Disposition:** suites RETAINED; `08` and `P8` MERGED and ADAPTED; framework gates CREATED.

## 23. P9 — Infrastructure, observability, backup and recovery

**[AICWDF §4C, §20]** Default topology: domain and Cloudflare, Hostinger VPS, Nginx or Coolify-managed routing, Laravel, PostgreSQL. Define containers, environments including staging, secrets, logs, metrics, uptime, error tracking, backup, offsite backup, restore testing, a recurring-cost inventory and a paid-service exception registry. Target additional recurring cost is zero.

**[FACT]** `09` and `P9` specify Docker Compose with PHP-FPM, Nginx and private PostgreSQL, separate runtime, migration and backup credentials, forward-only migrations with populated rehearsal, an encrypted independent backup with a restore runbook and a recovery epoch, retention proposals, and an honest "Belum Terverifikasi" status. They assume "the existing VPS and domain" without naming them. They do not mention Coolify, Cloudflare or Hostinger, do not define a staging environment, and leave TLS issuance and renewal as a hand-built operator task. `.env.example` is a leftover from an earlier stack: it contains `BETTER_AUTH_SECRET`, `NODE_ENV` and Web Push keys, and `.gitignore` still lists `.next/`.

**[ANALYSIS]** The backup and recovery thinking exceeds what the framework asks and should be kept. The hosting design should be re-decided once you tell me what the server is (question 7): if Coolify is used, as the framework assumes, TLS renewal and deployment stop being custom work. An independent backup destination may carry a small real cost, which under §4C.2 needs your explicit approval (question 18).

**Disposition:** backup and recovery RETAINED; `09` and `P9` MERGED and ADAPTED; topology REVALIDATED; `.env.example` and `.gitignore` REBUILT at the foundation Task.

## 24. P10 — Release, migration, cutover, rollback and UAT

**[AICWDF §21, §32, §39]** Every release declares database change and recurring-cost change. Release needs required Tasks done, CI, staging, critical E2E, localization check, regression, UAT, approval, rollback target, backup readiness, health and smoke checks. The branch model is feature → develop → staging → main, with `main` protected. A release integrity report is produced.

**[FACT]** `P10` defines readiness, cutover with an onboarding freeze, a pilot per role with real devices, and the rule that software rollback is only safe with a compatible schema. It has no CI, no staging step, no branch model, no release declarations and no integrity report. `main` currently holds an obsolete plan.

**Disposition:** cutover and pilot content RETAINED; `P10` ADAPTED into release and rollback runbooks; branch and environment model CREATED in P0.

## 25. P11 — Build Units versus the framework's Task model

**[AICWDF §22, §23, §35]** P11 publishes a Task baseline with a total count, a visible checkbox list in `TASK_PLAN.md`, stable Task IDs, dependencies, a READY gate, one Task at a time, a sizing rule (one coherent outcome that can be implemented and verified end to end), a risk class, and a Task contract with about thirty fields including in and out of scope, expected file areas, business rules, database impact, authentication impact, cost impact, tool requirements, UI requirements, route impact, interaction contracts, navigation contract, numbered acceptance criteria, a test checklist, completion evidence, safe next Task and "do not do".

**[FACT]** The repository has 27 Build Unit cards of about 5 KB each, a registry, a dependency graph without cycles, and hour estimates. About 31 % of the text in the cards is identical boilerplate repeated in at least 20 of the 27 cards. The task-specific part of a card is a one-sentence data line, three or four dense bullets under "DO", and two or three under "VERIFY". Cards have no numbered acceptance criteria, no expected file areas, no route or interaction contracts, no risk class and no out-of-scope list. Sizes are uneven: BU-UI-003 carries 22 requirements and 15 suites in an estimated 13 hours; BU-SALE-001 carries 18 requirements and 11 suites in 13 hours. The graph is layered horizontally: all backend units come first, the four UI units come after them, and four verification units come last.

**[ANALYSIS]** These are work packages, not Tasks. An executor given BU-SALE-001 would have to decide the schema, the routes, the request shapes and the test list itself, which is exactly the ambiguity the framework exists to remove. The horizontal layering also means that no user-visible flow exists until late, so the first real test of the design happens after most of the backend is written. I recommend rebuilding P11 as vertical slices, each delivering one flow from migration to browser test (for example: receive stock; sell for cash; close a session), with the Build Unit IDs kept in a mapping table for traceability. I will not state a Task count now; the framework forbids inventing one before planning is complete (§37).

**Disposition:** Build Unit cards and registry ARCHIVED after their content is absorbed; `TASK_PLAN.md`, `EXECUTION_CONTEXT.md` and `TASK-XXX.md` CREATED in P11.

---

# Part C — Cross-cutting findings

## 26. Decision register audit (D-001 to D-018)

**[FACT]** `10-DECISIONS.md` holds 18 decisions. Eight originate from you (D-001, D-002, D-003, D-010 in part, D-011, D-015, D-017, D-018). Ten were made by the planning agent under the AUTONOMOUS class.

| Decision | Subject | Source | Disposition |
| --- | --- | --- | --- |
| D-001 | Adopt the "MULTIPLECORP discussion" governance frame | Owner | SUPERSEDED by AICWDF-4.3. The ban on touching MULTIPLECORP material is RETAINED. |
| D-002 | V1 minimum scope | Owner | REVALIDATED — see R-01, R-05, R-06, R-08 |
| D-003 | One full payment, cash or bank transfer | Owner | REVALIDATED lightly — see R-06 |
| D-004, D-005 | Stack and minimum infrastructure | Planner | RETAINED; identical to the framework default; convert to ADRs |
| D-006 | Ledger, atomic sale, exact numbers, idempotency | Planner | RETAINED |
| D-007 | Per-role data allowlists; Operations cost is write-only | Planner | Allowlists RETAINED; write-only cost REVALIDATED — R-03 |
| D-008 | Correction, refund and return semantics; cost replay | Planner | REVALIDATED — R-04 and R-09 |
| D-009 | Cash session rules | Planner | REVALIDATED — R-04 and R-14 |
| D-010 | Design pipeline and runtime client identity | Owner and planner | Pipeline ADAPTED in the P7 rebuild; runtime identity REVALIDATED — R-10 |
| D-011 | Executor labels | Owner | RETAINED; reconcile "high" versus "xhigh" |
| D-012 | 27 Build Units and hour estimates | Planner | SUPERSEDED by the P11 rebuild |
| D-013 | Security defaults including session timeouts | Planner | RETAINED; timeouts REVALIDATED against counter use — R-14 |
| D-014 | Review before freeze; P7 before P8–P11 | Planner, corrected by D-017 | ADAPTED |
| D-015 | One local planning commit | Owner | Consumed. ARCHIVED as history. |
| D-016 | Preservation of Plan 2.0 inputs | Planner | SUPERSEDED once legacy trees are retired |
| D-017 | P7 is an actual design with exact-revision approval | Owner | REVALIDATED — R-11 |
| D-018 | Four visual notes: dates, photos, Barang keluar menu, chart | Owner | Dates and Barang keluar RETAINED; photos REVALIDATED — R-08 |

**[ANALYSIS]** The decision classes worked as designed for technical choices. They worked less well where a "technical" choice carried shop meaning: D-007, D-008 and D-009 decide who sees cost, who may refund and how a drawer is shared. Those are the ones I bring back to you.

## 27. Traceability, estimates and schedule claims

**[FACT]** The repository states 293 engineering hours (40 planning, 253 implementation), a 128-hour critical path, a 64-hour reserve, and "36.6 days × 8 h" for one executor. It labels these as judgment, not measurement.

**[AICWDF §22, §35]** Progress is tracked as Task counts and statuses in `TASK_PLAN.md`. The framework does not use hour estimates.

**[ANALYSIS]** Hours are a poor unit for AI execution and these figures look optimistic for the amount of race, rollback and privacy testing each unit demands. I recommend dropping hour estimates from the canonical documents and reporting progress as Tasks done over Tasks total, with risk class per Task. If you want a calendar forecast later, it should be derived from observed Task throughput after the first few Tasks.

## 28. Data integrity

**[FACT]** Checked against the data-integrity rules in your Project instructions: no floating point for money (integer rupiah, decimal strings across the wire, Brick Math); exact quantities (PostgreSQL `numeric`, precision 0–3); auditable history (append-only movements, sales, payments, snapshots, audit events, with runtime database privileges that deny update and delete); correction and reversal semantics (linked compensating records, one full reversal, separate money and quantity bounds); a single source of truth (ledger is truth, balance is a synchronous projection, reconciliation runs daily); transactional safety (one transaction for the whole sale); explicit concurrency (lock order and race matrix); explicit idempotency (key, fingerprint, source intent).

**[ANALYSIS]** All eight rules are satisfied at specification level. Nothing is proven, because nothing is built. The two risks are the missing logical schema (section 16) and the size of the cost-replay design (R-09), which is the single most intricate piece of the system and the most likely to slip.

## 29. Security posture

**[FACT]** Default-deny server authorization, object scope, mass-assignment allowlists, parameterized SQL, output escaping, CSRF and origin checks, upload validation with re-encoding, path and remote-fetch denial (SSRF is named), rate limiting, session hardening, security headers and secret hygiene are all specified with a negative test each. Command injection is not named anywhere in the active documents. No secrets are tracked: `.env.example` holds key names only, and `.gitignore` excludes `.env*`, keys and `.local/`.

**[ANALYSIS]** Add command injection to the threat table when P5 is merged; the backup and scheduled-command paths are where it could matter. Otherwise the model is complete for this product. I could not determine whether the GitHub repository is private; `.gitignore` contains the comment "never belongs in the public repository". If it is public, the threat model and operations runbooks are public too. **[OWNER]** Question 4. *Revision 2: the Owner has confirmed the repository is public; see section 41, item OF-01 and open item N-01.*

## 30. Toolchain and MCP policy

**[AICWDF §5, §6, §6A, §7]** Required capabilities: current documentation (Context7 preferred), codebase impact analysis (Graphify preferred, may be deferred on an empty repository), browser E2E (Playwright preferred), shadcn/ui, a visual reference (DesainPakaiAI preferred), static and automated verification. A `TOOLCHAIN.md` registry and an on-demand MCP policy are required.

**[FACT]** The repository names Playwright, Pest or PHPUnit, PHPStan/Larastan, Pint, Vitest and shadcn/ui as intended tools. It uses the DesainPakeAI command-line tool (`dpai` 0.2.2) and explicitly forbids its MCP. Context7 and Graphify are not mentioned. There is no toolchain registry and no MCP policy. An old `impeccable` skill remains in the ignored `.agents/` folder, and `AGENTS.md` says that tool is no longer part of the workflow.

**[ANALYSIS]** Create `TOOLCHAIN.md` with: Context7 required for version-sensitive work; Graphify deferred until there is code, then required for significant changes; Playwright required for UI Tasks through the project package, not MCP; `dpai` CLI for design; heavy MCP servers off by default. None of this needs your decision.

## 31. Cost policy

**[AICWDF §4C]** Additional recurring production cost target is zero beyond client-funded domain and VPS; a paid service is a stop condition needing your approval; record free-tier limits and exit strategy.

**[FACT]** The repository targets "about Rp0" additional software cost and insists that this does not excuse skipping an independent backup. It records that backup storage and DesainPakeAI entitlements may carry real cost and that neither has been verified. There is no cost inventory or exception registry.

**[ANALYSIS]** The policy matches the framework. Create `COST_POLICY.md` with an inventory (currently: domain, VPS, backup destination to be decided) and an empty exception registry. The design tool is a development-time cost, not a production one, and should be listed separately so it is not mistaken for a runtime dependency.

## 32. Localization

**[AICWDF §4B, §18.11, §41]** Default locale id-ID; English mandatory as a second language; a language switcher; no hardcoded user-facing copy; missing translation keys zero. Weakening this needs explicit approval.

**[FACT]** The repository plans an Indonesian-only interface. English, locale and i18n are not addressed in any product or design document. The prototype's copy is written directly into its script. Date and currency formatting are specified for Indonesian (D-018).

**[ANALYSIS]** This is the clearest gap between the repository and the framework. The architectural part (all copy behind translation keys, a glossary with both languages) is cheap if done from the first Task and expensive to retrofit, so I recommend it regardless. Whether English strings are actually shipped and tested in V1 is your decision. **[OWNER]** Question 5.

## 33. Executor context efficiency

**[AICWDF §8, §9.2, §34A]** The executor reads `AGENTS.md`, `EXECUTION_CONTEXT.md`, `CURRENT_HANDOFF.md`, `TASK_PLAN.md`, the active Task, and only the exact sections it references. It should not reread the planning corpus for each Task.

**[FACT]** The repository's mandatory read path for every Build Unit is `AGENTS` → `00` → `11` → card → `01` → `02` → `10`, plus `CLAUDE.md`, plus the card's own list. For BU-SALE-001 that is about 147 KB (roughly 16,000 words) of dense prose before any source file is opened, including the full 40 KB acceptance document.

**[ANALYSIS]** This is far more than a well-formed Task should need, and most of it is repeated for every unit. The fix is structural: a short `EXECUTION_CONTEXT.md` carrying the stable rules once, and Task contracts that quote the specific business rules and acceptance criteria they need by ID.

## 34. Stale and contradictory material

**[FACT]** Items found in the working tree that no longer describe the project:

| Item | Why it is stale |
| --- | --- |
| "This correction session authorizes one local commit" in eight files | The commit was made; the text reads as a live permission. |
| "Adopts the MULTIPLECORP governance framework" in `AGENTS.md`, `P0`, D-001 | AICWDF-4.3 is now the baseline; the repository never names it. |
| `.env.example` | Variables from an earlier Next.js, Better Auth and Web Push stack. |
| `.gitignore` | Lists `.next/`, `out/`, `*.tsbuildinfo`; nothing for Laravel. |
| `assets/brand/README.md` | Refers to BU-DESIGN-001, which was removed. |
| `assets/brand/source/latansa-official-colored-mark.png` | A 6.7 MB logo of another brand, kept "without approval to use it". |
| `docs/archive/plan2/` (41 files) | Plan 2.0 task cards, superseded twice. |
| `prototypes/ui01`, `prototypes/ui02`, `.impeccable/review/` | Prototypes and reviews from a design direction and a tool both declared retired. |
| `docs/evidence/plan2-validation.md`, `rebaseline-p0-p11/` | Evidence for superseded baselines. |
| `main` branch | Holds Plan 3.1.1, three baselines old. |
| `upstream` remote | Points at `latansa-inventory`. |
| `stash@{0}` and `.local/ui03` | Rejected UI03 prototype. *Revision 2: the two are not duplicates. Three of the six stashed files (`script.js`, `shell.js`, `styles.css`) differ from the copies in `.local/ui03`, so the stash holds unique content.* |
| `plan-p0-p11-review.patch` | Duplicate of commit `5e100aa`. |
| Eight CRM template pages in the design project | Unrelated to BantuKasir; they travel inside every export. |
| Benchmark dataset of 5,000 products | Contradicts manual-only product entry (R-05). |
| Product photos in the candidate | Contradicts "product image upload is Later" (R-08). |

---

# Part D — Re-foundation plan, registers and Owner decisions

## 35. Disposition of existing material

**[ANALYSIS]** Proposed treatment of every existing item. "Deleted" means removed from the working tree in a normal commit; Git history keeps every version, and a short index of the commit SHAs where each legacy tree last existed would be kept in the repository.

| Existing item | Disposition | Destination or reason |
| --- | --- | --- |
| `AGENTS.md` | REBUILT | Short stable contract (AICWDF §34) plus BantuKasir safety rules; no session-specific text |
| `CLAUDE.md` | SUPERSEDED | Becomes a few lines pointing to `AGENTS.md` and `EXECUTION_CONTEXT.md` |
| `README.md` | REBUILT | What the project is, where to start, document map |
| `docs/00-CURRENT-STATE.md` | SUPERSEDED | `docs/handoff/CURRENT_HANDOFF.md` |
| `docs/planning/P0-GOVERNANCE.md`, `GAPS-CHANGES.md` | ADAPTED | `docs/00-governance/` (rules, source of truth, open items) |
| `docs/10-DECISIONS.md` | ADAPTED | Decision log keeps D-001 to D-018 with their new status; architectural ones become ADRs |
| `docs/01-PRD.md`, `P1-PRODUCT.md` | MERGED, REVALIDATED | `docs/01-product/PRODUCT_BLUEPRINT.md` |
| `core.json`, `CORE-MAP.md` | SUPERSEDED | Rebuilt requirement register inside the product blueprint |
| `02` (domain part), `P2`, `06`, `12`, `15`, `17` | MERGED, ADAPTED | `docs/02-domain/` with glossary and lifecycle matrix added |
| `P3-CRITICAL-WORKFLOWS.md` | ADAPTED | `docs/03-workflows/CRITICAL_FLOWS.md` |
| Route and interaction contracts | CREATED | `docs/03-workflows/` after P7 |
| `02` (architecture part), `P4` | MERGED, RETAINED | `docs/04-architecture/` plus logical schema and ADRs |
| `04`, `P5` | MERGED, RETAINED | `docs/05-security/SECURITY_MODEL.md` plus authentication profile |
| `P6-CONCURRENCY-API.md` | RETAINED, ADAPTED | `docs/06-api-performance/TECHNICAL_RULES.md` |
| `DESIGN.md`, `P7-UX-DESIGN.md`, `P7-COVERAGE.md` | REBUILT | `docs/07-ux-design/`; colour and spacing tokens carried over |
| `docs/evidence/p7-correction`, `p7-owner-r1` | ARCHIVED | Kept until the rebuilt P7 replaces the candidate, then deleted with a SHA pointer |
| `08`, `P8` | MERGED, ADAPTED | `docs/08-testing/` |
| `09`, `P9` | MERGED, ADAPTED | `docs/09-operations/` |
| `P10-RELEASE-UAT.md` | ADAPTED | `docs/10-release/` |
| `11-BUILD-PLAN.md`, `P11`, 27 cards, `registry.json` | SUPERSEDED, then ARCHIVED | Replaced by `docs/11-tasks/` in P11; a BU-to-Task mapping table is kept |
| `docs/archive/plan2/`, `docs/evidence/plan2-validation.md`, `rebaseline-p0-p11/` | DELETED | Superseded twice; in Git at `c4a145d` and `5e100aa` |
| `prototypes/ui01`, `ui02`, `.impeccable/` | DELETED | Retired direction and retired tool; in Git at `f6c9c58` |
| `assets/brand/` | REVALIDATED | Depends on question 14; the LATANSA logo should not stay in this repository unless LATANSA is the client |
| `.env.example`, `.gitignore` | REBUILT | At the foundation Task, for Laravel |
| `plan-p0-p11-review.patch`, `stash@{0}` | RETAINED during RF-1 | Owner decision D-022 (section 41) |
| `upstream` remote | Marked for removal | By the Windows executor at the RF-1 checkpoint (D-022) |
| `main` | Untouched | No push, no merge in RF-1; a later pull request may replace it after explicit approval (D-022) |

## 36. Target structure and sequence

**[AICWDF §33]** Recommended layout, adopted with the project-specific additions marked.

```text
AGENTS.md
README.md
CLAUDE.md                      (pointer only)
docs/
  00-governance/   PROJECT_RULES, SOURCE_OF_TRUTH, TOOLCHAIN, COST_POLICY,
                   PRODUCTION_DATA_SAFETY, DECISIONS, OPEN_ITEMS, LEGACY_INDEX
  01-product/      PRODUCT_BLUEPRINT (with requirement register)
  02-domain/       DOMAIN_MODEL, GLOSSARY, INVENTORY, COSTING, SALES_CASH, CATALOG_BARCODE
  03-workflows/    CRITICAL_FLOWS, ROUTE_CONTRACTS, INTERACTION_CONTRACTS
  04-architecture/ ARCHITECTURE, STACK_AND_MODULES, SCHEMA
  05-security/     SECURITY_MODEL (with AUTH PROFILE)
  06-api-performance/ TECHNICAL_RULES
  07-ux-design/    INFORMATION_ARCHITECTURE, PAGE_INVENTORY, NAVIGATION_CONTRACTS,
                   COPY_AND_TERMS, DESIGN_SYSTEM, LOCALIZATION, DESIGN_REFERENCES
  08-testing/      TEST_STRATEGY, ACCEPTANCE
  09-operations/   INFRASTRUCTURE, BACKUP_RESTORE
  10-release/      RELEASE_RUNBOOK, ROLLBACK_RUNBOOK
  11-tasks/        EXECUTION_CONTEXT, TASK_PLAN, TASK-001 …
  adr/
  handoff/         CURRENT_HANDOFF
```

**[ANALYSIS]** Proposed sequence. Each step is one bounded piece of work that ends in a checkpoint and waits for you.

| Step | Content | Needs from you |
| --- | --- | --- |
| RF-0 | This report | Answers to questions 1–4 |
| RF-1 | Governance and structure: create the layout, rebuild `AGENTS.md`, handoff, toolchain, cost policy, decision log with new statuses; retire legacy trees; no change to business content | Authorization for RF-1 |
| RF-2 | Product and domain: merged blueprint, real requirement register, glossary in Indonesian and English, lifecycle matrix | Answers to questions 8–15 and 19 |
| RF-3 | Architecture, security, technical rules: merge, ADRs, logical schema, authentication profile, hosting topology | Answers to questions 5–7 and 18 |
| RF-4 | P7 rebuild: tasks per role, information architecture, page inventory, navigation contracts, copy deck, design system, key screens in the BantuKasir design project | Your review at each of the three gates decided in D-024 and D-025 |
| RF-5 | Workflows completed with route and interaction contracts | — |
| RF-6 | Testing, operations, release documents adapted to the above | — |
| RF-7 | P11: Task baseline, `TASK_PLAN.md`, `EXECUTION_CONTEXT.md`, Task contracts | Approval of the baseline, then a separate authorization to begin implementation |

## 37. AICWDF deviation register

Entries use the format required by your Project instructions. Entries FD-02, FD-05 and FD-06 exist only if you choose the deviating option in the question named.

```text
FRAMEWORK DEVIATION: FD-01 — Restructure the repository instead of preserving its layout
AICWDF RULE: §1, §1.1, §1.2, §33 — reuse existing structure; do not reorganize a mature
  repository cosmetically; no documentation reset unless the owner requests it
BANTUKASIR REASON: No code exists; five superseded planning baselines coexist with the live
  one; two parallel document families cover the same subjects; the Owner has asked for
  re-foundation
BENEFIT: One authoritative document per subject; much smaller executor read path
RISK: Old identifiers (BU-…, CORE-…, D-…) lose their files; mitigated by a mapping table,
  a legacy index of commit SHAs and Git history
OWNER APPROVAL REQUIRED: YES (question 1)
```

```text
FRAMEWORK DEVIATION: FD-02 — English strings deferred beyond V1 (only if question 5 = B)
AICWDF RULE: §4B.2, §18.11, §41 — English mandatory as second language with a switcher
BANTUKASIR REASON: One Indonesian shop with Indonesian-speaking staff
BENEFIT: No translation or second-language layout testing on every UI Task
RISK: Later retrofit; mitigated by building translation keys and a bilingual glossary from
  the first Task
OWNER APPROVAL REQUIRED: YES
```

```text
FRAMEWORK DEVIATION: FD-03 — Google sign-in OFF
AICWDF RULE: §4A.3 — on by default for ordinary internet-connected web applications; may be
  skipped for a closed internal system where it adds complication without benefit
BANTUKASIR REASON: Invite-only staff accounts on a shared counter device; staff may have no
  Google account, or would leave a personal Google session signed in on the shop computer;
  the till should not depend on an external identity service
BENEFIT: Simpler authentication; no Google Cloud credentials or account-linking rules
RISK: Loss of a convenience for the Owner; can be added later behind the authentication
  boundary
OWNER APPROVAL REQUIRED: YES (question 6)
```

```text
FRAMEWORK DEVIATION: FD-04 — No email-based reset or verification
AICWDF RULE: §4A.2 — password reset and email verification in the default core
BANTUKASIR REASON: Zero recurring cost means no mail service; accounts are created by the
  Owner; the Owner issues one-time reset links to staff; the Owner's own recovery is an
  operator command on the server
BENEFIT: No mail dependency or cost
RISK: If the Owner is locked out, recovery needs server access; the procedure must be
  documented and rehearsed
OWNER APPROVAL REQUIRED: YES (question 6)
```

```text
FRAMEWORK DEVIATION: FD-05 — Point-of-sale screen designed counter-first (only if question 15
  confirms a fixed counter with scanner)
AICWDF RULE: §18, §27 — mobile-first
BANTUKASIR REASON: The cashier's primary tool would be a counter computer or tablet with a
  barcode scanner and keyboard
BENEFIT: Fastest path for the most frequent task
RISK: Phone use of the till is secondary; it must still work at 375 px. Owner and Operations
  screens stay mobile-first
OWNER APPROVAL REQUIRED: YES
```

```text
FRAMEWORK DEVIATION: FD-06 — Hosting other than the framework default (only if question 7
  rules the default out)
AICWDF RULE: §10, §20, §32 — Cloudflare, Hostinger VPS, Coolify; develop → staging → main
BANTUKASIR REASON: The repository assumes a generic VPS with hand-built Compose, Nginx and
  TLS renewal, and defines no staging environment
BENEFIT: None by default; I propose adopting the framework topology unless the server
  cannot support it
RISK: Hand-built TLS and deployment are extra work and extra failure points
OWNER APPROVAL REQUIRED: YES if deviating
```

```text
FRAMEWORK DEVIATION: FD-07 — Dedicated final-verification Tasks kept
AICWDF RULE: §19 — testing is inside each Task
BANTUKASIR REASON: Restore drills, real scanners and printers, and VPS benchmarks cannot be
  done inside a feature Task
BENEFIT: Honest evidence for physical and operational proof; matches §28 layer 3
RISK: None, provided feature Tasks still carry their own tests
OWNER APPROVAL REQUIRED: NO
```

```text
FRAMEWORK DEVIATION: FD-08 — Build Unit identifiers replaced by TASK-NNN
AICWDF RULE: §22.4 — Task IDs are permanent
BANTUKASIR REASON: No Build Unit was ever started; the units are being re-cut as vertical
  slices
BENEFIT: Framework-standard IDs and checklist from the first executed Task
RISK: Lost references; mitigated by a BU-to-Task mapping table
OWNER APPROVAL REQUIRED: NO
```

**Conformance actions that are not deviations. [ANALYSIS]** Adopt password profile AICWDF-COMPAT-8 in place of the prototype's 12-character minimum (§4A.5 precedence rule). Adopt the framework's stricter production rule: coding agents prepare migrations and never run anything against production; the repository currently lets a Build Unit perform the production cutover after exact approval. Replace the repository's status vocabularies with TODO, READY, IN_PROGRESS, BLOCKED, VERIFYING, DONE, SUPERSEDED. Defer Graphify until code exists, which §6.4 permits. Add Context7 to the toolchain.

## 38. OWNER_REVALIDATION_REQUIRED

Each item is an existing decision that I recommend you re-confirm before it is carried into the re-founded documents. None will be changed without your answer.

| ID | Existing decision and source | Why it needs re-confirmation | Question |
| --- | --- | --- | --- |
| R-01 | Exactly three roles; the documents do not say whether one person may hold two (`01`, `04`, D-002) | In a small shop the same person may sell and receive goods. The current rule gives Operations no till and the Cashier no stock functions. | 8 |
| R-02 | Authentication details left open: password rule, Google sign-in, how reset links are delivered (`04`, `P5`) | The framework requires one recorded profile. | 6 |
| R-03 | Operations may type a purchase cost but may never see it again, not even on the review screen (D-007, planner) | The person typing the cost is reading it from the supplier's invoice, so the secrecy gained is small, while the rule drives a large amount of privacy engineering and makes staff retype the cost after any form error. | 9 |
| R-04 | Refunds and returns are Owner-only and paid from the Owner's separate funds, never from the cashier's drawer; no paid-in or paid-out (D-008, D-009, planner; G3 open) | If customers return goods at the counter when you are not there, the shop cannot serve them. If refunds do come from the drawer in practice, expected cash must account for it. | 11 |
| R-05 | Products are entered by hand; opening stock is typed in batches of at most 100; CSV import is "Later" (`06`, D-002; G2 open) | The performance benchmark assumes 5,000 products. Hand entry of a real stationery catalogue may be impractical. | 13 |
| R-06 | Payment is cash or bank transfer only; no discount or price change at the till (D-003, D-002) | Static QRIS confirmed by eye is common and is operationally the same as a transfer; "no discount at all" should be a deliberate choice. | 12 |
| R-07 | Every posting action, including an ordinary sale, must pass draft → review → explicit confirm (`P3`, planner) | Right for corrections and receiving. For sales it adds a second dialog and a mandatory checkbox to the most frequent task in the shop. | 19 |
| R-08 | Example product photos were added to the design (D-018) while photo upload stays out of scope (`01`) | The design promises something V1 cannot deliver; the till grid would show placeholders. | 13 |
| R-09 | Unknown purchase cost is allowed; when cost is supplied later the system replays history and publishes a new versioned valuation, keeping every earlier version (D-008, `15`, planner) | This is the most intricate component in the plan. Whether you need every past version of a profit figure, or only the corrected figure with a record of who corrected it, is a business choice. | 10 |
| R-10 | The client's name, logo, receipt header and accent colour are runtime settings with immutable revisions frozen into every receipt (D-010) | Necessary if BantuKasir will be deployed for several shops; more than is needed for one known shop. | 14 |
| R-11 | P7 completes only when you approve one exact DesainPakeAI revision covering everything, and P8–P11 wait for that (D-017, Owner) | One all-or-nothing visual approval is hard to give and has stalled the plan. Three smaller approvals would let you judge structure and wording before pixels. | 17 |
| R-12 | Hosting by hand-built Docker Compose, Nginx and TLS renewal on "the existing VPS"; no staging (D-005, `09`) | Differs from the framework default; the actual server is unknown (TARGET gate). | 7 |
| R-13 | Indonesian-only interface (implicit in all design documents) | The framework requires English as well. | 5 |
| R-14 | Staff sessions end after 30 minutes of inactivity and 12 hours absolute; sensitive Owner actions need the password again within 15 minutes (D-013, planner) | A cashier idle for half an hour on a quiet afternoon would be signed out with a drawer open. The right values depend on how the counter is used. | 15 |
| R-15 | Repository documents written in dense mixed Indonesian and English | Hard to read for you and imprecise for an executor. | 2 |

## 39. Owner questions

Questions 1–4 are needed first; they are enough to authorize RF-1. The others can be answered while RF-1 is under way, and each is needed only before the step shown in section 36. Where I give a recommendation it is marked.

*Revision 2 status: questions 1, 2, 3, 4, 16 and 17 are **ANSWERED** (section 41). Question 5 is explicitly deferred by the Owner. Questions 6–15, 18 and 19 remain open. The original wording is kept below for the record.*

**Needed first**

1. **Depth of re-foundation.** (A) Full restructure to the layout in section 36, with legacy trees removed from the working tree and preserved by Git history — recommended. (B) Keep today's files and add only the missing framework pieces.

2. **Language of repository documents.** (A) English for everything agents read (rules, contracts, Tasks, architecture), with Indonesian used for the interface copy deck and the glossary — recommended, because the framework and your instructions are in English and it removes the mixed-language ambiguity. (B) Indonesian, as now. (C) Both, side by side.

3. **Who applies the changes.** From this session I can write files into the folder, but this environment has no Git identity and different line-ending behaviour, so commits should be made on your Windows machine. (A) I write the planning documents directly into the folder with no Git operations, and Opus in Claude Code Desktop then validates and commits them as one bounded checkpoint — recommended for planning documents. (B) I prepare a prompt and Opus makes every change.

4. **Housekeeping, each yes or no.** (a) Delete the untracked `plan-p0-p11-review.patch`, which duplicates commit `5e100aa`? (b) Drop `stash@{0}` (rejected UI03 prototype)? (c) Remove the `upstream` remote that points at `latansa-inventory`? (d) Is the GitHub repository `yusufarst/bantukasir` private? (e) After the RF-1 checkpoint, may the branch be pushed to `origin`, and should `main` eventually be replaced by the re-founded branch through a pull request? (f) Executor effort label: "high" or "xhigh"?

**Framework defaults**

5. **English in the interface.** (A) Translation keys from the first Task, Indonesian and English both shipped and tested in V1 — this is what the framework requires. (B) Translation keys from the first Task, Indonesian only in V1, English added later — deviation FD-02. (C) No localization architecture — not recommended.

6. **Sign-in.** Confirm: email and password only, no Google sign-in; accounts created by you; you issue one-time reset links to staff; your own recovery is an operator command on the server; password rule is the framework's 8-character profile. If you want Google sign-in for yourself, say so.

7. **Server.** Which provider and what size is the VPS (CPU, memory, disk)? Is anything else running on it? May Coolify be installed, and may a staging copy run on the same machine? Is the domain behind Cloudflare?

**Business rules**

8. **Roles per person.** In the real shop, will one person ever need to both sell and manage stock? (A) Never: one role per account, as now. (B) Yes: an account may hold more than one of the three roles.

9. **Purchase cost and the Operations role.** (A) Keep write-only. (B) Operations can see and correct cost on the receiving records they handle, but never profit, margin or cost reports — recommended. (C) Operations never enters cost; you complete it afterwards.

10. **Correcting a cost later.** When a missing or wrong purchase cost is fixed after sales have happened: (A) the system recalculates and keeps every earlier version of the affected profit figures, as now; (B) the system recalculates, the corrected figures replace the old ones, and an audit entry records who changed which cost and when — recommended.

11. **Refunds and returns.** Who handles a customer return at the counter, and where does the money come from? (A) Only you, from your own funds, never from the drawer, as now. (B) The cashier, from the drawer, with your approval on the spot. (C) Only you, but paid from the drawer. If B or C, cash paid out must enter the session's expected cash.

12. **Payment and price at the till.** (a) Do you accept static QRIS, and if so should it appear as its own method, confirmed by the cashier, with no gateway? (b) Confirm there are no discounts and no price changes at the till in V1.

13. **Catalogue.** About how many products will the shop have at launch? Do they already exist in a spreadsheet? If there are more than a few hundred I recommend moving CSV product import into V1. And for photos: (A) no product photos in V1, and the design stops using photo cards — recommended; (B) add photo upload to V1.

14. **One shop or many.** Is V1 for one known shop, or a product you will deploy separately for several client shops? This decides whether versioned client branding belongs in V1. Related: is LATANSA the client, and should its logo remain in this repository?

15. **The counter.** What will the cashier use: a computer with a USB scanner, a tablet, a phone? Is it shared between staff? Which receipt printer and paper width? How many cash drawers? Is there usually an opening float? Does the shop charge PPN or need tax details on the receipt? These answers settle FD-05, R-14 and gates G1 and G3.

**Design**

16. **P7 path.** (A) Rebuild from tasks, structure and wording, keeping the visual tokens — recommended. (B) Correct the current candidate step by step. (C) Approve the current candidate as it is.

17. **Form of your design approval.** Replace the single exact-revision approval of D-017 with three: navigation and page structure; terminology and copy; visual direction on a small set of key screens. Agree or keep D-017 as written?

**Operations**

18. **Backup destination.** An independent copy outside the VPS is required. Options with no or low cost: a free-tier object store, or a machine or cloud drive you already own. Will you accept a small monthly cost if the free options do not fit, and who should hold the recovery key?

19. **Confirming a sale.** (A) Keep two steps and the mandatory checkbox on every sale. (B) One payment screen that shows the total, the method and the change, with one confirm button; the duplicate-charge protection stays in the system but is not shown as a step — recommended. Corrections, refunds, receiving and session close keep their review step.

## 40. RF-1 scope for final Owner authorization (revision 2)

RF-1 has **not** started. This section replaces the outline given in the first revision and applies the decisions in section 41. It is the exact scope I am asking you to authorize.

### 40.1 Purpose and boundary

RF-1 gives the repository the AICWDF-4.3 structure and a truthful set of governance documents. It changes no business rule, no architecture decision, no security rule and no design. Existing specifications are moved, not edited.

Baseline: branch `plan/bantukasir-pos-v1`, HEAD `604d9254f6a88958a1f1e36d54beabff54efdb96`, nothing staged, one untracked patch file, one stash. **[FACT]** Re-verified immediately before writing this section.

### 40.2 What happens to each of the 208 tracked files

**[FACT]** The counts below were derived from `git ls-files` and add up to 208.

| Group | Files | Treatment in RF-1 | Done by |
| --- | ---: | --- | --- |
| Root rules files: `AGENTS.md`, `CLAUDE.md`, `README.md` | 3 | Rewritten in place, in English | Planner |
| Existing canonical documents and current P7 evidence | 72 | Moved byte-for-byte into `legacy/` subfolders of the new layout | Planner |
| Retired trees | 128 | Removed from the working tree with `git rm`, after validation | Windows executor |
| `assets/brand/` (3), `.env.example`, `.gitignore` | 5 | Not touched | — |
| New documents | 12 | Written in English | Planner |

After RF-1 the repository holds 92 tracked files: 3 rewritten + 72 moved + 5 untouched + 12 new.

### 40.3 Part A — planner work (file operations only)

I perform no Git command that writes, and I delete nothing.

**Rewritten (3) and new (12) documents.**

| Path | Content |
| --- | --- |
| `AGENTS.md` | Short stable contract: authority model, read path, safety rules, what is and is not authorized. No session-specific text. |
| `CLAUDE.md` | A few lines pointing to `AGENTS.md`. |
| `README.md` | What BantuKasir is, current state in two sentences, document map. |
| `docs/00-governance/PROJECT_RULES.md` | Decision classes, change control, phase continuity, quality and validation rules, Git safety, Windows line-ending rule. Adapted from the existing P0 and your Project instructions. |
| `docs/00-governance/SOURCE_OF_TRUTH.md` | Authority hierarchy and a document map giving the status of every file, including each legacy document. |
| `docs/00-governance/TOOLCHAIN.md` | Capability registry and MCP policy (section 30). |
| `docs/00-governance/COST_POLICY.md` | Zero-recurring-cost rule, cost inventory, empty exception registry. |
| `docs/00-governance/PRODUCTION_DATA_SAFETY.md` | Production database zero-touch rules; existing protections carried over unchanged in meaning. |
| `docs/00-governance/DECISIONS.md` | D-001 to D-018 with their new status (section 26) and D-019 to D-025 (section 41). |
| `docs/00-governance/FRAMEWORK_DEVIATIONS.md` | FD-01 approved; FD-07 and FD-08 recorded; FD-02 to FD-06 proposed and pending. |
| `docs/00-governance/OPEN_ITEMS.md` | Open Owner questions 5–15, 18, 19; revalidation items R-01 to R-15 with status; gates G1–G4, DEVICES, TARGET; new items N-01 and N-02. |
| `docs/00-governance/LEGACY_INDEX.md` | Old path → new path for the 72 moved files; the retired trees with the commit where each last existed; the preserved patch and stash with their hashes; a note on BU, CORE and suite identifiers. |
| `docs/00-governance/REFOUNDATION_PLAN.md` | Steps RF-0 to RF-7 with status, and the three P7 gates. |
| `docs/00-governance/records/REFOUNDATION_ANALYSIS_2026-10-03.md` | This report, as a historical record. |
| `docs/handoff/CURRENT_HANDOFF.md` | Current state, last action, open blockers, next authorized action, what is not authorized. |

The first three rows are the rewritten root files; the other twelve are new.

**Moves (72 files, content unchanged).**

| From | To |
| --- | --- |
| `docs/00-CURRENT-STATE.md`, `docs/10-DECISIONS.md`, `docs/planning/P0-GOVERNANCE.md`, `docs/planning/GAPS-CHANGES.md` | `docs/00-governance/legacy/` |
| `docs/01-PRD.md`, `docs/planning/P1-PRODUCT.md`, `docs/requirements/` (2 files) | `docs/01-product/legacy/` |
| `docs/06-INVENTORY-SPEC.md`, `docs/12-BARCODE-SCANNER.md`, `docs/15-FINANCE-PROFITABILITY.md`, `docs/17-POS-SALES.md`, `docs/planning/P2-DOMAIN.md` | `docs/02-domain/legacy/` |
| `docs/planning/P3-CRITICAL-WORKFLOWS.md` | `docs/03-workflows/legacy/` |
| `docs/02-ARCHITECTURE.md`, `docs/planning/P4-ARCHITECTURE.md` | `docs/04-architecture/legacy/` |
| `docs/04-AUTH-RBAC-SECURITY.md`, `docs/planning/P5-SECURITY.md` | `docs/05-security/legacy/` |
| `docs/planning/P6-CONCURRENCY-API.md` | `docs/06-api-performance/legacy/` |
| `DESIGN.md`, `docs/planning/P7-UX-DESIGN.md`, `P7-COVERAGE.md`, `docs/evidence/p7-correction/` (7), `docs/evidence/p7-owner-r1/` (8) | `docs/07-ux-design/legacy/` |
| `docs/08-TESTING-ACCEPTANCE.md`, `docs/planning/P8-QUALITY.md` | `docs/08-testing/legacy/` |
| `docs/09-DEPLOYMENT-OPS.md`, `docs/planning/P9-OPERATIONS.md` | `docs/09-operations/legacy/` |
| `docs/planning/P10-RELEASE-UAT.md` | `docs/10-release/legacy/` |
| `docs/11-BUILD-PLAN.md`, `docs/planning/P11-BUILD-UNITS.md`, `docs/build-units/` (28) | `docs/11-tasks/legacy/` |

**Planning choices I have made within my own authority.** You can overrule any of them.

1. Legacy documents are moved and nothing inside them is edited or translated. They stay in Indonesian and remain the authoritative content for their subject until the later step that rebuilds that folder replaces them. This keeps the promise that RF-1 changes no business rule, and it lets the executor prove it mechanically.
2. Links inside legacy documents are not maintained and will point at old paths. The document map says so. Links in new documents are checked.
3. The four superseded governance files are kept in `docs/00-governance/legacy/` so that the new decision log and rules can be checked against them side by side. They are removed in a later step.
4. No `.gitattributes` and no line-ending normalization. The CRLF/LF difference is recorded as an open technical item for the foundation Task.
5. Nothing is created in `docs/11-tasks/` (other than the legacy folder) or `docs/adr/`. Task files belong to RF-7 and ADRs to RF-3.

**Handover.** When Part A is done I give you one bounded checkpoint prompt for Claude Code Desktop and stop.

### 40.4 Part B — Windows executor checkpoint (Claude Opus 5.5 xhigh)

One bounded Task, run from my prompt, in this order:

1. **Intake.** Confirm branch, HEAD `604d925`, no divergence beyond the known 7 commits ahead, nothing staged, and that every difference in pre-existing tracked files is either a planned move, a planned rewrite or line endings only.
2. **Safety copy (recommended, needs your answer in 40.7).** Create a Git bundle of all refs and the stash at a location you name outside the repository folder, and verify it. **[FACT]** Seven commits and the stash exist only on this computer; `origin` stops at `d41543d`, and no push is allowed in RF-1.
3. **Validate Part A independently.** Every one of the 72 moves is detected by Git as a 100 % rename. The three rewritten root files and the 12 new files contain no secrets, no placeholders and no broken links. D-001 to D-018 are carried faithfully into `DECISIONS.md`. Counts are re-derived and match section 40.2.
4. **Remove the retired trees with `git rm`** (128 files), only after confirming each exists at `604d925`: `docs/archive/plan2/` (41), `docs/evidence/plan2-validation.md` (1), `docs/evidence/rebaseline-p0-p11/` (3), `prototypes/` (26), `.impeccable/` (57).
5. **Remove the `upstream` remote**, after confirming its only ref equals `main`. **[FACT]** `upstream/plan/retail-order-replan` and `main` are both `f7cecfe`, so no unique commit is lost.
6. **Stage exact paths only.** Never `git add -A`. `.local/`, `.agents/`, the patch file and the stash are left exactly as they are.
7. **One local commit** under your configured Git identity, with no AI attribution trailer. Proposed message: `docs: re-found repository on AICWDF-4.3 structure (RF-1)`.
8. **Report and stop.** Exact evidence: commands run, rename table, file counts, staged scope, commit SHA, final `git status`. No push, no merge, no change to `main`.

**Stop conditions for the executor.** HEAD is not `604d925`; any unexpected modified, staged or untracked file; any moved file that is not a 100 % rename; any path in the removal list that is missing or not in the list; a secret pattern in any new file; any instruction that would require a push, a merge, a history rewrite, deleting the patch or dropping the stash.

### 40.5 Completion

RF-1 is complete only when all three have happened: the executor's commit exists; I have reviewed the executor's report against the actual repository state; and you have accepted the result. The patch file and the stash are reconsidered only after that, under D-022.

### 40.6 Not part of RF-1

Any change to product, domain, workflow, architecture, security, testing, operations or release content; translation of legacy documents; P7 design work or any design-tool access; route or interaction contracts; ADRs; the Task baseline or any Task file; advancement of P8–P11; `.env.example`, `.gitignore`, `assets/`; tool installation; deleting the patch; dropping the stash; push, merge, pull request, or any change to `main`; application code.

### 40.7 What I need from you to begin

1. **Authorization:** "RF-1 authorized as scoped in section 40", or your changes to the scope.
2. **Safety copy:** yes or no to the Git bundle in step 2 of Part B, and if yes, the folder where it should be written (for example an external drive or a synced folder outside `C:\Projects\bantukasir`).

**STOP.** I am not starting RF-1. I am waiting for your authorization.

## 41. Owner decision register (RF-0 answers, 3 October 2026)

**[OWNER]** Decisions given in this conversation. They will be written into `docs/00-governance/DECISIONS.md` during RF-1, continuing the repository's numbering after D-018.

| ID | Question | Decision | Effect |
| --- | --- | --- | --- |
| D-019 | 1 | Full restructure to the AICWDF-4.3 layout. | FD-01 approved. AICWDF-4.3 replaces the framework reference in D-001. D-016's preservation approach is superseded. |
| D-020 | 2 | English for agent-facing repository documents. Indonesian remains the primary interface language. Localization for V1 will be finalized separately. | R-15 resolved. Question 5 and FD-02 stay open by your choice. |
| D-021 | 3 | The planner (Fable) may write planning and re-foundation documents directly, with no commits and no other Git mutations. Claude Opus 5.5 xhigh on Windows independently validates and creates the checkpoint commit. | Defines Parts A and B of section 40. |
| D-022 | 4a, 4b, 4c, 4e | (a) Do not delete `plan-p0-p11-review.patch` during RF-1; keep it until RF-1 and the legacy mapping are independently validated. (b) Do not drop `stash@{0}` during RF-1; keep it until its unique content is verified as recoverable elsewhere. (c) The `upstream` remote is marked for removal as unrelated legacy state, performed by the Windows executor during checkpoint work. (e) No push in RF-1; `main` is not modified; no merge. After the re-founded baseline is reviewed and explicitly approved, a later pull request may replace the obsolete `main`. | Applied in section 40.4. |
| D-023 | 4f | Planning architect: Fable 5.1 xhigh. Implementation executor: Claude Opus 5.5 xhigh. | Confirms D-011 with "xhigh". |
| D-024 | 16 | P7 is rebuilt from information architecture, user tasks and plain-language copy, retaining useful visual tokens. | Candidate `sha256-bef01d649ea91b2b` will not be approved; it becomes legacy evidence. `approvedRevision` stays null. |
| D-025 | 17 | The single all-or-nothing P7 approval is replaced by three Owner gates: (1) navigation and page structure; (2) terminology and copy; (3) visual direction and key screens. | Supersedes the approval mechanism of D-017. R-11 resolved. |

**Standing boundary restated by the Owner:** do not begin implementation.

**Owner-supplied fact.**

| ID | Question | Fact | Consequence |
| --- | --- | --- | --- |
| OF-01 | 4d | The GitHub repository `yusufarst/bantukasir` is currently public. | Everything pushed is published, including the security model and operations runbooks. Nothing new is published in RF-1 because RF-1 does not push. |

**New open items raised by these answers.**

| ID | Item | My recommendation | Needed by |
| --- | --- | --- | --- |
| N-01 | Repository visibility before the next push. **[OWNER]** | Decide before any push whether the repository should stay public. The re-founded documents will include the threat model, backup design and deployment topology. I recommend making it private before the next push. | Before the first push after RF-1 |
| N-02 | Off-machine safety copy. **[OWNER]** | Seven commits and the stash exist only on this computer. Create a verified Git bundle outside the repository folder before the retired trees are removed. | RF-1 authorization (section 40.7) |
| N-03 | Wording in the Project instructions. | The Project instructions still say the executor runs "at high reasoning". You may wish to change that line to "xhigh" to match D-023; I cannot edit Project instructions. | When convenient |

**Verified while recording these decisions. [FACT]** The stash is not a duplicate of `.local/ui03`: three of its six files differ, which supports keeping it (D-022b). The `upstream` remote's only ref equals `main`, so removing it loses nothing (D-022c). The repository is unchanged: HEAD `604d925`, nothing staged, one untracked patch, one stash.
