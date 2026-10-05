# Domain model

| | |
| --- | --- |
| Status | CURRENT — written in RF-2 under D-041, 4 October 2026 (Asia/Jakarta) |
| Phase | P2 — domain model and business rules |
| Replaces | `docs/02-domain/legacy/P2-DOMAIN.md`, and the domain part (entity table, numbers and time) of `docs/04-architecture/legacy/02-ARCHITECTURE.md` |
| Detailed rules | [CATALOG_BARCODE](CATALOG_BARCODE.md), [INVENTORY](INVENTORY.md), [COSTING](COSTING.md), [SALES_CASH](SALES_CASH.md) |
| Terms | [GLOSSARY](GLOSSARY.md) |
| Requirements | [REQUIREMENTS](../01-product/REQUIREMENTS.md) |

This document is the map of the BantuKasir business domain: the entities, who owns them, how they relate, their states, the invariants that hold across the whole system, the lifecycle of every important entity, retention and audit. It is a specification, not a database schema; tables, keys and constraints are [SCHEMA](../04-architecture/SCHEMA.md) (RF-3B).

## 1. How to read this document

- **Rule identifiers.** Rules are numbered `BR-<area>-<nn>`. Identifiers are permanent. This document holds `BR-DOM-…`, `BR-NUM-…`, `BR-CMD-…`, `BR-USR-…`, `BR-AUD-…`, `BR-CFG-…` and `BR-KEEP-…`. The detailed domain documents use the same scheme (`BR-CAT-…`, `BR-INV-…`, `BR-COST-…`, `BR-SALE-…`, `BR-PAY-…`, `BR-DOC-…`, `BR-CASH-…`, `BR-RFD-…`, `BR-RPT-…`). Requirements in [REQUIREMENTS](../01-product/REQUIREMENTS.md) cite these rules.
- **Sources.** Every rule names its source: a decision `D-…` in [DECISIONS](../00-governance/DECISIONS.md), a CURRENT governance document, or a legacy document by the key in section 2. A rule without a source would be new business meaning; there is none.
- **Words.** Domain terms are used exactly as defined in [GLOSSARY](GLOSSARY.md). In domain documents, "Owner" means a user with the role SUPER_ADMIN at an installation. The project's final authority is also called the Owner in the governance documents; the two are kept apart by context, and product decisions are always cited by their `D-…` number. In the P1 and P2 documents "session" alone means a cash session; a sign-in session is always named in full.
- **OPEN.** Where a decision is pending, the text says `OPEN` and names the item in [OPEN_ITEMS](../00-governance/OPEN_ITEMS.md). Nothing is filled by assumption.
- **Authority.** This document and the four detailed domain documents are the authoritative P2 content (SOURCE_OF_TRUTH). Where a legacy document that is still in force for another subject (P3 workflows, P4 architecture, P5 security, P6 technical rules) disagrees with a rule here, this document prevails for the domain rule.

## 2. Source keys

| Key | Document | Status today |
| --- | --- | --- |
| L-PRD | [01-PRD](../01-product/legacy/01-PRD.md) | LEGACY — SUPERSEDED by the P1 documents |
| L-P2 | [P2-DOMAIN](legacy/P2-DOMAIN.md) | LEGACY — SUPERSEDED by this document |
| L-02 | [02-ARCHITECTURE](../04-architecture/legacy/02-ARCHITECTURE.md) | LEGACY — SUPERSEDED: the domain part by this document (RF-2), the architecture parts by the RF-3A documents, §Constraint database and the schema-delivery part of §Payment extension boundary dan schema delivery by SCHEMA (RF-3B; plan decision P-6, effective when the Owner accepts the RF-3B checkpoint) ([LEGACY_INDEX](../00-governance/LEGACY_INDEX.md) sections 8 and 9) |
| L-P4 | [P4-ARCHITECTURE](../04-architecture/legacy/P4-ARCHITECTURE.md) | LEGACY — SUPERSEDED — §Schema, immutability dan constraints by SCHEMA (RF-3B; P-6, effective when the Owner accepts the RF-3B checkpoint), the other parts by the RF-3A documents — except §Testing implications and §Deployment, operasi dan maintenance, LEGACY — INPUT ONLY for RF-6, and §Sumber primer, RECORD (LEGACY_INDEX sections 8 and 9) |
| L-06 | [06-INVENTORY-SPEC](legacy/06-INVENTORY-SPEC.md) | LEGACY — SUPERSEDED by INVENTORY |
| L-12 | [12-BARCODE-SCANNER](legacy/12-BARCODE-SCANNER.md) | LEGACY — SUPERSEDED by CATALOG_BARCODE |
| L-15 | [15-FINANCE-PROFITABILITY](legacy/15-FINANCE-PROFITABILITY.md) | LEGACY — SUPERSEDED by COSTING |
| L-17 | [17-POS-SALES](legacy/17-POS-SALES.md) | LEGACY — SUPERSEDED by SALES_CASH |
| L-P3 | [P3-CRITICAL-WORKFLOWS](../03-workflows/legacy/P3-CRITICAL-WORKFLOWS.md) | LEGACY — IN FORCE until RF-5 |
| L-04 | [04-AUTH-RBAC-SECURITY](../05-security/legacy/04-AUTH-RBAC-SECURITY.md) | LEGACY — SUPERSEDED by SECURITY_MODEL (RF-3A) |
| L-P5 | [P5-SECURITY](../05-security/legacy/P5-SECURITY.md) | LEGACY — SUPERSEDED by SECURITY_MODEL (RF-3A) |
| L-P6 | [P6-CONCURRENCY-API](../06-api-performance/legacy/P6-CONCURRENCY-API.md) | LEGACY — SUPERSEDED by TECHNICAL_RULES (RF-3A) |
| L-08 | [08-TESTING-ACCEPTANCE](../08-testing/legacy/08-TESTING-ACCEPTANCE.md) | LEGACY — DRAFT until RF-6 |
| L-09 | [09-DEPLOYMENT-OPS](../09-operations/legacy/09-DEPLOYMENT-OPS.md) | LEGACY — DRAFT until RF-6 |
| PDS | [PRODUCTION_DATA_SAFETY](../00-governance/PRODUCTION_DATA_SAFETY.md) | CURRENT |
| AGENTS | [AGENTS.md](../../AGENTS.md) section 5 | CURRENT |
| CORE-nnn | A legacy Core requirement entry in [core.json](../01-product/legacy/requirements/core.json) and [CORE-MAP](../01-product/legacy/requirements/CORE-MAP.md) | LEGACY — SUPERSEDED by REQUIREMENTS, whose section 21 maps every entry |

