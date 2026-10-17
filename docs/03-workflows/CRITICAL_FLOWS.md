# Critical flows

| | |
| --- | --- |
| Status | CURRENT — written in RF-5 under D-087, 18 October 2026 (Asia/Jakarta); amended in RF-6 under D-088, 18 October 2026: WF-08, WF-09, WF-27 and WF-32, and the test references of section 1 |
| Phase | P3 — workflow contracts: each V1 workflow from its entry to its result, with its refusals, recovery, concurrency, records and proof |
| Replaces | `docs/03-workflows/legacy/P3-CRITICAL-WORKFLOWS.md` (LEGACY — SUPERSEDED; [LEGACY_INDEX](../00-governance/LEGACY_INDEX.md) section 14) |
| Decisions | D-002 to D-087 as cited; [ADR-007](../adr/ADR-007-command-identity-and-idempotency.md), [ADR-008](../adr/ADR-008-cost-allocation-representation.md), [ADR-009](../adr/ADR-009-command-receipts-and-reconciled-outcomes.md) |
| Related | [ROUTE_CONTRACTS](ROUTE_CONTRACTS.md), [INTERACTION_CONTRACTS](INTERACTION_CONTRACTS.md), [DOCUMENT_CONTRACTS](DOCUMENT_CONTRACTS.md), [REQUIREMENTS](../01-product/REQUIREMENTS.md), [USER_TASKS](../07-ux-design/USER_TASKS.md) |

The workflows of BantuKasir V1, each from the page where it starts to the record it leaves. A workflow names its actors, its user tasks (USER_TASKS), its routes (ROUTE_CONTRACTS), the requirements it carries (REQUIREMENTS) and the rules it applies; the request-level detail of each command is in INTERACTION_CONTRACTS section 5. Workflows add no business rule: where RF-5 had to settle a detail, D-087 records it and the rule documents state it. Workflow identifiers `WF-nn` are permanent.

## 1. Conventions

- **Draft, review, confirm.** Searching, scanning, entering quantities and reviewing write nothing (TR-CMD-03). Every critical mutation has a review with an explicit confirmation, except an ordinary Sale, which is confirmed on one payment screen (BR-CMD-04, D-040).
- **One command, one transaction.** The confirmation sends one command with the envelope of ADR-007; its facts, projections, audit event and result commit together or not at all (TR-TX-01). No human, printer, payment or cash handling happens inside it (TR-TX-03).
- **Outcomes.** COMMITTED leads to the result page; REJECTED returns to the review with the reason at the field or line; UNCERTAIN and HELD keep the frozen request and offer only its repeat or its status check (INTERACTION_CONTRACTS section 1.3). A new draft of the same business action is never started while the outcome is unknown (NC-13).
- **Authorization.** Every route checks its capability and object scope again; a hidden control is never the authorization (SEC-AZ-07).
- **Proof.** "Tests" name the race (TECHNICAL_RULES section 8) and the test families of SCHEMA section 9.4 that prove the workflow; the suites of every workflow are [ACCEPTANCE_SUITES](../08-testing/ACCEPTANCE_SUITES.md) section 9 (RF-6, D-088).

## 2. Workflows

### WF-01 Sign in, sign out and the sign-in session

| | |
| --- | --- |
| Actors | Every user (CAP-01) |
| User tasks | UXT-01, UXT-11 |
| Routes | RP-001, RA-01, RN-01, RA-02, RA-03, RA-04, RA-07 |
| Requirements | REQ-ACC-03, REQ-ACC-04, REQ-ACC-07, REQ-NFR-07 |
| Rules | SEC-AUTH-01 to SEC-AUTH-11, SEC-SESS-02, SEC-SESS-05, SEC-CH-02, SEC-CH-04, SEC-CH-05, BR-USR-06 |

1. The user signs in with email and password (RA-01). Failure gives one uniform message (UXS-42); repeated failures are throttled (UXS-12). Success regenerates the session and loads the role's home with a full page load (RN-01, NC-12).
2. Every later request passes the active-account check and the two limits of the sign-in session: 30 minutes without user activity, 12 hours at most. Automatic reads (RD-02, RD-03, repeated status checks) never count as activity (RT-10). Before the idle limit the page offers "Tetap masuk" (RA-07).
3. Sensitive actions ask for the password again when the last confirmation is older than 15 minutes (RA-03); the frozen request is then sent unchanged.
4. Sign-out (RA-02) warns first when the user has unresolved requests, and keeps their references. The sign-in session ends; browser storage is cleared except those references; a cash session is never closed, transferred or changed (BR-CASH-11).

Exceptions: a deactivated account cannot sign in and its sessions are revoked (BR-USR-05); an expired session leads to RP-001 with a full page load (NC-06). Tests: AUTH families of RF-6; the stock refresh does not keep a session alive (SEC-SESS-02).

### WF-02 First password and reset links

| | |
| --- | --- |
| Actors | The Owner issues (CAP-25, CAP-26); the staff member consumes, signed out |
| User tasks | UXT-12, UXT-35 |
| Routes | RC-35, RC-41, RC-42, RP-606, RP-002, RA-06 |
| Requirements | REQ-ACC-05 |
| Rules | D-049, SEC-ACC-04 to SEC-ACC-07, THR-21 |

1. The Owner creates a staff account (RC-35) or issues a link (RC-41), with recent authentication. The answer carries the link once; RP-606 shows it in page memory as text and a scannable code, never again after a reload (UXS-49).
2. The staff member opens the link; RP-002 reads the token from the fragment and removes it; the new password is posted to RA-06.
3. Under the account lock the token is checked (exists, unexpired, unused), the account must be ACTIVE and not anonymized; the password is set, the token removed and every sign-in session revoked (RACE-20). Every failure gives the same `LINK_INVALID` (UXS-41).
4. The Owner may revoke a link (RC-42); deactivation also revokes it.

Tests: RACE-20; a link never in a URL query, a log, an audit payload or browser storage.

### WF-03 Own account

| | |
| --- | --- |
| Actors | Every user (CAP-01); the Owner for the Owner's own details (CAP-27) |
| User tasks | UXT-11, UXT-39 |
| Routes | RP-003, RP-004, RA-05, RP-005, RC-37 |
| Requirements | REQ-ACC-06, REQ-ACC-08 |
| Rules | SEC-AUTH-11, TR-LOCK-05, SEC-ACC-10 |

