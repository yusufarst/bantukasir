# TASK-016 — Barcodes and exact lookup (server)

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 4 Catalogue · lane C, catalogue |
| Size and review | M · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-015](TASK-015-catalog-core.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-016-barcodes` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5 at effort Max (D-066), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Catalog |
| Baseline | RF-7, D-090 |

## 1. Goal

Barcode tokens and their mappings — add, generate an internal code, retire with a reason — with the exact lookup that scanning uses, on the server, including the barcode part of product creation and of the search-first check.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- CATALOG_BARCODE sections 3 and 5 (BR-CAT-16 to BR-CAT-22, BR-CAT-28 to BR-CAT-31) and BR-CAT-50 to BR-CAT-52
- SCHEMA section 5.6 (barcode tables), G-09 and section 11
- TECHNICAL_RULES section 7.2 (barcode add, generate, retire), RACE-17 and RACE-19, section 11.3
- INTERACTION_CONTRACTS RC-17, RC-27 to RC-29, sections 3.1 and 3.5
- ROUTE_CONTRACTS RC-27 to RC-29
- SECURITY_MODEL CAP-06; D-051, D-052

## 4. Scope

### 4.1 In scope

1. Create `barcode_tokens`, `barcode_mappings`, `barcode_mapping_retirements` and `barcode_active_mappings` with their keys, checks and grants.
2. Implement the token rules: exact round-trip of leading zeros, case and punctuation; only the scanner terminator and outer spaces removed; 1 to 128 printable ASCII characters; the check digit of a declared EAN or UPC validated; the `BK-` namespace reserved; internal codes `BK-P-` followed by 16 Crockford Base32 characters from a secure random source, never reused; a retired token never added again except by the correction of TASK-020; the retirement's reason class recorded.
3. Implement RC-27, RC-28 and RC-29, and the barcodes of RC-17, with their locks, canonical insert order (SCH-32) and audit.
4. Add to RD-01 the `exact` mode (exact barcode, then exact SKU, at most one product) and the barcode rank in `search`; add the barcode check to RD-06.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | `barcode_tokens`, `barcode_mappings`, `barcode_mapping_retirements`, `barcode_active_mappings` |
| Keys completed (EXC-44) | — |
| Routes | RC-27, RC-28, RC-29 |
| Route elements added | RC-17: barcodes at product creation, RD-01: `exact` mode and barcode rank, RD-06: barcode check |
| Pages | — |
| Controls | — |
| Controls extended with a destination | — |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- The misassignment correction (TASK-020).
- Labels (TASK-018).
- The pages (TASK-017).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-CAT-04, REQ-CAT-05 | REQ-CAT-03, REQ-SCAN-02 |
| Workflows | — | WF-14, WF-16 |
| User tasks | — | — |
| Acceptance suites | SEARCH01 (final: TASK-044) | SEARCH02, BARCODE01 |
| Races | — | RACE-17, RACE-19 |
| Guards | G-09 | — |
| Threats | — | — |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-016-01** Tokens round-trip exactly; only the terminator and outer spaces are removed; length and character rules hold; a wrong EAN or UPC check digit is refused.
- **AC-016-02** Generated codes are `BK-P-` and 16 Crockford Base32 characters and are never reused; an alias in the `BK-` namespace is refused; a retired token is refused by RC-17 and RC-27.
- **AC-016-03** A token is active on at most one product, also when two attaches race (RACE-17, attach part); a retirement leaves no active mapping (G-09).
- **AC-016-04** RD-01 `exact` returns at most one product for a scanned code; RD-06 reports an exact barcode match.
- **AC-016-05** The constraint, deferred and privilege families pass for the four tables.

## 7. Tests

- L1 token rules.
- L2 families for the four tables.
- L3 RACE-17 attach part.
- L4 commands and reads.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-016.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- Nothing beyond EXC-61.

## 9. Definition of Done

| Dimension | This Task |
| --- | --- |
| Business | Applies — the criteria of section 6 and the requirements of section 5 |
| UI and UX | N/A — no page or control |
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

- TASK-017 shows barcodes; TASK-020 corrects misassignments; TASK-029 scans with the exact mode.
- Tasks that depend on this one directly: [TASK-017](TASK-017-catalog-pages.md), [TASK-021](TASK-021-ledger-receiving.md), [TASK-028](TASK-028-checkout.md).

## 13. Expected files

- `app/Domain/Catalog/Barcodes/`, `app/Actions/Catalog/`
- `database/migrations/`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