Legacy documents are in Indonesian. A citation such as "L-17 §Laporan Sesi Kas" names the legacy section heading.

## 3. Domain boundaries

| Area | Owns | Authoritative document |
| --- | --- | --- |
| Identity and access | User, roles, sign-in session, recent authentication, audit attribution | This document (sections 8 and 9); enforcement in [SECURITY_MODEL](../05-security/SECURITY_MODEL.md) |
| Configuration | BusinessProfile and its revisions, logo asset | This document (section 10) |
| Catalogue | Category, Product, selling price and its changes, barcode tokens, product photo, CSV product import, labels, Product Search | [CATALOG_BARCODE](CATALOG_BARCODE.md) |
| Inventory | Stock movements of every type, stock balance, receiving, manual issue, opening stock and onboarding, adjustment, reversal, stock attention, reconciliation | [INVENTORY](INVENTORY.md) |
| Costing | Acquisition cost evidence, moving weighted average valuation, issue-cost allocation, cost corrections and replay, cost of goods sold and gross profit, cost visibility | [COSTING](COSTING.md) |
| Sales and cash | Sale, payment, receipt, cash register, cash session, expected cash, refund and return, session report, daily summary | [SALES_CASH](SALES_CASH.md) |
| Cross-cutting | Global invariants, numbers and time, commands and results, audit, lifecycle, retention | This document |

Operations records (backups, restore drills, reconciliation runs, logs) belong to P9 and stay in L-09 until RF-6. They are not entities of this model.

## 4. Entity catalogue

The attributes listed are the business attributes that rules depend on. They are not a column list.

| Entity | Meaning | Key business attributes | Detailed rules |
| --- | --- | --- | --- |
| User | An individual account of a person working in the shop | Name, sign-in identifier (an email address, D-049), roles, active, deactivated or anonymized | Section 8 |
| Sign-in session | An authenticated browser session of a user (not a cash session) | User, start, last activity, end | Section 8 |
| BusinessProfileRevision | One immutable version of the shop identity | Shop name, logo, address, contact, optional email, receipt and document header and footer, document identity, constrained accent; which revision is current | Section 10 |
| Category | A grouping of products | Name | CATALOG_BARCODE |
| Product | A goods item the shop sells from its one stock pool | SKU, name, category, optional brand, base unit, quantity precision, current selling price, minimum stock, active or inactive, version, optional photo | CATALOG_BARCODE |
| Barcode | A token that identifies exactly one product: a manufacturer alias or an internal code | Token text, kind (manufacturer or internal), product, active or retired, retirement reason (a misassignment or another reason, D-052), mapping history (D-051) | CATALOG_BARCODE |
| SellingPriceRevision | One recorded selling price of a product and when it applied | Amount in rupiah, actor, time | CATALOG_BARCODE |
| Product photo | The one optional primary photo of a product, with its thumbnail | Image, product | CATALOG_BARCODE |
| CSV product import | One confirmed import of new products from a CSV file | Rows, validation result, durable result | CATALOG_BARCODE |
| StockMovement | One immutable physical stock change of one product | Type, product, signed quantity, source, reason, actor, server time, per-product posting sequence | INVENTORY |
| StockBalance | The current on-hand quantity of one product; a synchronous projection of its movements | Product, on-hand quantity, version | INVENTORY |
| Receiving document | The source of a RECEIPT movement ("Barang Masuk") | Lines of product, quantity and acquisition cost; optional reference; entered, reviewed, confirmed | INVENTORY |
| Manual issue | The source of a MANUAL_ISSUE movement ("Barang Keluar") | Lines of product and quantity; mandatory non-sale reason | INVENTORY |
| Opening stock entry and onboarding state | The Owner's initial stock count and the onboarding freeze | Count rows, cost evidence or UNKNOWN, count reference, zero-count checklist, onboarding flag | INVENTORY |
| Adjustment | The source of an ADJUSTMENT movement | Signed quantity, reason, count, reference | INVENTORY |
| Reversal | The source of a REVERSAL movement: the exact inverse of one eligible source | Original source, reason | INVENTORY |
| AcquisitionCostEvidence | The cost recorded for stock that enters through OPENING, RECEIPT or a positive ADJUSTMENT | Total amount, quantity, status KNOWN or UNKNOWN, reference, actor | COSTING |
| Cost correction | An append-only Owner fact that corrects or completes acquisition cost after posting | Cost source, old value, new value, actor, time, reason | COSTING |
| Inventory valuation | The current, corrected moving-weighted-average value and quantity of a product | Product, value, known or unknown status | COSTING |
| Issue-cost allocation | The cost assigned to an issued quantity (sale, manual issue, negative adjustment) | Issue; amount or UNKNOWN at commit, an immutable fact; the current corrected amount, a projection that each replay replaces (D-060); for a saleable return, the link to the original issue allocation (D-061) | COSTING; [ADR-008](../adr/ADR-008-cost-allocation-representation.md) |
| Sale | A completed, fully paid sale of goods handed over directly | Number, source intent, actor, cash session, server time, business date, total | SALES_CASH |
| SaleLine | One product line of a Sale | Product, unit, quantity, unit price applied, line total | SALES_CASH |
| Payment | The one full payment of a Sale | Method, applied amount; tendered amount and change for CASH; optional reference for BANK_TRANSFER and STATIC_QRIS | SALES_CASH |
| ReceiptSnapshot | The immutable receipt content of a Sale | Number, frozen shop identity revision, template and asset revision, lines, payment, cashier, session | SALES_CASH |
| Refund | Money paid back against an original Sale, with its payout | Sale and lines, amount, payout method, source of money, cash session when drawer-funded, actor, reason | SALES_CASH |
| Return | Goods physically returned against an original Sale | Sale lines, quantity, condition (saleable or not) | SALES_CASH |
| CashRegister | The logical identity of one physical cash drawer | Code, label, active or inactive | SALES_CASH |
| CashSession | One accountability period of one responsible person at one cash register | Number, register, responsible user, opened at, opening cash, activity version, OPEN or CLOSED, close result | SALES_CASH |
| CashSessionReportSnapshot | The immutable report of a CLOSED cash session | Counts and totals as closed, expected cash, physical cash, variance, cutoff, close actor | SALES_CASH |
| Owner review note | A note the Owner links to a closed session report, for example a counting mistake | Session report, text, actor, time | SALES_CASH |
| Command result | The durable result of one critical command | Actor and operation scope, idempotency key, fingerprint, source intent, result, recovery epoch | Section 7 |
| AuditEvent | An append-only record of a successful business change | Actor, time, action, source or command, reason, safe change data | Section 9 |

