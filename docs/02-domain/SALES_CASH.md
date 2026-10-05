# Sales, payments and cash

| | |
| --- | --- |
| Status | CURRENT — written in RF-2 under D-041, 4 October 2026 (Asia/Jakarta) |
| Phase | P2 — domain rules |
| Replaces | `docs/02-domain/legacy/17-POS-SALES.md` as to domain rules |
| Map and terms | [DOMAIN_MODEL](DOMAIN_MODEL.md), [GLOSSARY](GLOSSARY.md) |

The Sale, its one full payment and how it is confirmed, commit and recovery, receipts, cash registers and cash sessions, expected cash, the session report, refunds and returns, and the Owner's daily summary. Stock effects are in [INVENTORY](INVENTORY.md); cost effects and finance reporting are in [COSTING](COSTING.md).

The conventions of DOMAIN_MODEL section 1 apply: rules are `BR-SALE-nn`, `BR-PAY-nn`, `BR-DOC-nn`, `BR-CASH-nn`, `BR-RFD-nn` and `BR-RPT-nn`; every rule names its source; `OPEN` items are in [OPEN_ITEMS](../00-governance/OPEN_ITEMS.md). Source keys are listed in DOMAIN_MODEL section 2. The receipt and screen layouts described in L-17 are not carried here; they are input for RF-4 and RF-5.

In this document "session" alone always means a cash session, never a sign-in session, and "drawer" alone means the physical cash drawer that a CashRegister identifies (GLOSSARY section 9).

## 1. The Sale

| ID | Rule | Source |
| --- | --- | --- |
| BR-SALE-01 | A Sale is a completed sale of goods, paid in full and handed over directly. There is no order, booking, deposit, outstanding balance, reservation or staged fulfilment. | L-17, D-002 |
| BR-SALE-02 | Checkout needs the acting user's own eligible OPEN cash session. This applies to the Owner, to cashiers and to combined accounts, and to sales paid by bank transfer or QRIS as much as to cash. Without such a session the Sale is refused before any payment or stock fact. | D-009, L-17, D-032 |
| BR-SALE-03 | CASHIER and the Owner sell; a combined account sells through its CASHIER role. OPERATIONS_ADMIN alone has no access to the till or to cash sessions. | L-04, L-17, D-032 |
| BR-SALE-04 | The server validates the actor and role, the source intent, the responsibility for the session, each product and its version, the authoritative price, quantity, unit and stock, the total and the payment. | L-17, BR-DOM-06 |
| BR-SALE-05 | The price of a line is the authoritative current selling price in the product master data. There is no discount, price override or negotiated price at the till. If a price changes while a cart is open, checkout becomes stale and needs an explicit review or refresh before confirmation; the total never changes silently. SaleLine and ReceiptSnapshot store the price applied to that Sale. | D-036, L-17 |
| BR-SALE-06 | A line total is quantity × unit price rounded half-up once to whole rupiah; the Sale total is the sum of the line totals (BR-NUM-01). Example: 1.5 units at Rp3.333 is Rp4.999,5, which rounds to Rp5.000. | L-02, D-006 |
| BR-SALE-07 | The server computes the Sale's business date (Asia/Jakarta) at commit and stores it with the Sale, separately from the identity of the cash session. | L-17, BR-NUM-05 |
| BR-SALE-08 | A customer name on the receipt is optional. There is no mandatory customer record and no customer relationship management. Buyer identity on documents is part of gate G1. | L-17 |
| BR-SALE-09 | Each SaleLine issues its product through a SALE_ISSUE movement. Insufficient stock refuses the Sale (INVENTORY BR-INV-04). | L-17, L-06 |

## 2. Payment

