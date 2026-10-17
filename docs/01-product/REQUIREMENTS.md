# Requirements

| | |
| --- | --- |
| Status | CURRENT — written in RF-2 under D-041, 4 October 2026 (Asia/Jakarta) |
| Phase | P1 — functional and non-functional requirements, acceptance criteria |
| Replaces | `docs/01-product/legacy/requirements/core.json` and `docs/01-product/legacy/requirements/CORE-MAP.md` (CORE-001 to CORE-073) |
| Product | [PRODUCT_BLUEPRINT](PRODUCT_BLUEPRINT.md) |
| Domain rules | [DOMAIN_MODEL](../02-domain/DOMAIN_MODEL.md), [CATALOG_BARCODE](../02-domain/CATALOG_BARCODE.md), [INVENTORY](../02-domain/INVENTORY.md), [COSTING](../02-domain/COSTING.md), [SALES_CASH](../02-domain/SALES_CASH.md) |

The requirement register of BantuKasir V1. Each requirement says what the product must do, how acceptance is judged, where the requirement comes from, and which legacy acceptance suite tests it today. Section 21 maps every legacy CORE entry to these requirements; section 22 traces every decision D-001 to D-084.

## 1. How to read this register

- **Identifiers** are `REQ-<area>-<nn>` and permanent. Task contracts cite them. A requirement that is replaced keeps its row and is marked SUPERSEDED with its replacement.
- **Requirement** states the obligation in shop terms ([GLOSSARY](../02-domain/GLOSSARY.md)). The detailed rule is the `BR-…` it cites in a domain document; where a requirement and a rule seem to differ, the rule's source decision decides, and the difference is a defect to report.
- **Acceptance criteria** are the observable conditions that must pass with real evidence (PRODUCT_BLUEPRINT section 11).
- **Source** names decisions, legacy documents (keys in DOMAIN_MODEL section 2) and rules. No requirement exists without a source.
- **Tests** names the legacy acceptance suites in `docs/08-testing/legacy/08-TESTING-ACCEPTANCE.md`, which remain the test references until RF-6. `NEW` means no legacy suite covers the requirement; RF-6 adds coverage. Several suites were written before D-033 to D-040; where a suite's text conflicts with a requirement here, the requirement prevails. Known cases: AUTH02 (write-only cost for OPERATIONS_ADMIN, superseded by D-033); RET01 and SESSION04 (refunds from Owner funds only and expected cash without refunds, superseded by D-035); UX01 (approval of the former design revision, superseded by D-024 and D-025). The "no QRIS" expectation of POS02 and PAYEXT01, superseded by D-036, holds again under D-077.
- **Decisions taken during RF-2 and before RF-3A.** Questions 21 to 24 and 27 to 28 were asked during RF-2 and answered by D-042 to D-047; questions 25, 6, 5, 26, 31, 7 and 30 were answered before RF-3A by D-048 to D-054. Section 20 lists the requirements these decisions shape. Questions 33, 34 and 32 were answered before RF-3B by D-060 to D-062; they change no requirement (section 22). No requirement is pending.
- **Words.** "Session" alone means a cash session; a sign-in session is always named in full (DOMAIN_MODEL section 1).

## 2. Scope

| ID | Requirement | Acceptance criteria | Source | Tests |
| --- | --- | --- | --- | --- |
| REQ-SCOPE-01 | One installation serves one business with one stock pool, for goods only. It is not multi-tenant; another shop uses its own installation. | No tenant, outlet, warehouse or location concept exists in data or interface. Two installations share no database, storage or configuration. | D-002, D-038; BR-DOM-01 | SEARCH02, INV01, POS01 |
| REQ-SCOPE-02 | Capabilities outside V1 (PRODUCT_BLUEPRINT section 8) are inactive. No visible control, menu, method code or template makes one appear available. | The interface inventory shows no such control. The server refuses unknown methods and operations. | D-002, L-PRD §Batas Later; BR-PAY-07 | UX01, PAYEXT01 |
| REQ-SCOPE-03 | The product is client-neutral: the shop's identity comes only from its BusinessProfile. "BantuKasir" is never shown as the shop's identity, and LATANSA is never a default. | No client name or logo exists in code or bundled assets. No client identity appears unless configured in the BusinessProfile. | D-038, D-010; BR-DOM-11, BR-CFG-01 | IDENTITY01 |

## 3. Access and accounts

| ID | Requirement | Acceptance criteria | Source | Tests |
| --- | --- | --- | --- | --- |
| REQ-ACC-01 | There are exactly three fixed roles: SUPER_ADMIN (Owner), OPERATIONS_ADMIN and CASHIER. | No function creates or edits roles. The server refuses any other role value. | D-002, D-032; BR-USR-01 | AUTH01 |
| REQ-ACC-02 | One account may hold CASHIER and OPERATIONS_ADMIN together, with the union of their permissions. SUPER_ADMIN is never combined. No Owner-only right is reachable through the combination. | Server tests show a combined account is refused every Owner-only action: cost of goods sold and profit, cost correction after posting, refunds and returns, opening stock, adjustments, reversals, user management, configuration. Assigning SUPER_ADMIN together with another role is refused. | D-032; BR-USR-02 | NEW (extends AUTH01, AUTH03) |
| REQ-ACC-03 | Authorization is deny by default and enforced on the server for every request — mutations, search, history, status, recovery, reprint, PDF, CSV and media — with object scope. The actor comes from the sign-in session; identities sent by the browser are never trusted. | Direct requests by each role to every route, and requests with guessed object identifiers, are refused outside scope. | D-007, AGENTS rule 8; BR-DOM-06, BR-DOM-07 | AUTH01 |
| REQ-ACC-04 | Every output channel follows a per-role allowlist: search, detail, history, validation errors, status and recovery, receipts, reports, print, PDF, CSV, media, caches and logs. Staff never receive cost beyond the D-033 grant, profit, or sensitive security or recovery detail. After sign-out or a change of user, Back, Forward, reload and the browser cache show nothing of the previous user. | Payload inspection per role and channel. Sign-out → other user → Back, Forward, reload tests show no earlier data. | D-007, D-033, L-04; BR-DOM-07, BR-COST-06 to BR-COST-08 | AUTH02 |
| REQ-ACC-05 | Accounts are individual and created only by the Owner. There is no public sign-up. The first Owner account is created by a private one-time operator procedure. There is no default or demo account in production. Sign-in is by email and password; the email is never verified and the application sends no email; there is no Google sign-in. A staff member sets the first password, and a reset password, through a one-time link the Owner creates and hands over directly; it expires after 60 minutes and works once. The Owner's own recovery is an operator procedure. Passwords follow AICWDF-COMPAT-8. There is no two-factor authentication. | No sign-up or public password-reset route exists and no flow sends email. The bootstrap procedure refuses to run twice. A production build seeds no account. A link is refused after use, after 60 minutes, after it is replaced or revoked, and after the account is deactivated. A password that breaks AICWDF-COMPAT-8 is refused. | L-04, L-P3, D-049, D-048; BR-USR-03; SECURITY_MODEL sections 2 and 3 | AUTH01, SEC01 (link expiry and replacement NEW) |
| REQ-ACC-06 | Only the Owner assigns roles; nobody assigns a role to themselves. After a role change or deactivation no old permission survives. Deactivation ends sign-in sessions and mutation rights, also for a command already in flight. | A role change sent by a non-Owner is refused. A command racing a deactivation fails before any fact (real PostgreSQL). Old sign-in sessions are revoked. | L-04, CORE-003; BR-USR-04, BR-USR-05 | AUTH01, AUTH03 |
| REQ-ACC-07 | A sign-in session ends after 30 minutes of inactivity and after 12 hours at most. Signing out or a sign-in session ending never changes a cash session. | Both limits are enforced. After a timeout or sign-out the user's cash session is still OPEN and unchanged. | D-039, D-013; BR-USR-06, BR-CASH-11 | NEW (AUTH01 covers expiry) |
| REQ-ACC-08 | Sensitive Owner actions need authentication within the last 15 minutes: refunds and returns, cost corrections, adjustments and reversals, barcode misassignment corrections, user management, recovery, sensitive configuration (BusinessProfile, cash registers, onboarding) and Owner-assisted close (SECURITY_MODEL section 6). | Each action is refused without recent authentication and accepted after it. | D-013, D-008, D-035, D-051, L-04; BR-USR-07 | AUTH03, RET01, INV03 |
| REQ-ACC-09 | Writers of users, roles, configuration, payment methods, profile and onboarding, and business commands, use matching ordered guards: the change that wins applies at validation, and snapshots use the correct revision. | Real PostgreSQL race tests for each writer against racing commands. | L-04, D-006; BR-DOM-05 | AUTH03 |
| REQ-ACC-10 | The Owner deactivates and reactivates staff users, never a SUPER_ADMIN account (D-048); the user's identity stays on everything they did. Accounts are never deleted, softly or permanently. | A deactivated user cannot sign in or act; their history shows their identity; reactivation restores the same account; no delete function exists. | L-04, L-02, D-042, D-048; BR-USR-05, BR-USR-09, BR-USR-10 | AUTH01, AUDIT01 (reactivation NEW) |
| REQ-ACC-11 | Only the Owner may anonymize an account, only after it is deactivated, with a reason; personal and profile fields are replaced with a neutral label, the sign-in email with a unique unusable placeholder, and the password is made unusable (SECURITY_MODEL SEC-ACC-09); the internal identity and audit relationships remain; the action is audited permanently; an anonymized account is never reactivated or reused; a returning person gets a new account. Anonymization never rewrites any historical record. | Non-Owner requests and requests on an active account are refused; after anonymization, earlier receipts and session reports reproduce exactly as before, audit links remain, and reactivation is refused. | D-043, D-049; BR-USR-11, BR-USR-12 | NEW |
| REQ-ACC-12 | There is exactly one active Owner (SUPER_ADMIN) account at any time. No function of the product creates a second Owner account, assigns or removes SUPER_ADMIN, or deactivates or anonymizes the only active Owner account. Owner recovery and Owner replacement are documented operator procedures on the server, rehearsed before the pilot; a replaced Owner's account is deactivated, never deleted. | Requests to create a SUPER_ADMIN account, to assign SUPER_ADMIN, to deactivate or anonymize the only active Owner account, or to reactivate a former Owner account are refused; the bootstrap refuses to run when any account exists; after a rehearsed replacement exactly one Owner account is active and the former one is deactivated; a rehearsed recovery issues a one-time link and revokes the Owner's sign-in sessions. | D-048, D-032, D-042; BR-USR-04; SECURITY_MODEL SEC-ACC-01, section 14 | NEW |

## 4. Shop identity and configuration