### Relationships

- A Product belongs to one Category, has zero or more Barcodes (each Barcode belongs to exactly one Product), has at most one product photo, one StockBalance and one Inventory valuation, and a history of SellingPriceRevisions (L-02, L-12, D-037).
- Every StockMovement concerns one Product and points to exactly one source: an opening stock entry, a receiving document line, a SaleLine, a manual issue, an adjustment, a reversal or a Return (L-06).
- AcquisitionCostEvidence belongs to one OPENING, RECEIPT or positive ADJUSTMENT movement. Cost corrections point to the evidence they correct (L-15, D-034).
- A Sale belongs to one CashSession of its acting user, has one or more SaleLines, exactly one Payment and exactly one ReceiptSnapshot. Each SaleLine is issued by a SALE_ISSUE movement of its product (L-02, L-17).
- A Refund and a Return point to the original Sale and its lines. A saleable Return is received by a RETURN_RECEIPT movement. A drawer-funded CASH refund points to exactly one CashSession that was OPEN when the refund committed; its cash is paid out from that drawer afterwards (D-035, D-046).
- A CashSession belongs to one CashRegister and one responsible User, and has at most one CashSessionReportSnapshot, created when it closes. Owner review notes point to that snapshot (L-17).
- A ReceiptSnapshot and a CashSessionReportSnapshot point to the BusinessProfileRevision in force when they were created (D-038).
- Every AuditEvent and Command result points to the acting User (D-032). The only exceptions are events written by an operator procedure or a scheduled command, which name that procedure or command as their actor (SECURITY_MODEL section 14); such an actor is stored as SCHEMA section 3.4 describes.

## 5. States and transitions

