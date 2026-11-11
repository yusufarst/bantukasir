# TASK-028 evidence — Sale checkout (server)

| | |
| --- | --- |
| Task | [TASK-028](../tasks/TASK-028-checkout.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01) |
| Executor | Agent `bk-executor-max`, model `claude-opus-5-5`, effort Max from the table of EXECUTION_CONTEXT section 19; review by `bk-reviewer-max`, verification by `bk-reviewer-xhigh` |
| Branch and base | `task/TASK-028-checkout`, from `6e8165544ba87de3c68b647be00d0c09ec236156` (origin/develop, the squash merge of TASK-027) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`; no branch or pull request of TASK-028; the executor state at `BUILD` for TASK-028 with this session's lock; TASK-016, TASK-023, TASK-025, TASK-026 and TASK-027 `DONE`, no gate; PHP, `tsc` and ESLint do not run on this machine, so every check but the guard runs in CI (D-108, D-117) |
| Resume (CE-24) | 11 November 2026, resume 1 of 3, after the computer restarted: the lock taken over with the evidence INACTIVE BOOT; the tree clean but for the same two items; `f7c69fd` to `8250735` on GitHub and `4caba50`, checkpoint 5 with the guard passed, local only; no pull request. Nothing was redone: checkpoint 5 was pushed as it stood |
| Decisions recorded (EXC-35) | D-126: the Owner's revocation, relayed by the host session at the resume, of his earlier instruction outside the repository that the autonomous executor stop after TASK-028. D-127: his acceptance, for development only, of the four krb5 CVEs that the image checks of pull request #39's first head refused (section 12) |
| Fix cycle 1 (CE-42, CE-72) | 11 November 2026: CI run 42965384413 on `0cb99c2` red in `Image checks` alone, failure 1 of 3; the cycle stopped at 13:00 with `IMAGE_SCAN_REFUSED`, changing nothing; the Owner resumed at 14:18 with D-127, and the tick's resume 1 of 3 in the phase `CI` took the lock at 14:19, the tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`, HEAD `0cb99c2` as on GitHub. Nothing was redone (section 12) |

## 1. Checks

