# Product blueprint

| | |
| --- | --- |
| Status | CURRENT — written in RF-2 under D-041, 4 October 2026 (Asia/Jakarta) |
| Phase | P1 — product definition, scope and acceptance |
| Replaces | `docs/01-product/legacy/01-PRD.md` and `docs/01-product/legacy/P1-PRODUCT.md` |
| Requirements | [REQUIREMENTS](REQUIREMENTS.md) |
| Domain | [DOMAIN_MODEL](../02-domain/DOMAIN_MODEL.md) and the documents it lists; terms in [GLOSSARY](../02-domain/GLOSSARY.md) |

What BantuKasir is for, who uses it, what V1 includes and excludes, and what must be true for V1 to be released. Every statement comes from a decision in [DECISIONS](../00-governance/DECISIONS.md) or from the legacy product documents; source keys are listed in DOMAIN_MODEL section 2. Nothing here adds business meaning.

## 1. Problem

A small shop that sells goods needs to sell quickly and correctly at the counter, keep its stock right, know its real gross profit, and hold each person accountable for the cash in their drawer — without paying for extra software services every month and without losing its records when something breaks. Today the shop has no system that does this for it. (Derived from the outcomes in L-PRD §Hasil and from D-002, D-038 and the cost policy.)

## 2. Goals

| ID | Goal | Source |
| --- | --- | --- |
| PG-1 | A cashier sells fast and without mistakes on a PC or a tablet: scan or search, cart, one payment screen, receipt. Stock is never oversold and a customer is never charged twice when something is retried. | L-PRD §Hasil, D-039, D-040 |
| PG-2 | Stock is exact: every change has a reason and a history, and the balance always equals that history. | L-PRD, D-006 |
| PG-3 | The Owner sees an honest cost of goods sold and gross profit, with unknown cost shown as unknown, never as zero. | L-PRD, D-033, D-034 |
| PG-4 | Cash is accountable per person: each drawer has one responsible user at a time, and differences are visible. | D-009, D-035 |
| PG-5 | Mistakes are corrected by new linked records, never by rewriting history, and every change is audited. | D-006, D-008, D-034, D-035 |
| PG-6 | The business can be recovered from an independent backup. | D-005, L-PRD |
| PG-7 | No additional recurring production cost, unless the Owner approves it. | COST_POLICY |
| PG-8 | The same product can be installed separately for several shops, each with its own identity. | D-038 |

## 3. Non-goals

BantuKasir V1 is not: a multi-tenant or multi-outlet system; a warehouse system; an accounting or tax system; a payroll, attendance or shift system; a customer-relationship or loyalty system; an online shop or ordering system; an integration with a payment gateway. It does not report net profit. (D-002, D-036, D-038, D-039, L-PRD §Batas Later.)

## 4. Users

| User | Description | Device |
| --- | --- | --- |
| Owner (SUPER_ADMIN) | The shop owner. Sets up the shop, enters opening stock, corrects cost, handles refunds and returns, reads all reports and the audit log, manages users and cash registers. May also sell, in the Owner's own cash session. | Any supported device; Owner screens follow the framework's mobile-first rule (FD-05) |
| OPERATIONS_ADMIN | Manages the catalogue, receives goods, records non-sale issues and watches stock. | Any supported device (FD-05) |
| CASHIER | Sells at the till in their own cash session, opens and closes that session, prints receipts. | PC with physical keyboard and USB HID scanner, or tablet with touch and Bluetooth HID scanner (D-039) |

One account may hold CASHIER and OPERATIONS_ADMIN together (D-032). The first client is oriented to stationery (ATK); test fixtures use synthetic stationery goods only, never real client data (L-PRD). Facts about the first store that are not yet known are listed in section 13.

## 5. Roles and what each may do

This is the product statement of who may do what. Server-side enforcement, object scope and the detailed permission matrix are security content: they stay in `docs/05-security/legacy/04-AUTH-RBAC-SECURITY.md` until RF-3 and must agree with this table; where that legacy table predates a decision below, the decision prevails (SOURCE_OF_TRUTH section 2).