| Entity | States | Transitions and guards | Source |
| --- | --- | --- | --- |
| User | ACTIVE, DEACTIVATED, ANONYMIZED | The Owner deactivates and reactivates; deactivation ends sign-in sessions and mutation rights. The Owner may anonymize a deactivated account; an anonymized account stays deactivated for good and is never reactivated or reused. One-time links for the first password and for a reset belong to the authentication profile (D-049, SECURITY_MODEL section 3). Exactly one account is the active Owner (D-048). | L-04, L-P3, D-042, D-043, D-048, D-049 |
| Category | ACTIVE, INACTIVE | The Owner or OPERATIONS_ADMIN deactivates and reactivates. An inactive category cannot be chosen for a product; deactivating it changes no product. | D-042 |
| Product | ACTIVE, INACTIVE | Owner or OPERATIONS_ADMIN switches the active flag. Only an active product can be posted or sold; inactive products stay readable in history. | L-PRD, L-12, L-06 |
| Barcode | ACTIVE, RETIRED | Owner or OPERATIONS_ADMIN retires a barcode, recording whether it was a misassignment or another reason. A retired token is never used again in general. Only the Owner may move a manufacturer token active on the wrong product to the correct one, or re-attach a manufacturer token retired as a misassignment whose earlier mapping is traceable, with a reason and recent authentication; a token is active on at most one product; internal `BK-P-…` codes are never reused (CATALOG_BARCODE BR-CAT-19, BR-CAT-50 to BR-CAT-52). | L-02, L-12, D-051, D-052 |
| CashRegister | ACTIVE, INACTIVE | Owner changes code, label or active flag only while no session is OPEN on it. | L-02 |
| Receiving document | Being entered or reviewed → CONFIRMED (posted) | Before confirmation OPERATIONS_ADMIN and the Owner may change it, including cost (D-033). Confirmation posts one RECEIPT; afterwards it never changes. Whether an unconfirmed document is held on the server is OPEN (T-17 a). | D-033, L-06 |
| Onboarding | OFF, ON | The Owner sets ON at a documented cutover and releases it after reconciliation, through the shared guard. | L-06 |
| Sale | COMPLETED | There is no stored draft, pending or partially paid Sale. The client draft becomes a COMPLETED Sale in one transaction or not at all. | L-02, L-17 |
| CashSession | OPEN → CLOSED | Close is atomic, checks the activity version, and is final: no reopening, no CLOSING state, no change of the responsible user. | L-17, D-009 |
| Confirmed command request (client view) | SUBMITTING → COMMITTED, REJECTED or UNCERTAIN | UNCERTAIN freezes the original intent until its result is recovered. HELD is a form of UNCERTAIN in which the request is never repeated, because the server reported an identity conflict, which its actor or the Owner resolves, or an intent from before a restore, which the Owner checks under the restore runbook (ADR-007 section 5). | L-P3, L-12, D-040; ADR-007 |
| Acquisition cost | KNOWN, UNKNOWN | UNKNOWN becomes known only through an Owner cost correction (D-034) or, before confirmation of a receiving document, by editing that document (D-033). | D-033, D-034, L-15 |
| Stock attention | NORMAL, LOW, OUT | Derived from the current balance and minimum stock; never stored as a separate truth. | L-06 |
| Reporting completeness | COMPLETE, INCOMPLETE | Derived from cost status; a rejected cost correction marks the affected reporting incomplete (COSTING BR-COST-27), which also states how the mark is recorded, scoped, audited and cleared (T-17 i, resolved in RF-3B). | L-15, D-034 |

## 6. Global invariants

| ID | Rule | Source |
| --- | --- | --- |
| BR-DOM-01 | One installation serves one business with one stock pool for goods. There is no tenant, outlet, warehouse, location or reservation concept. Another shop uses another installation with its own database, storage and configuration. | D-002, D-038, L-06 |
| BR-DOM-02 | Historical money and stock facts are append-only. They are corrected only by new, linked correcting records, never by editing or deleting the original. | AGENTS rule 7, D-006, D-034, D-035 |
| BR-DOM-03 | One source of truth per fact. The movement ledger is the truth for stock; StockBalance is its synchronous projection. Sale and Payment facts are the truth for sales and payments; receipts and session reports are accountability documents, never a second source of totals. | AGENTS rule 7, L-02, L-06, L-17 |
| BR-DOM-04 | Every critical money or stock action is one database transaction that writes its facts, projections, audit event and durable result together, or writes nothing. | D-006, L-02 |
| BR-DOM-05 | Concurrency is explicit: database constraints together with ordered locks keep every rule true when requests race, and each known race has a defined outcome. The lock order and the race matrix are in [TECHNICAL_RULES](../06-api-performance/TECHNICAL_RULES.md) sections 7 and 8. | D-006, L-02, L-P6, TECHNICAL_RULES |
| BR-DOM-06 | The server decides the actor, the authoritative price, totals, the responsible cashier, the stock balance and the cost scope. Values for these sent by the browser are never trusted. | L-P3, L-P6, L-04 |
| BR-DOM-07 | Authorization is deny by default, enforced on the server for every request, including reads, status, recovery, print, export and media, with object scope. Hiding a control is not authorization. | AGENTS rule 8, D-007, L-04 |
| BR-DOM-08 | UNKNOWN is never zero. An unknown cost is shown as unknown; a failed or stale query is shown as failed or stale, never as zero, empty or "Normal". | D-033, D-034, L-P3, L-06 |
| BR-DOM-09 | A draft is not a fact. Searching, scanning, entering quantities, previewing and reviewing create no business fact. | L-P3, L-02, L-12 |
| BR-DOM-10 | No human action, printer or network wait happens inside a database transaction. Customer payment, bank transfer, counting cash and printing happen outside it. | L-02, L-17 |
| BR-DOM-11 | The code is client-neutral. The shop's identity comes only from its BusinessProfile; "BantuKasir" is a codename and LATANSA is never a default identity. | D-038, D-010 |

## 7. Numbers, time and commands