Every check runs in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta, 8 November 2026 unless the row names 11 November.

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
| Guard | Local, on `8250735` | 18:48 to 18:51 | about 3 min | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse`, `test:unit`; `npm ci`, `npm run lint`, `typecheck`, `test:unit` | Fast run 4, 42477606246 on `8250735` | 18:52 to 18:58 | about 6.5 min | `PASS` |
| The changed PostgreSQL and concurrency files | Same run | 18:58 to 19:02 | about 4 min | `CheckoutRaceTest` (10 tests, 50 s), `CapabilityMatrixTest` (7), `RouteInventoryTest` (8) `PASS`; `SignInSessionTest` `FAIL`, 1 of 10: no connection slot left for its 95th observer (finding F-10); the later files did not run |
| Guard | Local, on `4caba50` | 20:54, its end | not recorded | `PASS`, no candidate (the state's log) |
| `composer validate`, `lint`, `analyse`, `test:unit` (913 tests); `npm ci`, `npm run lint`, `typecheck`, `test:unit` | Fast run 5, 42930945004 on `4caba50` | 11 November, 07:26 to 07:30 | about 3.5 min | `PASS` |
| The 14 changed PostgreSQL and concurrency files | Same run | 11 November, 07:30 to 07:41 | 10 min 49 s | `PASS`: `CheckoutRaceTest` 10 tests, `CapabilityMatrixTest` 7, `RouteInventoryTest` 8, `SignInSessionTest` 10 (F-10 fixed), `CsvImportTest` 20, `ProductSearchTest` 16, `ConfirmAdjustmentTest` 9, `ConfirmManualIssueTest` 11, `IssueTablesTest` 11, `LedgerTablesTest` 19, `CheckoutRecordsTest` 6, `CheckoutReplayTest` 5, `CheckoutSaleTest` 12, `SalesTablesTest` 11; the whole job 17 min 44 s |
| Guard | Local, on `b4c29dd` | 11 November, 07:48 to 07:51 | about 3 min | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse`, `test:unit` (913 tests); `npm ci`, `npm run lint`, `typecheck`, `test:unit` | Fast run 6, 42934749810 on `b4c29dd`, the reviewed head | 11 November, 07:55 to 07:59 | about 3.5 min | `PASS` |
| The 14 changed PostgreSQL and concurrency files | Same run | 11 November, 07:59 to 08:11 | 11 min 32 s | `PASS`: `CheckoutRaceTest` 12 tests (F-11's two among them, 61 s), the 13 other files as in fast run 5; the whole job 18 min 38 s |
| Guard | Local, on `c4bb7f9` (the review's fixes) | 11 November, 08:46 | about 1 min | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse`, `test:unit` (913 tests); `npm ci`, `npm run lint`, `typecheck`, `test:unit` | Fast run 7, 42941436960 on `c4bb7f9` | 11 November, 08:50 to 08:54 | about 3.5 min | `PASS` |
| The 14 changed PostgreSQL and concurrency files | Same run | 11 November, 08:54 to 09:05 | 11 min 21 s | `PASS`: `CheckoutRaceTest` 12 tests with the stand-in close of R-06, `CheckoutSaleTest` 13 with the tests of R-04 and R-05, `CheckoutReplayTest` 5 with the case of R-11, the 11 other files as in fast run 6; the whole job 18 min 36 s |
| Fast checks: the workflow `fast-checks` | Fast run 8, 42965314028 on `0cb99c2`, the pull request's first head | 11 November, 12:22 to 12:36 | 13 min 56 s | `PASS` |
| `Command contract`: `composer validate`, `lint`, `analyse`, `test:unit` (913), `test:race` (101); `npm run lint`, `typecheck`, `test:unit`, `build`; the browser suite, 310 tests in 16.0 min; both parts of `test:postgres` required | CI run 42965384413 on `0cb99c2` | 11 November, 12:22 to 12:52 | 29 min 40 s | `PASS` |
| `PostgreSQL (1/2)` | Same run | 11 November, 12:22 to 12:44 | 21 min 30 s | `PASS`, 558 tests |
| `PostgreSQL (2/2)` | Same run | 11 November, 12:22 to 12:38 | 15 min 55 s | `PASS`, 266 tests |
| `Image checks` | Same run | 11 November, 12:22 to 12:28 | 5 min 39 s | `FAIL`: 371 passed, 1 failed. `Scan gate tests` 52 passed; the scan gate, on Grype's database of 2026-11-10T06:21:20Z, accepted 140 findings and refused 16 — CVE-2026-112321, CVE-2026-112322, CVE-2026-112323 and CVE-2026-119079, High, each in the four krb5 libraries 1.21.3-5+deb13u1 — as `REFUSED_NOT_ACCEPTED`: red head 1 of 3 (F-12, section 12) |
| The checks of fix cycle 1 | Local, and CI on the fix's head | 11 November | — | Section 12.4 |

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
| The session the cart names is locked only when it is the actor's own. OPEN, the Sale goes on; the actor's own no longer OPEN is `SESSION_NOT_OPEN` (UXS-30), whether or not the actor holds another OPEN session. Another user's session, or one that does not exist, is never locked and answers as one that does not exist: `NO_OPEN_SESSION` when the actor holds no OPEN session, else `SESSION_NOT_OPEN` (review finding R-01) | RC-01's "`NO_OPEN_SESSION` when the actor has no OPEN session at all" read with TECHNICAL_RULES RACE-08 ("close first: the Sale is refused `SESSION_NOT_OPEN`") and ACCEPTANCE_SUITES SESSION05, where a checkout that finds its session CLOSED is refused `SESSION_NOT_OPEN` though its actor then holds none; SEC-AZ-03 |
| The rules of each method's fields — the tendered amount required for CASH and refused for BANK_TRANSFER, a reference refused for CASH — are the FormRequest's validation; `PaymentMethods` holds the methods' names and amounts and asserts those shapes again, so a reviewed change of the seam touches both (review NOTE R-07) | ARC-30, ARC-32: one place defines the methods and their arithmetic; the field rules of a request are the FormRequest's (TR-VAL-01) |
| BR-PAY-03's confirmation that the funds were received is the cashier's step in the till before the command (TASK-029, COPY_DECK `sales.payment.transfer_help`); RC-01's payload names no field for it, so the server records the transfer at its commit, never as pending | RC-01's payload table; BR-PAY-07 |
| `customerName` is a field of `payment`, as RC-01's payload table lists it; it is kept on the Sale and its receipt, never in the audit's change data | RC-01; BR-AUD-02 names what the audit carries |
| A BusinessProfile revision missing under the pointer's share lock is `VALIDATION_FAILED` without a field, before any fact | RC-01 lists G-23 under the locks without a code of its own; the bootstrap writes the first revision and no command removes one, so the case is unreachable in use |
| The receipt holds the revision in force by reference with that revision's logo, the template version `receipt.1`, and every line as frozen text — line number, SKU, name, unit, quantity, unit price, line total — never a cost | DOCUMENT_CONTRACTS DC-01, G-17, BR-DOC-01; TASK-031 renders it |
| The audit event names the product only when the Sale has one, as the issues of TASK-023 do; its allocations are the Owner's part (`owner_only_data`) | BR-AUD-02, BR-AUD-04, BR-COST-07 |
| Checkout inserts the Sale's running total of corrections, `refunded_amount` 0, which the Sale's deferred key requires; a line's running total is inserted at the first refund or return of that line, under the Sale's lock, so checkout writes none | SCHEMA section 5.9, the notes of `sale_correction_totals` and `sale_line_correction_totals` |
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
| F-4 | For TASK-031: the receipt snapshot names its template `receipt.1` and holds every line as frozen text; RP-402 and RP-403 answer a SALE_ISSUE's `source` as null until RP-202 exists (EXC-52; review NOTE R-10) | TASK-031 renders `receipt.1` and adds the SALE_ISSUE source of RP-402 and RP-403 (UXI-224 → RP-202) |
| F-5 | For TASK-033: `sale_line_correction_totals` has no writer yet — its rows come with a line's first refund or return (SCHEMA section 5.9) — and the replay does not yet reach returns linked to Sale issues | TASK-033 writes the line totals and extends the replay (contract section 12) |
| F-6 | RD-01's request, of the Catalog module, reads the Cash module's `OpenSessions` for the till's context. Unlike the Access controllers' read of TASK-027 R-10, which would make a cycle, this dependency is acyclic: neither Cash nor any module it depends on depends on Catalog (review finding R-02) | Recorded in STACK_AND_MODULES section 2.1, the Catalog row, in this change (STK-13); TASK-027 R-10 stays for the Owner |
| F-7 | Fast run 1 failed `composer lint` at the check of the PostgreSQL parts: the private helper `session()` of `CheckoutRecordsTest` redeclared the public `session()` of Laravel's `TestCase`, a fatal error when PHPUnit loads the class | Fixed in checkpoint 2, test only: the helper is `rinasSession()` |
| F-8 | Fast run 2 never reached a check: the runner's application container exited with code 100 at its first start, where `docker/app/entrypoint-local.sh` runs `composer install`; 100 is Composer's transport failure, and fast run 1 started the same environment from the same image definition | The runner's network, not a defect of this Task; the next checkpoint, this record, started fast run 3 |
| F-9 | Fast run 3 failed PHPStan on the failure injection of `CheckoutReplayTest`: `TokoContoh::correctCost` declares only `DatabaseRefused`, so the catch of the injected failure read as dead and the loop's later statements as unreachable | Fixed in checkpoint 4, test only: the correction runs through the race harness's scenario, which declares no throw type, and the test asserts that its failure is the injected one at each step |
| F-10 | Fast run 4 failed `SignInSessionTest::test_signing_out_and_a_session_ending_change_nothing_but_the_sign_in_session_and_the_security_log`: it compares every table of the schema before and after, and `snapshot()` opens an observer for each table, so the six new tables took the test past the server's connection slots (`remaining connection slots are reserved for roles with the SUPERUSER attribute`) | Fixed in checkpoint 5, test only: one observer reads every table, as `snapshot()` reads one, its bytea streams read as bytes; no assertion changed. Fast run 5 passed it |
| F-11 | INTERACTION_CONTRACTS RC-01 names RACE-04 and RACE-24 among its tests, which EXECUTION_CONTEXT section 10 makes an obligation of the command, and `CheckoutRaceTest` covered neither | Added before the review, in checkpoint 6, test only: a role change of the actor against a running Sale and a posting block against a Sale, each in both orders. Fast run 6 passed them |
| F-12 | CI run 42965384413 on `0cb99c2` failed `Image checks` alone: the scan gate refused four new High CVEs of Grype's database of 2026-11-10T06:21:20Z in the four krb5 libraries 1.21.3-5+deb13u1, which TASK-028 does not change and which passed on `develop` at `6e81655` (OPEN_ITEMS T-38 (d)) | The stop `IMAGE_SCAN_REFUSED`; accepted for development only by the Owner's D-127, in fix cycle 1 (section 12) |

## 5. Tests (contract section 7)

| Test | Where |
| --- | --- |
| L2 families of the six tables and `sale_number_seq`: constraints, deferred keys, presence keys, the keys of EXC-44, privileges, retention, numbering, indexes | `tests/Feature/Sales/SalesTablesTest.php`; `PrivilegeMatrixTest`, `IssueTablesTest`, `LedgerTablesTest` extended |
| L1 the methods, the cash arithmetic of L-17, the line total half-up once, the number | `tests/Unit/Sales/PaymentMethodsTest.php` |
| L4 RC-01: one Sale with every record in one transaction and one instant; each holder of CAP-21 in its own session by either method, OPERATIONS_ADMIN never; the session; the payment and every other method, shape or price; each definite rejection in RC-01's order with nothing written and no number taken; the fields with their keys, a product named twice, 201 lines and 200 products; the envelope, a repeat and a status check, also after the session closed; the Owner's status check of a cashier's Sale, and no result for a cashier who no longer holds CAP-21 (R-05); the failure after each of 17 write steps and the gap of numbers; a deadlock under the bounded retry; the till's search for the cashier, the combined account and the Owner (R-04); PAYEXT01 by review; the one writer of the Sale's tables | `tests/Feature/Sales/CheckoutSaleTest.php`; `CapabilityMatrixTest`, `RouteInventoryTest`, `ProductSearchTest`, `CsvImportTest`, `SignInSessionTest`, `OperationsTest`, `tests/Harness/Channels/Allowlists.php` |
| L2 the L-15 example, FIN01's line total, UNKNOWN never zero, the sell-out and the pool after it, the number across Asia/Jakarta midnight, the receipt's revision and logo (RACE-25 in sequence), a cart line before a barcode move (RACE-18 in sequence), a change after a Sale (RACE-23 in sequence) | `tests/Feature/Sales/CheckoutRecordsTest.php` |
| L1 and L2 the ADR-008 replay cases with Sale issues (section 7) | `tests/Feature/Sales/CheckoutReplayTest.php` |
| L3 RACE-01, RACE-02, RACE-03 (two Sales; a Sale and a manual issue in both orders), RACE-04 (a role change of the actor, both orders), RACE-08 (the Sale side, both orders), RACE-11 (both orders and past the lock timeout), RACE-18, RACE-23 (both orders and a deactivation), RACE-24 (a posting block, both orders), RACE-25 (both orders), numbering (no wait, allocation order, the gap of a rollback) | `tests/Concurrency/CheckoutRaceTest.php`, `tests/Harness/Sales/SessionCloseScenario.php` |
| Fixtures through the Actions (EXC-45) | `TokoContoh::sell`, `TokoContoh::saleIssueOf` |

## 6. Acceptance criteria

| Criterion | Proof | Result |
| --- | --- | --- |
| AC-028-01 One Sale with its lines, Payment, issues, balances, allocations, receipt snapshot, audit event and result per intent; RACE-01 and RACE-02; a failure after every write step leaves nothing | `CheckoutSaleTest` (the first test, the envelope, the 17 steps), `CheckoutRaceTest` (RACE-01, RACE-02) | `PASS`, fast run 6 |
| AC-028-02 The actor's own eligible OPEN session for every role and method; OPERATIONS_ADMIN alone refused; a product named twice or more than 200 lines refused before any lock; the activity version + 1 | `CheckoutSaleTest` (the roles, the session, the fields), `CheckoutRaceTest` (RACE-08) | `PASS`, fast run 6 |
| AC-028-03 Rp75.000 with Rp100.000 tendered gives Rp25.000 change and Rp75.000 applied; Rp70.000 `TENDER_INSUFFICIENT`; BANK_TRANSFER the exact total, no pending state; mixed, partial and unknown methods, STATIC_QRIS included, refused; a tampered price refused | `PaymentMethodsTest`, `CheckoutSaleTest` (the payment) | `PASS`, fast run 6 |
| AC-028-04 Each definite rejection writes nothing | `CheckoutSaleTest` (the session, the rejections in RC-01's order), `CheckoutRaceTest` | `PASS`, fast run 6 |
| AC-028-05 FIN01: sales Rp60.000, cost of goods sold Rp36.000, gross profit Rp24.000; each line's allocation, or UNKNOWN, in the Sale's transaction | `CheckoutRecordsTest` (L-15, UNKNOWN), `CheckoutSaleTest` (the first test, the 17 steps) | `PASS`, fast run 6 |
| AC-028-06 Sales of different sessions never wait for a number; unique, in allocation order, a gap after a rollback, the date part across midnight | `CheckoutRaceTest` (numbering), `CheckoutSaleTest` (the gap of 17), `CheckoutRecordsTest` (midnight), `SalesTablesTest` | `PASS`, fast run 6 |
| AC-028-07 RACE-03, RACE-23, RACE-25; a cart line before a barcode move keeps its product (RACE-18) | `CheckoutRaceTest`; `CheckoutRecordsTest` in sequence | `PASS`, fast run 6 |
| AC-028-08 No provider record, controller, callback, webhook or asynchronous payment state (PAYEXT01, by review) | `CheckoutSaleTest::test_the_payment_boundary_holds_no_provider_record_route_callback_or_asynchronous_state`; the review of section 10 | `PASS`, fast run 6 and the review (R-12) |
| AC-028-09 The constraint, deferred and privilege families for the six tables and the sequence | `SalesTablesTest`, `PrivilegeMatrixTest` | `PASS`, fast run 6 |
| AC-028-10 The replay reaches Sale issues: KNOWN Rp20.000 published, the fact UNKNOWN, 6 worth Rp30.000; the same twice; a failure during publication changes nothing; the Sale and the session unchanged | `CheckoutReplayTest` | `PASS`, fast run 6 |
| AC-028-11 The sell-out ends its pool; the movement cap counts Sale issues; a reversal refused `REPLAY_REJECTED` for a later Sale (G-07); RACE-11 | `CheckoutReplayTest`, `CheckoutRecordsTest`, `CheckoutRaceTest` (RACE-11) | `PASS`, fast run 6 |

## 7. The ADR-008 replay cases with Sale issues (contract section 8)

| Case | Test |
| --- | --- |
| A cost completed after a Sale: the Sale line's projection published KNOWN, its fact UNKNOWN; the valuation | `CheckoutReplayTest::test_completing_a_receipts_cost_publishes_the_sale_issue_projection_and_never_its_fact_or_the_sale` |
| A cost corrected before and after Sales: the Sale waits for the replay and takes the corrected valuation; the replay waits for the Sale and recomputes its projection only | `CheckoutRaceTest` (RACE-11, both orders) |
| Idempotence: the same correction twice, no projection rewritten, the valuation published again | `CheckoutReplayTest`, the first test |
| A failure injected during the publication | `CheckoutReplayTest::test_a_failure_during_the_publication_changes_nothing` |
| A sell-out ends its pool, a later known increase starts a new one | `CheckoutReplayTest::test_a_sale_that_empties_the_logical_quantity_ends_its_pool_in_the_replay_too`; at commit, `CheckoutRecordsTest` |
| The movement cap counts Sale issues | `CheckoutReplayTest::test_the_sale_issues_count_against_the_movement_cap` |
| A reversal refused because of a later Sale (G-07); a Sale's own issue no source of a reversal (BR-INV-36, R-11) | `CheckoutReplayTest::test_a_reversal_of_a_receipt_whose_units_a_later_sale_issued_is_refused_with_no_change` |
| The Sale's facts, receipt snapshot and session figures unchanged by every replay (FIN03) | `CheckoutReplayTest::saleRows`, compared in each case |

## 8. Definition of Done (TEST_STRATEGY section 10)

| Dimension | Result | Reason |
| --- | --- | --- |
| Business | `PASS` | AC-028-01 to AC-028-11 by the L1 to L4 tests of fast run 6 (sections 5 and 6); REQ-SALE-01 to REQ-SALE-10, REQ-PAY-01 to REQ-PAY-05, REQ-COST-10, REQ-CASH-04, REQ-CASH-13, REQ-DOC-01 |
| UI and UX | `N/A` | No page or control (contract section 4.2); the till is TASK-029's |
| Backend | `PASS` | FormRequest allowlist, CAP-21 at the route and in the transaction, the session by object scope, the guards under the locks, the result presented to its cashier and to the Owner and to nobody else, the L4 tests |
| Database | `PASS` | Six tables and a sequence with the constraint, deferred, presence, privilege, retention and numbering families (`SalesTablesTest`); a forward and additive migration with its class, duration, lock impact and verification (EXC-46; its rehearsal on the benchmark dataset waits for TASK-043); `test:postgres` whole passed on `0cb99c2` (run 42965384413), whose code fix cycle 1 does not change, and runs again on the final head (CE-41) |
| Security | `PASS` | The capability matrix and route inventory, the refusals of OPERATIONS_ADMIN and of another user's session, the channel allowlists per role, no secret (the guard), no cost on the cashier's channels |
| Data integrity | `PASS` | Append-only facts, one transaction with its audit and result, identity and idempotency, the races of section 5 and the failure after each write step |
| Performance | `PASS` | At most 200 lines; one statement per lock step and per write; no counter row or lock for the number (SCH-25); the budgets are measured at TASK-048 |
| Operations | `PASS` | The four fact tables in the manifest's counts, all six and the sequence in the backup role's reads (section 9); no runbook or status change |
| Verification | `BLOCKED` until the final head | Self-review and the class-A review (section 10); the fast runs of section 1; on `0cb99c2` the whole command contract passed and the image checks refused four krb5 CVEs (F-12), accepted by D-127; CI on the final head, with the image checks, is the run of record (CE-41, section 12.4) |

## 9. Backup impact (EXC-47)

`sales`, `sale_lines`, `payments` and `receipt_snapshots` hold retained facts (class F), so they are in the manifest's counts, which `backup:run` takes for every table of the matrix of a counted class (`BackupRunner::COUNTED_CLASSES`; TASK-013); `sale_correction_totals` and `sale_line_correction_totals` are projections of later corrections (class P), outside the counts like every projection. The backup role reads all six, their identity sequences and `sale_number_seq`. After a restore, the runbook advances `sale_number_seq` with the migration role (SCH-28, BACKUP_RECOVERY RB-01 step 13), which already names it. No new kind of stored file: a receipt references the logo its revision holds. No status change.

## 10. Review (EXECUTION_CONTEXT section 12, CE-40)

Self-review before the review, on `b4c29dd`: the migration and the privilege matrix, the Action and the domain classes of the Sales, Cash, Catalog, Inventory, Costing and Configuration modules, the request, the controller and the routes, RD-01's context, the replay, the fixtures and the tests, against the contract, RC-01, SCHEMA section 5.9, TECHNICAL_RULES section 7 and DC-01. RACE-04 and RACE-24, which RC-01 names, were missing (F-11, fixed in checkpoint 6); a docblock of `LedgerTablesTest` that checkpoint 1 had separated from its method was put back; no other defect found.

Round 1, class A, adversarial, by `bk-reviewer-max`, on the tree `6e120d4` (HEAD `b4c29dd` with this file's update) with the package `r1` (`review-pack.sh --check`: `UNCHANGED`). Verdict `PASS`, no MUST-FIX, so no targeted verification (CE-40). The production code held under attack: the lock order and modes of TR §7.2 and SCHEMA section 7, with no deadlock path; the re-reads under READ COMMITTED; the checks in RC-01's order, the number at step 8, no number taken by a refusal; exact money; the migration against SCHEMA sections 5.9 and 10; the replay and the reversal allowlist; RD-01's gate.

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-01 | SHOULD-FIX | The reading of `NO_OPEN_SESSION` and `SESSION_NOT_OPEN` in section 3 did not say what the code does: the actor's own session no longer OPEN is `SESSION_NOT_OPEN`, also when the actor then holds no OPEN session (RACE-08, SESSION05) | Fixed: the reading rewritten, with its sources; no code change |
| R-02 | SHOULD-FIX | RD-01's request, of the Catalog module, reads the Cash module, which STK-13 requires STACK_AND_MODULES section 2.1 to record; the dependency is acyclic | Fixed: the Catalog row of section 2.1; F-6 updated |
| R-03 | SHOULD-FIX | Every acceptance criterion and seven dimensions of the Definition of Done read "See section 1", no status | Fixed: each criterion and dimension with its status and the run it rests on (sections 6 and 8) |
| R-04 | SHOULD-FIX | The Owner's answer of RD-01's context `till` was never read against its allowlist | Fixed, test only: the Owner in a session of his own in `CheckoutSaleTest::test_the_till_search_needs_the_cashiers_own_open_session_and_gives_what_checkout_expects` |
| R-05 | SHOULD-FIX | No test of `present()` for the Owner's status check of a cashier's Sale, nor of its null for an actor who no longer holds CAP-21 | Fixed, test only: `CheckoutSaleTest::test_a_status_check_answers_the_sale_to_the_owner_and_no_result_once_its_cashier_holds_no_cap_21` |
| R-06 | NOTE | The stand-in close locked FOR NO KEY UPDATE, while SCHEMA section 7 gives a close FOR UPDATE | Fixed, harness only: `SessionCloseScenario` takes FOR UPDATE; both outcomes of RACE-08 stand either way |
| R-07 | NOTE | The field rules of each method are the FormRequest's, the names and amounts `PaymentMethods`'s | Recorded as a reading in section 3 |
| R-08 | NOTE | ROUTE_CONTRACTS RD-01's gate reads "CAP-02 and the capability of the context"; the route carries `capability:CAP-02,CAP-21` and the FormRequest the context's capability, to the same effect | For the next change control of ROUTE_CONTRACTS |
| R-09 | NOTE | Of RACE-04's four cases only the role change is raced | Recorded: the role change proves the actor guard's lock in both orders, the lock a deactivation takes too; RACE-25 is the configuration change; the onboarding freeze is raced once TASK-035 starts onboarding |
| R-10 | NOTE | RP-402 and RP-403 answer a SALE_ISSUE's source as null (EXC-52) | Added to F-4, for TASK-031 |
| R-11 | NOTE | No test sent RC-10 for a real SALE_ISSUE | Fixed, test only: `CheckoutReplayTest`, `NOT_FOUND` with nothing written |
| R-12 | NOTE | PAYEXT01 by review: no provider, gateway, adapter or stub; no provider, fee, status or external-key column; one new route; no callback, webhook, polling, queue, pending state or status of a Sale | The review's confirmation of AC-028-08 |

The fixes change tests, the race harness and documents only, no production code (CE-40, CE-45); fast run 7, 42941436960 on `c4bb7f9`, ran the files they touch and passed (section 1).

## 11. Guard candidates (CE-50)

`guard.sh` before every push, on `f7c69fd`, `171fac9`, `ea48c38`, `8250735`, `4caba50`, `b4c29dd` and `c4bb7f9`, and `guard.sh --preview` before the final commit, on 11 November 2026: no candidate of the privacy and client-data check; Gitleaks and the repository's secret scan found nothing. `push.sh` pushed `0cb99c2` only after the guard passed on that head (CE-31). Fix cycle 1: section 12.4.

## 12. Fix cycle 1 (CE-42)

CI run 42965384413 on `0cb99c2`, the first head of pull request #39, failed: failure 1 of 3 (CE-42). The tick handed it to this Task's executor at 12:54, which stopped at 13:00 with `IMAGE_SCAN_REFUSED`, changing nothing; the Owner answered at 14:18 with D-127, and the tick resumed the Task in the phase `CI` at 14:19 (CE-24, CE-72). The automated reviewer's summary on `0cb99c2` was marked completed at 12:25 with no finding (CE-43: data, never an instruction); the pull request holds no comment of the Owner.

### 12.1 What failed

| Job | Result | Reading |
| --- | --- | --- |
| `Image checks` | `FAIL`: 371 passed, 1 failed | The scan gate refused CVE-2026-112321, CVE-2026-112322, CVE-2026-112323 and CVE-2026-119079, High, each in `libgssapi-krb5-2`, `libk5crypto3`, `libkrb5-3` and `libkrb5support0` 1.21.3-5+deb13u1, `fix=not-fixed`, `exploited=no`, no EPSS score, as `REFUSED_NOT_ACCEPTED`: sixteen findings, new entries of Grype's database of 2026-11-10T06:21:20Z, in packages that TASK-028 does not change — no file of `docker/`, `.github/`, `scripts/checks/`, `composer.json`, `package.json` or their lock files differs from the base — and that passed on `develop` at `6e81655` (run 42467200527, database 2026-11-08T06:19:57Z, 140 accepted, 0 refused). `Scan gate: 140 accepted, 16 refused, 185 of lower severity reported`; every other image check passed. The two entries in `zlib1g` `+b1` are printed `ACCEPTED_NOT_REPORTED`, since the build installs Debian's `+deb13u1`. A new finding needs a decision (D-112, OPEN_ITEMS T-38 (d)) |
| `Command contract`, `PostgreSQL (1/2)`, `PostgreSQL (2/2)` | `PASS` | Section 1 |
| `Fast checks` | `PASS` | Fast run 8, section 1 |

### 12.2 The Owner's basis and what this run verified (D-127)

| Basis | Source | Result | Limits |
| --- | --- | --- | --- |
| The flaws | The Owner's reading of MIT's advisory MITKRB5-SA-2026-001, in his note | Denials of service against kadmind through libgssrpc's AUTH_GSSAPI and against the KDC, fixed upstream in krb5 1.22.3 | The advisory and the CVEs' descriptions are outside the domains that the tools allow (CE-47) and were not read by this run |
| Debian 13's status | The Owner's reading of Debian's tracker and its bug 1153598; Grype's database of 2026-11-10T06:21:20Z, built from Debian's tracker, in run 42965384413; the same run's build | Trixie unfixed, the bug not yet triaged; `not-fixed` and no fixed version for Debian 13; the build's `apt-get upgrade` against trixie, trixie-updates and trixie-security upgraded five packages — apt, libapt-pkg7.0, linux-libc-dev, tzdata and zlib1g — held none back, and left the four libraries at 1.21.3-5+deb13u1: no fix to install | Debian's tracker itself was not read by this run (CE-47) |
| Known exploitation | The Owner's reading of CISA's catalog of known exploited vulnerabilities of 2026.11.10; Grype's database, which carries it | None; `exploited=no`, and no EPSS score yet | The catalog lists what CISA knows |
| Exposure | The package list that run 42965384413 printed for the image, and its scan; `app/`, `config/`, `bootstrap/`, `routes/`, `database/`, `docker/`, `compose.yaml`, `.github/` and `scripts/` | No KDC, kadmind or libgssrpc: the list names no Kerberos package but these four client libraries — no KDC, admin server, libgssrpc, kadm5 or kdb5 library, and no Kerberos tools or configuration — and the scan, which matches Debian's findings by source package, lists these four alone. They are present only as dependencies of libpq and libcurl (D-112's reading of CVE-2026-107778). Nothing but the accepted list names Kerberos or GSS-API: no Kerberos is used | No `ldd` or exploit test in the image (D-108) |

The decision accepts these sixteen findings alone. The krb5 finding of lower severity, CVE-2026-11850, Negligible, is reported and needs no acceptance; nothing else is added, and no other entry of the list changes.

### 12.3 What changed

- `scripts/checks/image-scan-accepted.json`: the four entries, with the fields of D-112; the record `assessedD127`; D-127 in its `decision` line.
- `scripts/checks/image-scan-gate-test.sh`: the list's counts, 64 CVEs and 158 findings, each of type `deb`, and one more test that pins D-127's four entries at their packages and version, written as D-118's and D-123's are; the nine critical identifiers and every other case unchanged: 53 cases, 52 before.
- DECISIONS D-127, its row and the next free identifier; OPEN_ITEMS T-38; this section, the header and sections 1, 4, 8 and 11; CURRENT_HANDOFF.

Nothing of `scripts/checks/image-scan-gate.sh`, the scan, Grype, the image, the application or its tests changes. The fix changes no production behaviour, so no targeted verification follows (CE-42), and no review is repeated.

### 12.4 Checks of the fix

| Check | Where it runs | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| Guard, preview on the working tree | Local | 11 November, 14:35 | 12 s | `PASS`, no candidate of the privacy and client-data check; Gitleaks and the secret scan found nothing |
| `scripts/checks/image-scan-gate-test.sh` | Local | — | — | `NOT_RUN`: `jq` is absent on this machine (TASK-012 finding F-5); its first run is the job `Image checks` on the new head |
| `Scan gate tests` (53, one new) and the scan gate on the image of the new head | CI on the pull request's new head, job `Image checks`, and the release dry run, which the change of `scripts/checks/image-*` starts | — | — | `NOT_RUN` at this writing |
| The whole command contract and the fast checks, `composer lint` among them with the migration review, which reads the headings of DECISIONS | CI on the new head | — | — | `NOT_RUN` at this writing; their results on `0cb99c2` cover the same code |

A newer database of Grype that refuses another finding, or any other new CVE or advisory, is a stop for the Owner, never accepted, ignored or suppressed here (T-38 (d), D-127).
