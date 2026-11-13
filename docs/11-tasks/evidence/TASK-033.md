# TASK-033 evidence — Refunds and returns: correction core (server)

| | |
| --- | --- |
| Task | [TASK-033](../tasks/TASK-033-refunds-core.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01) |
| Executor | Agent `bk-executor-max`, model `claude-opus-5-5`, effort Max from the table of EXECUTION_CONTEXT section 19; review by `bk-reviewer-max`, verification by `bk-reviewer-xhigh` |
| Branch and base | `task/TASK-033-refunds-core`, from `8eda3a86c08569658a31b602d626d810c8c42339` (origin/develop, the squash merge of TASK-032) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`; the stash and the worktree `bantukasir-task002` as recorded; no branch or pull request of TASK-033 and no open pull request; the executor state at `BUILD` for TASK-033 with this session's lock; TASK-031, TASK-025 and TASK-028 `DONE`, no gate. PHP, `tsc` and ESLint do not run on this machine, so every check but the guard runs in CI (D-108, D-117) |
| Owner's directives in force | The host forwarded them at the start (13 November 2026): TASK-033 only; the earliest safe release with the fewest tokens; fix only proven failures, each classified; no weakened test, gate, baseline or timeout; a new CVE is a stop; the next free decision D-129 (none taken). The session was resumed once after a stall of the stream (CE-24), with nothing written before it |

## 1. Checks

Every check runs in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck`, `npx tsc --noEmit`, `npx eslint`, PHP's checks | Local | — | — | `NOT_RUN`: neither PHP nor `tsc` and `eslint` are on the path of this machine; the fast runs run them. No TypeScript changed |
| Guard (Gitleaks 8.30.1, the secret scan of the tree and the commits, identity, candidates) | Local, on `565fef1` (checkpoint 1) | 13 Nov, 23:23 to 23:31 | about 8 min | `PASS`, no candidate |
| `composer validate`, `composer lint` (Pint, the translation keys and their types, the migration review and the rest), `composer analyse`, `composer test:unit`; `npm run lint`, `typecheck`, `test:unit` | Fast run 1, 43463640402 on `565fef1` | 23:32 to 23:37 | about 5 min | `PASS` |
| The changed PostgreSQL and concurrency files, in the order of their paths: `CorrectionRaceTest` (7 tests), `CapabilityMatrixTest` (7), `RouteInventoryTest` (8), `LedgerTablesTest` (19), then `CheckoutSaleTest` | Same run | 23:37 to 23:41 | 4 min 20 s; the run 9 min 41 s in all | `FAIL`: the first four `PASS`; `CheckoutSaleTest` one failure of 13 tests, the payment methods' names in a second file (F-5); the files after it did not run |

## 2. What was built (contract section 4.1)