| ID | Rule | Source |
| --- | --- | --- |
| BR-PAY-01 | A Sale has exactly one full payment, by CASH, BANK_TRANSFER or STATIC_QRIS. There is no split, partial, deposit, credit or mixed payment and no second instrument. The server refuses any other method. | D-003, D-036 |
| BR-PAY-02 | CASH: the tendered amount is at least the total; change = tendered − total; the applied amount equals the total. The tendered amount is not sales value. | D-040, L-17, L-15 |
| BR-PAY-03 | BANK_TRANSFER and STATIC_QRIS: the amount recorded equals the full total. The cashier explicitly confirms that the funds were received, based on reasonable merchant evidence such as the merchant app, a soundbox or an available account statement, not only a screenshot or the customer's phone. A reference is optional, when the merchant practice provides one. | D-036, L-17 |
| BR-PAY-04 | STATIC_QRIS is static and confirmed manually. There is no payment gateway, provider API, webhook, callback, status polling or pending-payment state. Static QRIS does not authorize dynamic QRIS or any gateway integration. | D-036 |
| BR-PAY-05 | The Sale is recorded at the gross amount the customer paid. Provider fees, MDR, settlement delay and net bank settlement are not modelled in V1. | D-036 |
| BR-PAY-06 | BANK_TRANSFER and STATIC_QRIS never add to a cash session's expected physical cash. Session and Owner reports show CASH, BANK_TRANSFER and STATIC_QRIS separately. The receipt shows the QRIS method explicitly. | D-036, L-17 |
| BR-PAY-07 | Payment methods are an explicit list checked by the server. A future payment provider would be an additive, reviewed change behind that boundary; V1 has no placeholder provider record, controller or asynchronous status, and a completed Sale never becomes pending. | D-003, D-036, L-02, L-17 §Future payment seam |

**Example (L-17, CORE-012).** Total Rp75.000, tendered Rp100.000: change Rp25.000, applied Rp75.000. Tendered Rp70.000 is refused.

## 3. Confirming a Sale

| ID | Rule | Source |
| --- | --- | --- |
| BR-SALE-10 | An ordinary Sale is confirmed on one payment screen, not in a separate mandatory review dialog with a checkbox. The screen shows the total, the chosen method, for CASH the tendered amount and the change, and for BANK_TRANSFER and STATIC_QRIS the explicit confirmation that the funds were received. Exactly one final action posts the Sale. | D-040 |
| BR-SALE-11 | CASH: the final action is enabled only when the tendered amount is valid and at least the total. An exact-cash action ("Uang Pas") may be offered. The change is shown before confirmation. No checkbox. | D-040 |
| BR-SALE-12 | BANK_TRANSFER and STATIC_QRIS: no routine checkbox. The final button itself states the confirmation in clear Indonesian, for example "Dana sudah diterima — Selesaikan"; pressing it is the cashier's explicit confirmation that the funds were received. No gateway verification is claimed. | D-040 |
| BR-SALE-13 | A scanner terminator never triggers the final action; the control is never focused or wired so that a scanner Enter can submit the Sale. | D-040, L-12 |
| BR-SALE-14 | The final action shows distinct enabled, submitting, committed, rejected and uncertain states. After a successful commit the committed receipt or result is shown with no further confirmation step. On the tablet the final action has a touch target adequate for production use and sits away from ordinary cart controls; on the PC, keyboard and scanner work stays fast. | D-040, D-039 |
| BR-SALE-15 | This applies to ordinary Sale checkout only. Corrections, refunds and returns, receiving, opening stock and cash session close keep their review and confirmation steps. | D-040 |

## 4. Commit, duplicates and recovery

