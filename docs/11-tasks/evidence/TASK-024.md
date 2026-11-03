# TASK-024 evidence — Stock adjustment

| | |
| --- | --- |
| Task | [TASK-024](../tasks/TASK-024-stock-adjustment.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01) |
| Executor | Agent `bk-executor-high`, model `claude-opus-5-5`, effort High from the table of EXECUTION_CONTEXT section 19; review by `bk-reviewer-high`, verification by `bk-reviewer-high` |
| Branch and base | `task/TASK-024-stock-adjustment`, from `25d9474bd08540eaacbbbbaecf4fc8347e8b3432` (origin/develop) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`; the stash of D-030; no branch or pull request of TASK-024; the executor state at `BUILD` for TASK-024 with this session's lock; TASK-023 `DONE`, no gate; `tsc` and ESLint do not run on this machine (no `node_modules/.bin`), PHP neither, so every check runs in CI (D-108, D-117) |

## 1. Checks

Every check runs in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta, 3 November 2026.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck`, `npx tsc --noEmit` | Local | 20:23 | — | `NOT_RUN`: `tsc` is not installed on this machine |
| Guard (Gitleaks 8.30.1, the secret scan, identity, candidates) | Local, on `b75ac85`, `1662fc3`, `142a2c3`, `aa93aad` | 20:12, 20:19, 20:27, 20:39 | under 1 min of scanning each | `PASS`, no candidate |
| `composer validate`, `lint` | Fast run 41642332926 on `b75ac85` | 20:16 | 36 s | `PASS` |
| `composer analyse` | Same run | 20:16 | 12 s | `FAIL`: 8 PHPStan errors — a nullable result reference, a needless `array_values`, a helper missing from a test, an assertion PHPStan judged always true (finding F-1); the later steps did not run |
| `composer validate`, `lint`, `analyse`, `test:unit` | Fast run 41643442045 on `1662fc3` | 20:23 to 20:24 | about 1 min | `PASS` |
| `npm run lint` | Same run | 20:24 | 4 s | `FAIL`: one template literal with letters inside JSX, an id of `Menu.tsx` (finding F-1); the later steps did not run |
| `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit` | Fast run 41644529667 on `142a2c3` | 20:30 to 20:33 | about 3 min | `PASS` |
| `AdjustmentRaceTest`, `CapabilityMatrixTest`, `RouteInventoryTest`, `ProductSearchTest` | Same run | 20:33 to 20:36 | 3 min 8 s | `PASS` |
| `AdjustmentPagesTest` | Same run | 20:36 | 12 s | `FAIL`: 1 of 3, a defect of the test, `?? 'absent'` on a prop that is null (finding F-1); the later files did not run |
| `composer validate`, `lint`, `analyse`, `test:unit` | Fast run 41646371938 on `aa93aad` | 20:43 to 20:44 | 1 min 11 s | `PASS` |
| `npm run lint`, `typecheck`, `test:unit`, `adjustmentDraft.test.ts` among them | Same run | 20:45 to 20:46 | 1 min 51 s | `PASS` |
| The PostgreSQL and concurrency test files that the branch changes: `AdjustmentRaceTest`, `CapabilityMatrixTest`, `RouteInventoryTest`, `ProductSearchTest`, `AdjustmentPagesTest`, `AdjustmentTablesTest`, `ConfirmAdjustmentTest`, `IssueTablesTest`, `LedgerTablesTest`, `StockPagesTest` | Same run | 20:46 to 20:51 | 5 min 18 s | `PASS` |
| The whole command contract — `test:postgres`, `test:race`, `build`, the browser suite with `tests/Browser/inventory/adjustment.spec.ts` — and the image checks | CI on the pull request's final head | — | — | `NOT_RUN` at this writing (CE-41, D-117); never on this machine (D-108) |

Fast run 4, 41646371938 on `aa93aad`, is the run of the code to review.

## 2. What was built (contract section 4.1)

