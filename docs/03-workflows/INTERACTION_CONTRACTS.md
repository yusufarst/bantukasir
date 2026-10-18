# Interaction contracts

| | |
| --- | --- |
| Status | CURRENT — written in RF-5 under D-087, 18 October 2026 (Asia/Jakarta); amended in RF-6 under D-088, 18 October 2026: sections 1.4 and 2.5 and RC-03 and RC-04, for the Owner's decision on N-07; the audit codes of OP-06 and OP-07 in sections 8, 11 and 12; corrected under D-089, 18 October 2026: the settlement of request receipts in sections 2.1 to 2.3 and 2.5 and in RC-03 and RC-04 (finding B-2 of the RF-6 review) |
| Phase | P3 — the interaction contracts of AICWDF section 14: for every command and read, its request, validation, checks, result and refusals, and the route of every control of INTERACTION_INVENTORY |
| Decisions | D-033, D-035, D-040, D-045, D-046, D-049, D-051, D-052, D-054, D-062, D-067 to D-074, D-077, D-086, D-087, D-088, D-089; [ADR-007](../adr/ADR-007-command-identity-and-idempotency.md), [ADR-009](../adr/ADR-009-command-receipts-and-reconciled-outcomes.md) |
| Related | [ROUTE_CONTRACTS](ROUTE_CONTRACTS.md), [CRITICAL_FLOWS](CRITICAL_FLOWS.md), [DOCUMENT_CONTRACTS](DOCUMENT_CONTRACTS.md), [UI_STATES](../07-ux-design/UI_STATES.md), [NAVIGATION_CONTRACTS](../07-ux-design/NAVIGATION_CONTRACTS.md), [INTERACTION_INVENTORY](../07-ux-design/INTERACTION_INVENTORY.md), [COPY_DECK](../07-ux-design/COPY_DECK.md) |

What each request carries, what the server validates before and after it takes its locks, what it writes, what it answers and how the page shows each answer. The command contracts are named by their route (`RC-nn`, ROUTE_CONTRACTS section 6); the other contracts are `IC-nn`. These contracts apply the rules of the domain documents, TECHNICAL_RULES, SCHEMA and SECURITY_MODEL; where a rule text was brought in line in RF-5, the amendment is listed in D-087. They add no error code: every refusal uses one of the 32 codes of TECHNICAL_RULES section 4, with the state of UI_STATES section 3 and the message key of COPY_DECK section 13.1.

Field names are technical identifiers in English camelCase. Values follow ADR-006: a rupiah amount is a string of digits (`"15000"`); a quantity is a decimal string with a full stop and at most the product's precision (`"1.5"`); a date is `YYYY-MM-DD` in Asia/Jakarta; a time is ISO 8601 in UTC. Interface words are those of COPY_DECK; a word that COPY_DECK does not yet name is a proposal for its change control (section 11).

## 1. The common command contract (IC-00)

### 1.1 Request

Every `RC` route accepts one JSON body (multipart for RC-25 and RC-32, whose envelope travels as form fields beside the file):

| Field | Rule |
| --- | --- |
| `operation` | The operation of ROUTE_CONTRACTS section 6; it must equal the operation of the path |
| `version` | `1`, the contract version of every V1 operation |
| `sourceIntentId` | UUID, created once when the draft is frozen at the final action and kept with the frozen draft (ADR-007 section 1) |
| `idempotencyKey` | UUID for one request series; every repeat of the frozen request reuses it |
| `recoveryEpoch` | The epoch the page received in its shared props, sent back unchanged |
| `expected` | The versions the user reviewed, named per command in section 5; an empty object where a command names none |
| `payload` | The business fields of section 5; an unknown or missing field is refused (TR-VAL-01) |

The server derives the actor from the sign-in session, the fingerprint (ADR-007 section 1; for a multipart request the SHA-256 of the file stands for the file) and the received time (TR-TIME-03). It never takes from the client a price applied, a total, a change amount, expected cash, a cost, a business date, a number or a responsible user (TR-VAL-03).

A request passes, in this order:

1. The middleware of ROUTE_CONTRACTS RT-04, ending with the capability gate and, where section 5 says so, recent authentication. A refusal here runs nothing and records no receipt.
2. The **request receipt** (ADR-009 section 1): actor, operation, intent, key, epoch and received time, in a short transaction of its own.
3. The FormRequest: shape, allowlist, formats, limits and object scope (TR-VAL-01 to TR-VAL-07). A refusal here takes no lock and writes no fact.
4. The Action, in one transaction: the command identity (step 1), the epoch (guard G-12), the locks of the command's family in the order of TECHNICAL_RULES section 7, the checks that depend on current state (TR-VAL-08), then the facts, projections, audit event and durable result.
5. The receipt's outcome is marked; security log entries are written after the transaction (SEC-LOG-03); the answer is built from an allowlisted DTO.

### 1.2 Answers

| Case | HTTP | Body |
| --- | --- | --- |
| Committed now | 201 | `{"outcome": "COMMITTED", "operation", "commandId", "repeated": false, "result": {"type", "id", "number", "location"}}`; `number` only for a numbered record; `location` is a path of ROUTE_CONTRACTS |
| Original result of a repeated identity | 200 | The same body with `"repeated": true`, rebuilt from the stored references under the actor's current authorization (ADR-007 section 3) |
| Refused | 401, 403, 404, 409, 422, 429, 503 | The error body of ROUTE_CONTRACTS RT-07; the status per code is that of TECHNICAL_RULES section 4 |
| CSRF check failed | 419 | The framework's answer, read as `CSRF_FAILED` |
| Recent authentication needed | 423 | The framework's password-confirmation answer, read as `RECENT_AUTH_REQUIRED` |

### 1.3 Client states

| State | Entered when | Shown | What the user may do |
| --- | --- | --- | --- |
| SUBMITTING | The final action is pressed; operation, intent, key, payload and expected versions are frozen | UXS-06 on the control; the draft is read-only | Nothing; a second press sends nothing (BR-SALE-18) |
| COMMITTED | 200 or 201 | UXS-04, then the result page at `location` (NC-08) | Leave; Back never resubmits (NC-04) |
| REJECTED | A definite refusal: 403, 404, 409 `STALE_VERSION`, `PRICE_CHANGED`, `RECOUNT_REQUIRED` or `INTENT_ABANDONED`, or 422 | The state of the code (UI_STATES section 3), at the field or line concerned; the draft is editable again (NC-09) | Review; a new confirmation creates a new intent and key (ADR-007 section 2) |
| UNCERTAIN | No answer within the client wait, a network error, 5xx, `SERVICE_BUSY` or an unreadable answer | UXS-14 with the family's panel (COPY_DECK section 14.1) | "Kirim ulang" (the same frozen request) and "Cek status" (IC-01) |
| HELD | 409 `IDEMPOTENCY_CONFLICT` or `EPOCH_STALE` | UXS-15 | "Cek status" only; the request is never repeated (ADR-007 section 5) |
| Not run | 401, 419, 423 or 429 | UXS-09, UXS-10, UXS-11 or UXS-12 | Sign in, reload, confirm the password and send the same frozen request, or wait for `Retry-After` |

The client waits at least 65 seconds — the application limit of 60 seconds plus a margin (TECHNICAL_RULES section 9) — before it reads a missing answer as UNCERTAIN; it may say "Masih diproses" earlier, but time alone never makes a request REJECTED (TR-ERR-01). A status check that gets no answer leaves the request in the state it was in: UXS-14 stays UXS-14, UXS-15 stays UXS-15 with "Cek status" only (D-084; COPY_DECK section 14.2; finding F-05, section 10).

### 1.4 No replacement while an outcome is uncertain

NAVIGATION_CONTRACTS NC-13 blocks a new command of a family while the user has an unresolved request of that family. The client reads the unresolved requests from two sources and applies the block for both:

- the minimal references in this browser (SEC-CH-05), and
- the user's request receipts on the server that are not resolved (RD-04, `scope=own`; ADR-009 section 2), read when a page of the family opens, before a final action is enabled and with the shell refresh of RD-03.

A receipt makes the block act on every device and browser of the user, also after the browser's site data are cleared, for every request that reached the server (N-07 mitigation; D-087). A request refused before its receipt (sections 1.1 step 1) ran nothing and needs no block. The Owner decided N-07 in D-088: the block stays a client rule for every family, and the server refuses nothing because of an unresolved receipt except a close while the responsible user has an unresolved checkout (section 2.5; G-27). The narrow window of a request still on its way to the server when the user starts the same action on another device is an accepted risk; a duplicate that results is corrected by an audited correction — a refund or return of the duplicate Sale, or a reversal of a duplicate receiving or manual issue (DECISIONS section 5). The families and their operations:

| Family (TECHNICAL_RULES section 7.2) | Operations |
| --- | --- |
| Sale checkout | `sale.checkout` |
| Open cash session | `cash_session.open` |
| Close, own or Owner-assisted | `cash_session.close`, `cash_session.close_assisted` |
| Drawer-funded refund or return | `sale_correction.drawer_funded` |
| Owner-funded or BANK_TRANSFER refund or return | `sale_correction.other` |
| Receiving confirmation, manual issue, adjustment | `receiving.confirm`, `manual_issue.confirm`, `adjustment.confirm` |
| Opening stock | `opening_stock.confirm`, `opening_stock.replace`, `onboarding.withdraw_zero_count` (D-087) |
| Reversal | `reversal.confirm` |
| Cost correction or completion | `cost_correction.confirm` |
| Product creation | `product.create` |
| Price change; product edit; product deactivation or reactivation | `product.update`, `product.deactivate`, `product.reactivate` |
| Category create, rename, deactivate or reactivate | `category.create`, `category.rename`, `category.deactivate`, `category.reactivate` |
| Product photo upload, replace or remove | `product_photo.set`, `product_photo.remove` |
| Barcode add, generate, retire | `barcode.add`, `barcode.generate`, `barcode.retire` |
| Barcode misassignment correction | `barcode.correct_misassignment` |
| CSV import confirmation | `csv_import.confirm` |
| Onboarding set or release | `onboarding.start`, `onboarding.release` |
| BusinessProfile change | `business_profile.change` |
| Cash register configuration | `cash_register.create`, `cash_register.update` |
| User management | `user.create`, `user.update`, `owner.update_details`, `user.deactivate`, `user.reactivate`, `user.anonymize`, `user.issue_link`, `user.revoke_link`; `user.deactivate` and `user.revoke_link` stay available unless the unresolved request is itself one of them (NC-13 (d)) |
| Reconciliation reopening | `posting_block.reopen` |
| Owner review note | `review_note.add` |
| Print, reprint or export | `document.print_receipt`, `document.print_session_report`, `document.export_session_report`, `document.print_labels` — exempt from the block |

The Owner's own block uses only the Owner's own requests; other users' requests are listed for resolution (section 2.2) but never block the Owner.

### 1.5 Recent authentication

The capabilities marked in SECURITY_MODEL section 6, and the operations that section 5 marks, need a password confirmation within the last 15 minutes (SEC-SESS-05). When a review opens, the page asks RA-04; if the confirmation has expired, the dialog of UXS-11 opens before the request is sent. A 423 answer means nothing ran; after RA-03 succeeds, the same frozen request is sent with the same intent and key. Operations that need it: `cash_session.close_assisted`, `sale_correction.drawer_funded`, `sale_correction.other`, `adjustment.confirm`, `reversal.confirm`, `onboarding.start`, `onboarding.release`, `onboarding.withdraw_zero_count`, `opening_stock.replace`, `cost_correction.confirm`, `posting_block.reopen`, `barcode.correct_misassignment`, `business_profile.change`, `cash_register.create`, `cash_register.update` and every user-management operation.

### 1.6 Field and line errors

