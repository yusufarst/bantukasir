# TASK-027 — Cash registers and cash session open

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 6 Cash and sales · lane S, cash and sales |
| Size and review | M · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-011](TASK-011-user-management.md), [TASK-022](TASK-022-receiving-stock-pages.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-027-registers-session-open` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5, at the effort that the Owner's instruction names for this Task (D-031, D-095), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Cash |
| Baseline | RF-7, D-090 |

## 1. Goal

The Owner's configuration of cash registers and the opening of a cash session by its responsible user, with an explicit opening amount, the session number and the rules of one OPEN session per register and per user.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- SALES_CASH section 6 (BR-CASH-01 to BR-CASH-10)
- SCHEMA section 5.10 (`cash_registers`, `cash_sessions`), section 6 (SCH-20 to SCH-28), G-18, G-19 and G-23
- TECHNICAL_RULES section 7.2 (open cash session; cash register configuration), RACE-07, RACE-26 and TR-TX-10
- INTERACTION_CONTRACTS RC-02, RC-33 and RC-34
- ROUTE_CONTRACTS RC-02, RC-33, RC-34, RP-104, RP-609 and RP-610
- SECURITY_MODEL CAP-21 and CAP-31
- COPY_DECK sections 7.4, 11.9 and 11.10
- KEY_SCREENS KS-06
- CRITICAL_FLOWS WF-04 and WF-30

## 4. Scope

### 4.1 In scope

1. Create `cash_registers` and `cash_sessions` (class FS, with its `close_presence` key completed by TASK-030) and the sequence `cash_session_number_seq` (`CACHE 1`, `NO CYCLE`; runtime `USAGE` only).
2. Implement RC-33 and RC-34: the Owner; recent authentication; register codes unique without regard to letter case and the label limits; a change refused while a session is OPEN on the register (`VALIDATION_FAILED`, as INTERACTION_CONTRACTS RC-34 reads it); audit.
3. Implement RC-02: the register's update lock; the register active; no OPEN session on the register or for the user; a BusinessProfile revision exists; an explicit opening amount of zero or more (blank refused, Rp0 valid); the number `YYYYMMDD-n` and the business date taken at step 8 (SCH-21, G-18); atomic, creating no Sale and no movement.
4. Build RP-609, RP-610 and RP-104 (KS-06 with its states), the settings index entry for registers, the OPEN-session mark of UXP-602 and its entries; switch RN-01 to RP-104 for a cashier without an OPEN session until TASK-029 provides RP-101. The "Batal" of UXP-104 (UXI-100) leads to the role's non-till home — UXP-401 for the combined account now, UXP-201 for a cashier from TASK-031 and UXP-007 for the Owner from TASK-041, RN-01 until then (EXC-52).

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | `cash_registers`, `cash_sessions` |
| Keys completed (EXC-44) | — |
| Routes | RC-02, RC-33, RC-34, RP-104, RP-609, RP-610 |
| Route elements added | RN-01: home of a cashier without an OPEN session (RP-104) |
| Pages | UXP-104 "Buka sesi kas", UXP-609 "Laci kas", UXP-610 "Form laci kas" |
| Controls | 16: UXI-002, UXI-094, UXI-095, UXI-096, UXI-097, UXI-098, UXI-099, UXI-100, UXI-328, UXI-367, UXI-368, UXI-369, UXI-370, UXI-371, UXI-372, UXI-373 |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- Closing a session (TASK-030).
- The till (TASK-029).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-CASH-01, REQ-CASH-02, REQ-CASH-03, REQ-CFG-04 | REQ-CASH-10, REQ-ACC-09 |
| Workflows | WF-04, WF-30 | — |
| User tasks | UXT-02, UXT-36, UXT-37 | — |
| Acceptance suites | SESSION02 (final: TASK-044), SESSION03 (final: TASK-044), CONFIG01 (final: TASK-044) | AUTH03 |
| Races | RACE-07, RACE-26 | RACE-04 |
| Guards | G-19 | G-18, G-23 |
| Threats | — | — |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-027-01** The two partial unique indexes hold; concurrent opens of one register or by one user give one success (RACE-07).
- **AC-027-02** No shared drawer, transfer of responsibility, paid-in, paid-out or cash pickup exists in the route and command inventory.
- **AC-027-03** A blank opening amount is refused; Rp0 is accepted; the open is atomic and creates no Sale or movement; a revision exists from the bootstrap (G-23).
- **AC-027-04** Registers are created and changed with recent authentication and audit; a change is refused while a session is OPEN on the register, also against a concurrent open (RACE-26); codes are unique without regard to letter case.
- **AC-027-05** Session numbers follow allocation order, do not wait on each other, leave a gap after a rollback and carry the right date part across Asia/Jakarta midnight (numbering family).
- **AC-027-06** KS-06 is reproduced with its states; the pages pass the accessibility checks.

## 7. Tests

- L2 families for the two tables and the sequence.
- L3 RACE-07, RACE-26 and the numbering family.
- L4 commands and the capability matrix.
- L5 KS-06, RP-609, RP-610.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-027.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

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

- TASK-028 sells in these sessions; TASK-030 closes them.
- Tasks that depend on this one directly: [TASK-028](TASK-028-checkout.md).

## 13. Expected files

- `app/Domain/Cash/`, `app/Actions/Cash/`, `app/Http/Controllers/Cash/`
- `resources/js/pages/cash/`
- `database/migrations/`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