| ID | Requirement | Acceptance criteria | Source | Tests |
| --- | --- | --- | --- | --- |
| REQ-CFG-01 | The Owner maintains the BusinessProfile (shop name, logo, address, contact, optional email, receipt and document header and footer, document identity, constrained accent). Each change creates an immutable revision and moves the current pointer; the change is audited. | Each change creates a revision and an audit event; the previous revision is unchanged. | D-038, L-02; BR-CFG-01, BR-CFG-02 | IDENTITY01 |
| REQ-CFG-02 | Receipts and documents keep the identity revision in force when they were created. A logo file referenced by a document is never overwritten; referenced assets are kept. | After a profile or logo change, a reprint of an earlier Sale or session report shows the original identity. | D-038, L-17; BR-CFG-03, BR-DOC-05 | IDENTITY01 |
| REQ-CFG-03 | A logo is PNG, JPEG or WebP, at most 5 MiB and 16 megapixels, verified, re-encoded and stripped of metadata; SVG, HTML and remote URLs are refused; logos are private and served only to authorized requests. | Malformed, oversized, SVG, HTML and URL uploads are refused; metadata is removed; an unauthorized download is refused. | L-04; BR-CFG-04 | IDENTITY01, SEC01 |
| REQ-CFG-04 | The Owner configures cash registers (code, label, active) only while no session is OPEN on the register, with recent authentication and audit. Historical snapshots keep the identity they recorded. | A change is refused while a session is OPEN; earlier snapshots are unchanged after a change. | L-02; BR-CASH-02 | NEW (AUTH03 covers configuration writers) |

## 5. Catalogue

| ID | Requirement | Acceptance criteria | Source | Tests |
| --- | --- | --- | --- | --- |
| REQ-CAT-01 | The Owner and OPERATIONS_ADMIN create and edit products: SKU, name, category, optional brand, base unit, quantity precision, selling price, minimum stock, active flag. CASHIER manages no master data. | Selling price is whole rupiah and not negative; precision is 0 to 3; minimum stock is not negative; a category is required. CASHIER requests are refused. | L-P3, L-02, L-04, D-007; BR-CAT-01, BR-CAT-02, BR-CAT-09, BR-CAT-11, BR-CAT-12 | SEARCH01, SEARCH02 |
| REQ-CAT-02 | A new product starts with stock 0 and no movement. Creating master data, scanning or printing a label never posts stock. | After creation the balance is 0 and no movement exists. | L-06, L-12; BR-CAT-03 | SEARCH02 |
| REQ-CAT-03 | Product creation is search-first: exact SKU and barcode are checked and similar names shown; going ahead needs "Tetap buat produk baru". An exact SKU or barcode collision is refused, also when two creations race. | The similar-name warning appears; two concurrent creations with the same SKU give one success and one refusal. | L-12; BR-CAT-04 | SEARCH02 |
| REQ-CAT-04 | SKU and barcode tokens are text and unique across all products. Tokens keep leading zeros, case and punctuation; only the scanner terminator and outer spaces are removed; length 1 to 128 printable ASCII; a declared EAN or UPC check digit is validated. | Round trip keeps the exact token; an invalid check digit, a 129-character token and control characters are refused; a duplicate under concurrency is refused. | L-12, D-037, CORE-004; BR-CAT-05, BR-CAT-16, BR-CAT-17 | SEARCH01 |
| REQ-CAT-05 | Internal codes are generated as `BK-P-` plus 16 Crockford Base32 characters for Code 128. The `BK-` namespace is reserved. A retired token is never used again, except through the Owner's misassignment correction of a manufacturer barcode (REQ-CAT-13); `BK-P-…` codes are never reused. | Generated codes match the format; an alias starting with `BK-` is refused; a retired token cannot be added to any product except through REQ-CAT-13; a `BK-P-…` code never again. | L-12, L-02, D-051, D-052; BR-CAT-18, BR-CAT-19 | SEARCH01 |
| REQ-CAT-06 | Base unit and quantity precision cannot change after the product's first movement, including in a race with that first posting. There is no pack conversion. | An edit after the first movement is refused; the race test gives a consistent result. | L-02, L-06; BR-CAT-06, BR-CAT-07 | SEARCH02 |
| REQ-CAT-07 | One shared Product Search serves every workflow: exact barcode first, then SKU, then name; category and brand filters; server paging of 25 by default and at most 100 in a stable order; the whole list is never fetched; results never show cost; inactive products are read-only. | Matching order, paging limits and stable order verified; payloads contain no cost; inactive products cannot be posted. | L-12, L-P6; BR-CAT-23 to BR-CAT-27 | SEARCH01, SEARCH02 |
| REQ-CAT-08 | A price changes only through master data, by the Owner or OPERATIONS_ADMIN, durably and audited, with a SellingPriceRevision. There is no price override at the till. | A price change creates a revision and an audit event. The till offers no price edit; a price sent by the browser is ignored. | D-036, L-02; BR-CAT-13, BR-CAT-15 | NEW (POS02 and POS03 cover override and tampering) |
| REQ-CAT-09 | CSV import creates new products only: strict header, barcode and SKU as text, UTF-8, no formulas, preview and validation, duplicates and conflicts found before commit, unknown columns refused, size and row limits, all-or-nothing, idempotent, catalogue roles only, never stock or cost, no XLSX. The uploaded file is temporary working data: it is removed when the import reaches its final outcome and is never kept or downloadable; the audit keeps the import result (products created, row summary, file checksum, actor, time). | Each invalid case is refused before commit; a valid file creates all rows or none; a retry creates no duplicates; no movement or cost is created; CASHIER is refused; after commit, refusal or cancellation the uploaded file no longer exists, an unconfirmed upload is removed within 24 hours, and the audit holds the result with the checksum. | D-037, D-054, L-06 §Batas Later, D-013; BR-CAT-37 to BR-CAT-43; SECURITY_MODEL section 13 | NEW |
| REQ-CAT-10 | A product has at most one optional primary photo (JPG, PNG or WebP, validated), shown as a thumbnail at the till, with a placeholder when absent. Only the Owner and OPERATIONS_ADMIN upload, replace or remove it. The file of a replaced or removed photo is kept, no longer shown, and stays in backups. | Invalid type, size or dimensions are refused; a second photo replaces the first; CASHIER upload is refused; the till shows thumbnails and the placeholder; a replaced photo's file still exists and is not shown. | D-037, D-013, D-042; BR-CAT-44 to BR-CAT-49 | NEW |
| REQ-CAT-11 | Barcode labels are Code 128 with code, name and SKU, never cost, printed through the browser; a reprint uses the same code and is audited. | The label render contains no cost; a reprint is audited; physical scannability is proven at gate DEVICES. | L-12; BR-CAT-35, BR-CAT-36 | SESSION08, DEVICE01 |
| REQ-CAT-12 | The Owner and OPERATIONS_ADMIN deactivate and reactivate products and categories; an inactive product cannot be received, issued, adjusted or sold, and stays readable; an inactive category cannot be chosen for a product. Products, categories and cash registers are never deleted. | Postings and checkout of an inactive product are refused; it remains visible, read-only, in history; an inactive category is not offered for a product and its products are unchanged; no delete function exists. | L-PRD, L-12, L-06, D-042; BR-CAT-08, BR-CAT-09, BR-KEEP-06 | SEARCH02 (partial) |
| REQ-CAT-13 | Only the Owner may correct a manufacturer barcode attached to the wrong product, with a reason and recent authentication: move a token active on the wrong product to the correct one, or re-attach a token whose retirement is recorded as a misassignment and whose earlier mapping is traceable. Retiring a token records whether it was a misassignment or another reason. A token retired for another reason, and every `BK-P-…` code, stays unusable. A token is active on at most one product; the mapping history and audit are kept; earlier Sales keep their product. | OPERATIONS_ADMIN and CASHIER requests are refused; a correction without recent authentication or a reason is refused; re-attaching a token retired for another reason, or any `BK-P-…` code, is refused; after a move the token resolves to the correct product, the history shows both mappings and earlier receipts are unchanged; two racing corrections leave one active mapping. | D-051, D-052; BR-CAT-19, BR-CAT-50 to BR-CAT-52 | NEW (extends SEARCH01) |

## 6. Scanning

| ID | Requirement | Acceptance criteria | Source | Tests |
| --- | --- | --- | --- | --- |
| REQ-SCAN-01 | USB HID scanners on the PC and Bluetooth HID scanners on the tablet work as keyboard input, with manual entry as the fallback. No proprietary driver, no timing heuristic, no camera scanning. | Both scanner types work on production-intended devices (gate DEVICES); manual entry gives the same result. | L-12, D-039; BR-CAT-28 | SEARCH03, DEVICE01 |
| REQ-SCAN-02 | Each complete scan adds one base unit; empty terminators and key repeat add nothing; scans apply in scan order and a late lookup never overrides a newer one; an unknown token is kept for search; fractions need an explicit quantity edit. | Tests for repeated codes, empty CR/LF, key repeat, IME, out-of-order lookups, disconnect and partial input. | L-12; BR-CAT-29 to BR-CAT-31 | SEARCH03 |
| REQ-SCAN-03 | A scan only changes a draft. A scanner Enter never confirms a payment, a posting or a session close. Capture pauses while a dialog, review or cash count is open. | A scanner Enter on the payment screen does not submit; capture is paused while a dialog is open. | L-12, D-040; BR-CAT-32, BR-SALE-13 | SEARCH03 |
| REQ-SCAN-04 | Scanning at the till needs the user's own eligible OPEN cash session and never opens, hands over or closes one. Without a connection posting stops and nothing is queued. | Without a session, scan capture at the till is unavailable; offline, posting is refused and no change is queued. | L-12; BR-CAT-33, BR-CAT-34 | SEARCH03, SESSION03 |

## 7. Inventory

