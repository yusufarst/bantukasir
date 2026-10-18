# Technical rules

| | |
| --- | --- |
| Status | CURRENT — written in RF-3A under D-055, 4 October 2026 (Asia/Jakarta); amended in RF-5 under D-087, 18 October 2026: the JSON endpoints (TR-HTTP-01), the onboarding, opening stock, reversal and CSV rows of section 7.2, RACE-31 to RACE-34, and the limits that RF-5 set; amended in RF-6 under D-088, 18 October 2026: the close row of section 7.2 and RACE-35 (the Owner's decision on N-07), the record-check wait of TR-TIME-03, and the test references; corrected under D-089, 18 October 2026: the settlement of request receipts in section 7.2 and RACE-35 (finding B-2 of the RF-6 review) |
| Phase | P6 — concurrency, idempotency, API and performance |
| Replaces | `docs/06-api-performance/legacy/P6-CONCURRENCY-API.md` (LEGACY — SUPERSEDED); the command, transaction, lock and read sections of `docs/04-architecture/legacy/02-ARCHITECTURE.md` and `docs/04-architecture/legacy/P4-ARCHITECTURE.md` ([LEGACY_INDEX](../00-governance/LEGACY_INDEX.md) section 8) |
| Decisions | [ADR-002](../adr/ADR-002-minimum-runtime-infrastructure.md), [ADR-006](../adr/ADR-006-exact-numbers.md), [ADR-007](../adr/ADR-007-command-identity-and-idempotency.md), [ADR-008](../adr/ADR-008-cost-allocation-representation.md); D-062 |
| Related | [ARCHITECTURE](../04-architecture/ARCHITECTURE.md), [SCHEMA](../04-architecture/SCHEMA.md), [SECURITY_MODEL](../05-security/SECURITY_MODEL.md), [DOMAIN_MODEL](../02-domain/DOMAIN_MODEL.md) |

How requests, commands, transactions, locks, retries, limits and performance work. These rules enforce the domain rules of P1 and P2; they add no business meaning. Every rule names its source: a decision `D-…`, a CURRENT document, or a legacy source key from [DOMAIN_MODEL](../02-domain/DOMAIN_MODEL.md) section 2. Rules are numbered `TR-<area>-nn` and races `RACE-nn`; identifiers are permanent.

The logical database schema — tables, columns, keys and constraints — is [SCHEMA](../04-architecture/SCHEMA.md) (RF-3B). Where a rule below relies on a database guarantee, it states the guarantee; SCHEMA section 9.1 names the constraint that holds each one. The logical guarantees these rules rely on are: a barcode token is active on at most one product; a token that has ever been mapped to a product, active or retired, is never mapped again except by the Owner's misassignment correction (BR-CAT-19, BR-CAT-50, BR-CAT-51); SKUs are unique; at most one cash session is OPEN per register and per user; at most one SUPER_ADMIN account is ACTIVE (D-048); and the pairs (actor, operation, key) and (operation, intent) of ADR-007 are unique.

## 1. Words

- **Command.** A request that changes state. **Critical command**: one of the commands listed in ADR-007 section 1.
- **Definite rejection.** The server refused and wrote no business fact, projection, audit event or command result. A security log entry may be written outside the transaction (SECURITY_MODEL SEC-LOG-03). The one exception is the mark that affected reporting is incomplete after a cost correction is refused because its replay cannot be done safely: the mark and its audit event are written after the rollback, in a transaction of their own; they record the refusal and change no business fact, projection or result of the refused command (TR-TIME-01; SCHEMA section 5.8).
- **UNCERTAIN.** The client does not know whether the command committed (BR-CMD-03).
- **Lock.** A PostgreSQL row lock taken inside the command's transaction. "Update lock" means the row may change or must not be changed by anyone else during the command; "share lock" means it must only not change.

## 2. HTTP contract

| ID | Rule | Source |
| --- | --- | --- |
| TR-HTTP-01 | BantuKasir is one same-origin web application: Laravel routes render Inertia pages. JSON endpoints exist only where a page needs data or a result without a navigation: product search and scan lookup, including the search-first check and the barcode token lookup; the stock attention refresh and the shell indicators read with it; command submission with client-managed states; command resolution and the listing of unresolved request receipts (ADR-009); the CSV import preview and its cancel; and the issuing of a one-time link, whose response carries the link once (SECURITY_MODEL SEC-ACC-05). [ROUTE_CONTRACTS](../03-workflows/ROUTE_CONTRACTS.md) sections 3 to 6 list them. There is no public API and no token-based API. | L-P6 §Kontrak HTTP, AICWDF §4A.7, ADR-004 |
| TR-HTTP-02 | `GET` and `HEAD` never change state. Anything that writes — including the audit event of a print, reprint or export request (BR-AUD-06, BR-DOC-04) — is a `POST`. Rendering a document for the browser's print dialog is a read. | L-P6, BR-DOM-09, SECURITY_MODEL section 12 |
| TR-HTTP-03 | Request pipeline: middleware (authentication, active account, sign-in session limits, CSRF, rate limit, recent authentication where required) → FormRequest (policy and object scope; validation allowlist) → Action (one transaction, in-transaction guards) → response built from an allowlisted DTO. Controllers contain no business logic. | L-P6, L-P4 §Struktur modular minimal, AICWDF §15 |
| TR-HTTP-04 | Commands are `POST` requests carrying the envelope of ADR-007. There is no `DELETE` route: nothing is deleted (D-042, D-044); removing a product photo or retiring a barcode is a command. | ADR-007, D-042 |
| TR-HTTP-05 | A command submitted by an Inertia form ends in a redirect to a page that shows the result; a JSON command endpoint returns the result. The meaning of each outcome (section 4) is the same on both paths. A one-time link is never delivered through a redirect, flashed data, page props or a URL (SECURITY_MODEL SEC-ACC-05). | L-P6 |
| TR-HTTP-06 | Inertia's own protocol responses (for example on an asset version change) keep their framework meaning and are never reused for a domain outcome. | Inertia protocol; this document |
| TR-HTTP-07 | Every list, search, history, report, document, media, export and status request is authorized and scoped again (SECURITY_MODEL section 5) and is paged or otherwise bounded (sections 11 and 12). No endpoint returns an unbounded collection or the whole product master. | L-P6, BR-CAT-25, BR-DOC-06 |
| TR-HTTP-08 | The server sets every fact time in UTC. A time sent by the client is never a fact; an optional document date is stored separately and never backdates a fact. | BR-NUM-05, L-02 |
| TR-HTTP-09 | Authenticated responses are not cacheable by shared caches and are not stored by the browser, except media (SECURITY_MODEL section 7). | L-04, L-P4 §History Inertia |
| TR-HTTP-10 | Requests the client sends without a user action — the stock attention refresh and automatic repeats of a resolution — never count as user activity for the idle limit of the sign-in session (SECURITY_MODEL SEC-SESS-02). | D-039, BR-INV-41 |

## 3. Command contract

| ID | Rule | Source |
| --- | --- | --- |
| TR-CMD-01 | Every command carries the envelope of ADR-007 section 1: operation and version, source intent, idempotency key, recovery epoch, expected versions and the business payload. The server derives the actor and the fingerprint. | ADR-007, BR-CMD-01, D-006 |
| TR-CMD-02 | The identity outcomes are those of ADR-007 section 3; the resolution of an uncertain outcome is ADR-007 section 4. | ADR-007, BR-CMD-02, BR-CMD-03 |
| TR-CMD-03 | Drafts, scans, quantity entry, previews and reviews create no business fact. A preview that needs an upload (the CSV import) keeps only temporary working data (D-054; SECURITY_MODEL section 13). | BR-DOM-09, D-054 |
| TR-CMD-04 | Every critical mutation has a review and an explicit confirmation before its command is sent, except ordinary Sale checkout, which is confirmed on one payment screen with one final action. | BR-CMD-04, D-040 |
| TR-CMD-05 | The final action of a command is never triggered by a scanner terminator, and the client blocks edits and further submissions while a command is submitting. | BR-SALE-13, BR-SALE-18, BR-CAT-32 |
| TR-CMD-06 | Every successful command writes its audit event in its own transaction; a failed command writes none, except the audit event that accompanies the mark of section 1 (TR-TIME-01). | BR-AUD-01, BR-DOM-04 |
| TR-CMD-07 | Authentication endpoints — sign-in, sign-out, password confirmation, own password change and one-time link consumption — and operator procedures carry no command envelope. The endpoints are protected by CSRF, throttling and their own locks (section 7.2); operator procedures are console commands (SECURITY_MODEL section 14). | ADR-007, ADR-004 |

## 4. Error mapping

Every refusal carries a stable error code in `UPPER_SNAKE_CASE`, a translation key for its Indonesian message (ADR-005) and only safe parameters. The codes below are technical identifiers; the words the shop sees are approved at gate P7-G2.

| Situation | JSON endpoint | Inertia form submission | Example codes | Client state |
| --- | --- | --- | --- | --- |
| Committed, or the original result returned for a repeated identity | 200 or 201 | Redirect to the result | — | COMMITTED |
| Not signed in, or the sign-in session ended | 401 | Redirect to sign-in | `UNAUTHENTICATED` | Nothing ran; after signing in, the stored command reference is resolved (it yields NOT_COMMITTED) and the user starts again (ADR-007 section 5) |
| CSRF check failed | Framework response | Framework response | `CSRF_FAILED` | Nothing ran; reload |
| Recent authentication required | Framework password-confirmation response | Redirect to confirmation | `RECENT_AUTH_REQUIRED` | Nothing ran; confirm, then repeat |
| Capability missing for the role | 403 | Error page | `FORBIDDEN` | REJECTED |
| Object does not exist or is outside the actor's scope (one response for both) | 404 | Error page | `NOT_FOUND` | REJECTED |
| Identity conflict, or an intent created before a restore | 409 | Validation-error channel | `IDEMPOTENCY_CONFLICT`, `EPOCH_STALE` | HELD, a form of UNCERTAIN that is never repeated — the original may exist; no new attempt and no replacement. `IDEMPOTENCY_CONFLICT`: the intent is resolved by its actor or the Owner (ADR-007 section 4). `EPOCH_STALE`: the Owner checks it under the restore runbook (RF-6). The customer is never asked to pay again before then (BR-SALE-20) |
| Intent fenced by a resolution | 409 | Validation-error channel | `INTENT_ABANDONED` | REJECTED — the original can never commit; a new draft may be started |
| Version conflict | 409 | Validation-error channel | `STALE_VERSION`, `PRICE_CHANGED`, `RECOUNT_REQUIRED` | REJECTED; review or refresh |
| Invalid input, or a domain rule refused the command before any fact | 422 | Validation-error channel | `VALIDATION_FAILED`, `INSUFFICIENT_STOCK`, `PRODUCT_INACTIVE`, `CATEGORY_INACTIVE`, `NO_OPEN_SESSION`, `SESSION_NOT_OPEN`, `ONBOARDING_FREEZE`, `POSTING_BLOCKED`, `METHOD_NOT_ALLOWED`, `TENDER_INSUFFICIENT`, `REFUND_BOUND_EXCEEDED`, `RETURN_BOUND_EXCEEDED`, `DRAWER_CASH_INSUFFICIENT`, `BARCODE_NOT_ELIGIBLE`, `DUPLICATE_SKU`, `DUPLICATE_BARCODE`, `CSV_INVALID`, `REPLAY_REJECTED`, `LINK_INVALID` | REJECTED; review |
| Rate limit reached | 429 with `Retry-After` | Error message | `THROTTLED` | Nothing ran; wait |
| Retries exhausted or lock wait exceeded | 503 | Error message | `SERVICE_BUSY` | UNCERTAIN; repeat the same identity |
| Any other 5xx, timeout or lost connection | — | — | — | UNCERTAIN (BR-CMD-03) |

| ID | Rule | Source |
| --- | --- | --- |
| TR-ERR-01 | A 5xx response, a timeout or a lost connection is never a definite failure. | L-P6, BR-CMD-03 |
| TR-ERR-02 | Error responses contain no stack trace, SQL, file path, secret, object of another scope, or value the role may not see (no cost for staff beyond D-033, no profit). Debug output is off in every non-local environment. | L-P6, L-04, L-P5 |
| TR-ERR-03 | A missing object and an object outside the actor's scope give the same response, so that existence cannot be probed. | L-04, REQ-ACC-03 |
| TR-ERR-04 | Sign-in, one-time link and recent-authentication failures give uniform messages that never reveal whether an account, a link or an email exists. | L-P5, D-049 |
| TR-ERR-05 | A definite rejection never changes the payable total silently; the user returns to an explicit review state. | BR-SALE-19, D-040 |
| TR-ERR-06 | The client never turns HELD or UNCERTAIN into REJECTED by itself, and never creates a new intent for the same business action while either lasts. | BR-CMD-03, BR-SALE-20, D-040 |

## 5. Validation and allowlists

| ID | Rule | Source |
| --- | --- | --- |
| TR-VAL-01 | Each route has a FormRequest that lists every accepted field with its type, format and limits. A command with an unknown field is refused, not silently trimmed. | L-P6, L-P4 |
| TR-VAL-02 | Only validated data reaches an Action. Models are never filled from raw request input; mass assignment uses explicit allowlists. | L-P5, AICWDF threat classes |
| TR-VAL-03 | The server never accepts from the client, as an authoritative value: the actor or the actor's own role; the responsible cashier or session owner; the price applied to a Sale line, line and Sale totals, change or expected cash; a derived cost (allocation, unit or average cost, valuation) or the cost scope of a response; a business date, a fact time, a sequence number or a fingerprint. Values that a capability exists to enter are validated inputs, never derived results: a selling price in master data (CAP-05), an acquisition cost on a receiving document or in opening stock (CAP-11, CAP-15), a corrected cost (CAP-19), tendered, opening and counted cash, and the roles the Owner assigns to a staff account (CAP-25, CAP-27). | BR-DOM-06, BR-SALE-04, L-P3; SECURITY_MODEL section 6 |
| TR-VAL-04 | Enumerations are allowlisted on the server: payment methods (CASH, BANK_TRANSFER), stock movement types, manual issue reasons, barcode retirement reason classes (misassignment or other reason), refund funding source, return condition and the three roles. | D-036, D-077, BR-INV-19, D-052, D-035, D-032 |
| TR-VAL-05 | Text must be valid UTF-8 and is refused if it contains control characters (a line break is allowed only in multi-line notes). Outer whitespace is trimmed, except in passwords and one-time link tokens, which are never altered; barcode tokens follow BR-CAT-17. A SKU is trimmed like other text, keeps its letter case and is unique without regard to letter case (BR-CAT-05). Lengths are those of section 11. | BR-CAT-17, BR-CAT-05, L-P6 |
| TR-VAL-06 | Numbers are canonical strings validated for scale and range before any arithmetic, lock or write (ADR-006). | BR-NUM-04, ADR-006 |
| TR-VAL-07 | Sort and filter fields are allowlisted per list. All SQL uses bound parameters; no SQL fragment is built from input. | L-P5, SECURITY_MODEL THR-04 |
| TR-VAL-08 | A rule that depends on current state — eligibility, active flags, versions, stock, money and quantity bounds, expected cash — is validated again inside the transaction, after the locks of section 7 are held. A check made when the page was opened is never enough. | L-P4 §Transaksi, L-02, BR-INV-06 |

## 6. Transaction boundaries

| ID | Rule | Source |
| --- | --- | --- |
| TR-TX-01 | One command is one Action and one database transaction. Its facts, projections, audit event and command result commit together, or nothing is written. | BR-DOM-04, D-006, L-02 |
| TR-TX-02 | Inside that transaction no component opens its own connection or commits on its own; Eloquent observers, model events and listeners carry no business effect; nothing is queued (ADR-002). | L-P4 §Struktur modular minimal |
| TR-TX-03 | **No human action, printer or network wait occurs inside a database transaction or while a database lock is held.** Customer payment, bank transfer, counting cash, physical cash handling — including taking the cash of a drawer-funded refund from the drawer, which happens only after the refund has committed — and printing all happen outside it. A transaction never spans two HTTP requests and never waits for the browser. | BR-DOM-10, BR-SALE-21, BR-RFD-14, BR-RFD-17, D-046, L-02 |
| TR-TX-04 | No external network call is made inside a transaction. V1 has none: no payment gateway, no mail, no remote fetch (D-036, D-049). | L-P6, D-036, D-049 |
| TR-TX-05 | A file is written outside the transaction: the upload is validated and stored under a new random key first; the transaction then records the reference. If the transaction rolls back, the unreferenced file is removed by bounded housekeeping, which removes only a file that no record references and that is older than a grace period well above the longest request (at least one hour), so a file whose transaction is still running is never removed. A stored file is never overwritten. | ARCHITECTURE section 6, D-042, BR-CFG-03 |
| TR-TX-06 | Commands run at READ COMMITTED with the explicit locks of section 7. | L-02, L-P4, L-P6 |
| TR-TX-07 | A report runs in one read-only REPEATABLE READ transaction, so every figure comes from the same snapshot, which is the report's as-of time. A failed report query is shown as an error, never as zero. | BR-COST-38, REQ-RPT-03 |
| TR-TX-08 | An expected uniqueness conflict (a duplicate identity, SKU or barcode) is handled after the transaction has rolled back: the original result is read, or the conflict code is returned. An aborted PostgreSQL transaction is never continued. | L-P4 §Transaksi |
| TR-TX-09 | A sign-in session ending during a command does not affect that command's transaction; the next request is unauthenticated. | BR-USR-06 |
| TR-TX-10 | A Sale number and a cash session number are taken from their sequence inside the committing transaction, after every guard has passed and immediately before the record is inserted, never earlier. Numbers follow allocation order, not commit order; no lock serializes commands for numbering; a gap left by a rolled-back or failed transaction is valid and is never reported, filled or reused; the date part is not a chronological sort key (SCHEMA section 6). | D-062, D-063 |

## 7. Lock order

### 7.1 The order

Every command takes its locks in this order and only in this order (L-02 §Urutan lock, L-P6 §Matriks race; extended for D-035, D-043, D-045, D-046, D-048, D-049, D-051 and D-052):

1. **Command identity** — the identity record of ADR-007.
2. **Actor and configuration guards**, in this sub-order:
   1. the acting user's account (share lock);
   2. for user-management commands, the target account (update lock);
   3. installation state: recovery epoch and onboarding state;
   4. the current BusinessProfile pointer, when the command freezes the shop identity in a snapshot (share lock) or changes it (update lock).
3. **Cash register** — opening a session; register configuration.
4. **Cash session** — the actor's own OPEN session (checkout, close), the target session (Owner-assisted close), or the OPEN session selected as the funding source of a drawer-funded refund.
5. **Original Sale** — refund and return.
6. **Catalogue rows**: first the categories that the command assigns to a product (share lock) or whose name or lifecycle it changes (update lock), in ascending order; then the **products**, in ascending internal identifier order — the product guard: active flag, price revision, unit and precision lock, posting block and the product's barcode mappings. In section 7.2, "6" alone means the products.
7. **Stock balance and cost projections** of those products, in the same ascending order.
8. **Append** facts, audit event and command result; commit.

| ID | Rule | Source |
| --- | --- | --- |
| TR-LOCK-01 | A command skips the steps that do not apply but never takes a lock of an earlier step after a lock of a later step. Several rows in one step are locked in ascending internal identifier order. | L-02, L-P6 |
| TR-LOCK-02 | A writer of lifecycle or configuration — deactivation, role change, onboarding flag, BusinessProfile, cash register, price, product active flag, unit or precision — takes the same guard as the commands it could invalidate, so that the change that wins is the one in force at validation. | BR-USR-05, REQ-ACC-09, L-04, L-P6 |
| TR-LOCK-03 | Lock waits are bounded by the lock timeout of section 9. A command that cannot obtain its locks in time fails with `SERVICE_BUSY` and writes nothing. | L-P6 |
| TR-LOCK-04 | Because every command family follows the same order, a cycle of waits cannot form between families. A deadlock that still occurs is retried under section 9. | L-02 |
| TR-LOCK-05 | When the target of step 2.2 is the actor's own account (own password change; the Owner changing the Owner's own display name or email), the account is locked once, with the update lock of step 2.2; a share lock is never followed by an update lock on the same row. | this document; L-02 |

