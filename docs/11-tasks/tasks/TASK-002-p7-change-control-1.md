# TASK-002 — P7 and copy change control, batch 1

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 1 Foundation · lane D, documents and change control |
| Size and review | M · review class O — the Owner approves the artifacts by hash ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | — |
| Gates | GATE-P11, GATE-BRANCH (before DONE: its merge into `develop`, after TASK-001), GATE-PUBLISH (before DONE: its push and pull request) ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-002-p7-change-control-1` from `develop` (EXC-22); or, before `develop` exists, the branch the Owner names, since this Task changes documents only; merged into `develop` only after TASK-001, with `develop` merged into it first (EXC-36, EXC-37) |
| Executor | Claude Opus 5.5 at effort Max (D-066), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None. |
| Modules | Documents only |
| Baseline | RF-7, D-090; corrected under D-091 |

## 1. Goal

Bring the approved P7 artifacts and COPY_DECK in line with the decisions taken after their approval, and add the copy that later Tasks need, as one change-control package that the Owner approves by hash before any interface Task uses it (OPEN_ITEMS T-19 and T-20; COPY_DECK section 1.1; PROJECT_RULES section 6). The approval is gate GATE-CC1.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied, except a gate marked "before DONE", which must hold before this Task is `DONE` ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- PROJECT_RULES section 6
- COPY_DECK sections 1, 2, 19, 20 and 21
- DESIGN_SYSTEM section 13
- INTERACTION_CONTRACTS sections 8, 10 and 11
- DOCUMENT_CONTRACTS DC-04
- OPEN_ITEMS T-19 and T-20
- DECISIONS D-082, D-086, D-087 and D-088 (their terms)
- GLOSSARY sections 1 and 13
- ADR-010 item 11 and OPERATIONS_RUNBOOK section 5 (the backup store's usage)
- INTERACTION_INVENTORY section 3 and the rows that INTERACTION_CONTRACTS section 10 names; NAVIGATION_CONTRACTS, the UXP-006 row

## 4. Scope

### 4.1 In scope

1. Add to COPY_DECK, with their keys, sources and the Owner's final wording: the five accent names, `reporting.finance.table_date`, `reporting.period.label` and `ux.action.more_actions`, and the percent format `32,0%` in section 1.3 (T-19); the keys of INTERACTION_CONTRACTS section 11 — `commands.requests.other_user`, `commands.requests.other_user_help`, `commands.requests.from_server`, `commands.requests.lost_help`, `ux.disabled.unresolved_checkouts`, the texts of `inventory.onboarding.withdraw_zero` and `inventory.opening_entry.replace`, and the five audit labels including `audit.action.projection_rebuilt` — and the CSV column headers of DOCUMENT_CONTRACTS DC-04 that UXI-206 shows (T-20).
2. Ask the Owner, one question at a time, whether the close refused while a checkout is unresolved gets a dedicated message instead of "Ada transaksi baru; hitung ulang kas" (D-088), and whether the system status shows the backup store's usage (ADR-010 item 11 says it does; OPERATIONS_RUNBOOK section 5 says it is no separate item); record each answer and apply it in COPY_DECK, OPERATIONS_RUNBOOK section 5 or both.
3. Apply every row of INTERACTION_CONTRACTS section 10 to the document it names — INTERACTION_INVENTORY rows UXI-025, UXI-029, UXI-055, UXI-058, UXI-076, UXI-086, UXI-103, UXI-149, UXI-150, UXI-227, UXI-228, UXI-291, UXI-292 and UXI-363, and the UXP-006 row of NAVIGATION_CONTRACTS — and re-derive the counts of INTERACTION_INVENTORY section 3 (finding F-02); UXI-244 and UXI-348 stay not provided.
4. Apply COPY_DECK findings F-01 to F-05: the gate rows of the approved documents name their approving decisions; the working labels that COPY_DECK section 19 lists are replaced by the deck's wording in the six P7-G1 documents; F-04 is closed by D-086; F-02 and F-05 as the previous item says.
5. Add a change-control section to COPY_DECK listing every change with its reason and source decision, so that the Owner reviews one list.
6. Measure, after the change commit, the Git blob, size and SHA-256 of every changed artifact and of every approved artifact that did not change; prepare the record of the Owner's approval in the form of D-082, D-086 and D-087. The approval itself is the Owner's decision, entered in DECISIONS when given.
7. Merge into `develop` only after TASK-001 has been merged (EXECUTION_CONTEXT EXC-37): first merge `develop` into this Task's branch, never rebase (EXC-36); resolve any conflict without changing an approved artifact; re-run the checks; and measure again the blob, size and SHA-256 of every artifact of the package on the commit to be merged. The Owner's approval (GATE-CC1) covers those values; a value that differs from the approved one is approved again before the merge.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | — |
| Keys completed (EXC-44) | — |
| Routes | — |
| Route elements added | — |
| Pages | — |
| Controls | — |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- The keyboard shortcuts and breakpoints that gate DEVICES confirms or changes (TASK-047).
- Any new page, control, state, navigation path, business rule or visual change.
- Implementation of any key (the interface Tasks).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | — | REQ-NFR-01, REQ-UX-01, REQ-UX-02 |
| Workflows | — | — |
| User tasks | — | — |
| Acceptance suites | — | L10N01, UX01 |
| Races | — | — |
| Guards | — | — |
| Threats | — | — |
| Open items | T-19 — resolved by this Task when GATE-CC1 is given, T-20 — resolved by this Task when GATE-CC1 is given, except the shortcuts and breakpoints of gate DEVICES (TASK-047) | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-002-01** Every proposal of DESIGN_SYSTEM section 13 and INTERACTION_CONTRACTS section 11, and every DC-04 column header, has its key in COPY_DECK with the Owner's approved text and its source; the percent format is in COPY_DECK section 1.3.
- **AC-002-02** Every row of INTERACTION_CONTRACTS section 10 and every finding F-01 to F-05 is applied in the documents it names, or recorded as needing no change with its reason; INTERACTION_INVENTORY section 3's counts are re-derived, not copied.
- **AC-002-03** No page, control, state, navigation path, business rule or visual design changes beyond those rows; the change list in COPY_DECK cites a decision for every entry.
- **AC-002-04** The Owner's two answers (the close-refusal message, the store's usage on the system status) are recorded verbatim and applied.
- **AC-002-05** Every approved artifact that the Task does not change is byte-identical to its approved blob, verified by hash; every changed artifact is listed with its blob, size and SHA-256 at the change commit.
- **AC-002-06** Links, table shape, LF line endings and the absence of trailing whitespace are checked on every changed document.
- **AC-002-07** The branch is merged into `develop` after TASK-001, from a state into which `develop` was merged without rebase; every artifact of the package on the merged commit has the blob, size and SHA-256 that the Owner approved.

## 7. Tests

- Hash comparison of every approved artifact against D-082, D-086 and D-087.
- Link and table-shape checks on the changed documents.
- A key-coverage check: every key the later Tasks' contracts name exists in COPY_DECK.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-002.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The change list with sources.
- The Owner's answers, verbatim.
- The table of blobs, sizes and SHA-256 at the change commit.

## 9. Definition of Done

| Dimension | This Task |
| --- | --- |
| Business | N/A — a documentation Task; it adds no rule (its acceptance is section 6) |
| UI and UX | Applies — the approved P7 artifacts change only as section 4.1 says |
| Backend | N/A — no code |
| Database | N/A — no migration |
| Security | Applies — no secret or personal datum in the changed documents |
| Data integrity | N/A — writes no business fact |
| Performance | N/A — no code |
| Operations | N/A — no backup or runbook impact, except an answer of the Owner applied to OPERATIONS_RUNBOOK |
| Verification | Applies — hashes, links and table shape checked; the Owner's approval by hash |

Each dimension is recorded `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason (EXECUTION_CONTEXT section 13).

## 10. Stop conditions

- A change would need a new rule, page, control or state.
- The Owner has not answered a question; the Task stays BLOCKED on it.
- Merging `develop` into the branch would change an approved artifact, or a value of the package that the Owner has not approved again.
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- COPY_DECK
- The six P7-G1 documents of `docs/07-ux-design/` (only the rows and labels named)
- GLOSSARY (only its gate row, if the Owner wants it updated)
- OPERATIONS_RUNBOOK section 5 (only by the Owner's answer)
- OPEN_ITEMS T-19 and T-20
- DECISIONS (the Owner's approval, when given)
- SOURCE_OF_TRUTH section 3 (the P7 rows)
- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- GATE-CC1 unblocks TASK-005, TASK-009 and TASK-010 and every interface Task that uses a key added here.
- Tasks that depend on this one directly: [TASK-005](TASK-005-design-system.md), [TASK-009](TASK-009-command-client.md), [TASK-010](TASK-010-bootstrap-profile-media.md).

## 13. Expected files

- `docs/07-ux-design/COPY_DECK.md` and the six P7-G1 documents
- `docs/00-governance/` registers

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
