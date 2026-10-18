# Acceptance suites

| | |
| --- | --- |
| Status | CURRENT — written in RF-6 under D-088, 18 October 2026 (Asia/Jakarta); corrected under D-089, 18 October 2026: the settlement cases of SESSION05, CMD01 and REC02 and the decryption case of REC01 (findings B-2 and B-1 of the RF-6 review) |
| Phase | P8 — the acceptance suites, their cases and their traceability to requirements, races, guards, threats, workflows and operator procedures |
| Replaces | The suite matrix, the surface matrix and the performance dataset section of `docs/08-testing/legacy/08-TESTING-ACCEPTANCE.md` (LEGACY — SUPERSEDED, [LEGACY_INDEX](../00-governance/LEGACY_INDEX.md) section 15) |
| Decisions | D-024 to D-089 as cited; FD-07 |
| Related | [TEST_STRATEGY](TEST_STRATEGY.md), [DEVICE_VERIFICATION](DEVICE_VERIFICATION.md), [REQUIREMENTS](../01-product/REQUIREMENTS.md), [CRITICAL_FLOWS](../03-workflows/CRITICAL_FLOWS.md), [TECHNICAL_RULES](../06-api-performance/TECHNICAL_RULES.md) section 8, [SCHEMA](../04-architecture/SCHEMA.md) sections 8 and 9, [SECURITY_MODEL](../05-security/SECURITY_MODEL.md) sections 10 and 15 |

What must be proven before V1 is accepted. Every suite has an identifier, the requirements it accepts, the races, guards and threats it proves, its levels (TEST_STRATEGY section 2), its final point (TEST_STRATEGY section 12) and its required cases. Status today: every suite is `NOT_RUN`; nothing is built.

## 1. How to read

- **Identifiers** are permanent. The 39 suites of the legacy draft keep their identifiers and are rebuilt here to the current rules; 17 suites are new. A legacy suite text that a later decision changed has no force: section 2 lists what changed.
- **Cases** name the rule or race they prove. The required outcome of a race is TECHNICAL_RULES section 8; the meaning of a guard is SCHEMA section 8; the negative proof of a threat is SECURITY_MODEL section 10; the acceptance criteria of a requirement are REQUIREMENTS. A case is never weaker than its source.
- **Test families** (TEST_STRATEGY section 5) apply to every table, constraint, privilege and command a suite touches, in addition to its listed cases.
- **Evidence** follows TEST_STRATEGY section 8; nothing here says that any case passed.

## 2. The legacy suites rebuilt

| Legacy suite | What changed in the rebuild | Decision |
| --- | --- | --- |
| AUTH02 | The write-only purchase cost of OPERATIONS_ADMIN is replaced by its visibility on receiving documents and the Owner correction mark | D-033, D-047 |
| RET01, SESSION04 | Refunds are no longer only from the Owner's own funds; drawer-funded refunds enter expected cash; their own cases are RET02 | D-035, D-045, D-046 |
| UX01 | The former design revision and its approval are replaced by the approved P7 artifacts | D-024, D-025, D-082, D-086, D-087 |
| SEARCH01 | A retired manufacturer barcode may be moved or re-attached by the Owner's misassignment correction (BARCODE01) | D-051, D-052 |
| OPEN01 | Onboarding starts and is released at most once per business-data lifecycle, also across a restore; corrections before the release | D-071, D-072, D-087 |
| POS01 | One Sale line per product | D-067 |
| SESSION05 | A close is refused while the responsible user has an unresolved checkout request (G-27, RACE-35); a conclusive status check settles every receipt of the intent (ADR-009 section 1) | D-088, D-089 |
| REC01, REC02 | The backup method, key custody and the reconciliation of a restore | D-072, D-073, D-088; ADR-009, ADR-010 |
| RELEASE01 | The release is operated by a person, never an agent | D-088 |
| POS02, PAYEXT01 | CASH and BANK_TRANSFER only, as in the legacy text | D-077 |

## 3. Catalogue

Levels: L1 unit, L2 database, L3 concurrency, L4 HTTP, L5 browser, L6 operations, L7 performance, L8 devices, L9 UAT and pilot.

