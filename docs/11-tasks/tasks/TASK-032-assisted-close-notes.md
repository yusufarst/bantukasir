# TASK-032 — Owner-assisted close and session review notes

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 6 Cash and sales · lane S, cash and sales |
| Size and review | M · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-030](TASK-030-session-close.md), [TASK-011](TASK-011-user-management.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-032-assisted-close-notes` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5 at effort Max (D-066), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Cash |
| Baseline | RF-7, D-090 |

## 1. Goal

The Owner's close of another user's session — with a count, a reason, recent authentication and the responsible user's unresolved checkouts resolved first — and the Owner's append-only review notes on closed sessions.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- SALES_CASH BR-CASH-11, BR-CASH-16, BR-CASH-18 and BR-CASH-19
- SCHEMA section 5.10 (`cash_session_review_notes`) and G-19
- TECHNICAL_RULES section 7.2 (close; Owner review note), RACE-09 and RACE-28
- INTERACTION_CONTRACTS RC-04, RC-43 and section 2.5
- ROUTE_CONTRACTS RC-04, RC-43 and RP-207
- SECURITY_MODEL CAP-22, CAP-23 and SEC-OP-03
- D-068, D-069 (d) and (f), D-087
- COPY_DECK sections 7.11 and 7.12
- CRITICAL_FLOWS WF-09 and WF-10

## 4. Scope

### 4.1 In scope

1. Create `cash_session_review_notes`.
2. Implement RC-04: the Owner; recent authentication; a reason; a count; the responsible user unchanged; the close actor recorded; G-27 applies as for RC-03.
3. Build RP-207: the close preview with the responsible user's unresolved checkout receipts and the Owner's "Cek status" (CAP-34); "Lanjut hitung" disabled while one is listed.
4. Implement RC-43: the Owner; a CLOSED session under a share lock; append-only; several notes per session; never edited.
5. Add the Owner's sections of RP-206 — review notes and, once TASK-034 exists, the drawer-funded refunds beside the variance — and the entries from RP-206 and RP-604.
6. Prove the cash-session parts of deactivation (D-069 (d)) and of OP-03 (D-087): the session stays OPEN and unchanged until the Owner-assisted close.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | `cash_session_review_notes` |
| Keys completed (EXC-44) | — |
| Routes | RC-04, RC-43, RP-207 |
| Route elements added | — |
| Pages | UXP-207 "Tutup sesi oleh Pemilik" |
| Controls | 12: UXI-140, UXI-146, UXI-149, UXI-150, UXI-151, UXI-152, UXI-153, UXI-154, UXI-155, UXI-156, UXI-157, UXI-158 |
| Controls extended with a destination | UXI-148, UXI-340 |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- Drawer-funded refunds (TASK-034).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-CASH-11 | REQ-CASH-09, REQ-CASH-12, REQ-RPT-05, REQ-ACC-10, REQ-ACC-12 |
| Workflows | WF-09, WF-10 | — |
| User tasks | UXT-25, UXT-26 | — |
| Acceptance suites | SESSION07 (final: TASK-044), SESSION10 (final: TASK-044) | SESSION05, ACC01, OPPROC01 |
| Races | RACE-09, RACE-28 | RACE-35 |
| Guards | — | G-19, G-27 |
| Threats | — | — |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-032-01** The Owner-assisted close needs the capability, recent authentication, a count and a reason; the responsible user stays and the close actor is recorded; RACE-09 against the user's own close gives one CLOSED session.
- **AC-032-02** The close is refused while the responsible user has an unresolved checkout receipt; after the Owner's status check settles it, the close succeeds.
- **AC-032-03** A session of a deactivated user, or of an Owner replaced by OP-03, stays OPEN and unchanged until the Owner-assisted close (RACE-28).
- **AC-032-04** Review notes are append-only, several per session, and change nothing in the report.
- **AC-032-05** RP-207 and the Owner's sections of RP-206 follow the approved artifacts.

## 7. Tests

- L2 families for the notes table.
- L3 RACE-09 and RACE-28.
- L4 RC-04 and RC-43.
- L5 RP-207.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-032.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- Nothing beyond EXC-61.

## 9. Definition of Done

| Dimension | This Task |
| --- | --- |
| Business | Applies — the criteria of section 6 and the requirements of section 5 |
| UI and UX | Applies — the approved P7 artifacts for its pages and controls (EXC-50 to EXC-53) |
| Backend | Applies — FormRequest allowlists, policies and object scope, in-transaction guards, DTO allowlists, L4 tests |
| Database | Applies — migrations reviewed and rehearsed (EXC-41 to EXC-46); the constraint, deferred, privilege and retention families for its tables |
| Security | Applies — the threat paths of section 5, the refusals of the other roles and the combined account, no secret, the static checks |
| Data integrity | Applies — append-only facts, atomic audit and result, identity and idempotency, the races and the failure injection of section 5 |
| Performance | Applies — bounded queries and paging; the budgets are measured at TASK-048 |
| Operations | Applies — its tables in the backup manifest's counts and any runbook or status impact (EXC-47) |
| Verification | Applies — the command contract green; self-review; the reviews of class A (EXECUTION_CONTEXT section 12); evidence recorded |

Each dimension is recorded `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason (EXECUTION_CONTEXT section 13).

## 10. Stop conditions

- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-034 shows drawer-funded refunds on RP-206.
- Tasks that depend on this one directly: [TASK-034](TASK-034-drawer-refunds.md), [TASK-041](TASK-041-owner-home-daily.md).

## 13. Expected files

- `app/Actions/Cash/CloseCashSessionAssisted`, `app/Actions/Cash/AddReviewNote`
- `resources/js/pages/cash/AssistedClose`
- `database/migrations/`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