`fields` maps a path of the request (`payload.lines.3.quantity`) to message keys with their parameters, from COPY_DECK section 16 or the code's own key; `lines` maps a line index to safe values: the available stock (`INSUFFICIENT_STOCK`), the current price and revision (`PRICE_CHANGED`), the remaining money and quantity bounds (`REFUND_BOUND_EXCEEDED`, `RETURN_BOUND_EXCEEDED`), the reason of an ineligible opening row, a CSV row error (DC-04). The page keeps every input and shows each message at its field or line (NC-09); a refusal of a final action returns to the review (TR-ERR-05). Nothing outside the role's scope is ever named (TR-ERR-02).

### 1.7 Enter, scanner and double submission

A scanner terminator never confirms a payment, a posting or a close: final and confirm controls never take the Enter of a scan, and scan capture pauses while a dialog, a review or a cash count is open (BR-SALE-13, BR-CAT-32, TR-CMD-05, UXA-10). While a request is SUBMITTING, its draft and its final control are read-only, and a second press, tap or Enter sends nothing. On the server, the identity collapses any duplicate to one execution (RACE-01, RACE-02).

## 2. Resolution and recovery (IC-01)

### 2.1 The status check (RC-50)

Request: `{"operation", "sourceIntentId", "recoveryEpoch"}`; it carries no envelope of its own and takes only the identity step (TECHNICAL_RULES section 7.2). The intent's actor may check it; the Owner may check any user's intent (CAP-34); anyone else gets `NOT_FOUND`.

| Server finds | Answer | Client |
| --- | --- | --- |
| A committed identity for the operation and intent — also for an intent of an earlier epoch whose identity is in the restored data, which is a verified outcome | 200 `COMMITTED` with the result, under the checker's current authorization; a one-time link is never returned again (UI_STATES section 4) | Release the reference; "Lihat hasil"; a Sale's cart is discarded (NC-13 (e)) |
| The original still running | 200 `IN_PROGRESS` | UXS-06; checked again automatically after 3, 6 and 12 seconds, then every 15 seconds while the page is visible, as background requests (RT-10) |
| No identity, current epoch | 200 `NOT_COMMITTED`; a fence is written in its own transaction, with the checker as actor (ADR-007 section 4; RACE-27) | Release the reference; the user may start a new draft |
| No identity, earlier epoch, no reconciliation | 409 `EPOCH_STALE`; a stale-intent report is recorded if absent (ADR-009 section 3) | Stay HELD (UXS-15); the family stays blocked |
| An earlier-epoch intent reconciled as not committed | 200 `NOT_COMMITTED`, from the reconciliation and its fence | Release the reference |
| No answer | — | The state is unchanged (section 1.3) |

A conclusive answer settles the intent's receipts: on COMMITTED or NOT_COMMITTED, every receipt of the same operation and intent that is PENDING or HELD takes that outcome, whether the intent's actor or the Owner checked; the receipt keeps why it was held and records the identity or fence that settled it and the checker (ADR-009 section 1; SCHEMA section 5.3; D-089). An `EPOCH_STALE` answer marks the intent's PENDING receipts HELD; IN_PROGRESS and no answer change nothing.

### 2.2 Unresolved requests (UXP-006)

RP-006 lists the union of the user's references in this browser and the user's unresolved receipts (RD-04, `scope=own`), one row per operation and intent, each with its family label, its time and "Cek status" (UXI-055). A row that only the server knows shows the same family label; its status check sends the operation and intent of the receipt.

For the Owner the page also lists every other user's unresolved receipts (RD-04, `scope=all`), with the user's name, and a "Cek status" that resolves the intent as the Owner (UXI-058, provided by D-087; ADR-007 section 4). An Owner's check that answers NOT_COMMITTED writes the fence; a conclusive answer, COMMITTED or NOT_COMMITTED, settles the other user's receipts of the intent at once (section 2.1), and that user's own block ends at the next read of RD-04, which no longer lists them, or at the next status check, which answers from the identity or the fence. The words of these rows are proposals (section 11).

### 2.3 Requests from before a restore (D-073)

A command or status check whose epoch is not the current one is refused `EPOCH_STALE` before any fact; the refusal records, if absent, a stale-intent report of the operation, intent, actor and epoch (ADR-009 section 3). Under the restore runbook the Owner examines each reported intent against the records that survived and the gap review, and the operator procedure OP-05 records the Owner's outcome: not committed in the restored data, with a fence of that operation and intent in the current epoch. The procedure is audited (section 8) and settles the intent's receipts in the same transaction (section 2.1). Afterwards the status check answers `NOT_COMMITTED` and the reference is released. An intent whose identity is present in the restored data needs no reconciliation: it answers `COMMITTED` (section 2.1). The runbook (RF-6) lets the Owner's own user-management requests be reconciled first, so that NC-13 never stops the re-application of SEC-OP-04 (D-073).

### 2.4 Lost references

A browser reference lost with the browser's data no longer loses the intent: the request receipt holds its operation, intent and key for every request that reached the server, and RP-006 lists it on any device until it is resolved through 2.1. When a user believes an action was sent but neither RP-006 nor the records show it — no Sale in the user's sales, no correction, no movement — the request never reached the server or ended without a fact. The user, for their own action, or the Owner, for any user's action, may then conclude that it did not commit once the record-check wait of TR-TIME-03 has passed since the last moment the request could have been sent (proposed 10 minutes): no request can reach the server after that, and any request that reached it is listed. Until then BR-SALE-20 applies. The conclusion is not a separate record: the receipts are the server's record of what reached it, and the new draft is an ordinary command (ADR-007 section 5; ADR-009 section 4). The words of this rule are a proposal (section 11).

### 2.5 Unresolved checkouts before a close (BR-CASH-16)

- **Own close (RP-105).** "Lanjut hitung" (UXI-103) is disabled while the user has an unresolved `sale.checkout` reference or receipt. A user holds one OPEN session at a time, so such a request belongs to this session or to none.
- **Owner-assisted close (RP-207).** The close preview lists the responsible user's unresolved `sale.checkout` receipts (ADR-009 section 4). "Lanjut hitung" (UXI-150) is disabled while one is listed; the Owner resolves each with "Cek status" as the Owner (CAP-34). A receipt whose original still runs answers IN_PROGRESS until its hard end (TR-TIME-03).
- **Server refusal of the close (D-088).** The Owner decided N-07 with one server refusal: RC-03 and RC-04 are refused `RECOUNT_REQUIRED` while the session's responsible user has a `sale.checkout` receipt whose outcome is PENDING or HELD, an earlier-epoch HELD receipt included; the check is made after the session lock is held (G-27, RACE-35). The session stays OPEN; the page reloads the preview, where the unresolved checkout is listed with "Cek status". A receipt settled by a conclusive status check — the user's own or the Owner's — or by a restore reconciliation no longer counts (section 2.1; D-089). The refusal uses an existing code, so no error code and no copy is added; the close also stays protected by the session lock and the activity version (BR-CASH-13 to BR-CASH-15), so no committed Sale is ever missing from its report.

### 2.6 Cash of a drawer-funded refund

No cash leaves the drawer before the refund is COMMITTED; an uncertain refund is resolved first and is never replaced (BR-RFD-17, RACE-15). The result page RP-204 shows the instruction to take the amount from the named drawer now (D-068).

## 3. Reads

### 3.1 Product Search (IC-02, RD-01)

| Parameter | Rule |
| --- | --- |
| `q` | 0 to 128 characters after trimming; a scanned token keeps its case and punctuation (BR-CAT-17) |
| `mode` | `exact` for a scanned or typed code (exact barcode, then exact SKU, at most one product); `search` otherwise (BR-CAT-24) |
| `context` | `till`, `catalog`, `receiving`, `issue`, `adjustment`, `opening`, `stock`, `correction` or `labels`; it sets which products are selectable and which fields are returned. A context is open only to the capability of the pages that use it — `till` CAP-21, `catalog` CAP-04, `receiving` CAP-11, `issue` CAP-13, `adjustment` CAP-16, `opening` CAP-15, `stock` CAP-14 (stock pages), `correction` CAP-07, `labels` CAP-08 — and any other answers `FORBIDDEN` |
| `category`, `brand`, `status`, `page`, `perPage` | Filters and paging; `status` only for `catalog`; `perPage` at most 100 |

- **Order and paging.** Exact barcode, then SKU exact and prefix, then name without regard to letter case; within each, by name, then internal identifier; 25 per page by default (BR-CAT-24, BR-CAT-25).
- **Superseding.** Each request carries a client sequence number in a header; the page applies only the answer to its newest request, and scans are applied in the order they were made (BR-CAT-30, TR-PAGE-04).
- **The till.** `context=till` needs the actor's own OPEN session, else `NO_OPEN_SESSION` (BR-CAT-33). An exact match of an active product adds one base unit to the cart, combined with that product's line (BR-CAT-29, D-067); no match keeps the code in the field (UXS-33).
- **Fields.** Every role: identity, name, SKU, base unit and precision, current price with its revision, active flag, thumbnail. Cashier: on-hand stock for checkout. Owner and OPERATIONS_ADMIN: on-hand stock, minimum stock and attention state. Never a cost, a valuation or a margin (BR-CAT-26).
- **Opening context** (D-087) adds each product's eligibility: `ELIGIBLE` (no movement and no effective entry), `ZERO_COUNTED` (an effective zero count, with its identifier), `COUNTED` (an effective OPENING), `REPLACEABLE` (every OPENING of the product is reversed and it has no effective entry, with the latest reversed opening line it would replace) or `HAS_MOVEMENT` (another movement exists; not eligible, BR-INV-26).
- **Correction and labels contexts** return active products only.

### 3.2 Lists, filters and paging (IC-03)

Page routes accept only the parameters of ROUTE_CONTRACTS section 2. An invalid value is refused `VALIDATION_FAILED` and shown at its filter (UXS-05) while the earlier results stay. Keyset lists return `nextCursor` and `previousCursor`, opaque values of the boundary rows' sort keys; a cursor is read again under the actor's scope and never grants access (TR-PAGE-03). Filters, sort and page are kept in the encrypted history state when the user returns from a child page (NC-10). A failed count is an error, never zero (TR-PAGE-05).

### 3.3 Live reads (IC-04, RD-02 and RD-03)

The stock list and the shell indicators are read when the page opens, at least every 30 seconds while the page is visible, when the tab regains focus and after any committed command (BR-INV-41). These automatic reads carry the background marker (RT-10). A failed read keeps the last data with its time and UXS-21; it never shows zero. The badge and the list come from one query of committed balances and minimum stock and therefore agree.

### 3.4 Reports (IC-05, RP-501 and RP-502)

- One read-only REPEATABLE READ transaction per report; every figure, the trend and the completeness come from that snapshot, whose time is the as-of time shown (TR-TX-07, BR-COST-38).
- **Rekap harian:** `date` is an Asia/Jakarta date; today by default; a date after today is refused `VALIDATION_FAILED`.
- **Laporan keuangan:** `period` is `today`, `7d`, `this_month`, `last_month` or `custom`, computed on the server in Asia/Jakarta as BR-COST-37 defines; "until now" ends at the snapshot.
- **Custom range** (TECHNICAL_RULES section 11.4): `from` and `to` are required dates; `from` after `to`, `to` after today (Asia/Jakarta, at the request), or a range longer than 366 days is refused `VALIDATION_FAILED` at the date fields; nothing is fetched. A range that ends today is computed until the snapshot.
- A failed query is UXS-22, never zero; figures that depend on an unknown cost show UXS-25 while sales and payment totals still show (BR-COST-38).

### 3.5 Search-first and token lookup (IC-06)

- **RD-06** runs on "Cek" or on the Enter that ends a scanned code in the search-first step of UXP-303: exact barcode and exact SKU, each with its product when in scope, and up to 10 similar names. An exact match shows its product; similar names show UXS-47 and need "Tetap buat produk baru" (BR-CAT-04). The server does not refuse a similar name; it refuses a duplicate SKU or barcode when the product is created (RC-17).
- **RD-05** returns the token's kind, its active product, its retirement records and whether it is correctable: a manufacturer token that is active on a product, or retired as a misassignment (D-051, D-052).

### 3.6 Unresolved request receipts (IC-07, RD-04)