1. **The two tables and the key** (migration `2026_11_03_000002_create_adjustment_tables`): `adjustments` (reason 1–1,000, count reference and reference 1–100 each, all mandatory; 1–200 lines; the command unique) and `adjustment_lines` (a signed quantity, never zero, at most the range of a movement either way; `UNIQUE (id, product_id, quantity)`), with their checks, keys, indexes and grants (`database/privileges.php`); the line's deferred key to its movement; and the key `stock_movements.adjustment_line_id` → `adjustment_lines (id, product_id, quantity)` (EXC-44). The movement's cost evidence and allocation are held by the presence keys of TASK-021 and TASK-023.
2. **RC-09 `adjustment.confirm`** (`app/Actions/Inventory/ConfirmAdjustment.php`, `AdjustmentLine`, `ConfirmAdjustmentRequest`, `ConfirmAdjustmentController`; `App\Domain\Inventory\Adjustments`, `StockLedger::appendAdjustment`): CAP-16, the Owner's alone, with recent authentication at the route (`password.confirm`); a reason, a count reference and a reference; up to 200 lines, each `INCREASE` or `DECREASE` of a positive quantity, one ADJUSTMENT of the signed quantity each, in the order of the lines; an increase with its acquisition cost evidence, KNOWN or UNKNOWN, an explicit zero with its evidence or reason (`CostEvidence`, type ADJUSTMENT, the adjustment's reference); a decrease with its allocation through TASK-023's `IssueAllocation::compute` and `IssueAllocations::record` (type ADJUSTMENT); INSUFFICIENT_STOCK for the decreases of a product added together; the locks and refusals of TECHNICAL_RULES section 7.2; one audit event `ADJUSTMENT_CONFIRM`, the costs and allocations in its Owner-only part; the result `location` RP-402 filtered to the adjusted products.
3. **RP-410** (`Inventory\AdjustmentsController`, `AdjustmentPageRequest`; `resources/js/pages/inventory/AdjustmentCreate.tsx`): the draft in page memory; `product` as its first line, read as RD-01 reads it; `from_page` (`stock`, `history`) for "Kembali ke …" and "Batal"; RD-01's context `adjustment` with each row's version; "Tambah" / "Kurangi", the quantity, an increase's cost; the three fields; the review of the stock before, the change and the stock after; the password asked before the first request (UXS-11); refusals at their lines; UXS-14 with "Kirim ulang" and "Cek status".
4. **The entries**: UXI-217, the row overflow "Sesuaikan stok" of UXP-401 (`Stock.tsx`), and UXI-221, the button of UXP-402 with one product filtered (`History.tsx`), each the Owner's, unavailable with its reason under the onboarding freeze or an unresolved posting request; `postingFrozen` on RP-401 and RP-402. The menu of DESIGN_SYSTEM 8.9 gains an `unavailable` item (UXS-13 in a menu).
5. **RP-402 filtered to several products**: its `product` takes one key or up to 200 separated by commas, each once (`HistoryPageRequest`, `StockHistory`, `HistoryController`, `lib/inventory/stock.ts`); one product keeps its posting order, several are by time; ROUTE_CONTRACTS RP-402 records it (EXC-81).
6. **Copy** in `lang/id/inventory.php`, verbatim from COPY_DECK sections 4.1, 9.2, 9.3, 9.11 and 15, and `resources/js/types/translation-keys.ts`.
7. **Tests:** L1 `tests/Unit/Inventory/adjustmentDraft.test.ts`; L2 and L4 `tests/Feature/Inventory/AdjustmentTablesTest.php`, `ConfirmAdjustmentTest.php`, `AdjustmentPagesTest.php`; L3 `tests/Concurrency/AdjustmentRaceTest.php`; L5 `tests/Browser/inventory/adjustment.spec.ts`. The fixture `TokoContoh::adjust()` goes through RC-09's Action.
8. **Guard tests widened**, each for what this Task adds: `LedgerTablesTest` and `IssueTablesTest` (their ADJUSTMENT movements now name real lines, or move to `AdjustmentTablesTest`), `StockPagesTest` (the products of the filter), `PrivilegeMatrixTest`, `OperationsTest`, `RouteInventoryTest`, `CapabilityMatrixTest`, `ProductSearchTest` (the context `adjustment` now open to the Owner), `Tests\Harness\Channels\Allowlists`.

## 3. Readings and technical choices

1. **The stock check of an adjustment.** RC-09 checks, "for issues and decreases, stock enough for the lines of each product added together". The decreases of a product are added together and compared with its stock before the adjustment, whatever increases of it the adjustment also holds, so that no order of the lines can make stock negative at any point (BR-INV-33). Every decrease of a short product is named with the stock available.
2. **Signed lines.** The payload names a direction and a positive quantity (INTERACTION_CONTRACTS RC-09); the line and its movement hold the signed quantity (SCHEMA section 5.7).
3. **A decrease's allocation** is computed from the valuation the line before it left, in the order of the lines, as a manual issue's (BR-INV-32, ADR-008 section 4.2); an increase changes the valuation as a receipt does.
4. **The result** is RP-402 filtered to the adjusted products, each once and ascending. RP-402 took one product; it now takes several (section 2 item 5). Its prop `product` became `products`, a list, and `filters.product` became `filters.products`.
5. **The first line** given by the address has no direction and no quantity, so that the Owner chooses both; it does not count as an unsaved change until it is edited.
6. **The direction** is a segmented control of DESIGN_SYSTEM 8.4 whose legend is "Perubahan" (`inventory.line.change`), the review's word for the change; COPY_DECK names no other legend.
7. **UXI-217 unavailable**: a menu item cannot hold a link, so the reason for an unresolved request is the item's text, and "Buka Permintaan belum pasti" is a second item of the same menu.
8. **Not built here:** "Lihat hasil" of UXP-006 for an adjustment (UXI-056 is not among this Task's controls); a source link of an ADJUSTMENT on RP-402 and RP-403 (no page of an adjustment exists). The movement's cost evidence of a positive ADJUSTMENT already shows on RP-403 for the Owner (UXI-225).