| ID | Rule | Source |
| --- | --- | --- |
| BR-SALE-16 | The final action freezes the authoritative source intent, the payment payload, the product and price state and the command identity. | D-040, L-17 |
| BR-SALE-17 | The Sale commits in one transaction with its SaleLines, its Payment, the SALE_ISSUE movements and balances, the cost allocation or UNKNOWN and valuation (COSTING BR-COST-16), the ReceiptSnapshot, the audit event and the durable result, and increments the session's activity version. A failure anywhere commits nothing. There is no payment-only or stock-only success and no separate payment step that could leave a Sale incomplete. | D-006, D-040, L-17, L-02 |
| BR-SALE-18 | A double click, a double tap or a repeated submission never creates a second Sale or Payment. While the request is submitting, edits and repeated posting are prevented. The source intent prevents a duplicate even when a retry uses a different key. | D-040, D-006, BR-CMD-02 |
| BR-SALE-19 | A definite rejection — for example stale price, insufficient stock, inactive product, invalid or closed cash session, method not allowed, or any other failed check of BR-SALE-04 — happens before any payment or stock fact. The user returns to an explicit review or correction state, with an explicit revision or a new eligible session as the cause requires; the payable total never changes silently. | D-040, L-17 |
| BR-SALE-20 | If the outcome is uncertain, the original intent stays frozen and the same transaction identity is recovered, queried or retried. A replacement Sale is never created because a response was lost, failure is never concluded from a timeout, and the customer is never asked to pay or transfer again before the original is recovered. A committed result stays recoverable after its cash session is CLOSED. Recovery checks authorization again. | D-040, L-17, BR-CMD-03 |
| BR-SALE-21 | The customer's payment and the bank transfer happen outside the database transaction. Printing happens after commit; a printer failure never cancels or repeats a Sale. | L-17, BR-DOM-10 |
| BR-SALE-22 | A reload keeps a minimal recovery reference without secrets or private payload. An ordinary stale draft is validated again for product and price. | L-17, L-12 |
| BR-SALE-23 | After a restore, the recovery epoch rule applies (BR-CMD-06): old browser intents are never replayed blindly. | L-17, L-09 |

## 5. Receipts and documents

| ID | Rule | Source |
| --- | --- | --- |
| BR-DOC-01 | Every Sale has one number — unique per installation, taken by the server inside the Sale transaction, increasing in allocation order with gaps allowed, and carrying the Asia/Jakarta business date as its readable part (D-062; [SCHEMA](../04-architecture/SCHEMA.md) section 6) — and one immutable ReceiptSnapshot holding: the shop name, logo, address, contact, header, footer and document identity frozen from the BusinessProfileRevision in force; the identity, template and asset revisions; the time, stored in UTC and shown in Asia/Jakarta; the cashier and the cash session number; each item with SKU, unit, quantity, unit price and line total; the total; the payment method, with QRIS shown explicitly; tendered amount and change for CASH; and the optional customer name. It never holds cost or profit. | L-17, D-036, D-038, D-062 |
| BR-DOC-02 | Receipts print through browser-print templates for thermal 58 mm, thermal 80 mm and A4 ("Nota Penjualan"), all from the same snapshot, with no re-entry. | D-039, L-17 |
| BR-DOC-03 | V1 calculates no tax, and no document claims to be equivalent to a tax invoice. Any change to tax or buyer-identity content needs gate G1 first. | D-039, L-17 |
| BR-DOC-04 | A reprint shows the same number and content with a copy marker and creates no Sale, Payment or movement. Print requests are audited. A print request, or closing the print dialog, is not proof of ink. | L-17 |
| BR-DOC-05 | Templates and assets that a document references are kept so that the document can be reproduced. | L-17, D-038 |
| BR-DOC-06 | Every receipt, history, reprint, print, PDF and media request is authorized again: a cashier reaches only their own Sales, the Owner reaches all. Knowing a number, an identifier or a URL is not permission. | L-17, L-04 |

## 6. Cash registers and cash sessions

