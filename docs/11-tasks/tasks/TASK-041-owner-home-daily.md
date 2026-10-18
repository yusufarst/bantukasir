# TASK-041 — Owner home and daily summary

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 8 Reports and audit views · lane R, reports, audit views and release |
| Size and review | L · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-039](TASK-039-system-status.md), [TASK-040](TASK-040-finance-report.md), [TASK-032](TASK-032-assisted-close-notes.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-041-owner-home-daily` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5, at the effort that the Owner's instruction names for this Task (D-031, D-095), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Reporting |
| Baseline | RF-7, D-090 |

## 1. Goal

The Owner's home with today's figures, the open sessions and the attention items, and the daily summary computed once from source records.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- SALES_CASH section 9 (BR-RPT-01 to BR-RPT-04)
- INTERACTION_CONTRACTS section 3.4
- ROUTE_CONTRACTS RP-007 and RP-501
- KEY_SCREENS KS-02
- COPY_DECK sections 6.8 and 10.2
- INFORMATION_ARCHITECTURE UXP-007
- OPERATIONS_RUNBOOK section 5
- CRITICAL_FLOWS WF-28

## 4. Scope

### 4.1 In scope

1. Build RP-007 (KS-02 with its states): its panels as deferred props that fail on their own (UXS-22) — today's figures, the open sessions, the attention items including stock attention, open incidents, costs to complete and "Status sistem perlu dicek" (UXI-064); switch RN-01 to RP-007 for the Owner.
2. Build RP-501: one read-only snapshot; Sales by business date; refunds and returns by posting date; sessions listed with their variance separately; a session across midnight once with its full span; nothing counted twice; a date after today refused.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | — |
| Keys completed (EXC-44) | — |
| Routes | RP-007, RP-501 |
| Route elements added | RN-01: home of the Owner (RP-007) |
| Pages | UXP-007 "Beranda", UXP-501 "Rekap harian" |
| Controls | 12: UXI-001, UXI-007, UXI-018, UXI-059, UXI-060, UXI-061, UXI-062, UXI-063, UXI-064, UXI-065, UXI-306, UXI-307 |
| Controls extended with a destination | UXI-100 |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- Anything not listed in section 4.1 (EXECUTION_CONTEXT section 14).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-RPT-04 | REQ-RFD-07, REQ-NFR-09, REQ-SALE-10, REQ-RPT-05, REQ-OPS-04 |
| Workflows | WF-28 | — |
| User tasks | UXT-23, UXT-24, UXT-28 | — |
| Acceptance suites | SESSION09 (final: TASK-044) | UX01, STATUS01 |
| Races | — | — |
| Guards | — | G-18 |
| Threats | — | — |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-041-01** Totals are computed once from source records; a session across midnight appears once with its full span; nothing is counted twice.
- **AC-041-02** Two Sales at 23.59 and 00.01 Asia/Jakarta in one session fall on different business dates.
- **AC-041-03** Each Beranda panel loads and fails on its own; the attention items appear exactly when their evidence says so.
- **AC-041-04** KS-02 is reproduced with its states; RN-01 sends the Owner to Beranda; only the Owner reaches these pages.

## 7. Tests

- L2 daily summary fixtures across midnight.
- L4 access.
- L5 KS-02 states.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-041.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- Nothing beyond EXC-61.

## 9. Definition of Done

| Dimension | This Task |
| --- | --- |
| Business | Applies — the criteria of section 6 and the requirements of section 5 |
| UI and UX | Applies — the approved P7 artifacts for its pages and controls (EXC-50 to EXC-53) |
| Backend | N/A — no route or Action beyond those of section 4.2 |
| Database | N/A — no migration |
| Security | Applies — the threat paths of section 5, the refusals of the other roles and the combined account, no secret, the static checks |
| Data integrity | N/A — writes no business fact |
| Performance | Applies — bounded queries and paging; the budgets are measured at TASK-048 |
| Operations | N/A — no backup, runbook or status impact |
| Verification | Applies — the command contract green; self-review; the reviews of class A (EXECUTION_CONTEXT section 12); evidence recorded |

Each dimension is recorded `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason (EXECUTION_CONTEXT section 13).

## 10. Stop conditions

- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- —
- Tasks that depend on this one directly: [TASK-043](TASK-043-performance-harness.md).

## 13. Expected files

- `app/Queries/Reporting/`
- `resources/js/pages/reporting/Home`, `Daily`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
