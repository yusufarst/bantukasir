# TASK-029 — Till, payment and receipt pages

| | |
| --- | --- |
| Status | `TODO`; the status of record is [TASK_PLAN](../TASK_PLAN.md) section 4 |
| Phase and lane | 6 Cash and sales · lane S, cash and sales |
| Size and review | L · review class A — adversarial review in a separate session ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) section 12) |
| Depends on | [TASK-028](TASK-028-checkout.md), [TASK-017](TASK-017-catalog-pages.md) |
| Gates | — ([TASK_PLAN](../TASK_PLAN.md) section 3) |
| Branch | `task/TASK-029-till-pages` from `develop` (EXC-22) |
| Executor | Claude Opus 5.5 at effort Max (D-066), on one explicit instruction of the Owner (EXC-10, EXC-11) |
| MCP | None required. |
| Modules | Sales (pages), Frontend |
| Baseline | RF-7, D-090 |

## 1. Goal

The cashier's till, payment screen and receipt page on PC and tablet — scanning, the cart with one line per product, one payment screen with one final action, the recovery of an uncertain checkout — exactly as the approved key screens show.

## 2. Prerequisites

- Every Task under "Depends on" is `DONE`, and every gate above is satisfied ([EXECUTION_CONTEXT](../EXECUTION_CONTEXT.md) EXC-21).
- The Owner's instruction names this Task and states what it authorizes beyond local commits (EXC-10).
- The checks of EXECUTION_CONTEXT section 5 pass.

## 3. Read first

After AGENTS.md, CURRENT_HANDOFF, EXECUTION_CONTEXT and TASK_PLAN sections 1 to 4 (EXC-01):

- KEY_SCREENS KS-03, KS-04 and KS-05 with their previews
- DESIGN_SYSTEM sections 7.2, 7.3, 8.2, 8.14 and 11
- ADAPTIVE_AND_ACCESSIBILITY UXA-07, UXA-09 to UXA-11 and UXA-25
- USER_TASKS section 5
- NAVIGATION_CONTRACTS, INTERACTION_INVENTORY and UI_STATES for UXP-101 to UXP-103
- COPY_DECK sections 7.1 to 7.3 and 14.1
- INTERACTION_CONTRACTS sections 1.3, 1.4, 1.7, 2.1, 3.1 and 9
- CATALOG_BARCODE section 5 (BR-CAT-28 to BR-CAT-34)
- SALES_CASH section 3; D-040, D-067, D-075
- CRITICAL_FLOWS WF-05 and WF-07
- ROUTE_CONTRACTS RP-101 to RP-103

## 4. Scope

### 4.1 In scope

1. Build RP-101 (KS-03): search and scanning through RD-01 `till` with `exact` for a scan; one base unit per complete scan; empty CR, LF and key repeat add nothing; scans applied in scan order, a late lookup never overriding a newer one; an unknown code kept in the field (UXS-33); fractions only by an explicit quantity edit; a product already in the cart added to its own line (D-067); the stepper and remove; the total and "Bayar" (F8); capture paused while a dialog is open; the price-changed state with "Setuju harga baru" (UXS-18); the offline banner with nothing queued (UXS-20); the session header; the unresolved-checkout panel; thumbnails with the placeholder.
2. Build RP-102 (KS-04): Tunai preselected (D-075); tendered amount and "Uang Pas" (Alt+U); the change shown before the final action; for a transfer the final action "Dana sudah diterima — Selesaikan"; the final action 64 px high, at least 24 px from other controls, never the initial focus, never activated by Enter, with the Ctrl+Enter repeat guard; SUBMITTING, UNCERTAIN and HELD with the Sale family's panel (never ask the customer to pay again); the optional customer name and transfer reference; loaded without a cart, back to RP-101.
3. Build RP-103 (KS-05): the committed receipt from the snapshot with no further confirmation; "Transaksi baru" and a scan start the next sale. Its print controls are added by TASK-031.
4. Bind the provisional shortcuts (F2, F8, Alt+1, Alt+2, Alt+U, Ctrl+Enter); use the icon rail of the till pages; resolve an uncertain checkout through "Cek status", a COMMITTED answer discarding the cart and opening its receipt (NC-13 (e)); switch RN-01 to RP-101 for a cashier and for the combined account with an OPEN session.

### 4.2 Owned by this Task

| Kind | Items |
| --- | --- |
| Tables | — |
| Keys completed (EXC-44) | — |
| Routes | RP-101, RP-102, RP-103 |
| Route elements added | RN-01: home of a cashier and of the combined account with an OPEN session (RP-101) |
| Pages | UXP-101 "Kasir", UXP-102 "Pembayaran", UXP-103 "Struk" |
| Controls | 25: UXI-066, UXI-067, UXI-068, UXI-069, UXI-070, UXI-071, UXI-072, UXI-073, UXI-074, UXI-075, UXI-076, UXI-078, UXI-079, UXI-080, UXI-081, UXI-082, UXI-083, UXI-084, UXI-085, UXI-086, UXI-087, UXI-088, UXI-089, UXI-091, UXI-093 |
| Controls extended with a destination | UXI-002, UXI-056 |
| Document contracts | — |