Each row: operation, family, intent, key, epoch marker (current or earlier), received time, outcome (PENDING or HELD) and, with `scope=all`, the user's identifier and display name. Never a payload, an amount or a product (ADR-009 section 2). Ordered by received time; at most 100 rows, with a cursor. `scope=all` is the Owner's only.

### 3.7 Media (IC-08, RD-07)

A stored image is served only to a signed-in user whose role may see it, through the application, never from a storage path (SEC-UP-06). Its key never changes, so the answer may be cached privately by the browser (`Cache-Control: private, max-age=86400, immutable`); a removed photo is no longer referenced by any page, while its file is kept (D-042).

## 4. Authentication and sign-in session (IC-09 to IC-14)

| ID | Route | Contract |
| --- | --- | --- |
| IC-09 | RA-01 | Email and password; the account must be ACTIVE; the session is regenerated; the answer never tells which part failed (UXS-42, TR-ERR-04); after success a full page load to RN-01, so no page of an earlier user is shown (SEC-CH-04) |
| IC-10 | RA-02 | If the user has unresolved references or receipts, the sign-out warning opens first (UXI-026): "Lihat permintaan" goes to RP-006, "Tetap keluar" signs out and keeps the references (SEC-CH-05). Sign-out never changes a cash session (BR-CASH-11) |
| IC-11 | RA-03, RA-04 | The password is checked against the signed-in account; success starts 15 minutes of recent authentication; failure is UXS-42 and is throttled (bucket `confirm`) |
| IC-12 | RA-05 | Current password, new password and its repetition, 8 to 128 characters; the user's other sign-in sessions end (SEC-AUTH-11); audit `PASSWORD_CHANGE` |
| IC-13 | RA-06 | The page reads the account and token from the fragment, removes the fragment, and posts them with the new password; success leads to RP-001 with a success message; every failure is the one `LINK_INVALID` answer (UXS-41); audit `LINK_CONSUME` |
| IC-14 | RA-07 | Sent at most once a minute while the user types, and by "Tetap masuk" (UXI-034); it counts as user activity (SEC-SESS-02) |

## 5. Command contracts

Each contract lists the payload, the expected versions, what is refused before any lock (the FormRequest), what is checked under the locks, what commits, the result and the further refusals (the common ones are in ROUTE_CONTRACTS section 6). "Owner" means the SUPER_ADMIN account.

### RC-01 `sale.checkout`

| Part | Contract |
| --- | --- |
| Payload | `sessionId`; `lines[]`: `productId`, `quantity`; `payment`: `method` (`CASH` or `BANK_TRANSFER`), `tendered` (CASH only, required), `reference` (BANK_TRANSFER only, optional, up to 100), `customerName` (optional, up to 100) |
| Expected | `lines[].priceRevisionId`: the price revision the cashier reviewed |
| Before locks | 1 to 200 lines; each `productId` at most once, else `VALIDATION_FAILED` at the repeated line (D-067); quantities above 0 and within range; a method outside the list `METHOD_NOT_ALLOWED` (BR-PAY-01) |
| Under locks | Family Sale checkout. Actor active with CAP-21; onboarding off (`ONBOARDING_FREEZE`); a BusinessProfile revision exists (G-23); the session is OPEN and the actor's own (`SESSION_NOT_OPEN`; `NO_OPEN_SESSION` when the actor has no OPEN session at all); each product active (`PRODUCT_INACTIVE`), not blocked (`POSTING_BLOCKED`), quantity within its precision, current price revision equal to the expected one (`PRICE_CHANGED`, with each changed line's new price in `lines`); stock enough for each product (`INSUFFICIENT_STOCK`, with the available stock in `lines`); line totals rounded half-up once, the total their sum (BR-SALE-06); CASH tendered at least the total (`TENDER_INSUFFICIENT`) |
| Commits | The number (TR-TX-10), Sale, one line per product, Payment (CASH: tendered, change, applied = total; BANK_TRANSFER: applied = total), SALE_ISSUE movements and balances, cost allocations, the ReceiptSnapshot from the BusinessProfile revision in force, the session's activity version + 1, audit `SALE_CHECKOUT`, result (BR-SALE-17) |
| Result | `sale`, its number, `location` RP-103; the cart is discarded |
| Tests | One line per product and the refusal of a repeated product (D-067); 200 distinct products and the refusal of 201; RACE-01 to RACE-04, RACE-08, RACE-23 to RACE-25 |

### RC-02 `cash_session.open`

| Part | Contract |
| --- | --- |
| Payload | `registerId`; `openingCash` (required; `"0"` valid, BR-CASH-04) |
| Expected | None |
| Under locks | Family Open cash session. Register active and without an OPEN session, and the actor without an OPEN session (`VALIDATION_FAILED` at `registerId`, or at the form when the actor already has one; BR-CASH-05, BR-CASH-06); a BusinessProfile revision exists (G-23) |
| Commits | Session number, the OPEN session with its opening cash, audit `CASH_SESSION_OPEN`, result |
| Result | `cash_session`, its number, `location` RP-101 |
| Tests | RACE-07; RACE-26; opening with Rp0; a blank amount refused |

### RC-03 `cash_session.close` and RC-04 `cash_session.close_assisted`

| Part | Contract |
| --- | --- |
| Payload | `sessionId`; `physicalCash` (required, zero or more); RC-04 also `reason` (1 to 1,000) |
| Expected | `activityVersion` from the close preview |
| Gate | RC-03: the session's responsible user (CAP-21). RC-04: the Owner (CAP-23), recent authentication, and the responsible user is not the Owner; the responsible user may be deactivated (D-069 (d), D-087) |
| Under locks | Family Close. Session OPEN (`SESSION_NOT_OPEN`); activity version equal (`RECOUNT_REQUIRED`; BR-CASH-14); no `sale.checkout` request receipt of the responsible user with outcome PENDING or HELD, read after the session lock (`RECOUNT_REQUIRED`; G-27, D-088); totals and expected cash derived from the session's facts under its lock (BR-CASH-13, G-05) |
| Commits | Close record (physical cash, expected cash, variance, cutoff, closed time, close actor), the CashSessionReportSnapshot (BR-CASH-20) with its drawer-funded refunds, CLOSED status, audit `CASH_SESSION_CLOSE` or `CASH_SESSION_CLOSE_ASSISTED`, result; the responsible user never changes (BR-CASH-18) |
| Result | `cash_session_close`, the session number, `location` RP-206 |
| Client | BR-CASH-16 as section 2.5; on `RECOUNT_REQUIRED` the page reloads the close preview, which shows the new totals or the unresolved checkout with "Cek status"; the close preview lists the drawer-funded refunds with the reminder of D-068 |
| Tests | RACE-08, RACE-09, RACE-13 including the D-068 scenario (CRITICAL_FLOWS WF-09), RACE-28 with an Owner-assisted close of a deactivated user's session, RACE-35 with the settlement of receipts (D-088, D-089) |

### RC-05 `sale_correction.drawer_funded` and RC-06 `sale_correction.other`

