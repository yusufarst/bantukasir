# Costing and gross profit

| | |
| --- | --- |
| Status | CURRENT — written in RF-2 under D-041, 4 October 2026 (Asia/Jakarta) |
| Phase | P2 — domain rules |
| Replaces | `docs/02-domain/legacy/15-FINANCE-PROFITABILITY.md` |
| Map and terms | [DOMAIN_MODEL](DOMAIN_MODEL.md), [GLOSSARY](GLOSSARY.md) |

Acquisition cost evidence, who may see and change cost, moving weighted average valuation, issue-cost allocation, the cost effect of returns and reversals, cost correction and replay, and the Owner's cost of goods sold and gross profit reporting. Physical stock rules are in [INVENTORY](INVENTORY.md); sales, refunds and returns are in [SALES_CASH](SALES_CASH.md). Section 10 records how the design issue T-16 was resolved.

The conventions of DOMAIN_MODEL section 1 apply: rules are `BR-COST-nn`, every rule names its source, and `OPEN` items are in [OPEN_ITEMS](../00-governance/OPEN_ITEMS.md). Source keys are listed in DOMAIN_MODEL section 2.

## 1. Principles

| ID | Rule | Source |
| --- | --- | --- |
| BR-COST-01 | Cost of goods is a perpetual moving weighted average (MWA) per product, for interchangeable goods in the one stock pool. It is a management figure for the Owner. It is not tax accounting, and gross profit is never presented as net profit. The latest purchase price is never used as cost. | L-15, D-006, D-039 |

## 2. Acquisition cost evidence

| ID | Rule | Source |
| --- | --- | --- |
| BR-COST-02 | Stock enters with acquisition cost evidence through OPENING, RECEIPT or a positive ADJUSTMENT. The evidence holds the total landed acquisition amount, the quantity, a reference, the actor and a status: KNOWN or UNKNOWN. | L-15 |
| BR-COST-03 | UNKNOWN ("Biaya perlu dilengkapi pemilik") is a status of its own. It is different from an evidenced zero and is never treated as zero in any calculation, report or display. | D-033, D-034, L-15 |
| BR-COST-04 | An explicit zero cost needs evidence or a reason confirmed by the Owner. | L-15 |
| BR-COST-05 | The Owner enters cost evidence for every source. OPERATIONS_ADMIN enters and corrects the purchase cost on a receiving document until it is confirmed (INVENTORY BR-INV-14). Every entry, confirmation and correction is audited against the user's identity. | D-033, L-15 |

## 3. Who sees cost

| ID | Rule | Source |
| --- | --- | --- |
| BR-COST-06 | The Owner sees all cost data: evidence, corrections, valuation, allocations, cost of goods sold, margin, gross profit and inventory value. | L-15, L-04, D-033 |
| BR-COST-07 | OPERATIONS_ADMIN sees the purchase cost on the receiving documents the role can access — after an Owner cost correction or completion, the current cost with the Owner's mark and the cost at confirmation (INVENTORY BR-INV-15) — and nothing else about cost: never the reason for a correction, moving weighted average cost, cost of goods sold, margin, profit, aggregate inventory value, cost reports or profitability reports. | D-033, D-047 |
| BR-COST-08 | CASHIER has no access to purchase cost. Point-of-sale screens, receipts, cash session reports, labels and search results never contain cost. On a combined account, cost is available only through the operational functions entitled to it. | D-033, L-17, L-12 |

## 4. Moving weighted average arithmetic

| ID | Rule | Source |
| --- | --- | --- |
| BR-COST-09 | For a product with known quantity Q and inventory value V, an increase of q units with total cost c gives Q' = Q + q, V' = V + c, average = V' / Q'. | L-15 |
| BR-COST-10 | An issue of q units is allocated q × (V / Q) at the average before the issue, and V is reduced by that amount. This applies to SALE_ISSUE, MANUAL_ISSUE and negative ADJUSTMENT alike; only SALE_ISSUE allocations enter cost of goods sold. | L-15 |
| BR-COST-11 | The issue that takes the product's stock to zero takes the whole remaining value, so no rounding residue is left. | L-15 |
| BR-COST-12 | Cost arithmetic uses exact decimals with six fractional digits. A computed cost value is rounded half-up to six fractional digits once, at the end of its computation; under the cumulative rule for partial returns each cumulative share is rounded once (ADR-008 section 5). Figures are aggregated before rounding; rounding to rupiah happens only for report display, at the end (DOMAIN_MODEL BR-NUM-03; ADR-008 section 5). No binary floating point (BR-NUM-04). | L-15, L-P6; ADR-008 |