### 7.2 Steps per command family

| Command family | Steps, in order | Checked under the locks |
| --- | --- | --- |
| Sale checkout | 1; 2.1; 2.3 (onboarding, share); 2.4 (share); 4 own OPEN session (update; activity version + 1); 6; 7; 8 | Actor active and allowed to sell; session OPEN and owned by the actor; onboarding off; each product active, not blocked, price revision as reviewed; stock sufficient for the aggregated lines; method allowed; tendered cash at least the total (BR-SALE-04) |
| Open cash session | 1; 2.1; 3 register (update); 8 | Register active; no OPEN session on the register or for the user (BR-CASH-05, BR-CASH-06); a BusinessProfile revision exists (SCHEMA section 5.2) |
| Close, own or Owner-assisted | 1; 2.1; 2.4 (share); 4 session (update); 8 | Session OPEN; activity version equals the reviewed one; the session's responsible user has no `sale.checkout` request receipt whose outcome is PENDING or HELD, read after the session lock, else `RECOUNT_REQUIRED` (G-27; D-088); a receipt settled by a conclusive status check or a restore reconciliation no longer counts (SCHEMA section 5.3; D-089); for Owner-assisted close, recent authentication and reason. Totals and expected cash are derived from facts under the session lock (BR-CASH-13, BR-CASH-16, BR-CASH-18) |
| Drawer-funded refund or return | 1; 2.1; 2.3 (onboarding, share) when a saleable return is received; 4 selected session (update; activity version + 1); 5 original Sale (update); 6 returned products; 7; 8 | Session OPEN; amount at most the session's expected cash computed under its lock (D-045); cumulative money and quantity bounds of the original Sale; recent authentication; reason; onboarding off, and each returned product not blocked, when a saleable return posts a RETURN_RECEIPT (BR-INV-11, BR-INV-24, BR-INV-45). Cash is taken from the drawer only after commit (D-046) |
| Owner-funded or BANK_TRANSFER refund or return | 1; 2.1; 2.3 (onboarding, share) when a saleable return is received; 5 original Sale (update); 6; 7; 8 | Bounds; recent authentication; reason; onboarding off, and each returned product not blocked, when a saleable return posts a RETURN_RECEIPT (BR-INV-11, BR-INV-24, BR-INV-45). No session is touched (D-035) |
| Receiving confirmation, manual issue, adjustment | 1; 2.1; 2.3 (onboarding, share); 6; 7; 8 | Onboarding off; products active and not blocked; stock not negative after issue (aggregated lines); versions; reason where required (BR-INV-06) |
| Opening stock | 1; 2.1; 2.3 (onboarding, share); 6; 7; 8 | Onboarding `ACTIVE` without an unreconciled restore hold (D-072); each product has no effective onboarding entry (G-22); an original OPENING only for a product with no earlier movement; at most 100 rows (BR-INV-24 to BR-INV-27). The withdrawal of a zero count and the replacement of a reversed OPENING belong to this family (D-087): the Owner, recent authentication and a reason; a replacement names a reversed opening line not yet replaced, for a product whose movements are only OPENINGs and their reversals (BR-INV-48, G-24) |
| Reversal | 1; 2.1; 2.3 (onboarding, share); 6; 7; 8 | Source eligible and not reversed; product not blocked (SCHEMA guard G-11); replay safe (BR-INV-34 to BR-INV-37); clears the active marks of the reversed source's cost evidence (SCHEMA section 5.8); onboarding off for the reversal of a RECEIPT or MANUAL_ISSUE (BR-INV-24); an OPENING may also be reversed while onboarding is on, to correct it before the release (D-087) |
| Cost correction or completion | 1; 2.1; 6; 7; 8 | Owner; recent authentication; reason; product not blocked (SCHEMA guard G-11); bounded replay of section 9 under the product guard (BR-COST-23 to BR-COST-27); clears the active marks of the same cost source (SCHEMA section 5.8) |
| Product creation | 1; 2.1; 6 category (share); 8 | Category active (BR-CAT-09); SKU unique; every barcode passes the checks of a barcode add |
| Price change; product edit, including minimum stock and category; product deactivation or reactivation | 1; 2.1; 6 newly chosen category (share) when the category changes, then the product (update); 8 | Unit and precision unchanged after the first movement (BR-CAT-07); price revision appended (BR-CAT-13); a newly chosen category active (BR-CAT-09) |
| Category create, rename, deactivate or reactivate | 1; 2.1; 6 category (update; nothing to lock for a creation); 8 | Name within the limits of section 11; deactivation changes no product (BR-CAT-09) |
| Product photo upload, replace or remove | 1; 2.1; 6 product (update); 8 | Product exists; the file was validated and stored before the transaction (TR-TX-05); a replaced or removed file is kept (D-042) |
| Barcode add, generate, retire | 1; 2.1; 6 (update); 8 | An added token has never been mapped to any product, active or retired (BR-CAT-19); a manufacturer token does not use the `BK-` namespace and a generated `BK-P-…` code has never been used (BR-CAT-18); a retirement records its reason class (BR-CAT-16 to BR-CAT-19, BR-CAT-52, D-052). Only the misassignment correction re-attaches a retired token |
| Barcode misassignment correction (move or re-attach) | 1; 2.1; 6 both products, ascending (update); 8 | Owner; recent authentication; reason; manufacturer token only; traceable prior mapping; for a re-attachment, retirement recorded as misassignment; at most one active mapping after commit (D-051, D-052) |
| CSV import confirmation | 1; 2.1; 6 categories named by the rows (share, ascending); 8 | Re-validation of the whole file against the previewed checksum; each named existing category is active; each row's category name matches exactly one active category as DOCUMENT_CONTRACTS DC-04 defines, at confirmation as at preview (D-069 (h)); SKUs unique; every barcode passes the checks of a barcode add, including the `BK-` refusal; all rows or none (BR-CAT-38) |
| Onboarding set or release | 1; 2.1; 2.3 (update); 8 | Owner; recent authentication (BR-INV-24, BR-INV-28); start only from `NOT_STARTED`, with no release record and no unreconciled restore hold; release only from `ACTIVE` without such a hold; the release record makes it final (D-071, D-072; G-26) |
| BusinessProfile change | 1; 2.1; 2.4 (update); 8 | Owner; recent authentication (BR-CFG-02) |
| Cash register configuration | 1; 2.1; 3 (update); 8 | No OPEN session on the register; Owner; recent authentication (BR-CASH-02) |
| User management (create, role change, deactivate, reactivate, anonymize, issue or revoke a link) | 1; 2.1; 2.2 target (update); 8 | Owner; recent authentication; never creates a SUPER_ADMIN account, assigns or removes SUPER_ADMIN, deactivates or anonymizes the active Owner account, or reactivates any SUPER_ADMIN account (D-048, BR-USR-04); anonymizes only a deactivated staff account or a former Owner account deactivated by the replacement procedure (D-043, D-048); deactivation, role change and anonymization revoke the target's sign-in sessions and links, and an email change revokes its link, in the same transaction (BR-USR-05, SECURITY_MODEL SEC-ACC-04) |
| One-time link consumption (signed out) | 2.2 target account (update); 8 | Token exists, unexpired, unused; account ACTIVE and not anonymized; password profile; token removed and sign-in sessions revoked in the same transaction (D-049) |
| Own password change (signed in) | 2.2 own account (update, TR-LOCK-05); 8 | Current password; AICWDF-COMPAT-8; the user's other sign-in sessions revoked (SECURITY_MODEL SEC-AUTH-11) |
| Reconciliation block (scheduled command) or reopening (Owner) | 1; 2.1 for a reopening; 6 (update); 8 | Reopening by the Owner with recent authentication (BR-INV-45, BR-INV-46) |
| Owner review note on a CLOSED session | 1; 2.1; 4 session (share); 8 | Owner; session CLOSED; count, variance and snapshot unchanged (BR-CASH-19). Whether a note can be edited is decided by D-069 (T-17 f): it cannot; a correction is a new note |
| Print, reprint or export request | 1; 2.1; 8 | Capability and object scope of the document; writes only its audit event (BR-AUD-06) |
| Resolution of an uncertain intent | 1; with a fence it writes, the settlement of every PENDING or HELD receipt of the operation and intent, in ascending order, in the same transaction; after it reads a committed identity or an existing fence, that settlement in a transaction of its own; after an `EPOCH_STALE` answer, the stale-intent report and the HELD marking of the intent's PENDING receipts in a transaction of its own. No session lock | ADR-007 section 4; ADR-009 sections 1 and 3; D-089 |
| Request receipt | Its own short transaction, after the middleware and before step 1 of the command; its outcome marked in another after the command ends, replacing only PENDING | ADR-009 section 1; G-25 |
| Bootstrap (operator procedure) | 2.3 installation state (update); 8 | No account of any kind exists; after commit exactly one ACTIVE SUPER_ADMIN account (SECURITY_MODEL SEC-OP-01) |
| Owner recovery (operator procedure) | 2.2 Owner account (update); 8 | It is the one ACTIVE SUPER_ADMIN account (SEC-OP-02) |
| Owner replacement (operator procedure) | 2.2 outgoing Owner account, named by its internal identifier (update); 8 | The named account is the one ACTIVE SUPER_ADMIN account; after commit exactly one is active; a repeated run finds it DEACTIVATED and is refused; an OPEN cash session of the outgoing Owner is not changed and stays OPEN for an Owner-assisted close (SEC-OP-03, D-087) |
| After a restore (operator procedure) | 2.3 installation state (update); 8 | A recovery epoch never issued before; every sign-in session revoked; an onboarding restore hold when the restored state is not `RELEASED` (SEC-OP-04, D-072) |
| Restore reconciliation (operator procedure) | 1 (the fence); 2.3 installation state (update) for an onboarding hold; 8 | The Owner's decision for each reported intent (not committed, with a fence in the current epoch) or for the onboarding hold; audit; the settlement of the intent's PENDING or HELD receipts in the same transaction (SEC-OP-05; D-072, D-073; ADR-009 sections 1 and 3) |