| ID | Rule | Source |
| --- | --- | --- |
| BR-CASH-01 | A CashRegister is only the logical identity of one physical cash drawer. It is not a warehouse, an attendance list, a work schedule or a shift engine. The rule is: one drawer, one OPEN session, one responsible user. | D-009, L-17 |
| BR-CASH-02 | The Owner configures cash registers (code, label, active), with recent authentication and audit, only while no session is OPEN on the register. Historical snapshots keep the identity they recorded. | L-02 |
| BR-CASH-03 | A shop may start with one configured register. Several physical drawers follow the same rules. The number and labels of the first store's drawers are UNKNOWN (gate G3). | L-17, D-039 |
| BR-CASH-04 | A cash session has an immutable number, formed like a Sale number (D-062), its register, its responsible user, the time it opened, an explicit opening cash amount that is zero or more (Rp0 is valid; a blank amount is refused), an activity version and a status, OPEN or CLOSED. | L-17, D-062 |
| BR-CASH-05 | A CASHIER, the Owner or a combined account opens a session as themselves: choose an available authorized register, count and enter the opening cash, review, confirm. Opening locks the register, checks that neither the register nor the user has an OPEN session, and commits the session, the count, the audit event and the result together. Opening creates no Sale and no movement. | L-17, L-P3, D-032 |
| BR-CASH-06 | There is at most one OPEN session per register and at most one OPEN session per user, also when two opens race. | D-009, L-17, CORE-020 |
| BR-CASH-07 | There is no shared drawer, no transfer of responsibility within a session, and no paid-in, paid-out, cash pickup or expense from the drawer. The only outflow from a drawer is a drawer-funded CASH refund (section 8); its cash leaves the drawer only after the refund has committed (BR-RFD-17), and expected cash never goes below zero (BR-RFD-15). | D-009, D-035, D-045, D-046 |
| BR-CASH-08 | The Owner never acts as another cashier at the till; the Owner sells in the Owner's own session. | L-17, L-04 |
| BR-CASH-09 | A user who stops working closes the session. Returning on the same business date means opening a new session. A handover is: user A closes, then user B opens. A session may cross midnight and stays one accountability document. | L-17 |
| BR-CASH-10 | The activity version of a session increases with every Sale in it and with every drawer-funded refund paid from it. | L-17, D-035 |
| BR-CASH-11 | Signing out, or a sign-in session ending, never closes, transfers or changes a cash session. Whether a user who holds an OPEN session may be deactivated before it is closed is T-17 d; Owner-assisted close (BR-CASH-18) is available either way. | D-039, L-17 |

### 6.1 Expected cash and variance

| ID | Rule | Source |
| --- | --- | --- |
| BR-CASH-12 | expectedCash = openingCash + applied CASH Sale payments in the session − drawer-funded CASH refunds paid from the session. variance = physicalCash − expectedCash. BANK_TRANSFER, STATIC_QRIS and Owner-funded refunds never change expected cash. Opening cash and variance are never sales or profit. Opening and physical cash are zero or more. There is no hidden balancing adjustment. | D-035, D-036, L-17 |

**Example.** Legacy example (L-17): opening Rp20.000, applied CASH Rp100.000, BANK_TRANSFER Rp50.000, physical Rp118.000: expected Rp120.000, variance −Rp2.000. Applying D-035 and D-036 to the same session with, in addition, a STATIC_QRIS sale of Rp30.000 and a drawer-funded CASH refund of Rp10.000 paid by the Owner: expected Rp110.000; with physical Rp108.000 the variance is −Rp2.000.

### 6.2 Closing a session

