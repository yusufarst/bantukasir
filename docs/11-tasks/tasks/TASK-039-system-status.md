# TASK-039 — System status and monitoring

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 7 Onboarding, reconciliation and operations · lane O, operations and delivery |
| Size and review | M · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-037](TASK-037-restore-tooling.md), [TASK-036](TASK-036-reconciliation-repair.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-039-system-status` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5, at the effort that the Owner's instruction names for this Task (D-031, D-095), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Operations |
| Baseline | RF-7, D-090 |

## 1. Goal

The Owner's system status with the evidence and thresholds of each item, the attention signal that Beranda shows, and the external web heartbeat.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- OPERATIONS_RUNBOOK sections 4 and 5
- ENVIRONMENTS SC-04 and sections 5 and 13
- ADR-011 item 11; ADR-010 items 9 and 11 and the TASK-002 outcome on the store's usage
- SCHEMA section 5.12
- ROUTE_CONTRACTS RP-611, SC-04 and RH-01
- SECURITY_MODEL CAP-32
- COPY_DECK section 11.11
- UI_STATES UXS-22 and UXS-43
- CRITICAL_FLOWS WF-31

## 4. Scope

### 4.1 In scope

1. Implement the status query: the four items of OPERATIONS_RUNBOOK section 5 in one request, with the thresholds of 7 hours (and the Sunday re-verification within 8 days), 35 days, 20 % free disk and 26 hours, and every "Belum Terverifikasi" case; a failed read is UXS-22, never a passing look; the presentation of the store's usage as TASK-002 decided.
2. Build RP-611 with the incident link while an incident is open (UXI-375) and the settings index entry; provide the attention signal "Status sistem perlu dicek" for Beranda (UXI-064, shown by TASK-041).
3. Implement SC-04 `monitor:heartbeat` every 5 minutes: fetch the environment's public `/up` through Cloudflare from the configured address and, on 200, ping the web check; never print an address.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | — |
| Keys completed (EXC-44) | — |
| Routes | RP-611, SC-04 |
| Route elements added | — |
| Pages | UXP-611 "Status sistem" |
| Controls | 4: UXI-329, UXI-374, UXI-375, UXI-376 |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- Beranda (TASK-041).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-OPS-04 | REQ-INV-12, REQ-NFR-05 |
| Workflows | WF-31 | — |
| User tasks | UXT-38 | — |
| Acceptance suites | STATUS01 (final: TASK-045) | UX01 |
| Races | — | — |
| Guards | — | — |
| Threats | — | — |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-039-01** Each item shows its evidence and thresholds, including every "Belum Terverifikasi" case; a failed read shows UXS-22; only the Owner reaches the page.
- **AC-039-02** The incident link appears while an incident is open.
- **AC-039-03** SC-04 pings the web check only after the public check succeeds; the backup and reconciliation checks are pinged on success by their commands.
- **AC-039-04** `backup_runs`, `restore_drills` and `reconciliation_runs` are read as SCHEMA section 5.12 says.

## 7. Tests

- L2 thresholds with the test clock.
- L4 RP-611 by role.
- L5 RP-611 states.
- L2 SC-04 against a local stand-in of the public address.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-039.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

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
| Operations | Applies — its tables in the backup manifest's counts and any runbook or status impact (EXC-47) |
| Verification | Applies — the command contract green; self-review; the reviews of class A (EXECUTION_CONTEXT section 12); evidence recorded |

Each dimension is recorded `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason (EXECUTION_CONTEXT section 13).

## 10. Stop conditions

- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-041 shows the attention signal on Beranda; TASK-045 receives each alert once on the target.
- Tasks that depend on this one directly: [TASK-041](TASK-041-owner-home-daily.md).

## 13. Expected files

- `app/Queries/Operations/SystemStatus`, `app/Console/Commands/` (`monitor:heartbeat`)
- `resources/js/pages/operations/Status`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
