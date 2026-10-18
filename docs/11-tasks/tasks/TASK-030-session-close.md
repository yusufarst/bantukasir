# TASK-030 — Cash session close and session pages

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 6 Cash and sales · lane S, cash and sales |
| Size and review | L · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-029](TASK-029-till-pages.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-030-session-close` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5 at effort Max (D-066), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Cash, Sales (totals query) |
| Baseline | RF-7, D-090; corrected under D-091 |

## 1. Goal

The responsible user's close of an own cash session — preview, physical count outside the transaction, atomic close with the immutable session report, a recount on any change, refusal while a checkout is unresolved — and the session list and detail pages.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- SALES_CASH sections 6 and 7 (BR-CASH-11 to BR-CASH-22)
- SCHEMA section 5.10 (`cash_session_closes`, `cash_session_reports`), G-05, G-17, G-19 and G-27
- TECHNICAL_RULES section 7.2 (close), RACE-08, RACE-09, RACE-13 and RACE-35
- ADR-009 section 6
- INTERACTION_CONTRACTS RC-03 and section 2.5
- ROUTE_CONTRACTS RC-03, RP-105, RP-205 and RP-206
- DOCUMENT_CONTRACTS DC-02
- COPY_DECK sections 7.5, 7.10 and 7.11 and the TASK-002 key `ux.disabled.unresolved_checkouts`
- KEY_SCREENS KS-07
- D-068, D-088, D-089
- STACK_AND_MODULES STK-10
- CRITICAL_FLOWS WF-08

## 4. Scope

### 4.1 In scope

1. Create `cash_session_closes` and `cash_session_reports`; complete the deferred key `cash_sessions.close_presence` → `cash_session_closes`.
2. Implement RC-03: the identity; the actor (share); the pointer (share); the session `FOR UPDATE`; the session OPEN and the actor its responsible user; the activity version equal to the reviewed one, else `RECOUNT_REQUIRED`; after the lock, the responsible user's `sale.checkout` receipts read, and any PENDING or HELD one refusing `RECOUNT_REQUIRED` (G-27, RACE-35); expected cash derived from the facts under the lock (G-05); the variance; the report snapshot of DC-02 with per-method totals, the drawer-funded refunds listed separately (populated from TASK-034), the frozen identity and names; the status CLOSED; no CLOSING state; no lock held while cash is counted. Sale totals come through an Action that reads the Sales module's query (STK-10).
3. Build RP-105 (KS-07: the preview and count steps, the review, the recount state; "Lanjut hitung" disabled while an unresolved checkout is known), RP-205 (keyset list; own scope for a cashier) and RP-206 (OPEN: running figures; CLOSED: the immutable report). The Owner's sections of RP-206 are added by TASK-032; the print and export controls by TASK-031.
4. Add the till's "Tutup sesi kas" entry and the session link of UXP-604.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | `cash_session_closes`, `cash_session_reports` |
| Keys completed (EXC-44) | `cash_sessions.close_presence` → `cash_session_closes` (deferred) |
| Routes | RC-03, RP-105, RP-205, RP-206 |
| Route elements added | — |
| Pages | UXP-105 "Tutup sesi kas", UXP-205 "Sesi kas", UXP-206 "Detail sesi kas" |
| Controls | 25: UXI-004, UXI-019, UXI-077, UXI-101, UXI-102, UXI-103, UXI-104, UXI-105, UXI-106, UXI-107, UXI-108, UXI-109, UXI-110, UXI-111, UXI-135, UXI-136, UXI-137, UXI-138, UXI-139, UXI-143, UXI-144, UXI-145, UXI-147, UXI-148, UXI-340 |
| Controls extended with a destination | UXI-056 |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- Owner-assisted close and review notes (TASK-032).
- Printing and exporting the report (TASK-031).
- Drawer-funded refunds (TASK-034).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-CASH-05, REQ-CASH-06, REQ-CASH-07, REQ-CASH-08, REQ-CASH-09, REQ-CASH-10, REQ-PAY-04 | REQ-CASH-12, REQ-SALE-12 |
| Workflows | WF-08 | — |
| User tasks | UXT-09 | — |
| Acceptance suites | SESSION01 (final: TASK-044), SESSION04 (final: TASK-044), SESSION05 (final: TASK-044), SESSION06 (final: TASK-044) | SESSION07, SESSION08, SESSION10, AUTH01 |
| Races | RACE-08, RACE-35 | RACE-09, RACE-13 |
| Guards | G-27 | G-05, G-17, G-19 |
| Threats | — | — |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-030-01** Opening Rp20.000, CASH Rp100.000 and BANK_TRANSFER Rp50.000 with Rp118.000 counted give expected Rp120.000 and variance −Rp2.000; BANK_TRANSFER never changes expected cash; per-method totals are separate; opening cash and variance are never sales or profit.
- **AC-030-02** RACE-08 in both orders: a Sale first makes the close refuse `RECOUNT_REQUIRED`; a close first makes the Sale refuse `SESSION_NOT_OPEN` before any fact; no successful Sale is missing from its report; no lock is held while cash is counted.
- **AC-030-03** G-27 and RACE-35: a close is refused while the responsible user has a PENDING or HELD checkout receipt, an earlier-epoch HELD receipt included; after the status check settles it (D-089 scenario of SESSION05 with real checkout receipts, by the user and by the Owner) the close succeeds; the server refusal holds when the client's list is stale.
- **AC-030-04** A repeated or lost close with the same or a different key gives one CLOSED session, one snapshot and one result; a different count never overwrites; a failure injected at the snapshot, audit or result keeps the session OPEN; the result is recoverable after the close.
- **AC-030-05** Closing and opening again on one business date gives two sessions with two reports; a session across midnight stays one document.
- **AC-030-06** The snapshot agrees with the facts it freezes (G-17) and never changes afterwards; there is no reopening.
- **AC-030-07** KS-07 is reproduced with its states, including the disabled "Lanjut hitung" while a checkout is unresolved.

## 7. Tests

- L2 families for the two tables.
- L3 RACE-08, RACE-09 own part, RACE-35 and the settlement against a close.
- L2 failure injection.
- L4 RC-03 and scopes.
- L5 KS-07.
- Adds the close of a cash session to the L5 critical paths that every later pull request runs (EXECUTION_CONTEXT EXC-34).
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-030.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The adversarial review of the close transaction.

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

- TASK-031 prints and exports the report; TASK-032 adds the Owner-assisted close and notes; TASK-034 lists drawer-funded refunds in it.
- Tasks that depend on this one directly: [TASK-031](TASK-031-documents-sales-lookup.md), [TASK-032](TASK-032-assisted-close-notes.md).

## 13. Expected files

- `app/Actions/Cash/CloseCashSession`, `app/Domain/Cash/`
- `resources/js/pages/cash/`
- `database/migrations/`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
