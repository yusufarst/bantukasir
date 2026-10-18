# TASK-038 — Housekeeping and retention

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 7 Onboarding, reconciliation and operations · lane O, operations and delivery |
| Size and review | M · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-019](TASK-019-csv-import.md), [TASK-018](TASK-018-photos-labels.md), [TASK-011](TASK-011-user-management.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-038-housekeeping` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5 at effort Max (D-066), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Operations, Access, Commands, Media, Catalog |
| Baseline | RF-7, D-090 |

## 1. Goal

The hourly housekeeping that removes exactly what the decisions allow — expired link tokens, unreferenced stored files after their grace period, temporary CSV files after 24 hours, resolved request receipts after 30 days and security log entries after 365 days — and never a business record or an unresolved receipt.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- SCHEMA section 4, SCH-11, sections 5.3, 5.4 and 5.5
- SECURITY_MODEL SEC-ACC-07 and SEC-LOG-05
- TECHNICAL_RULES TR-TX-05 and TR-RUN-04
- ENVIRONMENTS SC-02 and section 14
- ROUTE_CONTRACTS SC-02
- ARCHITECTURE ARC-41 and ARC-43; D-044, D-054, D-088
- The evidence of TASK-006 on T-23

## 4. Scope

### 4.1 In scope

1. Implement SC-02 `housekeeping:run`, hourly, without overlap, with the process actor `housekeeping` and arguments from configuration only, in bounded batches: expired link tokens; stored files that no row references and that are older than the grace period of at least one hour; temporary CSV files older than 24 hours whatever their state; command request receipts whose outcome is COMMITTED, REJECTED or NOT_COMMITTED and whose `outcome_at` is more than 30 days old; security log entries older than 365 days, within the database enforcement of T-23.
2. Prove that no product path deletes or rewrites a row of classes F, FS, M or S, and that no delete, purge or archival function exists for business records or master data.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | — |
| Keys completed (EXC-44) | — |
| Routes | SC-02 |
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
| Requirements | REQ-OPS-05 | REQ-AUD-02, REQ-CAT-09, REQ-CAT-10 |
| Workflows | — | — |
| User tasks | — | — |
| Acceptance suites | KEEP01 (final: TASK-045) | CMD01, IMPORT01, MEDIA01, LINK01, AUDIT01 |
| Races | — | — |
| Guards | — | — |
| Threats | — | THR-10 |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-038-01** Each cleanup removes only its class and its age: never an unresolved receipt, never a referenced file, never a security log entry younger than 365 days, never a row of classes F, FS, M or S.
- **AC-038-02** An unreferenced file is removed after the grace period and never earlier; a temporary CSV file after 24 hours.
- **AC-038-03** The retention family passes; the static inventory shows no delete, purge or archival function for business records.
- **AC-038-04** The command never overlaps itself and takes no argument from stored data.

## 7. Tests

- L2 retention family.
- L2 each cleanup with the test clock at the boundaries.
- L4 route inventory: no deleting route.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-038.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- Nothing beyond EXC-61.

## 9. Definition of Done

| Dimension | This Task |
| --- | --- |
| Business | Applies — the criteria of section 6 and the requirements of section 5 |
| UI and UX | N/A — no page or control |
| Backend | Applies — FormRequest allowlists, policies and object scope, in-transaction guards, DTO allowlists, L4 tests |
| Database | N/A — no migration |
| Security | Applies — the threat paths of section 5, the refusals of the other roles and the combined account, no secret, the static checks |
| Data integrity | Applies — append-only facts, atomic audit and result, identity and idempotency, the races and the failure injection of section 5 |
| Performance | Applies — bounded queries and paging; the budgets are measured at TASK-048 |
| Operations | Applies — its tables in the backup manifest's counts and any runbook or status impact (EXC-47) |
| Verification | Applies — the command contract green; self-review; the reviews of class A (EXECUTION_CONTEXT section 12); evidence recorded |

Each dimension is recorded `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason (EXECUTION_CONTEXT section 13).

## 10. Stop conditions

- A cleanup would need to remove anything a decision does not allow.
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- —
- Tasks that depend on this one directly: [TASK-043](TASK-043-performance-harness.md).

## 13. Expected files

- `app/Console/Commands/` (`housekeeping:run`)
- `app/Domain/Operations/Housekeeping/`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