| ID | Title | Requirements | Races, guards and threats | Levels | Final point |
| --- | --- | --- | --- | --- | --- |
| AUTH01 | Server authorization and object scope | REQ-ACC-01, REQ-ACC-02, REQ-ACC-03, REQ-ACC-06, REQ-SALE-12, REQ-RFD-01, REQ-RPT-05 | THR-02, THR-03, THR-19 | L4, L5 | Integrated verification |
| AUTH02 | Sensitive channels per role | REQ-ACC-04, REQ-INV-05, REQ-INV-11, REQ-COST-04, REQ-DOC-04, REQ-RPT-05 | THR-08, THR-18 | L4, L5 | Integrated verification |
| AUTH03 | Ordered guards and recent authentication | REQ-ACC-06, REQ-ACC-08, REQ-ACC-09, REQ-CFG-04 | RACE-04, THR-20 | L3, L4 | Integrated verification |
| ACC01 | Account lifecycle and the single Owner | REQ-ACC-02, REQ-ACC-10, REQ-ACC-11, REQ-ACC-12 | RACE-16, RACE-21, RACE-28, RACE-30, G-08, G-16, THR-03 | L2, L3, L4, L5 | Integrated verification |
| LINK01 | One-time links and no email | REQ-ACC-05 | RACE-20, THR-01, THR-21 | L2, L3, L4, L5 | Integrated verification |
| SIGNIN01 | Sign-in sessions and shared devices | REQ-ACC-07 | THR-18 | L4, L5 | Integrated verification |
| SEC01 | Application threat paths | REQ-CFG-03, REQ-AUD-02, REQ-NFR-07 | THR-01, THR-04, THR-05, THR-06, THR-07, THR-09, THR-10, THR-11, THR-12, THR-13, THR-14, THR-15 | L1, L4, L5 | Integrated verification |
| SEC02 | Infrastructure exposure on the target | REQ-OPS-06, REQ-NFR-07 | THR-15, THR-16, THR-17 | L6 | Operations verification |
| AUDIT01 | Audit log and security log | REQ-ACC-10, REQ-RPT-05, REQ-AUD-01, REQ-AUD-02 | G-16 | L2, L4, L5 | Integrated verification |
| SEARCH01 | Barcode tokens and SKUs | REQ-CAT-01, REQ-CAT-04, REQ-CAT-05 | G-09 | L1, L2, L4 | Integrated verification |
| SEARCH02 | Product Search, master data and lifecycle | REQ-SCOPE-01, REQ-CAT-01, REQ-CAT-02, REQ-CAT-03, REQ-CAT-06, REQ-CAT-07, REQ-CAT-08, REQ-CAT-12 | RACE-05, RACE-23, RACE-29 | L2, L3, L4, L5 | Integrated verification |
| SEARCH03 | Scanning logic | REQ-SCAN-01, REQ-SCAN-02, REQ-SCAN-03, REQ-SCAN-04 | — | L1, L5 | Integrated verification |
| BARCODE01 | Barcode misassignment correction | REQ-CAT-13 | RACE-17, RACE-18, RACE-19, G-09 | L2, L3, L4, L5 | Integrated verification |
| IMPORT01 | CSV product import | REQ-CAT-09 | RACE-22, THR-09 | L1, L2, L3, L4, L5 | Integrated verification |
| MEDIA01 | Product photos and media | REQ-CAT-10 | G-20, THR-09, THR-12 | L2, L4, L5 | Integrated verification |
| INV01 | Ledger, receiving and reconciliation | REQ-SCOPE-01, REQ-INV-01, REQ-INV-03, REQ-INV-04, REQ-INV-05, REQ-INV-11, REQ-INV-12, REQ-INV-13, REQ-CMD-01, REQ-CMD-02, REQ-NFR-08 | RACE-01, RACE-24, G-02, G-03, G-11 | L2, L3, L4, L5 | Integrated verification |
| INV02 | Manual issue, aggregation and the last unit | REQ-INV-02, REQ-INV-03, REQ-INV-06 | RACE-03, G-02 | L2, L3, L4 | Integrated verification |
| INV03 | Adjustment and reversal | REQ-ACC-08, REQ-INV-08, REQ-INV-09, REQ-COST-05, REQ-COST-08 | RACE-10, G-07, G-11 | L2, L3, L4, L5 | Integrated verification |
| OPEN01 | Opening stock and onboarding | REQ-INV-07 | RACE-04, RACE-06, RACE-31, RACE-32, G-10, G-22, G-24, G-26 | L2, L3, L4, L5 | Integrated verification |
| STOCK01 | Stock attention | REQ-INV-10 | — | L2, L4, L5 | Integrated verification |
| FIN01 | Costing arithmetic | REQ-SALE-03, REQ-INV-06, REQ-COST-01, REQ-COST-02, REQ-COST-10 | G-13 | L1, L2 | Integrated verification |
| FIN02 | Unknown cost and corrections | REQ-COST-03, REQ-COST-05, REQ-RPT-03 | G-15 | L1, L2, L4, L5 | Integrated verification |
| FIN03 | Replay, returns and reports | REQ-INV-13, REQ-COST-06, REQ-COST-07, REQ-COST-09, REQ-RFD-07, REQ-RPT-01, REQ-RPT-02, REQ-RPT-03, REQ-RPT-06, REQ-NFR-09 | RACE-11, G-07, G-13, G-14, G-21 | L1, L2, L3, L4, L5 | Integrated verification |
| POS01 | Atomic checkout and command identity | REQ-SCOPE-01, REQ-SALE-01, REQ-SALE-05, REQ-SALE-06, REQ-SALE-09, REQ-COST-10, REQ-CMD-01, REQ-CMD-04, REQ-NFR-08 | RACE-01, RACE-02, G-01, G-04, G-17, G-18 | L2, L3, L4, L5 | Integrated verification |
| POS02 | Payment arithmetic and methods | REQ-CAT-08, REQ-SALE-02, REQ-SALE-03, REQ-PAY-01, REQ-PAY-02, REQ-PAY-03 | — | L1, L2, L4, L5 | Integrated verification |
| POS03 | Rejections, uncertainty and printing after commit | REQ-CAT-08, REQ-SALE-02, REQ-SALE-06, REQ-SALE-07, REQ-SALE-08, REQ-SALE-11, REQ-DOC-03, REQ-CMD-01 | RACE-01, RACE-23, RACE-27 | L3, L4, L5 | Integrated verification |
| POS04 | One payment screen | REQ-SALE-04 | — | L5 | Integrated verification |
| PAYEXT01 | Payment boundary | REQ-SCOPE-02, REQ-PAY-01, REQ-PAY-03, REQ-PAY-05 | — | L2, L4 | Integrated verification |
| CMD01 | Request receipts across devices | REQ-SALE-08, REQ-CMD-01 | RACE-27, RACE-33, G-25 | L2, L3, L4, L5 | Integrated verification |
| RET01 | Refund and return bounds | REQ-ACC-08, REQ-COST-07, REQ-RFD-01, REQ-RFD-02, REQ-RFD-05, REQ-RFD-06, REQ-RFD-07, REQ-CMD-02 | RACE-10, G-06, THR-19 | L2, L3, L4, L5 | Integrated verification |
| RET02 | Drawer-funded refunds | REQ-CASH-13, REQ-RFD-03, REQ-RFD-04, REQ-RFD-08, REQ-RFD-09 | RACE-13, RACE-14, RACE-15, G-05 | L2, L3, L4, L5 | Integrated verification |
| SESSION01 | Same-day sessions and handover | REQ-CASH-10 | — | L2, L5 | Integrated verification |
| SESSION02 | One OPEN session per register and per user | REQ-CASH-01, REQ-CASH-02 | RACE-07 | L2, L3, L4 | Integrated verification |
| SESSION03 | Opening cash and attribution | REQ-SALE-01, REQ-SCAN-04, REQ-CASH-03, REQ-CASH-04 | G-23 | L2, L4, L5 | Integrated verification |
| SESSION04 | Expected cash and variance | REQ-PAY-04, REQ-CASH-05 | G-05 | L1, L2 | Integrated verification |
| SESSION05 | Sale against close and unresolved checkouts | REQ-CASH-06, REQ-CASH-07, REQ-CASH-13 | RACE-08, RACE-13, RACE-35, G-27 | L2, L3, L4, L5 | Integrated verification |
| SESSION06 | Repeated and lost closes | REQ-CASH-06, REQ-CASH-08, REQ-CMD-01, REQ-CMD-02 | RACE-01, RACE-09 | L2, L3, L4, L5 | Integrated verification |
| SESSION07 | Closed sessions are final | REQ-COST-09, REQ-CASH-09, REQ-CASH-12 | — | L2, L4, L5 | Integrated verification |
| SESSION08 | Documents, print and export | REQ-CAT-11, REQ-DOC-01, REQ-DOC-02, REQ-DOC-03, REQ-CASH-12, REQ-NFR-10 | G-17 | L1, L2, L4, L5 | Integrated verification |
| SESSION09 | Daily summary | REQ-SALE-10, REQ-RFD-07, REQ-RPT-04, REQ-NFR-09 | G-18 | L1, L2, L5 | Integrated verification |
| SESSION10 | Session access and Owner-assisted close | REQ-SALE-12, REQ-DOC-04, REQ-CASH-11, REQ-CASH-12, REQ-RPT-05 | RACE-09, RACE-28, G-19 | L3, L4, L5 | Integrated verification |
| CONFIG01 | Cash register configuration | REQ-CFG-04 | RACE-26, G-19 | L2, L3, L4, L5 | Integrated verification |
| IDENTITY01 | Shop identity | REQ-SCOPE-03, REQ-CFG-01, REQ-CFG-02, REQ-CFG-03, REQ-DOC-01, REQ-UX-01 | RACE-25, G-20, G-23 | L2, L3, L4, L5 | Integrated verification |
| UX01 | Approved design and states | REQ-SCOPE-02, REQ-SALE-04, REQ-DOC-05, REQ-CMD-02, REQ-NFR-02, REQ-UX-01, REQ-UX-02 | — | L5 | Integrated verification |
| UX02 | Accessibility targets | REQ-UX-03 | — | L5, L8 | Integrated verification; Device verification for the manual checks |
| L10N01 | Localization | REQ-NFR-01 | — | L1, L5 | Integrated verification |
| DB01 | Migrations and privileges | REQ-OPS-02, REQ-OPS-05 | THR-17 | L2, L6 | Operations verification |
| KEEP01 | Retention | REQ-OPS-05 | — | L2, L4 | Operations verification |
| REC01 | Backup and isolated restore | REQ-OPS-01, REQ-NFR-05 | THR-17 | L6 | Operations verification |
| REC02 | After a restore: fence and reconciliation | REQ-INV-07, REQ-CMD-03, REQ-NFR-05 | RACE-12, RACE-34, G-12, G-26, THR-17 | L2, L3, L6 | Operations verification |
| STATUS01 | System status and monitoring | REQ-INV-12, REQ-OPS-04 | — | L2, L4, L5, L6 | Operations verification |
| OPPROC01 | Operator procedures | REQ-ACC-05, REQ-ACC-12, REQ-CMD-03 | RACE-21, RACE-30, THR-10, THR-20 | L2, L3, L6 | Operations verification |
| PERF01 | Performance on the intended server | REQ-COST-06, REQ-NFR-04 | — | L7 | Performance verification |
| DEVICE01 | Real devices | REQ-CAT-11, REQ-SCAN-01, REQ-DOC-02, REQ-NFR-02, REQ-NFR-03 | — | L8 | Device verification |
| READY01 | Release readiness | REQ-OPS-03, REQ-OPS-06, REQ-NFR-06 | THR-14 | L6 | Operations verification |
| RELEASE01 | Pilot and production acceptance | REQ-OPS-03 | — | L9 | Pilot |

## 4. Cases

### Access and security

**AUTH01 — Server authorization and object scope**
- Route inventory: every route of ROUTE_CONTRACTS names its gate; a route without one fails the test (RT-05, SEC-AZ-01).
- For every capability of SECURITY_MODEL section 6: each allowed role is accepted and every other role, and the combined account where the capability is Owner-only, is refused `FORBIDDEN` by direct request (REQ-ACC-02, REQ-RFD-01).
- Guessed identifiers of another cashier's Sale, session, receipt and media, and of a finance report for staff, answer the same `NOT_FOUND` as a missing object (THR-02, TR-ERR-03, REQ-SALE-12).
- The actor comes only from the sign-in session; a role or another authoritative value in a request body is refused (THR-03, TR-VAL-03); a role outside the three is refused (REQ-ACC-01).
- Only the Owner changes roles; nobody assigns a role to themselves; after a role change no old permission survives (REQ-ACC-06).
- Staff are refused refunds, returns and cost corrections on the server (THR-19).

**AUTH02 — Sensitive channels per role**
- Payload inspection per role for every channel of SECURITY_MODEL section 7: page props, JSON reads, validation errors, status and recovery answers, receipts, reports, print views, CSV, media and logs (REQ-ACC-04).
- OPERATIONS_ADMIN sees the purchase cost on receiving documents, edits it until confirmation and never after; after the Owner's correction from Rp5.000 to Rp5.500 it sees Rp5.500, "Dikoreksi Pemilik" and Rp5.000, never the reason; it never receives average cost, cost of goods sold, margin, profit or inventory value (REQ-INV-05, D-047).
- CASHIER never receives a cost; till screens, receipts, session reports, labels and search results contain none (REQ-COST-04).
- Sign-out, then another user on the same browser, then Back, Forward and reload show nothing of the first user; authenticated responses are `no-store` (THR-08, THR-18).