1. A user changes their own password with the current one (RA-05); the account is locked once; the user's other sign-in sessions end.
2. The Owner changes the Owner's own display name or email with recent authentication (RC-37). Nobody changes their own role (BR-USR-04).

### WF-04 Open a cash session

| | |
| --- | --- |
| Actors | Owner, Cashier, Combined (CAP-21), as themselves |
| User tasks | UXT-02 |
| Routes | RN-01 or the navigation → RP-104 → RC-02 → RP-101 |
| Requirements | REQ-CASH-01, REQ-CASH-02, REQ-CASH-03, REQ-CASH-10 |
| Rules | BR-CASH-01, BR-CASH-04 to BR-CASH-06, BR-CASH-09, G-23 |

1. The user chooses an available register (active, no OPEN session) and counts the opening cash; Rp0 is valid, a blank amount is not (UXS-05).
2. "Tinjau" opens the review; "Buka sesi" sends RC-02.
3. Under the register lock: the register is active and free, the user has no OPEN session, and a BusinessProfile revision exists. The session gets its number and opens.
4. Result: the till (RP-101) with the session header.

Exceptions: no register free (UXS-48); a register taken meanwhile (`VALIDATION_FAILED`, UXS-05). A handover is A closes, then B opens (BR-CASH-09). D-068: before the opening cash is counted on a register whose previous session closed with refund cash still in the drawer, that cash is handed to the customer first (operational rule, RF-6 guidance). Tests: RACE-07, RACE-26.

### WF-05 Sale checkout

| | |
| --- | --- |
| Actors | Owner, Cashier, Combined (CAP-21), in their own OPEN session |
| User tasks | UXT-03, UXT-04, UXT-07 |
| Routes | RP-101, RD-01 (`context=till`), RP-102, RC-01, RP-103 |
| Requirements | REQ-SALE-01, REQ-SALE-02, REQ-SALE-03, REQ-SALE-04, REQ-SALE-05, REQ-SALE-06, REQ-SALE-07, REQ-SALE-09, REQ-SALE-10, REQ-SALE-11, REQ-PAY-01, REQ-PAY-02, REQ-PAY-03, REQ-PAY-05, REQ-SCAN-01, REQ-SCAN-02, REQ-SCAN-03, REQ-SCAN-04, REQ-CASH-04, REQ-CASH-13, REQ-COST-02, REQ-COST-10, REQ-INV-01, REQ-INV-02, REQ-INV-03, REQ-DOC-01 |
| Rules | BR-SALE-01 to BR-SALE-23, BR-PAY-01 to BR-PAY-07, BR-CAT-28 to BR-CAT-34, D-040, D-067, D-077 |

1. **Cart.** Scans and searches add products (RD-01). Each complete scan adds one base unit; a product already in the cart is added to its own line (D-067); fractional quantities are typed. An unknown code stays in the field (UXS-33). Nothing is reserved (BR-INV-01).
2. **Payment screen.** "Bayar" opens RP-102 with the total. CASH is chosen first (D-075): the tendered amount must be at least the total, "Uang Pas" fills it, the change shows before the final action. BANK_TRANSFER: the final button itself states that the funds were received (BR-SALE-12). An optional customer name and, for a transfer, an optional reference.
3. **Final action.** Pressing it freezes the intent; a scanner Enter can never press it (BR-SALE-13). RC-01 is sent.
4. **Commit.** Under the locks of the Sale checkout family the server checks the session, onboarding, each product, each price revision, the stock of the combined lines and the tender; then numbers and commits the Sale, its lines, payment, stock issues, cost allocations, receipt snapshot and the session's activity version (BR-SALE-17).
5. **Result.** The receipt (RP-103) with no further confirmation (BR-SALE-14); "Transaksi baru" or a scan starts the next sale.

Refusals return to the review before any payment or stock fact: `PRICE_CHANGED` (UXS-18: "Setuju harga baru" accepts the new prices, the total changes visibly), `INSUFFICIENT_STOCK` (UXS-31), `PRODUCT_INACTIVE` (UXS-32), `SESSION_NOT_OPEN` (UXS-30), `NO_OPEN_SESSION` (UXS-29), `ONBOARDING_FREEZE` (UXS-27), `POSTING_BLOCKED` (UXS-28), `TENDER_INSUFFICIENT` (UXS-34), `METHOD_NOT_ALLOWED` (UXS-46), a repeated product (`VALIDATION_FAILED`). The customer is never asked to pay again while the outcome is unknown (WF-07). Tests: RACE-01 to RACE-04, RACE-08, RACE-23 to RACE-25; Numbering; Failure injection; the D-067 refusal and the 200-product limit.

### WF-06 Receipt print and reprint

| | |
| --- | --- |
| Actors | The selling user for own Sales (CAP-21); the Owner for all (CAP-22) |
| User tasks | UXT-06 |
| Routes | RP-103, RC-44, RP-103P, RP-202 |
| Requirements | REQ-DOC-01, REQ-DOC-02, REQ-DOC-03, REQ-DOC-04, REQ-DOC-05, REQ-SALE-11 |
| Rules | BR-DOC-01 to BR-DOC-06, D-039, DC-01 |

1. "Cetak struk" chooses 58 mm, 80 mm or A4 and sends RC-44, which records the print request and its audit event.
2. The browser opens RP-103P, rendered from the receipt snapshot; the first print request of a Sale is the original, every later one carries the copy marker "Salinan" (DC-01).
3. The browser print dialog follows. A print request, or closing the dialog, is not proof of ink; a failed print is requested again (UXS-44) and never repeats or cancels the Sale (BR-SALE-21).

Tests: a reprint creates no Sale, Payment or movement; the snapshot keeps its BusinessProfile revision after a profile change (RACE-25).

### WF-07 Recover a request whose outcome is uncertain

| | |
| --- | --- |
| Actors | The request's user for their own requests; the Owner for any (CAP-34) |
| User tasks | UXT-08 |
| Routes | The page's uncertain panel; RP-006, RD-04, RC-50 |
| Requirements | REQ-SALE-06, REQ-SALE-08, REQ-CMD-01, REQ-CMD-04 |
| Rules | ADR-007, ADR-009, BR-CMD-01 to BR-CMD-05, NC-13, D-073, D-074 |