| ID | Rule | Source |
| --- | --- | --- |
| BR-CASH-13 | Closing has four steps. (1) The user asks for a close preview of their own OPEN session; the server returns the session identity, the current activity version and the totals the role may see; no database lock is held while cash is counted, and blind counting is not required. (2) The user pauses checkout, counts the drawer and submits the physical cash with the session identity, the activity version and a frozen command identity; scan capture pauses and a scanner Enter never closes the session. (3) Close locks the session with the same lock as checkout and compares the activity version. (4) If it matches, close derives the source Sale, Payment and drawer-funded refund identities, totals and expected cash, and commits the physical count, variance, cutoff, closed time, the immutable CashSessionReportSnapshot, the CLOSED status, the audit event and the result together. | L-17, D-035 |
| BR-CASH-14 | If the activity version changed, close is refused with "Ada transaksi baru; hitung ulang kas" and the session stays OPEN. This covers a drawer-funded refund paid after the preview or the count began. | L-17, D-035 |
| BR-CASH-15 | A Sale that takes the session lock first commits once and changes the version, so close needs a recount. A close that commits first makes a later Sale fail before any payment or stock fact. No successful Sale is ever missing from its session report. | L-17, CORE-023 |
| BR-CASH-16 | Every UNCERTAIN checkout of the session is resolved through its original identity before close. An UNCERTAIN drawer-funded refund that names the session is resolved through its original identity before any cash is taken for it (BR-RFD-17); if it committed, its cash leaves the drawer before the session is counted, like that of every committed drawer-funded refund (BR-RFD-17; question 29 covers a close that comes first). | L-17, D-035, D-046 |
| BR-CASH-17 | A repeated or lost close, with the same or a different key, results in one CLOSED session, one snapshot and one result; a repeat with a different count never overwrites. If writing the snapshot, audit or result fails, the session stays OPEN. There is no persistent CLOSING state. | L-17, CORE-024 |
| BR-CASH-18 | The Owner may close another user's session (Owner-assisted close) in the same transaction, with current permission, a physical count, recent authentication and a mandatory reason. The responsible user does not change; the close actor is recorded. | L-17, L-04 |
| BR-CASH-19 | A CLOSED session is final: no reopening, no change of the responsible user. A counting mistake gets a linked Owner review note; the count and variance are never overwritten. A CLOSED session accepts no refund payout. Later refunds, returns and corrections are linked facts and never rewrite the session or its report. | L-17, D-035 |

### 6.3 The session report