Controls are those of [TRACEABILITY](../TRACEABILITY.md) section 9.2, each built with its route of INTERACTION_CONTRACTS section 9 (EXC-52).

### 4.3 Out of scope

- Printing (TASK-031).
- Closing the session (TASK-030).

## 5. Traceability

| Subject | Owned or carried here | Contributed to |
| --- | --- | --- |
| Requirements | REQ-SALE-04, REQ-SALE-06, REQ-SALE-07, REQ-SALE-08, REQ-SCAN-01, REQ-SCAN-02, REQ-SCAN-03, REQ-SCAN-04 | REQ-NFR-02, REQ-PAY-02, REQ-UX-01, REQ-UX-03 |
| Workflows | WF-05 | WF-07 |
| User tasks | UXT-03, UXT-04, UXT-07 | — |
| Acceptance suites | SEARCH03 (final: TASK-044), POS03 (final: TASK-044), POS04 (final: TASK-044) | POS02, UX01, UX02, CMD01 |
| Races | — | RACE-27 |
| Guards | — | — |
| Threats | — | — |
| Open items | — | — |

"Owned" means this Task builds the harness and the cases of the suite, race, guard or threat; contributors add the cases of what they add. A suite reaches `FINAL_PASS` only at its final point (TEST_STRATEGY TS-09).

## 6. Acceptance criteria

- **AC-029-01** With emulated keyboard-wedge input, each complete scan adds one base unit, empty terminators and key repeat add nothing, scans apply in order, an unknown code stays in the field and a fraction needs an explicit edit.
- **AC-029-02** A scanner Enter never confirms a payment; capture pauses while a dialog or review is open; offline, nothing is queued and posting is refused.
- **AC-029-03** The payment screen has no checkbox and no separate review dialog, exactly one final action, Tunai preselected, and visibly shows enabled, submitting, committed, rejected and uncertain; the receipt follows the commit without a further step.
- **AC-029-04** A lost response, a reload or a status check recovers the original Sale; HELD offers "Cek status" only; nothing allows a second charge; recovery rechecks authorization.
- **AC-029-05** A definite rejection returns to the review with the reason at its line and never changes the total silently.
- **AC-029-06** KS-03, KS-04 and KS-05 are reproduced with their states at the widths of KEY_SCREENS section 4 and 1440 px; on the tablet layout the final action has a production-usable target away from the cart controls; the keyboard path of UXA-09 works with the provisional shortcuts.
- **AC-029-07** The step budgets of USER_TASKS section 5 for selling are met, measured in Playwright.

## 7. Tests

- L5 scanning with emulated wedge input.
- L5 POS04 and the KS states.
- L5 recovery with TS-22 faults.
- L5 accessibility and keyboard paths.
- The standing obligations of EXECUTION_CONTEXT section 10 for every route, table, command, read, page, procedure and document this Task adds.

## 8. Evidence

`docs/11-tasks/evidence/TASK-029.md` (EXC-60 to EXC-63), with the records of TEST_STRATEGY TS-30 and, in addition:

- The measured step counts against USER_TASKS section 5.

## 9. Definition of Done

| Dimension | This Task |
| --- | --- |
| Business | Applies — the criteria of section 6 and the requirements of section 5 |
| UI and UX | Applies — the approved P7 artifacts for its pages and controls (EXC-50 to EXC-53) |
| Backend | N/A — no route or Action beyond those of section 4.2 |
| Database | N/A — no migration |
| Security | Applies — the threat paths of section 5, the refusals of the other roles and the combined account, no secret, the static checks |
| Data integrity | N/A — writes no business fact |
| Performance | Applies — bounded queries and paging; the budgets are measured at TASK-048 |
| Operations | N/A — no backup, runbook or status impact |
| Verification | Applies — the command contract green; self-review; the reviews of class A (EXECUTION_CONTEXT section 12); evidence recorded |

Each dimension is recorded `PASS`, `FAIL`, `BLOCKED` or `N/A` with its reason (EXECUTION_CONTEXT section 13).

## 10. Stop conditions

- A page cannot follow the approved artifacts (EXC-50).
- Every stop condition of EXECUTION_CONTEXT section 14.

## 11. Documents this Task updates

- Its row in TASK_PLAN section 4, its evidence file, and CURRENT_HANDOFF last (EXECUTION_CONTEXT section 15)

## 12. For later Tasks

- TASK-030 adds the close entry; TASK-031 adds printing; TASK-046 verifies the real devices.
- Tasks that depend on this one directly: [TASK-030](TASK-030-session-close.md).

## 13. Expected files

- `resources/js/pages/sales/Till`, `resources/js/pages/sales/Receipt`
- `app/Http/Controllers/Sales/`
- `lang/id/sales.php`

The layout follows STACK_AND_MODULES section 3; a different path that the pinned framework needs is recorded under STK-22.