**Example (L-15).** 10 units with total cost Rp100.000 and then 10 units with total cost Rp140.000 give 20 units worth Rp240.000, average Rp12.000. Selling 3 units at Rp20.000 gives sales Rp60.000, cost of goods sold Rp36.000 and gross profit Rp24.000, leaving 17 units worth Rp204.000. A manual issue of 2 units then reduces inventory value by Rp24.000 and adds nothing to cost of goods sold.

## 5. UNKNOWN cost and cost pools

| ID | Rule | Source |
| --- | --- | --- |
| BR-COST-13 | Stock that enters with UNKNOWN cost makes the product's valuation unknown, and the allocations of later issues that depend on it are UNKNOWN; the cost of goods sold they feed is incomplete. | L-15, D-034 |
| BR-COST-14 | Allocations made while the valuation was known stay valid when stock with UNKNOWN cost enters later: that later entry does not make them unknown or incomplete. A correction of an earlier cost source still includes them in the replay (BR-COST-23, BR-COST-25), which rewrites their projections and never the allocations recorded at commit (D-060; ADR-008). | L-15, D-034, D-060 |
| BR-COST-15 | When the product's stock reaches zero, a later increase whose whole cost is KNOWN can start a new known pool. The new pool does not complete earlier periods with missing evidence: they stay incomplete until the missing cost is completed by an Owner cost correction (BR-COST-22). | L-15, D-034 |

## 6. Sales, refunds and returns

| ID | Rule | Source |
| --- | --- | --- |
| BR-COST-16 | A completed Sale records the issue-cost allocation of each line, or UNKNOWN, in the same transaction as the Sale. That recorded allocation is an immutable original fact; a later replay restates only its separately stored projection, which Owner reports use. The same holds for the allocations of MANUAL_ISSUE and negative ADJUSTMENT (ADR-008). | D-006, D-040, L-17, D-060 |
| BR-COST-17 | A refund without returned goods does not reverse cost of goods sold and adds no stock. A saleable return restores the cost tied to the original sale issue, for the returned quantity, once only, and reverses cost of goods sold by the same amount. While that cost is UNKNOWN, the return's valuation is incomplete. The return is linked to the original issue allocation: the cost it restored when it committed stays as a fact, and a later correction recomputes the issue and the linked return projections together, so the cost restored follows the latest evidence, is restored exactly once and keeps BR-COST-18 (ADR-008). The refund-money bound and the return-quantity bound are kept separate so that cost is never restored twice. | D-034, D-035, L-15, D-061 |
| BR-COST-18 | If the source cost is corrected later, the replay updates the related valuation consistently, without creating a new return, a new movement or a double cost recovery. | D-034 |
| BR-COST-19 | Only a saleable return restores cost. A damaged or non-saleable return adds no saleable stock and restores no cost. | D-034, D-035, L-15 |

**Valuation effect of a saleable return (arithmetic that follows from BR-COST-09, BR-COST-13 and BR-COST-17).** A saleable return of r units adds r to the product's quantity and adds the restored cost to its inventory value, as any increase does under BR-COST-09; net cost of goods sold falls by the same amount (BR-COST-35). The restored cost is the cost tied to the original sale issue for the r units returned: for a partial return of a line, the returned quantity's share of that cost. Several partial returns of one line leave no residue: each restores the difference between the line's cumulative returned share after it and before it, rounded half-up to six digits, so the whole issue cost is restored exactly when the whole quantity has been returned (ADR-008 section 5; T-17 e). If the restored cost is UNKNOWN, the product's valuation becomes unknown as under BR-COST-13.

## 7. Cost correction and replay

