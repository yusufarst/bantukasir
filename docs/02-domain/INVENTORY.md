# Inventory

| | |
| --- | --- |
| Status | CURRENT — written in RF-2 under D-041, 4 October 2026 (Asia/Jakarta); amended in RF-5 under D-087, 18 October 2026: onboarding once per set of business data and after a restore (D-071, D-072), and the correction of onboarding entries before the release (T-17 b) |
| Phase | P2 — domain rules |
| Replaces | `docs/02-domain/legacy/06-INVENTORY-SPEC.md` |
| Map and terms | [DOMAIN_MODEL](DOMAIN_MODEL.md), [GLOSSARY](GLOSSARY.md) |

Stock movements, the stock balance, receiving, manual issue, opening stock and onboarding, adjustment, reversal, returns to stock, stock attention and reconciliation. The money side of every movement — cost evidence, valuation, allocations and corrections — is in [COSTING](COSTING.md). Sales and returns are initiated in [SALES_CASH](SALES_CASH.md).

The conventions of DOMAIN_MODEL section 1 apply: rules are `BR-INV-nn`, every rule names its source, and `OPEN` items are in [OPEN_ITEMS](../00-governance/OPEN_ITEMS.md). Source keys are listed in DOMAIN_MODEL section 2.

## 1. Principles

| ID | Rule | Source |
| --- | --- | --- |
| BR-INV-01 | There is one stock pool for the whole business. There is no warehouse, location, transfer or reserved quantity. Putting a product in a cart or scanning it reserves nothing. | L-06, L-P3, D-038 |
| BR-INV-02 | Every physical change of stock is one immutable StockMovement with its type, product, signed quantity, source, reason, actor, server time and posting sequence within the product. Movements are the truth about stock. | L-06, L-02, D-006 |
| BR-INV-03 | StockBalance is updated in the same transaction as each movement. On-hand quantity always equals the sum of the product's signed movements. The balance is never edited directly. | L-06, D-006 |
| BR-INV-04 | Stock is never negative. When two requests compete for the last unit, exactly one succeeds and the other is refused for insufficient stock. | L-06, D-006, CORE-008 |
| BR-INV-05 | The server's time and the posting order within a product are facts. An optional document date changes neither the order nor the date of a fact and never backdates it. | L-06, BR-NUM-05 |

## 2. Movement types

| Type | Shop term (provisional) | Sign | Who | Source and required evidence | Cost effect (COSTING) |
| --- | --- | --- | --- | --- | --- |
| OPENING | Stok awal | + | Owner | The Owner's verified initial count for a product with no earlier movement; count reference; acquisition cost KNOWN or UNKNOWN | Adds value, or makes it unknown |
| RECEIPT | Barang masuk | + | Owner, OPERATIONS_ADMIN | A confirmed receiving document line; acquisition cost KNOWN or UNKNOWN; optional reference | Adds value, or makes it unknown |
| SALE_ISSUE | Keluar karena penjualan | − | Created only by a completed Sale (CASHIER or Owner) | A SaleLine of a completed Sale | Allocation enters cost of goods sold |
| MANUAL_ISSUE | Barang keluar | − | Owner, OPERATIONS_ADMIN | A confirmed manual issue with a mandatory non-sale reason | Reduces inventory value by its allocation, or the allocation is UNKNOWN; never cost of goods sold |
| ADJUSTMENT | Penyesuaian stok | + or − | Owner | An Owner-verified physical correction with reason, count and reference | Positive: adds value, or makes it unknown (cost evidence KNOWN or UNKNOWN); negative: as MANUAL_ISSUE |
| REVERSAL | Pembatalan | inverse of source | Owner | The exact inverse of one eligible OPENING, RECEIPT or MANUAL_ISSUE | Replayed as a logical cancellation of the source |
| RETURN_RECEIPT | Retur masuk stok | + | Created only by an Owner return of saleable goods | A verified return linked to the original SaleLine and its issue | Adds the cost tied to the original sale issue for the returned quantity, once (COSTING section 6) |

Sources: L-06 §Command fisik, L-15, D-034, D-035. Shop terms are provisional until gate P7-G2 ([GLOSSARY](GLOSSARY.md)).

## 3. Rules for every posting