| ID | Requirement | Acceptance criteria | Source | Tests |
| --- | --- | --- | --- | --- |
| REQ-INV-01 | Every physical stock change is one immutable movement; the balance is updated in the same transaction, equals the sum of movements and is never edited directly. | Ledger sum equals balance after every command; no function edits a balance. | D-006, L-06; BR-INV-02, BR-INV-03 | INV01 |
| REQ-INV-02 | Stock is never negative. Competing requests for the last unit give one success and one insufficient-stock refusal. Lines of the same product are added together before the check. | Concurrent last-unit Sale and issue give exactly one success; duplicate lines are aggregated. | D-006, L-06; BR-INV-04, BR-INV-07 | INV02 |
| REQ-INV-03 | Every posting is atomic with its cost records (acquisition cost evidence, or an issue-cost allocation or UNKNOWN), audit and result; a failure anywhere leaves nothing. A retry with the same or a different key posts once. | Failure injection at each boundary rolls back everything; retries post once. | D-006, L-06; BR-INV-08, BR-INV-09 | INV01, INV02 |
| REQ-INV-04 | The Owner and OPERATIONS_ADMIN receive goods: existing product, quantity above zero, acquisition cost KNOWN or UNKNOWN (never stored as zero; an explicit zero needs Owner-confirmed evidence), optional reference, review, confirmation, one RECEIPT per line. Receiving is separate from opening stock and manual issue. | A missing cost is stored as UNKNOWN; nothing posts before confirmation; an explicit zero without Owner confirmation is refused. | L-06, D-033, L-15; BR-INV-12, BR-INV-13, BR-INV-16, BR-COST-04 | INV01 |
| REQ-INV-05 | OPERATIONS_ADMIN sees the purchase cost on every receiving document its role can access, may change it until confirmation and never after; after the Owner corrects or completes that cost, it sees the current cost, the Owner correction mark and the cost at confirmation, never the reason; it never sees average cost, cost of goods sold, margin, profit, inventory value or cost reports. CASHIER never sees purchase cost. | OPERATIONS_ADMIN can view and edit cost before confirmation; an edit after confirmation is refused; after an Owner correction from Rp5.000 to Rp5.500 per unit it sees Rp5.500, "Dikoreksi Pemilik" and Rp5.000, and no reason; after the Owner completes an UNKNOWN cost it sees the completed cost, the same mark and that the cost was UNKNOWN at confirmation; every channel denies OPERATIONS_ADMIN the listed cost figures. | D-033, D-047; BR-INV-14, BR-INV-15, BR-COST-07 | NEW (replaces the write-only clause of AUTH02) |
| REQ-INV-06 | The Owner and OPERATIONS_ADMIN record goods leaving for a non-sale reason (damaged, internal use, lost, sample, other); the Owner has an explicit "Barang keluar" entry. The issue reduces inventory value, never cost of goods sold, and never creates a Sale. | A missing reason is refused; insufficient stock is refused; valuation drops by the issue's allocation (the average before the issue, or the whole remaining value when stock reaches zero; UNKNOWN while the valuation is unknown) with no cost of goods sold; no Sale, Payment or receipt is created. | L-06, D-018, L-15; BR-INV-18 to BR-INV-22, BR-COST-10, BR-COST-11 | INV02, FIN01 |
| REQ-INV-07 | Only the Owner enters opening stock under the onboarding freeze: ordinary postings and Sales are refused, also in a race; at most 100 rows per command, all or nothing; once per product without earlier movement; zero counts go to the checklist without movement; the Owner reconciles before releasing the freeze. | A 101st row and any invalid row refuse the whole command; a second opening for a product is refused; the freeze blocks Sales and postings; a zero count creates no movement. | L-06; BR-INV-23 to BR-INV-29 | OPEN01 |
| REQ-INV-08 | Only the Owner adjusts stock, with signed quantity, reason, count, reference and recent authentication; a positive adjustment carries acquisition cost evidence (KNOWN or UNKNOWN); no adjustment makes stock negative. | Non-Owner requests are refused; missing reason, count or reference is refused; a negative result is refused. | L-06, L-15; BR-INV-31 to BR-INV-33 | INV03 |
| REQ-INV-09 | Only the Owner reverses one eligible OPENING, RECEIPT or MANUAL_ISSUE, once, as its exact inverse, with reason and recent authentication; the original stays; a reversal that would leave negative stock or an invalid history, or cannot be replayed safely, is refused. | The COSTING section 8 fixture gives physical quantity 5, value 50 and issue cost 50 in the corrected projection; the invalid-history fixture is refused with no movement; a second reversal is refused; SALE_ISSUE and RETURN_RECEIPT cannot be reversed. | L-06, L-15; BR-INV-34 to BR-INV-37, BR-COST-32, BR-COST-33 | INV03 |
| REQ-INV-10 | Stock attention shows OUT, LOW and NORMAL from current balance and minimum stock, in a badge and a list that agree, refreshed after changes, on focus and at least every 30 seconds; a failed refresh shows a stale or error state, never empty, zero or Normal. | With minimum 5: 6 is NORMAL, 5 and 1 are LOW, 0 is OUT; minimum 0 has no LOW; a new product with stock 0 is OUT; a failed refresh shows stale with the last refresh time. | L-06; BR-INV-40 to BR-INV-43 | STOCK01 |
| REQ-INV-11 | Stock and history are read on the server with paging. OPERATIONS_ADMIN sees no cost except the purchase cost on receiving documents; CASHIER sees only the stock needed at checkout. | Paging enforced; payloads per role match the rule. | L-06, D-033; BR-INV-44 | INV01, AUTH02 |
| REQ-INV-12 | A daily read-only reconciliation compares movements with balances and cost projections; a mismatch blocks the product's postings and raises an Owner incident; repair only by controlled rebuild from facts. | An injected mismatch blocks postings of that product and raises an incident; no direct balance edit exists. | L-06, L-09; BR-INV-45, BR-INV-46 | INV01 |
| REQ-INV-13 | Server time and posting order within a product are facts; an optional document date is stored separately and never backdates. | Ordering follows server sequence; a document date does not move a fact to another period. | L-06, L-02; BR-INV-05, BR-NUM-05 | INV01, FIN03 |

## 8. Costing

| ID | Requirement | Acceptance criteria | Source | Tests |
| --- | --- | --- | --- | --- |
| REQ-COST-01 | Cost of goods is a perpetual moving weighted average per product; the issue that empties stock takes the remaining value; the latest purchase price is never used. | The L-15 example gives sales Rp60.000, cost of goods sold Rp36.000 and gross profit Rp24.000; emptying stock leaves no residue. | L-15, D-006; BR-COST-01, BR-COST-09 to BR-COST-11 | FIN01 |
| REQ-COST-02 | Numbers are exact: whole rupiah for prices and totals with half-up rounding once per line, six-decimal cost, quantities with 0 to 3 decimals, no binary floating point anywhere including transport, out-of-range values refused. | 1.5 × Rp3.333 gives a line total of Rp5.000; money and quantities travel as decimal strings; an overflow is refused. | L-02, L-15, L-P6, D-006; BR-NUM-01 to BR-NUM-04, BR-COST-12 | FIN01 |
| REQ-COST-03 | UNKNOWN cost is distinct from zero and propagates as incomplete; allocations made while the valuation was known stay valid when unknown cost enters later; at zero stock a fully known increase starts a new known pool; incomplete figures are never shown as complete or zero. | The FIN02 cases pass. | L-15, D-033, D-034; BR-COST-03, BR-COST-13 to BR-COST-15 | FIN02 |
| REQ-COST-04 | Cost visibility: the Owner sees all; OPERATIONS_ADMIN only purchase cost on receiving documents, after an Owner correction the current cost with the mark (REQ-INV-05); CASHIER none; till screens, receipts, session reports, labels and search results never contain cost. | Channel tests per role; documents and till payloads contain no cost. | D-033, D-047; BR-COST-06 to BR-COST-08 | AUTH02 |
| REQ-COST-05 | Only the Owner corrects cost after posting, with recent authentication, as an append-only audited fact recording source, old value, new value, actor, time and reason. Completing an UNKNOWN cost follows the same rules. | OPERATIONS_ADMIN and CASHIER are refused; the correction is a new fact and the original evidence is unchanged. | D-034, D-008; BR-COST-20 to BR-COST-22 | FIN02, INV03 |
| REQ-COST-06 | A correction runs a bounded, deterministic replay from the affected source to the current state, validates the whole affected history, and publishes corrected valuation, cost of goods sold and profitability atomically; other postings of the product wait or are refused; if it cannot complete safely it is rejected with no change and the affected reporting is marked incomplete. | The same correction replayed twice gives the same result; failure injection during publication changes nothing; reaching the cap or a timeout rejects the correction and marks reporting incomplete. | D-034, L-15; BR-COST-23 to BR-COST-28 | FIN03, PERF01 |
| REQ-COST-07 | A refund without return keeps cost of goods sold and stock; a saleable return restores the cost tied to the original sale issue, for the returned quantity, once (incomplete while that cost is UNKNOWN); a damaged return restores no cost; a later correction updates the related valuation without a new return, a new movement or a double recovery. | The RET01 and FIN03 cases pass, plus a correction after a return that recovers cost exactly once. | D-034, D-035, L-15; BR-COST-17 to BR-COST-19 | RET01, FIN03 |
| REQ-COST-08 | A reversal cancels its source logically in the replay and skips the paired inverse; an old receipt is never reversed at the latest average cost. | The COSTING section 8 reversal fixture gives physical 5, value 50 and issue cost 50 in the corrected projection. | L-15; BR-COST-32, BR-COST-33 | INV03 |
| REQ-COST-09 | After a correction, Owner reports show corrected figures, also for earlier periods; receipts and session reports never change; original facts and correction history stay; earlier derived projections need not be kept. | Last month's cost of goods sold shows the corrected value; an earlier receipt and session report are unchanged; the Owner can read both the original evidence and the correction. | D-034; BR-COST-21, BR-COST-29 to BR-COST-31 | FIN03, SESSION07 |
| REQ-COST-10 | A completed Sale records each line's issue-cost allocation, or UNKNOWN, in the Sale transaction. This requirement does not depend on how the allocation is represented (D-060; ADR-008). | After commit every line has an allocation or UNKNOWN; a failure rolls back the allocation with the Sale. | D-006, D-040, L-17; BR-COST-16 | POS01, FIN01 |

## 9. Sales

