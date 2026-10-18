# TASK-035 — Onboarding and opening stock

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 7 Onboarding, reconciliation and operations · lane I, inventory and costing |
| Size and review | L · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-026](TASK-026-reversal.md), [TASK-034](TASK-034-drawer-refunds.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-035-onboarding` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5 at effort Max (D-066), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Inventory, Costing |
| Baseline | RF-7, D-090 |

## 1. Goal

The Owner's single onboarding period — start, opening stock in rows of at most 100 with zero counts on the checklist, corrections before the release (the withdrawal of a zero count; the reversal and one explicit replacement of an OPENING) and the final release — with the freeze that stops Sales and ordinary postings while it is active, and the restore hold that D-072 requires.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- INVENTORY section 6 (BR-INV-23 to BR-INV-30, BR-INV-47, BR-INV-48); D-071, D-072, D-087
- SCHEMA section 5.7 (`onboarding_state`, `onboarding_releases`, `opening_stock_entries`, `opening_stock_lines`, `onboarding_zero_counts`, `onboarding_zero_count_withdrawals`, `onboarding_restore_holds`, `onboarding_restore_reconciliations`), G-10, G-22, G-24 and G-26
- TECHNICAL_RULES section 7.2 (opening stock; onboarding set or release), RACE-04, RACE-06, RACE-31, RACE-32 and TR-TX-03
- INTERACTION_CONTRACTS RC-11 to RC-14, RC-48 and section 3.1 (the `opening` context)
- ROUTE_CONTRACTS RC-11 to RC-14, RC-48, RP-412 and RP-413
- SECURITY_MODEL CAP-15
- COPY_DECK sections 9.13 and 9.14 and the TASK-002 keys; UI_STATES UXS-27
- CRITICAL_FLOWS WF-24 and WF-25
- RELEASE_PROCESS RB-24

## 4. Scope

### 4.1 In scope

1. Create `opening_stock_entries`, `opening_stock_lines`, `onboarding_zero_counts`, `onboarding_zero_count_withdrawals`, `onboarding_restore_holds` and `onboarding_restore_reconciliations`; complete the keys `stock_movements.opening_line_id` → `opening_stock_lines` and the replacement keys of an opening line to its reversed original.
2. Implement RC-12 (start: the Owner; recent authentication; only from NOT_STARTED with no release record and no unreconciled hold) and RC-13 (release: only from ACTIVE without a hold; the release record makes it final).
3. Implement RC-11 (at most 100 rows, all or nothing; an original OPENING once per product with no earlier movement; zero counts on the checklist without a movement; one effective entry per product, G-22), RC-14 (withdrawal of a zero count: the Owner, recent authentication, a reason) and RC-48 (one explicit replacement of a reversed OPENING, once; G-24).
4. Extend the replay to OPENING; prove the reversal of an OPENING during onboarding through RC-10 (TASK-026).
5. Build RP-412 (the state — not started, active, released, held for restore reconciliation — and the checklist with its corrections) and RP-413 (the entry draft; RD-01 `opening` with the eligibility values of D-087; "Buat produk baru" over the page through RP-303).
6. Add the onboarding state to RD-03 and the banner UXS-27 on every page; prove the freeze (G-10) against every posting family: receiving, manual issue, adjustment, checkout, a saleable return and a reversal of a RECEIPT or MANUAL_ISSUE; prove RACE-04 for the start.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | `opening_stock_entries`, `opening_stock_lines`, `onboarding_zero_counts`, `onboarding_zero_count_withdrawals`, `onboarding_restore_holds`, `onboarding_restore_reconciliations` |
| Keys completed (EXC-44) | `stock_movements.opening_line_id` → `opening_stock_lines`, the replacement keys of `opening_stock_lines` to `stock_movements` and `reversals` |
| Routes | RC-11, RC-12, RC-13, RC-14, RC-48, RP-412, RP-413 |
| Route elements added | RD-01: `opening` context, RD-03: onboarding state |
| Pages | UXP-412 "Stok awal", UXP-413 "Input stok awal" |
| Controls | 19: UXI-016, UXI-030, UXI-228, UXI-285, UXI-286, UXI-287, UXI-288, UXI-289, UXI-290, UXI-291, UXI-292, UXI-293, UXI-294, UXI-295, UXI-296, UXI-297, UXI-298, UXI-299, UXI-300 |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- Setting and deciding a restore hold (OP-04, OP-05; TASK-037).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-INV-07 | REQ-INV-09, REQ-ACC-08, REQ-ACC-09 |
| Workflows | WF-24, WF-25 | — |
| User tasks | UXT-33 | — |
| Acceptance suites | OPEN01 (final: TASK-044) | AUTH03, INV03, REC02 |
| Races | RACE-06, RACE-31, RACE-32 | RACE-04 |
| Guards | G-10, G-22, G-24, G-26 | — |
| Threats | — | — |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-035-01** Only the Owner; the freeze refuses ordinary postings and Sales, also racing its start (RACE-04, G-10), for every posting family.
- **AC-035-02** At most 100 rows; a 101st or an invalid row refuses the whole command; an original OPENING once per product without an earlier movement; zero counts on the checklist without a movement (RACE-06).
- **AC-035-03** One effective entry per product: a zero count withdrawn, an OPENING reversed and replaced once, each by the Owner with a reason and recent authentication (RACE-31, G-22, G-24).
- **AC-035-04** One start and one release at most, also concurrently (RACE-32); a start after the release is refused (G-26).
- **AC-035-05** No database lock is held while people count (TR-TX-03).
- **AC-035-06** RP-412 and RP-413 follow the approved artifacts with the TASK-002 keys; UXS-27 appears on every page while onboarding is active.
- **AC-035-07** The constraint, deferred and privilege families pass for the six tables.

## 7. Tests

- L2 families for the six tables.
- L3 RACE-04 onboarding, RACE-06, RACE-31, RACE-32.
- L4 the commands and the freeze against every posting family.
- L5 RP-412 and RP-413.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-035.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

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

- TASK-037 sets and decides restore holds and proves the restore case of OPEN01.
- Tasks that depend on this one directly: [TASK-037](TASK-037-restore-tooling.md), [TASK-042](TASK-042-audit-views.md).

## 13. Expected files

- `app/Domain/Inventory/Onboarding/`, `app/Actions/Inventory/`
- `resources/js/pages/inventory/Onboarding`, `OpeningEntry`
- `database/migrations/`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