| ID | Rule | Source |
| --- | --- | --- |
| BR-INV-06 | Every posting command validates, under product locks taken in a fixed order: the product is active; quantities respect its precision and range; the actor is active and authorized; a required reason is present; the source is eligible; the expected versions match. The lock order is in TECHNICAL_RULES section 7. | L-06, L-02, TECHNICAL_RULES |
| BR-INV-07 | Lines of the same product in one command are added together before the stock check. | L-06, CORE-008 |
| BR-INV-08 | A posting writes its movements, the cost records COSTING requires for it (acquisition cost evidence, or an issue-cost allocation or UNKNOWN), balances, valuation, audit event and durable result in one transaction. A failure anywhere, including in writing cost, audit or result, leaves no movement and no balance change. An issue-cost allocation is an immutable fact recorded at commit, with a separately stored projection that a replay replaces (D-060; [ADR-008](../adr/ADR-008-cost-allocation-representation.md)). | L-06, D-006, D-060 |
| BR-INV-09 | A retry with the same or a different key posts once. A lost response is recovered through the status or retry of the original command, never by posting a replacement movement. | L-06, BR-CMD-02, BR-CMD-03 |
| BR-INV-10 | Every posting passes a review with an explicit confirmation (BR-CMD-04). Searching, entering quantities and reviewing create no movement. | L-P3, BR-DOM-09 |
| BR-INV-11 | The onboarding freeze (section 6) applies to ordinary stock postings and Sales. OPENING is entered while onboarding is on; products created after onboarding enter stock through RECEIPT (BR-INV-16). A reconciliation block on the product (section 11) applies to every posting. | L-06, L-P3 |

## 4. Receiving — Barang Masuk

| ID | Rule | Source |
| --- | --- | --- |
| BR-INV-12 | The Owner and OPERATIONS_ADMIN receive goods. Each line names an existing product found through the shared Product Search (creating a product, when allowed, is a separate step and posts nothing), a quantity greater than zero in the product's precision, and the acquisition cost as a KNOWN amount or UNKNOWN. An optional source or reference may be added. After review and explicit confirmation each line posts one RECEIPT. | L-06, L-P3, D-033 |
| BR-INV-13 | A missing cost is UNKNOWN, shown as "Biaya perlu dilengkapi pemilik", and is never stored as zero. An explicit zero cost needs evidence or a reason confirmed by the Owner (COSTING BR-COST-04). | D-033, L-15, L-06 |
| BR-INV-14 | OPERATIONS_ADMIN sees the purchase cost on every receiving document the OPERATIONS_ADMIN role can access, not only the documents that person created, and may enter and correct it while the document is being entered or reviewed. After confirmation OPERATIONS_ADMIN cannot change it; only the Owner corrects cost after posting, by a cost correction (COSTING section 7). | D-033, D-034 |
| BR-INV-15 | OPERATIONS_ADMIN never sees moving weighted average cost, cost of goods sold, margin, profit, inventory value, or cost and profitability reports. After the Owner corrects the cost of a confirmed receiving line, OPERATIONS_ADMIN sees on that receiving document the current cost after the latest correction, the mark "Dikoreksi Pemilik", and the cost recorded at confirmation. Completing an UNKNOWN cost follows the same rule (D-034): OPERATIONS_ADMIN sees the completed cost, the same mark, and that the cost was UNKNOWN at confirmation. The reason for the correction and the rest of the correction history stay Owner-only (BR-DOM-07). A rejected correction changes nothing that OPERATIONS_ADMIN sees. CASHIER has no access to purchase cost. Labels are provisional until P7-G2. | D-033, D-034, D-047, L-04 |
| BR-INV-16 | Receiving is separate from opening stock and from manual issue. A RECEIPT is never used to disguise an opening count. Products created after onboarding enter stock through RECEIPT. | L-06, CORE-043 |
| BR-INV-17 | No stock or cost fact exists before confirmation. Whether an unconfirmed receiving document is held on the server, so that another authorized user can continue it, is decided by D-069 (T-17 a): not in V1. A receiving draft is held in page memory only, never in browser storage, and recovery after a reload uses the command reference (SECURITY_MODEL section 7, ADR-007); the earlier write-only rule for cost entered by OPERATIONS_ADMIN no longer applies. | D-033, L-06, L-04 |

## 5. Manual issue — Barang Keluar

| ID | Rule | Source |
| --- | --- | --- |
| BR-INV-18 | The Owner and OPERATIONS_ADMIN record goods leaving stock for a reason other than a sale. The Owner has an explicit "Barang keluar" entry for this. | L-06, L-04, D-018 |
| BR-INV-19 | Each line names an active product and a quantity. A reason is mandatory, chosen from: damaged, internal use, lost, sample, other. The review shows stock before, the change and stock after. After explicit confirmation the issue posts one MANUAL_ISSUE per line. | L-06, L-P3 |
| BR-INV-20 | Insufficient stock refuses the issue; a warning is never an override. | L-P3, BR-INV-04 |
| BR-INV-21 | A manual issue reduces inventory value by its issue-cost allocation: q × (V / Q) at the average before the issue (COSTING BR-COST-10), or the whole remaining value when it takes the product's stock to zero (BR-COST-11). While the product's valuation is unknown, the allocation is UNKNOWN (BR-COST-13). It never counts as cost of goods sold. | L-15, L-06 |
| BR-INV-22 | A manual issue is never a way to record or hide a customer sale: it creates no Sale, Payment or receipt. | L-06, CORE-044 |

