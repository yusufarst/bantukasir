# TASK-028 evidence — Sale checkout (server)

| | |
| --- | --- |
| Task | [TASK-028](../tasks/TASK-028-checkout.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01) |
| Executor | Agent `bk-executor-max`, model `claude-opus-5-5`, effort Max from the table of EXECUTION_CONTEXT section 19; review by `bk-reviewer-max`, verification by `bk-reviewer-xhigh` |
| Branch and base | `task/TASK-028-checkout`, from `6e8165544ba87de3c68b647be00d0c09ec236156` (origin/develop, the squash merge of TASK-027) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`; no branch or pull request of TASK-028; the executor state at `BUILD` for TASK-028 with this session's lock; TASK-016, TASK-023, TASK-025, TASK-026 and TASK-027 `DONE`, no gate; PHP, `tsc` and ESLint do not run on this machine, so every check but the guard runs in CI (D-108, D-117) |

## 1. Checks

Every check runs in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta, 8 November 2026.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck`, `npx eslint` | Local | — | — | `NOT_RUN`: `tsc` and ESLint are not installed on this machine; no TypeScript changed |
| Guard (Gitleaks 8.30.1, the secret scan, identity, candidates) | Local, on `f7c69fd` | 18:18 to 18:22 | about 4 min | `PASS`, no candidate |
| `composer validate`, `lint` | Fast run 1, 42475514675 on `f7c69fd` | 18:23 to 18:27 | about 4 min | `FAIL`: Pint passed on 749 files, the translation keys and the migration review passed; the check of the PostgreSQL parts could not list the tests, because PHPUnit could not load `CheckoutRecordsTest` (finding F-7); the later steps did not run |
| Guard | Local, on `171fac9` | 18:28 to 18:32 | about 4 min | `PASS`, no candidate |
| Start of the local environment | Fast run 2, 42476194681 on `171fac9` | 18:32 to 18:37 | about 4.5 min | `FAIL`: the application container exited with code 100 at its first start, Composer's code of a transport failure in `composer install` (finding F-8); no check ran |
| Guard | Local, on `ea48c38` | 18:38 to 18:41 | about 3 min | `PASS`, no candidate |
| `composer validate`, `lint` (Pint, the translation keys and types, the migration review, the parts of `test:postgres`, the secret scan, the audits) | Fast run 3, 42476896749 on `ea48c38` | 18:42 to 18:46 | about 4 min | `PASS` |
| `composer analyse` | Same run | 18:46 | 14 s | `FAIL`: four findings of PHPStan in one test, `CheckoutReplayTest` (finding F-9); none in `app/`; the later steps did not run |
| `composer test:postgres` whole, `test:concurrency` whole, the browser suite, the build, the image checks | The pull request's final head | — | — | `NOT_RUN` (CE-44, D-117) |

## 2. What was built (contract section 4.1)