| ID | Rule | Source |
| --- | --- | --- |
| BR-NUM-01 | Money is Indonesian rupiah in whole rupiah. A selling price and every payable total are integers. A line total is quantity × unit price, rounded half-up once per line to whole rupiah; the Sale total is the sum of its line totals. | L-02 §Angka dan waktu, D-006 |
| BR-NUM-02 | Quantities are exact, in the product's base unit, with 0 to 3 decimal places fixed by the product's quantity precision. The real precisions of the first store are part of gate G2. | L-02, L-06 |
| BR-NUM-03 | Cost values are exact decimals with six fractional digits. A cost value computed by multiplication and division is rounded half-up (ties away from zero) to six fractional digits, once, at the end of its computation. Aggregation happens before rounding; rounding to whole rupiah happens only for report display, half-up (ties away from zero, also for a negative figure such as a loss), at the end. The final-issue rule (COSTING BR-COST-11) and the cumulative rule for partial returns (ADR-008 section 5) leave no residue. | L-15, L-P6; T-17 e (RF-3B, D-059); ADR-008 |
| BR-NUM-04 | No binary floating point is used for money, cost or quantity anywhere: storage, calculation, transport (decimal strings) or display. Values outside the supported range are rejected, never truncated. The ranges are in TECHNICAL_RULES section 11 and the representations in ADR-006. | AGENTS rule 7, L-02, L-P6, ADR-006 |
| BR-NUM-05 | Every fact carries the server's UTC time. The business date is the Asia/Jakarta calendar date computed by the server. An optional document date is stored separately and never backdates a physical or financial fact. | L-02, L-06, L-17 |
| BR-NUM-06 | Report periods are Asia/Jakarta dates converted to half-open UTC ranges; an inclusive custom range ends at the start of the next day, exclusive. | L-15, L-02 |
| BR-NUM-07 | Dates are shown with the full Indonesian month name and dotted time, for example `01 Oktober 2026, 09.15.00`. This is presentation only; it changes no stored time, business date or period. | D-018, L-P3 |
| BR-CMD-01 | A critical command carries an authenticated and authorized actor, a validated envelope, a source intent, an idempotency key scoped to actor and operation, a fingerprint computed by the server, one transaction, an audit event and a durable result kept with the business facts. | D-006, L-02 §Kontrak command |
| BR-CMD-02 | The same key with the same payload returns the original result. The same key with a different payload is a conflict. A different key with the same source intent never posts twice. | D-006, L-02, L-P6 |
| BR-CMD-03 | A lost or unclear response makes the request UNCERTAIN: the original intent, key and payload are frozen, and the only allowed actions are status and retry of that same identity. A replacement command is never created because a response was lost. Recovery checks current authorization and object scope again before returning a result. | L-P3, L-12, L-17, D-040 |
| BR-CMD-04 | Every critical mutation passes draft, review and explicit confirmation. The one exception is ordinary Sale checkout, which uses one payment screen with one final action (D-040). Corrections, refunds and returns, receiving, opening stock and cash session close keep their review step. | L-P3, D-040 |
| BR-CMD-05 | Internal retries after a deadlock or serialization failure are bounded and reuse the same command identity. External human, payment or printer effects are never repeated by a retry. | L-P6 |
| BR-CMD-06 | After a restore, sign-in sessions are revoked and a new recovery epoch rejects stale browser intents. A result missing from the restored data is not proof that the original command never committed. | D-006, L-02, L-09 |

## 8. Users and accounts

| ID | Rule | Source |
| --- | --- | --- |
| BR-USR-01 | Accounts are individual. There are exactly three fixed roles: SUPER_ADMIN (Owner), OPERATIONS_ADMIN and CASHIER. There are no custom or dynamic roles. | D-002, D-032 |
| BR-USR-02 | One account may hold CASHIER and OPERATIONS_ADMIN together and then has the union of their permissions. SUPER_ADMIN is never combined with another role. No right reserved to the Owner is reachable through the combination. | D-032 |
| BR-USR-03 | Only the Owner creates staff accounts. There is no public sign-up. The first Owner account is created by a private one-time operator procedure; there is no default or demo account. Sign-in is by email and password: the email is never verified and the application sends no email; there is no Google sign-in; a staff member sets the first password, and a reset password, through a short-lived one-time link that the Owner creates and hands over directly; the Owner's own recovery is an operator procedure; the password profile is AICWDF-COMPAT-8; there is no two-factor authentication (SECURITY_MODEL section 2). | L-04, L-P3, D-048, D-049 |
| BR-USR-04 | Only the Owner assigns roles; nobody assigns a role to themselves. After a role change no old permission survives in a sign-in session or cache. There is exactly one active SUPER_ADMIN account at any time: no function of the product creates a second Owner account or assigns SUPER_ADMIN, and the Owner cannot deactivate or anonymize the only active Owner account; Owner recovery and Owner replacement are documented operator procedures (SECURITY_MODEL section 14). | L-04, L-P3, D-048 |
| BR-USR-05 | Deactivating an account ends its sign-in sessions and its right to change anything, and is enforced under the same guard as racing commands: a command that passed a check before the deactivation does not commit after it. | L-04, CORE-003 |
| BR-USR-06 | A sign-in session ends after 30 minutes of inactivity and after 12 hours at most. Signing out, or a sign-in session ending, never closes, transfers or otherwise changes a cash session. | D-039, D-013 |
| BR-USR-07 | Sensitive Owner actions need authentication within the last 15 minutes: refunds and returns, cost corrections, adjustments and reversals, barcode misassignment corrections, user management, recovery, sensitive configuration and Owner-assisted close. The full list is the recent-authentication column of SECURITY_MODEL section 6. | D-013, D-008, D-035, D-051, L-04, L-P5 |
| BR-USR-08 | Every action is attributed to the one identity of the user who performed it, including on a combined account. | D-032 |
| BR-USR-09 | A deactivated user's identity stays attached to everything that user did. | L-02, L-04 §Audit |
| BR-USR-10 | The Owner may reactivate a deactivated account that has not been anonymized, but never a SUPER_ADMIN account (D-048). Accounts are never deleted, softly or permanently. | D-042, D-048 |
| BR-USR-11 | The Owner, and only the Owner, may anonymize an account, and only after it has been deactivated. Anonymization replaces personal and profile fields with a neutral, non-personal label where applicable; the sign-in email is replaced by a unique, unusable placeholder and the password is made unusable (SECURITY_MODEL SEC-ACC-09). It is not a deletion: the stable internal user identity and every audit relationship remain. The action needs a reason and is audited permanently with the Owner as actor and its time. An anonymized account stays deactivated and is never reused for another person; a former staff member who returns gets a new account, never the anonymized one. | D-043, D-049 |
| BR-USR-12 | Anonymization changes only the current User profile. It never rewrites a historical record: Sales, ReceiptSnapshots, cash session reports, refunds, returns, audit events and every other record keep the actor identity and display information they recorded when they were created, so that every record the decisions require to reproduce exactly (receipts, session reports) still does. The records that name a user as actor, cashier or responsible person — those listed in SCHEMA section 3.5 — capture that user's display name as it was when the record was created. This is product lifecycle policy, not a claim of legal compliance or a substitute for legal advice on personal-data requests. | D-043, D-035, D-038 |

