# Execution context

| | |
| --- | --- |
| Status | CURRENT — written in RF-7 under D-090, 18 October 2026 (Asia/Jakarta); corrected under D-091 the same day, after the independent review of the baseline: EXC-12, EXC-21, EXC-22, EXC-31, EXC-32 and EXC-63, the new rules EXC-34 to EXC-37, and sections 4 and 15. The Task baseline it serves is approved by the Owner (D-092; gate GATE-P11 of [TASK_PLAN](TASK_PLAN.md)) |
| Phase | P11 — Task planning, dependencies and execution specifications |
| Replaces | The execution, holds, status and evidence sections of `docs/11-tasks/legacy/11-BUILD-PLAN.md` and `docs/11-tasks/legacy/P11-BUILD-UNITS.md`, and the READ, VERIFY, STOP and UPDATE sections of the legacy Build Unit cards (LEGACY — SUPERSEDED, [LEGACY_INDEX](../00-governance/LEGACY_INDEX.md) section 16) |
| Decisions | D-019, D-021, D-031, D-066, D-076, D-085, D-088, D-090, D-091, D-092; FD-07, FD-08 |
| Related | [TASK_PLAN](TASK_PLAN.md), [DEPENDENCY_GRAPH](DEPENDENCY_GRAPH.md), [TRACEABILITY](TRACEABILITY.md), the Task contracts in [tasks/](tasks/), [TEST_STRATEGY](../08-testing/TEST_STRATEGY.md), [PROJECT_RULES](../00-governance/PROJECT_RULES.md) |

How an implementation executor carries out one Task of BantuKasir: what it reads, what it may and may not do, the obligations every Task carries, how evidence is recorded and how a Task is closed. Rules are numbered `EXC-nn`; identifiers are permanent. This document authorizes nothing: a Task runs only on the Owner's explicit instruction naming it ([AGENTS.md](../../AGENTS.md) rule 1; PROJECT_RULES section 4). Where this document and a source above it in [SOURCE_OF_TRUTH](../00-governance/SOURCE_OF_TRUTH.md) section 1 disagree, the source above prevails and the difference is reported.

## 1. The product in one paragraph

BantuKasir is a goods-only point of sale for one business with one stock pool per installation (D-038): catalogue, barcode search and labels; receiving, manual issue, adjustment, reversal and opening stock; a moving-weighted-average cost with UNKNOWN cost and Owner corrections; a till with cash sessions, one full CASH or BANK_TRANSFER payment per Sale and receipts; Owner refunds and returns; Owner reports; audit, recovery and an independent encrypted backup. Three fixed roles: SUPER_ADMIN (the Owner), OPERATIONS_ADMIN and CASHIER, with one combined CASHIER and OPERATIONS_ADMIN account allowed (D-032). The stack is Laravel, Inertia, React with TypeScript, shadcn/ui with Tailwind CSS and PostgreSQL in one modular monolith ([ADR-001](../adr/ADR-001-stack-and-modular-monolith.md); [STACK_AND_MODULES](../04-architecture/STACK_AND_MODULES.md)). V1 ships in Bahasa Indonesia only, with every text from translation keys ([ADR-005](../adr/ADR-005-localization-architecture.md)).

## 2. What to read