| ID | Rule | Source |
| --- | --- | --- |
| BR-COST-20 | After posting, only the Owner corrects a cost, with recent authentication. The correction is a new, append-only, audited fact that records at least the cost source corrected, the old value, the new value, the actor, the time and the reason. | D-034, D-008, BR-USR-07 |
| BR-COST-21 | The original receiving and other source facts, the original cost evidence, correction events, Sale facts, StockMovement facts, refunds and returns, actors, timestamps, reasons and audit history are immutable and retained. | D-034 |
| BR-COST-22 | Completing an UNKNOWN cost later is a cost correction under the same rules. | D-034 |
| BR-COST-23 | A correction runs a bounded, deterministic moving-weighted-average replay from the affected cost source to the current state of that product. The latest purchase price, or any other shortcut, never replaces the replay. | D-034, L-15 |
| BR-COST-24 | Before publishing, the replay validates the whole affected history: the signed physical prefix at every point, the boundaries between known and unknown pools, the links of issue and return allocations, and reversal pairs (section 8). | L-15 |
| BR-COST-25 | The corrected current valuation, cost of goods sold and profitability projection are published atomically: all new results appear at once, or nothing changes. | D-034 |
| BR-COST-26 | While a replay for a product runs, other postings of that product wait or are refused safely. | L-15 |
| BR-COST-27 | If the replay cannot be done safely — an unresolved conflict or concurrency issue, a timeout or the set capacity limit — the correction is rejected with no partial change, and the affected reporting stays marked incomplete until a reviewed recovery procedure exists. The mark is written after the rollback, with an audit event, in a transaction of its own, once per refused correction; it covers the Owner's cost figures of that product for every period that ends after the affected cost source; it is cleared only by a replay that later succeeds for the same cost source — a successful correction of that source, which publishes the latest evidence for it, or a successful reversal of its movement, after which it no longer counts — or by the reviewed recovery procedure, never by editing. A concurrency issue is handled before the replay starts: every writer of the product's cost figures waits for the same product guard, so a command that could not start its replay because the product was busy is answered as not committed, to be repeated with the same identity, and writes no mark (SCHEMA section 5.8; T-17 i). | D-034, L-15, D-063 |
| BR-COST-28 | Replay capacity is measured on the intended server. The reference of 10,000 movements per product is a starting point, not a measurement or a guarantee. V1 has no general asynchronous valuation platform; the cap and the time budget are in TECHNICAL_RULES section 9. | L-15, L-08 (PERF01), TECHNICAL_RULES |
| BR-COST-29 | V1 need not keep every earlier derived valuation or report projection merely so that an old profit report can be shown exactly as it was before a correction. This never permits erasing an original business fact or any correction history. | D-034 |
| BR-COST-30 | Owner reports show the corrected figures based on the latest cost evidence, also for periods before the correction. The audit shows who changed which cost, from which value to which value, when and why. | D-034 |
| BR-COST-31 | Receipts and cash session reports never change after a correction; they contain no cost or profit. | D-034, L-17 |

## 8. Reversal in valuation

| ID | Rule | Source |
| --- | --- | --- |
| BR-COST-32 | The stock ledger always keeps both the original and its inverse REVERSAL. The valuation replay cancels the original logically and skips its paired inverse; it never removes the original and then applies the inverse again. | L-15 |
| BR-COST-33 | An old receipt is never reversed at the latest average cost. A reversal waits or is refused until its replay can be done safely (INVENTORY BR-INV-37). | L-15 |

**Test fixture (L-15).** OPENING 10 units, total cost 100; RECEIPT 10 units, total cost 200; an issue of 5 units. Physical quantity 15, value 225, issue cost 75. A full reversal of the RECEIPT gives physical quantity 5, value 50, and an issue cost of 50 in the corrected projection. The issue cost of 75 captured before the reversal stays as the allocation fact; its projection becomes 50 (D-060; ADR-008). Invalid history: RECEIPT 10, ISSUE 10, RECEIPT 20, current 20. The physical inverse of the first receipt would leave a current 10, which is not negative, but the logical cancellation leaves the issue of 10 without stock at its time; the whole reversal is refused before commit, with no movement and no partial publication.

**Completeness after a reversal.** Because the valuation follows the logical ledger, a reversal can also change which later issues are complete, without any cost correction: cancelling an increase can create a point where the logical quantity reaches zero, after which a known pool starts; cancelling a manual issue can remove such a point and merge a later known pool into an unknown one. ADR-008 section 4.1 gives an example of each. Both follow from BR-COST-13, BR-COST-15 and BR-COST-32.

## 9. Reporting