| Capability | Owner | OPERATIONS_ADMIN | CASHIER |
| --- | --- | --- | --- |
| Search and scan products (operational view, never cost) | Yes | Yes | Yes, at the till |
| Products, categories, selling prices, barcodes, labels, product photos, CSV product import | Yes | Yes | No (sees thumbnails) |
| Receiving; purchase cost on receiving documents, editable until confirmation; after an Owner correction, the current cost with the Owner's mark | Yes | Yes (D-033, D-047) | No |
| Manual issue ("Barang keluar") | Yes (explicit entry, D-018) | Yes | No |
| Current stock, stock attention, operational stock history | Yes | Yes, without cost except receiving purchase cost | Only the stock needed at checkout |
| Opening stock and onboarding; adjustment; reversal | Yes | No | No |
| Cost correction after posting; valuation, cost of goods sold, gross profit, margin, cost reports | Yes | No | No |
| Sell at the till; open, close and read their own cash session; own sales and receipts | Yes, in the Owner's own session | No | Yes |
| Refunds and returns, paid from the drawer of an OPEN session or the Owner's own funds | Yes (D-035) | No | No |
| All cash sessions and reports, Owner-assisted close, review notes | Yes | No | No |
| Users and roles (including deactivation, reactivation and anonymization of former staff, D-042, D-043), BusinessProfile, cash registers, system status, audit log | Yes | No | No |
| Own account | Yes | Yes | Yes |

A combined account has the union of the CASHIER and OPERATIONS_ADMIN columns and never any Owner right (D-032). Master data is deactivated and reactivated, never deleted (D-042). Sources: L-04 §Otorisasi server, L-PRD §Role, D-018, D-032, D-033, D-035, D-036, D-037, D-042, D-043, D-047.

## 6. Primary journeys

| ID | Journey | Actor | Requirements |
| --- | --- | --- | --- |
| J-01 | Open a cash session: choose a free cash register, count and enter opening cash (Rp0 allowed), confirm. | CASHIER, Owner | REQ-CASH-03 |
| J-02 | Sell: scan or search, adjust quantities in the cart, choose CASH, BANK_TRANSFER or STATIC_QRIS on the payment screen, press the one final action, show and print the receipt. | CASHIER, Owner | REQ-SCAN-01 to REQ-SCAN-04, REQ-SALE-01 to REQ-SALE-12, REQ-PAY-01 to REQ-PAY-05, REQ-DOC-01 to REQ-DOC-05 |
| J-03 | Close a cash session: preview, count the drawer, close; recount if new activity arrived; read and print the session report. | CASHIER, Owner | REQ-CASH-06 to REQ-CASH-12 |
| J-04 | Add products: search first, create, set price, barcodes, label and photo; or import new products from CSV. | OPERATIONS_ADMIN, Owner | REQ-CAT-01 to REQ-CAT-12 |
| J-05 | Receive goods with cost or with cost marked unknown; record goods leaving for a non-sale reason; follow stock attention. | OPERATIONS_ADMIN, Owner | REQ-INV-04 to REQ-INV-06, REQ-INV-10 |
| J-06 | Onboard the shop: products, opening stock under the onboarding freeze, reconciliation, release. | Owner | REQ-INV-07 |
| J-07 | Correct: complete or correct cost, adjust stock, reverse an eligible movement, refund and take back goods. | Owner | REQ-COST-05 to REQ-COST-09, REQ-INV-08, REQ-INV-09, REQ-RFD-01 to REQ-RFD-09 |
| J-08 | Oversee: finance report and daily summary, all session reports, audit log, system status; manage users, BusinessProfile and cash registers. | Owner | REQ-RPT-01 to REQ-RPT-06, REQ-AUD-01, REQ-AUD-02, REQ-ACC-05 to REQ-ACC-11, REQ-CFG-01 to REQ-CFG-04, REQ-OPS-04 |

Page flows and screens are designed in RF-4 and RF-5, not here.

