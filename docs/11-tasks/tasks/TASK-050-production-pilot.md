# TASK-050 — Production release, cutover and pilot acceptance

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 10 Release · lane R, reports, audit views and release |
| Size and review | L · review class V — verification results accepted by the Owner ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-049](TASK-049-release-readiness.md) |
| Gates | RELEASE (the Owner's exact authorization), GATE-PUBLISH (the merge into `main` and the tag), G4 (accepted before the cutover) ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-050-production-pilot` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5, at the effort that the Owner's instruction names for this Task (D-031, D-095), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None. |
| Modules | All |
| Baseline | RF-7, D-090 |

## 1. Goal

The authorized production release of V1 by the operator, the first installation in production with the Owner's setup, the first production backup and drill for gate G4, the cutover of master data and opening stock, a pilot of at least 7 trading days and the Owner's written acceptance.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- RELEASE_PROCESS sections 1, 2, 6 to 11 (RB-20, RB-21, RB-23 steps 7 to 11, RB-24) and 12
- UAT_PILOT section 3
- BACKUP_RECOVERY section 12 and RB-02
- OPERATIONS_RUNBOOK sections 8 and 9
- ACCEPTANCE_SUITES RELEASE01
- ADR-011 item 3
- EXECUTION_CONTEXT section 16

## 4. Scope

### 4.1 In scope

1. With the Owner's authorizations: bring the final candidate into `main` and tag `v1.0.0` (ADR-011 item 3), after the publication guard; the release workflow publishes the image.
2. Operator: RB-20 in production with the migration job; RB-21; OP-01 `owner:bootstrap` with the shop's name and the Owner's email; the link handed to the Owner directly.
3. Owner: the password, the BusinessProfile, the cash registers, the staff accounts with their links (D-056).
4. Operator: the first production backup and the first production drill, recorded with OP-08; the Owner accepts the measured recovery point and time (gate G4, REQ-NFR-05).
5. Owner with staff: the cutover RB-24 — onboarding started and released once (D-071).
6. The pilot of at least 7 consecutive trading days and its criteria PIL-01 to PIL-11.
7. Write the release record `docs/10-release/records/RELEASE-v1.0.0.md` from the operator's report without real identities, creating the folder; record the Owner's written acceptance of the pilot in DECISIONS.

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
| Requirements | REQ-OPS-03 | REQ-NFR-05, REQ-OPS-01 |
| Workflows | — | — |
| User tasks | — | — |
| Acceptance suites | RELEASE01 (final: TASK-050) | REC01 |
| Races | — | — |
| Guards | — | — |
| Threats | — | — |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-050-01** The release is carried out by the operator under the Owner's exact authorization, and recorded.
- **AC-050-02** Gate G4 is accepted by the Owner on the measured production values before the cutover.
- **AC-050-03** PIL-01 to PIL-11 hold; RELEASE01 is `FINAL_PASS`; the Owner's written acceptance is in DECISIONS.

## 7. Tests

- RELEASE01 (PIL-01 to PIL-11).
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-050.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The release record.
- The pilot records, summarized without identities.

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

- An S1 incident (UAT_PILOT section 3).
- Any step not covered by the Owner's authorization.
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- `docs/10-release/records/` (created)
- DECISIONS (the Owner's acceptance)
- OPEN_ITEMS section 3 (G4, RELEASE)
- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- After the pilot, V1 is in production; later work follows change control and releases.

## 13. Expected files

- The release record
- The evidence file

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