| ID | Requirement | Acceptance criteria | Source | Tests |
| --- | --- | --- | --- | --- |
| REQ-SALE-01 | Checkout needs the user's own eligible OPEN cash session — for the Owner, cashiers and combined accounts, for every payment method — and is refused before any fact otherwise. OPERATIONS_ADMIN alone cannot sell. | Without a session, checkout is refused with no payment or stock fact; an OPERATIONS_ADMIN-only account is refused. | D-009, L-17, D-032; BR-SALE-02, BR-SALE-03 | POS01, SESSION03 |
| REQ-SALE-02 | Each line uses the authoritative current selling price; no discount, override or negotiated price. A price change while the cart is open makes checkout stale until reviewed; the total never changes silently; the applied price is stored. | A price change during an open cart forces review; a tampered price is refused; SaleLine and receipt keep the applied price. | D-036, L-17; BR-SALE-05 | POS02, POS03 |
| REQ-SALE-03 | A line total is quantity × unit price rounded half-up once to whole rupiah; the Sale total is the sum of line totals. | The rounding example and sums verified. | L-02; BR-SALE-06, BR-NUM-01 | FIN01, POS02 |
| REQ-SALE-04 | An ordinary Sale is confirmed on one payment screen with one final action, without a separate review dialog or checkbox; the action shows enabled, submitting, committed, rejected and uncertain states; after commit the receipt is shown; on the tablet the action has a production-usable touch target away from cart controls. | No checkbox exists; exactly one action posts; each state is visible; the receipt follows a commit with no extra step. | D-040, D-039; BR-SALE-10, BR-SALE-14, BR-SALE-15 | NEW (UX01 partial) |
| REQ-SALE-05 | The Sale commits atomically with its lines, Payment, stock issues, balances, cost allocations, receipt, audit and result, and increments the session's activity version. | Failure injection at each boundary leaves nothing; the activity version increases by one. | D-006, D-040, L-17; BR-SALE-17 | POS01 |
| REQ-SALE-06 | A double click, double tap or repeated submission, with the same or a different key, never creates a second Sale or Payment; edits are blocked while submitting. | Repeated submissions give one Sale. | D-040, D-006; BR-SALE-18 | POS01, POS03 |
| REQ-SALE-07 | A definite rejection (for example stale price, insufficient stock, inactive product, invalid or closed session, method not allowed) writes nothing and returns to an explicit review state without silently changing the total. | Each cause is tested; no fact is written; the total is unchanged until the user reviews. | D-040, L-17; BR-SALE-19 | POS03 |
| REQ-SALE-08 | An uncertain outcome keeps the original intent frozen and is resolved only through the same identity; no replacement Sale, no second charge; a committed result is recoverable after the session closes; recovery re-checks authorization. | A lost-response simulation recovers the same Sale; no replacement is created; recovery works after close. | D-040, L-17; BR-SALE-20, BR-SALE-22 | POS03 |
| REQ-SALE-09 | Goods are handed over directly: no order, booking, deposit or reservation. A customer name on the receipt is optional; no customer record is required. | No such state or route exists; a Sale without customer name is valid. | L-17, D-002; BR-SALE-01, BR-SALE-08 | POS01 |
| REQ-SALE-10 | The server computes each Sale's business date in Asia/Jakarta at commit and stores it separately from the session identity. | Two Sales at 23.59 and 00.01 Jakarta in one session get different business dates. | L-17, L-02; BR-SALE-07, BR-NUM-05 | SESSION09 |
| REQ-SALE-11 | No human, printer or network wait happens inside the transaction; printing follows commit; a printer failure never cancels or repeats a Sale. | A print failure after commit leaves the Sale intact and reprintable. | L-17, L-02; BR-SALE-21, BR-DOM-10 | POS03 |
| REQ-SALE-12 | A cashier reaches only their own Sales, history and receipts; the Owner reaches all; OPERATIONS_ADMIN has no access to the till. | Guessed identifiers of another cashier's Sales are refused. | L-17, L-04; BR-DOC-06, BR-RPT-04 | AUTH01, SESSION10 |

## 10. Payment

| ID | Requirement | Acceptance criteria | Source | Tests |
| --- | --- | --- | --- | --- |
| REQ-PAY-01 | Each Sale has exactly one full payment by CASH or BANK_TRANSFER; no split, partial, deposit, credit or mixed payment; the server refuses other methods. | Mixed, partial and unknown methods are refused, STATIC_QRIS included. | D-003, D-036, D-077; BR-PAY-01 | POS02, PAYEXT01 |
| REQ-PAY-02 | CASH: tendered at least the total, change = tendered − total, applied = total; "Uang Pas" may set tendered to the total; the final action is enabled only when tendered is valid and at least the total; change is shown before confirmation. | Total Rp75.000 with Rp100.000 gives Rp25.000 change; Rp70.000 is refused; "Uang Pas" sets tendered equal to the total. | D-040, L-17; BR-PAY-02, BR-SALE-11 | POS02 |
| REQ-PAY-03 | BANK_TRANSFER: the full total, confirmed by the final button stating that the funds were received, based on merchant evidence; optional reference; no gateway, API, webhook, callback, polling or pending state; gross amount; fees not modelled. | No pending state exists; the button text states the confirmation; the reference is optional. | D-036, D-040, D-003, D-077; BR-PAY-03, BR-PAY-05, BR-PAY-07, BR-SALE-12 | POS02, PAYEXT01 |
| REQ-PAY-04 | BANK_TRANSFER never changes expected cash; session and Owner reports show CASH and BANK_TRANSFER separately. | A session with a BANK_TRANSFER sale keeps the same expected cash; per-method totals appear separately. | D-036, D-077; BR-PAY-06, BR-CASH-12 | SESSION04 (adapted) |
| REQ-PAY-05 | Payment methods sit behind an explicit server boundary; a future provider would be additive; V1 has no placeholder provider record, controller or asynchronous status; a completed Sale never becomes pending. | Code and schema review find no provider placeholders or asynchronous payment states. | D-003, D-036, L-02; BR-PAY-07 | PAYEXT01 |

## 11. Receipts and documents

| ID | Requirement | Acceptance criteria | Source | Tests |
| --- | --- | --- | --- | --- |
| REQ-DOC-01 | Every Sale has one number and one immutable receipt snapshot with the frozen shop identity, template and asset revisions, time, cashier, session, items, total, method, tendered and change for CASH, optional customer name, and no cost or profit. | The snapshot holds the listed fields; later profile or price changes do not alter it. | L-17, D-036, D-038, D-077; BR-DOC-01 | IDENTITY01, SESSION08 |
| REQ-DOC-02 | Receipts print through browser templates for thermal 58 mm, thermal 80 mm and A4 ("Nota Penjualan") from the same snapshot; no tax is calculated and no document claims to be a tax invoice. | The three templates render from one snapshot; no tax line is computed. | D-039, L-17; BR-DOC-02, BR-DOC-03 | DEVICE01, SESSION08 |
| REQ-DOC-03 | A reprint shows the same number and content with a copy marker, creates no fact and is audited; a print dialog is not proof of ink. | A reprint creates no Sale, Payment or movement; an audit event records the request. | L-17; BR-DOC-04, BR-AUD-06 | SESSION08, POS03 |
| REQ-DOC-04 | Every document and media request is authorized again: a cashier reaches their own documents, the Owner all. | A guessed receipt number or URL is refused outside scope. | L-17, L-04; BR-DOC-06 | SESSION10, AUTH02 |
| REQ-DOC-05 | Dates are shown with the full Indonesian month name and dotted time, for example `01 Oktober 2026, 09.15.00`; stored UTC times and business dates are unaffected. | Rendering tests on screens and documents. | D-018; BR-NUM-07 | UX01 |

## 12. Cash sessions

| ID | Requirement | Acceptance criteria | Source | Tests |
| --- | --- | --- | --- | --- |
| REQ-CASH-01 | One drawer has one OPEN session with one responsible user. No shared drawer, no transfer of responsibility, no paid-in, paid-out or cash pickup; the only drawer outflow is a drawer-funded CASH refund. | No such function exists; the server refuses such requests. | D-009, D-035, L-17; BR-CASH-01, BR-CASH-07 | SESSION02 |
| REQ-CASH-02 | At most one OPEN session per cash register and per user, also when opens race. | Concurrent opens give one success. | D-009, L-17; BR-CASH-06 | SESSION02 |
| REQ-CASH-03 | A user opens a session as themselves with explicit opening cash of zero or more (Rp0 valid, blank refused), atomically, creating no Sale or movement. | A blank amount is refused; Rp0 is accepted; no Sale is created. | L-17, D-009, D-032; BR-CASH-04, BR-CASH-05 | SESSION03 |
| REQ-CASH-04 | Every completed Sale, whatever its method, is attributed once to its own session, cash register and user. | A transfer-only Sale appears once in its session report. | D-009, L-17; BR-SALE-02 | SESSION03 |
| REQ-CASH-05 | expectedCash = openingCash + applied CASH Sale payments − drawer-funded CASH refunds; variance = physical − expected; no hidden adjustment; opening and physical cash are zero or more. | Legacy example: expected Rp120.000, physical Rp118.000, variance −Rp2.000. With a drawer-funded refund of Rp10.000 added: expected Rp110.000, physical Rp108.000, variance −Rp2.000. | D-035, D-036, L-17, D-077; BR-CASH-12 | SESSION04 (adapted) |
| REQ-CASH-06 | Close uses a preview, a count outside the transaction, and an atomic close that checks the activity version under the checkout lock; a changed version refuses with "Ada transaksi baru; hitung ulang kas" and keeps the session OPEN; a failure keeps it OPEN; there is no CLOSING state. | The SESSION05 and SESSION06 cases pass. | L-17, D-009; BR-CASH-13, BR-CASH-14, BR-CASH-17 | SESSION05, SESSION06 |
| REQ-CASH-07 | In a race between a Sale and a close, either the Sale commits and the close needs a recount, or the close commits and the Sale is refused before any fact; no successful Sale is missing from its report; uncertain checkouts are resolved before close. | Both race orders tested. | L-17; BR-CASH-15, BR-CASH-16 | SESSION05 |
| REQ-CASH-08 | A repeated or lost close, with any key, gives one CLOSED session, one snapshot and one result; a conflicting count never overwrites; the result is recoverable after close. | A different-key repeat returns the original result. | L-17; BR-CASH-17 | SESSION06 |
| REQ-CASH-09 | A CLOSED session is final: no reopening, no change of responsible user; a counting mistake gets a linked Owner review note; no refund payout is accepted; later corrections never rewrite it. | No reopen function exists; a refund that selects a CLOSED session is refused; the report is unchanged after a later refund. | L-17, D-035; BR-CASH-19, BR-RFD-09 | SESSION07 |
| REQ-CASH-10 | Returning on the same business date opens a new session; a handover is A closes, then B opens; a session across midnight stays one document. | Two sessions on the same date give two distinct reports. | L-17; BR-CASH-09 | SESSION01 |
| REQ-CASH-11 | The Owner may close another user's session with current permission, a count, recent authentication and a reason; the responsible user stays; the close actor is recorded. | Without recent authentication it is refused; the snapshot shows both users. | L-17, L-04; BR-CASH-18 | SESSION10 |
| REQ-CASH-12 | The session report holds the listed contents, including CASH and BANK_TRANSFER totals separately and each drawer-funded refund separately, and never cost or profit; a cashier reaches only their own reports, the Owner all, OPERATIONS_ADMIN none; CSV export is formula-safe, keeps negative variance numeric and rupiah, time zone and session context; a failed export or print retries the same snapshot. | Report fields verified; CSV formula prefixes neutralized; access per role verified. | L-17, D-035, D-036, D-077; BR-CASH-20 to BR-CASH-22 | SESSION07, SESSION08, SESSION10 |
| REQ-CASH-13 | The activity version increases with every Sale and every drawer-funded refund of the session. | A drawer-funded refund after the close preview makes the close refuse with the recount message. | L-17, D-035; BR-CASH-10, BR-RFD-08 | NEW (extends SESSION05) |

## 13. Refunds and returns