| ID | Rule | Source |
| --- | --- | --- |
| EXC-01 | **Read path.** For every Task, in this order: [AGENTS.md](../../AGENTS.md); [CURRENT_HANDOFF](../handoff/CURRENT_HANDOFF.md); this document; [TASK_PLAN](TASK_PLAN.md) sections 1 to 4; the active Task contract; then exactly the sections the contract lists under "Read first". Read more only when a cited section points to another one that the work needs. | PROJECT_RULES section 14 |
| EXC-02 | **The contract cites, it does not copy.** A Task contract names the rules it implements by their identifiers (`REQ-…`, `BR-…`, `SCH-…`, `TR-…`, `SEC-…`, `RC-…`, `UXI-…` and so on). The cited document is the authority for the rule's text; the contract adds no business rule. | SOURCE_OF_TRUTH section 1 |
| EXC-03 | **Version-sensitive facts** are checked against the official documentation of the pinned version, through Context7 or the documentation site, before they are relied on; remembered APIs are not evidence. The check and its source are recorded in the evidence file. | STK-04, TOOLCHAIN section 1 |
| EXC-04 | **Interface Tasks** read, for each page they build: the page's rows in INFORMATION_ARCHITECTURE section 5, NAVIGATION_CONTRACTS section 2, INTERACTION_INVENTORY section 2, UI_STATES section 6, the page's section of COPY_DECK, INTERACTION_CONTRACTS section 9 for its controls, DESIGN_SYSTEM sections 7 to 12 and, where one exists, its key screen in KEY_SCREENS with its preview in `docs/07-ux-design/previews/`. These are approved artifacts (D-082, D-086, D-087): they are implemented, never edited by an interface Task. | D-082, D-086, D-087 |
| EXC-05 | **The Golden Frontend Reference v1.0.0** is read only at commit `14497d997ee7994dfa9153d41b8cea636578e24c`, after confirming that tag `v1.0.0` still resolves to it, and only for its generic tokens, components and UX patterns, translated into the stack; its MULTIPLECORP business examples, branding, fixtures and workflows are never adopted, and its files are not copied into this repository. A Task that uses it says so in its evidence, naming what it used. | TOOLCHAIN section 3, AGENTS rules 5 and 6, D-085 |
| EXC-06 | **Legacy material** (`legacy/` folders, the Build Unit cards) is never a source for a Task. TASK_PLAN section 9 maps each former Build Unit to its Tasks for history only. | AGENTS rule 15, FD-08 |

## 3. Authorization, model and session

| ID | Rule | Source |
| --- | --- | --- |
| EXC-10 | **One Task, one explicit instruction.** A Task starts only when the Owner's instruction names it. The instruction states Agent, Model, Effort, Session, Reason and Escalation, and whether it authorizes a push of the Task branch, a pull request, a merge, a tag or anything else beyond local commits. Finishing a Task authorizes nothing: not its merge, not the next Task. | AGENTS section 6, D-031, PROJECT_RULES section 4 |
| EXC-11 | **Model and effort.** Every executor runs as Claude Opus 5.5 at effort Max. An executor that is not running as Opus 5.5 at effort Max states its model and effort and stops (gate EXECUTOR). | D-066, OPEN_ITEMS section 3 |
| EXC-12 | **One executor, one Task.** An executor works on one Task at a time. The Owner may run Tasks of different lanes of [DEPENDENCY_GRAPH](DEPENDENCY_GRAPH.md) section 4 in parallel, each in its own session and its own Git worktree or clone, when their dependencies allow it; two executors never change the same files at the same time, apart from their own entries in the shared registers of EXC-35. | PROJECT_RULES section 7, D-091 |
| EXC-13 | **No production, no staging.** No executor connects to, deploys to, migrates, restores or runs an operator procedure on staging or production, or holds a credential of the VPS, Coolify, Cloudflare, the backup store or the heartbeat monitor. Steps that a Task contract assigns to the operator are carried out by a person; the executor prepares them and records their result from the operator's report (section 13). | AGENTS rule 10, PRODUCTION_DATA_SAFETY section 5, ADR-011 item 1 |
| EXC-14 | **No new cost.** A Task never opens an account with, or pays for, any service. A tool that needs a login, an API key, a subscription, payment or administrator rights is a stop condition: the executor completes every safe step and asks the Owner for the minimum action needed. | AGENTS rule 13, COST_POLICY, TOOLCHAIN section 2 |

## 4. Task statuses

The statuses are those of PROJECT_RULES section 7. TASK_PLAN section 4 holds the status of every Task; it is the only place a status is recorded.