**AUTH03 — Ordered guards and recent authentication**
- Every operation that INTERACTION_CONTRACTS section 1.5 lists answers 423 without a confirmation within 15 minutes and succeeds when the same frozen request is sent after RA-03 (REQ-ACC-08, THR-20).
- RACE-04 in both orders for each writer: deactivation, role change, onboarding start, BusinessProfile change, cash register change, price change and product deactivation against a running command; the change that commits first is in force at validation and a command no longer allowed writes nothing (REQ-ACC-09).
- A register configuration writer against a session open, as AUTH03 checks writers in general; its full cases are CONFIG01 (REQ-CFG-04).

**ACC01 — Account lifecycle and the single Owner**
- A combined account holds the union of CASHIER and OPERATIONS_ADMIN and is refused every Owner-only capability; SUPER_ADMIN is never combined (REQ-ACC-02, THR-03).
- Deactivate and reactivate a staff account; history keeps the identity; a SUPER_ADMIN account is never deactivated, reactivated or anonymized by a route (REQ-ACC-10, REQ-ACC-12).
- Anonymize only a deactivated account, with a reason; frozen display names on earlier receipts and reports are unchanged and a reprint is identical (RACE-16, G-16); an anonymized account is never reactivated (REQ-ACC-11).
- Exactly one active Owner: the partial unique index, the replacement procedure and RACE-21, RACE-30 (G-08).
- RACE-28: deactivation of a user holding an OPEN session leaves the session OPEN and unchanged; every command of that user is refused; the Owner-assisted close succeeds.

**LINK01 — One-time links and no email**
- No sign-up and no public password-reset route exists; no flow sends email (REQ-ACC-05, THR-01).
- A link works once, within 60 minutes; it is refused after use, after expiry, after being replaced or revoked, and after the account is deactivated, anonymized or its email changed; RACE-20 in both orders; failures are uniform.
- The link appears only in the issuing JSON response, never in props, history state, a URL or a log; the fragment is removed after reading (SEC-ACC-05).
- Every password-setting path enforces AICWDF-COMPAT-8; hashes are Argon2id.
- Issuance and consumption are audited; the handover warning of D-056 is shown (THR-21).

**SIGNIN01 — Sign-in sessions and shared devices**
- The 30-minute idle limit counts user-initiated requests only; background refreshes (RT-10) never extend it; the 12-hour absolute limit holds (REQ-ACC-07).
- Signing out or a session ending never changes a cash session (BR-CASH-11).
- Client timers clear the history key; Back after expiry shows nothing; no remember-me; the revocation triggers of SEC-SESS-04 (THR-18).

**SEC01 — Application threat paths**
- Enumeration: uniform sign-in, link and confirmation failures; throttling of every bucket of SECURITY_MODEL section 11, including forged forwarding headers in application tests (THR-01).
- Injection: SQL and script payloads in every text input, CSV values and BusinessProfile texts are stored and shown as text on screens, receipts and labels (THR-04, THR-05).
- CSRF in both layers; every security header of SEC-HDR-02 on every response; the content security policy in a real browser with no violation and no blocked needed script or style (THR-06, THR-07).
- Uploads: every refusal case of SECURITY_MODEL section 13; metadata removed (REQ-CFG-03, THR-09).
- Command injection: the static rule of TEST_STRATEGY section 6; file names and CSV values with shell metacharacters never reach a process (THR-10).
- No URL input is fetched; manipulated paths and guessed media identifiers are refused; only local redirect targets (THR-11, THR-12, THR-13).
- Debug output is off outside local; environment, vendor and private paths are unreachable (THR-15).
- The advisory report of every release is recorded (THR-14); logs hold no secret or full payload (REQ-AUD-02).

**SEC02 — Infrastructure exposure on the target**
- From outside: the database port, Coolify's direct ports and the origin's 443 from a non-Cloudflare address are unreachable; SSH accepts keys only (THR-16; ENVIRONMENTS section 9).
- Full (strict) TLS through Cloudflare; HSTS after verification; trusted hosts refuse other host names.
- A forged `X-Forwarded-For` or `CF-Connecting-IP` from outside does not change the address in the security log or the rate limiter (ENVIRONMENTS section 10).
- Staging and production share no database, volume or secret, compared by fingerprint without revealing a value; staging is behind its access gate and answers `noindex` (REQ-OPS-06, ENV-02, ENV-06).
- The backup credentials exist only in the backup container; the migration credential exists in no container; private paths are unreachable on the target (THR-15, THR-17).

**AUDIT01 — Audit log and security log**
- Every successful business change writes its audit event in its own transaction; a failed command writes none, except the event of a cost-incomplete mark (REQ-AUD-01, TR-CMD-06).
- A deactivated or anonymized user's events still show the frozen identity; a combined account's events name one identity (REQ-ACC-10, G-16).
- The Owner's filters by date, user, type, product, session and reference; staff see only their scope; security audit and the security log are Owner-only (REQ-AUD-02, REQ-RPT-05).
- A security log entry survives the rollback of its request's transaction; it holds no secret, token, cookie, user agent or payload.
- Housekeeping removes security log entries older than 365 days and nothing newer; audit events are never removed (D-088, SEC-LOG-05).

### Catalogue

**SEARCH01 — Barcode tokens and SKUs**
- Tokens round-trip exactly with leading zeros, case and punctuation; only the scanner terminator and outer spaces are removed; 1 to 128 printable ASCII; a declared EAN or UPC check digit is validated (REQ-CAT-04).
- Generated internal codes are `BK-P-` and 16 Crockford Base32 characters; an alias in the `BK-` namespace is refused; a retired token is never added again except by BARCODE01; a `BK-P-…` code is never reused (REQ-CAT-05, G-09).
- SKUs are unique without regard to letter case, with the case vectors of SCH-14; duplicates under concurrency give one success (REQ-CAT-01).

**SEARCH02 — Product Search, master data and lifecycle**
- Matching order exact barcode, then SKU, then name; paging 25 by default and at most 100 in a stable order; the whole list is never fetched; results never carry cost; each context opens only to its capability (REQ-CAT-07; INTERACTION_CONTRACTS section 3.1).
- Search-first creation with "Tetap buat produk baru" for a similar name; two concurrent creations with one SKU give one success (REQ-CAT-03); a new product has stock 0 and no movement (REQ-CAT-02).
- Unit and precision cannot change after the first movement, also in RACE-05 (REQ-CAT-06).
- A price change creates a SellingPriceRevision and an audit event; the till offers no price edit and ignores a price sent by the browser; RACE-23 (REQ-CAT-08).
- Deactivation and reactivation of products and categories; an inactive product cannot be posted or sold; an inactive category cannot be assigned, also in RACE-29; no delete function exists (REQ-CAT-12, REQ-CAT-01).
- No tenant, outlet, warehouse or location exists in data or interface (REQ-SCOPE-01).

**SEARCH03 — Scanning logic**
- With emulated keyboard-wedge input: one base unit per complete scan; empty CR, LF and key repeat add nothing; scans apply in order and a late lookup never overrides a newer one; an unknown token stays in the field; a fraction needs an explicit quantity edit (REQ-SCAN-02).
- A scanner Enter never confirms a payment, a posting or a close; capture pauses while a dialog, a review or a count is open (REQ-SCAN-03).
- At the till scanning needs the user's own OPEN session; offline, posting is refused and nothing is queued; IME input, partial input and a disconnect mid-scan behave as INTERACTION_CONTRACTS section 1.7 says (REQ-SCAN-04, REQ-SCAN-01; physical scanners in DEVICE01).

**BARCODE01 — Barcode misassignment correction**
- Only the Owner, with a reason and recent authentication; OPERATIONS_ADMIN and CASHIER are refused (REQ-CAT-13).
- Moving an active manufacturer token; re-attaching a token retired as a misassignment; refusing a token retired for another reason and every `BK-P-…` code (G-09).
- RACE-17 and RACE-19: one active mapping after racing corrections or attaches; RACE-18: a lookup during a correction resolves to one product; earlier Sales keep their product and receipts are unchanged.

**IMPORT01 — CSV product import**
- The dialect of DOCUMENT_CONTRACTS DC-04: header, semicolon or comma, byte-order mark, decimal commas and thousands full stops, barcodes and SKUs as text, UTF-8 only (REQ-CAT-09).
- Refusals before commit: a formula cell, a control character, an unknown or missing column, more than 5,000 rows or 2 MiB, a missing, ambiguous or inactive category name — never creating a category (D-069 (h)) — and duplicates within the file or against the database (THR-09).
- All rows or none; a repeated confirmation returns the original result; the checksum ties the confirmation to the previewed file (RACE-22, SEC-UP-04); no stock or cost is created; CASHIER is refused.
- The temporary file is removed at commit, refusal or cancellation, and by housekeeping after 24 hours; it is never served; the audit keeps the result with the checksum (D-054).

