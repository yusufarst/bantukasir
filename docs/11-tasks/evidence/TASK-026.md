# TASK-026 evidence — Reversal of movements

| | |
| --- | --- |
| Task | [TASK-026](../tasks/TASK-026-reversal.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01) |
| Executor | Agent `bk-executor-high`, model `claude-opus-5-5`, effort High from the table of EXECUTION_CONTEXT section 19; review by `bk-reviewer-high`, verification by `bk-reviewer-high` |
| Branch and base | `task/TASK-026-reversal`, from `139f99d8d29a3191df96334edfa0e7b3a271d2d0` (origin/develop, the squash merge of TASK-025) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`; the stash of D-030; no branch or pull request of TASK-026; the executor state at `BUILD` for TASK-026 with this session's lock; TASK-025 `DONE`, merged as `139f99d` (pull request #35), no gate; `tsc`, ESLint and PHP do not run on this machine (`node_modules/.bin` absent), so every check but the guard runs in CI (D-108, D-117) |

## 1. Checks

Every check runs in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta, 7 November 2026.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck` | Local | 09:40 | — | `NOT_RUN`: `tsc` is not installed on this machine |
| Guard (Gitleaks 8.30.1, the secret scan, identity, candidates) | Local, on `4bd8cd8` | 10:10 | under 4 min | `PASS`, no candidate |
| `composer validate`, `lint` | Fast run 1, 42332801319 on `4bd8cd8` | 10:14 to 10:15 | 29 s of `lint` | `FAIL`: Pint's `ordered_imports` in `ConfirmReversal.php`, `Reversals` before `ReversalSource` (finding F-4); the later steps did not run |

## 2. What was built (contract section 4.1)

| Item | Where |
| --- | --- |
| 1. `reversals`, with the keys `stock_movements.reversal_id` → `reversals (id, product_id, inverse_quantity)` and `cost_incomplete_mark_clearances (clearing_reversal_id, evidence_movement_id)` → `reversals (id, original_movement_id)` (EXC-44) | `database/migrations/2026_11_07_000001_create_reversals_table.php`, `database/privileges.php`; `App\Domain\Inventory\Reversals`, `ReversalSource`, `StockLedger::appendReversal` |
| 2. RC-10, in the order of RC-10 under the locks of TR §7.2 "Reversal" | `App\Actions\Inventory\ConfirmReversal`, `ConfirmReversalRequest`, `ConfirmReversalController`, `routes/web.php`, `CommandActions` |
| 3. The replay of TASK-025 extended to REVERSAL: the logical ledger leaves every cancelled pair out of the zero point, the state before the start and the movements from it; a reversed manual issue's projection becomes `CANCELLED`; the mark of a refused correction of a reversed source is written with its clearance `SOURCE_REVERSED` | `ReplayLedger`, `ProductReplay`, `ProjectionChange`, `IssueAllocations::republish`, `CostIncompleteMarks`, `CorrectCost::mark()` |
| 4. RP-411 and the entries on RP-403 (UXI-227 and the reversal links of COPY_DECK 9.4), RP-406 (UXI-250) and RP-409 (UXI-266) | `ReversalsController`, `ReversalPageRequest`, `App\Queries\Inventory\Reversibility`; `resources/js/pages/inventory/Reversal.tsx`, `resources/js/lib/inventory/Reversal.tsx`, `Movement.tsx`, `Receiving.tsx`, `Issue.tsx`; `lang/id/inventory.php` |

## 3. Readings and technical choices