| Status | Entered when | Set by |
| --- | --- | --- |
| `TODO` | The Task is in the baseline | The baseline (RF-7) |
| `READY` | Every Task it depends on is `DONE` and every gate in its row of TASK_PLAN is satisfied, except a gate marked "before `DONE`"; only the Owner's instruction is missing | The executor that closes the last dependency, or the planning agent's bookkeeping instruction |
| `IN_PROGRESS` | The Owner's instruction names the Task and the executor has passed the checks of section 5 | The executor, in the Task's first commit |
| `BLOCKED` | Work cannot continue: a gate, an Owner question, a defect outside the Task or a stop condition of section 14. The row names the reason | The executor |
| `VERIFYING` | The implementation is complete, the Task's tests pass locally and in CI where a push is authorized, and the evidence file is written; the reviews of section 12 and the Owner's acceptance are pending | The executor, in the Task's last commit |
| `DONE` | The Definition of Done holds (section 13), the reviewer's check and, for review class A, the adversarial review passed with every finding fixed and verified, the Owner accepted the Task, every gate of its row marked "before `DONE`" holds, and its branch is merged into `develop` with CI green on the merge, which needs gate GATE-PUBLISH (EXC-31, EXC-32) | The executor of the next authorized Task, in its first commit, or a bookkeeping instruction of the Owner, naming the merge commit and the acceptance |
| `SUPERSEDED` | The Owner replaced the Task through change control (section 17) | The change that replaces it |

A Task is not `READY` because it exists, and not `DONE` because code compiles or a page renders (PROJECT_RULES section 7).

## 5. Before starting a Task

| ID | Rule | Source |
| --- | --- | --- |
| EXC-20 | **Git state.** Inspect the branch, HEAD, staged, unstaged and untracked files, the stash and the remotes, and compare them with CURRENT_HANDOFF section 2. A material difference is a stop condition; nothing is repaired or tidied. On Windows, check line-ending-only differences before treating a file as modified. | AGENTS section 4, PROJECT_RULES section 9 |
| EXC-21 | **Prerequisites.** Confirm in TASK_PLAN that every dependency is `DONE` and every gate in the Task's row is satisfied — a gate marked "before `DONE`" must hold only before the Task is `DONE` — and read the Task's "Prerequisites" section. A missing prerequisite is a stop condition. | PROJECT_RULES section 4, D-091 |
| EXC-22 | **Branch.** Create `task/TASK-NNN-<short-name>` from the current `develop` (ADR-011 item 3), with the short name of the Task's row. Before gate GATE-BRANCH no implementation Task can start. TASK-002, which changes documents only, may be written before `develop` exists, on the branch the Owner names (EXC-37). | ADR-011 item 3, OPEN_ITEMS N-12, D-091 |
| EXC-23 | **Tools.** Check that the tools the Task needs are present at the pinned versions; installing a missing development tool follows TOOLCHAIN section 2 and is recorded. The MCP servers a Task needs are named in its contract; every other server stays off. | TOOLCHAIN sections 2 and 4 |
| EXC-24 | **Record the start.** The Task's first commit sets its status to `IN_PROGRESS` in TASK_PLAN section 4 and creates its evidence file (section 11), with the base commit and the Owner's instruction reference. | PROJECT_RULES section 13 |

## 6. Branches, commits and publication

