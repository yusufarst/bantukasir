# TASK-047 — P7 change control, batch 2: device results

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 9 Verification · lane D, documents and change control |
| Size and review | S · review class O — the Owner approves the artifacts by hash ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-046](TASK-046-device-verification.md) |
| Gates | GATE-CC2 (only when an approved artifact changes) ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-047-p7-change-control-2` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5 at effort Max (D-066), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None. |
| Modules | Documents; the interface only if values change |
| Baseline | RF-7, D-090 |

## 1. Goal

Close the rest of OPEN_ITEMS T-20: confirm the provisional shortcuts and breakpoints that gate DEVICES verified, or change them in the approved artifacts and in the implementation through change control, and verify again what the change touches.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- DEVICE_VERIFICATION DVT-R4 and the evidence of TASK-046
- ADAPTIVE_AND_ACCESSIBILITY UXA-02 and UXA-09
- DESIGN_SYSTEM section 11
- COPY_DECK section 5.1
- D-075
- PROJECT_RULES section 6

## 4. Scope

### 4.1 In scope

1. If gate DEVICES confirmed the provisional values: record the confirmation for the Owner's decision that ends their provisional status (D-075), and close T-20.
2. If it changed them: amend ADAPTIVE_AND_ACCESSIBILITY, DESIGN_SYSTEM and the accessible names of COPY_DECK, for the Owner's approval by hash (GATE-CC2); change the shortcut registry and the breakpoints in the implementation; cut a new candidate; run UX01, UX02, POS04 and the DVT checks the change touches again (TS-32).

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

- Anything not listed in section 4.1 (EXECUTION_CONTEXT section 14).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | — | REQ-UX-03, REQ-NFR-02 |
| Workflows | — | — |
| User tasks | — | — |
| Acceptance suites | — | UX01, UX02, POS04, DEVICE01 |
| Races | — | — |
| Guards | — | — |
| Threats | — | — |
| Open items | T-20 — resolved here: the shortcuts and breakpoints of gate DEVICES | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-047-01** OPEN_ITEMS T-20 is closed, either by the recorded confirmation or by changes the Owner approved by hash.
- **AC-047-02** Every suite a change touches is `FINAL_PASS` again on the new candidate.

## 7. Tests

- Only when values change: UX01, UX02, POS04 and the DVT checks touched.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-047.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The Owner's decision and, if values changed, the hashes.

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

- A change would need a new rule or page.
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- ADAPTIVE_AND_ACCESSIBILITY, DESIGN_SYSTEM, COPY_DECK (only if values change)
- OPEN_ITEMS T-20
- DECISIONS (the Owner's decision)
- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-049 uses the final candidate.
- Tasks that depend on this one directly: [TASK-049](TASK-049-release-readiness.md).

## 13. Expected files

- The documents named; the shortcut registry and breakpoints only if values change

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
