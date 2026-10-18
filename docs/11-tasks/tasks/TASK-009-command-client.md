# TASK-009 — Command client, recovery and unresolved requests

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 2 Platform core · lane P, platform trunk |
| Size and review | M · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-008](TASK-008-command-platform.md), [TASK-002](TASK-002-p7-change-control-1.md) |
| Gates | GATE-CC1 ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-009-command-client` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5, at the effort that the Owner's instruction names for this Task (D-031, D-095), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Commands (client), Frontend shell |
| Baseline | RF-7, D-090; corrected under D-091 |

## 1. Goal

The browser side of every command — frozen intents, the client states, the 65-second wait, "Kirim ulang" and "Cek status", the stored references, the family block of NC-13 across devices through RD-04, the uncertain and held panels — and the page of unresolved requests with the shell's indicators.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- INTERACTION_CONTRACTS sections 1.1 to 1.7, 2.1 to 2.4, 3.3 and 3.6
- ADR-007 section 5; ADR-009 section 2
- NAVIGATION_CONTRACTS NC-13 and the UXP-006 contract
- UI_STATES UXS-06, UXS-13 to UXS-16 and section 4
- INTERACTION_INVENTORY UXP-006 rows and UXP-000 rows UXI-025, UXI-026, UXI-029 and UXI-030
- COPY_DECK sections 6.7 and 14 and the TASK-002 additions
- SECURITY_MODEL SEC-CH-05
- ROUTE_CONTRACTS RP-006, RD-03, RD-04 and RT-10
- TEST_STRATEGY TS-22 and TS-23

## 4. Scope

### 4.1 In scope

1. Build the command client in `resources/js/lib`: freeze operation, intent (a UUID created at the final action), key, payload and expected versions; send the recovery epoch of the shared props; read every answer into the client states of INTERACTION_CONTRACTS section 1.3; wait at least 65 seconds, saying "Masih diproses" earlier; "Kirim ulang" reuses the intent and key; "Cek status" calls RC-50 and repeats automatically after 3, 6 and 12 seconds, then every 15 seconds while the page is visible, as background requests; time alone never yields REJECTED; a check without an answer leaves the state as it was (F-05, D-084).
2. Keep the minimal references of SEC-CH-05 in local storage under the user's internal identifier, across a reload, a closed tab and a sign-out, never shown to another user, removed when resolved; at sign-out and change of user clear all other browser storage.
3. Apply NC-13: the families of INTERACTION_CONTRACTS section 1.4 are blocked by the union of the local references and the user's unresolved receipts from RD-04, read when a page of the family opens, before a final action is enabled and with the shell refresh; the disabled reason (UXS-13) links to UXP-006; the exceptions of NC-13 (d); print, reprint and export exempt; the Owner's block uses only the Owner's own requests; a command page opened while a reference exists opens on UXS-14 or UXS-15 with that reference (NC-13 (b)).
4. Build the uncertain and held panels with the family texts of COPY_DECK section 14.1, the recent-authentication dialog flow for 423 (UXS-11, then the same frozen request), and the handling of 401 and 419.
5. Build the sign-out warning of IC-10 (UXI-026): "Lihat permintaan" or "Tetap keluar".
6. Build UXP-006 (RP-006): the union of the user's references and receipts, one row per operation and intent, with its family label, time and "Cek status"; for the Owner, every other user's unresolved receipts with the user's name and the Owner's "Cek status" (UXI-058); the lost-reference rule's text; all words from the TASK-002 keys.
7. Create RD-03 with the number of the actor's unresolved receipts and the as-of time, read at least every 30 seconds while visible, on focus and after any command, as background requests; a failed read keeps the last data with its time (UXS-21). Add the banner (UXI-029, UXI-030) and the account-menu count (UXI-025).
8. Build the browser harness for network faults (TS-22: a response dropped after commit, 409 answers, 503, a fake clock beyond 65 seconds) and for a second device (TS-23: two contexts of one user, and one with its site data cleared), proven with the probe operation of TASK-008.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | — |
| Keys completed (EXC-44) | — |
| Routes | RP-006, RD-03 |
| Route elements added | — |
| Pages | UXP-006 "Permintaan belum pasti" |
| Controls | 12: UXI-025, UXI-027, UXI-028, UXI-029, UXI-031, UXI-032, UXI-033, UXI-036, UXI-037, UXI-055, UXI-057, UXI-058 |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- The till's recovery of a checkout (TASK-029) and every page's own panel use (their Tasks).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | — | REQ-CMD-01, REQ-SALE-06, REQ-SALE-08, REQ-UX-02 |
| Workflows | WF-07 | — |
| User tasks | UXT-08 | — |
| Acceptance suites | — | CMD01, POS03, UX01 |
| Races | — | RACE-27, RACE-33 |
| Guards | — | — |
| Threats | — | THR-18 |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-009-01** Every answer class leads to the client state of INTERACTION_CONTRACTS section 1.3, proven with the faults of TS-22; HELD offers "Cek status" only; a status check without an answer leaves UXS-14 or UXS-15 as it was.
- **AC-009-02** While SUBMITTING, a second press, tap or Enter sends nothing; Enter never activates a final action.
- **AC-009-03** References survive a reload, a closed tab and a sign-out, are never shown to another user and are removed when the server reports their intent resolved.
- **AC-009-04** NC-13 blocks the family in a second browser context of the same user and after the site data are cleared, for every request that reached the server (TS-23), with the exceptions of NC-13 (d) and the print exemption; other users' requests never block the Owner.
- **AC-009-05** UXP-006 lists the union of references and receipts for the user, and every user's unresolved receipts for the Owner, whose "Cek status" writes the fence or settles the receipts as section 2.2 says.
- **AC-009-06** The sign-out warning appears with an unresolved request; RD-03 and the indicators refresh as section 3.3 says and never show zero after a failed read.
- **AC-009-07** Every word comes from COPY_DECK keys, including the TASK-002 additions; the panels and UXP-006 pass the accessibility checks.

## 7. Tests

- L5 client states with TS-22 faults.
- L5 cross-device block with TS-23.
- L4 RD-03.
- L5 UXP-006 for a cashier and for the Owner.
- Adds one recovery case — a command whose answer is lost, resolved through the status check with a TS-22 fault — to the L5 critical paths that every later pull request runs (EXECUTION_CONTEXT EXC-34).
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-009.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The fault matrix and its results.

## 9. Definition of Done

| Dimension | This Task |
| --- | --- |
| Business | Applies — the criteria of section 6 and the requirements of section 5 |
| UI and UX | Applies — the approved P7 artifacts for its pages and controls (EXC-50 to EXC-53) |
| Backend | Applies — FormRequest allowlists, policies and object scope, in-transaction guards, DTO allowlists, L4 tests |
| Database | N/A — no migration |
| Security | Applies — the threat paths of section 5, the refusals of the other roles and the combined account, no secret, the static checks |
| Data integrity | N/A — writes no business fact |
| Performance | Applies — bounded queries and paging; the budgets are measured at TASK-048 |
| Operations | N/A — no backup, runbook or status impact |
| Verification | Applies — the command contract green; self-review; the reviews of class A (EXECUTION_CONTEXT section 12); evidence recorded |

Each dimension is recorded `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason (EXECUTION_CONTEXT section 13).

## 10. Stop conditions

- A needed text has no approved key (EXC-51).
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- Every page with a command uses this client and its panels.
- Tasks that depend on this one directly: [TASK-011](TASK-011-user-management.md).

## 13. Expected files

- `resources/js/lib/commands/`
- `resources/js/pages/commands/`
- `app/Http/Controllers/Access/ShellDataController`
- `tests/Browser/`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