| Reading | Why |
| --- | --- |
| A movement that does not exist, or whose type no reversal may cancel (ADJUSTMENT, REVERSAL, SALE_ISSUE, RETURN_RECEIPT), is `NOT_FOUND` on RC-10 and RP-411 | RC-10 lists no refusal for an ineligible type and COPY_DECK has no text for one; the controls are not shown for those types (INTERACTION_INVENTORY UXI-227), so such a movement is no object of the route (TR-ERR-03). No business meaning changes |
| RC-10 does not refuse an inactive product | RC-10 and its family in TR §7.2 list no `PRODUCT_INACTIVE`, unlike RC-07 to RC-09; a wrong receipt of a product deactivated since can be reversed |
| An OPENING may be reversed while onboarding is ACTIVE, and the onboarding state is read under its share lock for every reversal (step 2.3) | RC-10, D-087; no OPENING exists before TASK-035, which proves the case |
| The writes of the reversal precede its replay in the same transaction, so that the replay reads the logical ledger with the pair already cancelled; the logical end then equals the new balance | ADR-008 section 4.3; a cancelled pair sums to zero |
| A stock after the REVERSAL above the range of a balance is `REPLAY_REJECTED`, below zero `INSUFFICIENT_STOCK` | TR-TIME-01 (a result outside the ranges), BR-INV-37 |
| The REVERSAL movement's source on UXP-403 is the link `inventory.movement.reverses`, and the source's the link `inventory.movement.reversed_by`; UXP-411 names the source with `inventory.movement.source_pair` | COPY_DECK section 9.4 |
| Costing reads `reversals` for the logical ledger | STACK_AND_MODULES section 2.1, Costing's row amended |
| Success leads to UXP-403 of the REVERSAL, whatever the opener | NAVIGATION_CONTRACTS UXP-411, RC-10's location |

## 4. Findings

| ID | Finding | Outcome |
| --- | --- | --- |
| F-1 | RP-503, the worklist of TASK-025, still lists an UNKNOWN cost source whose movement was reversed, though after its reversal the source no longer counts in any figure (BR-COST-32; SCHEMA section 5.8) | Recorded for the next change of RP-503 or TASK-040: changing the worklist is outside TASK-026's scope. A completion of such a source changes no figure |
| F-2 | `tsc`, ESLint and PHP do not run on this machine, so every static check is met first in CI (TASK-011 F-5) | As recorded since TASK-011; the fast runs cover them |
| F-4 | Fast run 1 failed Pint: the imports of `ConfirmReversal.php` out of order | Fixed in checkpoint 2 |
| F-3 | UXI-228, "Batalkan mutasi" for an OPENING while onboarding is active, and the replacement that follows it, are TASK-035's, which proves the OPENING case (contract section 4.3) | The server accepts an OPENING during onboarding; the control on UXP-403 shows for an OPENING as UXI-227 |

## 5. Tests (contract section 7)

| Test | Where |
| --- | --- |
| L2 families of `reversals` (constraint, deferred, privilege, retention, indexes) | `tests/Feature/Inventory/ReversalTablesTest.php`; `LedgerTablesTest` and `PrivilegeMatrixTest` extended |
| L1 the COSTING section 8 fixtures | `tests/Unit/Costing/ValuationReplayTest.php`, `test_the_reversal_fixture_of_costing_section_8_on_the_logical_ledger` |
| L2 the COSTING section 8 fixtures, through the Actions, with the two examples of ADR-008 section 4.1 | `tests/Feature/Inventory/ConfirmReversalTest.php` |
| L3 RACE-10's reversal part, RACE-01, RACE-02, a posting waiting for the reversal | `tests/Concurrency/ReversalRaceTest.php` |
| L4 RC-10: the envelope's fields, its receipt, the roles and the combined account, recent authentication, the failure after each of the eight write steps, its audit event and durable result, recovery by repeat and status check; RP-411 and the entries per role with the channel allowlists | `ConfirmReversalTest`, `tests/Feature/Inventory/ReversalPagesTest.php`, `CapabilityMatrixTest`, `RouteInventoryTest`, `tests/Harness/Channels/Allowlists.php` |
| L5 RP-411 at every width of KEY_SCREENS section 4 and 1440 px, with axe | `tests/Browser/inventory/reversal.spec.ts` |

## 6. Backup impact (EXC-47)

`reversals` holds retained facts, class F in `database/privileges.php`, so it is in the manifest's counts, which `backup:run` takes for every table of the matrix of a counted class (`BackupRunner::COUNTED_CLASSES`; TASK-013), with the backup role's `SELECT` on the table and its sequence. No new kind of stored file. No runbook or status change.