Authentication endpoints and operator procedures carry no command envelope, so they have no step 1 (TR-CMD-07).

## 8. Race matrix

The required outcome of every known race. Each is proven on real PostgreSQL with at least two independent connections and committed fixtures (L-P6 §Matriks race; [TEST_STRATEGY](../08-testing/TEST_STRATEGY.md) TS-20). In addition, every write domain has a failure-injection test: a failure injected after each write step of a command leaves no fact, projection, audit event or command result behind (L-P6; TS-21). The test references below were written before RF-6; the suites of every race are [ACCEPTANCE_SUITES](../08-testing/ACCEPTANCE_SUITES.md) section 6, which prevails where they differ (D-088).

| ID | Race | Serialization point | Required outcome | Source | Test reference |
| --- | --- | --- | --- | --- | --- |
| RACE-01 | Same idempotency key sent twice, with the same or a different payload | Command identity (step 1) | Same payload: one execution, both receive the original result. Different payload: `IDEMPOTENCY_CONFLICT`, nothing written | ADR-007, BR-CMD-02, L-P6 | POS01, POS03, INV01, SESSION06 |
| RACE-02 | Different keys with the same source intent | Command identity | At most one execution; the same fingerprint receives the original result, a different one `IDEMPOTENCY_CONFLICT` | ADR-007, BR-SALE-18, L-P6 | POS01 |
| RACE-03 | Two Sales, or a Sale and a manual issue, for the last unit | Product guard and balance (steps 6, 7) | Exactly one succeeds; the other is refused `INSUFFICIENT_STOCK`; stock never negative | BR-INV-04, L-P6 | INV02 |
| RACE-04 | Deactivation, role change, onboarding freeze or a configuration change against a running command | Actor, target and configuration guards (step 2) | The change that commits first is the one in force at validation; a command that is no longer allowed writes no fact | BR-USR-05, REQ-ACC-09, L-P6 | AUTH03, OPEN01 |
| RACE-05 | Edit of base unit or precision against the product's first movement | Product guard | Either the edit commits before any movement, or it is refused; posted facts never change meaning | BR-CAT-07, L-P6 | SEARCH02 |
| RACE-06 | Opening stock with duplicate rows, duplicate commands, or a product that gains a movement meanwhile | Onboarding guard and product guards | An original OPENING posted once per product with no earlier movement; at most 100 rows; all or nothing; zero counts only on the checklist; at most one effective entry per product (G-22) | BR-INV-25, BR-INV-26, L-P6, D-087 | OPEN01 |
| RACE-07 | Two opens of the same register, or two opens by the same user | Register lock and the database uniqueness of OPEN sessions | At most one OPEN session per register and per user | BR-CASH-06, L-P6 | SESSION02 |
| RACE-08 | Sale against close of the same session | Session lock and activity version | Sale first: it commits once and the close needs a recount (`RECOUNT_REQUIRED`). Close first: the Sale is refused `SESSION_NOT_OPEN` before any fact. No successful Sale is missing from its report | BR-CASH-15, L-P6 | SESSION05 |
| RACE-09 | Repeated close, close with a different count, or Owner-assisted close against the user's own close | Command identity and session lock | One CLOSED session, one snapshot, one result; a repeat with the same identity returns it; any other close is refused `SESSION_NOT_OPEN`; a different count never overwrites | BR-CASH-17, BR-CASH-18, L-P6 | SESSION06, SESSION10 |
| RACE-10 | Concurrent refunds or returns on the same Sale; two reversals of the same source | Original Sale lock (step 5); product guard for reversals | Cumulative money and quantity bounds hold separately; a reversal happens at most once | BR-RFD-03, BR-INV-35, L-P6 | RET01, INV03 |
| RACE-11 | Cost-correction replay against postings of the same product, or against a return that restores cost | Product guard (step 6) held for the whole replay | Other postings of the product wait within the lock timeout or are refused `SERVICE_BUSY`; publication is all or nothing; unsafe chronology is refused; cost is restored exactly once. The replay never touches allocation facts; it rewrites only projections and valuations, recomputing each linked return projection together with its issue (ADR-008) | BR-COST-18, BR-COST-24 to BR-COST-26, D-034, D-060, D-061 | FIN03 |
| RACE-12 | Restore against stale browser intents | Recovery epoch, read without a lock: the restore runbook keeps traffic off until the after-restore procedure has replaced it (SCHEMA section 5.3) | Sign-in sessions revoked; a command whose epoch differs from the current one is refused `EPOCH_STALE`, also after the same backup is restored twice; a result missing from the restored data is not proof that the original never committed | BR-CMD-06, L-P6 | REC02 |
| RACE-13 | Drawer-funded refund against close of the selected session | Session lock and activity version | Refund first: activity version increases; a close sent with the earlier version is refused `RECOUNT_REQUIRED` and the session stays OPEN; the refund's cash is taken from the drawer after the refund commits and before the session is counted again. Close first: the refund naming that session is refused `SESSION_NOT_OPEN` before any fact and no cash leaves the drawer; the Owner may select another OPEN session with enough expected cash or the Owner's own funds. No cash is handled under the lock. A committed refund whose cash was not yet taken when the session is counted and closed (D-068): the session closes with a variance of +R and its report as recorded; no fact, column or cash adjustment is created; a payout against the CLOSED session stays refused (CRITICAL_FLOWS WF-08, WF-10) | D-035, D-045, D-046, D-068, BR-CASH-14, BR-RFD-15 to BR-RFD-17 | NEW (extends SESSION05) |
| RACE-14 | Two drawer-funded refunds, or a refund and a Sale, against the same session's expected cash | Session lock | Expected cash is computed from the session's committed facts under the lock; each refund is checked against it at that moment; expected cash never becomes negative; a refund that no longer fits is refused `DRAWER_CASH_INSUFFICIENT` and the source is never switched or split | D-045, BR-RFD-15, BR-RFD-16 | NEW |
| RACE-15 | Uncertain outcome of a drawer-funded refund against the physical payout | Command identity and resolution (ADR-007) | No cash leaves the drawer until the original refund is known to be COMMITTED; if resolution returns NOT_COMMITTED, no cash leaves and the Owner may start a new refund; a replacement refund is never created while the outcome is uncertain | D-046, BR-RFD-17, BR-CASH-16 | NEW |
| RACE-16 | Anonymization against historical display, reprint and reactivation | Target account lock | Anonymization requires DEACTIVATED and changes only the current account profile; reactivation requires DEACTIVATED and not ANONYMIZED; receipts, session reports and audit show the display information frozen when they were created, so a reprint is identical before and after (SCHEMA section 3.5) | D-043, BR-USR-11, BR-USR-12 | NEW |
| RACE-17 | Owner move of an active manufacturer token against another attach of the same token | Both product guards and the database guarantee of at most one active mapping per token | Exactly one succeeds; the other is refused `DUPLICATE_BARCODE` or `BARCODE_NOT_ELIGIBLE`; a token is never active on two products | D-051, BR-CAT-16 | NEW (extends SEARCH01) |
| RACE-18 | Move or re-attachment against a scan lookup | The correction's single transaction | A lookup resolves the token to the product before or after the correction, never to none and never to both; Sale lines reference products, not tokens, so earlier Sales are unaffected; a cart line taken before the move keeps its product and checkout validates it as usual | D-051, D-052, BR-CAT-30 | NEW |
| RACE-19 | Two misassignment corrections of the same retired token, or a correction against an ordinary attach of that token | Product guards and the active-mapping guarantee | The first correction wins; the second finds the token active and is refused; an ordinary attach of a retired token is always refused | D-052, BR-CAT-19 | NEW |
| RACE-20 | One-time link consumption against revocation, re-issue or deactivation; the same link used twice | Target account lock | Whichever commits first wins: a link revoked, replaced or belonging to a deactivated account is refused `LINK_INVALID` with a uniform message; a link is consumed at most once | D-049, SECURITY_MODEL section 3 | NEW (extends AUTH01) |
| RACE-21 | Owner replacement procedure against commands of the outgoing Owner | Outgoing Owner's account lock | A command validated before the replacement commits normally; after it, the outgoing account is deactivated and its commands are refused before any fact; after commit exactly one SUPER_ADMIN account is active | D-048, BR-USR-05 | NEW |
| RACE-22 | CSV import confirmation against creation of a product with the same SKU or barcode; the confirmation sent twice | Command identity and the uniqueness of SKUs and tokens | A conflict at confirmation refuses the whole import with nothing created; a repeated confirmation with the same identity returns the original result; the confirmation is tied to the previewed file by its checksum | D-037, D-054, BR-CAT-38, BR-CAT-40 | NEW |
| RACE-23 | Price change or product deactivation against a Sale whose cart is open | Product guard | Change first: the Sale is refused `PRICE_CHANGED` or `PRODUCT_INACTIVE` and the user reviews; Sale first: the change applies to later Sales; the Sale line keeps the price it applied | D-036, BR-SALE-05, BR-CAT-08 | POS02, POS03 |
| RACE-24 | Reconciliation posting block against a posting | Product guard | Block first: the posting is refused `POSTING_BLOCKED`; posting first: reconciliation sees it | BR-INV-45 | INV01 |
| RACE-25 | BusinessProfile change against a Sale or close that freezes the identity | BusinessProfile pointer (step 2.4) | Each snapshot uses exactly one revision, the one current when its lock was taken | D-038, BR-CFG-03 | IDENTITY01 |
| RACE-26 | Cash register configuration against a session open | Register lock | A configuration change is refused while a session is OPEN on the register; an open and a change never interleave | BR-CASH-02 | NEW |
| RACE-27 | Resolution fence against a late original | Command identity | Fence first: the original is refused `INTENT_ABANDONED` before any fact; original first: resolution reports COMMITTED | ADR-007 | NEW |
| RACE-28 | Deactivation of a user who holds an OPEN cash session | Target account lock | Deactivation never closes, transfers or changes the session (BR-CASH-11); Owner-assisted close stays available. A staff account may be deactivated before its session is closed (D-069 (d)), and the Owner replacement procedure may run while the outgoing Owner holds an OPEN session (D-087). Tests: the session stays OPEN and unchanged; every command of the deactivated account is refused, also one racing the deactivation (RACE-04); the Owner-assisted close then succeeds (CRITICAL_FLOWS WF-09) | BR-CASH-11, D-069, D-087 | NEW |
| RACE-31 | Two onboarding entries for one product: rows of two commands, a zero count and a positive row, a withdrawal and a new entry, two replacements of the same reversed line | Onboarding share lock and product guard | At most one effective entry per product (G-22); a line is replaced at most once; a zero count is withdrawn at most once; the loser is refused `VALIDATION_FAILED` or `STALE_VERSION` with nothing written | D-087 | NEW |
| RACE-32 | Two starts, two releases, or a start against a release of onboarding | Onboarding update lock | One start and one release at most; a start after a release is refused `STALE_VERSION`, also when both run at once and after the restore of an earlier backup (D-071, D-072) | D-071, D-072 | NEW |
| RACE-33 | The Owner's status check of another user's request against that user's repeat of it | Command identity | As RACE-27: fence first, the repeat is refused `INTENT_ABANDONED`; original first, the check reports COMMITTED | ADR-007, ADR-009 | NEW |
| RACE-34 | The restore reconciliation of an intent against a status check or a repeat of it | Command identity; the report's uniqueness | Before the reconciliation the answer is `EPOCH_STALE`; after it, `NOT_COMMITTED`; the intent never commits; the report is written once | D-073, ADR-009 | NEW |
| RACE-35 | A close, own or Owner-assisted, against a checkout of the session's responsible user whose request receipt is being written or is unresolved | Session lock, then the read of that user's receipts (G-27) | A receipt PENDING or HELD when the close reads it: the close is refused `RECOUNT_REQUIRED`, writes nothing and the session stays OPEN; the close succeeds once a conclusive status check — the user's own or the Owner's — or a restore reconciliation has settled every receipt of that intent (ADR-009 section 1; D-089). The settlement takes no session lock: settled before the close's read, the receipt no longer counts, and its checkout either committed — the activity version has changed, as in RACE-08 — or can never commit (RACE-27); settled after it, the close was already refused. A receipt written after the close's read: its checkout waits for the session lock and is refused `SESSION_NOT_OPEN` before any fact, so no committed Sale is missing from the report. A checkout that committed first: the activity version has changed and the close is refused `RECOUNT_REQUIRED` as in RACE-08. No other family is refused because of an unresolved receipt (D-088) | D-088, D-089, BR-CASH-16, ADR-009 | SESSION05 |
| RACE-29 | Category deactivation against product creation, product edit or CSV import that assigns that category | Category lock (step 6) | Deactivation first: the assignment is refused `CATEGORY_INACTIVE` and nothing is written. Assignment first: it commits, and the deactivation changes no product | BR-CAT-09 | NEW |
| RACE-30 | Two bootstrap runs; a repeated Owner replacement; reactivation of a former Owner account | Installation state (bootstrap); the outgoing Owner's account lock (replacement); the guarantee of at most one ACTIVE SUPER_ADMIN account | Exactly one bootstrap succeeds and the other is refused; a repeated replacement is refused; a SUPER_ADMIN account is never reactivated; two Owner accounts are never active | D-048, BR-USR-04 | NEW |