**MEDIA01 — Product photos and media**
- JPEG, PNG and WebP accepted by decoding; size and dimension limits; animated images refused; normalized to 2,048 pixels with thumbnails; metadata removed (REQ-CAT-10, THR-09).
- A replaced or removed photo's file is kept and no longer shown; CASHIER cannot upload; the till shows thumbnails and the placeholder (G-20).
- An unreferenced file of a rolled-back command is removed after the grace period of at least one hour, never earlier (TR-TX-05).
- Media are served only through RD-07 to roles that may see them, with `Cache-Control: private`; guessed identifiers are refused (THR-12).

### Inventory and costing

**INV01 — Ledger, receiving and reconciliation**
- Every physical change is one movement; the balance equals the sum of movements after every command; no function edits a balance (REQ-INV-01, REQ-NFR-08, G-03).
- Receiving with KNOWN cost and with UNKNOWN cost, never stored as zero; an explicit zero needs the Owner's confirmation; nothing posts before confirmation; no draft table or draft endpoint exists (REQ-INV-04, REQ-INV-05, D-069 (a)).
- A retry with the same or a different key posts once (RACE-01, REQ-CMD-01); receiving has a review and an explicit confirmation (REQ-CMD-02); failure injection at every write step (REQ-INV-03).
- The reconciliation detects induced mismatches in balances, valuations, allocation projections, running totals and active mappings, blocks the product and raises an incident; RACE-24; a blocked product refuses postings `POSTING_BLOCKED` (REQ-INV-12, G-11).
- Paging and role payloads of stock and history (REQ-INV-11, G-02); server time and posting order are facts; a document date never moves a fact (REQ-INV-13); one stock pool (REQ-SCOPE-01).

**INV02 — Manual issue, aggregation and the last unit**
- A reason from the five of BR-INV-19 is required; insufficient stock is refused; lines of one product are added together before the check (REQ-INV-02, G-02).
- RACE-03: a last-unit Sale against a manual issue gives exactly one success and stock never goes negative.
- The issue's valuation: the average before the issue, the remaining value when stock reaches zero, UNKNOWN while the valuation is unknown; no cost of goods sold and no Sale (REQ-INV-06); failure injection (REQ-INV-03).

**INV03 — Adjustment and reversal**
- Only the Owner adjusts, with a signed quantity, reason, count, reference and recent authentication; a positive adjustment carries cost evidence; no adjustment makes stock negative (REQ-INV-08, REQ-ACC-08).
- A reversal is the exact inverse of one eligible OPENING, RECEIPT or MANUAL_ISSUE, once (RACE-10); the COSTING section 8 fixture gives quantity 5, value 50 and issue cost 50; the invalid-history fixture is refused with no movement; SALE_ISSUE and RETURN_RECEIPT cannot be reversed (REQ-INV-09, REQ-COST-08, G-07).
- A RECEIPT or MANUAL_ISSUE is reversed only while onboarding is not active; an OPENING also while it is active (D-087); a blocked product refuses both (G-11).
- Cost correction by the Owner only, append-only (REQ-COST-05).

**OPEN01 — Opening stock and onboarding**
- Only the Owner; the freeze refuses ordinary postings and Sales, also racing its start (RACE-04, G-10).
- At most 100 rows; a 101st or an invalid row refuses the whole command; an original OPENING once per product without an earlier movement; zero counts on the checklist without a movement (RACE-06).
- One effective entry per product: withdrawal of a zero count, reversal of an OPENING and one explicit replacement, each by the Owner with a reason and recent authentication; a line replaced at most once (RACE-31, G-22, G-24).
- One start and one release at most, also concurrently (RACE-32); a start after the release is refused; after a test restore of a database dumped while onboarding was ACTIVE and later released, OP-04 sets a hold and every onboarding and opening command is refused until OP-05 records the Owner's outcome (G-26, D-072; TEST_STRATEGY TS-24).
- No database lock is held while people count (TR-TX-03).

**STOCK01 — Stock attention**
- With minimum 5: 6 is Normal, 5 and 1 are Menipis, 0 is Habis; minimum 0 has no Menipis; a new product with stock 0 is Habis (REQ-INV-10).
- The badge and the list come from one query and agree; refresh after a change, on focus and at least every 30 seconds; a failed refresh shows stale data with its time, never empty, zero or Normal; the automatic reads carry the background marker.

**FIN01 — Costing arithmetic**
- The L-15 example gives sales Rp60.000, cost of goods sold Rp36.000 and gross profit Rp24.000; the issue that empties stock takes the remaining value (REQ-COST-01).
- 1.5 × Rp3.333 gives a line total of Rp5.000; half-up once per line; six-decimal cost; quantities with 0 to 3 decimals; values travel as decimal strings; an overflow is refused; no floating point (REQ-COST-02, REQ-SALE-03).
- A Sale records each line's allocation or UNKNOWN in its transaction; non-sale issues are valued as INV02 says (REQ-COST-10, REQ-INV-06); the ADR-008 test list (G-13).

**FIN02 — Unknown cost and corrections**
- UNKNOWN is distinct from zero and propagates; allocations made while the valuation was known stay valid; at zero stock a fully known increase starts a new known pool; incomplete figures are never shown as complete or zero (REQ-COST-03).
- Correction and completion only by the Owner with recent authentication, as a new fact with the old and new value; the original evidence is unchanged (REQ-COST-05, G-15).
- Reports show "Data biaya belum lengkap", the count of lines with unknown cost and of products with an active mark (REQ-RPT-03).

**FIN03 — Replay, returns and reports**
- The same correction replayed twice gives the same result; failure injection during publication changes nothing; RACE-11 (REQ-COST-06, G-07, G-14).
- Reaching the movement cap or the time budget refuses the correction and writes one mark per refused intent; no mark after `SERVICE_BUSY`, `POSTING_BLOCKED` or a refused reversal; a mark is cleared only by a later correction of the same source, a reversal of its movement or OP-06 (G-21; SCHEMA section 5.8).
- A refund without return keeps cost of goods sold; a saleable return restores the cost of its sale issue once; a correction after a return recovers cost exactly once (REQ-COST-07).
- Owner reports show corrected figures also for earlier periods; receipts and session reports never change (REQ-COST-09); refunds are reported on their posting date (REQ-RFD-07, REQ-INV-13).
- The finance report from one snapshot with as-of time, time zone, completeness and the trend; the periods as half-open Asia/Jakarta ranges with the custom-range refusals; margin only when cost is complete; never net profit (REQ-RPT-01, REQ-RPT-02, REQ-RPT-03, REQ-RPT-06, REQ-NFR-09, G-13).

### Sales and payment

**POS01 — Atomic checkout and command identity**
- Exactly one Sale with its lines, Payment, issues, balances, allocations, receipt, audit and result per intent; RACE-01 and RACE-02; failure injection at every write step (REQ-SALE-05, REQ-CMD-01, G-01, G-17).
- Checkout needs the actor's own eligible OPEN session for every role and method; an OPERATIONS_ADMIN-only account is refused (REQ-SALE-01, G-04).
- One line per product: a request naming a product twice or with more than 200 lines is refused `VALIDATION_FAILED` before any lock (D-067); the activity version increases by one.
- Numbering: concurrent checkouts in different sessions do not wait on each other for a number; numbers are unique, follow allocation order and leave a gap after a rollback; the date part is right across midnight (G-18; SCHEMA section 6).
- A deadlock injection resolves with one result under the bounded retry with the same identity (REQ-CMD-04); no order, booking or customer record is needed (REQ-SALE-09); one stock pool (REQ-SCOPE-01).

**POS02 — Payment arithmetic and methods**
- Total Rp75.000 with Rp100.000 tendered gives Rp25.000 change and Rp75.000 applied; Rp70.000 is refused `TENDER_INSUFFICIENT`; "Uang Pas" sets tendered to the total (REQ-PAY-02).
- BANK_TRANSFER for the exact total with an optional reference, confirmed by "Dana sudah diterima — Selesaikan", with no pending state (REQ-PAY-03).
- Mixed, partial and unknown methods are refused, STATIC_QRIS included (REQ-PAY-01); the applied price is the current revision; a tampered or overridden price is refused (REQ-SALE-02, REQ-CAT-08); line totals and the Sale total (REQ-SALE-03).

**POS03 — Rejections, uncertainty and printing after commit**
- Each definite rejection — stale price, insufficient stock, inactive product, invalid or closed session, method not allowed — writes nothing and returns to the review without a silent change of total (REQ-SALE-07, REQ-SALE-02; RACE-23).
- A lost response, reload or status check recovers the original Sale, also after the session closed; no replacement and no second charge; HELD offers "Cek status" only; recovery re-checks authorization (REQ-SALE-08, REQ-SALE-06, REQ-CMD-01; RACE-01).
- RACE-27: a fence against a late original.
- A printer failure after commit leaves the Sale intact and reprintable; a reprint creates no fact (REQ-SALE-11, REQ-DOC-03); price changes and the till (REQ-CAT-08).

