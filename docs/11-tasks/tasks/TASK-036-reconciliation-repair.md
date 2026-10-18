# TASK-036 — Reconciliation, incidents and data-repair procedures

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 7 Onboarding, reconciliation and operations · lane O, operations and delivery |
| Size and review | L · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-034](TASK-034-drawer-refunds.md), [TASK-020](TASK-020-barcode-correction.md), [TASK-013](TASK-013-independent-backup.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-036-reconciliation-repair` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5, at the effort that the Owner's instruction names for this Task (D-031, D-095), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Operations, Inventory, Costing, Sales (running totals), Catalog (active mappings) |
| Baseline | RF-7, D-090 |

## 1. Goal

The daily read-only reconciliation that compares facts with every projection and blocks a mismatching product, the Owner's incident pages and reopening, and the two operator procedures that repair what it finds: the projection rebuild and the cost recovery.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- INVENTORY section 11 (BR-INV-45, BR-INV-46)
- SCHEMA G-03, section 5.7 (blocks and reopenings), section 5.8 (marks), section 5.12 (`reconciliation_runs`) and section 9.4 (the reconciliation family)
- ADR-008 section 5
- TECHNICAL_RULES section 7.2 (reconciliation block or reopening) and RACE-24
- INTERACTION_CONTRACTS RC-16 and section 7
- ROUTE_CONTRACTS SC-01, RC-16, RP-414, RP-415, OP-06 and OP-07
- SECURITY_MODEL CAP-18, SEC-OP-06 and SEC-OP-07
- OPERATIONS_RUNBOOK sections 3 and 4, RB-04 and RB-12
- ENVIRONMENTS SC-01
- COPY_DECK sections 9.15 and 9.16 and the TASK-002 label of `PROJECTION_REBUILT`
- CRITICAL_FLOWS WF-27 and WF-32

## 4. Scope

### 4.1 In scope

1. Create `reconciliation_runs`.
2. Implement SC-01 `reconciliation:run` (daily at 03.00 Asia/Jakarta): each product read in one consistent snapshot; balances, valuations and issue and return allocation projections recomputed with the application's own arithmetic, never SQL arithmetic (ADR-008 section 5); running totals and active barcode mappings compared; an inventory or cost mismatch written by the enveloped `posting_block.block` (process actor; a deterministic intent per run and product); a running-total or mapping difference recorded as an incident; one `reconciliation_runs` row; the reconciliation heartbeat pinged after completion; bounded; no overlap.
3. Implement RC-16 `posting_block.reopen` (the Owner; recent authentication) and build RP-414 and RP-415; add the open-incident count to RD-03 (UXI-017).
4. Implement OP-07 `projection:rebuild` (SEC-OP-07): for a product its balance, valuation and allocation projections, for a Sale its running totals, for a token its active mapping, recomputed from facts under the guards of the commands that write them; audit `PROJECTION_REBUILT` with cost only in Owner-only data; never a fact changed; refused with no change when the facts fail validation.
5. Implement OP-06 `costing:recover` (SEC-OP-06): on the Owner's written decision, the correction's validation and replay with a bound of at most ten times the movement cap and 10 minutes, under the product guard; the correction fact published with the Owner as actor; the marks of that cost source cleared with `RECOVERY_PROCEDURE`; audit; a repeated run with the same mark returning the first result; never used for an unsafe history.
6. Prepare the staging rehearsal script of UAT-20 (an induced mismatch on staging, run by the operator).

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | `reconciliation_runs` |
| Keys completed (EXC-44) | — |
| Routes | SC-01, RC-16, RP-414, RP-415, OP-06, OP-07 |
| Route elements added | RD-03: open incidents |
| Pages | UXP-414 "Insiden rekonsiliasi", UXP-415 "Detail insiden" |
| Controls | 6: UXI-017, UXI-301, UXI-302, UXI-303, UXI-304, UXI-305 |
| Controls extended with a destination | UXI-223 |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- The system status of reconciliation (TASK-039).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-INV-12 | REQ-COST-06, REQ-OPS-04, REQ-CMD-03 |
| Workflows | WF-27 | WF-32 |
| User tasks | UXT-34 | — |
| Acceptance suites | — | INV01, OPPROC01, FIN03, STATUS01 |
| Races | RACE-24 | — |
| Guards | G-03 | G-11, G-21 |
| Threats | — | THR-10 |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-036-01** Mismatches induced on a test database in balances, valuations, allocation projections, running totals and active mappings are detected; an inventory or cost mismatch blocks the product and refuses its postings `POSTING_BLOCKED`, while other products post.
- **AC-036-02** RACE-24 gives its outcome in both orders.
- **AC-036-03** Reopening needs the Owner and recent authentication; the incident pages follow the approved artifacts.
- **AC-036-04** OP-07 rebuilds projections from facts, never changes a fact, refuses on invalid facts and writes `PROJECTION_REBUILT`.
- **AC-036-05** OP-06 publishes the correction with the extended bound or refuses with no change, clears the marks with `RECOVERY_PROCEDURE`, and is idempotent per mark.
- **AC-036-06** Both procedures follow the common rules of SECURITY_MODEL section 14 (typed environment, typed options, audit and security log).
- **AC-036-07** One `reconciliation_runs` row per run; the heartbeat is pinged only after a completed run.

## 7. Tests

- L2 reconciliation family.
- L3 RACE-24.
- L2 procedure tests of OP-06 and OP-07.
- L4 RC-16.
- L5 RP-414 and RP-415.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-036.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

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

- The reconciliation cannot finish within its bound on the provisional dataset.
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-039 shows the reconciliation status; TASK-045 rehearses OP-06, OP-07 and UAT-20 on staging.
- Tasks that depend on this one directly: [TASK-039](TASK-039-system-status.md), [TASK-042](TASK-042-audit-views.md).

## 13. Expected files

- `app/Domain/Operations/Reconciliation/`, `app/Console/Commands/` (`reconciliation:run`, `projection:rebuild`, `costing:recover`)
- `resources/js/pages/inventory/Incidents`
- `database/migrations/`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