## 6. Opening stock and onboarding

| ID | Rule | Source |
| --- | --- | --- |
| BR-INV-23 | Only the Owner enters opening stock. Real opening data needs gate G2 and an authorized cutover. Synthetic data is allowed only in an isolated non-production environment. | L-06, L-P3 |
| BR-INV-24 | The Owner starts onboarding at a documented cutover; its persistent state is NOT_STARTED, ACTIVE or RELEASED. While it is ACTIVE, ordinary stock postings and Sales are refused, including requests that race the state change, which use the same guard. No database lock is held while people count. Creating products stays possible and creates no stock. Onboarding is started and released at most once for a set of business data: after its release it never starts or continues again, also after a reinstallation, a server migration or the restore of an earlier backup. After a restore whose onboarding state cannot be verified, onboarding is held — not started, continued or released — until the Owner's restore reconciliation; if it was never released, it continues as the same period. | L-06, CORE-010, D-071, D-072 |
| BR-INV-25 | One opening command holds at most 100 product rows. Each positive row has a quantity in the product's precision, an acquisition cost KNOWN or UNKNOWN, and a count reference. All rows commit together; one invalid row, or a 101st row, refuses the whole command. A duplicate or retried command returns the original result. | L-06, CORE-010, CORE-045 |
| BR-INV-26 | OPENING is posted while onboarding is ACTIVE, once per product and only for a product with no earlier movement, guarded against races. Different commands cover different products. One product's opening is never split across commands or disguised as a receipt. Before the release, an OPENING that the Owner reversed in full may be replaced by one explicit replacement OPENING (BR-INV-48). A product never has more than one effective OPENING, one that is not reversed. | L-06, D-087 |
| BR-INV-27 | A zero count is recorded in the onboarding checklist and creates no movement. Before the release, the Owner may withdraw it with a reason and recent authentication: the zero count stays, a withdrawal record is added, and the product can be counted again. | L-06, D-087 |
| BR-INV-47 | A product has at most one effective onboarding entry: a zero count that is not withdrawn, or an OPENING that is not reversed. Correcting an entry before the release is append-only and audited — a withdrawal, a reversal, an explicit replacement — and only the Owner corrects, with a reason and recent authentication. There is no reset, no deletion and no edit of an entry, and no correction of this kind after the release (BR-INV-29). | D-087 |
| BR-INV-48 | A replacement OPENING is recorded only by the Owner, with a reason, a count reference, its acquisition cost KNOWN or UNKNOWN and recent authentication, while onboarding is ACTIVE and not held; only for a product whose OPENINGs are all reversed and which has no effective entry; and it names the reversed opening line it replaces, which is replaced at most once. The original OPENING, its reversal and the replacement all stay in the history. | D-087 |
| BR-INV-28 | Before releasing the onboarding flag, the Owner verifies all counts, the barcode and minimum-stock setup and the remaining unknown costs. | L-06 |
| BR-INV-29 | After onboarding, mistakes are corrected by authorized corrections (adjustment, reversal, cost correction), never by a reset, a re-import or a balance edit. | L-06 |
| BR-INV-30 | If the real catalogue makes manual opening entry impractical, or serial, pack or batch identity turns out to be required, the affected onboarding stops for an Owner decision. No import of stock or cost is added silently; CSV import creates products only. | L-06, D-037 |

## 7. Adjustment

| ID | Rule | Source |
| --- | --- | --- |
| BR-INV-31 | Only the Owner adjusts stock, with a signed quantity, a reason, a count and a reference, and with recent authentication. | L-06, L-04, CORE-009, BR-USR-07 |
| BR-INV-32 | A positive adjustment carries acquisition cost evidence. Like all acquisition cost evidence, its status is KNOWN or UNKNOWN (COSTING BR-COST-02). A negative adjustment reduces inventory value by its issue-cost allocation in the same way as a manual issue (BR-INV-21), including the final-issue rule and UNKNOWN, and never counts as cost of goods sold. | L-06, L-15 |
| BR-INV-33 | An adjustment never makes stock negative and is never used to disguise or remove an earlier fact. | L-06, L-P3 |

## 8. Reversal

