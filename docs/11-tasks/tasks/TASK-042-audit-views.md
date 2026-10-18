# TASK-042 — Audit log and security log views

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 8 Reports and audit views · lane R, reports, audit views and release |
| Size and review | M · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-036](TASK-036-reconciliation-repair.md), [TASK-035](TASK-035-onboarding.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-042-audit-views` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5, at the effort that the Owner's instruction names for this Task (D-031, D-095), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Audit |
| Baseline | RF-7, D-090 |

## 1. Goal

The Owner's business audit log and security log, with their filters and labels, and the staff's audit within their own scope.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- DOMAIN_MODEL section 9 (BR-AUD-01 to BR-AUD-06)
- SECURITY_MODEL SEC-LOG-01 to SEC-LOG-05 and CAP-32
- SCHEMA section 5.4
- INTERACTION_CONTRACTS section 8
- ROUTE_CONTRACTS RP-612 and RP-613
- COPY_DECK sections 11.12 to 11.14 and the TASK-002 labels
- CRITICAL_FLOWS WF-31

## 4. Scope

### 4.1 In scope

1. Build RP-612: filters by date, user, action, product, session and reference; keyset paging; the label of every action code of INTERACTION_CONTRACTS section 8, the TASK-002 labels included; Owner-only data shown only to the Owner.
2. Build RP-613: filters by type and user; the outcome labels SUCCEEDED and REFUSED.
3. Expose the staff's audit only within their scope (BR-AUD-04): a cashier's own Sales and sessions within CAP-21, OPERATIONS_ADMIN's safe stock history within CAP-14, wherever the approved pages show it.
4. Add the settings index entries.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | — |
| Keys completed (EXC-44) | — |
| Routes | RP-612, RP-613 |
| Route elements added | — |
| Pages | UXP-612 "Riwayat aktivitas", UXP-613 "Log keamanan" |
| Controls | 11: UXI-330, UXI-331, UXI-377, UXI-378, UXI-379, UXI-380, UXI-381, UXI-382, UXI-383, UXI-384, UXI-385 |
| Controls extended with a destination | UXI-177, UXI-251, UXI-267, UXI-349 |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- Anything not listed in section 4.1 (EXECUTION_CONTEXT section 14).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-AUD-02, REQ-RPT-05 | REQ-AUD-01, REQ-ACC-10 |
| Workflows | — | WF-31 |
| User tasks | — | — |
| Acceptance suites | AUDIT01 (final: TASK-044) | AUTH02 |
| Races | — | — |
| Guards | — | G-16 |
| Threats | — | THR-08 |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-042-01** The Owner filters by date, user, type, product, session and reference; staff see only their scope; the security audit and the security log are the Owner's only.
- **AC-042-02** A deactivated or anonymized user's events show the frozen identity; a combined account's events name one identity.
- **AC-042-03** Every action code of INTERACTION_CONTRACTS section 8 has its approved label.
- **AC-042-04** No staff payload contains Owner-only data.

## 7. Tests

- L4 filters, scope and payloads per role.
- L5 RP-612 and RP-613.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-042.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

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

- An action code has no approved label (EXC-51).
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- —
- Tasks that depend on this one directly: [TASK-043](TASK-043-performance-harness.md).

## 13. Expected files

- `app/Queries/Audit/`
- `resources/js/pages/audit/`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