| ID | Rule | Source |
| --- | --- | --- |
| EXC-30 | **Commits.** Small, coherent local commits on the Task branch, under the Owner's configured Git identity, staged by exact path (never `git add -A`), with a message `TASK-NNN: <what changed>`. No attribution trailer and no mention of an AI model, agent or tool (`Co-Authored-By` and similar). Ignored tool folders (`.local/`, `.agents/`), `Claude outputs/`, `plan-p0-p11-review.patch` and the stash are never staged or changed. | AGENTS rules 11 and 12, PROJECT_RULES section 9 |
| EXC-31 | **Push, pull request, merge, tag.** Each needs the Owner's authorization for that action, given in the Task's instruction or separately. Before every push the publication guard of D-076 runs on everything the push would publish — a secret scan, a privacy check and a client-data check — and a finding stops the push. Never `--force` or `--force-with-lease`; never a push of `main` outside an authorized release, of a tag without its authorization (REL-10), of the stash or of a `refs/codex/` ref. Gate GATE-PUBLISH of TASK_PLAN — OPEN_ITEMS N-09 decided and the one-time audit N-10 completed — must hold before the first push. Until it holds a Task may start and be built and verified locally, TASK-001 first, but no Task can be `DONE`, because `DONE` needs its pull request merged into `develop` with CI green (section 4). | D-076, AGENTS rule 11, RELEASE_PROCESS REL-10, D-091 |
| EXC-32 | **Pull request into `develop`.** The pull request names the Task, links its contract and evidence file, and lists the checks run. Its CI run must pass the gates of TEST_STRATEGY section 7, with the L5 critical paths of EXC-34. It is merged only when its branch contains the current `develop` (EXC-36) and CI is green on that state, and it is merged with a merge commit — never squashed or rebased — so that every commit its evidence names stays in the history of `develop`. The merge is the Owner's decision after the reviews of section 12. | ADR-011 items 3 and 4, TEST_STRATEGY section 7, D-091 |
| EXC-33 | **History.** Published history is never rewritten; a merged commit is corrected by a new commit. Divergence is never repaired automatically. | AGENTS rule 11 |
| EXC-34 | **L5 critical paths of a pull request.** The pull-request gate of TEST_STRATEGY section 7 runs the L5 critical paths that are already implemented on `develop` or by the change. Each path joins the gate with the Task that delivers it and stays in it from then on: sign-in with TASK-006; one recovery case with TASK-009; checkout in cash and by bank transfer with TASK-029; the close of a cash session with TASK-030; the drawer-funded refund with TASK-034. Until TASK-006 the L5 step is the Playwright smoke test of TASK-001. An implemented path is never skipped or marked as expected to fail, and its failure blocks the merge. | TEST_STRATEGY section 7, D-091 |
| EXC-35 | **Shared registers.** Several Tasks change TASK_PLAN section 4, CURRENT_HANDOFF, DECISIONS, OPEN_ITEMS, SOURCE_OF_TRUTH, TOOLCHAIN, STACK_AND_MODULES section 2.1 and SCHEMA. A Task branch changes in them only its own entries: its own row of TASK_PLAN section 4; the row of another Task only to record a fact of `develop` — a merged Task `DONE` (section 4), a dependent `READY` — never an expectation; the totals line of section 4, recounted from the rows whenever a row changes; the facts of CURRENT_HANDOFF that concern its own Task, after which the whole file is re-read for consistency; and new entries of its own. It never reorders, rewords or deletes an entry that another Task wrote. A new decision or open-item identifier is the one that the Owner's instruction names, or else the next free one on `develop`; if `develop`, once merged into the branch, already holds that identifier, the branch renumbers its own entry by a new commit before its merge. An identifier is permanent once it is on `develop`. | PROJECT_RULES sections 7 and 13, D-091 |
| EXC-36 | **Bringing a Task branch up to date.** A Task branch takes the changes of `develop` only by merging `develop` into it — `git merge develop`, a merge commit on the Task branch — never by rebase, reset, a cherry-pick of another Task's work or a force push. Conflicts are resolved in that merge commit: in a shared register by taking `develop`'s entries and re-applying only the Task's own (EXC-35); in code and tests by keeping the behaviour of both sides. After the merge the command contract and the Task's tests run again, and the evidence names the merge commit. A conflict that would change an approved P7 artifact, COPY_DECK, a business rule, a migration already merged into `develop` (EXC-41) or the behaviour of another Task is a stop condition: the merge is abandoned with `git merge --abort`, which keeps every commit of the branch, and the Owner decides. | ADR-011 item 3, AGENTS rule 11, D-091 |
| EXC-37 | **The first merges.** TASK-001 is the first Task merged into `develop`: it sets the line-ending policy, the repository conventions and the CI that every later merge relies on. TASK-002, which changes documents only, may be written before `develop` exists, on the branch the Owner names, starting from the commit that `develop` is created from or from an ancestor of it, so that merging `develop` into it brings no unrelated history (OPEN_ITEMS N-12); its package may be approved (gate GATE-CC1) before TASK-001 is merged. It is merged into `develop` only after TASK-001, by merging `develop` into its branch first (EXC-36). The Git blob, size and SHA-256 of every artifact of its package are measured again on the commit to be merged; a value that differs from the approved one is approved again by the Owner before the merge. | ADR-011 item 3, OPEN_ITEMS T-01 and N-12, D-091 |

## 7. Engineering rules every Task applies

Each rule is cited, not restated. A Task contract lists only the rules specific to it.