## 7. V1 scope

In V1 (D-002 as amended by D-032, D-036, D-037, D-038):

- goods only, one business and one stock pool per installation;
- exactly three fixed roles, with the CASHIER and OPERATIONS_ADMIN combination;
- catalogue with categories, selling prices, barcodes (manufacturer and internal), labels, search-first creation, CSV import of new products, one optional photo per product;
- receiving, opening stock with onboarding, manual issue, adjustment, reversal, stock attention, stock history and reconciliation;
- moving-weighted-average cost with explicit UNKNOWN, Owner-only cost correction with replay, Owner-only cost of goods sold and gross profit;
- cash accountability: cash registers, own cash sessions, expected cash, safe close, immutable session reports;
- fully paid Sales with one payment by CASH, BANK_TRANSFER or STATIC_QRIS, confirmed on one payment screen;
- receipts for thermal 58 mm, thermal 80 mm and A4 by browser print, reprints;
- Owner-only refunds and returns, paid from the drawer of an OPEN cash session (never beyond its expected cash, and only after the refund is recorded; D-035, D-045, D-046) or the Owner's own funds;
- audit; reports for each role; BusinessProfile with revisions; independent backup and recovery;
- lifecycle: master data deactivated and reactivated, never deleted; Owner-only anonymization of former staff accounts; business records kept at least 10 years (D-042 to D-044).

## 8. Outside V1

Services, bookings, orders, deposits, deferred fulfilment and reservations; warehouses, locations, transfers and goods in transit; HR, attendance, payroll, complex shifts, shared active drawers, paid-in, paid-out and cash pickups; price overrides and discount engines; unpaid, split or partial payments; accounts receivable and payable, net profit, expenses and tax accounting; serial numbers, pack conversion, batches, expiry dates and consignment; automatic supplier purchasing, CRM, leads and targets; XLSX import and any import of stock or cost; product photo galleries and bulk image import; Web Push, email notifications, outbox and general background workers; camera scanning and offline sales; payment gateways, dynamic QRIS, cards and e-wallets; public sign-up, two-factor authentication, dynamic roles and multi-tenancy; tax calculation; the phone as a primary cashier device; deleting master data or business records, and any archival-delete or expiry of records; splitting one refund across several funding sources; cash top-ups.

Sources: L-PRD §Batas Later as amended by D-036 (static QRIS moved into V1) and D-037 (CSV product import and product photos moved into V1); D-038; D-039; D-042; D-044; D-045. A future method code or a design template never activates any of these (L-PRD). If a real shop turns out to need one of them, the affected work stops for an Owner decision before any implementation (L-PRD, L-06).

## 9. Requirements

Functional and non-functional requirements, each with its acceptance criteria and its source, are in [REQUIREMENTS](REQUIREMENTS.md). Detailed business rules are in the domain documents.

## 10. Non-functional requirements (summary)

| Subject | Requirement | Status |
| --- | --- | --- |
| Language | Indonesian is the default interface language, in plain Indonesian. English in V1 is question 5. | Decided (D-020); English DEFERRED (FD-02) |
| Devices | PC and tablet are primary cashier devices with equivalent cashier capabilities; the phone is not. | Decided (D-039, FD-05); real devices not yet accepted (gate DEVICES) |
| Printing | Browser-print receipt templates for 58 mm, 80 mm and A4. | Decided (D-039); real printer and paper width UNKNOWN |
| Sign-in | Sign-in sessions end after 30 minutes of inactivity and 12 hours at most; recent authentication within 15 minutes for sensitive Owner actions. | Decided (D-013, D-039); sign-in method is question 6 |
| Performance | Barcode lookup p95 ≤ 500 ms, product search ≤ 700 ms, 50-line Sale ≤ 2 s, period report ≤ 2 s with two concurrent cashiers. | Proposed targets, not measured; dataset waits for gate G2 (T-09) |
| Recovery | Independent backup outside the server; RPO ≤ 6 h and RTO ≤ 4 h. | Backup mandatory (D-005); objectives are proposals only (gate G4, question 18) |
| Cost | No additional recurring production cost without Owner approval. | Decided (COST_POLICY) |
| Security and integrity | Deny-by-default server authorization; threat controls; exact money and quantities; append-only facts; transactional and idempotent critical actions. | Decided (AGENTS rules 7 and 8, D-006, D-007, D-013) |
| Tax | V1 calculates no tax and claims no equivalence with a tax invoice. | Decided (D-039); PKP/PPN status of the first store UNKNOWN (gate G1) |
| Accessibility | Keyboard use, visible focus, 200 % zoom, screen reader, on-screen keyboard, long content, contrast, usable touch targets. | Validation requirements for the rebuilt design (OPEN_ITEMS section 3); targets set in RF-4 |
| Retention | Business records kept at least 10 years, as a minimum and not a deletion deadline; no deletion feature in V1; not a claim of legal or tax compliance. | Decided (D-044) |