| ID | Rule | Source |
| --- | --- | --- |
| BR-CASH-20 | The CashSessionReportSnapshot holds: report and session number (one number: the report is identified by its session's number, SCHEMA section 5.10); the frozen shop identity, template and asset revisions; register and responsible user; opened time, cutoff and closed time; opening cash; source identities and activity version; number and value of completed Sales; applied totals for CASH, BANK_TRANSFER and STATIC_QRIS separately; each drawer-funded refund separately, with the Sale and refund reference, amount, time and the Owner as actor; expected cash; physical cash; variance; close actor, reason and safe notes. It holds no cost, cost evidence, margin, profit or security data. It is the accountability document at close, not a second source of sales or payments. | L-17, D-035, D-036 |
| BR-CASH-21 | A cashier reads, prints and exports only their own sessions; the Owner reaches all; OPERATIONS_ADMIN has no access to session reports. Every list, detail, print, PDF, CSV, reprint and file request is authorized again. | L-17, L-04 |
| BR-CASH-22 | A CSV export quotes text fields, neutralizes formula and control prefixes in text, keeps a negative variance numeric, and keeps rupiah, time zone and session context. A failed print or export never affects the CLOSED status; a retry renders the same snapshot. Exports and reprints create and change nothing; their requests are audited. | L-17 |

## 7. Cash session lifecycle and visibility

The lifecycle of CashRegister, CashSession, CashSessionReportSnapshot and Owner review note is in [DOMAIN_MODEL](DOMAIN_MODEL.md) section 11.

## 8. Refunds and returns

| ID | Rule | Source |
| --- | --- | --- |
| BR-RFD-01 | Only the Owner executes refunds and returns. CASHIER, OPERATIONS_ADMIN and combined accounts never have that right. | D-035, D-032 |
| BR-RFD-02 | The Owner selects the original Sale, then the eligible lines, quantities and amounts, gives a mandatory reason with recent authentication, reviews the payout (method, amount and source of money) and the physical condition of returned goods, and confirms. | L-17, D-035, BR-USR-07 |
| BR-RFD-03 | Bounds, all cumulative over every refund and return of the original Sale: the refunded amount of the Sale never exceeds its paid value, which is the applied payment amount and equals the Sale total — never the tendered cash (BR-PAY-02); the refunded amount of a line never exceeds its line total; the returned quantity of a line never exceeds its issued quantity minus earlier returns. A partial refund of a line's value is allowed within that line's remaining money bound. The money bound and the quantity bound are separate and are protected by locks on the original Sale and the products, so racing or retried requests cannot exceed them. | L-17 §Refund/return minimal oleh Pemilik, D-008, D-035, BR-PAY-02 |
| BR-RFD-04 | One command may hold a refund, a return or both. Goods may be returned after an earlier refund, within the quantity bound, with no further money. There is no exchange or customer-credit engine. | L-17, L-P3 |
| BR-RFD-05 | A refund records its payout, by CASH or BANK_TRANSFER, with its reference: for an Owner-funded or BANK_TRANSFER refund, the payout actually made; for a drawer-funded refund, the payout amount and the selected session, committed with the refund; the cash is then taken from that drawer (BR-RFD-17). No source defines a STATIC_QRIS payout, and no rule ties the payout method to the original Sale's method. | L-17, D-035, D-036, D-046 |
| BR-RFD-06 | For a CASH refund the Owner chooses the source of the money at execution: the drawer of one OPEN cash session, or the Owner's own funds — exactly one explicitly selected source per refund. A BANK_TRANSFER refund does not affect drawer cash. | D-035, D-045 |
| BR-RFD-07 | A drawer-funded refund is linked to exactly one cash session, which is OPEN when the refund commits; its cash is then paid out from that drawer (BR-RFD-17). It may be a different session from the original Sale's. When several sessions are OPEN, the Owner selects explicitly the one the money actually came from. The refund is a correction fact at refund time and never rewrites an earlier session or session report. | D-035, D-046 |
| BR-RFD-08 | A drawer-funded refund reduces that session's expected cash (BR-CASH-12) and appears separately in its report, so the cashier is not held responsible for it. It is session activity: it follows the session's guards and concurrency rules and increases the activity version (BR-CASH-10, BR-CASH-14). | D-035 |
| BR-RFD-09 | A CLOSED session accepts no payout. An Owner-funded refund changes no session's expected cash. | D-035 |
| BR-RFD-10 | The refund payout, any saleable RETURN_RECEIPT, any restoration of the cost tied to the original sale issue (COSTING BR-COST-17), the cash session effect when there is one, the audit event and the durable command result commit together; nothing partial is published. | D-035, L-17 |
| BR-RFD-11 | A refund without returned goods adds no stock. A damaged or non-saleable return adds no saleable stock. A saleable return restores the cost tied to the original sale issue, for the returned quantity, once (COSTING BR-COST-17); after a later correction the linked return is recomputed together with the issue (D-061). | D-035, D-034, D-061 |
| BR-RFD-12 | The original Sale, Payment, ReceiptSnapshot and any closed session report stay unchanged; the correction record is linked to the original. A mandatory reason, recent authentication, the audit actor and time, and an idempotent durable result are always required. | D-035, L-17 |
| BR-RFD-13 | A drawer-funded refund is never counted twice: the same refund fact feeds net sales and refund reporting, and the cash session reconciliation, each in its own domain, never as two commercial refunds. | D-035 |
| BR-RFD-14 | Every payout happens outside the database transaction (BR-DOM-10). For an Owner-funded or BANK_TRANSFER refund, if recording the payout is uncertain, the original intent and the payout evidence are checked before anything is done again. A drawer-funded refund follows BR-RFD-17. | L-P3, BR-CMD-03, D-046 |
| BR-RFD-15 | A drawer-funded CASH refund is allowed only when its amount is at most the current expected cash of the selected OPEN session. Equality is valid: expected cash may reach exactly Rp0, never below. The check is made before commit, inside the refund's transaction under the session's lock (BR-RFD-08), so racing activity cannot together take expected cash below zero. There is no Owner override. The guard rests on the system's expected cash; it does not claim the physical cash was counted. | D-045, D-035 |
| BR-RFD-16 | If the selected drawer's expected cash is not enough, that funding source is refused before commit. The Owner may then explicitly choose another eligible OPEN session whose expected cash is enough, or the Owner's own funds. The system never switches drawers or funding sources by itself, and never splits one refund across several drawers or between a drawer and the Owner's funds: a cash refund has exactly one explicitly selected source. No cash top-up, paid-in, cash adjustment or negative-drawer mechanism exists as a workaround. | D-045 |
| BR-RFD-17 | For a drawer-funded refund, cash is taken from the drawer only after the refund has committed. The order is: the Owner selects the session; the server checks the bound of BR-RFD-15 and commits the refund; only then is the cash taken from that drawer and handed over. A refusal therefore always happens before any cash leaves the drawer. The cash of a committed drawer-funded refund leaves the drawer before that session is counted for close, because its expected cash already includes the refund (BR-CASH-12); what happens if a session is counted and closed before that cash was taken is OPEN — question 29. If the outcome is uncertain, the Owner first checks the status of the original refund command (BR-CMD-03); no cash is taken until the refund is recorded as committed, and a replacement refund is never created. Owner-funded and BANK_TRANSFER refunds still record the payout actually made (BR-RFD-05). | D-046, D-045, D-035 |

**Example (D-045, D-046).** A session with opening cash Rp50.000 and applied CASH sales of Rp30.000 has expected cash Rp80.000. A drawer-funded refund of Rp100.000 from it is refused before commit, and no cash leaves the drawer; the Owner may choose another OPEN session with enough expected cash, or the Owner's own funds. A drawer-funded refund of Rp80.000 is allowed; once it has committed, Rp80.000 is taken from the drawer and handed over, and expected cash is Rp0.

## 9. Daily summary and reporting

| ID | Rule | Source |
| --- | --- | --- |
| BR-RPT-01 | The Owner's daily summary uses Asia/Jakarta half-open days. Its figures are computed once from the source records: Sales, their payments, quantities and issue-cost allocations by each Sale's business date; refunds and returns on their server posting date (COSTING BR-COST-36); cost of goods sold and gross profit under COSTING section 9, from the latest cost evidence (BR-COST-30). Session snapshot totals are never added on top. Opening cash and variance are never sales or profit. | L-17, L-15, D-034 |
| BR-RPT-02 | The summary lists the relevant sessions with cashier, register, open and close time, status and each session's variance. A session that crosses midnight is shown once, with its full time span, on its close date or in an active section until it closes. Same-day returns to work and handovers give separate reports with no double counting. | L-17 |
| BR-RPT-03 | Later refunds and corrections never rewrite a closed session snapshot. Financial totals follow server posting dates and the cost evidence rules of COSTING; the as-closed report stays reproducible and is linked to later corrections. | L-17, D-034 |
| BR-RPT-04 | A cashier sees their own Sales, receipts, session reports and the totals their role allows. The Owner sees all sessions and all reports. Finance reporting is in COSTING section 9. | L-17, L-04 |

## 10. Outside V1

Orders, bookings, deposits, credit, split or partial payments; payment gateways, dynamic QRIS, cards and e-wallets; discounts and price overrides; shared drawers, paid-in, paid-out and cash pickups; complex shifts, attendance and payroll; offline sales; tax calculation; exchanges and customer credit (L-PRD §Batas Later, D-035, D-036, D-039).

## 11. Open items

| Item | Subject |
| --- | --- |
| Question 29 | A drawer-funded refund committed but its cash not yet taken when the session is counted and closed |
| T-17 d | Deactivating a user who holds an OPEN cash session |
| T-17 f | Editing an Owner review note |
| Gate G1 | Tax position and buyer identity on documents (PKP/PPN status of the first store: UNKNOWN) |
| Gate G3 | Number and labels of drawers, opening-float practice and whether one device is shared by several staff (first store: UNKNOWN) |
| Gate DEVICES | Real printers and paper width (first store: UNKNOWN), scanners, PC and tablet |

T-16 was resolved in RF-3B by D-060, D-061 and ADR-008 (COSTING section 10).

Sales, payment, receipt, refund and cash session records are business records: they are kept at least 10 years and V1 has no function that deletes them (D-044, DOMAIN_MODEL section 12).