| Subject | Rules |
| --- | --- |
| Data integrity | AGENTS rule 7; SCHEMA SCH-01 to SCH-10; ADR-006; ADR-007; TECHNICAL_RULES sections 6 to 8 |
| Layers and modules | ARCHITECTURE ARC-01 to ARC-14; STACK_AND_MODULES section 2 with STK-10 to STK-13, section 3 with STK-20 to STK-22, section 4 with STK-30 to STK-33. A new dependency between modules is recorded in STACK_AND_MODULES section 2.1 in the same change (STK-13) |
| Commands | INTERACTION_CONTRACTS sections 1 and 2; ROUTE_CONTRACTS RT-01 to RT-12; TECHNICAL_RULES sections 3, 4 and 9; ADR-009 |
| Authorization and privacy | AGENTS rule 8; SECURITY_MODEL sections 5 to 7; ROUTE_CONTRACTS RT-05; deny by default on the server, hiding is never authorization |
| Validation and limits | TECHNICAL_RULES sections 5, 11 and 12 |
| Numbers and time | ADR-006; ARCHITECTURE ARC-50 and ARC-51; no floating point for money, quantity or cost; facts in server UTC, business dates in Asia/Jakarta |
| Copy | ADR-005; COPY_DECK section 1; no user-facing literal in PHP, Blade, TypeScript or JSX (REQ-NFR-01) |
| Secrets | AGENTS rule 9; SECURITY_MODEL section 8; placeholders only in `.env.example` |
| Dependencies | STK-01 to STK-03 and COST_POLICY section 1: a package is added only when the Task needs it, with its licence recorded in the evidence and, for a runtime package, in TOOLCHAIN |
| Production data | PRODUCTION_DATA_SAFETY; no destructive operation, reset or seed on any path that production can reach |

## 8. Database and migrations

| ID | Rule | Source |
| --- | --- | --- |
| EXC-40 | **SCHEMA is the specification.** A migration implements the tables, columns, constraints, indexes and grants of SCHEMA exactly; a Task that must differ — a name, a mechanism the pinned version lacks — updates SCHEMA in the same change and records why (SCHEMA section 1, SCH-54). | SCHEMA sections 1 and 13 |
| EXC-41 | **Forward only.** Migrations are forward and additive (REL-05, SCH-50). A migration merged into `develop` is never edited; a later change is a new migration. No migration runs at application start-up, and no reset, `migrate:fresh`, `db:wipe` or seeder exists on a path that staging or production can reach. | PRODUCTION_DATA_SAFETY sections 1 and 2, ARC-64 |
| EXC-42 | **Raw SQL where the builder falls short.** Partial and expression unique indexes, check constraints, composite, deferred and presence keys, `MATCH FULL`, sequences, grants and revokes are written as explicit SQL statements, reviewed as SQL, and proven on real PostgreSQL (SCH-51). | SCH-51 |
| EXC-43 | **Grants with the table.** The migration that creates a table grants exactly the runtime privileges of SCHEMA section 10 and updates the machine-readable privilege matrix that the privilege family compares (TASK-004); default privileges are never relied on (SCH-45). | SCH-45, SCHEMA section 10 |
| EXC-44 | **Keys across Tasks.** SCHEMA defines some foreign keys between tables that different Tasks create (for example `stock_movements` and the line tables of every posting). The Task that creates a table creates every key of that table whose target already exists, and adds, by a new migration, every key of an earlier table that targets the new one; each contract lists those keys under "Keys completed". A column whose key is not yet complete is never written by application code before its key exists. | SCHEMA sections 3.6, 3.7 and 5 |
| EXC-45 | **Tests on the database.** Every table a Task creates gets the constraint, deferred, privilege and retention families of TEST_STRATEGY section 5 in that Task, on real PostgreSQL of the pinned version, with committed fixtures and independent connections, run as the runtime role. Fixtures are created through the Actions; only the constraint family writes violating rows directly (TEST_STRATEGY section 3). Until an Action exists for a parent record, its fixture uses the owning module's own service, never a raw insert. | TEST_STRATEGY sections 1 and 3, STK-20, SCH-53 |
| EXC-46 | **Rehearsal and review.** Every migration carries its class, expected duration, lock impact and verification queries (REL-20) and is rehearsed on the provisional benchmark dataset once TASK-043 provides it (REL-21). | RELEASE_PROCESS section 5 |
| EXC-47 | **Backup impact.** A new table with retained data is in the manifest's counts (BACKUP_RECOVERY section 5); a new kind of stored file is in the backup; the Task states the impact in its evidence (TEST_STRATEGY section 10, "Operations"). | TEST_STRATEGY section 10 |