**POS04 — One payment screen**
- No checkbox and no separate review dialog; exactly one final action posts; Tunai is preselected (D-075); the states enabled, submitting, committed, rejected and uncertain are each visible; the receipt follows the commit without a further step (REQ-SALE-04, D-040).
- On the tablet the final action has a production-usable touch target away from the cart controls; the keyboard path of UXA-09 with the provisional shortcuts.

**PAYEXT01 — Payment boundary**
- The server allowlist holds CASH and BANK_TRANSFER only; no provider record, controller, callback, webhook or asynchronous payment state exists; a completed Sale never becomes pending (REQ-PAY-05, REQ-PAY-01, REQ-PAY-03, REQ-SCOPE-02); evidence by code and schema review.

**CMD01 — Request receipts across devices**
- Every command request is recorded on arrival, in its own transaction, before the command's transaction; a receipt that cannot be written refuses the request `SERVICE_BUSY` with nothing run (G-25; failure injection).
- The outcome is marked COMMITTED, REJECTED, HELD or NOT_COMMITTED by the request itself, replacing only PENDING, and settled for every receipt of the intent by a conclusive status check or a reconciliation (ADR-009 section 1; SESSION05); RD-04 lists a user's PENDING and HELD receipts, and every user's to the Owner, and no longer lists a settled one (REQ-CMD-01).
- NC-13 blocks a family on a second browser context of the same user and after the site data are cleared, for every request that reached the server (TEST_STRATEGY TS-23; REQ-SALE-08).
- The Owner checks another user's request; a fence names the Owner as actor; RACE-33 and RACE-27.
- D-088: apart from the close (SESSION05), no family is refused by the server because of an unresolved receipt — a second checkout of the same user from another browser while a receipt is PENDING is not refused by the server; the client's NC-13 block is what prevents it.
- A lost reference may be concluded not committed only after the record-check wait of 10 minutes (TR-TIME-03, ADR-009 section 4).
- Housekeeping removes resolved receipts older than 30 days and never an unresolved one (KEEP01).

### Refunds and returns

**RET01 — Refund and return bounds**
- Only the Owner; CASHIER, OPERATIONS_ADMIN and combined accounts are refused on the server (REQ-RFD-01, THR-19); a reason, recent authentication and a review (REQ-ACC-08, REQ-CMD-02).
- Money and quantity bounds are separate and cumulative and hold in RACE-10: refunds never exceed the applied payment (a CASH Sale of Rp75.000 paid with Rp100.000 is refunded at most Rp75.000), a line's refunds never its line total, a line's returns never its issued quantity; a partial line refund within its bound; one command may hold a refund, a return or both (REQ-RFD-02, G-06).
- A refund without return adds no stock; a damaged return adds no saleable stock; a saleable return posts RETURN_RECEIPT with its cost recovery (REQ-RFD-06, REQ-COST-07).
- Atomic and idempotent; failure injection leaves nothing (REQ-RFD-05); the original Sale, Payment, receipt and closed report are unchanged; the refund is linked and reported on its posting date and counted once (REQ-RFD-07).

**RET02 — Drawer-funded refunds**
- For CASH the Owner chooses one OPEN session's drawer or "Dana Pemilik"; a BANK_TRANSFER refund affects no drawer (REQ-RFD-03).
- A drawer-funded refund reduces that session's expected cash, appears separately in its report with the Owner as actor and increases the activity version; a CLOSED session is refused (REQ-RFD-04, REQ-CASH-13, G-05).
- With expected cash Rp80.000: a Rp100.000 drawer refund is refused `DRAWER_CASH_INSUFFICIENT` with nothing written; Rp80.000 leaves Rp0; two racing refunds never take it below zero (RACE-14); the source is never switched or split (REQ-RFD-08).
- No cash before commit: a refusal comes before any cash leaves; an uncertain refund is resolved before cash is taken and is never replaced (RACE-15, REQ-RFD-09); RACE-13 in both orders.
- The scenario of D-068: a refund committed before the preview whose cash was still in the drawer at the count closes the session with variance +R and nothing else created; a payout against the CLOSED session stays refused; the payout reference is `<Sale number>/<k>`.

### Cash sessions

**SESSION01 — Same-day sessions and handover**
- Closing and opening again on the same business date gives two sessions with two distinct reports; a handover is A closes, then B opens; a session across midnight stays one document (REQ-CASH-10).

**SESSION02 — One OPEN session per register and per user**
- The two partial unique indexes; RACE-07: concurrent opens of one register or by one user give one success (REQ-CASH-02).
- No shared drawer, no transfer of responsibility, no paid-in, paid-out or cash pickup exists in the route and command inventory (REQ-CASH-01).

**SESSION03 — Opening cash and attribution**
- A blank opening amount is refused; Rp0 is accepted; the open is atomic and creates no Sale or movement; a BusinessProfile revision exists from the bootstrap (REQ-CASH-03, G-23).
- Every Sale, transfer-only included, is attributed once to its own session, register and user; checkout and till scanning without an eligible session are refused before any fact (REQ-CASH-04, REQ-SALE-01, REQ-SCAN-04).

**SESSION04 — Expected cash and variance**
- Opening Rp20.000, CASH Rp100.000 and BANK_TRANSFER Rp50.000 with physical Rp118.000 give expected Rp120.000 and variance −Rp2.000; with a drawer-funded refund of Rp10.000 added, expected Rp110.000, physical Rp108.000, variance −Rp2.000 (REQ-CASH-05, G-05).
- BANK_TRANSFER and Owner-funded refunds never change expected cash; per-method totals are separate; opening cash and variance are never sales or profit (REQ-PAY-04).

**SESSION05 — Sale against close and unresolved checkouts**
- RACE-08: Sale first, the close needs a recount (`RECOUNT_REQUIRED`); close first, the Sale is refused `SESSION_NOT_OPEN` before any fact; no successful Sale is missing from its report; no lock is held while cash is counted; there is no CLOSING state (REQ-CASH-06, REQ-CASH-07).
- A drawer-funded refund after the preview makes the close refuse with the recount message (RACE-13, REQ-CASH-13).
- D-088, G-27, RACE-35: a close, own or Owner-assisted, is refused `RECOUNT_REQUIRED` while the session's responsible user has a `sale.checkout` receipt that is PENDING or HELD, an earlier-epoch HELD receipt included; the session stays OPEN; after the status check resolves the receipt the close succeeds; a checkout whose receipt is written after the close's check finds the session CLOSED and is refused `SESSION_NOT_OPEN`.
- D-089, the settlement of receipts (ADR-009 section 1; SCHEMA section 5.3): the responsible user has two `sale.checkout` receipts of one intent, one PENDING because its process ended before marking it and one HELD with `IDEMPOTENCY_CONFLICT`. A status check that conclusively answers COMMITTED — run by the user, and in a second case by the Owner — settles both to COMMITTED in one transaction, keeps the `held_code` and records the settling identity and the checker; the close is then refused only by its activity version until the recount (RACE-08) and succeeds after it. A check that answers NOT_COMMITTED writes the fence and settles both in the fence's transaction; the close then succeeds, and a late original is refused `INTENT_ABANDONED` with nothing written.
- A check by a user who is neither the intent's actor nor the Owner answers `NOT_FOUND` and settles nothing; IN_PROGRESS and a check without an answer settle nothing; a settled receipt never returns to PENDING or HELD, also when its own request is processed afterwards.
- A settlement concurrent with a close, with controlled interleaving: settled before the close's read, the close proceeds under its other checks; settled after it, the close was already refused; the two never deadlock, and the close never commits while the checkout can still commit.
- An earlier-epoch HELD receipt keeps the close refused until the status check finds its identity in the restored data (COMMITTED) or OP-05 records NOT_COMMITTED with its fence, which settles it in the same transaction (REC02).
- The client keeps "Lanjut hitung" disabled while such a request is known (INTERACTION_CONTRACTS section 2.5); the server refusal holds also when the client's list is stale.

**SESSION06 — Repeated and lost closes**
- A repeated or lost close with the same or a different key gives one CLOSED session, one snapshot and one result; a different count never overwrites; a stale activity version asks for a recount and stays OPEN (RACE-09, RACE-01, REQ-CASH-08, REQ-CMD-01).
- Failure injection at the snapshot, audit and result keeps the session OPEN; the result is recoverable after the session closed; the close has its review and explicit confirmation (REQ-CASH-06, REQ-CMD-02).

