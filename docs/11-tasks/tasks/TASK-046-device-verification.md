# TASK-046 — Device verification

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 9 Verification · lane V, verification |
| Size and review | M · review class V — verification results accepted by the Owner ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-044](TASK-044-integrated-verification.md) |
| Gates | DEVICES (the shop's devices available), TARGET (staging), The operator named by the Owner ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-046-device-verification` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5, at the effort that the Owner's instruction names for this Task (D-031, D-095), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None. |
| Modules | All interface |
| Baseline | RF-7, D-090 |

## 1. Goal

The protocol of DEVICE_VERIFICATION on the shop's real PC, tablet, scanners, printers and browsers against the candidate on staging, with the manual accessibility checks and the confirmation or change of the provisional shortcuts and breakpoints.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- DEVICE_VERIFICATION (all)
- ACCEPTANCE_SUITES DEVICE01 and UX02
- ADAPTIVE_AND_ACCESSIBILITY UXA-02, UXA-09, UXA-13, UXA-14 and UXA-23
- DESIGN_SYSTEM sections 11 and 12
- DOCUMENT_CONTRACTS DC-01 to DC-05

## 4. Scope

### 4.1 In scope

1. Record the devices of DEVICE_VERIFICATION section 2.
2. Run every check DVT-01 to DVT-40 that applies, with a photo or scan of every printout the check names, and record a check that does not apply with its reason.
3. Run the manual parts of UX02: the screen-reader pass (DVT-32) and the on-screen keyboard with a connected scanner (DVT-11).
4. Record the outcome of DVT-R4 — the provisional shortcuts and breakpoints confirmed, or the replacements needed — for TASK-047.

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

- Changes to shortcuts or breakpoints (TASK-047).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-NFR-02, REQ-NFR-03, REQ-UX-03 | REQ-SCAN-01, REQ-CAT-11, REQ-DOC-02, REQ-CASH-12 |
| Workflows | — | — |
| User tasks | — | — |
| Acceptance suites | DEVICE01 (final: TASK-046) | UX02 |
| Races | — | — |
| Guards | — | — |
| Threats | — | — |
| Open items | T-20 — the shortcuts and breakpoints that gate DEVICES confirms or changes (outcome for TASK-047) | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-046-01** DEVICE01 is `FINAL_PASS`: every applicable check passes on the shop's devices with the candidate on staging; every non-applicable check has its reason.
- **AC-046-02** The manual accessibility checks of UX02 are recorded.
- **AC-046-03** The DVT-R4 outcome is recorded in the form TASK-047 needs.

## 7. Tests

- DEVICE_VERIFICATION section 3.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-046.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The device record and the printouts, summarized without identities.

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

- A device fails a check whose fix needs a business decision.
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-047 applies the DVT-R4 outcome; the Owner decides gate DEVICES.
- Tasks that depend on this one directly: [TASK-047](TASK-047-p7-change-control-2.md).

## 13. Expected files

- The evidence file

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
