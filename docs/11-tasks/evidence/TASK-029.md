# TASK-029 evidence — Till, payment and receipt pages

| | |
| --- | --- |
| Task | [TASK-029](../tasks/TASK-029-till-pages.md), review class A |
| Instruction | Continuous execution, D-101, as the delegation of the tick printed it (EXECUTION_CONTEXT CE-01) |
| Executor | Agent `bk-executor-high`, model `claude-opus-5-5`, effort High from the table of EXECUTION_CONTEXT section 19; review by `bk-reviewer-high`, verification by `bk-reviewer-high` |
| Branch and base | `task/TASK-029-till-pages`, from `341d4cb741a5793afebea8b05f244d98aaf0d08a` (origin/develop, the squash merge of TASK-028) |
| Preflight (EXC-20, EXC-21, EXC-23) | The tree clean but for `Claude outputs/` and `plan-p0-p11-review.patch`; no branch or pull request of TASK-029 and no open pull request; the executor state at `BUILD` for TASK-029 with this session's lock; TASK-028 and TASK-017 `DONE`, no gate. PHP does not run on this machine, and the `.bin` links of `node_modules` are absent, so `tsc` and ESLint do not run either: every check but the guard runs in CI (D-108, D-117, F-1) |
| Owner's directives in force | The host forwarded them at the start (11 November 2026): no repeat of valid work, fix only proven failures, every quality gate kept; a new CVE or advisory is a stop, never accepted here; no change of executor scripts, baselines, assertions or timeouts; the next free decision D-129 (none taken) |

## 1. Checks

Every check runs in CI, on GitHub's hosted runner, with the local environment's containers and synthetic data, started by the executor's push, except the guard, which runs natively (EXC-61, CE-31, CE-44, CE-46). Times are Asia/Jakarta, 11 November 2026.