| Part | Contract |
| --- | --- |
| Payload | `saleId`; `lines[]`: `saleLineId`, `refundAmount` (zero or more), `returnedSaleable` and `returnedNotSaleable` (quantities, zero or more; the conditions `SALEABLE` and `NOT_SALEABLE` of SCHEMA section 5.9); `reason` (1 to 1,000). RC-05: `payout`: `sessionId`. RC-06: `payout`, absent for a return without money, else `method` (`CASH` from the Owner's own funds, or `BANK_TRANSFER`) and `reference` (1 to 100, the payout actually made, BR-RFD-05) |
| Expected | None: the bounds and the drawer's expected cash are checked under the locks |
| Before locks | At least one line with an amount or a quantity; each `saleLineId` at most once and belonging to the Sale; RC-05 needs a refund amount above zero |
| Under locks | RC-05: family Drawer-funded refund — the selected session OPEN (`SESSION_NOT_OPEN`), the refund at most its expected cash (`DRAWER_CASH_INSUFFICIENT`, BR-RFD-15); RC-06: family Owner-funded or BANK_TRANSFER. Both: cumulative bounds of the Sale and each line (`REFUND_BOUND_EXCEEDED`, `RETURN_BOUND_EXCEEDED`, with the remaining bounds in `lines`; BR-RFD-03); a saleable return needs onboarding off (`ONBOARDING_FREEZE`) and its product not blocked (`POSTING_BLOCKED`); a returned product must be active to receive saleable stock (`PRODUCT_INACTIVE`) |
| Commits | The correction, refund and return lines, the payout — for RC-05 with the session and a reference generated as `<Sale number>/<k>`, where k is the ordinal of the correction on that Sale under the Sale lock (D-087) — RETURN_RECEIPT for saleable goods with the cost of the original issue (BR-COST-17), the session's activity version + 1 (RC-05), running totals, audit `SALE_CORRECTION`, result |
| Result | `sale_correction`, `location` RP-204; for RC-05 the amount, register and session to take the cash from (D-068) |
| Tests | RACE-10, RACE-13, RACE-14, RACE-15; a refund on a CLOSED session refused; the late handover of D-068 creates no fact (WF-10) |

### RC-07 `receiving.confirm`, RC-08 `manual_issue.confirm` and RC-09 `adjustment.confirm`

| Part | Contract |
| --- | --- |
| Payload | RC-07: `reference` (optional, up to 100), `documentDate` (optional), `lines[]` (1 to 500): `productId`, `quantity`, `cost`. RC-08: `reasonCode` (`DAMAGED`, `INTERNAL_USE`, `LOST`, `SAMPLE`, `OTHER`), `note` (optional, up to 1,000), `lines[]` (1 to 200): `productId`, `quantity`. RC-09: `reason` (1 to 1,000), `countReference` and `reference` (1 to 100 each), `lines[]` (1 to 200): `productId`, `direction` (`INCREASE` or `DECREASE`), `quantity`, `cost` for an increase |
| `cost` | `status` `KNOWN` or `UNKNOWN`; `amount` (the line's total, required when KNOWN); `zeroEvidence` (1 to 1,000, required when the amount is zero; BR-COST-04). UNKNOWN is never zero (BR-COST-03) |
| Expected | `products`: the version of each product as reviewed, so that a unit or precision changed before the product's first movement is never posted unseen |
| Under locks | Family Receiving confirmation, manual issue, adjustment. Onboarding off (`ONBOARDING_FREEZE`); products active (`PRODUCT_INACTIVE`), not blocked (`POSTING_BLOCKED`), versions equal (`STALE_VERSION`, with the products in `lines`), quantities within precision; for issues and decreases, stock enough for the lines of each product added together (`INSUFFICIENT_STOCK`; BR-INV-07, BR-INV-20) |
| Commits | The document, its movements, cost evidence or allocations, balances, valuation, audit `RECEIVING_CONFIRM`, `MANUAL_ISSUE_CONFIRM` or `ADJUSTMENT_CONFIRM`, result |
| Result | RC-07 `location` RP-406; RC-08 RP-409; RC-09 RP-402 filtered to its products |
| Tests | RACE-03, RACE-24; no fact before confirmation and no draft route (D-069 (a)); OPERATIONS_ADMIN sees the receiving cost it may see (D-033, D-047) |

### RC-10 `reversal.confirm`

| Part | Contract |
| --- | --- |
| Payload | `movementId`; `reason` (1 to 1,000). The Owner never enters a quantity (BR-INV-34) |
| Expected | None: "not yet reversed" is checked under the locks |
| Under locks | Family Reversal. The source is an OPENING, RECEIPT or MANUAL_ISSUE not yet reversed (`STALE_VERSION` when it was reversed meanwhile; BR-INV-35); a RECEIPT or MANUAL_ISSUE needs onboarding off (`ONBOARDING_FREEZE`), an OPENING may also be reversed while onboarding is active (D-087); product not blocked (`POSTING_BLOCKED`); stock stays non-negative (`INSUFFICIENT_STOCK`); the history stays valid and the replay is safe (`REPLAY_REJECTED`; G-07) |
| Commits | The reversal and its REVERSAL movement, balance, the replayed valuation, the clearance of the source's active cost marks, audit `REVERSAL_CONFIRM`, result |
| Result | `location` RP-403 of the REVERSAL; for a reversed OPENING during onboarding, RP-412 offers its replacement (RC-48) |
| Tests | RACE-10; INV03; the reversal of an OPENING during onboarding and its replacement (WF-25) |

### RC-11 `opening_stock.confirm`

| Part | Contract |
| --- | --- |
| Payload | `rows[]` (1 to 100): `productId`, `quantity` (`"0"` for a zero count), `countReference` (1 to 100, required for a positive row; optional for a zero row), `cost` (positive rows only, as RC-07) |
| Expected | `onboardingVersion`; `products`: versions as reviewed |
| Before locks | Each product at most once; at most 100 rows (BR-INV-25) |
| Under locks | Family Opening stock. Onboarding ACTIVE, never released and not held for a restore reconciliation (`STALE_VERSION`; D-071, D-072); each product active (`PRODUCT_INACTIVE`), not blocked (`POSTING_BLOCKED`), version equal (`STALE_VERSION`); no effective onboarding entry for the product (G-22; `VALIDATION_FAILED` with `inventory.opening_entry.already_zero` or `inventory.opening_entry.not_eligible`); a positive row only for a product with no movement at all (BR-INV-26; `inventory.opening_entry.not_eligible`), so a product whose OPENING was reversed is recorded again only by RC-48 |
| Commits | The entry; for each positive row a line and an OPENING at sequence 1 with its cost evidence, balance and valuation; for each zero row a zero count (BR-INV-27); audit `OPENING_STOCK_CONFIRM`; result. All rows or none |
| Result | `location` RP-412 |
| Tests | RACE-06 as amended; the 101st row; zero count then withdrawal then a positive row (WF-25) |

### RC-12 `onboarding.start` and RC-13 `onboarding.release`

| Part | Contract |
| --- | --- |
| Payload | None |
| Expected | `onboardingVersion` |
| Under locks | Family Onboarding set or release; the Owner with recent authentication. RC-12: the state is NOT_STARTED — never started, never released, not held (`STALE_VERSION` otherwise; D-071, D-072). RC-13: the state is ACTIVE and not held (`STALE_VERSION`) |
| Commits | RC-12: the state ACTIVE and its version + 1, audit `ONBOARDING_START` (the cutover is its time and actor). RC-13: the durable release record, the state RELEASED and its version + 1, audit `ONBOARDING_RELEASE`; nothing can start it again (D-071) |
| Result | `location` RP-412 |
| Tests | Start after release refused, also concurrently and after a restore of a backup taken before the release (D-071, D-072); release twice refused |

### RC-14 `onboarding.withdraw_zero_count` (D-087)

| Part | Contract |
| --- | --- |
| Payload | `zeroCountId`; `reason` (1 to 1,000) |
| Expected | `onboardingVersion` |
| Gate | The Owner (CAP-15) with recent authentication |
| Under locks | Family Opening stock (onboarding share lock, then the product). Onboarding ACTIVE and not held (`STALE_VERSION`); the zero count exists and is not withdrawn (`STALE_VERSION`) |
| Commits | A withdrawal record linked to the zero count — the zero count itself stays (append-only) — audit `ZERO_COUNT_WITHDRAW`, result. The product is "not counted" again and may be counted by RC-11 |
| Result | `location` RP-412 |
| Tests | Withdrawal twice refused; withdrawal after release refused; withdrawal racing an entry of the same product (G-22) |

### RC-48 `opening_stock.replace` (D-087)

| Part | Contract |
| --- | --- |
| Payload | `replacesLineId` (the opening line whose OPENING was reversed); `quantity` (above zero); `countReference` (1 to 100); `cost` (as RC-07); `reason` (1 to 1,000). One product per command: the replacement is explicit |
| Expected | `onboardingVersion`; `productVersion` |
| Gate | The Owner (CAP-15) with recent authentication |
| Under locks | Family Opening stock. Onboarding ACTIVE and not held (`STALE_VERSION`); the replaced line's OPENING is reversed and the line has not been replaced (`STALE_VERSION`); the product has no effective onboarding entry and only OPENING and REVERSAL movements (G-22, G-24; `VALIDATION_FAILED` with `inventory.opening_entry.not_eligible`); product active, not blocked, version equal |
| Commits | An entry of one row whose line names the line it replaces, the replacement OPENING at the product's next sequence with its cost evidence, balance and valuation, audit `OPENING_STOCK_REPLACE`, result. No earlier fact is changed |
| Result | `location` RP-412 |
| Tests | A second replacement of the same line refused; a replacement racing another (one OPENING effective per product); refused after release and while held |

### RC-15 `cost_correction.confirm`

| Part | Contract |
| --- | --- |
| Payload | `evidenceId`; `newCost`: `amount` (KNOWN) and `zeroEvidence` when the amount is zero; `reason` (1 to 1,000) |
| Expected | `correctionNo`: the number of the evidence's latest correction as reviewed (0 for none) |
| Under locks | Family Cost correction or completion; the Owner with recent authentication. Correction number equal (`STALE_VERSION`); product not blocked (`POSTING_BLOCKED`); bounded replay safe (`REPLAY_REJECTED`; BR-COST-23 to BR-COST-27, TR-TIME-01) |
| Commits | The correction with old and new values, the published replay, the clearance of the source's marks, audit `COST_CORRECTION`, result; on `REPLAY_REJECTED`, the incomplete mark and its audit in their own transaction (SCHEMA section 5.8) |
| Result | `location` the page that opened it (`from_page`), else RP-505 |
| Tests | RACE-11; G-15; G-21 |

### RC-16 `posting_block.reopen`

Payload `blockId`, `reason` (optional, up to 1,000); expected none. Family Reconciliation reopening; the Owner with recent authentication. The block exists without a reopening (`STALE_VERSION`). Commits the reopening, audit `POSTING_REOPEN`, result; `location` RP-415. Tests: RACE-24 with a reopening.

### RC-17 `product.create`

| Part | Contract |
| --- | --- |
| Payload | `sku` (1 to 64), `name` (1 to 200), `categoryId`, `brand` (optional, up to 100), `baseUnit` (1 to 32), `quantityPrecision` (0 to 3), `sellingPrice`, `minimumStock` (within the precision), `barcode` (optional): `token`, `symbology` (optional: `EAN_13`, `EAN_8`, `UPC_A`, `UPC_E`) |
| Expected | None |
| Under locks | Family Product creation. Category active (`CATEGORY_INACTIVE`); SKU unique without regard to letter case (`DUPLICATE_SKU`); a barcode passes the checks of RC-27 |
| Commits | The product with stock 0 and no movement (BR-CAT-03), its first price revision, the barcode token and mapping, audit `PRODUCT_CREATE`, result |
| Result | `location` RP-302; when opened over RP-405 or RP-413, the working page with the new product chosen (NC-11) |
| Tests | RACE-22, RACE-29; a unique SKU racing |

### RC-18 `product.update`, RC-19 `product.deactivate`, RC-20 `product.reactivate`

RC-18 payload `productId` and the fields of RC-17 except `sku` and `barcode`; expected `productVersion`. Under the product lock: version equal (`STALE_VERSION`); unit and precision unchanged after the first movement (`VALIDATION_FAILED` at the field; BR-CAT-07); a newly chosen category active (`CATEGORY_INACTIVE`); a changed price appends a revision (BR-CAT-13). Commits the change, audit `PRODUCT_EDIT` and, for a price, `PRICE_CHANGE`. RC-19 and RC-20: payload `productId`, expected `productVersion`; audit `PRODUCT_DEACTIVATE` or `PRODUCT_REACTIVATE`; history and documents keep showing the product (BR-CAT-08). Result `location` RP-302. Tests: RACE-05, RACE-23.

### RC-21 to RC-24 categories

RC-21 `category.create` payload `name` (1 to 100); names are not unique (SCHEMA section 5.6). RC-22 `category.rename` payload `categoryId`, `name`; RC-23 `category.deactivate` and RC-24 `category.reactivate` payload `categoryId`; each of RC-22 to RC-24 expects `categoryVersion` (`STALE_VERSION`). A deactivation changes no product (BR-CAT-09). Audit `CATEGORY_CREATE`, `CATEGORY_RENAME`, `CATEGORY_DEACTIVATE`, `CATEGORY_REACTIVATE`. Result: RP-307 in place. Tests: RACE-29.

### RC-25 `product_photo.set` and RC-26 `product_photo.remove`

RC-25 is multipart: the envelope fields, `productId` and `photo` (JPEG, PNG or WebP, at most 8 MiB and 24 megapixels, SECURITY_MODEL section 13); the file is validated, normalized and stored under a new random key before the transaction (TR-TX-05), and its checksum enters the fingerprint. RC-26 payload `productId`. Both expect `productVersion` (`STALE_VERSION`). A replaced or removed file is kept (D-042). Audit `PHOTO_CHANGE` or `PHOTO_REMOVE`. Result: RP-302 in place.

### RC-27 to RC-29 barcodes

| Operation | Payload | Under locks | Refusals |
| --- | --- | --- | --- |
| RC-27 `barcode.add` | `productId`, `token`, `symbology` (optional) | The token was never mapped to any product, active or retired (BR-CAT-19); not in the `BK-` namespace (BR-CAT-18); a declared EAN or UPC has a valid check digit (G-09) | `DUPLICATE_BARCODE` (active on a product); `BARCODE_NOT_ELIGIBLE` with `params.reason` `retired`, `reserved` or `check_digit` (COPY_DECK section 13.2) |
| RC-28 `barcode.generate` | `productId` | A new `BK-P-` code, retried on a clash (BR-CAT-18) | — |
| RC-29 `barcode.retire` | `mappingId`, `reasonClass` (`MISASSIGNMENT` or `OTHER`, as the dialog's reason choice records it; BR-CAT-52) | The mapping is active (`STALE_VERSION`) | — |

Audit `BARCODE_ADD`, `BARCODE_GENERATE`, `BARCODE_RETIRE`. Result: RP-302 in place. Tests: RACE-17, RACE-19.

### RC-30 `barcode.correct_misassignment`

Payload `token`, `targetProductId`, `reason` (1 to 1,000); expected `mapping`: the product the token was on and whether it was active or retired as a misassignment, as RD-05 showed it. The Owner with recent authentication; both products locked in ascending order. The token is a manufacturer token that is active on another product or retired as a misassignment (`BARCODE_NOT_ELIGIBLE`, `params.reason` `not_correctable`); the mapping is as expected (`STALE_VERSION`); at most one active mapping after commit (`DUPLICATE_BARCODE`). Commits the new mapping and, for a move, the retirement of the old one as a misassignment; audit `BARCODE_CORRECTION`. Result `location` RP-302 of the correct product. Tests: RACE-17 to RACE-19.

### RC-31 `csv_import.confirm`

Payload `importId` (from RR-01) and `fileSha256`; expected none. The file is read again from the temporary area and validated in full against DC-04; a missing or expired file, or a checksum that differs, is `CSV_INVALID` with `catalog.csv_import.error.file_changed` (SEC-UP-04). Categories named by the rows are matched again (DC-04), then locked (share, ascending): a category deactivated after matching is `CATEGORY_INACTIVE`; a row that no longer matches exactly one active category, or any other row error, is `CSV_INVALID` with the row errors in `lines`; SKU and barcode conflicts are `DUPLICATE_SKU` or `DUPLICATE_BARCODE` (RACE-22). All rows or none (BR-CAT-38), within 30 seconds (TR-TIME-02). Commits the products with stock 0, their first price revisions and barcodes, the import record, audit `CSV_IMPORT` with the row summary and checksum (D-054), result; the temporary file is then removed. Result `location` RP-301 filtered to the created products. Tests: D-069 (h): a missing, an ambiguous and an inactive category name, and a category changed between preview and confirmation.

### RC-32 `business_profile.change`

Multipart: the envelope fields, `shopName` (1 to 100), `address` (up to 500), `contact` (up to 200), `email` (optional, up to 254), `documentHeader` and `documentFooter` (up to 500 each), `documentIdentity` (up to 100, printed under the shop name; DC-01), `accentToken` (`green`, `teal`, `blue`, `purple` or `graphite`; DESIGN_SYSTEM section 3.3; anything else `VALIDATION_FAILED`), `logo` (an optional file: PNG, JPEG or WebP, at most 5 MiB and 16 megapixels) and `logoAction` (`keep`, `replace` or `remove`). Expected `revisionId`: the revision in force as reviewed (`STALE_VERSION`). The Owner with recent authentication. Commits a new revision and moves the current pointer; a referenced logo file is never deleted (BR-CFG-03); audit `PROFILE_CHANGE`. Result `location` RP-607. Tests: RACE-25.

### RC-33 `cash_register.create` and RC-34 `cash_register.update`

Payload `code` (1 to 32), `label` (1 to 100), `active`; RC-34 also `registerId` and expected `registerVersion` (`STALE_VERSION`). The Owner with recent authentication. Under the register lock: no OPEN session on it (`VALIDATION_FAILED` with the reason of UXS-13; G-19). Audit `REGISTER_CREATE` or `REGISTER_CHANGE`. Result `location` RP-609. Tests: RACE-26.

### RC-35 to RC-42 user management

| Operation | Payload | Under the target lock | Result |
| --- | --- | --- | --- |
| RC-35 `user.create` | `displayName` (1 to 100), `email` (up to 254), `roles`: `cashier`, `operationsAdmin` (at least one) | Email unused; never SUPER_ADMIN | The account, and its FIRST_PASSWORD link in this answer only: `link.url` with the token in the fragment, `link.expiresAt` (SEC-ACC-05); `location` RP-606 |
| RC-36 `user.update` | `userId`, `displayName`, `email`, `roles` | A staff account, not anonymized; version equal; a role change or an email change revokes sessions or link in the same transaction (SEC-ACC-04) | RP-604 |
| RC-37 `owner.update_details` | `displayName`, `email` | The Owner's own account, locked once (TR-LOCK-05) | RP-003 |
| RC-38 `user.deactivate` | `userId` | A staff account that is ACTIVE; allowed while it holds an OPEN cash session, which stays OPEN (D-069 (d), RACE-28); sessions and links revoked | RP-604 |
| RC-39 `user.reactivate` | `userId` | DEACTIVATED, not anonymized, not SUPER_ADMIN (BR-USR-10) | RP-604 |
| RC-40 `user.anonymize` | `userId`, `reason` (1 to 1,000) | DEACTIVATED staff account, or a former Owner deactivated by OP-03 (D-043, D-048); display name becomes `access.user.anonymized_name` with `:nomor` = the account's internal identifier in decimal; email becomes `anonymized-<identifier>@users.invalid` (SEC-ACC-09) | RP-604 |
| RC-41 `user.issue_link` | `userId`, `kind` (`FIRST_PASSWORD` or `RESET`) | A staff account that is ACTIVE; one new link per account per 60 seconds; an earlier link is replaced | The link once, as RC-35; RP-606 |
| RC-42 `user.revoke_link` | `userId` | A link exists (`STALE_VERSION` otherwise) | RP-604 |

Every operation except RC-35 expects `userVersion` (`STALE_VERSION`); the Owner with recent authentication; never creates, changes, deactivates or anonymizes a SUPER_ADMIN account through these routes (CAP-33, D-048). Audit: `USER_CREATE` with `LINK_ISSUE`, `ROLE_CHANGE`, `DETAILS_CHANGE`, `USER_DEACTIVATE`, `USER_REACTIVATE`, `USER_ANONYMIZE`, `LINK_ISSUE`, `LINK_REVOKE`. A committed link that the page lost is never shown again: its status check answers COMMITTED with `commands.resolution.committed_link_hidden`. Tests: RACE-16, RACE-20, RACE-21, RACE-28.

### RC-43 `review_note.add`

Payload `sessionId`, `text` (1 to 2,000, line breaks allowed); expected none. The Owner (CAP-22). Under the session's share lock: the session is CLOSED (`STALE_VERSION` otherwise). Commits the note; count, variance and report unchanged; no edit and no delete (D-069 (f)); audit `REVIEW_NOTE_ADD`. Result: RP-206 in place. Tests: a second note accepted and nothing else changed.

### RC-44 to RC-47 print and export requests

| Operation | Payload | Checks | Result |
| --- | --- | --- | --- |
| RC-44 `document.print_receipt` | `saleId`, `size` (`58`, `80`, `A4`) | Sale within scope (CAP-21 own, CAP-22) | `printRequestId`, `copy` (true for every print request of the Sale after its first, DC-01), `location` RP-103P |
| RC-45 `document.print_session_report` | `sessionId` | Session CLOSED and within scope | `location` RP-206P |
| RC-46 `document.export_session_report` | `sessionId` | As RC-45 | The CSV of DC-03 as the body, `Content-Disposition: attachment` |
| RC-47 `document.print_labels` | `productId`, `mappingId`, `count` (1 to 100) | Mapping active on the product (`STALE_VERSION`); CAP-08 | `location` RP-306P |

Each writes only its audit event (`PRINT` or `EXPORT`; BR-AUD-06) and its result; no business fact. A failed print or download is UXS-44 and is simply requested again: these requests are exempt from NC-13 and have no uncertain panel (COPY_DECK section 14.1). A repeat with the same identity returns the same print request, so the copy marker does not change on a retry.

## 6. Requests that are not commands

| ID | Contract |
| --- | --- |
| RR-01 | `multipart/form-data` with `file`. The file is judged by its content (UTF-8 text, at most 2 MiB, at most 5,000 data rows and one header row); a file-level failure is `CSV_INVALID` with its message key (`ux.validation.upload.csv_type`, `ux.validation.upload.too_large`, `ux.validation.upload.csv_rows`, `catalog.csv_import.error.missing_column` or `catalog.csv_import.error.unknown_column`). Otherwise the answer is 200 with `importId` (random, bound to the actor), `fileSha256`, `dataRowCount`, the summary counts and every row's errors (DC-04); a file with row errors is previewed, and its confirmation is refused until a corrected file is previewed. No business fact is written (D-054) |
| RR-02 | `importId`; removes the temporary file; 204, also when the import is missing or expired. Only the actor who previewed it may cancel it |

## 7. Scheduled command (SC-01)

`posting_block.block` uses the envelope with the process actor `reconciliation` and a source intent derived from the run and the product, so a repeated run blocks once (SCHEMA section 5.3). Under the product guard it writes the block with its detail code and audit `RECONCILIATION_BLOCK`. A posting that took the guard first is seen by the next comparison (RACE-24).

## 8. Audit action codes and security outcome codes

The `action` of an audit event (SCHEMA section 5.4) and its label in Riwayat aktivitas (COPY_DECK section 11.14):

| Action code | Written by | Label key |
| --- | --- | --- |
| `SALE_CHECKOUT` | RC-01 | `audit.action.sale` |
| `CASH_SESSION_OPEN`; `CASH_SESSION_CLOSE`; `CASH_SESSION_CLOSE_ASSISTED` | RC-02; RC-03; RC-04 | `audit.action.open_session`; `audit.action.close_session`; `audit.action.assisted_close` |
| `SALE_CORRECTION` | RC-05, RC-06 | `audit.action.refund` |
| `RECEIVING_CONFIRM`; `MANUAL_ISSUE_CONFIRM`; `ADJUSTMENT_CONFIRM` | RC-07; RC-08; RC-09 | `audit.action.receipt`; `audit.action.manual_issue`; `audit.action.adjustment` |
| `REVERSAL_CONFIRM` | RC-10 | `audit.action.reversal` |
| `OPENING_STOCK_CONFIRM` | RC-11 | `audit.action.opening_stock` |
| `OPENING_STOCK_REPLACE` | RC-48 | Proposed (section 11) |
| `ZERO_COUNT_WITHDRAW` | RC-14 | Proposed (section 11) |
| `ONBOARDING_START`; `ONBOARDING_RELEASE` | RC-12; RC-13 | `audit.action.onboarding_start`; `audit.action.onboarding_release` |
| `COST_CORRECTION`; `COST_INCOMPLETE_MARK` | RC-15 and the cost recovery OP-06 (D-088); the mark of BR-COST-27 | `audit.action.cost_correction`; `audit.action.cost_incomplete_mark` |
| `RECONCILIATION_BLOCK`; `POSTING_REOPEN` | SC-01; RC-16 | `audit.action.reconciliation_block`; `audit.action.reopen` |
| `PRODUCT_CREATE`; `PRODUCT_EDIT`; `PRICE_CHANGE`; `PRODUCT_DEACTIVATE`; `PRODUCT_REACTIVATE` | RC-17 to RC-20 | `audit.action.product_create`; `audit.action.product_edit`; `audit.action.price_change`; `audit.action.product_deactivate`; `audit.action.product_reactivate` |
| `CATEGORY_CREATE`; `CATEGORY_RENAME`; `CATEGORY_DEACTIVATE`; `CATEGORY_REACTIVATE` | RC-21 to RC-24 | `audit.action.category_create`; `audit.action.category_rename`; `audit.action.category_deactivate`; `audit.action.category_reactivate` |
| `PHOTO_CHANGE`; `PHOTO_REMOVE` | RC-25; RC-26 | `audit.action.photo_change`; `audit.action.photo_remove` |
| `BARCODE_ADD`; `BARCODE_GENERATE`; `BARCODE_RETIRE`; `BARCODE_CORRECTION` | RC-27 to RC-30 | `audit.action.barcode_add`; `audit.action.barcode_generate`; `audit.action.barcode_retire`; `audit.action.barcode_correction` |
| `CSV_IMPORT` | RC-31 | `audit.action.csv_import` |
| `PROFILE_CHANGE` | RC-32; OP-01 for the first revision | `audit.action.profile_change` |
| `REGISTER_CREATE`; `REGISTER_CHANGE` | RC-33; RC-34 | `audit.action.register_create`; `audit.action.register_change` |
| `USER_CREATE`; `ROLE_CHANGE`; `DETAILS_CHANGE` | RC-35, OP-01, OP-03; RC-36; RC-36, RC-37 | `audit.action.user_create`; `audit.action.role_change`; `audit.action.details_change` |
| `USER_DEACTIVATE`; `USER_REACTIVATE`; `USER_ANONYMIZE` | RC-38, OP-03; RC-39; RC-40 | `audit.action.user_deactivate`; `audit.action.user_reactivate`; `audit.action.user_anonymize` |
| `LINK_ISSUE`; `LINK_REVOKE`; `LINK_CONSUME` | RC-35, RC-41, OP-01 to OP-03; RC-42; RA-06 | `audit.action.link_issue`; `audit.action.link_revoke`; `audit.action.link_consume` |
| `PASSWORD_CHANGE` | RA-05 | `audit.action.password_change` |
| `OWNER_RECOVERY`; `OWNER_REPLACEMENT` | OP-02; OP-03 | `audit.action.owner_recovery`; `audit.action.owner_replacement` |
| `REVIEW_NOTE_ADD` | RC-43 | `audit.action.review_note` |
| `PRINT`; `EXPORT` | RC-44, RC-45, RC-47; RC-46 | `audit.action.print`; `audit.action.export` |
| `ONBOARDING_RESTORE_HOLD` | OP-04 (D-072) | Proposed (section 11) |
| `RESTORE_RECONCILIATION` | OP-05 (D-072, D-073) | Proposed (section 11) |
| `PROJECTION_REBUILT` | OP-07 (D-088) | Proposed (section 11) |

The category of each event, `BUSINESS` or `SECURITY`, follows SEC-LOG-02. The security log writes `outcome_code` `SUCCEEDED` or `REFUSED` only, labelled by `audit.security.outcome.succeeded` and `audit.security.outcome.refused`; its event type carries the kind of failure (UXI-383). A further outcome code would need its label through change control first.

## 9. Route of every control

Every row of INTERACTION_INVENTORY with the route that serves it, answering its "Route: RF-5" column (plan decision P-11). "Local" sends no request; "Opener" is the route of the page that opened it (NC-01). Routes are those of ROUTE_CONTRACTS; a control that reads a page route with parameters uses an Inertia partial reload of that route.

| UXI | Page | Kind | Route | Notes |
| --- | --- | --- | --- | --- |
| UXI-001 | UXP-000 | Navigate | RP-007 | — |
| UXI-002 | UXP-000 | Navigate | RP-101, or RP-104 | RP-101 redirects to RP-104 without an OPEN session |
| UXI-003 | UXP-000 | Navigate | RP-201 | — |
| UXI-004 | UXP-000 | Navigate | RP-205 | — |
| UXI-005 | UXP-000 | Navigate | RP-301 | — |
| UXI-006 | UXP-000 | Navigate | RP-401 | Badge from RD-03 |
| UXI-007 | UXP-000 | Navigate | RP-501 | — |
| UXI-008 | UXP-000 | Navigate | RP-601 | — |
| UXI-009 | UXP-000 | Navigate | RP-301 | — |
| UXI-010 | UXP-000 | Navigate | RP-307 | — |
| UXI-011 | UXP-000 | Navigate | RP-308 | — |
| UXI-012 | UXP-000 | Navigate | RP-401 | — |
| UXI-013 | UXP-000 | Navigate | RP-402 | — |
| UXI-014 | UXP-000 | Navigate | RP-404 | — |
| UXI-015 | UXP-000 | Navigate | RP-407 | — |
| UXI-016 | UXP-000 | Navigate | RP-412 | — |
| UXI-017 | UXP-000 | Navigate | RP-414 | Count from RD-03 |
| UXI-018 | UXP-000 | Navigate | RP-501 | — |
| UXI-019 | UXP-000 | Navigate | RP-205 | — |
| UXI-020 | UXP-000 | Navigate | RP-502 | — |
| UXI-021 | UXP-000 | Navigate | RP-503 | — |
| UXI-022 | UXP-000 | Local | — | No request |
| UXI-023 | UXP-000 | Local | — | No request |
| UXI-024 | UXP-000 | Navigate | RP-003 | — |
| UXI-025 | UXP-000 | Navigate | RP-006 | Shown from local references and RD-04 |
| UXI-026 | UXP-000 | Authentication | RA-02 | — |
| UXI-027 | UXP-000 | Navigate | RP-006 | — |
| UXI-028 | UXP-000 | Authentication | RA-02 | — |
| UXI-029 | UXP-000 | Navigate | RP-006 | Shown from local references and RD-04 |
| UXI-030 | UXP-000 | Navigate | RP-412 | Shown from RD-03 |
| UXI-031 | UXP-000 | Local | — | No request |
| UXI-032 | UXP-000 | Authentication | RA-03, with RA-04 | — |
| UXI-033 | UXP-000 | Local | — | No request |
| UXI-034 | UXP-000 | Read | RA-07 | User-activity request; counts as activity |
| UXI-035 | UXP-000 | Authentication | RA-02 | — |
| UXI-036 | UXP-000 | Command | The frozen request's RC route | Same envelope, key and intent |
| UXI-037 | UXP-000 | Resolution | RC-50 | — |
| UXI-038 | UXP-000 | Local | — | No request |
| UXI-039 | UXP-001 | Local | — | No request |
| UXI-040 | UXP-001 | Local | — | No request |
| UXI-041 | UXP-001 | Authentication | RA-01 | — |
| UXI-042 | UXP-002 | Local | — | No request |
| UXI-043 | UXP-002 | Authentication | RA-06 | — |
| UXI-044 | UXP-002 | Navigate | RP-001 | — |
| UXI-045 | UXP-003 | Navigate | RP-004 | — |
| UXI-046 | UXP-003 | Navigate | RP-005 | — |
| UXI-047 | UXP-004 | Local | — | No request |
| UXI-048 | UXP-004 | Authentication | RA-05 | — |
| UXI-049 | UXP-004 | Navigate | RP-003 | — |
| UXI-050 | UXP-004 | Navigate | RP-003 | — |
| UXI-051 | UXP-005 | Local | — | No request |
| UXI-052 | UXP-005 | Command | RC-37 | — |
| UXI-053 | UXP-005 | Navigate | RP-003 | — |
| UXI-054 | UXP-005 | Navigate | RP-003 | — |
| UXI-055 | UXP-006 | Resolution | RC-50 | Rows from local references and RD-04 |
| UXI-056 | UXP-006 | Navigate | RP-103, RP-204, RP-206, RP-406, RP-409, RP-403 | The result route of the resolved command |
| UXI-057 | UXP-006 | Navigate | Opener (NC-01) | The opener's route, or the role's home (NC-01, NC-12) |
| UXI-058 | UXP-006 | Resolution | RD-04 (`scope=all`); RC-50 | Provided by D-087 (ADR-009 section 2); was hidden |
| UXI-059 | UXP-007 | Navigate | RP-501 | — |
| UXI-060 | UXP-007 | Navigate | RP-206 | — |
| UXI-061 | UXP-007 | Navigate | RP-401 (`state`) | Count from RD-03 |
| UXI-062 | UXP-007 | Navigate | RP-503 | — |
| UXI-063 | UXP-007 | Navigate | RP-414 | — |
| UXI-064 | UXP-007 | Navigate | RP-611 | — |
| UXI-065 | UXP-007 | Navigate | RP-412 | — |
| UXI-066 | UXP-101 | Read | RD-01 | `context=till`, `mode=exact` for a scan, then `search` |
| UXI-067 | UXP-101 | Local | — | No request |
| UXI-068 | UXP-101 | Read | RD-01 | `page` |
| UXI-069 | UXP-101 | Read | RD-01 | `category`, `brand` |
| UXI-070 | UXP-101 | Local | — | No request |
| UXI-071 | UXP-101 | Local | — | No request |
| UXI-072 | UXP-101 | Local | — | No request |
| UXI-073 | UXP-101 | Local | — | No request |
| UXI-074 | UXP-101 | Local | — | No request |
| UXI-075 | UXP-101 | Navigate | RP-102 | — |
| UXI-076 | UXP-101 | Navigate | RP-006 | — |
| UXI-077 | UXP-101 | Navigate | RP-105 | — |
| UXI-078 | UXP-102 | Local | — | No request |
| UXI-079 | UXP-102 | Local | — | No request |
| UXI-080 | UXP-102 | Local | — | No request |
| UXI-081 | UXP-102 | Local | — | No request |
| UXI-082 | UXP-102 | Local | — | No request |
| UXI-083 | UXP-102 | Command | RC-01 | — |
| UXI-084 | UXP-102 | Navigate | RP-101 | — |
| UXI-085 | UXP-102 | Command | RC-01 | Repeat of the frozen request |
| UXI-086 | UXP-102 | Resolution | RC-50 | — |
| UXI-087 | UXP-102 | Navigate | RP-101 | — |
| UXI-088 | UXP-103 | Navigate | RP-101 | — |
| UXI-089 | UXP-103 | Navigate | RP-101 | — |
| UXI-090 | UXP-103 | Command | RC-44, then RP-103P | — |
| UXI-091 | UXP-103 | Local | — | No request |
| UXI-092 | UXP-103 | Navigate | RP-202 | — |
| UXI-093 | UXP-103 | Navigate | RP-101 | — |
| UXI-094 | UXP-104 | Local | — | No request |
| UXI-095 | UXP-104 | Local | — | No request |
| UXI-096 | UXP-104 | Local | — | No request |
| UXI-097 | UXP-104 | Command | RC-02 | — |
| UXI-098 | UXP-104 | Local | — | No request |
| UXI-099 | UXP-104 | Command | RC-02; RC-50 | Repeat; status check |
| UXI-100 | UXP-104 | Navigate | RP-007, RP-401, RP-201 | The opener's route, or the role's home (NC-01, NC-12) |
| UXI-101 | UXP-105 | Read | RP-105 | Deferred prop: close preview with drawer-funded refunds |
| UXI-102 | UXP-105 | Navigate | RP-006 | — |
| UXI-103 | UXP-105 | Local | — | No request |
| UXI-104 | UXP-105 | Local | — | No request |
| UXI-105 | UXP-105 | Local | — | No request |
| UXI-106 | UXP-105 | Local | — | No request |
| UXI-107 | UXP-105 | Command | RC-03 | — |
| UXI-108 | UXP-105 | Local | — | No request |
| UXI-109 | UXP-105 | Command | RC-03; RC-50 | Repeat; status check |
| UXI-110 | UXP-105 | Navigate | RP-101 | — |
| UXI-111 | UXP-105 | Navigate | RP-101 | — |
| UXI-112 | UXP-201 | Read | RP-201 | `from`, `to` |
| UXI-113 | UXP-201 | Read | RP-201 | `number` |
| UXI-114 | UXP-201 | Read | RP-201 | `cashier`, `register` (Owner) |
| UXI-115 | UXP-201 | Navigate | RP-202 | — |
| UXI-116 | UXP-201 | Read | RP-201 | `after`, `before` |
| UXI-117 | UXP-202 | Command | RC-44, then RP-103P | A reprint (copy marker) |
| UXI-118 | UXP-202 | Navigate | RP-203 | — |
| UXI-119 | UXP-202 | Navigate | RP-206 | — |
| UXI-120 | UXP-202 | Read | RP-202 | Owner-only prop: linked corrections |
| UXI-121 | UXP-202 | Navigate | RP-201 | The opener's route, or the role's home (NC-01, NC-12) |
| UXI-122 | UXP-203 | Local | — | No request |
| UXI-123 | UXP-203 | Local | — | No request |
| UXI-124 | UXP-203 | Local | — | No request |
| UXI-125 | UXP-203 | Local | — | No request |
| UXI-126 | UXP-203 | Local | — | No request |
| UXI-127 | UXP-203 | Local | — | No request |
| UXI-128 | UXP-203 | Command | RC-05 or RC-06 | Drawer-funded, or Owner-funded and BANK_TRANSFER |
| UXI-129 | UXP-203 | Local | — | No request |
| UXI-130 | UXP-203 | Command | RC-05 or RC-06; RC-50 | Repeat; status check |
| UXI-131 | UXP-203 | Navigate | RP-202 | — |
| UXI-132 | UXP-203 | Navigate | RP-202 | — |
| UXI-133 | UXP-204 | Read | RP-204 | The committed correction; cash instruction first |
| UXI-134 | UXP-204 | Navigate | RP-202 | — |
| UXI-135 | UXP-205 | Read | RP-205 | `status`, `from`, `to` |
| UXI-136 | UXP-205 | Read | RP-205 | `register`, `responsible` (Owner) |
| UXI-137 | UXP-205 | Navigate | RP-206 | — |
| UXI-138 | UXP-205 | Read | RP-205 | `after`, `before` |
| UXI-139 | UXP-206 | Navigate | RP-105 | — |
| UXI-140 | UXP-206 | Navigate | RP-207 | — |
| UXI-141 | UXP-206 | Command | RC-45, then RP-206P | — |
| UXI-142 | UXP-206 | Command | RC-46 | The answer is the CSV of DC-03 |
| UXI-143 | UXP-206 | Read | RP-206 | Prop: drawer-funded refunds of the session |
| UXI-144 | UXP-206 | Read | RP-206 | Owner-only prop: review notes |
| UXI-145 | UXP-206 | Local | — | No request |
| UXI-146 | UXP-206 | Command | RC-43 | — |
| UXI-147 | UXP-206 | Local | — | No request |
| UXI-148 | UXP-206 | Navigate | RP-205, RP-105, RP-207 | The opener's route, or the role's home (NC-01, NC-12) |
| UXI-149 | UXP-207 | Read | RP-207 | Deferred prop: close preview, with the responsible user's unresolved checkout receipts (ADR-009) |
| UXI-150 | UXP-207 | Local | — | No request |
| UXI-151 | UXP-207 | Local | — | No request |
| UXI-152 | UXP-207 | Local | — | No request |
| UXI-153 | UXP-207 | Local | — | No request |
| UXI-154 | UXP-207 | Command | RC-04 | — |
| UXI-155 | UXP-207 | Local | — | No request |
| UXI-156 | UXP-207 | Command | RC-04; RC-50 | Repeat; status check |
| UXI-157 | UXP-207 | Navigate | RP-206 | — |
| UXI-158 | UXP-207 | Navigate | RP-206 | — |
| UXI-159 | UXP-301 | Read | RD-01 | `context=catalog` |
| UXI-160 | UXP-301 | Read | RD-01 | `category`, `brand`, `status` |
| UXI-161 | UXP-301 | Navigate | RP-303 | — |
| UXI-162 | UXP-301 | Navigate | RP-302 | — |
| UXI-163 | UXP-301 | Navigate | RP-306 | — |
| UXI-164 | UXP-301 | Read | RD-01 | `page` |
| UXI-165 | UXP-302 | Navigate | RP-304 | — |
| UXI-166 | UXP-302 | Command | RC-19 or RC-20 | — |
| UXI-167 | UXP-302 | Navigate | RP-402 | — |
| UXI-168 | UXP-302 | Read | RP-302 | Prop: price revisions |
| UXI-169 | UXP-302 | Command | RC-25 | Multipart |
| UXI-170 | UXP-302 | Command | RC-26 | — |
| UXI-171 | UXP-302 | Command | RC-27 | — |
| UXI-172 | UXP-302 | Command | RC-28 | — |
| UXI-173 | UXP-302 | Command | RC-29 | — |
| UXI-174 | UXP-302 | Navigate | RP-305 | — |
| UXI-175 | UXP-302 | Navigate | RP-306 | — |
| UXI-176 | UXP-302 | Navigate | RP-505 | — |
| UXI-177 | UXP-302 | Navigate | RP-303, RP-612, RP-006, RP-301 | The opener's route, or the role's home (NC-01, NC-12) |
| UXI-178 | UXP-303 | Read | RD-06 | `barcode`, `sku`, `name` |
| UXI-179 | UXP-303 | Navigate | RP-302 | — |
| UXI-180 | UXP-303 | Local | — | No request |
| UXI-181 | UXP-303 | Local | — | No request |
| UXI-182 | UXP-303 | Local | — | No request |
| UXI-183 | UXP-303 | Command | RC-17 | — |
| UXI-184 | UXP-303 | Navigate | Opener (NC-01) | The opener's route, or the role's home (NC-01, NC-12) |
| UXI-185 | UXP-303 | Navigate | Opener (NC-01) | The opener's route, or the role's home (NC-01, NC-12) |
| UXI-186 | UXP-304 | Local | — | No request |
| UXI-187 | UXP-304 | Command | RC-18 | — |
| UXI-188 | UXP-304 | Navigate | RP-302 | — |
| UXI-189 | UXP-304 | Navigate | RP-302 | — |
| UXI-190 | UXP-305 | Read | RD-05 | `token` |
| UXI-191 | UXP-305 | Read | RD-01 | `context=correction` |
| UXI-192 | UXP-305 | Local | — | No request |
| UXI-193 | UXP-305 | Local | — | No request |
| UXI-194 | UXP-305 | Command | RC-30 | — |
| UXI-195 | UXP-305 | Local | — | No request |
| UXI-196 | UXP-305 | Command | RC-30; RC-50 | Repeat; status check |
| UXI-197 | UXP-305 | Navigate | RP-302 | — |
| UXI-198 | UXP-305 | Navigate | RP-302 | — |
| UXI-199 | UXP-306 | Local | — | No request |
| UXI-200 | UXP-306 | Command | RC-47, then RP-306P | — |
| UXI-201 | UXP-306 | Navigate | RP-302 | — |
| UXI-202 | UXP-307 | Read | RP-307 | `status` |
| UXI-203 | UXP-307 | Command | RC-21 | — |
| UXI-204 | UXP-307 | Command | RC-22 | — |
| UXI-205 | UXP-307 | Command | RC-23 or RC-24 | — |
| UXI-206 | UXP-308 | Read | RP-308 | Prop: the column list of DC-04 |
| UXI-207 | UXP-308 | Request | RR-01 | Multipart; temporary data only |
| UXI-208 | UXP-308 | Read | RR-01 | The preview in the answer of RR-01 |
| UXI-209 | UXP-308 | Navigate | RP-307 | — |
| UXI-210 | UXP-308 | Command | RC-31 | — |
| UXI-211 | UXP-308 | Command | RC-31; RC-50 | Repeat; status check |
| UXI-212 | UXP-308 | Request | RR-02 | Removes the temporary file |
| UXI-213 | UXP-401 | Read | RD-02 | `q` |
| UXI-214 | UXP-401 | Read | RD-02 | `state`, `category` |
| UXI-215 | UXP-401 | Read | RD-02 | With RD-03 for the badge |
| UXI-216 | UXP-401 | Navigate | RP-402 | — |
| UXI-217 | UXP-401 | Navigate | RP-410 | — |
| UXI-218 | UXP-401 | Read | RD-02 | `after` |
| UXI-219 | UXP-402 | Read | RP-402 | `product`, `type`, `from`, `to` |
| UXI-220 | UXP-402 | Navigate | RP-403 | — |
| UXI-221 | UXP-402 | Navigate | RP-410 | — |
| UXI-222 | UXP-402 | Read | RP-402 | `after`, `before` |
| UXI-223 | UXP-402 | Navigate | RP-302, RP-401, RP-415, RP-006 | The opener's route, or the role's home (NC-01, NC-12) |
| UXI-224 | UXP-403 | Navigate | RP-406, RP-409, RP-202 | — |
| UXI-225 | UXP-403 | Read | RP-403 | Owner-only prop: cost evidence (CAP-20) |
| UXI-226 | UXP-403 | Navigate | RP-504 | — |
| UXI-227 | UXP-403 | Navigate | RP-411 | — |
| UXI-228 | UXP-403 | Command | RP-411 → RC-10; then RP-413 → RC-48 | Provided by D-087 (T-17 b): reverse the OPENING, then record its replacement; was hidden |
| UXI-229 | UXP-403 | Navigate | RP-402 | The opener's route, or the role's home (NC-01, NC-12) |
| UXI-230 | UXP-404 | Read | RP-404 | `from`, `to`, `q` |
| UXI-231 | UXP-404 | Navigate | RP-405 | — |
| UXI-232 | UXP-404 | Navigate | RP-406 | — |
| UXI-233 | UXP-404 | Read | RP-404 | `after`, `before` |
| UXI-234 | UXP-405 | Read | RD-01 | `context=receiving` |
| UXI-235 | UXP-405 | Navigate | RP-303 | — |
| UXI-236 | UXP-405 | Local | — | No request |
| UXI-237 | UXP-405 | Local | — | No request |
| UXI-238 | UXP-405 | Local | — | No request |
| UXI-239 | UXP-405 | Local | — | No request |
| UXI-240 | UXP-405 | Command | RC-07 | — |
| UXI-241 | UXP-405 | Local | — | No request |
| UXI-242 | UXP-405 | Command | RC-07; RC-50 | Repeat; status check |
| UXI-243 | UXP-405 | Navigate | RP-404 | — |
| UXI-244 | UXP-405 | Not provided | — | No draft route (D-069 a) |
| UXI-245 | UXP-405 | Navigate | RP-404 | — |
| UXI-246 | UXP-406 | Read | RP-406 | Lines with cost as the role may see it |
| UXI-247 | UXP-406 | Read | RP-406 | Owner-only prop: correction history |
| UXI-248 | UXP-406 | Navigate | RP-403 | — |
| UXI-249 | UXP-406 | Navigate | RP-504 | — |
| UXI-250 | UXP-406 | Navigate | RP-411 | — |
| UXI-251 | UXP-406 | Navigate | RP-403, RP-612, RP-006, RP-404, RP-405 | The opener's route, or the role's home (NC-01, NC-12) |
| UXI-252 | UXP-407 | Read | RP-407 | `from`, `to`, `reason` |
| UXI-253 | UXP-407 | Navigate | RP-408 | — |
| UXI-254 | UXP-407 | Navigate | RP-409 | — |
| UXI-255 | UXP-407 | Read | RP-407 | `after`, `before` |
| UXI-256 | UXP-408 | Read | RD-01 | `context=issue` |
| UXI-257 | UXP-408 | Local | — | No request |
| UXI-258 | UXP-408 | Local | — | No request |
| UXI-259 | UXP-408 | Local | — | No request |
| UXI-260 | UXP-408 | Command | RC-08 | — |
| UXI-261 | UXP-408 | Local | — | No request |
| UXI-262 | UXP-408 | Command | RC-08; RC-50 | Repeat; status check |
| UXI-263 | UXP-408 | Navigate | RP-407 | — |
| UXI-264 | UXP-408 | Navigate | RP-407 | — |
| UXI-265 | UXP-409 | Navigate | RP-403 | — |
| UXI-266 | UXP-409 | Navigate | RP-411 | — |
| UXI-267 | UXP-409 | Navigate | RP-403, RP-612, RP-006, RP-407, RP-408 | The opener's route, or the role's home (NC-01, NC-12) |
| UXI-268 | UXP-410 | Read | RD-01 | `context=adjustment` |
| UXI-269 | UXP-410 | Local | — | No request |
| UXI-270 | UXP-410 | Local | — | No request |
| UXI-271 | UXP-410 | Local | — | No request |
| UXI-272 | UXP-410 | Command | RC-09 | — |
| UXI-273 | UXP-410 | Local | — | No request |
| UXI-274 | UXP-410 | Command | RC-09; RC-50 | Repeat; status check |
| UXI-275 | UXP-410 | Navigate | Opener (NC-01) | The opener's route, or the role's home (NC-01, NC-12) |
| UXI-276 | UXP-410 | Navigate | Opener (NC-01) | The opener's route, or the role's home (NC-01, NC-12) |
| UXI-277 | UXP-411 | Read | RP-411 | The source movement and its exact inverse |
| UXI-278 | UXP-411 | Local | — | No request |
| UXI-279 | UXP-411 | Local | — | No request |
| UXI-280 | UXP-411 | Command | RC-10 | — |
| UXI-281 | UXP-411 | Local | — | No request |
| UXI-282 | UXP-411 | Command | RC-10; RC-50 | Repeat; status check |
| UXI-283 | UXP-411 | Navigate | Opener (NC-01) | The opener's route, or the role's home (NC-01, NC-12) |
| UXI-284 | UXP-411 | Navigate | Opener (NC-01) | The opener's route, or the role's home (NC-01, NC-12) |
| UXI-285 | UXP-412 | Command | RC-12 | — |
| UXI-286 | UXP-412 | Navigate | RP-413 | — |
| UXI-287 | UXP-412 | Read | RP-412 | `filter` |
| UXI-288 | UXP-412 | Navigate | RP-403 | — |
| UXI-289 | UXP-412 | Command | RC-13 | — |
| UXI-290 | UXP-412 | Command | RC-12 or RC-13; RC-50 | Repeat; status check |
| UXI-291 | UXP-412 | Command | RC-14; or RP-411 → RC-10, then RC-48 | Provided by D-087 (T-17 b); was hidden |
| UXI-292 | UXP-413 | Read | RD-01 | `context=opening`: eligibility of D-087 |
| UXI-293 | UXP-413 | Navigate | RP-303 | — |
| UXI-294 | UXP-413 | Local | — | No request |
| UXI-295 | UXP-413 | Local | — | No request |
| UXI-296 | UXP-413 | Command | RC-11 | — |
| UXI-297 | UXP-413 | Local | — | No request |
| UXI-298 | UXP-413 | Command | RC-11; RC-50 | Repeat; status check |
| UXI-299 | UXP-413 | Navigate | RP-412 | — |
| UXI-300 | UXP-413 | Navigate | RP-412 | — |
| UXI-301 | UXP-414 | Read | RP-414 | `status` |
| UXI-302 | UXP-414 | Navigate | RP-415 | — |
| UXI-303 | UXP-415 | Navigate | RP-402 | — |
| UXI-304 | UXP-415 | Command | RC-16 | — |
| UXI-305 | UXP-415 | Navigate | RP-414 | — |
| UXI-306 | UXP-501 | Read | RP-501 | `date` |
| UXI-307 | UXP-501 | Navigate | RP-206 | — |
| UXI-308 | UXP-502 | Read | RP-502 | `period` |
| UXI-309 | UXP-502 | Read | RP-502 | `period=custom`, `from`, `to` (IC-05) |
| UXI-310 | UXP-502 | Read | RP-502 | Prop: trend series |
| UXI-311 | UXP-502 | Local | — | No request |
| UXI-312 | UXP-502 | Navigate | RP-503 | — |
| UXI-313 | UXP-503 | Read | RP-503 | `product` |
| UXI-314 | UXP-503 | Navigate | RP-504 | — |
| UXI-315 | UXP-503 | Navigate | RP-505 | — |
| UXI-316 | UXP-503 | Read | RP-503 | `after`, `before` |
| UXI-317 | UXP-504 | Local | — | No request |
| UXI-318 | UXP-504 | Local | — | No request |
| UXI-319 | UXP-504 | Command | RC-15 | — |
| UXI-320 | UXP-504 | Local | — | No request |
| UXI-321 | UXP-504 | Command | RC-15; RC-50 | Repeat; status check |
| UXI-322 | UXP-504 | Navigate | Opener (NC-01) | The opener's route, or the role's home (NC-01, NC-12) |
| UXI-323 | UXP-504 | Navigate | Opener (NC-01) | The opener's route, or the role's home (NC-01, NC-12) |
| UXI-324 | UXP-505 | Navigate | RP-504 | — |
| UXI-325 | UXP-505 | Navigate | Opener (NC-01) | The opener's route, or the role's home (NC-01, NC-12) |
| UXI-326 | UXP-601 | Navigate | RP-602 | — |
| UXI-327 | UXP-601 | Navigate | RP-607 | — |
| UXI-328 | UXP-601 | Navigate | RP-609 | — |
| UXI-329 | UXP-601 | Navigate | RP-611 | — |
| UXI-330 | UXP-601 | Navigate | RP-612 | — |
| UXI-331 | UXP-601 | Navigate | RP-613 | — |
| UXI-332 | UXP-602 | Read | RP-602 | `status`, `role` |
| UXI-333 | UXP-602 | Navigate | RP-603 | — |
| UXI-334 | UXP-602 | Navigate | RP-604 | — |
| UXI-335 | UXP-602 | Navigate | RP-601 | — |
| UXI-336 | UXP-603 | Local | — | No request |
| UXI-337 | UXP-603 | Command | RC-35 | The answer carries the link once |
| UXI-338 | UXP-603 | Navigate | RP-602 | — |
| UXI-339 | UXP-603 | Navigate | RP-602 | — |
| UXI-340 | UXP-604 | Navigate | RP-206, RP-207 | — |
| UXI-341 | UXP-604 | Navigate | RP-605 | — |
| UXI-342 | UXP-604 | Command | RC-41 | Kind RESET; the answer carries the link once |
| UXI-343 | UXP-604 | Command | RC-41 | Kind FIRST_PASSWORD; the answer carries the link once |
| UXI-344 | UXP-604 | Command | RC-42 | — |
| UXI-345 | UXP-604 | Command | RC-38 | — |
| UXI-346 | UXP-604 | Command | RC-39 | — |
| UXI-347 | UXP-604 | Command | RC-40 | — |
| UXI-348 | UXP-604 | Not provided | — | Operator procedure OP-03 only (CAP-33) |
| UXI-349 | UXP-604 | Navigate | RP-612, RP-602 | The opener's route, or the role's home (NC-01, NC-12) |
| UXI-350 | UXP-605 | Local | — | No request |
| UXI-351 | UXP-605 | Command | RC-36 | — |
| UXI-352 | UXP-605 | Navigate | RP-604 | — |
| UXI-353 | UXP-605 | Navigate | RP-604 | — |
| UXI-354 | UXP-606 | Read | RC-35, RC-41 | The link from the command's answer, in page memory only (SEC-ACC-05) |
| UXI-355 | UXP-606 | Local | — | No request |
| UXI-356 | UXP-606 | Read | RP-606 | Static guidance |
| UXI-357 | UXP-606 | Navigate | RP-604 | — |
| UXI-358 | UXP-607 | Navigate | RP-608 | — |
| UXI-359 | UXP-607 | Local | — | No request |
| UXI-360 | UXP-607 | Navigate | RP-601 | — |
| UXI-361 | UXP-608 | Local | — | No request |
| UXI-362 | UXP-608 | Local | — | No request |
| UXI-363 | UXP-608 | Field | RC-32 (`accentToken`) | Provided: the accent set of DESIGN_SYSTEM section 3.3 (D-086); was hidden |
| UXI-364 | UXP-608 | Command | RC-32 | Multipart when a logo is chosen |
| UXI-365 | UXP-608 | Navigate | RP-607 | — |
| UXI-366 | UXP-608 | Navigate | RP-607 | — |
| UXI-367 | UXP-609 | Navigate | RP-610 | — |
| UXI-368 | UXP-609 | Navigate | RP-610 | — |
| UXI-369 | UXP-609 | Navigate | RP-601 | — |
| UXI-370 | UXP-610 | Local | — | No request |
| UXI-371 | UXP-610 | Command | RC-33 or RC-34 | — |
| UXI-372 | UXP-610 | Navigate | RP-609 | — |
| UXI-373 | UXP-610 | Navigate | RP-609 | — |
| UXI-374 | UXP-611 | Read | RP-611 | Reload of the page |
| UXI-375 | UXP-611 | Navigate | RP-414 | — |
| UXI-376 | UXP-611 | Navigate | RP-601 | — |
| UXI-377 | UXP-612 | Read | RP-612 | `from`, `to`, `user`, `action`, `product`, `session`, `reference` |
| UXI-378 | UXP-612 | Local | — | No request |
| UXI-379 | UXP-612 | Navigate | RP-202, RP-206, RP-302, RP-403, RP-406, RP-409, RP-604 | — |
| UXI-380 | UXP-612 | Read | RP-612 | `after`, `before` |
| UXI-381 | UXP-612 | Navigate | RP-601 | — |
| UXI-382 | UXP-613 | Read | RP-613 | `from`, `to`, `type`, `user` |
| UXI-383 | UXP-613 | Local | — | No request |
| UXI-384 | UXP-613 | Read | RP-613 | `after`, `before` |
| UXI-385 | UXP-613 | Navigate | RP-601 | — |

## 10. Rows of the approved P7 documents read with later decisions

The six P7-G1 documents and COPY_DECK are approved by their hashes (D-082, D-086) and are not edited here. The following rows are read with the decisions named, and are brought in line at the next authorized revision of those documents (OPEN_ITEMS T-20):

| Row | Reading | Decision |
| --- | --- | --- |
| UXI-058 | Provided: the Owner's "Cek status" for another user's unresolved request on UXP-006 (section 2.2) | D-087, ADR-009 |
| UXI-228 | Provided on UXP-403 for an OPENING while onboarding is active: "Batalkan mutasi" leads to RP-411; after the reversal, the replacement is recorded with RC-48 | D-087 (T-17 b) |
| UXI-227 | While onboarding is active, the control is also shown for an OPENING (it was not shown pending T-17 b) | D-087 |
| UXI-291 | Provided on UXP-412: for a zero-count entry, its withdrawal (RC-14); for a reversed OPENING, the replacement entry (RC-48); for an effective OPENING, a link to UXP-403 | D-087 |
| UXI-292 | A product with an effective zero count or OPENING shows as counted; a product whose zero count was withdrawn is eligible; a product whose OPENING was reversed shows the replacement entry (section 3.1) | D-087 |
| UXI-363 | Provided: the accent set of DESIGN_SYSTEM section 3.3, checked on the server (RC-32) | D-086, D-087 |
| UXI-025, UXI-029, UXI-055 | The unresolved requests include the user's server receipts (section 1.4) | D-087 (N-07) |
| UXI-103, UXI-149, UXI-150 | BR-CASH-16 is met as section 2.5 says; UXI-150 is disabled while a listed checkout of the responsible user is unresolved | D-087 |
| UXI-076 | Finding F-02: shown only while a checkout is unresolved, never disabled; INTERACTION_INVENTORY section 3 counts 47 rows disabled with a reason, not 48 | COPY_DECK section 21.1 |
| UXI-055, UXI-086 and the UXP-006 row of NAVIGATION_CONTRACTS | Finding F-05: a status check without an answer leaves the request in the state it was in (section 1.3) | D-084 |
| UXI-244, UXI-348 | Stay not provided: no draft route (D-069 (a)); CAP-33 only through OP-01 and OP-03 | D-069, D-048 |

## 11. Copy proposals for COPY_DECK change control

These words are needed before the Tasks that use them ship; they are proposals, not approved copy (COPY_DECK section 1.1; OPEN_ITEMS T-20):

| Need | Proposed key and text |
| --- | --- |
| UXI-058, the Owner's rows of another user's requests | `commands.requests.other_user`: "Permintaan :nama"; `commands.requests.other_user_help`: "Cek status permintaan ini atas nama Pemilik. Jika belum tersimpan, permintaan ini tidak akan pernah tersimpan." |
| Rows known only from the server's receipts | `commands.requests.from_server`: "Dikirim dari perangkat lain atau sebelum data peramban dihapus." |
| The lost-reference rule (section 2.4) | `commands.requests.lost_help`: "Jika permintaan tidak ada di daftar ini dan tidak ada di riwayat, tunggu :menit menit sejak dikirim. Setelah itu permintaan itu pasti tidak tersimpan dan Anda dapat mulai lagi." |
| UXI-150 and UXI-103 disabled reason (section 2.5) | `ux.disabled.unresolved_checkouts`: "Ada penjualan yang statusnya belum pasti. Cek statusnya dulu sebelum menghitung laci." |
| UXI-291, withdrawal of a zero count (RC-14) | `inventory.onboarding.withdraw_zero`: "Tarik hitungan nol"; review title "Tarik hitungan nol?"; body "Hitungan nol ini tetap tercatat di riwayat, lalu produk ini bisa dihitung lagi."; success "Hitungan nol ditarik." |
| UXI-228 and UXI-291, the replacement of a reversed OPENING (RC-48) | `inventory.opening_entry.replace`: "Catat ulang stok awal"; eligibility "Stok awal dibatalkan; bisa dicatat ulang."; review title "Tinjau stok awal pengganti"; success "Stok awal pengganti tersimpan." |
| Audit labels (section 8) | `audit.action.opening_stock_replace`: "Stok awal dicatat ulang"; `audit.action.zero_count_withdraw`: "Hitungan nol ditarik"; `audit.action.onboarding_restore_hold`: "Stok awal ditahan setelah pemulihan"; `audit.action.restore_reconciliation`: "Rekonsiliasi pemulihan"; `audit.action.projection_rebuilt` (RF-6, D-088): "Data stok dan biaya dibangun ulang dari mutasi" |
| DC-04 column headers (UXI-206) | The headers of DOCUMENT_CONTRACTS DC-04 |
| T-19 | The eight texts and the percent format of DESIGN_SYSTEM section 13, already listed in OPEN_ITEMS |

## 12. Checks

| Check | Result |
| --- | --- |
| INTERACTION_INVENTORY rows with a route or an explicit "Local" or "Not provided" | 385 of 385 (section 9) |
| Rows still hidden by design | 0: UXI-058, UXI-228, UXI-291 and UXI-363 are provided |
| Command operations with a contract | 48 of 48, plus RC-50 and SC-01 |
| Error codes used that are not in TECHNICAL_RULES section 4 | 0 |
| Commands that take a price, a total, expected cash, a cost scope or a number from the client | 0 (TR-VAL-03) |
| Audit action codes without an approved label | 5, proposed in section 11 (the fifth, `PROJECTION_REBUILT`, added in RF-6) |