| Item | Where |
| --- | --- |
| 1. The six tables with their checks, keys, indexes and grants: `sale_corrections`, `refunds` (above zero, exactly one source, D-045), `refund_lines`, `return_lines` (SALEABLE or NOT_SALEABLE, the presence key `movement_presence`), `return_cost_allocations` (linked to the SALE_ISSUE allocation of the same line and product, saleable only) and `return_cost_allocation_projections`; the keys completed (EXC-44): `stock_movements_return_line_fk`, `stock_movements_return_allocation_presence_fk` (deferred) and `return_lines_movement_fk` (deferred); the indexes of SCHEMA section 11, the drawer-funded refunds of a session for TASK-034 among them | `database/migrations/2026_11_13_000002_create_sale_correction_tables.php`; `database/privileges.php` |
| 2. The correction engine shared with RC-05: facts first (the Sale, its lines, their products' precision), the onboarding state (2.3, share) when a saleable return is received, RC-05's session hook (4), the original Sale's guard row (5) and the cumulative bounds of the Sale and each line, separate for money and quantity, held again by the running totals' checks and changed only by relative increments (SCH-33); ONBOARDING_FREEZE, POSTING_BLOCKED and PRODUCT_INACTIVE for a saleable return only; the products and their balances, valuations and linked issue projections (6, 7); one command with a refund, a return or both; a RETURN_RECEIPT for a saleable line with its return allocation restoring the issue's cost by the cumulative rule (D-061); the posting date; the audit event `SALE_CORRECTION`; idempotent | `App\Actions\Sales\CorrectionAction`, `CorrectionPayload`, `CorrectionLine`, `ReturnPosting`; `App\Domain\Sales\Corrections\` (`SaleCorrections`, `CorrectionBounds`, `CorrectedLine`, `Payout`, `SaleTotals`, `LineTotals`, `SaleLineFacts`, `CorrectedSale`); `App\Domain\Costing\Allocations\ReturnAllocation`, `ReturnAllocations`, `LinkedIssue`, `IssueAllocations::ofSaleLines`; `App\Domain\Inventory\StockLedger::appendReturnReceipt` |
| 3. RC-06 `sale_correction.other`: CAP-24 at the route with recent authentication; a reason; the payout made, CASH from the Owner's own funds or BANK_TRANSFER, with its reference, absent for a return without money; no session touched | `App\Actions\Sales\RecordCorrection`; `App\Http\Requests\Sales\RecordCorrectionRequest`; `App\Http\Controllers\Sales\RecordCorrectionController`; `routes/web.php`; `App\Actions\Commands\CommandActions`; `App\Support\Validation\QuantityRule` (zero allowed) |
| 4. The replay extended to RETURN_RECEIPT: the state before the start counts the return projections; each return reached is recomputed from its link with its issue — the issue's projection as the replay recomputed it, or as it stands when it precedes the start — and R(k−1) read from the facts in ledger order; return projections published with the issue projections in the same write step of RC-15 and RC-10 | `App\Domain\Costing\Replay\ReplayLedger`, `ValuationReplay`, `LedgerEntry`, `ReturnLink`, `ReplayResult`, `ProductReplay`; `App\Actions\Costing\CorrectCost`; `App\Actions\Inventory\ConfirmReversal` |

## 3. Readings and technical choices

| Reading | Why |
| --- | --- |
| Step 6 locks the products of the saleable returns only: a not-saleable return posts no movement and restores no cost (BR-INV-39, BR-COST-19), so it needs no product guard. Every returned product's precision is read without a lock, from the product a Sale line names, which keeps its unit and precision once it has moved (the key of `stock_movements` to `products (id, base_unit, quantity_precision)` with `ON UPDATE RESTRICT`) | TECHNICAL_RULES section 7.2; SCH-31; BR-CAT-07 |
| The refusals under the locks stand in the order of RC-06 and of ROUTE_CONTRACTS RC-06: the bounds (money, then quantity), ONBOARDING_FREEZE, POSTING_BLOCKED, PRODUCT_INACTIVE. The onboarding state is locked at step 2.3, in the order of TR §7.1, and judged after the bounds | INTERACTION_CONTRACTS RC-06; TR-LOCK-01 |
| "Each `saleLineId` belonging to the Sale" is checked by the Action from facts before any lock — no FormRequest of the platform reads the database — and refused VALIDATION_FAILED at the line's `saleLineId` with `ux.validation.choose_one`; a Sale that does not exist is NOT_FOUND | INTERACTION_CONTRACTS RC-06, "Before locks"; TR-ERR-03 |
| A line of zeros is accepted and writes nothing, so that a page may send every line of the Sale (TECHNICAL_RULES section 11.2: at most the lines of the original Sale); at least one line must refund money or return goods, else VALIDATION_FAILED at `payload.lines` with `ux.validation.lines_min`. Money without a payout is refused at `payload.payout` (`ux.validation.required`); a payout without money is VALIDATION_FAILED without a field, since COPY_DECK names no text for it and no page sends it | INTERACTION_CONTRACTS RC-06 |
| REFUND_BOUND_EXCEEDED and RETURN_BOUND_EXCEEDED name in `lines` each line concerned with its `saleLineId`, `remainingAmount` and `remainingQuantity`, and in `params` the first such line's remainder, formatted (`uang`, `jumlah`), for the message of COPY_DECK section 13.1. The Sale's own money bound, implied by the lines' while G-01 and G-06 hold, is checked too | UXS-35; INTERACTION_CONTRACTS section 1.6 |
| A return allocation's `based_on_publication_no` is the product's valuation publication before the return, "as for issue allocations": the cost-evidence state it was computed under; its amount comes from the linked issue's projection in force | SCHEMA section 5.8; ADR-008 section 3 |
| The return projections are rewritten in the same write step as the issue projections of RC-15 and RC-10, so that their failure-injection steps stay six and eight; the audit's `projectionsRewritten` counts both | ADR-008 section 4.3; BR-COST-25 |
| The audit event `SALE_CORRECTION`: the Sale's number as its reference, the reason in its column, its product when one product came back, no session (RC-06); the refund without a session key, which AuditLog refuses as a possible secret; the cost restored in the Owner's part | INTERACTION_CONTRACTS section 8; BR-AUD-02, BR-AUD-05 |
| The result's location is RP-204's path, `/sales/{sale}/corrections/{correction}`, though TASK-034 builds that page, as TASK-028 answered RP-103 before TASK-029 | ROUTE_CONTRACTS RC-06, RP-204 |
| The partial index `refunds (cash_session_id) WHERE funding_source = 'DRAWER'` of SCHEMA section 11 is the index of the key column as well: only a drawer-funded refund names a session (`refunds_one_source`) | SCHEMA section 11 |
| The engine is an abstract Action, `CorrectionAction`, with the hooks RC-05 needs: `beforeSale()` for its session (step 4, between 2.3 and 5), `payout()` under the Sale's lock for its generated reference `<Sale number>/<k>`, `afterRecords()` for the session's activity version and `auditSession()` | TASK-033 scope item 2; TASK-034 |

## 4. Findings

| ID | Finding | Outcome |
| --- | --- | --- |
| F-1 | RC-05 is TASK-034's: the engine leaves it its hooks — `beforeSale()` for the selected session at step 4 with `SESSION_NOT_OPEN` and `DRAWER_CASH_INSUFFICIENT` against expected cash, `payout()` with `Payout::fromDrawer()` and the reference `<Sale number>/<k>` generated under the Sale's lock, `afterRecords()` for the session's activity version, `auditSession()` — and the partial index `refunds_drawer_session_index` for the expected cash of RC-03, RC-04 and RC-05 | For TASK-034, with RP-204 at `SaleCorrections::recordedPath()` |
| F-2 | `CorrectCostTest`, `ConfirmReversalTest` and `CostCorrectionRaceTest` are unchanged while RC-15 and RC-10 now lock and publish the return projections, so no fast run runs them | They run in the whole of `test:postgres` and `test:race` on the pull request's final head (CE-44, D-117); the new return cases of those Actions are in `CorrectionReplayTest` and `CorrectionRaceTest` |
| F-3 | The finance report reads a refund on its correction's business date, the posting date, never the Sale's, and the cost a return restored from its current projection | For TASK-040 (BR-COST-36, REQ-RFD-07) |
| F-4 | Self-review: `SignInSessionTest` reads every table of the schema in the order of its key, `id` unless named, and `return_cost_allocation_projections` has none — its key is `allocation_id` — so the whole of `test:postgres` would fail there; no fast run runs that unchanged file | Fixed in checkpoint 2: the projection named with its key, as `issue_cost_allocation_projections` is; the changed file runs in fast run 2 |
| F-5 | Fast run 1, a code defect: `Payout` wrote the payment methods' names `CASH` and `BANK_TRANSFER` a second time, where ARC-30 keeps them in `PaymentMethods` alone; `CheckoutSaleTest`'s payment boundary found two files naming `BANK_TRANSFER` | Fixed in checkpoint 2: `Payout`'s methods are `PaymentMethods::CASH` and `PaymentMethods::BANK_TRANSFER`, its own list still explicit; that test is the regression test, in fast run 2 |

## 5. Tests (contract section 7)

| Test | Where |
| --- | --- |
| L1 the cumulative rule — D-061's example, three partial returns of Rp100 over 3 restoring 33.333333, 33.333334 and 33.333333, the whole quantity restoring exactly the issue, UNKNOWN never zero, a return beyond its issue refused — and the valuation after a return, a new pool from zero | `tests/Unit/Costing/ReturnAllocationTest.php` |
| L1 the replay over returns: a correction recomputes the issue and its linked return together; the returns of an issue before the start keep its cost and leave no residue; an UNKNOWN issue's return completed with it; a return beyond its issue an unsafe history | `tests/Unit/Costing/ValuationReplayTest.php` |
| L1 the matrix holds the six tables; the built Actions | `tests/Unit/Database/PrivilegeMatrixTest.php`, `tests/Unit/Commands/OperationsTest.php` |
| L2 the six tables: the constraint family — every check, key and unique key, the nulls of SCH-13, the presence key of a return line, the keys of `stock_movements` completed; the deferred family — a saleable return line without its RETURN_RECEIPT, a RETURN_RECEIPT without its allocation, an allocation without its projection; the privilege family; the retention family through RC-06's Action; the indexes and the deferral of the keys | `tests/Feature/Sales/CorrectionTablesTest.php`; `tests/Feature/Inventory/LedgerTablesTest.php` (a RETURN_RECEIPT now names a real return line) |
| L2 ADR-008 return cases through the Actions: D-061's worked example with a later return and a correction back, cost restored exactly once; an UNKNOWN issue's return completed; a return of an issue before the start, and the state before the start; a reversal recomputing a return; a failure during the publication | `tests/Feature/Sales/CorrectionReplayTest.php` |
| L2 failure injection after each of the 15 write steps of a refund on two lines with a saleable and a damaged return; the order of the locks | `tests/Feature/Sales/RecordCorrectionTest.php` |
| L3 RACE-10, refunds and returns racing on the Sale's lock, the bounds separate; RACE-01 and RACE-02; RACE-11, the return side, in both orders and past the lock timeout; an increment that skips the Sale's lock refused its excess (SCH-33) | `tests/Concurrency/CorrectionRaceTest.php` |
| L4 RC-06: the commit with every record; the Owner alone with recent authentication and a reason; the bounds of AC-033-02; a refund without return, a return without money; onboarding, a block and an inactive product; the refusals before any lock; the envelope, repeats and the status check; the original Sale and a closed session's report unchanged; the one writer of each new table, and of the running totals now written by the Sales module's corrections too; the route inventory and the capability matrix | `tests/Feature/Sales/RecordCorrectionTest.php`, `tests/Feature/Sales/CheckoutSaleTest.php`, `tests/Feature/Access/RouteInventoryTest.php`, `CapabilityMatrixTest.php`, `tests/Harness/Channels/Allowlists.php` |
| Fixtures through the Actions (EXC-45) | `TokoContoh::correct` (RC-06's Action), `TokoContoh::saleLinesOf` |

## 6. Acceptance criteria

The PostgreSQL and concurrency files that this branch changes run in its fast runs; the whole suites run in CI on the pull request's final head (CE-44).

| Criterion | Test | Result |
| --- | --- | --- |
| AC-033-01 Only the Owner corrects; CASHIER, OPERATIONS_ADMIN and the combined account refused on the server; a reason and recent authentication required | `RecordCorrectionTest` (the roles, the reason, 423), `CapabilityMatrixTest` RC-06 | See section 1 |
| AC-033-02 Separate, cumulative money and quantity bounds under RACE-10: a CASH Sale of Rp75.000 paid with Rp100.000 refunded at most Rp75.000; a partial line refund within its bound | `RecordCorrectionTest` (the bounds), `CorrectionRaceTest` RACE-10, `CorrectionTablesTest` | See section 1 |
| AC-033-03 A refund without return adds no stock; a damaged return adds no saleable stock; a saleable return posts RETURN_RECEIPT with its cost recovery | `RecordCorrectionTest` (the commit; refund without return) | See section 1 |
| AC-033-04 A refund without return keeps cost of goods sold; a saleable return restores the cost of its sale issue once; a correction after a return recovers cost exactly once | `RecordCorrectionTest`, `CorrectionReplayTest`, `ReturnAllocationTest`, `ValuationReplayTest`, `CorrectionRaceTest` RACE-11 | See section 1 |
| AC-033-05 Atomic and idempotent; a failure after every write step leaves nothing; the original Sale, Payment, receipt and a closed report unchanged; the correction linked and dated by its posting | `RecordCorrectionTest` (the commit, failure injection, repeats, the closed session) | See section 1 |
| AC-033-06 The constraint, deferred and privilege families for the six tables | `CorrectionTablesTest` | See section 1 |

## 7. Database (EXC-41 to EXC-47)

| Item | Record |
| --- | --- |
| Migration (EXC-41, EXC-46) | `2026_11_13_000002_create_sale_correction_tables`, additive, its REL-20 metadata in its comment; no earlier migration edited; rehearsal on the benchmark dataset waits for TASK-043 |
| SQL where the builder falls short (EXC-42) | The checks, the composite and presence keys, the deferred keys and the partial index as explicit statements, proven on PostgreSQL by `CorrectionTablesTest` |
| Grants with the table (EXC-43) | Runtime `SELECT, INSERT` on the five facts, `SELECT, INSERT, UPDATE (current_status, current_amount, publication_no, updated_at)` on the projection; backup `SELECT` on the tables and the identity sequences; `database/privileges.php` and `PrivilegeMatrixTest` |
| Keys across Tasks (EXC-44) | `stock_movements (return_line_id, return_condition, product_id, line_quantity)` → `return_lines`; `stock_movements (return_allocation_presence)` → `return_cost_allocations (movement_id)`, deferred; `return_lines (movement_presence)` → `stock_movements (return_line_id)`, deferred |
| Tests on the database (EXC-45) | The constraint, deferred, privilege and retention families as the runtime role; fixtures through RC-01's and RC-06's Actions |
| Backup impact (EXC-47) | The five fact tables are class F, so the manifest's counts include them through `database/privileges.php` (`BackupRunner::COUNTED_CLASSES`); the projection is class P, rebuilt from facts; no stored file |

## 8. Definition of Done

| Dimension | Result | Reason |
| --- | --- | --- |
| Business | PASS, subject to CI on the final head | AC-033-01 to AC-033-06 by their tests (section 6); REQ-RFD-01, REQ-RFD-02, REQ-RFD-05 to REQ-RFD-07 and REQ-COST-07 built for RC-06 |
| UI and UX | N/A | No page or control: TASK-034 builds the refund pages |
| Backend | PASS | The FormRequest allowlist with its per-field keys, the gate of the route with recent authentication, the facts of the Sale checked before any lock, the actor guard, the guards under the locks, the result rebuilt per viewer; L4 tests |
| Database | PASS | One additive migration with its REL-20 metadata; the constraint, deferred, privilege and retention families for the six tables (section 7) |
| Security | PASS | CAP-24 the Owner's alone at the gate; every other role and the combined account refused (THR-19); recent authentication; channel allowlists exact; no secret; the guard `PASS` |
| Data integrity | PASS, subject to CI on the final head | Append-only facts; the correction all or nothing with failure injection after each of its write steps; RACE-10 on the Sale's lock with the running totals' checks behind it; RACE-11 on the product guard; the cost restored once by the cumulative rule, and again with its issue by a replay; identity, repeats and the status check |
| Performance | PASS | Bounded: at most 200 lines; the reads by key and by index (SCHEMA section 11); the budgets are TASK-048's |
| Operations | PASS | The five fact tables in the backup manifest's counts; no runbook or status impact |
| Verification | PASS, subject to CI on the final head | The fast runs green (section 1), the review of class A (section 9); the heavy checks on the final head |