## 9. Retries, timeouts and deadlocks

| ID | Rule | Source |
| --- | --- | --- |
| TR-RETRY-01 | A deadlock or serialization failure retries the whole transaction with the same identity, at most three attempts in total, with a jittered backoff between 50 and 250 milliseconds, and only within the retry window below. A bounded heavy command is not retried. After the last permitted attempt the answer is `SERVICE_BUSY`. | L-P6, BR-CMD-05 |
| TR-RETRY-02 | A lock wait that exceeds the lock timeout is not retried inside the request; the answer is `SERVICE_BUSY` and the client stays UNCERTAIN and repeats the same identity. | ADR-007 |
| TR-RETRY-03 | If the connection is lost during commit, the server does not guess; the client is UNCERTAIN and resolves through ADR-007. | BR-CMD-03 |
| TR-RETRY-04 | No retry repeats a human, payment, printer or physical cash effect; none of them happens inside a transaction (TR-TX-03). | BR-CMD-05, BR-DOM-10 |

Time limits. These are proposed defaults, set in configuration and verified by the foundation Task and PERF01 on the intended server; a change follows change control and never weakens integrity.

| Limit | Proposed value | Applies to |
| --- | --- | --- |
| PostgreSQL `lock_timeout` | 5 seconds | Every command |
| PostgreSQL `statement_timeout` | 10 seconds | Ordinary commands |
| Transaction budget for ordinary commands | 10 seconds | Every command that is not a bounded heavy command |
| Retry window for ordinary commands | A further attempt starts only within 15 seconds of the first | So all attempts of one ordinary command end within about 25 seconds |
| Transaction budget for bounded heavy commands | 30 seconds; not retried | Cost-correction replay and reversal replay; CSV import confirmation |
| PostgreSQL `statement_timeout` for reads | 15 seconds | Reports, history, search |
| PostgreSQL `idle_in_transaction_session_timeout` | 30 seconds | Every connection |
| Application execution limit per request | 60 seconds of wall-clock time, enforced by the PHP process manager's request termination (a CPU-time limit alone is not enough) | Every request; larger than the longest permitted sequence of attempts (about 25 seconds for an ordinary command, 30 seconds for a heavy one), so a transaction always ends before the request is killed. How each transaction budget is enforced — a transaction timeout where the pinned PostgreSQL version provides one, or an equivalent check — is verified by the foundation Task |
| Proxy and web-server timeouts | At least 60 seconds | So that the application, not a proxy, decides the outcome |
| Client wait before UNCERTAIN | At least the application limit plus a margin | The interface may say "still processing" earlier, but never reports failure because of time alone (RF-4, RF-5) |

