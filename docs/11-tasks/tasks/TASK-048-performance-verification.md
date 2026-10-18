# TASK-048 — Performance verification

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 9 Verification · lane V, verification |
| Size and review | M · review class V — verification results accepted by the Owner ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-044](TASK-044-integrated-verification.md), [TASK-043](TASK-043-performance-harness.md) |
| Gates | TARGET (the intended server before production data exist), G2 (when known, for the final dataset), The operator named by the Owner ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-048-performance-verification` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5, at the effort that the Owner's instruction names for this Task (D-031, D-095), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None. |
| Modules | All |
| Baseline | RF-7, D-090 |

## 1. Goal

PERF01 on the intended server with the provisional dataset, or the final one when gate G2 gives the catalogue's size, with the budgets of TECHNICAL_RULES section 13.1 measured and accepted or investigated, never by weakening integrity, privacy or cost completeness.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- TECHNICAL_RULES section 13
- ACCEPTANCE_SUITES PERF01
- ENVIRONMENTS sections 2, 5 and 17 (item 8)
- OPEN_ITEMS T-09
- ADR-004 item 7
- The evidence of TASK-043

## 4. Scope

### 4.1 In scope

1. The operator runs the TASK-043 harness on the intended server — on staging or a temporary environment, never production — and reports.
2. Measure and record every budget; benchmark the Argon2id parameters on the target (ADR-004 item 7); verify the worker pool and the timeouts of TR-TIME-03; measure the trigram index.
3. When gate G2 gives the catalogue's size, set the final dataset (T-09); otherwise state that the provisional one was used.
4. Handle a miss through change control; never weaken integrity, privacy or cost completeness.

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
| Requirements | REQ-NFR-04 | REQ-COST-06 |
| Workflows | — | — |
| User tasks | — | — |
| Acceptance suites | — | — |
| Races | — | — |
| Guards | — | — |
| Threats | — | — |
| Open items | T-09 — the final dataset when gate G2 is known | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-048-01** PERF01 is `FINAL_PASS`, or each miss is recorded with its change control; the Owner accepts the measured budgets, which were proposals until then (REQ-NFR-04).
- **AC-048-02** The Argon2id parameters, the worker pool and the timeouts are set from the measurements and recorded.

## 7. Tests

- PERF01 at L7 on the intended server.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-048.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The run records of TECHNICAL_RULES section 13.2.

## 9. Definition of Done

| Dimension | This Task |
| --- | --- |
| Business | Applies — the suites and criteria of section 6 at their final point |
| UI and UX | Applies where the suites of section 5 touch the interface |
| Backend | Applies through the suites; no new code unless change control adds a Task (EXC-82) |
| Database | Applies through the suites; no new migration |
| Security | Applies — no agent reaches staging or production (EXC-13); no identity or secret in evidence |
| Data integrity | Applies through the suites |
| Performance | Applies only where PERF01 is in scope |
| Operations | Applies — operator steps prepared and recorded (EXECUTION_CONTEXT section 16) |
| Verification | Applies — `FINAL_PASS` recorded per TS-30 for every suite of this final point |

Each dimension is recorded `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason (EXECUTION_CONTEXT section 13).

## 10. Stop conditions

- A budget can only be met by weakening a rule.
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- TECHNICAL_RULES section 13 (the accepted budgets, by the Owner's decision)
- OPEN_ITEMS T-09
- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- —
- Tasks that depend on this one directly: [TASK-049](TASK-049-release-readiness.md).

## 13. Expected files

- The evidence file

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