| Item | Where |
| --- | --- |
| 1. The six tables — `sales`, `sale_lines`, `payments`, `receipt_snapshots` (class F), `sale_correction_totals`, `sale_line_correction_totals` (class P, their amounts alone updated) — and `sale_number_seq` (`CACHE 1`, `NO CYCLE`; runtime `USAGE` only); the keys of EXC-44: `stock_movements (sale_line_id, product_id, line_quantity)` → `sale_lines (id, product_id, quantity)`, `issue_cost_allocations (sale_line_id, issued_quantity)` → `sale_lines (id, quantity)`, the deferred key from each Sale line to its movement, and the deferred keys from a Sale to its Payment, its receipt snapshot and its running total | `database/migrations/2026_11_08_000001_create_sales_tables.php`, `database/privileges.php` |
| 2. RC-01 with the locks of the family Sale checkout: the identity; the actor (share); onboarding (share, 2.3); the BusinessProfile pointer (share, 2.4); the actor's own OPEN session (update, 4; its activity version + 1); the products ascending (6); balances and valuations (7); the number and the clock at step 8, next to the Sale's insert. Under the locks, in the order of RC-01: onboarding off, a revision in force, the session OPEN and the actor's, each product found, active and not blocked, each quantity within its product's precision, each price revision the one reviewed (`PRICE_CHANGED` with the price in force and its revision), the stock of each product's lines (`INSUFFICIENT_STOCK` with the stock available), the payment: CASH tendered at least the total (`TENDER_INSUFFICIENT`), change and applied amount; BANK_TRANSFER the exact total with an optional reference, never pending | `App\Actions\Sales\CheckoutSale`, `CheckoutPayload`, `CheckoutLine`; `App\Domain\Sales\Sales`, `PaymentMethods`, `SalePayment`, `SaleNumber`, `PricedLine`, `SaleReference`; `App\Domain\Cash\Sessions::lockForSale`, `recordActivity`, `GuardedSession`; `App\Domain\Catalog\Products::lockForSale`, `SaleProduct`; `App\Domain\Inventory\PostingGuard::requireActiveAndUnblocked`, `StockLedger::appendSaleIssue`; `App\Domain\Configuration\BusinessProfiles::lockCurrentShared`; `App\Http\Controllers\Sales\CheckoutSaleController`; `routes/web.php`; `CommandActions` |
| 3. Refused before any lock: a product named twice (`VALIDATION_FAILED` at each repeated line, never merged), more than 200 lines, a method outside the list (`METHOD_NOT_ALLOWED`), every field with its key, and anything the allowlist does not name — a price, a total, a second payment, an amount of the client's | `App\Http\Requests\Sales\CheckoutSaleRequest` |
| 4. Line totals half-up once, the total their sum; the business date and the number at step 8; the lines, the Payment, each line's SALE_ISSUE with its balance, the allocation of every line (UNKNOWN when the valuation is) with its equal projection, the receipt snapshot (the revision in force with its logo, the template `receipt.1`, the cashier's frozen name, the session's number, every line as frozen text, total, method, tendered and change, the customer's name), the running total of later corrections, the audit event `SALE_CHECKOUT` (its allocations the Owner's part) and the result | `CheckoutSale`, `Sales`, `PricedLine::frozen`, `IssueAllocations::record(…, saleLineId:)`, `App\Support\Numbers\Money::lineTotal` |
| 5. The payment boundary of ARC-30 to ARC-33: one place of the methods and their rules; no provider record, table, controller, callback, webhook, polling or asynchronous state | `App\Domain\Sales\PaymentMethods`; proven by `CheckoutSaleTest::test_the_payment_boundary_holds_no_provider_record_route_callback_or_asynchronous_state` |
| 6. RD-01's context `till`: the gate of RD-01 opens for CAP-21 too, whose context `till` alone a cashier passes; it needs the actor's own OPEN session (`NO_OPEN_SESSION`); its rows carry the price revision that RC-01 expects and the stock of the viewer's cell of CAP-14, the quantity alone for a cashier | `ProductSearchRequest`, `routes/web.php` |
| 7. The replay of TASK-025 over SALE_ISSUE: an issue type of the logical ledger, validated against the logical quantity (G-07), counted against the movement cap, its projection rewritten only when its status or amount changes; nothing of a Sale, its Payment, its allocation fact, its receipt or a session touched; checkout holds the product guard in the lock order (RACE-11) | `App\Domain\Costing\Replay\ReplayLedger`, `LedgerEntry`; `IssueAllocations`; `StockLedger::SALE_ISSUE` |
| 8. The Sale side of RACE-18 and RACE-25 | `CheckoutRaceTest`, `CheckoutRecordsTest` |

## 3. Readings and technical choices

| Reading | Why |
| --- | --- |
| RC-01's result leads to RN-01 (`/`) until TASK-029 delivers RP-103 | EXC-52: a destination not yet delivered leads to the role's home (`CheckoutSale::LOCATION`) |
| Without an OPEN session of their own the actor is refused `NO_OPEN_SESSION`, whatever session the cart names; with one, any other session — another user's, one that does not exist, their own once closed — is `SESSION_NOT_OPEN`. Another user's session is never locked, and answers as one that does not exist | RC-01, "the session is OPEN and the actor's own (`SESSION_NOT_OPEN`; `NO_OPEN_SESSION` when the actor has no OPEN session at all)"; SEC-AZ-03 |
| BR-PAY-03's confirmation that the funds were received is the cashier's step in the till before the command (TASK-029, COPY_DECK `sales.payment.transfer_help`); RC-01's payload names no field for it, so the server records the transfer at its commit, never as pending | RC-01's payload table; BR-PAY-07 |
| `customerName` is a field of `payment`, as RC-01's payload table lists it; it is kept on the Sale and its receipt, never in the audit's change data | RC-01; BR-AUD-02 names what the audit carries |
| A BusinessProfile revision missing under the pointer's share lock is `VALIDATION_FAILED` without a field, before any fact | RC-01 lists G-23 under the locks without a code of its own; the bootstrap writes the first revision and no command removes one, so the case is unreachable in use |
| The receipt holds the revision in force by reference with that revision's logo, the template version `receipt.1`, and every line as frozen text — line number, SKU, name, unit, quantity, unit price, line total — never a cost | DOCUMENT_CONTRACTS DC-01, G-17, BR-DOC-01; TASK-031 renders it |
| The audit event names the product only when the Sale has one, as the issues of TASK-023 do; its allocations are the Owner's part (`owner_only_data`) | BR-AUD-02, BR-AUD-04, BR-COST-07 |
| A quantity is judged before the locks at the largest precision of the schema and under its product's lock against the product's own, at the field of each line that it does not fit | TR-VAL-08, BR-INV-06 |
| The till's search is RD-01 with the context `till`, whose rows already carry `price.revisionId` and the stock cell of CAP-14; its gate is `capability:CAP-02,CAP-21`, and only `till` is open to a holder of CAP-21 alone | INTERACTION_CONTRACTS section 3.1; SECURITY_MODEL CAP-02 "in the till", CAP-14 restricted |
| `issue_cost_allocations_sale_line_fk (sale_line_id, issued_quantity)` is implied by the movement's key and the allocation's own keys, so no row violates it alone; `SalesTablesTest` proves the refusal of such a row and reads the key's definition from `pg_catalog` | EXC-44 requires the key; SCHEMA section 9.4 |
| Until RC-03 of TASK-030 exists, the tests close a session as it will leave it — `CLOSED` through the runtime role's privilege — and `SessionCloseScenario` takes the session's lock as the close will, to prove the Sale side of RACE-08 | TASK-028 contributes to RACE-08 (TRACEABILITY); TS-20 |

## 4. Findings

| ID | Finding | Outcome |
| --- | --- | --- |
| F-1 | PHP, `tsc` and ESLint do not run on this machine, so every static check is met first in CI (TASK-011 F-5) | As recorded since TASK-011; the fast runs cover them |
| F-2 | For TASK-029: RC-01's location is RN-01 until RP-103 exists; the till's search and the cart's reviewed revisions are ready on RD-01 `till`; the confirmation of BR-PAY-03 is the till's step | TASK-029 switches `CheckoutSale::LOCATION` to RP-103 and builds the confirmation |
| F-3 | For TASK-030: the close compares the activity version that RC-01 increments under the session's lock (RACE-08), and reads the PENDING or HELD `sale.checkout` receipts of the responsible user (G-27, D-088); `SessionCloseScenario` and `CheckoutSaleTest::closeSession` are stand-ins to replace by RC-03 | TASK-030 |
| F-4 | For TASK-031: the receipt snapshot names its template `receipt.1` and holds every line as frozen text | TASK-031 renders `receipt.1` |
| F-5 | For TASK-033: `sale_line_correction_totals` has no writer yet, and the replay does not yet reach returns linked to Sale issues | TASK-033 writes the line totals and extends the replay (contract section 12) |
| F-6 | RD-01's request, of the Catalog module, reads the Cash module's `OpenSessions` for the till's context, as two Access controllers do since TASK-027 (TASK-027 R-10) | For the Owner with R-10 whether STACK_AND_MODULES 2.1 records it |
| F-7 | Fast run 1 failed `composer lint` at the check of the PostgreSQL parts: the private helper `session()` of `CheckoutRecordsTest` redeclared the public `session()` of Laravel's `TestCase`, a fatal error when PHPUnit loads the class | Fixed in checkpoint 2, test only: the helper is `rinasSession()` |
| F-8 | Fast run 2 never reached a check: the runner's application container exited with code 100 at its first start, where `docker/app/entrypoint-local.sh` runs `composer install`; 100 is Composer's transport failure, and fast run 1 started the same environment from the same image definition | The runner's network, not a defect of this Task; the next checkpoint, this record, started fast run 3 |
| F-9 | Fast run 3 failed PHPStan on the failure injection of `CheckoutReplayTest`: `TokoContoh::correctCost` declares only `DatabaseRefused`, so the catch of the injected failure read as dead and the loop's later statements as unreachable | Fixed in checkpoint 4, test only: the correction runs through the race harness's scenario, which declares no throw type, and the test asserts that its failure is the injected one at each step |

## 5. Tests (contract section 7)

| Test | Where |
| --- | --- |
| L2 families of the six tables and `sale_number_seq`: constraints, deferred keys, presence keys, the keys of EXC-44, privileges, retention, numbering, indexes | `tests/Feature/Sales/SalesTablesTest.php`; `PrivilegeMatrixTest`, `IssueTablesTest`, `LedgerTablesTest` extended |
| L1 the methods, the cash arithmetic of L-17, the line total half-up once, the number | `tests/Unit/Sales/PaymentMethodsTest.php` |
| L4 RC-01: one Sale with every record in one transaction and one instant; each holder of CAP-21 in its own session by either method, OPERATIONS_ADMIN never; the session; the payment and every other method, shape or price; each definite rejection in RC-01's order with nothing written and no number taken; the fields with their keys, a product named twice, 201 lines and 200 products; the envelope, a repeat and a status check, also after the session closed; the failure after each of 17 write steps and the gap of numbers; a deadlock under the bounded retry; the till's search; PAYEXT01 by review; the one writer of the Sale's tables | `tests/Feature/Sales/CheckoutSaleTest.php`; `CapabilityMatrixTest`, `RouteInventoryTest`, `ProductSearchTest`, `CsvImportTest`, `SignInSessionTest`, `OperationsTest`, `tests/Harness/Channels/Allowlists.php` |
| L2 the L-15 example, FIN01's line total, UNKNOWN never zero, the sell-out and the pool after it, the number across Asia/Jakarta midnight, the receipt's revision and logo (RACE-25 in sequence), a cart line before a barcode move (RACE-18 in sequence), a change after a Sale (RACE-23 in sequence) | `tests/Feature/Sales/CheckoutRecordsTest.php` |
| L1 and L2 the ADR-008 replay cases with Sale issues (section 7) | `tests/Feature/Sales/CheckoutReplayTest.php` |
| L3 RACE-01, RACE-02, RACE-03 (two Sales; a Sale and a manual issue in both orders), RACE-08 (the Sale side, both orders), RACE-11 (both orders and past the lock timeout), RACE-18, RACE-23 (both orders and a deactivation), RACE-25 (both orders), numbering (no wait, allocation order, the gap of a rollback) | `tests/Concurrency/CheckoutRaceTest.php`, `tests/Harness/Sales/SessionCloseScenario.php` |
| Fixtures through the Actions (EXC-45) | `TokoContoh::sell`, `TokoContoh::saleIssueOf` |

## 6. Acceptance criteria

| Criterion | Proof | Result |
| --- | --- | --- |
| AC-028-01 One Sale with its lines, Payment, issues, balances, allocations, receipt snapshot, audit event and result per intent; RACE-01 and RACE-02; a failure after every write step leaves nothing | `CheckoutSaleTest` (the first test, the envelope, the 17 steps), `CheckoutRaceTest` (RACE-01, RACE-02) | See section 1 |
| AC-028-02 The actor's own eligible OPEN session for every role and method; OPERATIONS_ADMIN alone refused; a product named twice or more than 200 lines refused before any lock; the activity version + 1 | `CheckoutSaleTest` (the roles, the session, the fields), `CheckoutRaceTest` (RACE-08) | See section 1 |
| AC-028-03 Rp75.000 with Rp100.000 tendered gives Rp25.000 change and Rp75.000 applied; Rp70.000 `TENDER_INSUFFICIENT`; BANK_TRANSFER the exact total, no pending state; mixed, partial and unknown methods, STATIC_QRIS included, refused; a tampered price refused | `PaymentMethodsTest`, `CheckoutSaleTest` (the payment) | See section 1 |
| AC-028-04 Each definite rejection writes nothing | `CheckoutSaleTest` (the session, the rejections in RC-01's order), `CheckoutRaceTest` | See section 1 |
| AC-028-05 FIN01: sales Rp60.000, cost of goods sold Rp36.000, gross profit Rp24.000; each line's allocation, or UNKNOWN, in the Sale's transaction | `CheckoutRecordsTest` (L-15, UNKNOWN), `CheckoutSaleTest` (the first test, the 17 steps) | See section 1 |
| AC-028-06 Sales of different sessions never wait for a number; unique, in allocation order, a gap after a rollback, the date part across midnight | `CheckoutRaceTest` (numbering), `CheckoutSaleTest` (the gap of 17), `CheckoutRecordsTest` (midnight), `SalesTablesTest` | See section 1 |
| AC-028-07 RACE-03, RACE-23, RACE-25; a cart line before a barcode move keeps its product (RACE-18) | `CheckoutRaceTest`; `CheckoutRecordsTest` in sequence | See section 1 |
| AC-028-08 No provider record, controller, callback, webhook or asynchronous payment state (PAYEXT01, by review) | `CheckoutSaleTest::test_the_payment_boundary_holds_no_provider_record_route_callback_or_asynchronous_state`; the review of section 10 | See sections 1 and 10 |
| AC-028-09 The constraint, deferred and privilege families for the six tables and the sequence | `SalesTablesTest`, `PrivilegeMatrixTest` | See section 1 |
| AC-028-10 The replay reaches Sale issues: KNOWN Rp20.000 published, the fact UNKNOWN, 6 worth Rp30.000; the same twice; a failure during publication changes nothing; the Sale and the session unchanged | `CheckoutReplayTest` | See section 1 |
| AC-028-11 The sell-out ends its pool; the movement cap counts Sale issues; a reversal refused `REPLAY_REJECTED` for a later Sale (G-07); RACE-11 | `CheckoutReplayTest`, `CheckoutRecordsTest`, `CheckoutRaceTest` (RACE-11) | See section 1 |

## 7. The ADR-008 replay cases with Sale issues (contract section 8)

| Case | Test |
| --- | --- |
| A cost completed after a Sale: the Sale line's projection published KNOWN, its fact UNKNOWN; the valuation | `CheckoutReplayTest::test_completing_a_receipts_cost_publishes_the_sale_issue_projection_and_never_its_fact_or_the_sale` |
| A cost corrected before and after Sales: the Sale waits for the replay and takes the corrected valuation; the replay waits for the Sale and recomputes its projection only | `CheckoutRaceTest` (RACE-11, both orders) |
| Idempotence: the same correction twice, no projection rewritten, the valuation published again | `CheckoutReplayTest`, the first test |
| A failure injected during the publication | `CheckoutReplayTest::test_a_failure_during_the_publication_changes_nothing` |
| A sell-out ends its pool, a later known increase starts a new one | `CheckoutReplayTest::test_a_sale_that_empties_the_logical_quantity_ends_its_pool_in_the_replay_too`; at commit, `CheckoutRecordsTest` |
| The movement cap counts Sale issues | `CheckoutReplayTest::test_the_sale_issues_count_against_the_movement_cap` |
| A reversal refused because of a later Sale (G-07) | `CheckoutReplayTest::test_a_reversal_of_a_receipt_whose_units_a_later_sale_issued_is_refused_with_no_change` |
| The Sale's facts, receipt snapshot and session figures unchanged by every replay (FIN03) | `CheckoutReplayTest::saleRows`, compared in each case |

## 8. Definition of Done (TEST_STRATEGY section 10)

| Dimension | Result | Reason |
| --- | --- | --- |
| Business | See section 1 | AC-028-01 to AC-028-11 by the L1 to L4 tests (sections 5 and 6); REQ-SALE-01 to REQ-SALE-10, REQ-PAY-01 to REQ-PAY-05, REQ-COST-10, REQ-CASH-04, REQ-CASH-13, REQ-DOC-01 |
| UI and UX | `N/A` | No page or control (contract section 4.2); the till is TASK-029's |
| Backend | See section 1 | FormRequest allowlist, CAP-21 at the route and in the transaction, the session by object scope, the guards under the locks, the result's presentation to its cashier and the Owner only, the L4 tests |
| Database | See section 1 | Six tables and a sequence with the constraint, deferred, presence, privilege, retention and numbering families (`SalesTablesTest`); a forward and additive migration with its class, duration, lock impact and verification (EXC-46; its rehearsal on the benchmark dataset waits for TASK-043) |
| Security | See section 1 | The capability matrix and route inventory, the refusals of OPERATIONS_ADMIN and of another user's session, the channel allowlists per role, no secret (the guard), no cost on the cashier's channels |
| Data integrity | See section 1 | Append-only facts, one transaction with its audit and result, identity and idempotency, the races of section 5 and the failure after each write step |
| Performance | See section 1 | At most 200 lines; one statement per lock step and per write; no counter row or lock for the number (SCH-25); the budgets are measured at TASK-048 |
| Operations | `PASS` | The four fact tables in the manifest's counts, all six and the sequence in the backup role's reads (section 9); no runbook or status change |
| Verification | See section 1 | Self-review and the class-A review (section 10); the fast runs of section 1; the whole command contract on the pull request's final head |

## 9. Backup impact (EXC-47)

`sales`, `sale_lines`, `payments` and `receipt_snapshots` hold retained facts (class F), so they are in the manifest's counts, which `backup:run` takes for every table of the matrix of a counted class (`BackupRunner::COUNTED_CLASSES`; TASK-013); `sale_correction_totals` and `sale_line_correction_totals` are projections of later corrections (class P), outside the counts like every projection. The backup role reads all six, their identity sequences and `sale_number_seq`. After a restore, the runbook advances `sale_number_seq` with the migration role (SCH-28, BACKUP_RECOVERY RB-01 step 13), which already names it. No new kind of stored file: a receipt references the logo its revision holds. No status change.

## 10. Review (EXECUTION_CONTEXT section 12, CE-40)

To be recorded.

## 11. Guard candidates (CE-50)

To be recorded.