| ID | Rule | Source |
| --- | --- | --- |
| TR-TIME-01 | A cost-correction or reversal replay is bounded by the movement cap (the reference of 10,000 movements per product, measured at PERF01) and by its transaction budget. Exceeding either, or finding an unsafe history or a result outside the ranges of section 11.1, rejects the correction or the reversal with no partial change (`REPLAY_REJECTED`). After a refused cost correction or completion, the affected reporting is marked incomplete: after the rollback, a transaction of its own takes steps 2.1, 6 (update) and 8 and writes the mark and its audit event, once per refused intent; `REPLAY_REJECTED` is answered only after that transaction commits, and `SERVICE_BUSY` if it fails. A refused reversal writes no mark, and a command that could not obtain the product guard within the lock timeout has not started its replay: it is answered `SERVICE_BUSY` (TR-RETRY-02) and writes no mark. The mark is cleared only by a replay that later succeeds for the same cost source — a correction of that source or a reversal of its movement — or by the reviewed recovery procedure (RF-6), never by editing (SCHEMA section 5.8). | BR-COST-27, BR-COST-28, D-034, D-063 |
| TR-TIME-02 | A CSV import confirmation is bounded by the row limit of section 11 and its transaction budget; exceeding the budget rejects the whole import with nothing created. | D-037, BR-CAT-38 |
| TR-TIME-03 | Every command has a hard end. "Received" is the moment the complete request, body included, reaches the web server of the application container, timestamped by that server; a time from a client or proxy header is never used. A command that starts executing more than 30 seconds after it was received is refused before any write (`SERVICE_BUSY`, so the client stays UNCERTAIN and may repeat). With the transaction budgets and the wall-clock application limit below, nothing a request carries can commit more than about 90 seconds after it was received. The foundation Task sizes the PHP worker pool so that, under normal load, the wait before a command starts stays far below 30 seconds even while heavy commands run. When a command reference has been lost, a conclusion that its intent did not commit is drawn from the committed records only after a record-check wait, counted from the latest moment the request could have been sent and judged conservatively by whoever confirms the conclusion, that is well above every proxy timeout plus the hard end: 10 minutes, set in RF-6 (D-088) above Cloudflare's origin timeout of 125 seconds and the hard end of about 90 seconds ([ENVIRONMENTS](../09-operations/ENVIRONMENTS.md) section 7), and verified by the foundation Task against the real proxy settings (ADR-007 section 5); who may draw that conclusion, and why it needs no separate record, is ADR-009 section 4. | BR-CMD-03, BR-SALE-20, D-040 |