**SESSION07 — Closed sessions are final**
- The snapshot, count, variance, cutoff, source identities, shop identity and template of a CLOSED session cannot change; there is no reopening (REQ-CASH-09).
- Owner review notes are append-only, several per session, and change nothing in the report (D-069 (f)); later refunds, returns and cost corrections are linked and never change the report as closed (REQ-COST-09, REQ-CASH-12).

**SESSION08 — Documents, print and export**
- The receipt snapshot holds the fields of BR-DOC-01 and DOCUMENT_CONTRACTS DC-01; 58 mm, 80 mm and A4 "Nota Penjualan" render from one snapshot; no tax line and no tax-invoice wording (REQ-DOC-01, REQ-DOC-02, REQ-NFR-10, G-17).
- Every print after the first carries "Salinan" first at the top; print, reprint and export create no Sale, Payment, movement or session change and are audited (REQ-DOC-03).
- The session report and its CSV of DC-03: semicolons, a byte-order mark, plain integers, formula and control prefixes neutralized, a negative variance kept numeric, time zone and session context; a failed export retries the same snapshot (REQ-CASH-12).
- Labels: Code 128 with code, name and SKU and never cost; 1 to 100 per request; a reprint is audited (REQ-CAT-11; scannability in DEVICE01).

**SESSION09 — Daily summary**
- Totals computed once from source records: Sales by business date, refunds and returns by posting date; sessions listed with their variance separately; a session across midnight once with its full span; nothing counted twice (REQ-RPT-04, REQ-RFD-07).
- Two Sales at 23.59 and 00.01 Asia/Jakarta in one session get different business dates (REQ-SALE-10, REQ-NFR-09, G-18).

**SESSION10 — Session access and Owner-assisted close**
- A cashier cannot read, print, export or close another cashier's session, also with guessed identifiers; OPERATIONS_ADMIN has no session or POS report access (REQ-CASH-12, REQ-DOC-04, REQ-SALE-12, REQ-RPT-05).
- The Owner-assisted close needs the capability, recent authentication, a count and a reason; the responsible user stays and the close actor is recorded; RACE-09 against the user's own close (REQ-CASH-11, G-19).
- RACE-28: a session of a deactivated user, or of an Owner replaced by OP-03, stays OPEN until the Owner-assisted close.

**CONFIG01 — Cash register configuration**
- Create and change registers with recent authentication and audit; a change is refused while a session is OPEN on the register (REQ-CFG-04, G-19); RACE-26 against a session open.
- Codes unique without regard to letter case; label limits; earlier snapshots keep the identity they recorded.

### Identity and interface

**IDENTITY01 — Shop identity**
- Each BusinessProfile change creates an immutable revision, moves the pointer and is audited; the first revision exists from the bootstrap (REQ-CFG-01, G-23).
- After a profile or logo change, reprints of earlier Sales and session reports show the original identity; RACE-25 (REQ-CFG-02, REQ-DOC-01).
- Logo rules and the accent from the set of DESIGN_SYSTEM section 3.3 (REQ-CFG-03, G-20); no client name or logo in code or bundled assets; "BantuKasir" never as the shop's identity (REQ-SCOPE-03, REQ-UX-01).

**UX01 — Approved design and states**
- Every page's controls, navigation and back actions per role equal INTERACTION_INVENTORY, NAVIGATION_CONTRACTS and INFORMATION_ARCHITECTURE as approved (D-082); every control routes as INTERACTION_CONTRACTS section 9 says; no dead control; nothing outside V1 looks active (REQ-UX-02, REQ-SCOPE-02).
- Every state of UI_STATES is reachable and shown with its COPY_DECK wording (D-086); the key screens match the approved previews and DESIGN_SYSTEM by review (D-087; REQ-UX-01).
- Dates as `01 Oktober 2026, 09.15.00` (REQ-DOC-05); critical mutations show their review except the ordinary Sale (REQ-CMD-02, REQ-SALE-04).
- The widths of KEY_SCREENS section 4 and 1440 px; the PC and tablet till are functionally equivalent in emulation (REQ-NFR-02; physical in DEVICE01).

**UX02 — Accessibility targets**
- Keyboard-only paths per page type and visible focus (UXA-08 to UXA-10); 200 % zoom without sideways scrolling or clipped actions (UXA-12); screen-reader structure — landmarks, headings, names, announcements (UXA-13, UXA-17) — by automated checks and a manual screen-reader pass; long content (UXA-15); rendered contrast of at least 4.5:1, or 3:1 for large text (UXA-18); touch targets of at least 44 px (UXA-07); reduced motion and time limits (UXA-20, UXA-21) (REQ-UX-03).

**L10N01 — Localization**
- Every user-facing text comes from a translation key; the build fails on a literal string in interface code or on a missing key; Indonesian only; no language switcher (REQ-NFR-01, ADR-005).
- The keys and texts equal the approved COPY_DECK (D-086); a proposal of T-19 or T-20 ships only after its change control.

### Operations and release

**DB01 — Migrations and privileges**
- Migrations are forward and additive, reviewed as SQL, and rehearsed as REL-21 says; after each: row counts, valid constraints, privileges equal to SCHEMA section 10, a clean reconciliation (REQ-OPS-02).
- The runtime role is refused DDL, `UPDATE` and `DELETE` on retained classes, `TRUNCATE` and `setval`; the backup role can read and insert `backup_runs` only; the migration credential is absent from the runtime and backup containers; `PUBLIC` rights are revoked (THR-17).
- The release preflight refuses an environment mismatch, a backup older than one hour, an unexpected migration, a destructive migration without its approval and `synchronous_commit` off; no container start runs a migration; no reset, seed or fresh migration exists on a production path (REQ-OPS-05).

**KEEP01 — Retention**
- No product path deletes or rewrites a row of classes F, FS, M or S; no delete, purge or archival function exists for business records or master data (REQ-OPS-05).
- The cleanups remove only: sign-in sessions, expired links, cache rows, resolved receipts older than 30 days, security log entries older than 365 days, unreferenced files after one hour, temporary CSV files after 24 hours — never an unresolved receipt.

**REC01 — Backup and isolated restore**
- A backup run as BACKUP_RECOVERY section 4 describes, including each failure code; the excluded table data; the files as a superset; the manifest last; the weekly re-verification (REQ-OPS-01).
- The server holds only the recipient: no private key exists on the server or in its configuration; the server's storage key cannot delete a locked object; the lifecycle tiers keep what section 6 says (THR-17). Every decryption of a drill or a restore runs on the operator's own machine or on an isolated drill target off the VPS, never on the VPS, and decrypted data reach another machine only through an SSH tunnel (BACKUP_RECOVERY BKP-08).
- A restore drill (RB-02) on a new isolated target off the VPS: decryption, hashes, restore as the migration role, grants, privilege test, file checksums, counts equal to the manifest, a clean reconciliation, the application started; the drill record shown by the status; the measured times (REQ-NFR-05).

**REC02 — After a restore: fence and reconciliation**
- OP-04 sets an epoch never issued before and revokes every session; RACE-12: stale intents are refused `EPOCH_STALE`, also after the same backup is restored twice (REQ-CMD-03, G-12).
- The number sequences are advanced so that no number issued after the backup point repeats (SCH-28).
- A status check of an earlier-epoch intent whose identity is in the restored data answers COMMITTED; otherwise `EPOCH_STALE` with one report; OP-05 records NOT_COMMITTED with a fence, settles the intent's PENDING or HELD receipts in the same transaction (ADR-009 section 1), and a late original is refused `INTENT_ABANDONED`; RACE-34.
- The Owner's user-management requests are reconciled before the security changes after the backup point are re-applied (RB-01 order); the onboarding hold and its two outcomes (REQ-INV-07, G-26, D-072).
- The rehearsal of RB-01 phases D and E on staging, with the Owner's time measured (REQ-NFR-05).

**STATUS01 — System status and monitoring**
- Each item of OPERATIONS_RUNBOOK section 5 with its evidence and thresholds — 7 hours, 35 days, 20 % free, 26 hours — including every "Belum Terverifikasi" case; a failed read is UXS-22; the attention item on Beranda; Owner-only (REQ-OPS-04).
- The incident link appears while an incident is open (REQ-INV-12).
- `/up` answers 200 or an error with no detail, needs no session and is throttled; SC-04 pings only after the public check succeeds; the backup and reconciliation checks are pinged on success; on the target each alert is received once.
- `backup_runs`, `restore_drills` and `reconciliation_runs` are written as SCHEMA section 5.12 says.