| ID | Rule | Source |
| --- | --- | --- |
| BR-COST-34 | The Owner's finance report shows: gross sales, refunds, net sales, number of completed Sales, applied payments by method (CASH and BANK_TRANSFER separately) and refund payouts separately, quantity sold and returned, cost of goods sold and gross profit. Margin is shown only when cost is complete and net sales are above zero. | L-15, D-036, D-077 |
| BR-COST-35 | Net sales = completed gross sales − refunds. Net cost of goods sold = sale allocations − cost of verified saleable returns. Gross profit = net sales − net cost of goods sold. | L-15 |
| BR-COST-36 | Refunds and returns appear on their server posting date with a reference to the original Sale. There is no backdating. | L-15, BR-NUM-05 |
| BR-COST-37 | Periods are Hari Ini (today), 7 Hari (today and the six dates before), Bulan Ini (from the first of this month until now), Bulan Lalu (the whole previous calendar month) and Rentang Kustom (chosen dates, inclusive), computed under BR-NUM-06. A custom range changes the report only when applied; cancelling keeps the previous period. The maximum query range is 366 days (TECHNICAL_RULES section 11). | L-15, L-P3, L-P6, TECHNICAL_RULES |
| BR-COST-38 | A report is read from one consistent snapshot and states its as-of time, time zone, completeness and the number of lines with unknown cost. Sales and payment totals are shown even when cost of goods sold, gross profit or margin are incomplete; those then show "Data biaya belum lengkap". Lines with unknown cost or a loss are never left out to make profit look complete. A failed query is shown as an error, never as zero. It identifies the cost-evidence state it reflects by its as-of time and the time of the latest cost correction published before it, and states how many products carry an active "reporting incomplete" mark (BR-COST-27; ADR-008 section 6). | L-15 |
| BR-COST-39 | Non-sale losses (manual issue, negative adjustment) and cash variance are separate operational facts; they are never cost of goods sold. Opening cash and variance are never sales or profit. | L-15, L-17 |
| BR-COST-40 | Stock attention (LOW, OUT) is current stock, not a figure that follows the report period. | L-15 |
| BR-COST-41 | Accounts receivable and payable, net profit, expenses, payroll and tax accounting are outside V1. | L-15, L-PRD §Batas Later, D-039 |

## 10. T-16 — resolved (ADR-008)

**Status: RESOLVED** ([OPEN_ITEMS](../00-governance/OPEN_ITEMS.md) T-16). RF-2 recorded the decided invariants and the open questions and chose no design (D-041). The Owner then decided both open parts, and RF-3B recorded the representation:

- **(a)** The issue-cost allocation captured when a Sale commits — which may be UNKNOWN — is an immutable original fact; the result of a correction replay is stored separately as a projection that each replay replaces, and Owner reports use the projection. The same holds for the allocations of MANUAL_ISSUE and negative ADJUSTMENT (D-060, question 33).
- **(b)** A saleable return references a link to the original issue allocation; a correction replay recomputes the issue and the linked return projections together; the value at return commit stays as a fact; cost is restored exactly once (D-061, question 34).
- **(c)** The representation, the replay, the enforcement of "exactly once" and the rounding of partial returns (T-17 e) are [ADR-008](../adr/ADR-008-cost-allocation-representation.md); the tables are [SCHEMA](../04-architecture/SCHEMA.md) section 5.8.

**Decided invariants.** They stay binding; ADR-008 proves each one:

1. The original Sale and StockMovement facts are immutable (D-034).
2. Cost correction evidence is append-only (D-034).
3. The moving-weighted-average replay is bounded and deterministic (D-034).
4. Publication of corrected results is atomic (D-034).
5. UNKNOWN is never zero (D-033, D-034).
6. A saleable return restores the cost tied to the original sale issue exactly once; a later correction of the source cost updates the related valuation consistently, without a new return, a new movement or a double cost recovery (D-034, D-035).
7. Corrected current reporting reflects the latest valid cost evidence (D-034).
8. Receipts and cash session reports never change (D-034).
9. V1 need not keep every earlier derived projection, and this never permits erasing an original fact or correction history (D-034).

**Conditions met.** The answers keep invariants 1 to 9 and keep an at-commit UNKNOWN distinguishable from zero. No requirement in [REQUIREMENTS](../01-product/REQUIREMENTS.md) changed. The meaning of profit and of the cost a return restores is unchanged: both answers lie within D-034 and D-035, and the Owner decided them explicitly before ADR-008 recorded their representation (plan decision P-8, D-059).

**Legacy statements.** While T-16 was open, the legacy statements that would have settled (a) or (b) carried no authority on that point. RF-2 listed them in this section; each has now received its disposition — adopted or rejected, with its reason — in ADR-008, section "Legacy statements listed in COSTING section 10". The rest of each legacy rule keeps the status SOURCE_OF_TRUTH gives it; the parts about bounded, deterministic replay, atomic publication, waiting or refusal while a product is busy, and rejection of unsafe chronology are carried by sections 7 and 8.

## 11. Open items

| Item | Subject |
| --- | --- |
| Gate G2 | Availability of cost evidence for the first store |
| Gate G1 | Tax position of each client; V1 calculates no tax (D-039) |

T-16 (section 10) and T-17 e and i (BR-COST-12, the valuation paragraph of section 6, BR-COST-27) were resolved in RF-3B: D-060, D-061, ADR-008 and SCHEMA section 5.8.
