# TASK-025 — Cost correction, completion and bounded replay

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 5 Inventory and costing · lane I, inventory and costing |
| Size and review | XL · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-024](TASK-024-stock-adjustment.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-025-cost-correction` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5, at the effort that the Owner's instruction names for this Task (D-031, D-095), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Costing, Reporting (cost pages) |
| Baseline | RF-7, D-090; corrected under D-091 |

## 1. Goal

The Owner's completion and correction of a cost after posting, as an append-only fact followed by the bounded, deterministic replay that publishes corrected valuations and allocation projections atomically; the cost-incomplete mark when a correction is refused; and the Owner's cost pages.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- COSTING sections 7 and 9
- ADR-008 sections 4.3, 5, 6, 7, the invariants and the test list; D-034, D-060, D-063
- SCHEMA section 5.8 (`cost_corrections`, `cost_incomplete_marks`, `cost_incomplete_mark_clearances`), G-07, G-15 and G-21
- TECHNICAL_RULES section 7.2 (cost correction or completion), RACE-11, TR-TIME-01 and section 9
- INTERACTION_CONTRACTS RC-15
- ROUTE_CONTRACTS RC-15 and RP-503 to RP-505
- SECURITY_MODEL CAP-12, CAP-19 and CAP-20; D-047
- COPY_DECK sections 10.4 to 10.6; UI_STATES UXS-24 to UXS-26
- CRITICAL_FLOWS WF-26

## 4. Scope

### 4.1 In scope

1. Create `cost_corrections`, `cost_incomplete_marks` and `cost_incomplete_mark_clearances`; the key of a clearance to a reversal is completed by TASK-026.
2. Implement RC-15: the Owner; recent authentication; a reason; the product guard; the next correction number with old values equal to the effective value (G-15); refused on a blocked product (G-11).
3. Implement the replay of ADR-008 section 4.3 from the affected source over the movement types whose writers exist — RECEIPT, MANUAL_ISSUE and ADJUSTMENT — bounded by the movement cap (the reference 10,000) and the 30-second budget; unsafe chronology refused; publication of valuations and projections all or nothing; facts never touched. TASK-026, TASK-028, TASK-033 and TASK-035 extend it to REVERSAL, SALE_ISSUE, RETURN_RECEIPT and OPENING.
4. On a refused correction, answer `REPLAY_REJECTED` only after the mark's own transaction commits — one mark per refused intent, `SERVICE_BUSY` if that transaction fails — and write no mark after lock contention, `POSTING_BLOCKED` or a refused reversal (TR-TIME-01, D-063). A mark is cleared only by a later successful correction of the same source, a reversal of its movement (TASK-026) or OP-06 (TASK-036).
5. Complete the corrected-cost display of RP-406 for OPERATIONS_ADMIN and the combined account (D-047): the current cost, "Dikoreksi Pemilik" and the cost at confirmation, never the reason or history.
6. Build RP-503 (UNKNOWN sources and marked products), RP-504 (the current value and history; a draft in page memory; review) and RP-505 (evidence, corrections, average cost, inventory value), and their entries from RP-302, RP-403 and RP-406.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | `cost_corrections`, `cost_incomplete_marks`, `cost_incomplete_mark_clearances` |
| Keys completed (EXC-44) | — |
| Routes | RC-15, RP-503, RP-504, RP-505 |
| Route elements added | — |
| Pages | UXP-503 "Biaya perlu dilengkapi", UXP-504 "Koreksi biaya", UXP-505 "Riwayat biaya produk" |
| Controls | 17: UXI-021, UXI-176, UXI-226, UXI-249, UXI-313, UXI-314, UXI-315, UXI-316, UXI-317, UXI-318, UXI-319, UXI-320, UXI-321, UXI-322, UXI-323, UXI-324, UXI-325 |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- The cost recovery procedure OP-06 (TASK-036).
- Reports that show corrected figures (TASK-040).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-COST-05, REQ-COST-06 | REQ-COST-03, REQ-COST-04, REQ-COST-09, REQ-RPT-03, REQ-INV-05, REQ-ACC-08 |
| Workflows | WF-26 | — |
| User tasks | UXT-29 | — |
| Acceptance suites | FIN02 (final: TASK-044), FIN03 (final: TASK-044) | AUTH02, INV03, AUTH03 |
| Races | RACE-11 | — |
| Guards | G-07, G-15, G-21 | G-11, G-14 |
| Threats | — | THR-19 |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-025-01** UNKNOWN is distinct from zero and propagates; allocations made while the valuation was known stay valid; at zero stock a fully known increase starts a new known pool; incomplete figures are never shown as complete or zero.
- **AC-025-02** Correction and completion are the Owner's only, with recent authentication, as a new fact holding the old and the new value; the original evidence is unchanged; staff are refused on the server.
- **AC-025-03** The same correction replayed twice gives the same result; a failure injected during publication changes nothing; RACE-11 gives its outcome.
- **AC-025-04** Reaching the movement cap or the time budget refuses the correction and writes exactly one mark for the refused intent; no mark after `SERVICE_BUSY`, `POSTING_BLOCKED` or a refused reversal; marks clear only as stated.
- **AC-025-05** After the Owner corrects Rp5.000 to Rp5.500, OPERATIONS_ADMIN sees Rp5.500, "Dikoreksi Pemilik" and Rp5.000, and never the reason (D-047).
- **AC-025-06** The cost pages follow the approved artifacts and show UXS-24, UXS-25 and UXS-26 where they apply.
- **AC-025-07** The constraint and privilege families pass for the three tables.

## 7. Tests

- L1 and L2 the ADR-008 replay cases.
- L3 RACE-11 and the mark transaction against a later correction.
- L2 failure injection during publication.
- L4 RC-15 and the role payloads.
- L5 cost pages.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-025.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The ADR-008 cases covered.
- The measured replay time on the local dataset (not a PERF01 pass).

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

- A replay case cannot be met without changing a cost rule.
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-026, TASK-028, TASK-033 and TASK-035 extend the replay; TASK-036 adds OP-06; TASK-040 reports the published projections.
- Tasks that depend on this one directly: [TASK-026](TASK-026-reversal.md), [TASK-028](TASK-028-checkout.md), [TASK-033](TASK-033-refunds-core.md), [TASK-040](TASK-040-finance-report.md).

## 13. Expected files

- `app/Domain/Costing/Replay/`, `app/Actions/Costing/CorrectCost`
- `resources/js/pages/costing/`, `resources/js/pages/reporting/CostWorklist`
- `database/migrations/`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