## 10. No queue or cache platform

| ID | Rule | Source |
| --- | --- | --- |
| TR-RUN-01 | There are no queued jobs, workers or asynchronous states. Every command completes or fails inside its request. | D-005, ADR-002 |
| TR-RUN-02 | The Laravel cache (database store) holds only rate-limiter counters and scheduler locks. No response, page, report, DTO, search result or cost figure is cached on the server. | D-005, ADR-002, L-P4 |
| TR-RUN-03 | Product Search uses PostgreSQL's own indexes and text matching; there is no external search service. The index design is SCHEMA section 11. | L-02 §Batas read, D-005 |
| TR-RUN-04 | Scheduled commands are bounded, protected against overlap, and take their arguments from configuration only. | ADR-002, SECURITY_MODEL THR-10 |

## 11. Numeric, input and query limits

These limits implement BR-NUM-04 ("values outside the supported range are rejected, never truncated"). They are chosen so that no plausible operation of a small goods shop reaches them. Reaching one is refused with a clear validation result before any lock. If a real shop reaches one (gate G2, the pilot), the limit is reviewed through change control; it is never removed silently, and integrity is never weakened to fit.

### 11.1 Numbers

| Kind | Range | Notes and source |
| --- | --- | --- |
| Selling price per unit | 0 to 999,999,999,999 rupiah | Whole rupiah (BR-CAT-12, BR-NUM-01) |
| Any single stored money amount: line total, Sale total, applied payment, tendered cash, change, refund amount, opening cash, physical cash, expected cash | 0 to 999,999,999,999 rupiah | Opening and physical cash are zero or more (BR-CASH-04, BR-CASH-12); a computed total above the range refuses the command |
| Cash variance | −999,999,999,999 to 999,999,999,999 rupiah | BR-CASH-12 |
| Quantity of one line or movement | above 0, at most 99,999,999.999, with no more decimals than the product's precision (0 to 3) | BR-NUM-02, BR-CAT-01 |
| Stock balance and minimum stock | 0 to 9,999,999,999.999 | Stock never negative (BR-INV-04); minimum stock not negative (BR-CAT-11) |
| Acquisition cost of one evidence; unit and average cost | 0 to 999,999,999,999.999999, six fractional digits | An explicit zero needs Owner-confirmed evidence (BR-COST-04); UNKNOWN is a status, not a number (BR-COST-03) |
| Inventory value of one product | 0 to 999,999,999,999,999.999999 | A posting that would exceed it is refused |
| Report aggregates | No fixed cap | Computed exactly; displayed from strings (ADR-006) |