| Check | Where it ran | Time | Duration | Result |
| --- | --- | --- | --- | --- |
| `npm run typecheck`, `npx tsc --noEmit`, `npx eslint` | Local | 17:20 | seconds | `NOT_RUN`: `tsc` and `eslint` are not on the path of this machine (F-1) |
| Guard (Gitleaks 8.30.1, the secret scan, identity, candidates) | Local, on `d3f163f` | 17:32 | about 1 min | `PASS`, no candidate |
| `composer validate`, `lint` (Pint, the translation keys and the generated key types, the migration review, the parts of `test:postgres`, the secret scan, the audits) | Fast run 1, 43004533625 on `d3f163f` | 17:33 to 17:37 | about 4 min | `PASS` |
| `composer analyse` | Same run | 17:37 | 18 s | `FAIL`: one finding of PHPStan, a value read as `mixed` given to `Money::fromDatabase()` in `App\Queries\Sales\Receipts` (F-4); the later steps did not run |
| Guard | Local, on `8ac5a15` | 17:40 | about 1 min | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse` | Fast run 2, 43005630629 on `8ac5a15` | 17:41 to 17:45 | 4 min 55 s for the run | `PASS` (F-4 fixed) |
| `composer test:unit` | Same run | 17:45 | 23 s | `FAIL`: 912 of 913; `CopyDeckTest` read the table of COPY_DECK section 5.1 as giving `sales.cart.pay` the copy "UXI-075" (F-7); the later steps did not run |
| `composer validate`, `lint`, `analyse`, `test:unit` | Fast run 3, 43006956683 on `d156ebe` | 17:50 to 17:55 | 4 min 59 s for the run | `PASS` (F-7 fixed) |
| `npm run lint` | Same run | 17:55 | 5 s | `FAIL`: two findings of ESLint, a useless assignment in `rememberedSize` and the literal `'0'` in the JSX of the payment's stock notice (F-8); the later steps did not run |
| `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit` | Fast run 4, 43008091896 on `7e25235` | 17:58 to 18:10 | 12 min 4 s for the run | `PASS` (F-8 fixed) |
| Changed PostgreSQL files: `CapabilityMatrixTest`, `HomeRedirectTest`, `RouteInventoryTest`, `CashPagesTest`, `OpenCashSessionTest` | Same run | 18:05 to 18:09 | about 4 min | `PASS` |
| `CheckoutSaleTest` | Same run | 18:10 | 65 s | `FAIL`: 11 of 13; TASK-028's boundary and one-writer scans refused RP-102's contracted path `/till/payment` and RP-103's reading query (F-9); the files after it did not run |
| Every step before the PostgreSQL files; the changed PostgreSQL files up to `CheckoutSaleTest` (13 tests, F-9 fixed) | Fast run 5, 43010335397 on `f6ebc13` | 18:14 to 18:24 | 10 min 14 s for the run | `PASS` |
| `TillPagesTest` | Same run | 18:24 | 16 s | `FAIL`: 3 of 4; RP-103's prop `shop` replaced the shell's shared `shop` (F-10) |
| Guard | Local, on each checkpoint `d156ebe`, `7e25235`, `f6ebc13`, `bfaf2df` | 17:50 to 18:28 | about 1 min each | `PASS`, no candidate |
| `composer validate`, `lint`, `analyse`, `test:unit`; `npm run lint`, `typecheck`, `test:unit`; the seven changed PostgreSQL files `CapabilityMatrixTest`, `HomeRedirectTest`, `RouteInventoryTest`, `CashPagesTest`, `OpenCashSessionTest`, `CheckoutSaleTest`, `TillPagesTest` (7 passed, 0 failed); `composer test:unit` 913 tests | Fast run 6, 43012323205 on `bfaf2df`, the reviewed head | 18:29 to 18:40 | 10 min 49 s | `PASS` |
| Guard | Local, on `e3f8af1` | 19:00 | about 1 min | `PASS`, no candidate |
| The same steps after the review's fixes (`npm run lint`, `typecheck` and `test:unit` with the new cases of `scanQueue.test.ts`; the changed PostgreSQL files) | Fast run 7, 43016672677 on `e3f8af1` | 19:00 to 19:10 | 8 min 49 s | `PASS` |
| Guard | Local, on `9a02421` | 19:18 | about 1 min | `PASS`, no candidate |
| The same steps after round 2's fixes | Fast run 8, 43019182845 on `9a02421` | 19:18 to 19:30 | 11 min 44 s | `PASS` |
| The whole of `test:postgres` and `test:race`, the browser suite with `tests/Browser/sales/till.spec.ts`, the build, the image checks | CI on the pull request's final head (CE-44, D-117) | — | — | `NOT_RUN` until then |
| Fix cycle 1 (section 10): `npm run typecheck`, `npx eslint` on the two changed files | Local | 19:41 | seconds | `NOT_RUN`: `tsc` and `eslint` are not on the path of this machine (F-1) |
| Fix cycle 1: `npm run lint`, `typecheck`, `test:unit`, the browser suite with the new test of `till.spec.ts`, and the rest of the command contract | CI on the fix's head (CE-41, CE-45) | — | — | `NOT_RUN` until then; the results before the fix stay valid for every file it does not touch |

## 2. What was built (contract section 4.1)

| Item | Where |
| --- | --- |
| 1. RP-101 (KS-03): the session's header; "Cari atau scan produk" with F2, scan capture and the down arrow into the results; each complete scan an exact lookup of RD-01 `till` adding one base unit, applied in scan order, a newer scan never dropping an older one; an empty terminator and a repeated Enter adding nothing; an unknown code kept in the field (UXS-33) and searched; an inactive product read only (UXS-32); typing that searches once the keys pause, superseded by newer typing, with the filters Kategori and Merek behind "Filter" and "Muat lebih banyak"; results with thumbnail or placeholder, SKU, stock and price; one line per product (D-067), a pick or scan of a product already in the cart adding to its line, highlighted and kept in view; the stepper with "+", "−" stopping at the smallest valid quantity with its reason, the typed quantity in the product's precision, remove, "Kosongkan keranjang" with its confirmation; the price change of a checkout or of a newer read marked at its line with the new total, "Bayar" waiting for "Setuju harga baru" (UXS-18); the stock available and a refused product's reason at their lines; "Bayar" (F8, never Enter) with its reasons (UXS-13): an unresolved checkout — with "Cek pembayaran yang belum pasti" as the page's primary action (UXI-076) — the onboarding freeze (UXS-27), an empty cart, a price to review, a line to correct; the "opened" note of RC-02 | `resources/js/pages/sales/Till.tsx` (`TillView`, `TillLine`); `resources/js/lib/sales/cart.ts`, `scanQueue.ts`, `till.ts`; `resources/js/components/ui/Till.tsx`; `App\Http\Controllers\Sales\TillController`; `App\Queries\Cash\OpenSessions::sessionOf` |
| 2. RP-102 (KS-04): the total; Tunai chosen first (D-075) with Alt+1 and Alt+2; "Uang diterima" focused, Enter in it sending nothing, "Uang Pas" (Alt+U), the change or the amount missing shown live and large; for Transfer bank the guidance of BR-PAY-03, the optional reference and the final action "Dana sudah diterima — Selesaikan"; the optional customer name; one final action of 64 px, at least 24 px below the controls above it, never the first focus, never Enter, Ctrl+Enter with the repeat guard, disabled with its reason while the cash is short, offline or the family is blocked; SUBMITTING with "Sedang diproses" and the screen frozen; UNCERTAIN and HELD on the Sale family's panel with "Kirim ulang" and "Cek status", or "Cek status" alone, the way back closed with its reason; a refusal at its line and in its notice, PRICE_CHANGED back to the till; NOT_COMMITTED with "Mulai lagi dari keranjang"; loaded without a cart, back to the till unless a checkout is unresolved, whose panel it then shows (NC-13 (b)) | `resources/js/pages/sales/Till.tsx` (`PaymentView`, `TillPage`) |
| 3. RP-103 (KS-05): the receipt from its snapshot with no further step — the shop identity of its revision with its logo, the number, time, cashier, session, customer, every frozen line, the total, the method and the cash amounts — in 58 mm, 80 mm or A4 "Nota Penjualan", the last size remembered for the browser session; "Transaksi baru"; the search field focused, whose scan or search starts the next sale on the till with that product; "Kembali ke Kasir"; for its own cashier with CAP-21 and the Owner (CAP-22), NOT_FOUND for anyone else | `resources/js/pages/sales/Receipt.tsx`; `App\Http\Controllers\Sales\ReceiptController`; `App\Queries\Sales\Receipts`; `App\Domain\Sales\Sales::receiptPath` |
| 4. The shortcuts F2, F8, Alt+1, Alt+2, Alt+U, Ctrl+Enter and Esc; the icon rail; an uncertain checkout resolved by "Cek status", a COMMITTED answer discarding the cart and opening its receipt (NC-13 (e)); RC-01's result at RP-103 and RC-02's at RP-101; RN-01 to RP-101 for a cashier and the combined account with an OPEN session; RP-104 with a session to RP-101; UXI-002 "Kasir" to `/till`; UXI-056 "Lihat hasil" to a Sale's receipt | `Till.tsx`, `Receipt.tsx`; `CheckoutSale::present`, `OpenCashSession::LOCATION`, `Homes::of`, `OpenSessionController::WITH_SESSION`; `resources/js/lib/navigation.ts`; `resources/js/pages/commands/Requests.tsx` |
| Copy | `lang/id/sales.php` (COPY_DECK sections 4.1, 7.1 to 7.3, 15), `lang/id/documents.php` (section 17.1), `lang/id/ux.php` (`ux.action.filter`), `resources/js/types/translation-keys.ts` |
| Routes | RP-101 `GET /till`, RP-102 `GET /till/payment` (CAP-21, `input.allow`), RP-103 `GET /sales/{sale}/receipt` (CAP-21 and CAP-22, `input.allow`) in `routes/web.php` |

## 3. Readings and technical choices

| Reading | Why |
| --- | --- |
| RP-101 and RP-102 are one page, `sales/Till`, whose two views are client visits that replace the same history entry: the cart and the payment's entries live in the page's memory and in that entry's encrypted props, and Back leaves the till rather than step between its views | ROUTE_CONTRACTS RP-102 ("a client view of the till page"); NC-04 and NC-13 (e): a history entry that held a cart a checkout paid could otherwise offer it again |
| While a checkout of the cart is out or its outcome unknown, and once it committed, the history entry holds an empty draft; the page's memory keeps the cart for "Kirim ulang" and "Mulai lagi dari keranjang". A reload therefore never restores a cart that may have been paid; a reload otherwise starts an empty cart, because Inertia gives a reload the server's props | NC-13 (e), BR-SALE-20; BR-SALE-22's restoration is a "may" (NAVIGATION_CONTRACTS UXP-101); prices restored by Back and Forward are validated by RC-01's expected revisions, PRICE_CHANGED returning to UXS-18 |
| The user's unresolved checkout receipts reach the till through RD-04, which the command client reads when the page opens and again before the final action, and through the references of this browser; RP-101's props carry the session's header, the onboarding state and the categories, not the receipts | INTERACTION_CONTRACTS section 1.4 and TASK-009's shared mechanism; ROUTE_CONTRACTS RP-101 lists the receipts among the page's inputs |
| A scan is the Enter that ends a code in the search field: its exact lookup starts at once and the answers are applied in the order of the scans (`createScanQueue`); a typed search is superseded by newer typing. An exact miss keeps the code in the field when no newer scan or typing took it, says UXS-33 and searches the text, so a name ended by Enter finds its products | BR-CAT-29 to BR-CAT-31, UXA-11; UXI-066's order "exact barcode, then SKU, then name" |
| "−" takes one base unit and stops at the smallest valid quantity of the product's precision, 1 for a whole product and 0,1 for one decimal | UXI-071; COPY_DECK `sales.cart.decrease_disabled` |
| A newer read of a product already in the cart that carries another price revision applies it: the same amount changes nothing shown; another amount is marked for review like a PRICE_CHANGED | BR-SALE-05: the total never changes silently |
| A change of the cart empties the payment's entries; going back and returning keeps them | NAVIGATION_CONTRACTS UXP-102, state preservation |
| Offline the final action is disabled with UXS-20's body as its reason and nothing is sent; a lookup that fails offline says UXS-22 and keeps the code; nothing is queued | BR-CAT-34, UXS-20; COPY_DECK section 15 has no reason of its own for this |
| The Owner's home stays Akun saya until Beranda exists (TASK-041); with an OPEN session "Kasir" leads him to the till | NC-12; EXC-52 |
| RP-103 renders the snapshot on screen in the look of DC-01's thermal layout for every size, A4 adding its title; the print view RP-103P with its A4 table and the copy marker is TASK-031's | KS-05; contract section 4.1 item 3 |
| The size of the receipt is kept in `sessionStorage` under `bk.receipt.size` | INTERACTION_INVENTORY UXI-091: a harmless interface preference for the browser session (SEC-CH-05) |
| The states of UI_STATES' rows UXP-101 and UXP-102 that the browser suite does not produce through the server: UXS-27, UXS-28, UXS-29, UXS-30, UXS-32 and UXS-46 are RC-01's refusals, answered in the browser in place of the server ("the refusals of RC-01"), whose server paths are TASK-028's tests; UXS-27 on RP-101 is the prop `postingFrozen`, asserted in L4, since no onboarding period can start in the browser suite before TASK-035; UXS-29 on RP-101 is the redirect to RP-104, asserted in L4; KS-03 `opened` is the note that RC-02 leaves, shown on the till's arrival, which `tests/Browser/cash/cash.spec.ts` reaches once per database — at every width once a session can be closed, TASK-030 | Review R-03; EXECUTION_CONTEXT section 10 |
| Every L5 path sells as the Owner; the cashier's rail and the copy that depends on the role (`ux.state.blocked.staff`) are rendered in L4's props only. A cashier's L5 run of KS-03 to KS-05 and one cash path needs a session that can be closed, so that the homes of `tests/Browser/support/access.ts` stay as they are | Review R-07; carried to TASK-030 |
| A scan into "Uang diterima", which holds the first focus with Tunai, replaces the amount with the code's digits; Enter sends nothing (AC-029-02), and the large change then shown is the safeguard before Ctrl+Enter | Review R-11 |
| RP-101's props do not carry the unresolved checkout receipts that ROUTE_CONTRACTS lists; RD-04 gives them (row above) | Review R-08: for the next change control of ROUTE_CONTRACTS |
| The browser tests sell as the Owner: the other people of Toko Contoh never hold an OPEN session in the browser suite, so that their homes stay as `tests/Browser/support/access.ts` says; the fixture `till` opens the Owner's session when `tests/Browser/cash/` has not | `playwright.config.ts` runs the people's files one at a time in the order of their paths |

## 4. Findings

| ID | Finding | Outcome |
| --- | --- | --- |
| F-1 | PHP does not run on this machine and `node_modules` has no `.bin` links, so `tsc` and ESLint do not run locally either; every static check is met first in CI | As recorded since TASK-011; the fast runs cover them |
| F-2 | For TASK-030: "Tutup sesi kas" (UXI-077) in the till's header, whose route RP-105 TASK-030 delivers (EXC-52) | TASK-030 |
| F-3 | For TASK-031: "Cetak struk" (UXI-090), the print view RP-103P and "Detail penjualan" (UXI-092, RP-202) on RP-103 | TASK-031 |
| F-4 | Fast run 1 failed PHPStan: the receipt's amounts were read as `mixed` and given to `Money::fromDatabase()`, which takes a whole number or a string | Fixed in checkpoint 2: the amount is checked to be either before it is read, else a defect (`LogicException`) |
| F-5 | Self-review: "Muat lebih banyak" continued the text then in the field, which a pick empties, rather than the search its rows answer | Fixed in checkpoint 2: the rows carry their search text, which the next page continues |
| F-6 | OPEN_ITEMS T-34 (b) and (d) and T-36 (e) name TASK-029 as "the till's recovery" and "the next change of `useCommand`"; the contract does not cite them (TASK-011's review NOTE R-19), and this Task does not change `useCommand` or the shell's refresh. The warning of T-36 (e) is met by the till: every COMMITTED answer, its own or an adopted one, discards the cart before the receipt opens, and the history entry never holds a cart a checkout may have paid | T-34 (b) and (d) and T-36 (e) stay OPEN, unchanged, for the next change of `useCommand` or the next change control |
| F-7 | Fast run 2 failed L10N01 (`CopyDeckTest`): its reading of a key named in a later cell took three approved forms of COPY_DECK that the till's keys are the first to use as copies — the table of section 5.1, whose cell after the keys lists the controls (`sales.cart.pay` → "UXI-075"; `sales.payment.method_cash` and `method_transfer` → "UXI-078"); a text after "Accessible names: " (`sales.cart.increase`, `decrease`); and a key followed by " + `ux.action.optional`", whose copy shows the label with the optional marker (`sales.payment.reference`, `customer_name`) | Fixed in checkpoint 3, in the test's reading only, as TASK-016, TASK-017 and TASK-025 extended it: a cell of interaction identifiers is not a copy; "Accessible name(s): " is a label before the text; the optional marker, read from `ux.action.optional`'s own row, must end such a copy and is taken off. Every assertion stays; the texts of `lang/id` are unchanged |
| F-8 | Fast run 3 failed ESLint: `no-useless-assignment` in `rememberedSize` (`resources/js/lib/sales/till.ts`), and a string literal in a JSX expression of `PaymentView` | Fixed in checkpoint 4: the size read returns from its `try`; the literal is a named constant |
| F-9 | Fast run 4 failed two of TASK-028's scans in `CheckoutSaleTest`: the payment boundary refused every route whose path holds "payment", which RP-102's contracted path `/till/payment` does; the one-writer scan allowed no file but the writer to name `sales` and `receipt_snapshots`, which RP-103's query `App\Queries\Sales\Receipts` reads | Fixed in checkpoint 5, as TASK-022 to TASK-026 list their readers: RP-102 is exempt by its exact path only, and the test proves it read only (GET and HEAD) and behind CAP-21; `Receipts` is listed as the one reader of both tables, and the test proves it holds no write. Every other assertion stays |
| F-10 | Fast run 5, the channel inspection of `TillPagesTest`: RP-103's page prop `shop`, the issuer's identity from the snapshot, replaced the shared prop `shop` of the shell — its name, initials and accent (TASK-010) — so the receipt page would have lost the shell's accent and initials | Fixed in checkpoint 6, a defect of this Task: the prop is `issuer` (`Receipts`, `Receipt.tsx`, the allowlist), and the test asserts the shared `shop` keeps its three fields on RP-103 for the cashier and the Owner (regression test) |

## 5. Tests (contract section 7)

| Test | Where |
| --- | --- |
| L1 the cart: one base unit per add and one line per product, fractions only typed, the precision, half-up line totals and their sum, "+" and "−" with the floor, the price review, a refusal's marks by the products sent, the tender and change, the request with the prices reviewed, the history's draft | `tests/Unit/Sales/cart.test.ts` |
| L1 the order of scans: answers applied in scan order whatever order they arrive, the same code twice twice, a reset | `tests/Unit/Sales/scanQueue.test.ts` |
| L4 RP-101 and RP-102 per role in its own session with exactly its props, each page only its path, Operations refused; without an OPEN session Buka sesi kas, with one the homes and RP-104 lead to the till; RP-103 from the snapshot to its cashier and the Owner, NOT_FOUND for another cashier and a guessed Sale, a transfer's receipt without cash amounts; RC-01's answer and its status check leading to RP-103 | `tests/Feature/Sales/TillPagesTest.php`; `CapabilityMatrixTest`, `RouteInventoryTest`, `CashPagesTest`, `OpenCashSessionTest`, `CheckoutSaleTest`, `tests/Harness/Channels/Allowlists.php` |
| L5 scanning with emulated wedge input (AC-029-01): order under a late lookup, an empty terminator, a held Enter, an unknown code, a typed fraction, a refused fraction, "−", a search by name with the arrow keys, F2, capture paused in the dialog, "Kosongkan keranjang" | `tests/Browser/sales/till.spec.ts` |
| L5 POS04 and the critical paths of EXC-34: a sale in cash and one by bank transfer, each in three actions by keyboard, the receipt, the next sale with one scan, Back never returning to the paid cart | `till.spec.ts` |
| L5 recovery with the faults of TS-22: a lost answer with "Kirim ulang"; a lost answer, the till's reason and UXP-006's "Cek status" and "Lihat hasil"; a lost answer and a reload; a held request with "Cek status" only — one Sale each | `till.spec.ts` |
| L5 the refusals: PRICE_CHANGED with "Setuju harga baru"; INSUFFICIENT_STOCK at its line; a scanner Enter on the payment screen; offline | `till.spec.ts` |
| L5 KS-03, KS-04 and KS-05 with their states at the widths of KEY_SCREENS section 4 and 1440 px with axe, targets, contrast and sideways scrolling; the tablet's final action; the refusals' states of UXP-102; the tablet's paths in counted taps; "Bayar" waiting for the scans still out; a 429 and its repeat | `till.spec.ts` |
| Fixtures through the Actions (EXC-45) | `TokoContoh::reprice`; `FixturesController::till` |

## 6. Acceptance criteria

The browser suite runs in CI on the pull request's final head (CE-44); until it has run there, each criterion that rests on it is met by its test as written, and its result is the browser suite's on that head.

| Criterion | Evidence | Result |
| --- | --- | --- |
| AC-029-01 Wedge scans: one base unit each, empty terminator and key repeat add nothing, in order, unknown code kept, a fraction only by an explicit edit | `till.spec.ts` "scanning"; `cart.test.ts`, `scanQueue.test.ts` | L1 in the fast runs; L5 on the final head |
| AC-029-02 A scanner Enter never confirms a payment; capture paused in a dialog; offline nothing queued, posting refused | `till.spec.ts` "a scanner Enter and offline", "scanning" (the dialog) | L5 on the final head |
| AC-029-03 One payment screen, no checkbox, no review dialog, one final action, Tunai preselected, the visible phases, the receipt with no further step | `till.spec.ts` "cash", "transfer", "recovery"; `TillPagesTest` (RC-01's location) | L4 and L5 on the final head |
| AC-029-04 A lost answer, a reload or a status check recovers the original Sale; HELD offers "Cek status" only; no second charge; recovery rechecks authorization | `till.spec.ts` "recovery" (one Sale each); the status check through RC-50, which authorizes each read (TASK-009) | L5 on the final head |
| AC-029-05 A definite refusal returns to the review with its reason at its line; the total never changes silently | `till.spec.ts` "refusals"; `cart.test.ts` (`applyRefusal`, `addProduct` repricing) | L1 in the fast runs; L5 on the final head |
| AC-029-06 KS-03 to KS-05 with their states at every width and 1440 px; the tablet's final action; the keyboard path of UXA-09 | `till.spec.ts` "KS-03 with its states" (`empty`, `default`, `price-changed`, `unresolved`), "KS-04 with its states" (`short`, `default`, `transfer`, `submitting`, `uncertain`, `held`; the tablet's final action 64 px and 24 px below the field above it), "KS-05 in each size" (58 mm, 80 mm, A4), each with axe, targets, contrast and sideways scrolling at every width of KEY_SCREENS section 4 and 1440 px; the refusals' states of UXP-102 ("the refusals of RC-01"); the keyboard paths of "cash" and "transfer". KS-03 `opened` and the rest of UI_STATES' rows in section 3, review R-03 | L5 on the final head |
| AC-029-07 The step budgets of USER_TASKS section 5 for selling, measured in Playwright | Section 7 | L5 on the final head |

## 7. Step counts (USER_TASKS section 5)

Measured in the browser suite, where each action of the path after the cart is filled is one numbered step of the test, the next screen asserted after it.

| Path | Budget | Measured path | Count |
| --- | --- | --- | --- |
| Sale paid CASH, PC | 3 | F8 → Alt+U → Ctrl+Enter ("cash") | 3 |
| Sale paid CASH, tablet | 3 | At 768 px by pointer: "Bayar" → "Uang Pas" → "Selesaikan", each tap counted ("three taps", review R-04) | 3 |
| Sale paid BANK_TRANSFER, PC | 3 | F8 → Alt+2 → Ctrl+Enter ("transfer"; the reference and the customer's name are optional and not counted) | 3 |
| Sale paid BANK_TRANSFER, tablet | 3 | At 768 px by pointer: "Bayar" → "Transfer bank" → "Dana sudah diterima — Selesaikan", each tap counted ("three taps") | 3 |
| Next sale after a receipt | 0 | A scan on RP-103 opens the till with that product in the cart ("cash") | 0 |

## 8. Definition of Done

| Dimension | Result | Reason |
| --- | --- | --- |
| Business | PASS on the final head's CI | Section 6; the cart's rules in L1 |
| UI and UX | PASS on the final head's CI | KS-03 to KS-05 built from the approved P7 artifacts with the components of the catalogue; the copy verbatim from COPY_DECK; the widths, axe and the targets in L5 |
| Backend | N/A | Three reads of pages and the locations of RC-01 and RC-02, no Action beyond section 4.2 |
| Database | N/A | No migration |
| Security | PASS | RP-101 and RP-102 under CAP-21, RP-103 scoped to its cashier and CAP-22 with NOT_FOUND otherwise (L4); the props allowlisted per channel; no secret; the guard |
| Data integrity | N/A | Writes no business fact; the cart a checkout may have paid is never kept in the history |
| Performance | PASS | The receipt one bounded read of its snapshot; the till's search through RD-01's paged reads; the budgets at TASK-048 |
| Operations | N/A | No backup, runbook or status impact |
| Verification | See sections 1 and 9 | The fast runs, the final head's CI, the self-review and the review of class A |

## 9. Review (EXECUTION_CONTEXT CE-40)

Self-review before the review: F-5, F-6 and F-10 (section 4). Round 1 by `bk-reviewer-high` on the package `.git/bantukasir-executor/review/TASK-029/r1` (head `bfaf2df`, tree `b368f4f`), `--check` `UNCHANGED`: verdict `FAIL`, four MUST-FIX.

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-01 | MUST-FIX | A scan's lookup answered after "Bayar" changed the cart under the payment screen — during SUBMITTING, after COMMITTED, or with a new price that the final action did not wait for | Fixed: the scan queue counts the scans still out and "Bayar" opens the payment only once they are applied (`scanQueue.ts`, `TillView`); leaving the till's view forgets every scan still out; while the page's checkout is out, unknown, committed or throttled, nothing changes the cart (`TillPage.add`); the final action also waits for an empty cart, a price to review or a line to correct. Regression tests: L1 `scanQueue.test.ts` (the count and the reset), L5 "\"Bayar\" waits for the scans still out" |
| R-02 | MUST-FIX | After a 429 (`throttled`) the final action was disabled without a reason and the way back let the cart change while the frozen request stayed | Fixed: in `throttled` the final action repeats the frozen request, the way back waits with its reason, and the history keeps the cart, since nothing ran. Regression test: L5 "a lost answer, a reload…", the 429 then one Sale |
| R-03 | MUST-FIX | KS-03 to KS-05 were checked at every width in a few of their states only; SUBMITTING asserted nowhere; UI_STATES' other states of UXP-101 and UXP-102 not shown | Fixed: three tests produce KS-03 `empty`, `default`, `price-changed`, `unresolved`; KS-04 `short`, `default`, `transfer`, `submitting`, `uncertain`, `held`; KS-05 in 58 mm, 80 mm and A4, each checked at every width; a test shows the refusals' states; the readings of section 3 for the rest |
| R-04 | MUST-FIX | The tablet path counted "by construction", the transfer path on PC only | Fixed: L5 "three taps" counts both at 768 px (section 7) |
| R-05 | SHOULD-FIX | A second scan on RP-103 before the till opened replaced the first | Fixed: the receipt holds every scan in order, opens the till once, and the till takes them all; regression in L5 "cash" (two scans on the receipt, two lines) |
| R-06 | SHOULD-FIX | The tablet's final action asserted at 56 px against the contract's 64 px | Fixed: 64 px; the payment view holds no cart controls, so the gap is measured to the field above it |
| R-07 | SHOULD-FIX | No L5 run as the cashier | Recorded (section 3), carried to TASK-030 |
| R-08 | NOTE | RP-101's receipts by RD-04, not props | Recorded (section 3), for the next change control of ROUTE_CONTRACTS |
| R-09 | NOTE | A failing answer stalled the scan queue; `draftOf`'s products not validated | The queue now passes the turn before applying an answer; `draftOf` unchanged, its input the page's own encrypted history |
| R-10 | NOTE | "−" described by a reason that is not rendered while the quantity is invalid | Fixed: the reason's id only while it is shown |
| R-11 | NOTE | A scan into "Uang diterima" replaces the amount | Recorded (section 3) |

Round 2, the targeted verification by `bk-reviewer-high` on the package `.git/bantukasir-executor/review/TASK-029/r2` (head `e3f8af1`, tree `25acb8f`, with round 1's package as the previous), `--check` `UNCHANGED`: verdict `PASS` — R-01 to R-04 FIXED, R-05 and R-06 without regression, no new MUST-FIX.

| ID | Severity | Finding | Outcome |
| --- | --- | --- | --- |
| R-12 | SHOULD-FIX | After a 429 the way back gave "Tunggu sampai hasil pembayaran diketahui.", against UXS-12 beside it, which says nothing ran | Fixed: in `throttled` the way back's reason is UXS-12's own body with its wait |
| R-13 | SHOULD-FIX | A scan that "Bayar" waited for and that added nothing (unknown, inactive, failed) let the payment open, its notice lost with the till | Fixed: such a scan cancels the waiting "Bayar", and the till stays with its notice. Regression test: L5 "\"Bayar\" waits for the scans still out", a held unknown code |
| R-14 | NOTE | Scans held by RP-103 stay in the page's memory when the visit to the till fails or is redirected | Accepted: the memory is the document's, never stored; a visit that does not reach the till leaves them for the till the same document opens next, the cashier's own scans of this sale |
| R-15 | NOTE | The R-01 test's wait was observable only right after F8 | Fixed: the test asserts no payment heading while the lookup is held |

## 10. Fix cycle 1 — the automated reviewer's findings on pull request #40 (CE-43)

The repository's automated reviewer raised two P2 findings on the head `d8cea77`; each was checked against the code and held, so each is a defect of this Task, fixed in `resources/js/pages/sales/Till.tsx` with a regression test. Its other output, a summary comment and a review receipt, holds no finding.

| ID | Finding | Outcome |
| --- | --- | --- |
| R-16 | Review comment 4537940397 (P2): an unknown code's answer called `runSearch(code)` unconditionally; when the cashier typed or scanned again while that exact lookup was out, its `supersede()` cancelled the newer typed search and showed the old code's results under the newer text | Held. Fixed: the code's text is searched only when the code goes back to the field — no newer scan, nothing typed (`queue.isLatest(scan)` and an empty field, the same condition as the field's), so BR-CAT-31 and the scan order are unchanged. Regression test: L5 "an unknown scan searches its code only while it keeps the field, and removing the last filter clears the results (pull request #40)": an untouched unknown code is searched; a held unknown code answered after typing keeps the typed text and its results, and its code is never searched |
| R-17 | Review comment 4537940412 (P2): with the field empty, removing the last category or brand filter left the filtered results or the empty-results state, since the filter effect did nothing without criteria; "Hapus filter" also let a search with the removed filter come back, through `edited('')`'s timer that read the filters before the change | Held. Fixed: the filter effect always supersedes the pending search, and with no text and no filter clears the results, as an emptied field does. Regression test: the same L5 test, the brand field emptied and "Hapus filter", the empty-results state gone in both |

Targeted verification (CE-43, class A, production behaviour changed) by `bk-reviewer-high` on the package `.git/bantukasir-executor/review/TASK-029/r3` (HEAD `d8cea77`, tree `976cbf7`, previous tree `25acb8f`), `--check` `UNCHANGED`: verdict `PASS` — R-16 and R-17 FIXED, no regression of BR-CAT-29 to BR-CAT-31, R-01 or R-13, the test failing before the fix and deterministic, no lint or type error seen by reading; two NOTEs: this record (done here), and the test's one fixed wait of one second after "Hapus filter", a negative check that cannot pass wrongly on the fixed code, the test failing before the fix at the brand field already (accepted, as ten other tests of the suite wait so).

## 11. Record (CE-50)

- Guard: `guard.sh --preview` of the uncommitted record against origin/develop `PASS`, no candidate to judge; the guard `PASS` with no candidate on every checkpoint (section 1); fix cycle 1's `guard.sh --preview` `PASS`, no candidate to judge.
- Backup impact: none — no table, no stored file, no procedure (DoD Operations N/A).
- The previous merge, TASK-028's pull request #39, is recorded in [its evidence](TASK-028.md) section 14.
- TASK_PLAN: TASK-029 `DONE`, TASK-030 `READY`, the totals TODO 19, READY 2, DONE 29; CURRENT_HANDOFF last.
