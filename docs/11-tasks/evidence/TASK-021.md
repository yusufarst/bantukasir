# TASK-021 evidence — Stock ledger, valuation and receiving (server)

| | |
| --- | --- |
| Task | [TASK-021](../tasks/TASK-021-ledger-receiving.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01); resumed once (CE-24, CE-72) on the Owner's note "Owner authorizes TASK-021 continuation from safe-shutdown checkpoint b531068." |
| Executor | Agent `bk-executor-max`, model `claude-opus-5-5`, effort Max from the table of EXECUTION_CONTEXT section 19; review by `bk-reviewer-max`, verification by `bk-reviewer-xhigh` |
| Branch and base | `task/TASK-021-ledger-receiving`, from `b363c634106cc29b06a7eb208349339b5542bdb3` (origin/develop) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`; the stash of D-030; TASK-016 `DONE`, no gate; PHP and `tsc` do not run on this machine, so every check ran in CI (D-108, D-117) |

## 1. The Owner's stop and the resume

The Owner paused the Task in its build on 2 November 2026 at about 13:59 (Asia/Jakarta), to shut down his laptop: checkpoint `b531068` pushed, `STOPPED OWNER_STOP` from the phase `BUILD`, no review started and no pull request. He resumed it at 15:33 (`RESUMED after OWNER_STOP with a note`), resume 1 of 3: the run took the lock, kept every earlier change and continued from the build (CE-24). A pause, not a finding.

## 2. Checks

Every check ran in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta, 2 November 2026.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| Guard, preview on the working tree | Local | 14:01 | under 1 min of scanning | `PASS`, after the candidate of section 11 was removed |
| Fast run 1 of 10 (`fast-checks`): `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit`; the changed test files `RouteInventoryTest`, `SignInSessionTest`, `LedgerTablesTest` | CI run 41404230188 on `b531068` | 14:07 to 14:14 | 6 min 52 s (the job) | `PASS`; read on resume, since the stop did not await it. Evidence for the code of `b531068` only (CE-45) |
| Guard, preview and on the commit | Local, on `3296ca2` | 16:16 | under 1 min of scanning | `PASS`, no candidate |
| Fast run 2: `composer validate`, `lint` | CI run 41419419566 on `3296ca2` | 16:17 | under 3 min | `PASS` |
| Fast run 2: `composer analyse` | Same run | 16:20 | 9 s | `FAIL`: one PHPStan error, a `mixed` read of the database given to `Cost::fromDatabase()` in `ConfirmReceivingTest` (finding F-1); the run's later steps did not run |
| Guard, on the commit | Local, on `d6b1414` | 16:24 | under 1 min of scanning | `PASS`, no candidate |
| Fast run 3: `composer validate`, `lint`, `analyse`, `test:unit` | CI run 41420476916 on `d6b1414` | 16:27 | 38 s | `PASS` |
| Fast run 3: `npm run lint`, `typecheck`, `test:unit` | Same run | 16:29 | 40 s | `PASS` |
| `tests/Concurrency/ReceivingRaceTest.php` | Same run | 16:29 | 7 s | `PASS`, 6 tests, 53 assertions |
| `tests/Feature/Access/CapabilityMatrixTest.php` | Same run | 16:29 | 1 min 32 s | `PASS`, 7 tests, 2,499 assertions |
| `tests/Feature/Access/RouteInventoryTest.php` | Same run | 16:31 | under 1 s | `PASS`, 8 tests, 937 assertions |
| `tests/Feature/Access/SignInSessionTest.php` | Same run | 16:31 | 13 s | `PASS`, 10 tests, 198 assertions |
| `tests/Feature/Catalog/CatalogCommandsTest.php` | Same run | 16:31 | 52 s | `PASS`, 16 tests, 647 assertions |
| `tests/Feature/Catalog/CatalogPagesTest.php` | Same run | 16:32 | 30 s | `PASS`, 9 tests, 24,714 assertions |
| `tests/Feature/Catalog/CsvImportTest.php` | Same run | 16:33 | 1 min 12 s | `PASS`, 20 tests, 5,204 assertions |
| `tests/Feature/Catalog/ProductSearchTest.php` | Same run | 16:34 | 52 s | `PASS`, 16 tests, 610 assertions |
| `tests/Feature/Inventory/ConfirmReceivingTest.php` | Same run | 16:35 | 47 s | `FAIL`: 12 of 14 tests passed; two defects of the test (finding F-5); `LedgerTablesTest`, after it, did not run, and stands on fast run 1, its file and the code it exercises unchanged since (CE-45) |
| The whole command contract and the image checks | CI on the pull request's final head | — | — | `NOT_RUN` (D-117) |

## 3. What was built (contract section 4.1)

1. **The eight tables** (`database/migrations/2026_11_02_000002_create_ledger_receiving_tables.php`, grants in `database/privileges.php`), with the deferred keys `receiving_lines_movement_fk` and `stock_movements_cost_evidence_presence_fk`, the key of the movements to `products (id, base_unit, quantity_precision)` with `ON UPDATE RESTRICT`, and an index on every key column that no other key leads (SCHEMA section 11).
2. **Inventory:** `StockLedger` (the only writer of `stock_movements` and `stock_balances`), `StockPosition`, `StockState`, `PostingGuard`, `PostingBlocks`, `Receivings`; `OnboardingState::lockShared`; `Catalog\Products::lockForPosting` with `GuardedProduct`.
3. **Costing:** `AcquisitionCost`, `CostEvidence` (the only writer of `acquisition_cost_evidence`), `ProductValuation`, `InventoryValuations` (the only writer of `inventory_valuations`).
4. **RC-07:** `ConfirmReceiving`, `ReceivingLine`, `ConfirmReceivingRequest`, `ConfirmReceivingController`, the route, its line last in `CommandActions::ACTIONS`, and the keys `ux.validation.lines_min`, `lines_max`, `zero_evidence` and `costing.field.zero_disabled`, copied from COPY_DECK.
5. **RD-01 stock per role:** `Queries\Inventory\ProductStock` and `ProductStockData`, composed by `SearchController`; `unitLocked` on RP-302 and RP-304 through `ProductDetailData::withUnitLocked`.
6. **RACE-05's refusal:** `UpdateProduct` reads `StockLedger::hasMovement` under its lock and refuses a change of unit or precision at its fields with `catalog.field.unit_lock_help` (TASK-015 finding F-3).
7. **Fixtures and harness:** `TokoContoh::receive`, `stock`, `postingBlock`, `reopenPostings`; `Tests\Harness\Inventory\PostingBlockScenario`; `CommandIdentities::insertProcessCommand` (EXC-45).
8. **Tests:** L1 `tests/Unit/Costing/ProductValuationTest.php`, `AcquisitionCostTest.php`, `tests/Unit/Inventory/StockPositionTest.php`, `StockStateTest.php`; L2 `tests/Feature/Inventory/LedgerTablesTest.php`; L2 and L4 `tests/Feature/Inventory/ConfirmReceivingTest.php`; L3 `tests/Concurrency/ReceivingRaceTest.php`.
9. **Guard tests widened**, each for what this Task adds, as reported to the reviewer: `CapabilityMatrixTest` (RC-07's row), `RouteInventoryTest` (RC-07 with CAP-11 and `throttle:command`), `PrivilegeMatrixTest` (the eight tables), `OperationsTest` (RC-07's Action), `SignInSessionTest::counts` (the key column of the three tables without `id`), `Tests\Harness\Channels\Allowlists` (RD-01's `stock` and `commands.refused.lines`), `ProductSearchTest` (section 3.1's exact answer gains `stock`; a new test of the stock per role), `CatalogPagesTest` (a new test of `unitLocked`), `CatalogCommandsTest` (a new test of RC-18 after the first movement) and `CsvImportTest` (an import writes no stock or cost row, and RD-01 reads its products' stock as zero).

## 4. Technical corrections of specifications (EXC-81)

Each records what the code does and changes no business meaning:

- INTERACTION_CONTRACTS section 3.1: the stock of a row is the object `stock`, with `onHand` for the cashier's restricted cell of CAP-14 and `onHand`, `minimum` and `state` for a full one; RD-06 carries none.
- INTERACTION_CONTRACTS RC-07: UNKNOWN takes neither an amount nor `zeroEvidence`, and `zeroEvidence` goes with a zero alone; an explicit zero is the Owner's, refused from anyone else with `costing.field.zero_disabled` (INFORMATION_ARCHITECTURE section 14); `expected.products` is a list of `{productId, version}` naming each product of the lines once.
- ROUTE_CONTRACTS RD-01: its handler adds the stock of `Queries\Inventory\ProductStock`.
- SCHEMA section 5.8: the evidence's quantity is bounded above by a movement's range, as the migration checks; the sentence that left the confirmation of an Ops-entered zero to RF-5 now says that only the Owner enters one in V1.
- `ConfirmReceiving`'s comment cites INFORMATION_ARCHITECTURE section 14, where the statement is, not section 6.

## 5. Readings and technical choices

1. `expected.products` is a list of `{productId, version}`, each product of the lines exactly once. A map keyed by the product's identifier would meet the channel allowlist's six-digit index.
2. The refusal order under the locks follows RC-07: `NOT_FOUND`, `PRODUCT_INACTIVE`, `POSTING_BLOCKED`, `STALE_VERSION`, then the precision at its fields. `lines` names every line concerned, by its index in the request.
3. An explicit zero is the Owner's alone (BR-COST-04, REQ-INV-04, INFORMATION_ARCHITECTURE section 14, UXI-236): staff are refused at the amount with `costing.field.zero_disabled`. The check stands first in the Action, before the onboarding lock, since it depends on the actor alone, whom the actor guard already holds.
4. COPY_DECK has no copy for a cost field (TASK-003 F-7). An amount reads `ux.validation.money` and `money_max`, as `CanonicalNumberInputTest` reads them; a balance past its range reuses `quantity_max`, a value past its range `money_max`. A document date that is not a date of the calendar is refused without a key of its field (finding F-2).
5. The module boundary (STK-13, TASK-017 F-1): Catalog does not read Inventory. RD-01's stock and `unitLocked` come from `Queries\Inventory\ProductStock`, composed by the web layer, in one bounded statement more; RD-06 stays without stock. STK-11: Inventory does not call Costing; the Action calls both. `UpdateProduct` is an Action, which may call Inventory (STACK_AND_MODULES section 2). Costing may not use `CommandRefused`, so it throws the kernel's exceptions, which the Action converts. No dependency is new to STACK_AND_MODULES section 2.1.
6. The attention state is null for an inactive product (INVENTORY section 10, UXI-162). CAP-14 `Full` gets the three fields; `Restricted` gets `onHand` alone. The cashier cannot reach RD-01 until TASK-028, so a test of the Query proves it.
7. The publication number grows by one per movement, and `last_sequence` is the movement reflected: two lines of one product publish twice.
8. Balances and valuations are computed in memory first, then written, then the facts appended, so that a value past its range refuses the whole command before any write. A movement bumps no product version, so RC-18 reads `hasMovement` under its `FOR UPDATE`.
9. One audit event per document, `RECEIVING_CONFIRM`, its costs in `owner_only_data` (BR-AUD-02). `product_id` is set only for a document of one product (finding F-4).
10. `CommandIdentities::insertProcessCommand` serves the block fixture and SC-01 of TASK-036.
11. Only `ACTIVE` freezes postings. Only TASK-035 starts onboarding, so the test sets the state directly as the runtime role.
12. No index beyond the keys' indexes; RP-404's list index is TASK-022's if it needs one.
13. A document date is any date of the calendar (a pattern and `date`; the year 0000 refused), stored apart.
14. The evidence's `reference` copies the document's reference.
15. AC-021-07 is proven with a clock that stands still, bound after the sign-in, and the same instant as the web server's received time (`REQUEST_RECEIVED_AT`, TR-TIME-03), so that the hard end compares one time with itself. The sign-in session's limits read the same clock (`App\Http\Auth\SignInSession`), so the test keeps it bound to its end (finding F-5).
16. Version-sensitive facts (EXC-03) are proven on PostgreSQL 18.6 by the tests, since the contract names no MCP server: a key with `ON UPDATE RESTRICT` answers SQLSTATE 23001 (`LedgerTablesTest`); `ORDER BY id FOR NO KEY UPDATE` locks the rows in that order, so a posting holds its lower product while it waits for the higher (`ReceivingRaceTest`); under READ COMMITTED a row lock that waited reads the row as the other command committed it (RACE-05's and RACE-24's tests).

## 6. Findings

- **F-1.** PHPStan reads a value of the database as `mixed`, which `Cost::fromDatabase()` does not take; the test now passes it through `text()`. A fast run was spent on it.
- **F-2, for the next change control of COPY_DECK:** no key names an invalid date of a document, nor a cost's own message (reading 4). TASK-022's date field is a date picker, so a page never sends one.
- **F-3, for TASK-022:** RT-12's JSON body of 1 MiB holds 500 lines of ordinary receipts many times over, but not 500 lines that each carry close to 1,000 characters of evidence outside ASCII: such a body is refused whole. Only the Owner enters such evidence; the page may warn before that.
- **F-4, for TASK-042:** the audit's product filter finds a document of one product only (reading 9).
- **F-5.** Fast run 3 failed two tests of `ConfirmReceivingTest`, both defects of the test: the audit writer reads the clock again after the facts, so its instant is not theirs (the test now asserts that it is not earlier); and the test of AC-021-07 bound the real clock again after its clock in the past, which `SignInSession` read as 16 days without activity, so the next request was signed out (401). A test that binds a clock keeps it to its end. A fast run was spent on them.
- **For TASK-022:** the `receiving` and `stock` contexts, a row's stock state, RP-406's location `/stock/receivings/{document}`, RP-404's index; `unitLocked` is true on RP-302 and RP-304 from a product's first movement.
- **For TASK-023:** `StockPosition::moved` takes a negative quantity; `ProductValuation::increased` starts a new pool at zero; the issue presence key.
- **For TASK-028:** the cashier's stock through `Grant::Restricted`.
- **For TASK-035:** G-10's race against a start of onboarding, through `OnboardingState::lockShared`.
- **For TASK-036:** SC-01 through `insertProcessCommand`, RC-16, and RACE-24 with the real reconciliation.

Earlier findings this Task closes: TASK-015 F-3 (RC-18's refusal at the field), TASK-017 F-2 (`unitLocked`, server side) and F-3 (a row's stock, ready for TASK-022); AC-019-03's stock tables now exist and `CsvImportTest` holds it.

## 7. Definition of Done (TEST_STRATEGY section 10)

| Dimension | Result | Reason |
| --- | --- | --- |
| Business | PASS, on the fast run | AC-021-01 to AC-021-08 below; REQ-INV-01, REQ-INV-03, REQ-INV-04, REQ-INV-13, REQ-CAT-06, REQ-COST-03 |
| UI and UX | N/A | No page or control (contract section 9) |
| Backend | PASS, on the fast run | The FormRequest's allowlist and keys, the actor guard and the checks under the locks, the DTO of RD-01 by CAP-14 and its channel allowlist, L4 tests |
| Database | PASS, on the fast run | One additive migration with its REL-20 metadata; the constraint, deferred, privilege and retention families of the eight tables; the composite key's `ON UPDATE RESTRICT` |
| Security | PASS, on the fast run | CAP-11 at the route and in the Action, the cashier and a visitor refused, the route inventory and the capability matrix; no cost in RD-01 or in the audit's staff part; no secret; the static checks |
| Data integrity | PASS, on the fast run | Append-only facts; the balance the sum of the movements after every commit; atomic audit and result; RACE-01, RACE-02, RACE-05, RACE-24, a deactivation in both orders, crossing postings without a deadlock; failure injection after each of the 16 write steps, for first and for later movements |
| Performance | PASS | Each read bounded: RD-01's page and one statement of stock for it; a document of at most 500 lines; the budgets are measured at TASK-048 |
| Operations | PASS | Six of the tables in the backup manifest's counts (section 12); no runbook or status impact |
| Verification | PASS, subject to CI on the final head | Self-review, the review of class A (section 10); a fast run green on the reviewed code (section 2); the whole command contract and the image checks run on the pull request's final head (CE-41, D-117) |

## 8. Acceptance criteria

| Criterion | Status | Where |
| --- | --- | --- |
| AC-021-01 | PASS on the fast run | `ConfirmReceivingTest`: one RECEIPT per line, the balance the sum of the movements after every command, the scan that finds `stock_balances` written by `StockLedger` alone; `LedgerTablesTest`: no update or delete of a fact, the balance's columns alone |
| AC-021-02 | PASS on the fast run | `ConfirmReceivingTest`: KNOWN and UNKNOWN, UNKNOWN never zero, the Owner's explicit zero with its evidence, staff refused, no route before RC-07; `LedgerTablesTest`: no draft table, the checks of status and amount |
| AC-021-03 | PASS on the fast run | `ReceivingRaceTest`: RACE-01 and RACE-02 with the same and another payload; `ConfirmReceivingTest`: the envelope, a repeat, a status check, the failure after each of the 16 write steps |
| AC-021-04 | PASS on the fast run | `ProductValuationTest` (L1) and `ConfirmReceivingTest` (L2): section 9 below |
| AC-021-05 | PASS on the fast run | `ReceivingRaceTest`: RACE-05 in both orders; `CatalogCommandsTest`: the refusal at the fields; `LedgerTablesTest`: the composite key refuses the change with 23001 |
| AC-021-06 | PASS on the fast run | `ConfirmReceivingTest`: a blocked product refused with its lines, another product posting, the reopening; `ReceivingRaceTest`: RACE-24 in both orders |
| AC-021-07 | PASS on the fast run | `ConfirmReceivingTest`: a future, a past and no document date, each fact at the server's instant, the sequence in the posting order |
| AC-021-08 | PASS on the fast run | `LedgerTablesTest`: the constraint, deferred, privilege and retention families of the eight tables; a RECEIPT without its evidence and a line without its movement fail at commit |

## 9. The ADR-008 cases covered

Of the test list in ADR-008's consequences, the cases of receipts:

| Case | Where |
| --- | --- |
| The L-15 receipts: 10 units for Rp100.000 and 10 for Rp140.000 give 20 units worth Rp240.000, average Rp12.000 | `ProductValuationTest` (L1); `ConfirmReceivingTest`, in one document and in two (L2) |
| Section 4.1's increase: an UNKNOWN cost makes the value UNKNOWN and a later KNOWN receipt leaves it so while stock remains; stock at zero starts a new pool | L1 and L2; the new pool after an issue in L1 only, since a receipt never takes stock to zero (TASK-023) |
| Section 5: sums exact, rounded nowhere, the average one division of them | `ProductValuationTest`; the average of L-15 from the stored value in `ConfirmReceivingTest` |
| Invariants 1, 4 and 5: facts insert-only, publication atomic, UNKNOWN never zero | The privilege family; the failure injection, which leaves every valuation row as it was; the checks of status and value and the tests of UNKNOWN |

The cases of issues, returns, the replay, the reversal and the rounding example belong to TASK-023, TASK-025, TASK-026 and TASK-033.

## 10. Review (CE-40)

Not run at this writing.

## 11. The guard's candidates (CE-31)

The first preview, before the stop, gave one candidate of the kind `number16` in `LedgerTablesTest`: the 16-digit limit of a valuation's overflow case. It was synthetic, no real finding; the test builds the value with `str_repeat`, and the next preview passed with no candidate. `guard.sh` on `b531068`, `3296ca2` and `d6b1414`, and `guard.sh --preview` before each, list no candidate: no address, host, e-mail or home path.

## 12. Operations (EXC-47)

`COUNTED_CLASSES` are F, FS, M and S, through the privilege matrix, so `stock_movements`, `receiving_documents`, `receiving_lines`, `acquisition_cost_evidence`, `inventory_posting_blocks` and `inventory_posting_block_reopenings` are in the backup manifest's counts. `stock_balances` and `inventory_valuations`, class P, are in the dump and not counted. No runbook or status impact.

## 13. For later Tasks

Section 6. The Task that depends directly on this one: TASK-022, `READY` at this merge.