### 11.2 Counts per command

| Command | Limit | Source |
| --- | --- | --- |
| Sale | At most 200 stored lines; lines of the same product are added together for the stock check (BR-INV-07); a Sale stores one line per product (D-067), so it holds at most 200 distinct products; a request of more than 200 lines, or one that names a product twice, is refused `VALIDATION_FAILED` before any lock (INTERACTION_CONTRACTS RC-01) | BR-INV-07, D-067; replaces the 100-line request limit of L-P6 |
| Receiving document | At most 500 lines | Replaces the 100-line request limit of L-P6 |
| Manual issue, adjustment | At most 200 lines | Replaces the 100-line request limit of L-P6 |
| Opening stock | At most 100 product rows | BR-INV-25 (domain rule) |
| Refund and return | At most the lines of the original Sale | BR-RFD-02 |
| CSV product import | At most 5,000 data rows and 2 MiB per file | BR-CAT-39; SECURITY_MODEL section 13 |
| Barcode labels | 1 to 100 per print request | INTERACTION_CONTRACTS RC-47 |
| Unresolved request receipts listed | At most 100 per page | INTERACTION_CONTRACTS section 3.6 |

### 11.3 Text

| Field | Length in characters, after trimming |
| --- | --- |
| Product name | 1 to 200 |
| Category name, brand | 1 to 100 |
| Base unit label | 1 to 32 |
| SKU | 1 to 64; no control characters; unique without regard to letter case (BR-CAT-05; SCHEMA section 5.6) |
| Barcode token | 1 to 128 printable ASCII characters (BR-CAT-17) |
| Mandatory reason; ordinary note | 1 to 1,000 |
| Owner review note | 1 to 2,000 |
| Customer name on a receipt; payment reference; count or document reference | up to 100 |
| User display name | 1 to 100 |
| Email (sign-in identifier, BusinessProfile email) | up to 254 |
| Password | 8 to 128 (AICWDF-COMPAT-8; the maximum bounds hashing cost) |
| BusinessProfile: shop name; address; contact; document header; document footer | 1 to 100; up to 500; up to 200; up to 500; up to 500 |
| Search text | up to 128 |
| Cash register code; label | 1 to 32; 1 to 100 (confirmed in RF-5, D-087) |
| BusinessProfile document identity | up to 100 (confirmed in RF-5, D-087) |