| ID | Requirement | Acceptance criteria | Source | Tests |
| --- | --- | --- | --- | --- |
| REQ-RFD-01 | Only the Owner executes refunds and returns. | CASHIER, OPERATIONS_ADMIN and combined accounts are refused on the server. | D-035, D-032; BR-RFD-01 | RET01, AUTH01 |
| REQ-RFD-02 | A refund or return starts from the original Sale, needs a reason, recent authentication and review; money and quantity bounds are separate, cumulative and hold under races — the Sale's refunds never exceed its applied payment amount (never the tendered cash), a line's refunds never exceed its line total, a line's returns never exceed its issued quantity; a partial line refund is allowed within the line's remaining bound; one command may hold a refund, a return or both; there is no exchange engine. | The RET01 cases pass; racing requests cannot exceed either bound; a CASH Sale of Rp75.000 paid with Rp100.000 cannot be refunded more than Rp75.000 in total. | L-17, D-008, D-035; BR-RFD-02 to BR-RFD-04, BR-PAY-02 | RET01 |
| REQ-RFD-03 | The payout is recorded by CASH or BANK_TRANSFER: as made, for an Owner-funded or BANK_TRANSFER refund; for a drawer-funded refund, as the payout amount and selected session committed with the refund, the cash being taken afterwards (REQ-RFD-09). For CASH the Owner chooses the source at execution: the drawer of one explicitly selected OPEN session (it may differ from the original Sale's session) or the Owner's own funds. A BANK_TRANSFER refund does not affect a drawer. | A drawer-funded refund is linked to the selected session; an Owner-funded refund changes no session. | D-035, D-046, L-17; BR-RFD-05 to BR-RFD-07, BR-RFD-09 | NEW (replaces the Owner-funds-only clause of RET01) |
| REQ-RFD-04 | A drawer-funded refund reduces that session's expected cash, appears separately in its report with the Owner as actor, follows the session's guards and increments its activity version; a CLOSED session accepts none. | The REQ-CASH-05 example; the report line; a stale-version close is refused. | D-035; BR-RFD-08, BR-RFD-09, BR-CASH-12 | NEW (extends SESSION04, SESSION05) |
| REQ-RFD-05 | The refund payout, any saleable return to stock, any cost recovery, the session effect, audit and result commit together; the command is idempotent. | Failure injection leaves nothing; repeated submissions refund once. | D-035, L-17; BR-RFD-10, BR-RFD-12 | RET01 |
| REQ-RFD-06 | A refund without return adds no stock; a damaged or non-saleable return adds no saleable stock; a saleable return adds stock through RETURN_RECEIPT. | Each case verified against the stock balance. | D-035, L-06; BR-RFD-11, BR-INV-38, BR-INV-39 | RET01 |
| REQ-RFD-07 | The original Sale, Payment, receipt and closed session report stay unchanged; the correction is linked to the original and reported on its posting date; a drawer-funded refund is never counted twice. | The original receipt is unchanged; the refund appears on its posting date; net sales and the session reconciliation each count it once. | D-035, L-17, L-15; BR-RFD-12, BR-RFD-13, BR-COST-36 | RET01, FIN03, SESSION09 |
| REQ-RFD-08 | A drawer-funded CASH refund is allowed only up to the selected OPEN session's current expected cash (reaching exactly Rp0 is allowed); otherwise that source is refused before commit, with no override. The Owner then explicitly chooses another OPEN session with enough expected cash or the Owner's own funds; the system never switches or splits the source, and no top-up or negative-drawer mechanism exists. | With expected cash Rp80.000: a Rp100.000 drawer refund is refused with nothing written; a Rp80.000 refund leaves expected cash Rp0; two racing refunds cannot together take it below zero. | D-045, D-035; BR-RFD-15, BR-RFD-16 | NEW |
| REQ-RFD-09 | For a drawer-funded refund, cash leaves the drawer only after the refund has committed, so a refusal always comes before any cash leaves. If the outcome is uncertain, the Owner checks the status of the original refund first; no cash is taken until it is recorded, and no replacement refund is created. | A refused drawer-funded refund writes no payout and no cash effect; recovery of an uncertain refund returns the original result through its original identity and never creates a second refund; the refund workflow designed in RF-5 places the cash handover after the committed result. | D-046, D-045, D-035; BR-RFD-17, BR-RFD-14, BR-CASH-16 | NEW |

## 14. Reporting

| ID | Requirement | Acceptance criteria | Source | Tests |
| --- | --- | --- | --- | --- |
| REQ-RPT-01 | The Owner's finance report shows gross sales, refunds, net sales, number of Sales, payments by method and refund payouts separately, quantities sold and returned, cost of goods sold and gross profit; margin only when cost is complete and net sales are above zero; never net profit. | FIN03 cases; the formulas of BR-COST-35 verified. | L-15, D-036; BR-COST-34, BR-COST-35, BR-COST-41 | FIN03 |
| REQ-RPT-02 | Periods Hari Ini, 7 Hari, Bulan Ini, Bulan Lalu and Rentang Kustom are Asia/Jakarta half-open ranges; a custom range applies only when applied and cancelling keeps the previous period. | Boundary tests at Jakarta midnight; apply and cancel behaviour. | L-15, L-P3; BR-COST-37, BR-NUM-06 | FIN03 |
| REQ-RPT-03 | A report comes from one consistent snapshot with as-of time, time zone, completeness and the count of lines with unknown cost; incomplete figures show "Data biaya belum lengkap"; no line is left out to make profit look complete; a failed query is an error, never zero. | FIN02 and FIN03 cases. | L-15; BR-COST-38 | FIN02, FIN03 |
| REQ-RPT-04 | The daily summary computes totals once from source records (Sales by business date, refunds and returns by posting date), lists sessions with their variance separately, shows a session across midnight once with its full span, and never double counts. | SESSION09 cases. | L-17, L-15; BR-RPT-01 to BR-RPT-03 | SESSION09 |
| REQ-RPT-05 | Report and history access follows the role: the Owner all; OPERATIONS_ADMIN safe stock history only; CASHIER their own Sales and sessions. | OPERATIONS_ADMIN is refused session and finance reports; a cashier is refused another cashier's data. | L-04, L-17; BR-RPT-04, BR-AUD-04 | AUDIT01, AUTH02, SESSION10 |
| REQ-RPT-06 | The finance report includes a trend of its figures over the chosen period. The chart behaviour is input to RF-4 (D-018). | The trend uses the same snapshot and completeness rules as the report. | CORE-069, D-018; BR-COST-38 | FIN03 |

## 15. Audit

| ID | Requirement | Acceptance criteria | Source | Tests |
| --- | --- | --- | --- | --- |
| REQ-AUD-01 | Every successful business change is audited in its own transaction and rolls back with it; the actor's identity is kept after deactivation; every action is attributed to one user identity, also on a combined account. | A failed command leaves no audit event; a deactivated user's events still show the user. | L-04, D-006, D-032; BR-AUD-01 to BR-AUD-03, BR-USR-08 | AUDIT01 |
| REQ-AUD-02 | The Owner's audit log filters by date, user, type, product, session and reference; staff see only their scope; security audit is Owner-only; logs hold no secrets or full private payloads; failed security events go to a separate log. | AUDIT01 filters; log inspection finds no secret or full cost payload. | L-04, L-P5; BR-AUD-04, BR-AUD-05 | AUDIT01, SEC01 |

## 16. Commands and recovery

| ID | Requirement | Acceptance criteria | Source | Tests |
| --- | --- | --- | --- | --- |
| REQ-CMD-01 | A critical command carries a source intent, a key scoped to actor and operation, and a server fingerprint; the same key and payload return the original result, the same key with another payload is a conflict, and another key with the same intent never posts twice. | The three cases tested per critical command. | D-006, L-02; BR-CMD-01, BR-CMD-02 | POS01, POS03, INV01, SESSION06 |
| REQ-CMD-02 | Every critical mutation passes draft, review and explicit confirmation, except ordinary Sale checkout (one payment screen). | Corrections, refunds and returns, receiving, opening stock and session close each show a review and need an explicit confirmation. | L-P3, D-040; BR-CMD-04 | INV01, RET01, SESSION06 |
| REQ-CMD-03 | After a restore, sign-in sessions are revoked and a new recovery epoch refuses stale browser intents; a missing result is not proof that a command never committed; no blind replay. | REC02 cases. | D-006, L-02, L-09; BR-CMD-06 | REC02 |
| REQ-CMD-04 | Internal retries after a deadlock or serialization failure are bounded and reuse the same identity; external effects are never repeated. | A deadlock injection resolves with one result. | L-P6; BR-CMD-05 | POS01 |

## 17. Operations and release

| ID | Requirement | Acceptance criteria | Source | Tests |
| --- | --- | --- | --- | --- |
| REQ-OPS-01 | An independent, encrypted backup outside the server holds the database, logos, product photos and templates with a manifest and checksums; an isolated restore is verified before the pilot and regularly afterwards. The destination and its cost are question 18 (before RF-6). | A restore to a new isolated target reconciles every fact and asset. | D-005, L-09, D-037; BR-CAT-49 | REC01 |
| REQ-OPS-02 | Production data is protected as PRODUCTION_DATA_SAFETY states; schema changes are forward migrations rehearsed on a populated isolated target; the runtime database role cannot change the schema or immutable facts; migration credentials are separate. | DB01 cases. | PDS, L-09; BR-KEEP-02 | DB01 |
| REQ-OPS-03 | V1 is released only within the release boundary of PRODUCT_BLUEPRINT section 12, after a pilot that the Owner accepts. | RELEASE01 evidence. | L-P10, L-08, D-025 | RELEASE01 |
| REQ-OPS-04 | The Owner's system status shows the last backup and restore, their age, failures, disk space and reconciliation; anything unproven shows "Belum Terverifikasi". | Each state shown from real evidence. | L-09 | NEW (L-08 surface matrix) |
| REQ-OPS-05 | Business records that form the basis of bookkeeping or business and tax records are kept at least 10 years, as a minimum and not a deletion deadline. V1 has no automatic expiry, purge, archival deletion, scheduled deletion or manual deletion of business records; deleting them is a destructive operation that needs the Owner's approval of that exact operation. Master data is never deleted. This is not a claim of legal or tax compliance. | No delete, purge or archival-delete function exists for business records or master data. | D-044, D-042, L-09, PDS; BR-KEEP-01 to BR-KEEP-08 | DB01 (partial) |
| REQ-OPS-06 | Each installation has a staging environment on the same server as production, with its own database, file storage and secrets; staging holds synthetic or anonymized data only. | Staging and production share no database, storage volume or secret; staging contains no production data. | D-053, PDS §4; ADR-003 | NEW |

## 18. Non-functional requirements

| ID | Requirement | Acceptance criteria | Source | Tests |
| --- | --- | --- | --- | --- |
| REQ-NFR-01 | Indonesian is the only V1 interface language, in plain Indonesian. Every user-facing text comes from translation keys from the first Task, hard-coded copy cannot be merged, and there is no language switcher. English is a later, separate scope decision (FD-02 approved). | All interface copy is Indonesian and comes from translation keys; the build fails on a literal string in the interface or on a missing key; no language switcher exists. | D-050, D-020, AICWDF §4B; ADR-005 | NEW |
| REQ-NFR-02 | PC and tablet are primary cashier devices with functionally equivalent cashier capabilities; the phone is not; Owner and OPERATIONS_ADMIN screens follow the framework's mobile-first rule. | The full cashier workflow runs on both device classes. | D-039, FD-05 | DEVICE01, UX01 |
| REQ-NFR-03 | Real PC, tablet, USB and Bluetooth scanners, receipt and label printers and browsers pass gate DEVICES; emulation and print dialogs never count. | DEVICE01 records with device, operating system, browser, media and revision. | D-039, L-08 | DEVICE01 |
| REQ-NFR-04 | Proposed performance targets, not yet accepted: barcode lookup p95 ≤ 500 ms, product search ≤ 700 ms, 50-line Sale ≤ 2 s, ordinary period report ≤ 2 s, with two concurrent cashiers on the intended server; a provisional dataset, labelled as an assumption, is in TECHNICAL_RULES section 13 until the catalogue size is known (G2, T-09); replay capacity is measured separately (reference 10,000 movements per product); a miss is never fixed by weakening integrity, privacy or cost completeness. | PERF01 measured on a representative target. | L-P6, L-08; TECHNICAL_RULES section 13 | PERF01 |
| REQ-NFR-05 | Recovery point ≤ 6 hours and recovery time ≤ 4 hours are proposals only; measured values must be accepted by the Owner at gate G4 (question 18). | Measured and accepted values recorded. | L-09, OPEN_ITEMS gate G4 | REC01, REC02 |
| REQ-NFR-06 | Additional recurring production cost is zero unless the Owner approves an exception; the independent backup is required regardless. | The cost inventory shows zero or approved exceptions. | COST_POLICY, AICWDF §4C | NEW |
| REQ-NFR-07 | The security model of SECURITY_MODEL applies, including the upload rules for logos, product photos and CSV files and protection against command injection (threat THR-10; OPEN_ITEMS T-04 resolved); no public sign-up, two-factor authentication or tenant scope in V1. | SEC01 cases and the security tests of SECURITY_MODEL section 15. | D-013, D-037, D-049, D-054; SECURITY_MODEL | SEC01 |
| REQ-NFR-08 | Data integrity (AGENTS rule 7): no floating point, append-only facts, one source of truth, transactional critical actions, explicit concurrency and idempotency, all proven on real PostgreSQL. | Covered by the inventory, costing, sales and cash requirements, tested on real PostgreSQL. | AGENTS rule 7, D-006; BR-DOM-02 to BR-DOM-05 | INV01, POS01 |
| REQ-NFR-09 | Facts carry server UTC time; business dates and report periods use Asia/Jakarta. | Covered by REQ-SALE-10 and REQ-RPT-02. | L-02, L-15; BR-NUM-05, BR-NUM-06 | FIN03, SESSION09 |
| REQ-NFR-10 | V1 calculates no tax and claims no equivalence with a tax invoice. The PKP/PPN status of the first store is UNKNOWN (gate G1). | No tax computation exists; documents carry no tax-invoice wording. | D-039 | NEW |

## 19. User interface

| ID | Requirement | Acceptance criteria | Source | Tests |
| --- | --- | --- | --- | --- |
| REQ-UX-01 | Every user-facing surface implements the artifacts the Owner approved at gates P7-G1 (navigation and page structure), P7-G2 (terminology and copy) and P7-G3 (visual direction and key screens). Nothing from the former design candidate counts as approved. | Each surface traces to an approved artifact recorded in DECISIONS. | D-024, D-025, D-017, D-010 | UX01, IDENTITY01 |
| REQ-UX-02 | Every surface has verifiable loading, empty, error, denied, stale, unknown-cost, uncertain, disabled, focus and success states; no visible control is without behaviour; nothing outside V1 looks active. | State checks per surface; zero dead interactions (AICWDF §25). | L-08 UX01, AICWDF §14.3; BR-DOM-08 | UX01 |
| REQ-UX-03 | The rebuilt design is validated for keyboard-only use with visible focus, 200 % browser zoom, screen reader, on-screen keyboard, long content and full rendered contrast, and for touch targets usable in production on the tablet. The exact targets are set in [ADAPTIVE_AND_ACCESSIBILITY](../07-ux-design/ADAPTIVE_AND_ACCESSIBILITY.md) (RF-4A, D-070). | Each check recorded on the approved design and on the build. | OPEN_ITEMS section 3, D-039, D-040, L-08 UX01 | UX01 |

## 20. Requirements shaped by decisions taken during RF-2 and before RF-3A

| Decision | Question | Requirements |
| --- | --- | --- |
| D-042 — no deletion of master data and configuration; deactivate and reactivate only | 21 | REQ-ACC-10, REQ-CAT-10, REQ-CAT-12, REQ-OPS-05 |
| D-043 — Owner may anonymize a deactivated account without rewriting history | 22 | REQ-ACC-11 |
| D-044 — business records kept at least 10 years; no deletion feature in V1 | 23 | REQ-OPS-05 |
| D-045 — a drawer-funded refund never takes expected cash below zero | 24 | REQ-RFD-08 |
| D-046 — cash leaves the drawer for a drawer-funded refund only after the refund has committed | 27 | REQ-RFD-03, REQ-RFD-09 |
| D-047 — after an Owner cost correction, OPERATIONS_ADMIN sees the current cost with the Owner correction mark and the cost at confirmation | 28 | REQ-INV-05, REQ-COST-04 |
| D-048 — exactly one active Owner account; Owner recovery and replacement by operator procedure | 25 | REQ-ACC-12, REQ-ACC-05 |
| D-049 — sign-in profile: email and password, no email sent, no Google, Owner-issued one-time links, operator recovery, AICWDF-COMPAT-8, no two-factor authentication | 6 | REQ-ACC-05, REQ-ACC-11 |
| D-050 — translation keys from the first Task; V1 Indonesian only, no language switcher | 5 | REQ-NFR-01 |
| D-051 — the Owner moves a misassigned manufacturer barcode to the correct product | 26 | REQ-CAT-05, REQ-CAT-13, REQ-ACC-08 |
| D-052 — a retired manufacturer barcode is re-attached only as the Owner's verified misassignment correction | 31 | REQ-CAT-05, REQ-CAT-13 |
| D-053 — hosting topology: Cloudflare, VPS, Coolify; staging on the same server with separate database, storage and secrets | 7 | REQ-OPS-06 |
| D-054 — uploaded CSV import files are not kept after the command's final outcome | 30 | REQ-CAT-09 |

## 21. Legacy CORE coverage

Every legacy Core entry CORE-001 to CORE-073 (`core.json`, `CORE-MAP.md`) is mapped to the requirements that now carry it, or retired with a reason. "Adapted" means a later decision changed part of the legacy text; the requirement carries the current rule.

| CORE | Current requirements | Status |
| --- | --- | --- |
| CORE-001 | REQ-ACC-03, REQ-ACC-05, REQ-ACC-06 | Mapped |
| CORE-002 | REQ-ACC-04, REQ-COST-04 | Adapted — the write-only cost clause is superseded by D-033 |
| CORE-003 | REQ-ACC-06, REQ-ACC-08, REQ-ACC-09 | Mapped |
| CORE-004 | REQ-CAT-04, REQ-CAT-05, REQ-CAT-13 | Adapted — a misassigned manufacturer barcode may be corrected by the Owner (D-051, D-052) |
| CORE-005 | REQ-CAT-02, REQ-CAT-03, REQ-CAT-06, REQ-CAT-07 | Mapped |
| CORE-006 | REQ-SCAN-02, REQ-SCAN-03, REQ-SCAN-04 | Mapped |
| CORE-007 | REQ-INV-01, REQ-INV-03, REQ-INV-11, REQ-INV-12, REQ-INV-13 | Mapped |
| CORE-008 | REQ-INV-02, REQ-INV-03, REQ-INV-06 | Mapped |
| CORE-009 | REQ-INV-08, REQ-INV-09, REQ-ACC-08 | Mapped |
| CORE-010 | REQ-INV-07 | Mapped |
| CORE-011 | REQ-SALE-01, REQ-SALE-05, REQ-SALE-06, REQ-COST-10 | Mapped |
| CORE-012 | REQ-PAY-02, REQ-PAY-03, REQ-SALE-02, REQ-CAT-08 | Mapped — STATIC_QRIS, added by D-036, was removed again by D-077 |
| CORE-013 | REQ-SALE-02, REQ-SALE-07, REQ-SALE-08, REQ-SALE-11, REQ-DOC-03 | Mapped |
| CORE-014 | REQ-RFD-01, REQ-RFD-02, REQ-RFD-03, REQ-RFD-05, REQ-RFD-06, REQ-COST-07 | Adapted — refunds may be drawer-funded (D-035) |
| CORE-015 | REQ-INV-10, REQ-CAT-01 | Mapped |
| CORE-016 | REQ-COST-01, REQ-COST-02, REQ-SALE-03 | Mapped |
| CORE-017 | REQ-COST-03, REQ-COST-05 | Mapped |
| CORE-018 | REQ-COST-06, REQ-COST-07, REQ-RPT-02, REQ-RPT-03, REQ-RFD-07 | Adapted — earlier derived projections need not be kept (D-034) |
| CORE-019 | REQ-CASH-10 | Mapped |
| CORE-020 | REQ-CASH-02 | Mapped |
| CORE-021 | REQ-CASH-03, REQ-CASH-04, REQ-SALE-01 | Mapped |
| CORE-022 | REQ-CASH-05, REQ-PAY-04 | Adapted — drawer-funded refunds (D-035); the STATIC_QRIS addition of D-036 was removed by D-077 |
| CORE-023 | REQ-CASH-06, REQ-CASH-07 | Mapped |
| CORE-024 | REQ-CASH-06, REQ-CASH-08 | Mapped |
| CORE-025 | REQ-CASH-09, REQ-CASH-12, REQ-COST-09 | Mapped |
| CORE-026 | REQ-DOC-03, REQ-CASH-12 | Mapped |
| CORE-027 | REQ-RPT-04, REQ-SALE-10 | Mapped |
| CORE-028 | REQ-CASH-11, REQ-CASH-12, REQ-DOC-04, REQ-SALE-12, REQ-RPT-05 | Mapped |
| CORE-029 | REQ-AUD-01, REQ-AUD-02, REQ-RPT-05 | Mapped |
| CORE-030 | REQ-CFG-01, REQ-CFG-02, REQ-CFG-03, REQ-SCOPE-03 | Mapped |
| CORE-031 | REQ-OPS-02, REQ-OPS-05 | Mapped |
| CORE-032 | REQ-OPS-01 | Mapped |
| CORE-033 | REQ-CMD-03, REQ-NFR-05 | Mapped |
| CORE-034 | REQ-UX-01, REQ-UX-02, REQ-UX-03, REQ-SCOPE-02, REQ-DOC-05 | Adapted — approval through gates P7-G1 to P7-G3 (D-024, D-025); the five legacy viewport widths are input to RF-4 |
| CORE-035 | REQ-SCAN-01, REQ-NFR-03, REQ-DOC-02 | Mapped |
| CORE-036 | REQ-NFR-04 | Mapped — targets remain proposals; dataset waits for G2 (T-09) |
| CORE-037 | REQ-OPS-03 | Mapped |
| CORE-038 | REQ-NFR-07, REQ-ACC-05 | Mapped |
| CORE-039 | REQ-PAY-01, REQ-PAY-05 | Mapped — STATIC_QRIS, added by D-036, was removed again by D-077 |
| CORE-040 | REQ-SCOPE-01, REQ-SCOPE-02, REQ-SALE-09 | Mapped |
| CORE-041 | REQ-ACC-01, REQ-ACC-03 | Mapped |
| CORE-042 | REQ-CAT-01, REQ-CAT-02, REQ-CAT-07 | Mapped |
| CORE-043 | REQ-INV-04 | Mapped |
| CORE-044 | REQ-INV-06 | Mapped |
| CORE-045 | REQ-INV-07 | Mapped |
| CORE-046 | REQ-INV-10 | Mapped |
| CORE-047 | REQ-INV-01, REQ-INV-02, REQ-INV-03, REQ-NFR-08 | Mapped |
| CORE-048 | REQ-COST-01, REQ-COST-02, REQ-COST-03 | Mapped |
| CORE-049 | REQ-COST-04, REQ-RPT-01, REQ-RPT-03, REQ-RPT-05 | Mapped |
| CORE-050 | — | **Retired.** The write-only rule for cost entered by OPERATIONS_ADMIN is superseded by D-033. The current rule is REQ-INV-05 and REQ-COST-04. |
| CORE-051 | REQ-CASH-01, REQ-CASH-02, REQ-CFG-04 | Mapped |
| CORE-052 | REQ-CASH-03, REQ-CASH-10 | Mapped |
| CORE-053 | REQ-PAY-01 | Mapped — STATIC_QRIS, added by D-036, was removed again by D-077 |
| CORE-054 | REQ-PAY-03 | Mapped |
| CORE-055 | REQ-PAY-03, REQ-PAY-05 | Mapped — no gateway, callback, webhook or asynchronous state; STATIC_QRIS, added by D-036, was removed again by D-077 |
| CORE-056 | REQ-SALE-05, REQ-COST-10, REQ-NFR-08 | Mapped |
| CORE-057 | REQ-CMD-01, REQ-SALE-06, REQ-SALE-08, REQ-CASH-08 | Mapped |
| CORE-058 | REQ-CASH-06, REQ-CASH-07 | Mapped |
| CORE-059 | REQ-CASH-05, REQ-PAY-04 | Adapted — drawer-funded refunds reduce expected cash (D-035) |
| CORE-060 | REQ-RFD-02, REQ-COST-07 | Mapped |
| CORE-061 | REQ-INV-09, REQ-COST-05, REQ-COST-06, REQ-COST-08 | Mapped |
| CORE-062 | REQ-DOC-01, REQ-DOC-02, REQ-DOC-03, REQ-CASH-12, REQ-CAT-11 | Mapped |
| CORE-063 | REQ-CFG-01, REQ-CFG-02, REQ-SCOPE-03 | Mapped |
| CORE-064 | REQ-AUD-01, REQ-AUD-02, REQ-ACC-04 | Mapped |
| CORE-065 | REQ-OPS-02 | Mapped |
| CORE-066 | REQ-OPS-01, REQ-CMD-03 | Mapped |
| CORE-067 | REQ-SCAN-01, REQ-SCAN-03, REQ-SALE-04, REQ-NFR-02, REQ-UX-03 | Adapted — the cashier devices are PC and tablet, not the phone (D-039) |
| CORE-068 | REQ-NFR-07, REQ-ACC-04, REQ-AUD-02 | Mapped |
| CORE-069 | REQ-RPT-01, REQ-RPT-02, REQ-RPT-03, REQ-RPT-06 | Mapped |
| CORE-070 | REQ-UX-01 | Adapted — the single exact-revision approval is replaced by gates P7-G1 to P7-G3 (D-024, D-025) |
| CORE-071 | REQ-NFR-03, REQ-SCAN-01, REQ-CAT-11 | Mapped |
| CORE-072 | REQ-NFR-04, REQ-COST-06 | Mapped |
| CORE-073 | REQ-OPS-03 | Mapped |

## 22. Decision traceability

Every decision D-001 to D-084 is either traced to at least one requirement or rule, or classified as having no product or domain content.

| Decision | Product or domain content | Traced to |
| --- | --- | --- |
| D-001 | Only the prohibition on MULTIPLECORP material remains in force; it governs agents, not the product | Non-product/domain — governance (AGENTS rule 5) |
| D-002 | V1 minimum scope, as amended | REQ-SCOPE-01, REQ-SCOPE-02, REQ-ACC-01, PRODUCT_BLUEPRINT sections 7 and 8; BR-DOM-01 |
| D-003 | One full payment per Sale; no gateway | REQ-PAY-01, REQ-PAY-03, REQ-PAY-05; BR-PAY-01, BR-PAY-07 |
| D-004 | Stack and modular monolith | Non-product/domain — architecture, recorded as ADR-001 (RF-3A); noted in PRODUCT_BLUEPRINT as a constraint by reference to DECISIONS |
| D-005 | Infrastructure; independent backup mandatory | REQ-OPS-01; the rest is architecture, recorded as ADR-002 (RF-3A) |
| D-006 | Integrity: immutable ledger, atomic Sale, exact cost, command identity, ordered guards | REQ-INV-01 to REQ-INV-03, REQ-SALE-05, REQ-SALE-06, REQ-CMD-01, REQ-COST-02, REQ-NFR-08; BR-DOM-02 to BR-DOM-05, BR-CMD-01, BR-CMD-02 |
| D-007 | Server authorization and per-role allowlists; CASHIER manages no master data (write-only cost superseded) | REQ-ACC-03, REQ-ACC-04, REQ-CAT-01; BR-DOM-07, BR-CAT-02 |
| D-008 | Corrections: originals kept, separate money and quantity bounds, cost restored once, Owner reason and recent authentication, bounded replay | REQ-RFD-02, REQ-COST-05 to REQ-COST-07, REQ-INV-08, REQ-INV-09, REQ-ACC-08; BR-RFD-03, BR-COST-20 |
| D-009 | Cash accountability, as amended by D-035 | REQ-CASH-01 to REQ-CASH-11, REQ-SALE-01; BR-CASH-01 to BR-CASH-19 |
| D-010 | Runtime client identity (pipeline adapted by D-024 and D-025) | REQ-SCOPE-03, REQ-CFG-01, REQ-UX-01; BR-DOM-11 |
| D-011 | Agent models (superseded by D-031) | Non-product/domain — agent workflow |
| D-012 | Build Units (not executable) | Non-product/domain — delivery planning (RF-7) |
| D-013 | Security support and sign-in session timeouts | REQ-ACC-07, REQ-ACC-08, REQ-CFG-03, REQ-NFR-07; BR-USR-06, BR-USR-07 |
| D-014 | Review before freeze; P7 before P8 to P11 | Non-product/domain — governance |
| D-015 | One local planning commit (consumed) | Non-product/domain — governance |
| D-016 | Preservation of earlier material | Non-product/domain — governance |
| D-017 | P7 is real design work | REQ-UX-01 (approval form replaced by D-025); the rest is governance |
| D-018 | Date format, Owner "Barang keluar" entry, trend chart as RF-4 input; example photos superseded by D-037 | REQ-DOC-05, REQ-INV-06, REQ-RPT-06; BR-NUM-07, BR-INV-18 |
| D-019 | Repository restructure (FD-01) | Non-product/domain — governance |
| D-020 | Repository documents in English; Indonesian is the primary interface language | REQ-NFR-01 |
| D-021 | Who applies re-foundation changes | Non-product/domain — governance |
| D-022 | Housekeeping (patch, stash, remote, no push) | Non-product/domain — governance |
| D-023 | Agent models (superseded by D-031) | Non-product/domain — agent workflow |
| D-024 | P7 rebuilt from information architecture, tasks and plain copy | REQ-UX-01 |
| D-025 | Three P7 approval gates | REQ-UX-01, REQ-OPS-03 |
| D-026 | RF-1 authorization | Non-product/domain — governance |
| D-027 | RF-1 safety bundle; accepted risk | Non-product/domain — governance |
| D-028 | RF-1 link fix | Non-product/domain — governance |
| D-029 | RF-1 Part B authorization | Non-product/domain — governance |
| D-030 | RF-1 acceptance | Non-product/domain — governance |
| D-031 | Agent model, effort and handoff convention | Non-product/domain — agent workflow |
| D-032 | CASHIER and OPERATIONS_ADMIN on one account | REQ-ACC-02, REQ-SALE-01, REQ-RFD-01, REQ-AUD-01; BR-USR-02, BR-USR-08 |
| D-033 | Purchase cost and OPERATIONS_ADMIN; UNKNOWN never zero | REQ-INV-04, REQ-INV-05, REQ-COST-03, REQ-COST-04; BR-INV-14, BR-INV-15, BR-COST-03, BR-COST-07 |
| D-034 | Owner-only cost correction with bounded replay; originals and history retained | REQ-COST-05 to REQ-COST-10; BR-COST-16 to BR-COST-31; COSTING section 10 (T-16, resolved by D-060 and D-061) |
| D-035 | Refunds and returns: Owner only, drawer or Owner funds, expected cash, activity version | REQ-RFD-01 to REQ-RFD-07, REQ-CASH-05, REQ-CASH-09, REQ-CASH-13; BR-RFD-01 to BR-RFD-13, BR-CASH-12 |
| D-036 | CASH, BANK_TRANSFER, STATIC_QRIS (STATIC_QRIS removed again by D-077); no discount or price change at the till | REQ-PAY-01 to REQ-PAY-05, REQ-SALE-02, REQ-CAT-08, REQ-RPT-01; BR-PAY-01 to BR-PAY-07, BR-SALE-05, BR-CAT-13 |
| D-037 | CSV import of new products; one optional photo | REQ-CAT-09, REQ-CAT-10, REQ-OPS-01, REQ-NFR-07; BR-CAT-37 to BR-CAT-49 |
| D-038 | One business per installation; client-neutral; BusinessProfile revisions | REQ-SCOPE-01, REQ-SCOPE-03, REQ-CFG-01, REQ-CFG-02; BR-DOM-01, BR-CFG-01 to BR-CFG-05 |
| D-039 | PC and tablet; print templates; no tax; sign-in timeouts | REQ-NFR-02, REQ-NFR-03, REQ-NFR-10, REQ-ACC-07, REQ-DOC-02, REQ-SCAN-01; BR-USR-06, BR-DOC-02, BR-DOC-03 |
| D-040 | One payment screen with one final action | REQ-SALE-04 to REQ-SALE-08, REQ-PAY-02, REQ-PAY-03, REQ-SCAN-03, REQ-CMD-02; BR-SALE-10 to BR-SALE-20, BR-CMD-04 |
| D-041 | RF-2 authorization, including the D-031 workflow clarification | Non-product/domain — governance |
| D-042 | No deletion of master data and configuration; deactivate and reactivate only; files of replaced photos kept | REQ-ACC-10, REQ-CAT-10, REQ-CAT-12, REQ-OPS-05; BR-USR-10, BR-CAT-08, BR-CAT-09, BR-CAT-46, BR-CFG-02, BR-KEEP-06; DOMAIN_MODEL section 11.1 |
| D-043 | Owner-only anonymization of a deactivated account; history never rewritten | REQ-ACC-11; BR-USR-11, BR-USR-12; DOMAIN_MODEL section 11 |
| D-044 | Business records kept at least 10 years; no deletion feature in V1; other data classes not decided | REQ-OPS-05; BR-KEEP-03 to BR-KEEP-08 |
| D-045 | A drawer-funded refund never takes expected cash below zero; one explicit funding source | REQ-RFD-08; BR-RFD-06, BR-RFD-15, BR-RFD-16, BR-CASH-07 |
| D-046 | Cash leaves the drawer for a drawer-funded refund only after commit; uncertain outcomes are checked first | REQ-RFD-03, REQ-RFD-09; BR-RFD-05, BR-RFD-07, BR-RFD-14, BR-RFD-17, BR-CASH-07, BR-CASH-16 |
| D-047 | What OPERATIONS_ADMIN sees on a receiving document after an Owner cost correction or completion | REQ-INV-05, REQ-COST-04; BR-INV-15, BR-INV-44, BR-COST-07; DOMAIN_MODEL section 11.2 |
| D-048 | Exactly one active Owner account; operator recovery and replacement | REQ-ACC-12, REQ-ACC-05; BR-USR-03, BR-USR-04 |
| D-049 | Sign-in profile | REQ-ACC-05, REQ-ACC-11; BR-USR-03, BR-USR-11 |
| D-050 | Translation keys from the first Task; V1 Indonesian only | REQ-NFR-01 |
| D-051 | The Owner moves a misassigned manufacturer barcode | REQ-CAT-05, REQ-CAT-13, REQ-ACC-08; BR-CAT-19, BR-CAT-50, BR-USR-07 |
| D-052 | A retired manufacturer barcode is re-attached only as a verified misassignment correction | REQ-CAT-05, REQ-CAT-13; BR-CAT-19, BR-CAT-51, BR-CAT-52 |
| D-053 | Hosting topology; staging separate from production on the same server | REQ-OPS-06; the rest is architecture, recorded as ADR-003 |
| D-054 | Uploaded CSV import files are not kept after the command's final outcome | REQ-CAT-09; BR-CAT-40, BR-KEEP-07 |
| D-055 | RF-3A authorization, including the acceptance of RF-2 and plan decisions P-1 to P-5 | Non-product/domain — governance |
| D-056 | Residual risk of Owner-issued one-time links accepted for V1; no other authentication channel | REQ-ACC-05 (the controls are unchanged); SECURITY_MODEL THR-21 |
| D-057 | Second review of the RF-3A report; scope amendment for the localization wording of AGENTS and PROJECT_RULES | Non-product/domain — governance |
| D-058 | Acceptance of the RF-3A checkpoint; final second review PASS; process note on D-031 | Non-product/domain — governance |
| D-059 | RF-3B authorization, including plan decisions P-6 to P-9 | Non-product/domain — governance |
| D-060 | Issue-cost allocation at commit is an immutable fact; replay results are a separate, replaceable projection used by reports | REQ-COST-07, REQ-COST-09, REQ-COST-10; BR-COST-14, BR-COST-16; COSTING section 10; ADR-008 |
| D-061 | A saleable return is linked to the original issue allocation and recomputed with it; cost restored exactly once | REQ-COST-07; BR-COST-17, BR-COST-18, BR-RFD-11; ADR-008 |
| D-062 | Sale and cash session numbers: unique, allocated in increasing order, gaps allowed, with the Asia/Jakarta business date | REQ-DOC-01; BR-DOC-01, BR-CASH-04; SCHEMA section 6 |
| D-063 | Second review of RF-3B: when the "reporting incomplete" mark is written (D-034); corrections and reversals refused on a reconciliation-blocked product; the number's date part is not a sort key; question 35 deferred | REQ-COST-06, REQ-INV-12, REQ-DOC-01; BR-COST-27, BR-INV-45, BR-INV-46, BR-DOC-01; SCHEMA sections 5.8, 6 and 8 |
| D-064 | Acceptance of the RF-3B checkpoint; two documentation notes; Git-based file searches in checkpoint instructions | Non-product/domain — governance |
| D-065 | Repository visibility (private); safety copies and their policy; the bookkeeping change | Non-product/domain — governance |
| D-066 | Acceptance of the bookkeeping checkpoint; the executor runs Claude Opus 5.5 at effort Max | Non-product/domain — governance |
| D-067 | One Sale line per product (question 35): the cart combines repeated additions; the server refuses a request that names a product twice | REQ-SALE-03, REQ-SALE-05, REQ-DOC-01, REQ-RFD-02, REQ-INV-02; BR-SALE-06, BR-SALE-16, BR-INV-07, BR-RFD-03 |
| D-068 | Refund cash still in the drawer at close (question 29): variance kept, linked Owner review note, handover before the next opening count | REQ-CASH-09, REQ-RFD-04, REQ-RFD-09; BR-CASH-16, BR-CASH-19, BR-RFD-09, BR-RFD-17 |
| D-069 | T-17 a, d, f and h: receiving drafts in page memory; deactivation of a staff account that holds an OPEN session; append-only review notes; CSV category names refused when missing or ambiguous | REQ-INV-04, REQ-ACC-06, REQ-ACC-10, REQ-CASH-09, REQ-CASH-11, REQ-CAT-09; BR-INV-17, BR-CASH-11, BR-CASH-18, BR-CASH-19, BR-CAT-38, BR-CAT-42 |
| D-070 | RF-4A authorization, including plan decisions P-10 to P-15 | Non-product/domain — governance |
| D-071 | At most one initial stock onboarding period per business-data lifecycle (question 36, finding F-03): no new onboarding after the first release; later corrections by adjustment, reversal or cost correction | REQ-INV-07, REQ-INV-08, REQ-INV-09, REQ-COST-05; BR-INV-16, BR-INV-24, BR-INV-29, BR-INV-31, BR-INV-34, BR-COST-20 |
| D-072 | Onboarding and a restore of earlier business data (question 37): a released onboarding is not reopened or continued by restoring an older backup; a period never released may continue as the same original period; an unverifiable state is held until the Owner's restore reconciliation | REQ-INV-07, REQ-CMD-03; BR-INV-24, BR-CMD-06 |
| D-073 | Server-authoritative recovery of a request refused as EPOCH_STALE after a restore: the status check stays available; a reference is released only by a verified outcome or an audited reconciliation with a safe fence; the re-application of SEC-OP-04 stays possible | REQ-CMD-01, REQ-CMD-03, REQ-SALE-08, REQ-UX-02; BR-CMD-03, BR-CMD-06; SECURITY_MODEL SEC-OP-04 |
| D-074 | Duplicate actions across devices, browsers or cleared browser data: not an accepted risk; RF-5 proposes the backend mitigation and its tests | REQ-SALE-08, REQ-CMD-01, REQ-RFD-09; BR-SALE-20, BR-CMD-03 |
| D-075 | Remediation of the RF-4A independent final review; the planning agent's G1 proposals accepted: the combined account's Stok home, no role switcher, "Tunai" preselected, provisional shortcuts and breakpoints | REQ-UX-03, REQ-SALE-04; the rest is governance |
| D-076 | Repository visibility (public); the publication guard before any push; RF-4A extended to record it | Non-product/domain — governance |
| D-077 | V1 payment methods CASH and BANK_TRANSFER only (the Owner's decision Q38): STATIC_QRIS removed from V1; goods only; one full payment per Sale; a future method needs a separate Owner decision | REQ-PAY-01, REQ-PAY-03, REQ-PAY-04, REQ-DOC-01, REQ-CASH-05, REQ-CASH-12; BR-PAY-01, BR-PAY-03, BR-PAY-04 (retired), BR-PAY-06, BR-SALE-02, BR-SALE-10, BR-SALE-12, BR-DOC-01, BR-CASH-12, BR-CASH-20, BR-RFD-05, BR-COST-34 |
| D-078 | Acceptance of the RF-4A checkpoint `c8dc234` and of its SSD safety bundle | Non-product/domain — governance |
| D-079 | Authorization of the Q38 canonical amendment | Non-product/domain — governance |
| D-080 | Acceptance of the Q38 checkpoint `d4c0594` and of its SSD safety bundle | Non-product/domain — governance |
| D-081 | Acceptance of the D-080 bookkeeping commit `cdb8361`; the wording issue R-2 closed administratively | Non-product/domain — governance |
| D-082 | Approval of gate P7-G1, navigation and page structure: the six RF-4A documents at checkpoint `d4c0594`, each named by its hash | REQ-UX-01 |
| D-083 | Authorization of RF-4B: the glossary's shop terms finalized and the copy deck | Non-product/domain — governance |
| D-084 | Acceptance of the RF-4B checkpoint `c4bf8e9` and authorization of one correction of the copy deck: a held request stays held when a status check gets no answer | Non-product/domain — governance |

## 23. Counts

Re-derived from this register: 23 sections; 134 requirement rows in sections 2 to 19, all with distinct identifiers; no requirement pending; 73 legacy CORE entries, of which 72 are mapped (9 of them adapted by later decisions) and 1 is retired (section 21); 84 decisions, of which 51 are traced to requirements or rules and 33 are classified as non-product/domain (section 22).