**OPPROC01 — Operator procedures**
- Every procedure refuses when the typed environment name differs from the configuration or the database marker; accepts only typed, validated options; prints a link only to the terminal, never to a log; writes its audit event with the procedure as actor and the operator's note, and a security log entry (THR-10, OPR-02).
- OP-01 refuses when any account exists and of two concurrent runs one succeeds (RACE-30, REQ-ACC-05); OP-02 issues a RESET link and revokes the Owner's sessions; OP-03 deactivates the named Owner, leaves an OPEN session OPEN and creates the new Owner with a link, a repeat refused (RACE-21, REQ-ACC-12, THR-20).
- OP-04 and OP-05 as REC02 (REQ-CMD-03); OP-06 runs the correction's validation and replay with the extended bound, publishes with the Owner as the correction's actor and clears the marks with `RECOVERY_PROCEDURE`, or refuses with no change; OP-07 rebuilds projections from facts, never changes a fact and refuses on invalid facts; OP-08 records a drill.
- The rehearsal of every procedure on staging, with RB-10 and RB-11.

**PERF01 — Performance on the intended server**
- The budgets of TECHNICAL_RULES section 13.1 with two cashiers checking out while the Owner runs a report, on the provisional dataset of section 13.2 until gate G2; the replay of 10,000 movements and the CSV import of 5,000 rows within their 30-second budgets (REQ-NFR-04, REQ-COST-06).
- The ten-year growth dataset keeps paging and indexes bounded; each run records versions, configuration, indexes, host, dataset, run count, p95 and a reconciliation before and after; a miss is never fixed by weakening integrity, privacy or cost completeness.

**DEVICE01 — Real devices**
- The protocol of DEVICE_VERIFICATION on the shop's PC, tablet, USB and Bluetooth scanners, receipt, A4 and label printers and browsers (REQ-NFR-02, REQ-NFR-03, REQ-SCAN-01, REQ-DOC-02, REQ-CAT-11).

**READY01 — Release readiness**
- The release integrity report complete (RELEASE_PROCESS section 4); the image checks passed; the scans clean or each finding accepted with its reason (THR-14); the publication guard passed (REL-10).
- The cost inventory shows zero or approved exceptions and the free-tier record is current (REQ-NFR-06); staging runs the same digest and its separation is verified (REQ-OPS-06).
- No `FINAL_PASS` on the candidate is invalidated (TEST_STRATEGY TS-32); the runbooks are approved (REQ-OPS-03).

**RELEASE01 — Pilot and production acceptance**
- PIL-01 to PIL-11 of UAT_PILOT section 3 after the authorized release and the cutover; the Owner's written acceptance recorded in DECISIONS (REQ-OPS-03; PRODUCT_BLUEPRINT section 12).

## 5. Requirement traceability

Every requirement of REQUIREMENTS with the suites that accept it, generated from section 3. Where the "Tests" column of REQUIREMENTS names other suites, this table prevails.

| Requirement | Suites |
| --- | --- |
| REQ-SCOPE-01 | SEARCH02, INV01, POS01 |
| REQ-SCOPE-02 | PAYEXT01, UX01 |
| REQ-SCOPE-03 | IDENTITY01 |
| REQ-ACC-01 | AUTH01 |
| REQ-ACC-02 | AUTH01, ACC01 |
| REQ-ACC-03 | AUTH01 |
| REQ-ACC-04 | AUTH02 |
| REQ-ACC-05 | LINK01, OPPROC01 |
| REQ-ACC-06 | AUTH01, AUTH03 |
| REQ-ACC-07 | SIGNIN01 |
| REQ-ACC-08 | AUTH03, INV03, RET01 |
| REQ-ACC-09 | AUTH03 |
| REQ-ACC-10 | ACC01, AUDIT01 |
| REQ-ACC-11 | ACC01 |
| REQ-ACC-12 | ACC01, OPPROC01 |
| REQ-CFG-01 | IDENTITY01 |
| REQ-CFG-02 | IDENTITY01 |
| REQ-CFG-03 | SEC01, IDENTITY01 |
| REQ-CFG-04 | AUTH03, CONFIG01 |
| REQ-CAT-01 | SEARCH01, SEARCH02 |
| REQ-CAT-02 | SEARCH02 |
| REQ-CAT-03 | SEARCH02 |
| REQ-CAT-04 | SEARCH01 |
| REQ-CAT-05 | SEARCH01 |
| REQ-CAT-06 | SEARCH02 |
| REQ-CAT-07 | SEARCH02 |
| REQ-CAT-08 | SEARCH02, POS02, POS03 |
| REQ-CAT-09 | IMPORT01 |
| REQ-CAT-10 | MEDIA01 |
| REQ-CAT-11 | SESSION08, DEVICE01 |
| REQ-CAT-12 | SEARCH02 |
| REQ-CAT-13 | BARCODE01 |
| REQ-SCAN-01 | SEARCH03, DEVICE01 |
| REQ-SCAN-02 | SEARCH03 |
| REQ-SCAN-03 | SEARCH03 |
| REQ-SCAN-04 | SEARCH03, SESSION03 |
| REQ-INV-01 | INV01 |
| REQ-INV-02 | INV02 |
| REQ-INV-03 | INV01, INV02 |
| REQ-INV-04 | INV01 |
| REQ-INV-05 | AUTH02, INV01 |
| REQ-INV-06 | INV02, FIN01 |
| REQ-INV-07 | OPEN01, REC02 |
| REQ-INV-08 | INV03 |
| REQ-INV-09 | INV03 |
| REQ-INV-10 | STOCK01 |
| REQ-INV-11 | AUTH02, INV01 |
| REQ-INV-12 | INV01, STATUS01 |
| REQ-INV-13 | INV01, FIN03 |
| REQ-COST-01 | FIN01 |
| REQ-COST-02 | FIN01 |
| REQ-COST-03 | FIN02 |
| REQ-COST-04 | AUTH02 |
| REQ-COST-05 | INV03, FIN02 |
| REQ-COST-06 | FIN03, PERF01 |
| REQ-COST-07 | FIN03, RET01 |
| REQ-COST-08 | INV03 |
| REQ-COST-09 | FIN03, SESSION07 |
| REQ-COST-10 | FIN01, POS01 |
| REQ-SALE-01 | POS01, SESSION03 |
| REQ-SALE-02 | POS02, POS03 |
| REQ-SALE-03 | FIN01, POS02 |
| REQ-SALE-04 | POS04, UX01 |
| REQ-SALE-05 | POS01 |
| REQ-SALE-06 | POS01, POS03 |
| REQ-SALE-07 | POS03 |
| REQ-SALE-08 | POS03, CMD01 |
| REQ-SALE-09 | POS01 |
| REQ-SALE-10 | SESSION09 |
| REQ-SALE-11 | POS03 |
| REQ-SALE-12 | AUTH01, SESSION10 |
| REQ-PAY-01 | POS02, PAYEXT01 |
| REQ-PAY-02 | POS02 |
| REQ-PAY-03 | POS02, PAYEXT01 |
| REQ-PAY-04 | SESSION04 |
| REQ-PAY-05 | PAYEXT01 |
| REQ-DOC-01 | SESSION08, IDENTITY01 |
| REQ-DOC-02 | SESSION08, DEVICE01 |
| REQ-DOC-03 | POS03, SESSION08 |
| REQ-DOC-04 | AUTH02, SESSION10 |
| REQ-DOC-05 | UX01 |
| REQ-CASH-01 | SESSION02 |
| REQ-CASH-02 | SESSION02 |
| REQ-CASH-03 | SESSION03 |
| REQ-CASH-04 | SESSION03 |
| REQ-CASH-05 | SESSION04 |
| REQ-CASH-06 | SESSION05, SESSION06 |
| REQ-CASH-07 | SESSION05 |
| REQ-CASH-08 | SESSION06 |
| REQ-CASH-09 | SESSION07 |
| REQ-CASH-10 | SESSION01 |
| REQ-CASH-11 | SESSION10 |
| REQ-CASH-12 | SESSION07, SESSION08, SESSION10 |
| REQ-CASH-13 | RET02, SESSION05 |
| REQ-RFD-01 | AUTH01, RET01 |
| REQ-RFD-02 | RET01 |
| REQ-RFD-03 | RET02 |
| REQ-RFD-04 | RET02 |
| REQ-RFD-05 | RET01 |
| REQ-RFD-06 | RET01 |
| REQ-RFD-07 | FIN03, RET01, SESSION09 |
| REQ-RFD-08 | RET02 |
| REQ-RFD-09 | RET02 |
| REQ-RPT-01 | FIN03 |
| REQ-RPT-02 | FIN03 |
| REQ-RPT-03 | FIN02, FIN03 |
| REQ-RPT-04 | SESSION09 |
| REQ-RPT-05 | AUTH01, AUTH02, AUDIT01, SESSION10 |
| REQ-RPT-06 | FIN03 |
| REQ-AUD-01 | AUDIT01 |
| REQ-AUD-02 | SEC01, AUDIT01 |
| REQ-CMD-01 | INV01, POS01, POS03, CMD01, SESSION06 |
| REQ-CMD-02 | INV01, RET01, SESSION06, UX01 |
| REQ-CMD-03 | REC02, OPPROC01 |
| REQ-CMD-04 | POS01 |
| REQ-OPS-01 | REC01 |
| REQ-OPS-02 | DB01 |
| REQ-OPS-03 | READY01, RELEASE01 |
| REQ-OPS-04 | STATUS01 |
| REQ-OPS-05 | DB01, KEEP01 |
| REQ-OPS-06 | SEC02, READY01 |
| REQ-NFR-01 | L10N01 |
| REQ-NFR-02 | UX01, DEVICE01 |
| REQ-NFR-03 | DEVICE01 |
| REQ-NFR-04 | PERF01 |
| REQ-NFR-05 | REC01, REC02 |
| REQ-NFR-06 | READY01 |
| REQ-NFR-07 | SEC01, SEC02 |
| REQ-NFR-08 | INV01, POS01 |
| REQ-NFR-09 | FIN03, SESSION09 |
| REQ-NFR-10 | SESSION08 |
| REQ-UX-01 | IDENTITY01, UX01 |
| REQ-UX-02 | UX01 |
| REQ-UX-03 | UX02 |