The accent colour of the BusinessProfile is one of `green`, `teal`, `blue`, `purple` and `graphite` (DESIGN_SYSTEM section 3.3), checked on the server (D-086, D-087).

### 11.4 Requests and queries

| Item | Limit | Source |
| --- | --- | --- |
| Request body (JSON or form) | 1 MiB, except upload endpoints (SECURITY_MODEL section 13) | L-P6 |
| Page size | Default 25, maximum 100 | BR-CAT-25 |
| Report date range | At most 366 days per query; a longer period is narrowed explicitly, never fetched whole. A custom range whose start date is after its end date, or whose end date is after today in Asia/Jakarta, is refused at its date fields (INTERACTION_CONTRACTS section 3.4) | L-P6; BR-COST-37, BR-NUM-06 |
| Unbounded lists | None; every list is paged (section 12) | L-P6 |

## 12. Paging

| ID | Rule | Source |
| --- | --- | --- |
| TR-PAGE-01 | Lists are paged on the server with a stable total order that ends in a unique tie-breaker. Product Search orders by name, then internal identifier (BR-CAT-25). Stock history orders by posting order within the product; audit and Sales lists by time, then identifier. | BR-CAT-25, L-P6 |
| TR-PAGE-02 | Long and growing lists (stock history, audit, Sales, sessions) use keyset paging: the next page continues after the last row's sort key. | this document; D-044 (records kept at least 10 years) |
| TR-PAGE-03 | Each page request is authorized and scoped again; a page token never grants access. | BR-DOC-06 |
| TR-PAGE-04 | A newer search supersedes an older one in the interface, and a late response never replaces a newer selection. | BR-CAT-25, BR-CAT-30 |
| TR-PAGE-05 | Total counts are shown only where they are cheap and bounded; a failed count is an error, never zero. | BR-DOM-08 |

## 13. Performance budgets and provisional benchmark dataset

### 13.1 Proposed budgets

**PROPOSED — not accepted.** Measured at PERF01 on a representative target (gate TARGET). A miss is investigated and is never fixed by weakening integrity, privacy or cost completeness (REQ-NFR-04).

| Operation | Proposed budget | Source |
| --- | --- | --- |
| Exact barcode lookup | p95 ≤ 500 ms | L-P6, REQ-NFR-04 |
| Product Search page | p95 ≤ 700 ms | L-P6 |
| Sale commit, 50 lines | p95 ≤ 2 s | L-P6 |
| Sale commit, 200 lines (the maximum) | p95 ≤ 5 s | this document |
| Cash session close | p95 ≤ 2 s | this document |
| Refund or return commit | p95 ≤ 2 s | this document |
| Ordinary period report (up to 31 days) | p95 ≤ 2 s | L-P6 |
| Report over the maximum range (366 days) | p95 ≤ 10 s, inside the read statement limit | this document |
| Stock attention refresh | p95 ≤ 500 ms | this document |
| Server time of an ordinary page visit | p95 ≤ 500 ms | this document |
| CSV import confirmation, 5,000 rows | completes within its 30-second budget | this document |
| Cost-correction replay, 10,000 movements of one product | completes within its 30-second budget | BR-COST-28 |

Load during measurement: two cashiers checking out concurrently while the Owner runs a report (L-P6, L-08 PERF01).

### 13.2 Provisional benchmark dataset — ASSUMPTION

**This dataset is an assumption, not a fact about any shop.** The number of products at go-live is UNKNOWN (gate G2), so the final dataset is set when it is known (T-09). Until then:

| Dataset | Content | Purpose |
| --- | --- | --- |
| Base (assumption) | 5,000 active and 500 inactive products in 50 categories; about 7,500 active barcode tokens plus retired ones; 100,000 stock movement lines, with one product holding 10,000 movements; three cash registers; two concurrent cashiers | Latency budgets of section 13.1 (the legacy dataset of L-P6, kept) |
| Ten-year growth (assumption) | About 600,000 Sales with 1,500,000 Sale lines and movements, and about 3,000,000 audit events | Proves that indexes and keyset paging stay bounded under the 10-year retention of D-044; not a latency gate |

Each run records the versions, configuration, indexes, host, dataset, run count, measured p95 and a reconciliation before and after (L-08 §Dataset dan target performance).

## 14. Open items

| Item | Subject | Step |
| --- | --- | --- |
| T-09, gate G2 | Final benchmark dataset | When G2 is known |
| Gate TARGET | The real server for PERF01 | Before PERF01 |

T-03, T-16 and T-17 e, g and i were resolved in RF-3B: SCHEMA, ADR-008, D-060 and D-061. Questions 35 and 29 are answered by D-067 and D-068, and T-17 a, d, f and h are decided by D-069; question 36, from finding F-03 of the RF-4A review, is answered by D-071 (all 11 October 2026). RF-5 specified their enforcement and decided T-17 b with the Owner (D-087): sections 7.2, 8 and 11 above, SCHEMA and INTERACTION_CONTRACTS. RF-6 recorded the Owner's decision on N-07 (D-088): the close row of section 7.2 and RACE-35, with guard G-27 in SCHEMA; it also set the record-check wait of TR-TIME-03. The correction of D-089 added the settlement of request receipts to the close, resolution, receipt and reconciliation rows of section 7.2 and to RACE-35.