## 9. Audit

| ID | Rule | Source |
| --- | --- | --- |
| BR-AUD-01 | Every successful business change is audited in the same transaction as the change and rolls back with it: sales and payments, stock movements, products, prices, categories, barcodes, product photos, CSV imports, users and roles, configuration, cash session open and close, refunds and returns, cost entries, confirmations and corrections. | L-04 §Audit, D-006, D-033, D-034, D-036, D-037 |
| BR-AUD-02 | An audit event records the actor, the server time, the action, the source or command, the reason where one is required, and the change in a safe form. Cost details in audit are visible to the Owner only. | L-02, L-04, D-034 |
| BR-AUD-03 | Audit events are append-only. The original actor and history stay intact after a user is deactivated or a profile changes. | L-04 §Audit |
| BR-AUD-04 | The Owner reads the whole audit log, filtered by date, user, type, product, cash session and reference. OPERATIONS_ADMIN reads only safe stock history; CASHIER reads only their own sales and cash sessions. Security audit is Owner-only. | L-04, CORE-029 |
| BR-AUD-05 | Logs and audit never contain passwords, invitation or reset tokens, secrets, sign-in session cookies or full payloads with private cost. Failed security events go to a separate safe log, not to the business audit. | L-04, L-P5 |
| BR-AUD-06 | Print, reprint and export requests are audited separately from the business facts and never change them. | L-17, L-12 |

## 10. Shop identity — BusinessProfile

| ID | Rule | Source |
| --- | --- | --- |
| BR-CFG-01 | Each installation has one BusinessProfile: shop name, logo, address, contact, optional email, receipt and document header and footer, document identity and a constrained accent colour. | D-038, L-02, CORE-030 |
| BR-CFG-02 | Every change creates a new immutable BusinessProfileRevision; a current pointer selects the revision in force. Revisions are never deleted. Only the Owner changes the BusinessProfile; the change is audited. Every BusinessProfile change needs recent authentication (SECURITY_MODEL section 6). | L-02, L-04, D-038, D-042 |
| BR-CFG-03 | Every receipt and document keeps the revision in force when it was created. Later changes never alter it. A logo file referenced by a historical document is never overwritten in place. | D-038, L-17 |
| BR-CFG-04 | A logo is PNG, JPEG or WebP, at most 5 MiB and 16 megapixels, verified by content, re-encoded and stripped of metadata; SVG, HTML and fetching from a remote URL are refused. Logos are stored privately and served only to authorized requests. | L-04, CORE-030 |
| BR-CFG-05 | Only the configuration and revision mechanism needed for one business per installation is built. | D-038 |

## 11. Lifecycle matrix

The columns follow AICWDF-4.3 §13. For BantuKasir they mean:

- **Create** — how the record comes into existence, and who may do it.
- **Edit** — whether it can be changed after creation, and by whom.
- **Archive** — taking a record out of new use while keeping it and its history readable (for BantuKasir: deactivate or retire).
- **Restore** — returning an archived record to use.
- **Soft delete** — marking a record deleted and hiding it from ordinary views while keeping it, distinct from archive.
- **Hard delete** — physically removing the record.
- **Anonymize** — irreversibly replacing personal data in the record.

Values: `YES — who`, `NO`, `N/A — reason`, or `OPEN` with the item that will decide it. "Owner" is SUPER_ADMIN; "Ops" is OPERATIONS_ADMIN, including a combined account through its OPERATIONS_ADMIN functions (D-032).

For every record that is a historical money or stock fact, "Hard delete: NO" means: never in normal operation and never by any product function; outside the product, only as an exact destructive operation approved by the Owner under PRODUCTION_DATA_SAFETY section 1. That rule (PDS §1, L-09 §Maintenance, D-044) is cited below as **"facts kept"**. For master data and configuration, "NO" for soft and hard delete means that V1 has no deletion of any kind for them (D-042). Anonymizing a user (D-043) changes only the current User profile; no record in sections 11.2 and 11.3 is rewritten by it.

### 11.1 Master data and configuration

