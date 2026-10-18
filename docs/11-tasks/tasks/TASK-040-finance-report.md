# TASK-040 — Finance report

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 8 Reports and audit views · lane R, reports, audit views and release |
| Size and review | L · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-034](TASK-034-drawer-refunds.md), [TASK-025](TASK-025-cost-correction.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-040-finance-report` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5 at effort Max (D-066), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Reporting |
| Baseline | RF-7, D-090 |

## 1. Goal

The Owner's finance report from one consistent snapshot — gross sales, refunds, net sales, the number of Sales, payments by method and refund payouts, quantities, cost of goods sold, gross profit and margin only when cost is complete, the completeness counts and the trend — over the decided periods.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- COSTING section 9 (BR-COST-34 to BR-COST-41)
- SALES_CASH section 9
- ARCHITECTURE ARC-24; ADR-008 section 6
- TECHNICAL_RULES TR-TX-07, sections 11.4 and 13.1
- INTERACTION_CONTRACTS section 3.4
- ROUTE_CONTRACTS RP-502
- SECURITY_MODEL CAP-20
- KEY_SCREENS KS-10
- DESIGN_SYSTEM sections 8.12 and 8.13; UXA-19
- COPY_DECK sections 10.1 and 10.3 and the TASK-002 keys and percent format
- CRITICAL_FLOWS WF-28; D-018

## 4. Scope

### 4.1 In scope

1. Implement the report query in one read-only REPEATABLE READ transaction whose snapshot is the as-of time; the periods `today`, `7d`, `this_month`, `last_month` and `custom` as half-open Asia/Jakarta ranges, with the custom refusals (start after end, end after today, longer than 366 days); figures from the allocation projections, never the facts recorded at commit; the count of lines with UNKNOWN cost and of products with an active mark; margin only when cost is complete; never net profit.
2. Build RP-502 (KS-10 with its states): the period choice, completeness, figures, and the trend — one series, net sales per business day — reachable with the keyboard and shown as a table on request; the percent format of TASK-002.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | — |
| Keys completed (EXC-44) | — |
| Routes | RP-502 |
| Route elements added | — |
| Pages | UXP-502 "Laporan keuangan" |
| Controls | 6: UXI-020, UXI-308, UXI-309, UXI-310, UXI-311, UXI-312 |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- Anything not listed in section 4.1 (EXECUTION_CONTEXT section 14).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-RPT-01, REQ-RPT-02, REQ-RPT-03, REQ-RPT-06, REQ-COST-09 | REQ-COST-04, REQ-NFR-09, REQ-RFD-07 |
| Workflows | — | WF-28 |
| User tasks | — | — |
| Acceptance suites | — | FIN03, FIN02, UX01, UX02 |
| Races | — | — |
| Guards | — | G-13 |
| Threats | — | THR-02 |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-040-01** The report comes from one snapshot with its as-of time, time zone, completeness and trend; a failed query shows UXS-22, never zero.
- **AC-040-02** The periods are half-open Asia/Jakarta ranges; the custom-range refusals happen at the date fields and fetch nothing.
- **AC-040-03** Margin is shown only when cost is complete; "Data biaya belum lengkap" with the counts of lines with unknown cost and of marked products otherwise; never net profit.
- **AC-040-04** After a cost correction the report shows corrected figures, also for earlier periods; refunds are reported on their posting date.
- **AC-040-05** KS-10 is reproduced with its states; the chart meets UXA-19; only the Owner reaches the report.

## 7. Tests

- L2 report figures on fixtures with corrections and returns.
- L4 period validation and access.
- L5 KS-10 and the chart.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-040.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

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

- TASK-041 reuses the period functions and today's figures.
- Tasks that depend on this one directly: [TASK-041](TASK-041-owner-home-daily.md).

## 13. Expected files

- `app/Queries/Reporting/FinanceReport`
- `resources/js/pages/reporting/Finance`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