## 9. Interface work

| ID | Rule | Source |
| --- | --- | --- |
| EXC-50 | **The approved artifacts are the specification.** Pages, controls, navigation, states, copy and visual design follow the documents of gates P7-G1, P7-G2 and P7-G3 as approved, read with the later decisions that INTERACTION_CONTRACTS section 10 lists and with the change control of TASK-002. A page that cannot follow them is a stop condition, never a silent redesign. | D-082, D-086, D-087 |
| EXC-51 | **Copy through keys.** Every text uses a COPY_DECK key, created with exactly its name by the first Task that uses it; a text COPY_DECK does not name ships only after its change control (COPY_DECK section 1.1). The proposals of OPEN_ITEMS T-19 and T-20 are used only after gate GATE-CC1. | COPY_DECK section 1.1, ADR-005 |
| EXC-52 | **Controls.** A Task builds the controls that [TRACEABILITY](TRACEABILITY.md) section 9.2 assigns to it, each with its route of INTERACTION_CONTRACTS section 9, and no other. A control whose route a later Task delivers is added by that Task; until then it is absent, never visible without its behaviour (zero dead interactions, INTERACTION_INVENTORY section 3). A control that leads to one of several destinations — by role, by the record's kind or by the page that opened it — is built with the destinations that exist; for a destination not yet delivered it leads to the role's home (RN-01) until the Task that delivers that destination completes it ("Extended by" in TRACEABILITY section 9.2). The same holds for RN-01's homes (TASK-007). | INTERACTION_INVENTORY section 3, REQ-UX-02 |
| EXC-53 | **States, widths and access.** Every page shows the states of UI_STATES section 6 that apply to it; works at the widths of KEY_SCREENS section 4 and 1440 px; meets ADAPTIVE_AND_ACCESSIBILITY section 3 and its rules per page type; and passes the automated accessibility checks of TEST_STRATEGY TS-25. | REQ-UX-02, REQ-UX-03, UXA-01 to UXA-26 |
| EXC-54 | **Previews are references.** The previews of `docs/07-ux-design/previews/` are approved review material, not application code: the implementation reproduces their design in the stack's own components, and their token values may be transcribed into the stack's stylesheet (DESIGN_SYSTEM section 14). | DESIGN_SYSTEM section 14, KEY_SCREENS |

## 10. Standing test obligations

Besides the cases its contract names, every Task proves these for what it adds. They are the per-Task share of the suites whose final point is a verification Task (TEST_STRATEGY TS-09, section 12).

| What the Task adds | It proves | Suites it contributes to |
| --- | --- | --- |
| A route | It is in the route-inventory test with its gate; each role and the combined account by direct request; object scope with guessed identifiers answering `NOT_FOUND`; no `GET` that writes; `no-store` on authenticated responses; the DTO allowlist per role on every channel it serves | AUTH01, AUTH02, SEC01 |
| A table | The constraint, deferred, privilege and retention families; the actual grants equal SCHEMA section 10 | DB01, KEEP01 |
| A command | The envelope and its refusals; its request receipt (G-25); RACE-01 and RACE-02 for its operation; failure injection after every write step (TS-21); its audit event and durable result; recovery of the original result; recent authentication where INTERACTION_CONTRACTS section 1.5 names it (AUTH03); every race and guard its contract names | CMD01, AUTH03 and the domain suites named |
| A read | Paging and its limits, filters as allowlists, scope, a failed read never shown as zero (UXS-21, UXS-22), background reads marked (RT-10) | AUTH01, AUTH02 |
| A page | Its controls (EXC-52), its states (UI_STATES section 6), its copy keys, keyboard path, focus, widths, 200 % zoom, the automated accessibility checks, and the key screen where one exists | UX01, UX02, L10N01 |
| An operator procedure or scheduled command | The common rules of SECURITY_MODEL section 14 and OPERATIONS_RUNBOOK OPR-02; arguments from configuration or typed options only (THR-10); no overlap; its audit event and security log entry | OPPROC01, SEC01 |
| A document or export | Rendering from the snapshot, the copy marker, the CSV dialect, the audit of the print or export request | SESSION08 |