| Entity | Create | Edit | Archive | Restore | Soft delete | Hard delete | Anonymize |
| --- | --- | --- | --- | --- | --- | --- | --- |
| User | YES — Owner creates staff accounts, each with a one-time first-password link (D-049); the first Owner by the private bootstrap procedure; a replacement Owner by the Owner replacement procedure; exactly one active Owner account (D-048, L-04, L-P3) | YES — the user: own password; Owner: staff roles (never self-assigned, never SUPER_ADMIN), staff display name and email, and the Owner's own display name and email (L-P3, L-04, D-032, D-048, SECURITY_MODEL SEC-ACC-10) | YES — Owner deactivates a staff account; the only active Owner account is deactivated only by the Owner replacement procedure (L-04, D-048) | YES — Owner reactivates, unless the account was anonymized or is a SUPER_ADMIN account (D-042, D-043, D-048) | NO (D-042) | NO (D-042) | YES — Owner only, after deactivation, with a reason; permanent; never rewrites history (D-043) |
| Sign-in session | YES — on sign-in (L-04) | NO | N/A — ends by sign-out, timeout or revocation (D-039, L-04) | N/A | N/A | YES — expired sign-in session records follow the library's retention, never removing the historical actor (L-09 §Maintenance) | N/A — holds no personal data beyond the user reference |
| BusinessProfileRevision | YES — Owner (D-038, L-02) | NO — immutable; a change creates a new revision (L-02, CORE-030) | N/A — older revisions stay as history | N/A — no source defines making an older revision current again; the Owner creates a new revision | NO (D-042) | NO (D-042, D-038) | N/A — shop identity, frozen in documents (D-038) |
| Category | YES — Owner, Ops (L-PRD, L-04) | YES — Owner, Ops (L-PRD, L-04) | YES — Owner, Ops deactivate (D-042) | YES — Owner, Ops reactivate (D-042) | NO (D-042) | NO (D-042) | N/A — no personal data |
| Product | YES — Owner, Ops, search-first or by CSV import; starts with stock 0 (L-12, L-06, D-037) | YES — Owner, Ops; price only through a price change; base unit and precision locked after the first movement (L-02, D-036) | YES — Owner, Ops set it inactive (L-PRD) | YES — Owner, Ops set it active again (L-PRD, D-042) | NO (D-042) | NO (D-042; facts kept) | N/A — no personal data |
| Barcode | YES — Owner, Ops: manufacturer alias or generated internal code (L-12, L-04) | NO — a token is never changed; it is retired and another is added; the Owner's misassignment correction moves a manufacturer token between products as a new mapping (L-12, D-051) | YES — Owner, Ops retire it, recording whether it was a misassignment or another reason (L-02, L-12, D-052) | Owner only, as a misassignment correction: a manufacturer token retired as a misassignment, with a traceable earlier mapping, may be re-attached to the correct product (D-052). Otherwise NO: a retired token is never used again, and `BK-P-…` codes never (L-02, L-12, CORE-004, D-051, D-052) | N/A — retirement is the only removal | NO — the record and its mapping history are what prevent a retired token from being reused (L-02, CORE-004, D-052) | N/A |
| SellingPriceRevision | YES — Owner, Ops, by a price change in master data (D-036) | NO (L-02) | N/A | N/A — a new price change can set any amount | NO — price changes are durable and audited (D-036) | NO (D-036) | N/A |
| Product photo | YES — Owner, Ops upload (D-037) | YES — Owner, Ops replace it (D-037) | N/A | N/A | N/A | Removing the photo from the product: YES — Owner, Ops; a product without a photo stays valid (D-037). The file of a replaced or removed photo: NO — it is kept, no longer shown, and stays in backups (D-042) | N/A |
| CashRegister | YES — Owner (L-02) | YES — Owner, code and label, only while no session is OPEN on it; snapshots keep the old identity (L-02) | YES — Owner, only while no session is OPEN (L-02) | YES — Owner (L-02, D-042) | NO (D-042) | NO (D-042; facts kept) | N/A |

### 11.2 Stock and cost

| Entity | Create | Edit | Archive | Restore | Soft delete | Hard delete | Anonymize |
| --- | --- | --- | --- | --- | --- | --- | --- |
| StockMovement (all types) | YES — only by the confirmed command of its type, with the role that type allows (INVENTORY, SALES_CASH) | NO — immutable (AGENTS rule 7, D-034) | N/A | N/A | NO (facts kept) | NO (facts kept) | NO — actor identity retained (L-04) |
| StockBalance | YES — system, with the product's first movement (L-06) | Only by posting commands in the same transaction; never edited directly (L-06) | N/A | N/A | N/A | N/A — a projection; rebuilt only by the controlled reconciliation procedure from immutable movements (L-06) | N/A |
| Receiving document | YES — Owner, Ops (L-06, D-033) | Before confirmation: YES — Owner, Ops, including cost (D-033). After confirmation: NO; cost only by an Owner cost correction (D-034), which Ops then sees as the current cost with the Owner correction mark and the cost at confirmation (D-047) | N/A | N/A | Unconfirmed: OPEN — T-17 a. Confirmed: NO (facts kept) | Unconfirmed: OPEN — T-17 a. Confirmed: NO (facts kept) | NO |
| Manual issue, adjustment, reversal, opening stock entry | YES — by role per type (INVENTORY) | NO after confirmation; before confirmation it is a draft and not a fact (L-P3). How a wrong positive OPENING is corrected before the onboarding flag is released: OPEN — T-17 b | N/A | N/A | NO (facts kept) | NO (facts kept) | NO |
| Onboarding zero-count checklist entry | YES — Owner during onboarding (L-06) | Before the onboarding flag is released: OPEN — T-17 b. After release: NO — the Owner reconciled it before release (L-06) | N/A | N/A | NO — V1 has no deletion function for it (D-044) | NO — V1 has no deletion function for it (D-044) | N/A — no personal data |
| AcquisitionCostEvidence | YES — with its OPENING, RECEIPT or positive ADJUSTMENT (L-15) | NO after posting; corrected only by a cost correction (D-034) | N/A | N/A | NO (facts kept) | NO (facts kept, D-034) | NO |
| Cost correction | YES — Owner only (D-034) | NO (D-034) | N/A | N/A | NO | NO (D-034) | NO |
| Inventory valuation (current) | YES — system | Only by posting and by an atomically published replay (D-034) | N/A | N/A | N/A | Superseded derived values: YES — V1 need not keep earlier derived projections (D-034); this never permits deleting an original fact or a correction | N/A |
| Issue-cost allocation | YES — system, with the issue: the fact recorded at commit and its projection (D-006, D-040, D-060); for a saleable return, the linked return allocation and its projection, with the return (D-061) | The fact: NO — immutable (D-060). The projection: only by an atomically published replay, which replaces it (D-034, D-060, D-061) | N/A | N/A | NO (facts kept) | The fact: NO (facts kept). Superseded projected values are not kept (D-034) | N/A |