1. A timeout, a lost connection, a 5xx or `SERVICE_BUSY` leaves the request UNCERTAIN (UXS-14): the page offers "Kirim ulang", which repeats the same frozen request, and "Cek status" (RC-50).
2. `IDEMPOTENCY_CONFLICT` or `EPOCH_STALE` leaves it HELD (UXS-15): only "Cek status".
3. The status check answers COMMITTED (show the result; a Sale's cart is discarded), IN_PROGRESS (check again), NOT_COMMITTED (a fence makes the original impossible; the user may start again) or, for a request from before a restore, EPOCH_STALE until the restore reconciliation has recorded an outcome (WF-32).
4. RP-006 lists every unresolved request of the user — from this browser and from the server's receipts, so also those sent from another device — and, for the Owner, every user's (UXI-058). While one is unresolved, the user starts no new command of its family on any device (NC-13; INTERACTION_CONTRACTS section 1.4).
5. A reference lost with the browser's data is found again through its receipt; a request with no receipt and no record after the record-check wait never reached the server (INTERACTION_CONTRACTS section 2.4).

Tests: RACE-01, RACE-02, RACE-27, RACE-33; a receipt listed on a second device blocks the family there; a request refused before its receipt is not listed.

### WF-08 Close one's own cash session

| | |
| --- | --- |
| Actors | The session's responsible user (CAP-21) |
| User tasks | UXT-09 |
| Routes | RP-105, RC-03, RP-206 |
| Requirements | REQ-CASH-05, REQ-CASH-06, REQ-CASH-07, REQ-CASH-08, REQ-CASH-09, REQ-CASH-12, REQ-PAY-04 |
| Rules | BR-CASH-12 to BR-CASH-17, BR-CASH-19, BR-CASH-20, BR-CASH-22, D-068 |

1. **Preview.** RP-105 shows the session, its activity version, the totals the role may see, and every drawer-funded refund of the session with the reminder that its cash should no longer be in the drawer (D-068). "Lanjut hitung" is disabled while the user has an unresolved checkout (BR-CASH-16).
2. **Count.** Checkout pauses; the user counts the drawer and enters the physical cash (Rp0 valid). Scan capture is paused; a scanner Enter never closes (BR-CAT-32).
3. **Review and confirm.** The review shows physical cash, expected cash and the variance in words (COPY_DECK section 1.4); "Tutup sesi" sends RC-03 with the activity version.
4. **Commit.** Under the session lock with the same lock as checkout: if the activity version changed, or the user has a checkout request whose receipt is PENDING or HELD (G-27, D-088), `RECOUNT_REQUIRED` (UXS-19) and the session stays OPEN; otherwise the close, the immutable report snapshot and the CLOSED status commit together.
5. **Result.** RP-206 with the report; print (RC-45) and CSV export (RC-46) follow DC-02 and DC-03.

Tests: RACE-08, RACE-09, RACE-13 with the scenario of D-068 (a refund committed before the preview, its cash still in the drawer at the count: the session closes with variance +R, nothing else is created, and a payout against the CLOSED session stays refused), RACE-35 (D-088).

### WF-09 Owner-assisted close

| | |
| --- | --- |
| Actors | The Owner (CAP-23), for another user's OPEN session |
| User tasks | UXT-26 |
| Routes | RP-206 or RP-604 → RP-207, RD-04, RC-50, RC-04, RP-206 |
| Requirements | REQ-CASH-11 |
| Rules | BR-CASH-16, BR-CASH-18, D-069 (d), D-087 |

1. The Owner opens the session and chooses "Tutup sesi oleh Pemilik". The preview shows the responsible user, the register, the totals, the drawer-funded refunds and the responsible user's unresolved checkout requests from the server's receipts (ADR-009 section 4).
2. While such a request is listed, "Lanjut hitung" is disabled; the Owner resolves each with "Cek status" as the Owner. This meets BR-CASH-16 when the responsible user's browser is not available, and the server refuses RC-04 while such a receipt is unresolved (G-27, D-088).
3. The Owner counts the drawer, gives a reason, confirms with recent authentication; RC-04 commits like WF-08 step 4. The responsible user does not change; the close actor is the Owner.
4. The responsible user may be deactivated (WF-29) or, for an outgoing Owner, replaced (WF-32): the session stays OPEN until this close (D-069 (d), D-087).

Tests: RACE-09, RACE-28 (deactivation never changes the session; the deactivated user's commands are refused; the Owner-assisted close succeeds), RACE-21, RACE-35.

### WF-10 Session review and Owner review notes

| | |
| --- | --- |
| Actors | The Owner (CAP-22) |
| User tasks | UXT-25 |
| Routes | RP-205, RP-206, RC-43 |
| Requirements | REQ-CASH-09 |
| Rules | BR-CASH-19, D-068, D-069 (f) |

1. The Owner reads a CLOSED session: count, variance, report, the drawer-funded refunds beside the variance, and its review notes.
2. "Tambah catatan tinjauan" adds a note (RC-43); a correction is a new note. Notes never change the count, the variance or the report, and are never edited or deleted.
3. D-068: when a drawer-funded refund's cash R was still in the drawer at the count, the variance shows +R; the Owner explains it with a note, and R is taken from that drawer and handed to the customer before the next opening count on that register. The handover is that refund's own cash: no fact, column or cash adjustment is created, and a payout against the CLOSED session stays refused (BR-RFD-09).

Tests: a second note accepted, nothing else changed; the D-068 scenario (WF-08).

### WF-11 Refunds and returns

| | |
| --- | --- |
| Actors | The Owner only (CAP-24) |
| User tasks | UXT-27 |
| Routes | RP-202 → RP-203 → RC-05 or RC-06 → RP-204 |
| Requirements | REQ-RFD-01, REQ-RFD-02, REQ-RFD-03, REQ-RFD-04, REQ-RFD-05, REQ-RFD-06, REQ-RFD-07, REQ-RFD-08, REQ-RFD-09, REQ-COST-07 |
| Rules | BR-RFD-01 to BR-RFD-17, BR-COST-17, D-035, D-045, D-046, D-067, D-068 |

1. The Owner opens the original Sale and chooses lines (one per product), refund amounts within each line's remaining money and quantities within its remaining quantity, the condition of returned goods, and a reason.
2. **Funding.** CASH from the drawer of a listed OPEN session (its expected cash shown), CASH from the Owner's own funds, or BANK_TRANSFER with the reference of the payout made; a return without money needs no payout.
3. **Review and confirm** with recent authentication. RC-05 (drawer-funded) or RC-06.
4. **Commit.** Under the locks: for RC-05 the session OPEN and the refund within its expected cash (D-045); the cumulative bounds of the Sale (BR-RFD-03); a saleable return posts RETURN_RECEIPT with the cost of the original issue. The drawer-funded payout gets the reference `<Sale number>/<k>` (D-087).
5. **Result.** RP-204; for a drawer-funded refund, first the instruction to take the amount from the named drawer now (D-068). Cash leaves the drawer only now, after commit (BR-RFD-17).

Exceptions: `DRAWER_CASH_INSUFFICIENT` (UXS-36: choose another session or own funds; the system never switches), `SESSION_NOT_OPEN`, `REFUND_BOUND_EXCEEDED` or `RETURN_BOUND_EXCEEDED` (UXS-35), `ONBOARDING_FREEZE` for a saleable return. Uncertain: no cash is taken until the refund is COMMITTED; no replacement refund (RACE-15). Tests: RACE-10, RACE-13 to RACE-15; Costing (return cost restored once).

### WF-12 Sales and sessions: lookup, print and export

| | |
| --- | --- |
| Actors | Cashier and Combined for their own (CAP-21); the Owner for all (CAP-22) |
| User tasks | UXT-10 |
| Routes | RP-201, RP-202, RP-205, RP-206, RC-44 to RC-46, RP-206P |
| Requirements | REQ-SALE-12, REQ-CASH-12, REQ-DOC-04, REQ-RPT-05 |
| Rules | BR-CASH-21, BR-CASH-22, BR-DOC-06, BR-RPT-04 |

1. Lists are paged on the server with keyset paging and filters (INTERACTION_CONTRACTS section 3.2); a cashier never sees another user's Sales or sessions, and OPERATIONS_ADMIN has no access.
2. A CLOSED session's report prints (RC-45, RP-206P) and exports as CSV (RC-46, DC-03), both from the snapshot; a failed print or export never affects the CLOSED status.

### WF-13 Product search and stock lookup

| | |
| --- | --- |
| Actors | Every role with CAP-02; stock for CAP-14 |
| User tasks | UXT-13 |
| Routes | RD-01, RP-301, RP-302, RP-401 |
| Requirements | REQ-CAT-07, REQ-INV-11 |
| Rules | BR-CAT-23 to BR-CAT-27, BR-INV-43, BR-INV-44 |

One shared search serves every workflow (INTERACTION_CONTRACTS section 3.1): exact barcode, then SKU, then name; 25 results per page; a newer search supersedes an older one; results never show cost. The product page shows only the sections the role may see.

### WF-14 Add a product (search first)

| | |
| --- | --- |
| Actors | Owner, OPERATIONS_ADMIN, Combined (CAP-04, CAP-05, CAP-06) |
| User tasks | UXT-17 |
| Routes | RP-303, RD-06, RC-17, RP-302; over RP-405 or RP-413 (NC-11) |
| Requirements | REQ-CAT-01, REQ-CAT-02, REQ-CAT-03, REQ-CAT-04 |
| Rules | BR-CAT-01 to BR-CAT-05, BR-CAT-09, BR-CAT-16 to BR-CAT-19 |

1. Step 1 checks the barcode, SKU and name (RD-06): an exact match leads to that product; similar names need "Tetap buat produk baru" (UXS-47).
2. Step 2 fills SKU, name, active category, optional brand, unit, precision, price, minimum stock and an optional barcode; "Simpan produk" sends RC-17.
3. The product starts with stock 0 and no movement; opened from a working page, the result returns there with the product chosen.

Exceptions: `DUPLICATE_SKU`, `DUPLICATE_BARCODE` (UXS-37), `BARCODE_NOT_ELIGIBLE` (UXS-38), `CATEGORY_INACTIVE` (UXS-32). Tests: RACE-29; unique SKU without regard to letter case.

### WF-15 Edit products and categories

| | |
| --- | --- |
| Actors | Owner, OPERATIONS_ADMIN, Combined (CAP-04, CAP-05) |
| User tasks | UXT-18, UXT-22 |
| Routes | RP-304, RC-18 to RC-20, RP-307, RC-21 to RC-24 |
| Requirements | REQ-CAT-01, REQ-CAT-06, REQ-CAT-08, REQ-CAT-12 |
| Rules | BR-CAT-07 to BR-CAT-15 |

1. Edits carry the product's version; a price change appends a revision; minimum stock changes attention at once. Unit and precision are locked after the first movement.
2. Products and categories are deactivated and reactivated, never deleted; an inactive category cannot be chosen; deactivating it changes no product.

Tests: RACE-05, RACE-23, RACE-29.

### WF-16 Barcodes, photos and labels

| | |
| --- | --- |
| Actors | Owner, OPERATIONS_ADMIN, Combined (CAP-06, CAP-08, CAP-09) |
| User tasks | UXT-19, UXT-20 |
| Routes | RP-302, RC-25 to RC-29, RP-306, RC-47, RP-306P |
| Requirements | REQ-CAT-04, REQ-CAT-05, REQ-CAT-10, REQ-CAT-11 |
| Rules | BR-CAT-16 to BR-CAT-22, BR-CAT-35, BR-CAT-36, BR-CAT-44 to BR-CAT-48, D-052, DC-05 |

1. A manufacturer barcode is added (RC-27), an internal `BK-P-` code generated (RC-28), or a barcode retired with its reason class (RC-29). A retired token is never attached again, except by WF-17.
2. A photo is uploaded, replaced or removed (RC-25, RC-26); the file of a replaced or removed photo is kept.
3. Labels: the token and count are recorded (RC-47), then printed through the browser (RP-306P, DC-05); never a cost.

Tests: RACE-17, RACE-19; upload validation by content (SECURITY_MODEL section 13).

### WF-17 Barcode misassignment correction

| | |
| --- | --- |
| Actors | The Owner only (CAP-07) |
| User tasks | UXT-32 |
| Routes | RP-305, RD-05, RD-01 (`context=correction`), RC-30 |
| Requirements | REQ-CAT-13 |
| Rules | BR-CAT-50 to BR-CAT-52, D-051, D-052 |

The Owner scans or types the token (RD-05 shows its mapping and retirement), chooses the correct product, gives a reason and confirms with recent authentication. One transaction moves the active token or re-attaches a token retired as misassigned; earlier Sales keep their products (RACE-18). Exceptions: `BARCODE_NOT_ELIGIBLE` with the reason `not_correctable`; `STALE_VERSION` when the mapping changed since the lookup. Tests: RACE-17 to RACE-19.

### WF-18 CSV product import

| | |
| --- | --- |
| Actors | Owner, OPERATIONS_ADMIN, Combined (CAP-10) |
| User tasks | UXT-21 |
| Routes | RP-308, RR-01, RR-02, RC-31, RP-301 |
| Requirements | REQ-CAT-09 |
| Rules | BR-CAT-37 to BR-CAT-43, D-037, D-054, D-069 (h), DC-04 |

1. The user reads the column list (DC-04) and chooses a file; RR-01 validates the whole file and previews every row's errors, including a category name that matches no active category or more than one (D-069 (h)).
2. A file with errors cannot be confirmed; the user corrects it and previews again. "Batalkan impor" removes the temporary file (RR-02).
3. "Impor :jumlah produk" sends RC-31 with the file's checksum: the file is validated again; all rows or none; products start with stock 0; no stock, cost or category is created.

Tests: RACE-22, RACE-29; the four cases of D-069 (h); a file changed after preview refused (SEC-UP-04); the temporary file removed at the final outcome and within 24 hours.

### WF-19 Receiving

| | |
| --- | --- |
| Actors | Owner, OPERATIONS_ADMIN, Combined (CAP-11, CAP-12) |
| User tasks | UXT-14 |
| Routes | RP-405, RD-01 (`context=receiving`), RC-07, RP-406, RP-404 |
| Requirements | REQ-INV-04, REQ-INV-05, REQ-INV-13, REQ-COST-03 |
| Rules | BR-INV-12 to BR-INV-17, BR-COST-02 to BR-COST-05, D-033, D-047, D-069 (a) |

1. Lines name existing active products, quantities above zero and the purchase cost of each line: KNOWN, UNKNOWN ("Biaya perlu dilengkapi pemilik", never zero) or an explicit zero with evidence. An optional reference and document date never backdate the fact.
2. The draft lives in page memory only: no draft route, no browser storage (D-069 (a)); leaving asks first (UXS-45).
3. The review and "Konfirmasi barang masuk" send RC-07; RECEIPT movements, cost evidence, balances and valuation commit together.

Exceptions: `ONBOARDING_FREEZE`, `POSTING_BLOCKED`, `PRODUCT_INACTIVE`, `STALE_VERSION`. Tests: no fact before confirmation; RACE-24; OPERATIONS_ADMIN's cost view (D-047).

### WF-20 Manual issue

| | |
| --- | --- |
| Actors | Owner, OPERATIONS_ADMIN, Combined (CAP-13) |
| User tasks | UXT-15 |
| Routes | RP-408, RC-08, RP-409, RP-407 |
| Requirements | REQ-INV-06 |
| Rules | BR-INV-18 to BR-INV-22 |

Lines and a reason (Rusak, Pemakaian internal, Hilang, Sampel, Lainnya); the review shows stock before, change and after; RC-08 posts MANUAL_ISSUE with its issue-cost allocation. Insufficient stock refuses it (UXS-31); a manual issue never records a sale. Tests: RACE-03.

### WF-21 Stock attention and history

| | |
| --- | --- |
| Actors | Owner, OPERATIONS_ADMIN, Combined (CAP-14) |
| User tasks | UXT-16 |
| Routes | RP-401, RD-02, RD-03, RP-402, RP-403 |
| Requirements | REQ-INV-10, REQ-INV-11 |
| Rules | BR-INV-40 to BR-INV-44 |

The badge and the list come from one query and refresh at least every 30 seconds while visible, on focus and after a change; a failed refresh keeps the last data with its time (UXS-21). History is keyset-paged in posting order; OPERATIONS_ADMIN never sees cost beyond receiving documents.

### WF-22 Stock adjustment

| | |
| --- | --- |
| Actors | The Owner (CAP-16) |
| User tasks | UXT-30 |
| Routes | RP-410, RC-09, RP-402 |
| Requirements | REQ-INV-08 |
| Rules | BR-INV-31 to BR-INV-33, BR-COST-02 |

Signed lines, a reason, a count and a reference, recent authentication; a positive line carries cost evidence. Never negative stock, never used to hide a fact. Tests: RACE-03, RACE-24.

### WF-23 Reversal

| | |
| --- | --- |
| Actors | The Owner (CAP-17) |
| User tasks | UXT-31 |
| Routes | RP-403 → RP-411 → RC-10 → RP-403 |
| Requirements | REQ-INV-09, REQ-COST-08 |
| Rules | BR-INV-34 to BR-INV-37, ADR-008, D-087 |

The Owner reverses one standalone OPENING, RECEIPT or MANUAL_ISSUE, once, as its exact inverse, with a reason and recent authentication. A RECEIPT or MANUAL_ISSUE cannot be reversed while onboarding is active; an OPENING can (D-087, WF-25). Refused when stock would go negative, the history would become invalid or the replay is unsafe (`REPLAY_REJECTED`, UXS-40). Tests: RACE-10, INV03; Costing.

### WF-24 Start and release onboarding

| | |
| --- | --- |
| Actors | The Owner (CAP-15), recent authentication |
| User tasks | UXT-33 |
| Routes | RP-412, RC-12, RC-13 |
| Requirements | REQ-INV-07 |
| Rules | BR-INV-23, BR-INV-24, BR-INV-28, D-071, D-072, D-087 |

1. **Start** (RC-12) is offered only while onboarding has never been started or released for these business data and is not held. While it is active, Sales and ordinary postings are refused (UXS-27), products can still be created.
2. **Entry** follows WF-25.
3. **Release** (RC-13): the review shows the counted and not-counted products and the lines with unknown cost (BR-INV-28); the release is durable and final. Afterwards the page is read only and corrections use WF-22, WF-23 and WF-26 (D-071).
4. **After a restore** (D-072): a restored state that is not RELEASED is held until the Owner's restore reconciliation (WF-32); while held, no start, entry or release (UXS-50).

Tests: RACE-04, RACE-32; start refused after a release, also concurrently and after the restore of an older backup.

### WF-25 Opening stock entry and corrections before release

| | |
| --- | --- |
| Actors | The Owner (CAP-15; CAP-17 for a reversal) |
| User tasks | UXT-33 |
| Routes | RP-412, RP-413, RD-01 (`context=opening`), RC-11, RC-14, RP-403, RP-411, RC-10, RC-48 |
| Requirements | REQ-INV-07 |
| Rules | BR-INV-23 to BR-INV-27, BR-INV-34, D-071, D-087 (T-17 b) |

1. **Entry.** Up to 100 rows per command; each positive row has a quantity, a count reference and a cost KNOWN or UNKNOWN; a zero row records a zero count without a movement. All rows commit together (RC-11). A product is counted once: it has at most one effective onboarding entry (G-22).
2. **Correcting a zero count** (D-087). The Owner withdraws it with a reason and recent authentication (RC-14). The zero count stays; a withdrawal record is added; the product can then be counted again.
3. **Correcting a wrong OPENING** (D-087). The Owner reverses it in full (WF-23, allowed while onboarding is active) and then records, explicitly, a replacement OPENING that names the reversed line, with a reason and recent authentication (RC-48). The original OPENING, its reversal and the replacement all stay in the history. At most one OPENING per product is effective at any time.
4. **Never** a reset, a deletion, an edit of history, or any of these corrections after the release (D-071, D-087).

Tests: RACE-06 as amended, RACE-31; a replacement refused when the OPENING is not reversed, when the line was already replaced, after the release or while held; a withdrawal refused twice.

### WF-26 Cost completion and correction

| | |
| --- | --- |
| Actors | The Owner (CAP-19, CAP-20) |
| User tasks | UXT-29 |
| Routes | RP-503, RP-504, RC-15, RP-505 |
| Requirements | REQ-COST-01, REQ-COST-02, REQ-COST-03, REQ-COST-04, REQ-COST-05, REQ-COST-06, REQ-COST-09 |
| Rules | BR-COST-01 to BR-COST-29, ADR-008 |

The worklist shows unknown costs and products marked incomplete. The Owner enters the corrected or completed cost with a reason and recent authentication (RC-15); a bounded replay publishes the new valuation and allocations atomically, or refuses with no change and the mark of BR-COST-27 (UXS-40). Tests: RACE-11; Costing; G-15, G-21.

### WF-27 Reconciliation incidents

| | |
| --- | --- |
| Actors | Scheduled reconciliation; the Owner (CAP-18) |
| User tasks | UXT-34 |
| Routes | SC-01, RP-414, RP-415, RC-16 |
| Requirements | REQ-INV-12 |
| Rules | BR-INV-45, BR-INV-46 |

Once a day a read-only comparison runs; a mismatch blocks the product's postings (`posting_block.block`) and raises an incident. The Owner investigates under [OPERATIONS_RUNBOOK](../09-operations/OPERATIONS_RUNBOOK.md) RB-04, where the operator repairs a projection only with the rebuild OP-07 on the Owner's request, and the Owner reopens with recent authentication (RC-16). Tests: Reconciliation; RACE-24.

### WF-28 Owner home and reports

| | |
| --- | --- |
| Actors | The Owner (CAP-20, CAP-22) |
| User tasks | UXT-23, UXT-24, UXT-28 |
| Routes | RP-007, RP-501, RP-502, RD-03 |
| Requirements | REQ-RPT-01, REQ-RPT-02, REQ-RPT-03, REQ-RPT-04, REQ-RPT-05, REQ-RPT-06, REQ-PAY-04, REQ-COST-04, REQ-COST-09, REQ-DOC-05 |
| Rules | BR-RPT-01 to BR-RPT-04, BR-COST-35 to BR-COST-39, BR-PAY-06 |

Beranda shows today, the OPEN sessions and what needs attention, each panel on its own. Rekap harian computes the day once from source records. Laporan keuangan reads one snapshot for the chosen period, with its as-of time, completeness and trend; a custom range in the future, reversed or longer than 366 days is refused at the date fields (INTERACTION_CONTRACTS section 3.4). Variance and opening cash are never sales or profit.

### WF-29 User management

| | |
| --- | --- |
| Actors | The Owner (CAP-25 to CAP-29), recent authentication |
| User tasks | UXT-35 |
| Routes | RP-602 to RP-606, RC-35 to RC-42 |
| Requirements | REQ-ACC-01, REQ-ACC-02, REQ-ACC-05, REQ-ACC-06, REQ-ACC-09, REQ-ACC-10, REQ-ACC-11, REQ-ACC-12 |
| Rules | BR-USR-01 to BR-USR-12, D-032, D-042, D-043, D-048, D-049, D-069 (d) |

1. The Owner creates staff accounts (Cashier, Operations or both) and hands over their link (WF-02); changes roles, names and emails; deactivates and reactivates staff; anonymizes a deactivated account with a reason.
2. A deactivation takes effect at once, also while the user holds an OPEN cash session, which stays OPEN for an Owner-assisted close (WF-09; D-069 (d)).
3. No route creates, changes, deactivates, anonymizes or reactivates a SUPER_ADMIN account (CAP-33): only the operator procedures of WF-32.

Tests: RACE-04, RACE-16, RACE-20, RACE-28, RACE-30.

### WF-30 BusinessProfile and cash registers

| | |
| --- | --- |
| Actors | The Owner (CAP-30, CAP-31), recent authentication |
| User tasks | UXT-36, UXT-37 |
| Routes | RP-607, RP-608, RC-32, RP-609, RP-610, RC-33, RC-34 |
| Requirements | REQ-CFG-01, REQ-CFG-02, REQ-CFG-03, REQ-CFG-04, REQ-SCOPE-03 |
| Rules | BR-CFG-01 to BR-CFG-05, BR-CASH-02, G-19, G-23 |

1. A profile change creates a new revision (RC-32), including the accent from the constrained set; documents keep the revision they froze. The first revision exists from the bootstrap (WF-32), so a Sale, an open or a close never lacks one (G-23).
2. Registers are created or changed only while no session is OPEN on them (RC-33, RC-34).

Tests: RACE-25, RACE-26.

### WF-31 System status, activity and security log

| | |
| --- | --- |
| Actors | The Owner (CAP-32) |
| User tasks | UXT-38 |
| Routes | RP-611, RP-612, RP-613 |
| Requirements | REQ-OPS-04, REQ-AUD-01, REQ-AUD-02 |
| Rules | BR-AUD-01 to BR-AUD-06, SEC-LOG-02 to SEC-LOG-05 |

System status shows each item with its evidence; anything unproven is "Belum Terverifikasi" (UXS-43). Riwayat aktivitas lists audit events with the labels of INTERACTION_CONTRACTS section 8 and the Owner's filters; Log keamanan lists security events (SUCCEEDED or REFUSED). OPERATIONS_ADMIN and cashiers have no access.

### WF-32 Operator procedures and restore

| | |
| --- | --- |
| Actors | The operator, a person and never an AI agent (D-088), on the Owner's authorization; the Owner for decisions |
| User tasks | — (outside the interface) |
| Routes | OP-01 to OP-08; RC-50, RD-04 |
| Requirements | REQ-ACC-05, REQ-ACC-12, REQ-CMD-03, REQ-OPS-01, REQ-OPS-02 |
| Rules | SEC-OP-01 to SEC-OP-05, BR-CMD-06, D-048, D-072, D-073, D-087 |

1. **Bootstrap** (OP-01): the first Owner account and link, the recovery epoch, the onboarding state and the first BusinessProfile revision with the shop's name; refused once any account exists (RACE-30).
2. **Owner recovery** (OP-02): a RESET link for the one Owner account.
3. **Owner replacement** (OP-03): the outgoing Owner is deactivated, also while holding an OPEN cash session, which stays OPEN until the new Owner closes it with an Owner-assisted close (WF-09; D-087); a new Owner account and link.
4. **After a restore** (OP-04): a new recovery epoch, every sign-in session revoked, and an onboarding hold when the restored onboarding state is not RELEASED (D-072).
5. **Restore reconciliation** (OP-05): the Owner decides, for each request reported as EPOCH_STALE, that it is not committed in the restored data — a fence makes it final — and, for the onboarding hold, whether onboarding was released before the restore (then it stays released) or never (then it continues as the same period). Every decision is audited. The Owner's own user-management requests are reconciled before the security changes of SEC-OP-04 are re-applied (D-073).

6. **Cost recovery, projection rebuild and drill record** (OP-06 to OP-08, written in RF-6): the Owner's correction published with an extended replay bound (OPERATIONS_RUNBOOK RB-12); a projection rebuilt from facts after a reconciliation incident (RB-04); a production restore drill recorded (BACKUP_RECOVERY RB-02).

Backups, restore drills and the runbooks are [BACKUP_RECOVERY](../09-operations/BACKUP_RECOVERY.md) and [OPERATIONS_RUNBOOK](../09-operations/OPERATIONS_RUNBOOK.md) (REQ-OPS-01, REQ-OPS-02; D-088). Tests: RACE-12, RACE-21, RACE-30, RACE-34; a restore of the same backup twice still refuses stale intents.

## 3. Cross-cutting requirements

Requirements that no single workflow carries are held by the contracts named:

| Requirement | Held by |
| --- | --- |
| REQ-SCOPE-01, REQ-SCOPE-02 | ROUTE_CONTRACTS: one installation, one stock pool, no route for anything outside V1 (PRODUCT_BLUEPRINT section 8) |
| REQ-ACC-03, REQ-ACC-04 | ROUTE_CONTRACTS RT-05; INTERACTION_CONTRACTS section 3 (fields per role) |
| REQ-ACC-08 | INTERACTION_CONTRACTS section 1.5 |
| REQ-CMD-02 | Section 1 of this document; INTERACTION_CONTRACTS section 1 |
| REQ-OPS-03, REQ-OPS-06 | The release boundary and the staging environment, RF-6 and the release gates |
| REQ-OPS-05 | No `DELETE` route and no deleting command (ROUTE_CONTRACTS section 8) |
| REQ-NFR-01 | Every text from COPY_DECK keys (INTERACTION_CONTRACTS section 11 for the proposals) |
| REQ-NFR-02, REQ-NFR-03 | The same routes for PC and tablet; gate DEVICES |
| REQ-NFR-04, REQ-NFR-05, REQ-NFR-06 | TECHNICAL_RULES section 13; RF-6 |
| REQ-NFR-07 | SECURITY_MODEL through ROUTE_CONTRACTS RT-04 to RT-12 |
| REQ-NFR-08, REQ-NFR-09 | ADR-006 values (INTERACTION_CONTRACTS conventions); server UTC facts, Asia/Jakarta dates |
| REQ-NFR-10 | DOCUMENT_CONTRACTS: no tax figure, no tax-invoice claim |
| REQ-UX-01, REQ-UX-02, REQ-UX-03 | The approved P7 artifacts; the states of every contract map to UI_STATES |

## 4. Traceability

Every requirement of REQUIREMENTS and every user task of USER_TASKS (UXT-05 is retired by D-077) with the workflows that carry it, generated from the rows of section 2 and the table of section 3.

| Requirement | Carried by |
| --- | --- |
| REQ-SCOPE-01 | Section 3 |
| REQ-SCOPE-02 | Section 3 |
| REQ-SCOPE-03 | WF-30 |
| REQ-ACC-01 | WF-29 |
| REQ-ACC-02 | WF-29 |
| REQ-ACC-03 | WF-01, Section 3 |
| REQ-ACC-04 | WF-01, Section 3 |
| REQ-ACC-05 | WF-02, WF-29, WF-32 |
| REQ-ACC-06 | WF-03, WF-29 |
| REQ-ACC-07 | WF-01 |
| REQ-ACC-08 | WF-03, Section 3 |
| REQ-ACC-09 | WF-29 |
| REQ-ACC-10 | WF-29 |
| REQ-ACC-11 | WF-29 |
| REQ-ACC-12 | WF-29, WF-32 |
| REQ-CFG-01 | WF-30 |
| REQ-CFG-02 | WF-30 |
| REQ-CFG-03 | WF-30 |
| REQ-CFG-04 | WF-30 |
| REQ-CAT-01 | WF-14, WF-15 |
| REQ-CAT-02 | WF-14 |
| REQ-CAT-03 | WF-14 |
| REQ-CAT-04 | WF-14, WF-16 |
| REQ-CAT-05 | WF-16 |
| REQ-CAT-06 | WF-15 |
| REQ-CAT-07 | WF-13 |
| REQ-CAT-08 | WF-15 |
| REQ-CAT-09 | WF-18 |
| REQ-CAT-10 | WF-16 |
| REQ-CAT-11 | WF-16 |
| REQ-CAT-12 | WF-15 |
| REQ-CAT-13 | WF-17 |
| REQ-SCAN-01 | WF-05 |
| REQ-SCAN-02 | WF-05 |
| REQ-SCAN-03 | WF-05 |
| REQ-SCAN-04 | WF-05 |
| REQ-INV-01 | WF-05 |
| REQ-INV-02 | WF-05 |
| REQ-INV-03 | WF-05 |
| REQ-INV-04 | WF-19 |
| REQ-INV-05 | WF-19 |
| REQ-INV-06 | WF-20 |
| REQ-INV-07 | WF-24, WF-25 |
| REQ-INV-08 | WF-22 |
| REQ-INV-09 | WF-23 |
| REQ-INV-10 | WF-21 |
| REQ-INV-11 | WF-13, WF-21 |
| REQ-INV-12 | WF-27 |
| REQ-INV-13 | WF-19 |
| REQ-COST-01 | WF-26 |
| REQ-COST-02 | WF-05, WF-26 |
| REQ-COST-03 | WF-19, WF-26 |
| REQ-COST-04 | WF-26, WF-28 |
| REQ-COST-05 | WF-26 |
| REQ-COST-06 | WF-26 |
| REQ-COST-07 | WF-11 |
| REQ-COST-08 | WF-23 |
| REQ-COST-09 | WF-26, WF-28 |
| REQ-COST-10 | WF-05 |
| REQ-SALE-01 | WF-05 |
| REQ-SALE-02 | WF-05 |
| REQ-SALE-03 | WF-05 |
| REQ-SALE-04 | WF-05 |
| REQ-SALE-05 | WF-05 |
| REQ-SALE-06 | WF-05, WF-07 |
| REQ-SALE-07 | WF-05 |
| REQ-SALE-08 | WF-07 |
| REQ-SALE-09 | WF-05 |
| REQ-SALE-10 | WF-05 |
| REQ-SALE-11 | WF-05, WF-06 |
| REQ-SALE-12 | WF-12 |
| REQ-PAY-01 | WF-05 |
| REQ-PAY-02 | WF-05 |
| REQ-PAY-03 | WF-05 |
| REQ-PAY-04 | WF-08, WF-28 |
| REQ-PAY-05 | WF-05 |
| REQ-DOC-01 | WF-05, WF-06 |
| REQ-DOC-02 | WF-06 |
| REQ-DOC-03 | WF-06 |
| REQ-DOC-04 | WF-06, WF-12 |
| REQ-DOC-05 | WF-06, WF-28 |
| REQ-CASH-01 | WF-04 |
| REQ-CASH-02 | WF-04 |
| REQ-CASH-03 | WF-04 |
| REQ-CASH-04 | WF-05 |
| REQ-CASH-05 | WF-08 |
| REQ-CASH-06 | WF-08 |
| REQ-CASH-07 | WF-08 |
| REQ-CASH-08 | WF-08 |
| REQ-CASH-09 | WF-08, WF-10 |
| REQ-CASH-10 | WF-04 |
| REQ-CASH-11 | WF-09 |
| REQ-CASH-12 | WF-08, WF-12 |
| REQ-CASH-13 | WF-05 |
| REQ-RFD-01 | WF-11 |
| REQ-RFD-02 | WF-11 |
| REQ-RFD-03 | WF-11 |
| REQ-RFD-04 | WF-11 |
| REQ-RFD-05 | WF-11 |
| REQ-RFD-06 | WF-11 |
| REQ-RFD-07 | WF-11 |
| REQ-RFD-08 | WF-11 |
| REQ-RFD-09 | WF-11 |
| REQ-RPT-01 | WF-28 |
| REQ-RPT-02 | WF-28 |
| REQ-RPT-03 | WF-28 |
| REQ-RPT-04 | WF-28 |
| REQ-RPT-05 | WF-12, WF-28 |
| REQ-RPT-06 | WF-28 |
| REQ-AUD-01 | WF-31 |
| REQ-AUD-02 | WF-31 |
| REQ-CMD-01 | WF-07 |
| REQ-CMD-02 | Section 3 |
| REQ-CMD-03 | WF-32 |
| REQ-CMD-04 | WF-07 |
| REQ-OPS-01 | WF-32 |
| REQ-OPS-02 | WF-32 |
| REQ-OPS-03 | Section 3 |
| REQ-OPS-04 | WF-31 |
| REQ-OPS-05 | Section 3 |
| REQ-OPS-06 | Section 3 |
| REQ-NFR-01 | Section 3 |
| REQ-NFR-02 | Section 3 |
| REQ-NFR-03 | Section 3 |
| REQ-NFR-04 | Section 3 |
| REQ-NFR-05 | Section 3 |
| REQ-NFR-06 | Section 3 |
| REQ-NFR-07 | WF-01, Section 3 |
| REQ-NFR-08 | Section 3 |
| REQ-NFR-09 | Section 3 |
| REQ-NFR-10 | Section 3 |
| REQ-UX-01 | Section 3 |
| REQ-UX-02 | Section 3 |
| REQ-UX-03 | Section 3 |

| User task | Carried by |
| --- | --- |
| UXT-01 | WF-01 |
| UXT-02 | WF-04 |
| UXT-03 | WF-05 |
| UXT-04 | WF-05 |
| UXT-06 | WF-06 |
| UXT-07 | WF-05 |
| UXT-08 | WF-07 |
| UXT-09 | WF-08 |
| UXT-10 | WF-12 |
| UXT-11 | WF-01, WF-03 |
| UXT-12 | WF-02 |
| UXT-13 | WF-13 |
| UXT-14 | WF-19 |
| UXT-15 | WF-20 |
| UXT-16 | WF-21 |
| UXT-17 | WF-14 |
| UXT-18 | WF-15 |
| UXT-19 | WF-16 |
| UXT-20 | WF-16 |
| UXT-21 | WF-18 |
| UXT-22 | WF-15 |
| UXT-23 | WF-28 |
| UXT-24 | WF-28 |
| UXT-25 | WF-10 |
| UXT-26 | WF-09 |
| UXT-27 | WF-11 |
| UXT-28 | WF-28 |
| UXT-29 | WF-26 |
| UXT-30 | WF-22 |
| UXT-31 | WF-23 |
| UXT-32 | WF-17 |
| UXT-33 | WF-24, WF-25 |
| UXT-34 | WF-27 |
| UXT-35 | WF-02, WF-29 |
| UXT-36 | WF-30 |
| UXT-37 | WF-30 |
| UXT-38 | WF-31 |
| UXT-39 | WF-03 |

Result: 134 requirements, 134 carried; 38 user tasks, 38 carried; 32 workflows.