Fixtures for screens and browser tests extend the synthetic shop "Toko Contoh" of KEY_SCREENS section 5, created through the Actions. Tests control the clock, randomness and interleaving; a test that fails sometimes is a defect, never retried until it passes (TS-08).

## 11. Evidence

| ID | Rule | Source |
| --- | --- | --- |
| EXC-60 | **One evidence file per Task**, `docs/11-tasks/evidence/TASK-NNN.md`, created by the Task's first commit. The folder `docs/11-tasks/evidence/` is the folder of evidence summaries that TEST_STRATEGY TS-31 leaves to RF-7. | TEST_STRATEGY TS-30, TS-31 |
| EXC-61 | **Content.** For each check: the suite and cases or the static check; the revision (commit, and image digest where one exists); the environment and target, described without real identities; the dataset; the commands; the date and time in Asia/Jakarta; the result per case with the status vocabulary of TEST_STRATEGY section 8 (`NOT_RUN`, `PASS`, `FAIL`, `BLOCKED`); who ran it, by role; the CI run, when a push was authorized; gaps; and anything that later invalidates it. Also: the Definition of Done table (section 13), the review findings and their fixes, the versions and sources checked under EXC-03, the licences of added dependencies, and the readings and technical choices the Task made. | TEST_STRATEGY TS-30 |
| EXC-62 | **Honesty.** A check not run is `NOT_RUN`, never `PASS`. Evidence is never edited to look current; a check that a later change invalidates is marked so and run again (TS-32). Operational, device and pilot evidence keeps identities in the operator's private record; the repository gets a summary without them. | AGENTS rule 16, TS-01, TS-31, TS-32 |
| EXC-63 | **No production data and no secrets** in evidence, logs, screenshots or CI output; the repository is public (D-076). No secret-looking value is ever committed, not even a fake one for a test: a test that needs one — the negative sample of the secret scan, for example — generates it at run time in a temporary location outside the tracked tree and removes it afterwards, so that the secret scan and the publication guard of D-076 never need an exception for a committed sample. | TS-06, SEC-SECRET-01, D-091 |

## 12. Reviews

| ID | Rule | Source |
| --- | --- | --- |
| EXC-70 | **Every Task:** the executor's self-review of the whole diff against the contract (PROJECT_RULES section 12), then the reviewer's check of the evidence against the repository. | TEST_STRATEGY section 11 |
| EXC-71 | **Review class A** — a Task that touches money, stock, cash sessions, cost, authorization, recovery, a migration or an operator procedure — also gets an independent adversarial review in a separate session before it is `DONE`; its findings are fixed and the fixes verified. TASK_PLAN gives each Task's class. | TEST_STRATEGY section 11, D-075 review method |
| EXC-72 | **Review class B** — the self-review and the reviewer's check. **Class O** — a documentation Task that ends in the Owner's approval of an artifact by its hash. **Class V** — a verification Task whose results the Owner accepts. | TEST_STRATEGY sections 9 and 11 |

## 13. Definition of Done