Each row is stated as a requirement in REQUIREMENTS section 18, except retention, which is REQ-OPS-05 in section 17.

## 11. Acceptance

A requirement is accepted only when its acceptance criteria pass on the revision being released, with real evidence: real PostgreSQL for constraints, races and rollback; direct requests for authorization; real devices for scanners and printers; a representative server for performance; a real restore for recovery (L-08). Legacy acceptance suites (`AUTH01`, `POS01`, …) remain the test references until RF-6 rebuilds the test strategy. A passing check is not a release authorization (PROJECT_RULES section 5).

## 12. Release boundary

V1 may be released only when all of the following hold (L-P10, L-08 §Hold, D-025):

1. every requirement in REQUIREMENTS is accepted on the release revision;
2. the Owner has approved the rebuilt design at gates P7-G1, P7-G2 and P7-G3;
3. gates G1 to G4, DEVICES and TARGET are satisfied with real evidence;
4. the Owner has authorized the exact target, revision and operation (gate RELEASE);
5. a pilot with real roles reconciles sales, sessions, stock, reports, reprints and backup, and the Owner accepts it in production.

Who may operate a production release is question 20 (OPEN). No release, migration, cutover or other production operation is authorized today.

## 13. Facts about the first store that are not known

These are recorded as UNKNOWN. Nothing in the product depends on guessing them (D-041).

| Fact | Gate or item |
| --- | --- |
| Number of products at go-live | G2 |
| Whether an initial product list already exists (Excel, Google Sheets or CSV) | G2 |
| Base units, precisions, barcodes, opening volume, availability of cost evidence, need for serial numbers | G2 |
| Whether one device is shared by several staff | G3 |
| Number and labels of cash drawers | G3 |
| Usual opening float | G3 |
| Receipt printer and paper width | DEVICES |
| PKP/PPN status and tax-document needs | G1 |
| Whether LATANSA is the first installation | Open fact; it changes nothing in the product (D-038) |
| The server: provider, size, other services, staging | Question 7, gate TARGET |

The first client has confirmed that its cashiers will use both a PC and a tablet (OF-02); the devices themselves are not yet verified.

## 14. Open product decisions

| Item | Subject | Needed before |
| --- | --- | --- |
| Question 5 | English in the interface (FD-02) | RF-3 |
| Question 6 | Sign-in method, password rule and reset delivery | RF-3 |
| Question 18 | Backup destination, cost and recovery key | RF-3 |
| Question 20 | Who may operate on production | RF-6 |
| Question 25 | Whether more than one account may hold SUPER_ADMIN | RF-3 |
| Question 26 | Correcting a manufacturer barcode attached to the wrong product (revalidation item R-17) | RF-3 |
| Question 29 | A drawer-funded refund committed but its cash not yet taken when the session is counted and closed | RF-5 |

Questions 21 to 24 and 27 to 28, raised during RF-2, were answered by D-042 to D-047 ([OPEN_ITEMS](../00-governance/OPEN_ITEMS.md)).

Input carried to RF-4 (D-018): interactive trend-chart points showing date and value.
