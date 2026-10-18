# TASK-034 — Drawer-funded refunds and the refund pages

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 6 Cash and sales · lane S, cash and sales |
| Size and review | L · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-033](TASK-033-refunds-core.md), [TASK-032](TASK-032-assisted-close-notes.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-034-drawer-refunds` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5 at effort Max (D-066), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Sales, Cash |
| Baseline | RF-7, D-090; corrected under D-091 |

## 1. Goal

The drawer-funded cash refund — from one OPEN session's drawer, bounded by its expected cash, raising its activity version, with the cash taken only after commit — and the refund pages for every source, including the late handover of D-068.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- SALES_CASH BR-RFD-03 to BR-RFD-09 and BR-RFD-14 to BR-RFD-17, BR-CASH-07, BR-CASH-14 and BR-CASH-16; D-035, D-045, D-046, D-068
- SCHEMA G-05
- TECHNICAL_RULES section 7.2 (drawer-funded refund or return), RACE-13, RACE-14 and RACE-15
- INTERACTION_CONTRACTS RC-05 and section 2.6
- ROUTE_CONTRACTS RC-05, RP-203 and RP-204
- COPY_DECK sections 7.8, 7.9 and 14.1
- UAT_PILOT UG-07
- CRITICAL_FLOWS WF-11

## 4. Scope

### 4.1 In scope

1. Implement RC-05 on the correction engine of TASK-033: the Owner; recent authentication; a reason; the selected OPEN session under its update lock (activity version + 1); the original Sale's guard row; the amount at most the session's expected cash computed under its lock (`DRAWER_CASH_INSUFFICIENT`; reaching Rp0 is allowed); the source never switched or split; a CLOSED session refused `SESSION_NOT_OPEN`; the payout reference `<Sale number>/<k>`.
2. List the drawer-funded refunds separately in the session report (DC-02) and on RP-206 for the Owner.
3. Build RP-203 (the lines with their remaining bounds; the source — an OPEN session's drawer with its expected cash, "Dana Pemilik" or transfer; review; recent authentication) and RP-204 (the committed correction; for a drawer-funded refund the instruction to take the cash now from the named drawer first, D-068); add the linked corrections of RP-202 for the Owner (UXI-120).
4. Apply RACE-15 in the interface: an uncertain refund is resolved before any cash is taken and is never replaced.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | — |
| Keys completed (EXC-44) | — |
| Routes | RC-05, RP-203, RP-204 |
| Route elements added | — |
| Pages | UXP-203 "Pengembalian uang dan retur", UXP-204 "Pengembalian tercatat" |
| Controls | 14: UXI-118, UXI-122, UXI-123, UXI-124, UXI-125, UXI-126, UXI-127, UXI-128, UXI-129, UXI-130, UXI-131, UXI-132, UXI-133, UXI-134 |
| Controls extended with a destination | UXI-056 |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- Anything not listed in section 4.1 (EXECUTION_CONTEXT section 14).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-RFD-03, REQ-RFD-04, REQ-RFD-08, REQ-RFD-09 | REQ-CASH-13, REQ-CASH-05, REQ-RFD-07 |
| Workflows | WF-11 | — |
| User tasks | UXT-27 | — |
| Acceptance suites | RET02 (final: TASK-044) | SESSION04, SESSION05, UX01 |
| Races | RACE-13, RACE-14, RACE-15 | — |
| Guards | G-05 | — |
| Threats | — | — |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-034-01** For CASH the Owner chooses one OPEN session's drawer or "Dana Pemilik"; a BANK_TRANSFER refund affects no drawer.
- **AC-034-02** A drawer-funded refund reduces that session's expected cash, appears separately in its report with the Owner as actor and increases the activity version; a CLOSED session is refused.
- **AC-034-03** With expected cash Rp80.000, a Rp100.000 drawer refund is refused `DRAWER_CASH_INSUFFICIENT` with nothing written; Rp80.000 leaves Rp0; two racing refunds never take it below zero (RACE-14).
- **AC-034-04** No cash before commit: a refusal comes before any cash leaves; an uncertain refund is resolved before cash is taken and never replaced (RACE-15); RACE-13 gives its outcome in both orders.
- **AC-034-05** The scenario of D-068 closes the session with variance +R and nothing else; a payout against the CLOSED session stays refused; the payout reference is `<Sale number>/<k>`.
- **AC-034-06** With a drawer-funded refund of Rp10.000 added to the SESSION04 example, expected cash is Rp110.000 and Rp108.000 counted gives −Rp2.000; a drawer refund after the close preview makes the close refuse `RECOUNT_REQUIRED`.
- **AC-034-07** RP-203 and RP-204 follow the approved artifacts.

## 7. Tests

- L3 RACE-13, RACE-14, RACE-15.
- L2 the D-068 scenario.
- L4 RC-05.
- L5 RP-203 and RP-204.
- Adds the drawer-funded refund to the L5 critical paths that every later pull request runs (EXECUTION_CONTEXT EXC-34).
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-034.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- Nothing beyond EXC-61.

## 9. Definition of Done

| Dimension | This Task |
| --- | --- |
| Business | Applies — the criteria of section 6 and the requirements of section 5 |
| UI and UX | Applies — the approved P7 artifacts for its pages and controls (EXC-50 to EXC-53) |
| Backend | Applies — FormRequest allowlists, policies and object scope, in-transaction guards, DTO allowlists, L4 tests |
| Database | N/A — no migration |
| Security | Applies — the threat paths of section 5, the refusals of the other roles and the combined account, no secret, the static checks |
| Data integrity | Applies — append-only facts, atomic audit and result, identity and idempotency, the races and the failure injection of section 5 |
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
- Tasks that depend on this one directly: [TASK-035](TASK-035-onboarding.md), [TASK-036](TASK-036-reconciliation-repair.md), [TASK-040](TASK-040-finance-report.md).

## 13. Expected files

- `app/Actions/Sales/RecordDrawerFundedCorrection`
- `resources/js/pages/sales/Refund`, `RefundRecorded`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