A Task is done when TEST_STRATEGY section 10 holds: its evidence records, for each of the nine dimensions — business, UI and UX, backend, database, security, data integrity, performance, operations, verification — `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason, and none is `FAIL`; its acceptance criteria pass; its contract's tests pass on its revision; the standing obligations of section 10 hold for what it added; the reviews of section 12 passed; the documents it changed are consistent (PROJECT_RULES section 12); and the Owner accepted it. `N/A` never hides a real risk. A `BLOCKED` dimension keeps the Task in `VERIFYING` or `BLOCKED` unless its contract states that the dimension's final evidence belongs to a verification Task, which is the case for the final points of TEST_STRATEGY section 12.

## 14. Stop conditions

Stop the affected work, complete what is independent and still authorized, record the facts in the evidence file and report to the Owner when:

1. a business meaning, money or payment behaviour, stock behaviour, role or authorization, legal or tax behaviour, V1 scope, major workflow, recurring cost, accepted risk, destructive data behaviour or release risk would have to be decided (AGENTS rule 3);
2. two sources contradict each other and SOURCE_OF_TRUTH does not settle it, or a cited rule is missing, ambiguous or impossible on the pinned versions (for example a feature of SCH-52 fails);
3. a dependency or gate is not satisfied, or the repository differs materially from CURRENT_HANDOFF;
4. a tool needs a login, a key, a subscription, payment or administrator rights, or a service would cost money (EXC-14);
5. anything would touch staging or production, or a destructive operation appears necessary (PRODUCTION_DATA_SAFETY section 1);
6. a secret, credential or personal datum would be exposed, or the publication guard finds anything;
7. an approved P7 artifact or COPY_DECK would have to change, or a needed text has no key;
8. a test cannot be made deterministic, or a race or failure-injection result contradicts TECHNICAL_RULES section 8;
9. the named model or effort is not available (EXC-11);
10. the work would exceed the Task's scope or start another Task.

## 15. Closing a Task and the report

1. Run the command contract of TEST_STRATEGY section 7 and every test the contract names; record the results (section 11).
2. Self-review the diff (PROJECT_RULES section 12); update every document the Task changed and nothing else.
3. Set the Task to `VERIFYING` in TASK_PLAN section 4; for each dependent Task whose last dependency this was, note it in CURRENT_HANDOFF (it becomes `READY` when this Task is `DONE` and its gates hold).
4. Update CURRENT_HANDOFF last, in the Task's own facts (EXC-35), so that a new agent with no chat history can continue (AGENTS rule 18).
5. Commit. If `develop` has moved since the branch was created or last brought up to date, merge it into the branch (EXC-36) and repeat steps 1 and 2 for what the merge changed. Push or open the pull request only if authorized (EXC-31, EXC-32).
6. Report to the Owner, in Bahasa Indonesia, with: Agent, Model, Effort, Session; the Task; the branch, base commit and commits; the files changed; the commands run with their results; the status of each acceptance criterion and test; the Definition of Done table; the readings and technical choices made; the findings raised and the open items added; what is not verified and why; and the next Task that would become `READY` — a proposal, never an authorization (D-031).

## 16. Operator steps

Verification and release Tasks contain steps on staging, the restore-drill target, the intended server and production. The executor prepares the scripts, checklists and expected results in the repository and in local environments with synthetic data; the operator, a person, runs the steps after the Owner's authorization where RELEASE_PROCESS or OPERATIONS_RUNBOOK requires one, and reports; the executor records the report as evidence under EXC-62. The executor never asks for, receives or stores a credential, an address or a key of those environments (ENVIRONMENTS section 3).

## 17. Change control of Tasks and specifications

| ID | Rule | Source |
| --- | --- | --- |
| EXC-80 | **Task identifiers are permanent.** A Task is never renumbered or reused. A new Task takes the next free number in TASK_PLAN; a replaced Task becomes `SUPERSEDED` and names its replacement. Adding, splitting, merging or removing a Task is a change of the baseline that the Owner approves. | PROJECT_RULES section 7, FD-08 |
| EXC-81 | **A defect in a specification** found during a Task is a stop condition when it needs an Owner decision; a technical correction that changes no business meaning — a name, an index, a document reference — is made in the authoritative document in the same Task, recorded in the evidence, and, when it has lasting effect, in DECISIONS (PROJECT_RULES section 3). | PROJECT_RULES sections 3 and 6 |
| EXC-82 | **A defect found by a verification Task** is fixed by a Task of the baseline's change control, not inside the verification Task; the fix invalidates the `FINAL_PASS` of the suites it touches, which run again (TS-32). | TEST_STRATEGY TS-32 |

## 18. Windows notes

- The Owner works on Windows. Run Git with `GIT_OPTIONAL_LOCKS=0` and `-c core.autocrlf=true` in the device shell until TASK-001 applies the line-ending policy of OPEN_ITEMS T-01 (CURRENT_HANDOFF section 2). Do not rewrite files only to normalize line endings.
- Paths in documents and scripts use forward slashes; scripts that the Tasks add run in the containers of the local environment (TASK-001), so that local, CI and the release image use the same runtime.
