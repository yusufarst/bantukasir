# TASK-021 evidence — Stock ledger, valuation and receiving (server)

| | |
| --- | --- |
| Task | [TASK-021](../tasks/TASK-021-ledger-receiving.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01) |
| Executor | Agent `bk-executor-max`, model `claude-opus-5-5`, effort Max from the table of EXECUTION_CONTEXT section 19; review by `bk-reviewer-max`, verification by `bk-reviewer-xhigh` |
| Branch and base | `task/TASK-021-ledger-receiving`, from `b363c634106cc29b06a7eb208349339b5542bdb3` (origin/develop) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`; the stash of D-030; TASK-016 `DONE`, no gate; PHP and `tsc` do not run on this machine, so every check runs in CI (D-108, D-117) |
| Status of this file | **In progress.** Written at the Owner's stop of section 1; the sections that a close needs are listed in section 4 |

## 1. The Owner's stop

The Owner ordered a lossless pause on 2 November 2026, about 13:59 (Asia/Jakarta), because he is shutting down the laptop. It is a pause of this Task in its build, not a finding against it.

| Subject | State |
| --- | --- |
| Phase before the stop | `BUILD` (CE-24: the run continues from it) |
| What this checkpoint holds | All the production code of section 3, the unit tests, the harness, `tests/Feature/Inventory/LedgerTablesTest.php` and the guard-test updates of section 4 item 1 done so far |
| What is not written | The tests, documents and records of section 4, listed one by one |
| What has run | No check of this Task has run: section 2 |
| Review | Not started; no reviewer was running at the stop |
| Pull request | None. It opens only after the review and the record (CE-32) |
| Merged | Nothing. TASK-021 stays `READY` in TASK_PLAN section 4 on `develop` |

**To resume (CE-72):** the Owner has `bash scripts/executor/state.sh resume` run in a Desktop session in this folder and approves its prompt. The tick then delegates this Task in mode `resume`. The executor reads this file and continues at section 4 item 1.

## 2. Checks

Times are Asia/Jakarta, 2 November 2026. PHP, PostgreSQL, the browser and every container run only in GitHub Actions (D-108, D-117). The results are recorded here as they arrive (EXC-61).

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `composer validate`, `lint`, `analyse`, `test:unit` | — | — | — | `NOT_RUN` |
| `npm run lint`, `typecheck`, `test:unit` | — | — | — | `NOT_RUN` |
| `tests/Feature/Inventory/LedgerTablesTest.php` and the other changed test files | — | — | — | `NOT_RUN` |
| The whole command contract and the image checks | CI on the pull request's final head | — | — | `NOT_RUN` (D-117) |
| Guard, preview on the working tree | Local | 14:01 | under 1 min of scanning | `PASS`, after the candidate of section 2.1 was removed |

The push of this checkpoint starts one fast run, the first of the 10 that CE-46 allows. By the Owner's order it was not awaited. Its result is not part of this file yet: read it on resume with `gh run list --branch task/TASK-021-ledger-receiving`. Expect it to be able to fail: this code has never met PHPStan, Pint or PostgreSQL, and `LedgerTablesTest` was never run. A failure there is the next thing to fix, not a surprise.

### 2.1 Publication guard

The first preview gave one candidate, of the kind `number16`, in `LedgerTablesTest`: the 16-digit limit value of a valuation's overflow case, one followed by fifteen zeros. It is synthetic and no real finding. The test now builds that value with `str_repeat`, the literal is gone, and the second preview passed with no candidate. The guard on the checkpoint's commit and the push are reported at the stop, because this file cannot name its own commit.

## 3. What is built so far (contract section 4.1)

None of this has been executed yet.

1. **The eight tables** (`database/migrations/2026_11_02_000002_create_ledger_receiving_tables.php`, grants in `database/privileges.php`), with the deferred keys `receiving_lines_movement_fk` and `stock_movements_cost_evidence_presence_fk`.
2. **Inventory:** `StockLedger` (the only writer of `stock_balances`), `StockPosition`, `StockState`, `PostingGuard`, `PostingBlocks`, `Receivings`; `OnboardingState::lockShared`; `Catalog\Products::lockForPosting` with `GuardedProduct`.
3. **Costing:** `AcquisitionCost`, `CostEvidence`, `ProductValuation`, `InventoryValuations` (the only writer of `inventory_valuations`).
4. **RC-07:** `ConfirmReceiving`, `ReceivingLine`, `ConfirmReceivingRequest`, `ConfirmReceivingController`, the route, its line last in `CommandActions::ACTIONS`, and the keys `ux.validation.lines_min`, `lines_max`, `zero_evidence` and `costing.field.zero_disabled`.
5. **RD-01 stock per role:** `Queries\Inventory\ProductStock` and `ProductStockData`, composed by `SearchController`; `unitLocked` on RP-302 and RP-304 through `ProductDetailData::withUnitLocked`.
6. **RACE-05's refusal:** `UpdateProduct` reads `StockLedger::hasMovement` under its lock and refuses a change of unit or precision at the field.
7. **Fixtures and harness:** `TokoContoh::receive`, `stock`, `postingBlock`, `reopenPostings`; `PostingBlockScenario`; `CommandIdentities::insertProcessCommand`.
8. **Tests written:** `tests/Unit/Costing/ProductValuationTest.php`, `AcquisitionCostTest.php`; `tests/Unit/Inventory/StockPositionTest.php`, `StockStateTest.php`; `tests/Feature/Inventory/LedgerTablesTest.php`.
9. **Guard tests updated:** `Allowlists` (the stock of RD-01, `commands.refused.lines`), `SignInSessionTest::counts`, `PrivilegeMatrixTest`, `OperationsTest`, `RouteInventoryTest`.

## 4. What is unfinished, in order

**1. The guard tests still to update.** Report each widened test to the reviewer and here.

- `tests/Feature/Access/CapabilityMatrixTest.php`: add `'RC-07' => ['method' => 'POST', 'path' => '/commands/receiving.confirm', 'json' => true, 'answers' => ['guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [403, null], 'combined' => [422, null]]]`, after RC-30, with a comment naming TASK-021.
- `tests/Feature/Catalog/ProductSearchTest.php`: the exact JSON of `test_the_fields_are_those_of_section_3_1_for_each_role_and_never_a_cost` gains `'stock' => ['onHand' => '0', 'minimum' => '0', 'state' => 'OUT']`. A new test: the states by role with a minimum of 5 (6 `NORMAL`; 5 and 1 `LOW`; 0 `OUT`), a minimum of 0, inactive gives a null state, decimals, the channel check, RD-06 without stock, `app(ProductStock::class)->added($page, Grant::Restricted)` gives `onHand` only, `Grant::None` gives no stock, and a cashier's `Capabilities::of(..., Capability::Cap14)` is `Restricted`.
- `tests/Feature/Catalog/CatalogPagesTest.php`: `unitLocked` is true after a receipt, on RP-302 and RP-304, with the channels.
- `tests/Feature/Catalog/CatalogCommandsTest.php`: RC-18 after the first movement is refused at `payload.baseUnit` and `payload.quantityPrecision` with `catalog.field.unit_lock_help`, nothing written; an edit of the name alone passes; a new product has no stock rows.
- `tests/Feature/Catalog/CsvImportTest.php`: no rows in the stock and cost tables (AC-019-03).
- Look again for other guard or inventory tests that this change touches indirectly: TASK-016 and TASK-019 found theirs only on the pull request's run.

**2. `app/Actions/Inventory/ConfirmReceiving.php` line 36** cites "INFORMATION_ARCHITECTURE section 6". The statement is at line 496 of `docs/07-ux-design/INFORMATION_ARCHITECTURE.md`: verify the section number or drop it.

**3. `tests/Feature/Inventory/ConfirmReceivingTest.php`** (L4 and L2, AC-021-01 to AC-021-07):

- The commit with its facts: movements, sequences, the unit copied, evidence, the balance equal to the sum of the movements, the valuation, an audit with no cost in `change_data`, a result of references only, the receipt `COMMITTED`, the channel check `commands.committed`.
- Several lines of one product. KNOWN and UNKNOWN: never zero, and it stays UNKNOWN.
- An explicit zero: the Owner with evidence; without evidence `ux.validation.zero_evidence`; Agus and Dewi `costing.field.zero_disabled`.
- The L-15 receipts (100000 + 140000 = 240000) and the publication numbers.
- A document date, past and future, moves no fact (bind `new TestClock(...)` with `$this->app->instance(Clock::class, ...)` after the sign-in; the session middleware does not use `Clock`).
- Refusals, each writing nothing: `ONBOARDING_FREEZE` (set ACTIVE directly as the runtime role, because only TASK-035 can do it), `POSTING_BLOCKED` (another product posts; after `reopenPostings` it posts again; a new block refuses again), `PRODUCT_INACTIVE`, `STALE_VERSION` with `lines` and the channel `commands.refused.lines`, `NOT_FOUND`, precision at the field.
- The validation table with its keys. 500 lines accepted, 501 refused with `ux.validation.lines_max` (`maks` 500).
- A balance overflow (101 lines of 99999999.999) and a value overflow (fixture through `InventoryValuations::publish`).
- The envelope's refusals, a stale epoch, an idempotency conflict, a repeat (200, `repeated`), the status check.
- Failure injection after every write step: 16 steps for 2 products and 3 lines (`FailurePoints` 1 to 16, 17 commits), for first and for later movements, the receipts `PENDING`. The write order: each product's balance, each product's valuation, the document, then per line the line, the movement and the evidence; the audit; the result.
- Roles: a cashier 403 with no receipt; a guest 401; the Action refuses with `ActorNotAllowed` directly.
- No draft table or route. Only `StockLedger` writes `stock_balances` and only `InventoryValuations` writes `inventory_valuations` (a static scan of `app/`).
- The bucket `throttle:command` allows 60 a minute: call `$this->as(person)` inside large loops, which flushes the cache.

**4. `tests/Concurrency/ReceivingRaceTest.php`** (L3), with `UserCommandScenario` and `PostingBlockScenario`:

- RACE-01, the same key with the same payload (a repeat) and with a different one (`IDEMPOTENCY_CONFLICT`).
- RACE-02, another key on the same intent, with the same and a different payload.
- RACE-05, edit first: RC-18 at the barrier `after_lock:6`, RC-07 blocked, then `STALE_VERSION`, no movement. Posting first: RC-07 at the barrier, RC-18 blocked, then `VALIDATION_FAILED`, the unit unchanged.
- RACE-24, block first gives `POSTING_BLOCKED`; posting first, both commit and the next posting is refused.
- Deactivation in both orders.
- The ascending lock order: A holds P2 at the barrier, B with lines [P2, P1] blocked, C on P1 blocked because B holds P1; no deadlock.
- Two receipts of one product serialize, sequences 1 and 2.

**5. Documents (EXC-81, technical corrections):**

- `docs/03-workflows/INTERACTION_CONTRACTS.md`: RC-07's expected shape, `zeroEvidence` the Owner's alone, the `stock` fields of section 3.1.
- `docs/03-workflows/ROUTE_CONTRACTS.md`: RD-01's handler with `Queries\Inventory\ProductStock`.
- `docs/04-architecture/SCHEMA.md`: the zero note's workflow sentence in V1; the evidence quantity's bound, `> 0 AND <= 99999999.999`, stronger than `> 0`; indexes if needed.
- A note in `docs/04-architecture/STACK_AND_MODULES.md` or here on the web layer's composition (reading 5).

**6. The flow to the end:** a checkpoint, the guard and a push, each in its own Bash call; the fast run green (at most 10, CE-46); self-review; `state.sh phase REVIEW`; `review-pack.sh --base b363c634106cc29b06a7eb208349339b5542bdb3 --round 1`; `bk-reviewer-max` once in the foreground; the fixes; `bk-reviewer-xhigh` only if a MUST-FIX was raised.

**7. The record (CE-50):**

- This file's close: the checks with where they ran, what was built, corrections, readings, findings, the nine dimensions of the Definition of Done, AC-021-01 to AC-021-08, the ADR-008 cases covered, the review record, the guard's candidates.
- The previous merge in TASK-020's evidence, a section "11. Merge" in the format of TASK-019's section 12: pull request #29, head `b489f7332822633faf79ce87c64c7afb4d8b8b25`, CI run 41391100676; squash commit `b363c634106cc29b06a7eb208349339b5542bdb3` on `develop`, CI run 41394247420; the tick's merge, automated review `DONE`; the tree equality by read-only Git.
- TASK_PLAN: TASK-021 `DONE`, TASK-022 `READY`, the totals `TODO` 27, `READY` 2, `IN_PROGRESS` 0, `BLOCKED` 0, `VERIFYING` 0, `DONE` 21, `SUPERSEDED` 0 (recount them).
- CURRENT_HANDOFF last: remove the note of this stop; the status block; the facts of TASK-020 with pull request #29 and `b363c63`; the TASK-021 bullet; the `READY` list; sections 1.1, 1.2, 6, 7, 8, 9; the next Task TASK-022 at effort High, `bk-executor-high`, `bk-reviewer-high`, `bk-reviewer-high`.

Then the publish of step 6, `pr.sh open` with a body file outside the tree, and `state.sh phase CI`.

## 5. Readings

1. `expected.products` is a list of `{productId, version}`, each product of the lines exactly once. A map keyed by id would hit the channel allowlist's six-digit index.
2. The refusal order under locks follows RC-07: `NOT_FOUND`, `PRODUCT_INACTIVE`, `POSTING_BLOCKED`, `STALE_VERSION`, then precision at the field. `lines` names every line concerned.
3. An explicit zero is the Owner's alone (REQ-INV-04, INFORMATION_ARCHITECTURE, UXI-236): staff are refused at the amount with `costing.field.zero_disabled`, under the actor's lock.
4. COPY_DECK has no copy for a cost field (TASK-003 F-7). An amount reads `ux.validation.money` and `money_max` (precedent `CanonicalNumberInputTest`). A balance overflow reuses `quantity_max`, a value overflow `money_max`. A finding for the next change control.
5. The module boundary (STK-13, TASK-017 F-1): Catalog does not read Inventory. RD-01's stock and `unitLocked` come from `Queries\Inventory\ProductStock`, composed by the web layer, in one extra bounded statement. RD-06 stays without stock. STK-11: Inventory does not call Costing; the Action calls both. Costing may not use `CommandRefused`, so it throws kernel exceptions that the Action converts.
6. The attention state is null for an inactive product (INVENTORY section 10, UXI-162). CAP-14 `Full` gets all three fields; `Restricted` gets `onHand` only: the cashier cannot reach RD-01 until TASK-028, so the Query proves it.
7. The publication number grows by one per movement. `last_sequence` is the movement reflected.
8. Balances and valuations are computed in memory first, then written, then the facts appended. A movement bumps no product version, so RC-18 must read `hasMovement` under `FOR UPDATE`.
9. One audit event per document. `product_id` is set only for a single-product document (a finding for TASK-042's product filter).
10. `CommandIdentities::insertProcessCommand` was added for the fixture and for SC-01 of TASK-036.
11. Only `ACTIVE` freezes postings.
12. No index beyond the keys' indexes. RP-404's list index is left for TASK-022 if it needs one.
13. A document date is any calendar date (a pattern and `date`; year 0000 refused), stored apart.
14. The evidence's `reference` copies the document's reference.

These assumptions in `LedgerTablesTest` have not met the database yet, and the fast run will test them. PostgreSQL evaluates a row's checks in alphabetical order of their names, so each case isolates the check it names. On 18.6 an `ON UPDATE RESTRICT` key reports SQLSTATE 23001, not 23503: this is from memory of PostgreSQL's source, and no earlier test here asserts it.

## 6. Findings for later Tasks

- **TASK-022:** the `receiving` and `stock` contexts, the stock state of a row, RP-406's location, RP-404's index.
- **TASK-023:** `StockPosition::moved` handles negatives; `ProductValuation::increased`'s new pool; the presence keys.
- **TASK-028:** the cashier's stock through `Grant::Restricted`.
- **TASK-035:** G-10's race, `OnboardingState::lockShared`.
- **TASK-036:** SC-01 through `insertProcessCommand`, RC-16, RACE-24 final.
- **TASK-042:** the audit's product filter.

Earlier findings this feeds: TASK-015 F-3 (RC-18's refusal at the field), TASK-017 F-2 (`unitLocked`, server side) and F-3 (a row's stock, available for TASK-022); AC-019-03's stock tables now exist.

## 7. Operations (EXC-47)

`COUNTED_CLASSES` are F, FS, M and S, through the privilege matrix, so `stock_movements`, `receiving_documents`, `receiving_lines`, `acquisition_cost_evidence`, `inventory_posting_blocks` and `inventory_posting_block_reopenings` are in the manifest's counts. `stock_balances` and `inventory_valuations`, class P, are dumped but not counted. No runbook or status impact.