## 6. Race traceability

Every race of TECHNICAL_RULES section 8 with its suites, generated from section 3.

| Race | Suites |
| --- | --- |
| RACE-01 | INV01, POS01, POS03, SESSION06 |
| RACE-02 | POS01 |
| RACE-03 | INV02 |
| RACE-04 | AUTH03, OPEN01 |
| RACE-05 | SEARCH02 |
| RACE-06 | OPEN01 |
| RACE-07 | SESSION02 |
| RACE-08 | SESSION05 |
| RACE-09 | SESSION06, SESSION10 |
| RACE-10 | INV03, RET01 |
| RACE-11 | FIN03 |
| RACE-12 | REC02 |
| RACE-13 | RET02, SESSION05 |
| RACE-14 | RET02 |
| RACE-15 | RET02 |
| RACE-16 | ACC01 |
| RACE-17 | BARCODE01 |
| RACE-18 | BARCODE01 |
| RACE-19 | BARCODE01 |
| RACE-20 | LINK01 |
| RACE-21 | ACC01, OPPROC01 |
| RACE-22 | IMPORT01 |
| RACE-23 | SEARCH02, POS03 |
| RACE-24 | INV01 |
| RACE-25 | IDENTITY01 |
| RACE-26 | CONFIG01 |
| RACE-27 | POS03, CMD01 |
| RACE-28 | ACC01, SESSION10 |
| RACE-29 | SEARCH02 |
| RACE-30 | ACC01, OPPROC01 |
| RACE-31 | OPEN01 |
| RACE-32 | OPEN01 |
| RACE-33 | CMD01 |
| RACE-34 | REC02 |
| RACE-35 | SESSION05 |

## 7. Guard traceability

Every guard of SCHEMA section 8 with its suites, generated from section 3.

| Guard | Suites |
| --- | --- |
| G-01 | POS01 |
| G-02 | INV01, INV02 |
| G-03 | INV01 |
| G-04 | POS01 |
| G-05 | RET02, SESSION04 |
| G-06 | RET01 |
| G-07 | INV03, FIN03 |
| G-08 | ACC01 |
| G-09 | SEARCH01, BARCODE01 |
| G-10 | OPEN01 |
| G-11 | INV01, INV03 |
| G-12 | REC02 |
| G-13 | FIN01, FIN03 |
| G-14 | FIN03 |
| G-15 | FIN02 |
| G-16 | ACC01, AUDIT01 |
| G-17 | POS01, SESSION08 |
| G-18 | POS01, SESSION09 |
| G-19 | SESSION10, CONFIG01 |
| G-20 | MEDIA01, IDENTITY01 |
| G-21 | FIN03 |
| G-22 | OPEN01 |
| G-23 | SESSION03, IDENTITY01 |
| G-24 | OPEN01 |
| G-25 | CMD01 |
| G-26 | OPEN01, REC02 |
| G-27 | SESSION05 |

## 8. Threat traceability

Every threat of SECURITY_MODEL section 10 with its suites, generated from section 3.

| Threat | Suites |
| --- | --- |
| THR-01 | LINK01, SEC01 |
| THR-02 | AUTH01 |
| THR-03 | AUTH01, ACC01 |
| THR-04 | SEC01 |
| THR-05 | SEC01 |
| THR-06 | SEC01 |
| THR-07 | SEC01 |
| THR-08 | AUTH02 |
| THR-09 | SEC01, IMPORT01, MEDIA01 |
| THR-10 | SEC01, OPPROC01 |
| THR-11 | SEC01 |
| THR-12 | SEC01, MEDIA01 |
| THR-13 | SEC01 |
| THR-14 | SEC01, READY01 |
| THR-15 | SEC01, SEC02 |
| THR-16 | SEC02 |
| THR-17 | SEC02, DB01, REC01, REC02 |
| THR-18 | AUTH02, SIGNIN01 |
| THR-19 | AUTH01, RET01 |
| THR-20 | AUTH03, OPPROC01 |
| THR-21 | LINK01 |

## 9. Workflows, procedures and scheduled commands

| Workflow or command | Suites |
| --- | --- |
| WF-01 Sign in, sign out and the sign-in session | SIGNIN01, SEC01 |
| WF-02 First password and reset links | LINK01 |
| WF-03 Own account | ACC01, LINK01 |
| WF-04 Open a cash session | SESSION02, SESSION03 |
| WF-05 Sale checkout | POS01, POS02, POS04, SEARCH03 |
| WF-06 Receipt print and reprint | SESSION08 |
| WF-07 Recover a request whose outcome is uncertain | POS03, CMD01 |
| WF-08 Close one's own cash session | SESSION04, SESSION05, SESSION06, SESSION07 |
| WF-09 Owner-assisted close | SESSION05, SESSION10 |
| WF-10 Session review and Owner review notes | SESSION07 |
| WF-11 Refunds and returns | RET01, RET02 |
| WF-12 Sales and sessions: lookup, print and export | SESSION08, SESSION10 |
| WF-13 Product search and stock lookup | SEARCH02, STOCK01 |
| WF-14 Add a product (search first) | SEARCH01, SEARCH02 |
| WF-15 Edit products and categories | SEARCH02 |
| WF-16 Barcodes, photos and labels | SEARCH01, MEDIA01, SESSION08 |
| WF-17 Barcode misassignment correction | BARCODE01 |
| WF-18 CSV product import | IMPORT01 |
| WF-19 Receiving | INV01 |
| WF-20 Manual issue | INV02 |
| WF-21 Stock attention and history | STOCK01, INV01 |
| WF-22 Stock adjustment | INV03 |
| WF-23 Reversal | INV03 |
| WF-24 Start and release onboarding | OPEN01 |
| WF-25 Opening stock entry and corrections before release | OPEN01 |
| WF-26 Cost completion and correction | FIN02, FIN03 |
| WF-27 Reconciliation incidents | INV01, OPPROC01 |
| WF-28 Owner home and reports | FIN03, SESSION09 |
| WF-29 User management | ACC01, LINK01 |
| WF-30 BusinessProfile and cash registers | IDENTITY01, CONFIG01 |
| WF-31 System status, activity and security log | STATUS01, AUDIT01 |
| WF-32 Operator procedures and restore | OPPROC01, REC01, REC02 |
| OP-01 to OP-03 | OPPROC01, ACC01, LINK01 |
| OP-04, OP-05 | REC02, OPPROC01 |
| OP-06 | OPPROC01, FIN03 |
| OP-07 | OPPROC01, INV01 |
| OP-08 | OPPROC01, STATUS01 |
| SC-01 `reconciliation:run` | INV01, STATUS01 |
| SC-02 `housekeeping:run` | KEEP01, IMPORT01, MEDIA01, CMD01, AUDIT01 |
| SC-03 `backup:run` | REC01, STATUS01 |
| SC-04 `monitor:heartbeat` and RH-01 `/up` | STATUS01, SEC01 |

## 10. Checks

Counted from the documents named, not copied.

| Check | Result |
| --- | --- |
| Suites | 56: 39 legacy suites rebuilt with their identifiers, 17 new (ACC01, LINK01, SIGNIN01, SEC02, BARCODE01, IMPORT01, MEDIA01, POS04, CMD01, RET02, CONFIG01, UX02, L10N01, KEEP01, STATUS01, OPPROC01, READY01) |
| Requirements of REQUIREMENTS with at least one suite | 134 of 134 |
| Races of TECHNICAL_RULES section 8 with a suite | 35 of 35 |
| Guards of SCHEMA section 8 with a suite | 27 of 27 |
| Threats of SECURITY_MODEL section 10 with a suite | 21 of 21 |
| Workflows of CRITICAL_FLOWS with a suite (section 9) | 32 of 32 |
| User tasks of USER_TASKS with a UAT script (UAT_PILOT section 2) | 38 of 38 |
| Suites with a cases block (section 4) | 56 of 56 |
| Suites whose status is other than NOT_RUN | 0: nothing is built |