| ID | Rule | Source |
| --- | --- | --- |
| BR-INV-34 | Only the Owner reverses, with a reason and recent authentication. A reversal is one full, linked REVERSAL that is the exact inverse of one eligible standalone OPENING, RECEIPT or MANUAL_ISSUE. The original stays. The Owner does not enter a free quantity. A RECEIPT or MANUAL_ISSUE is reversed only while onboarding is not ACTIVE (BR-INV-24); an OPENING may also be reversed while it is ACTIVE, to correct it before the release (BR-INV-48). | L-06, L-P3, D-008, CORE-009, D-087 |
| BR-INV-35 | A source is reversed at most once. A repeat with the same or a different key is refused or returns the original result. | L-06, CORE-009, BR-CMD-02 |
| BR-INV-36 | SALE_ISSUE and RETURN_RECEIPT are never reversed directly; sales are corrected by refunds and returns (SALES_CASH). ADJUSTMENT and REVERSAL are not eligible sources. | L-06 |
| BR-INV-37 | A reversal is refused if it would make stock negative now, if it would leave an invalid history (an issue that would have had no stock at its time, even when the current balance stays non-negative), or if the dependent valuation cannot be replayed safely (COSTING). | L-06, L-15 |

## 9. Returns to stock

| ID | Rule | Source |
| --- | --- | --- |
| BR-INV-38 | A RETURN_RECEIPT is created only by an Owner return of verified, saleable goods, linked to the original SaleLine and its issue, within the cumulative return-quantity bound (SALES_CASH section 8). It restores the cost tied to the original sale issue, for the returned quantity, once (COSTING BR-COST-17). | L-06, D-035, D-034 |
| BR-INV-39 | A refund without returned goods adds no stock. A damaged or non-saleable return adds no saleable stock. | L-06, D-035 |

## 10. Stock attention

| Condition of an active product | State | Shop label (provisional) |
| --- | --- | --- |
| on-hand = 0 | OUT | Habis |
| 0 < on-hand ≤ minimum stock | LOW | Menipis |
| on-hand > minimum stock | NORMAL | Normal |

| ID | Rule | Source |
| --- | --- | --- |
| BR-INV-40 | The states above are derived from the current balance and minimum stock. A minimum of zero has no LOW interval. A new active product with stock 0 is OUT; a "pending setup" filter may set it apart, but never calls it Normal. | L-06, CORE-015 |
| BR-INV-41 | The badge and the list come from one query of committed balance and minimum stock, and agree with each other. They refresh after a change, when the tab regains focus and at least every 30 seconds while visible. A failed refresh shows a stale or error state with the time of the last refresh, never an empty list, zero or Normal. | L-06, CORE-015 |
| BR-INV-42 | Stock attention is an in-app list and badge only: no notification engine, no acknowledgment duty, no purchase order. | L-06 |
| BR-INV-43 | The Owner and OPERATIONS_ADMIN see product, SKU, current stock, minimum stock and state, with filters, server paging and the last refresh time, and can go to stock history and receiving. CASHIER sees only the stock needed for checkout. | L-06 |

Example (L-06): with minimum stock 5, on-hand 6 is NORMAL, 5 and 1 are LOW, 0 is OUT; a receipt that brings it back to 6 makes it NORMAL.

## 11. History, reconciliation and incidents

| ID | Rule | Source |
| --- | --- | --- |
| BR-INV-44 | Stock and history are read on the server with paging. OPERATIONS_ADMIN reads operational history without private cost, except the purchase cost on receiving documents (BR-INV-14, BR-INV-15). The Owner may open the cost evidence. | L-06, D-033, D-047 |
| BR-INV-45 | Once a day a read-only reconciliation compares the movements with the balances and cost projections. A mismatch blocks postings of the affected product and raises an incident for the Owner. A valid UNKNOWN cost is not a mismatch. | L-06, L-P3 |
| BR-INV-46 | An incident is handled by stopping the affected postings, recovering the durable original result or verifying the facts (or restoring), reconciling, and reopening only with authorization. Repair uses only a controlled replay or rebuild from immutable facts, with audit; never a direct balance edit or an overwrite of history. The runbook stays in L-09 until RF-6. | L-06, L-09 |

## 12. Outside V1

Warehouses, locations, transfers and goods in transit; reservations; advanced stock-taking; serial numbers, pack conversion, batches, expiry dates and consignment; automatic purchasing and supplier orders; stock notifications; import of stock or cost (L-06 §Batas Later, L-PRD §Batas Later, D-037).

## 13. Open items

| Item | Subject |
| --- | --- |
| Gate G2 | Real units, precisions, opening volume, barcodes and availability of cost evidence |

T-16 was resolved in RF-3B by D-060, D-061 and ADR-008 (COSTING section 10). T-17 a is decided by D-069. T-17 b is decided by D-087 (BR-INV-26, BR-INV-27, BR-INV-34, BR-INV-47, BR-INV-48); D-071 and D-072 are written into BR-INV-24 in RF-5.

Stock records are business records: they are kept at least 10 years and V1 has no function that deletes them (D-044, DOMAIN_MODEL section 12).
