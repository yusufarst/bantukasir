# TASK-044 — Release candidate and integrated verification

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 9 Verification · lane V, verification |
| Size and review | L · review class V — verification results accepted by the Owner ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-043](TASK-043-performance-harness.md) |
| Gates | TARGET (the staging part), GATE-PUBLISH (the candidate tag) ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-044-integrated-verification` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5, at the effort that the Owner's instruction names for this Task (D-031, D-095), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | All |
| Baseline | RF-7, D-090 |

## 1. Goal

Cut the first release candidate and record `FINAL_PASS` for every suite whose final point is integrated verification, at levels L1 to L5, in CI and on staging, so that the operational, device and performance verification run on a known, fully tested revision (FD-07).

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- TEST_STRATEGY sections 7 to 12
- ACCEPTANCE_SUITES (all)
- RELEASE_PROCESS section 3
- TRACEABILITY (all sections)
- EXECUTION_CONTEXT sections 10 to 13 and 17

## 4. Scope

### 4.1 In scope

1. Prepare the candidate on `develop`: release notes listing every change, every migration with its class and every Task it closes; the candidate tag pushed only with its authorization and after the publication guard (REL-10).
2. Run every L1 to L4 case in CI on the candidate, and the L5 cases in CI and, where a real proxy or browser behaviour matters (the content security policy, the client address), against staging deployed by the operator with the candidate image.
3. Run UX01 in full: every page for every role and the combined account against the approved artifacts; every control routed as INTERACTION_CONTRACTS section 9 says; no dead control; every state reachable with its wording; the key screens against their previews; the widths of KEY_SCREENS section 4 and 1440 px.
4. Run the automated part of UX02, L10N01 and the complete capability matrix of AUTH01.
5. Check TRACEABILITY against the evidence: every requirement, race, guard, threat and workflow has its passing cases; report any gap.
6. Handle a defect only through change control (EXC-82): a fix Task, a new candidate and the suites it touches run again.

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

- Operational, device and performance suites (TASK-045, TASK-046, TASK-048).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-UX-01, REQ-UX-02, REQ-CMD-02, REQ-NFR-08, REQ-SCOPE-01 | REQ-UX-03, REQ-SCOPE-02 |
| Workflows | — | — |
| User tasks | — | — |
| Acceptance suites | — | — |
| Races | — | — |
| Guards | — | — |
| Threats | — | — |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-044-01** Every suite whose final point is "Integrated verification" in ACCEPTANCE_SUITES section 3 is `FINAL_PASS` on the candidate, with evidence per TS-30.
- **AC-044-02** UX01 finds no control without behaviour, no page or state that departs from the approved artifacts, and nothing outside V1 that looks active.
- **AC-044-03** TRACEABILITY's checks hold against the evidence; no open S1 or S2 defect remains.
- **AC-044-04** The candidate, its tag and its image digest are recorded.

## 7. Tests

- Every L1 to L5 suite of the integrated final point.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-044.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- Per suite: cases, revision, digest, environment, dataset, commands, results (TS-30).

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

- A defect needs a business decision.
- Staging is not available (gate TARGET); the staging part stays BLOCKED.
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- RELEASE_PROCESS (only the release notes' location, if needed)
- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-045, TASK-046 and TASK-048 verify this candidate.
- Tasks that depend on this one directly: [TASK-045](TASK-045-operations-verification.md), [TASK-046](TASK-046-device-verification.md), [TASK-048](TASK-048-performance-verification.md).

## 13. Expected files

- The release notes
- The evidence file

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