### 11.3 Sales and cash

| Entity | Create | Edit | Archive | Restore | Soft delete | Hard delete | Anonymize |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Sale, SaleLine, Payment | YES — CASHIER or Owner, including a combined account, at checkout in their own OPEN cash session (L-17, D-032) | NO — immutable (D-035, D-034) | N/A — a Sale has one state | N/A | NO (facts kept) | NO (facts kept) | NO — immutable (D-035) |
| ReceiptSnapshot | YES — system, with the Sale (L-17) | NO — immutable; a reprint adds no fact (D-035, L-17) | N/A | N/A | NO | NO (facts kept) | NO — immutable (D-035) |
| Refund, Return | YES — Owner only (D-035) | NO (D-035) | N/A | N/A | NO | NO (facts kept) | NO |
| CashSession | YES — CASHIER or Owner, including a combined account, as themselves (L-17, D-032) | Only by its own activity and by close; no reopening and no change of the responsible user (L-17) | N/A | NO — a CLOSED session is never reopened (L-17) | NO | NO (facts kept) | NO |
| CashSessionReportSnapshot | YES — system, at close (L-17) | NO — immutable (L-17, D-035) | N/A | N/A | NO | NO (facts kept) | NO |
| Owner review note | YES — Owner (L-17) | OPEN — T-17 f | N/A | N/A | NO — V1 has no deletion function for it (D-044) | NO — V1 has no deletion function for it (D-044) | NO — anonymizing a user never rewrites a historical record (D-043) |
| Command result | YES — system, with the command (L-02) | NO | N/A | N/A | NO | NO while the facts it belongs to exist (L-02) | NO |
| AuditEvent | YES — system, in the business transaction (L-04) | NO — append-only (L-04) | N/A | N/A | NO | NO (facts kept, L-09) | NO — the original actor is retained (L-04) |

## 12. Retention

| ID | Rule | Source |
| --- | --- | --- |
| BR-KEEP-01 | Nothing deletes stock movements, sales, payments, receipts, cash session reports, cost evidence or corrections, audit events, command results, or shop identity assets that a document still references, automatically. | L-09 §Maintenance, retensi |
| BR-KEEP-02 | Deleting any of those records is a destructive production operation. It needs the Owner's approval of that exact operation; a general request such as "fix" or "deploy" is not approval. | PDS §1 |
| BR-KEEP-03 | Business books, records, documents and electronic data that form the basis of bookkeeping or of business and tax records are retained for at least 10 years. This is a minimum, not a deletion deadline: there is no universal "delete after 10 years" rule. | D-044 |
| BR-KEEP-04 | V1 has no automatic expiry, purge, archival deletion or scheduled deletion of business records, and no manual deletion function for them. Immutable transactional and audit facts never become deletable because the minimum period has passed. | D-044 |
| BR-KEEP-05 | Any future archival or deletion capability needs a separate Owner decision, legal and compliance revalidation, an explicit data classification, and safeguards for historical references, audit, reconciliation and document reproduction. | D-044 |
| BR-KEEP-06 | Master data and configuration — users, categories, products, barcodes, cash registers, BusinessProfile revisions and the files of replaced or removed product photos — are never deleted in V1; they are deactivated or retired. | D-042, L-02 |
| BR-KEEP-07 | Data classes outside the bookkeeping rule may get their own lifecycle and retention rules; RF-2 decided none of them. The uploaded CSV import file is temporary working data of its command and is not kept after the command's final outcome; the import result is kept in the audit (D-054). Operational logs (debug and security access logs) and backup copies stay in L-09 until RF-6. Expired authentication tokens and one-time links follow the library's retention and never remove a historical actor. | D-044, D-054, L-09 |
| BR-KEEP-08 | These are product lifecycle rules. BantuKasir by itself does not guarantee legal or tax compliance. | D-044, D-043 |

## 13. Open items referenced in this document

| Item | Subject | Where |
| --- | --- | --- |
| Question 29 | A drawer-funded refund committed but its cash not yet taken when the session is counted and closed | SALES_CASH BR-RFD-17 |
| T-17 | Lifecycle details without business meaning, settled in RF-5 (a, b, d, f, h); T-17 c is resolved by D-054, and T-17 e, g and i in RF-3B | Sections 5, 11 and 12 |

Questions 21 to 24 and 27 to 28, asked during RF-2, are answered by D-042 to D-047. Questions 25, 6, 26 and 31 (R-17) and question 30, asked before RF-3A, are answered by D-048, D-049, D-051, D-052 and D-054. Questions 33, 34 and 32, asked before RF-3B, are answered by D-060 to D-062; T-16 is resolved by D-060, D-061 and ADR-008.
