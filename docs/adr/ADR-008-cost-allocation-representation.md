# ADR-008 — Issue-cost allocation representation

```text
ID:               ADR-008
TITLE:            Issue-cost allocation representation
STATUS:           ACCEPTED
DATE:             5 October 2026 (Asia/Jakarta), written in RF-3B (D-059)
DECISION SOURCE:  D-060 (Owner, question 33: an immutable at-commit allocation fact plus a replaceable corrected projection);
                  D-061 (Owner, question 34: a saleable return stays linked to the original issue allocation, and issue
                  and return projections are recomputed together); D-034 and D-035 (Owner: cost correction, replay and
                  returns); the rounding rule of T-17 e (Planner: a technical reading accepted for RF-3B planning in the
                  Owner's review of the RF-3B package and recorded with D-059)
```

## Context

D-034 keeps original facts and correction history and publishes a corrected current projection after a bounded, deterministic moving-weighted-average replay. Before RF-3B it was open (OPEN_ITEMS T-16) whether the issue-cost allocation captured when a Sale commits — which may be UNKNOWN — is an immutable original fact or a replaceable derived value, the same for MANUAL_ISSUE and negative ADJUSTMENT; which value a saleable return uses after a later correction, and how "restores cost exactly once" is enforced; and how all of this is stored. The Owner decided the first two parts: question 33, option A (D-060), and question 34, option A (D-061). This ADR records the representation that follows from them (T-16 c), the rounding rule of T-17 e and the replay that maintains the representation. It adds no business meaning: the meaning of profit, of cost of goods sold and of the cost a return restores stays that of D-034 and D-035 ([COSTING](../02-domain/COSTING.md)).

The decided invariants that every answer had to keep are listed in COSTING section 10; section "Invariants" below proves each one.

Terms used here (internal; not shown to shop users): an **allocation fact** is the immutable record written when an issue or a saleable return commits; an **allocation projection** is the replaceable current value of that allocation after the latest published replay. "Corrected projection" in [GLOSSARY](../02-domain/GLOSSARY.md) is the set of all current projections and the current valuation.

## Decision

### 1. Two records per allocation

Every allocation has exactly one allocation fact and exactly one allocation projection, kept in separate tables ([SCHEMA](../04-architecture/SCHEMA.md) section 5.8):

| Allocation | Fact (immutable) | Projection (replaceable) |
| --- | --- | --- |
| Issue allocation — one per SALE_ISSUE, MANUAL_ISSUE or negative ADJUSTMENT movement | `issue_cost_allocations` | `issue_cost_allocation_projections` |
| Return allocation — one per saleable return line, that is per RETURN_RECEIPT movement | `return_cost_allocations` | `return_cost_allocation_projections` |

The fact is written in the transaction of the movement it belongs to and is never updated or deleted (D-034; BR-COST-16, BR-COST-21). The projection is inserted in the same transaction with the same status and amount as the fact, and is afterwards changed only by a replay publication (section 4). The runtime database role may insert facts but never update or delete them; it may update projections (SCHEMA section 10).

### 2. Issue allocation

- **Fact.** The issued quantity (equal to the negated movement quantity and, for SALE_ISSUE, to the SaleLine quantity), a status KNOWN or UNKNOWN, the amount with six fractional digits when KNOWN and no amount when UNKNOWN (BR-DOM-08: UNKNOWN is never zero), the product sequence of the movement, and the product's valuation publication number on which the computation was based — the cost-evidence state the allocation depended on.
- **Computation at commit.** From the product's current valuation, which is the state immediately before the issue (section 4.2): the issue that takes the logical quantity to zero takes the whole remaining value (BR-COST-11); any other issue of q units out of Q takes V × q / Q, rounded once (section 5); while the valuation is UNKNOWN the allocation is UNKNOWN (BR-COST-13). The same rule applies to SALE_ISSUE, MANUAL_ISSUE and negative ADJUSTMENT (BR-COST-10); only SALE_ISSUE allocations enter cost of goods sold.
- **Projection.** Status KNOWN, UNKNOWN or CANCELLED. CANCELLED applies only to a MANUAL_ISSUE whose movement was logically cancelled by a REVERSAL (BR-COST-32; SALE_ISSUE and ADJUSTMENT are never reversed, BR-INV-36); a cancelled issue reduces no value.

### 3. Return allocation and the "exactly once" rule

- **Link.** Each saleable return line has one return allocation fact that references, by foreign key, the issue allocation of the SALE_ISSUE of the same SaleLine (D-061). The schema enforces that the linked issue allocation belongs to the same SaleLine and product and is a SALE_ISSUE allocation, that every movement moves exactly its line's product and quantity, and that a not-saleable return line has no return allocation (SCHEMA sections 5.7 and 5.8; BR-COST-19).
- **Fact.** The returned quantity, a status KNOWN or UNKNOWN and the amount restored when the return committed, computed from the linked issue projection in force at that moment by the cumulative rule of section 5 (D-061: "the value recorded when the return committed stays as an immutable fact").
- **Projection.** Recomputed from the link at every replay that touches the linked issue allocation, in the same publication (section 4).
- **Exactly once** is enforced by five parts together:
  1. each return line links to exactly one issue allocation, and each return line has at most one return allocation (unique key);
  2. the cumulative returned quantity of a SaleLine never exceeds its issued quantity: a running total per SaleLine with a check constraint, written under the original Sale lock (SCHEMA section 5.9; TECHNICAL_RULES RACE-10; BR-RFD-03);
  3. the cumulative rule of section 5 makes the total restored for an issue allocation equal to the issue projection's share of the returned quantity, never more, and exactly the whole issue projection when the whole quantity has been returned;
  4. a replay recomputes return projections from their links; it never creates a return, a movement or a second recovery (BR-COST-18);
  5. a refund without a return creates no return allocation and restores no cost (BR-COST-17).
- **UNKNOWN.** If the linked issue projection is UNKNOWN, the return allocation is UNKNOWN and the product's valuation becomes UNKNOWN (BR-COST-13); when the missing cost is completed later, the replay completes the issue projection and the linked return projection together (BR-COST-22; D-061).

### 4. Valuation and replay

#### 4.1 The logical ledger

The valuation of a product is computed over its **logical ledger**: its stock movements in product-sequence order, leaving out every logically cancelled pair — an OPENING, RECEIPT or MANUAL_ISSUE that has a REVERSAL, together with that REVERSAL (BR-COST-32). The physical ledger keeps both movements; only the valuation skips them.

The state after any prefix of the logical ledger is a logical quantity Q, a value V and a status S (KNOWN or UNKNOWN). The empty prefix is Q = 0, V = 0, S = KNOWN. Each movement changes the state:

| Movement | Cost of the movement | Effect |
| --- | --- | --- |
| OPENING, RECEIPT, positive ADJUSTMENT | The effective amount of its acquisition cost evidence: the original amount, or the new amount of its latest cost correction; or UNKNOWN | Increase: Q + q; if S and the cost are KNOWN, V + cost; otherwise S becomes UNKNOWN (BR-COST-09, BR-COST-13) |
| RETURN_RECEIPT | Its return projection (section 5), or UNKNOWN | Increase, as above (BR-COST-17) |
| SALE_ISSUE, MANUAL_ISSUE, negative ADJUSTMENT | Its issue projection (section 2), computed at this point | Q − q; if KNOWN, V − allocation (BR-COST-10, BR-COST-11) |

Whenever Q becomes zero, the next state starts a new pool: V = 0 and S = KNOWN. A later increase whose cost is KNOWN therefore starts a known pool, and earlier incomplete periods stay incomplete (BR-COST-15).

Because the pools are those of the logical ledger, a reversal can change where they begin and end, and with them the completeness of later issues, without any cost correction (BR-COST-32 with BR-COST-13 and BR-COST-15):

- reversing an increase can create a zero point: issues after it that depended on an UNKNOWN cost only through the cancelled quantity become KNOWN in a new pool. Example: RECEIPT A of 10 units with UNKNOWN cost; RECEIPT B of 5 units costing 50.000 in total; Sale of 10 (UNKNOWN); RECEIPT C of 10 units costing 100.000; Sale of 5 (UNKNOWN). Reversing B makes the Sale of 10 take Q to zero; the Sale of 5 then lies in C's known pool and its projection becomes KNOWN 50.000. The Sale of 10 stays UNKNOWN;
- reversing a manual issue can remove a zero point and merge two pools: issues that were KNOWN can become UNKNOWN. Example: RECEIPT A of 10 units with UNKNOWN cost; MANUAL_ISSUE of 10 (the pool resets); RECEIPT B of 10 units costing 100.000; Sale of 4 (KNOWN 40.000). Reversing the MANUAL_ISSUE merges A and B into one pool, and the Sale of 4 becomes UNKNOWN.

Both follow from the decided rules and add none: the reports reflect the logical history after the reversal, as D-034 requires of corrected reporting. BR-COST-15's "earlier periods" are those that, in the logical ledger, still depend on the missing evidence.

A history is **valid** only if no issue ever exceeds the logical quantity at its point, every return's linked issue allocation precedes it and belongs to the same product, and the cumulative returned quantity per issue allocation never exceeds its issued quantity (BR-COST-24, BR-INV-37).

#### 4.2 Postings

An ordinary posting appends one movement at the end of the ledger. It computes the movement's allocation, if any, from the product's current valuation, writes the fact and its equal projection, and updates the current valuation, all in the posting's transaction (INVENTORY BR-INV-08). No replay is needed.

#### 4.3 Replay

A replay recomputes one product's valuation from a **start position** to the end of its ledger and publishes the result atomically (D-034; BR-COST-23 to BR-COST-26):

- **Start position.** For a cost correction or completion: the movement whose acquisition cost evidence was corrected. For a reversal: the original movement being cancelled.
- **State before the start position.** The state produced by the logical ledger up to the start position. It is computed from the current projections and effective evidence amounts of the logical movements since the last point before the start position at which Q became zero (or since the product's first movement): Q is the sum of logical quantities; V is the sum of increase costs minus the sum of issue projections since that point; S is UNKNOWN if any of those costs is UNKNOWN. This is exact because nothing before the start position changes: every earlier correction was published before this one began, and every replay runs under the same product guard. Finding that zero point needs a running sum of the logical quantity over the product's history back to it, with cancelled pairs left out, which for a product that never sells out is its whole history; the cost sums since that point follow. This read is outside the movement cap, and its cost is measured with the replay at PERF01.
- **Recomputation.** Every logical movement from the start position to the end is applied in order, by the rules of section 4.1, recomputing every issue projection and every return projection reached, including the return projections linked to recomputed issue allocations. The history is validated as it is recomputed.
- **Publication.** Only projections whose status or amount changed are rewritten; the product's current valuation is replaced; the product's publication number increases by one and is written on every projection rewritten. Facts are never touched. Everything is written in the correction's or reversal's transaction, so all new results appear at once or nothing changes (BR-COST-25).
- **Bounds.** The replay is refused with no change (`REPLAY_REJECTED`) if the history is invalid, if a recomputed value would fall outside the ranges of SCHEMA section 3.2, if the number of logical movements from the start position to the end exceeds the movement cap, or if the transaction budget is exceeded (TECHNICAL_RULES TR-TIME-01; BR-COST-27, BR-COST-28). Values are range-checked before they are written (ADR-006), so a database range error never takes the place of this refusal. A product with an active posting block accepts no correction or reversal until the Owner reopens it after the incident is handled (SCHEMA guard G-11; BR-INV-46; D-063). The handling of reporting after a refused cost correction is SCHEMA section 5.8 (`cost_incomplete_marks`, T-17 i); it is not part of this ADR.

### 5. Rounding (T-17 e)

- Every cost value computed by multiplication and division is computed exactly and rounded **once**, at the end of that computation, **half-up** (ties away from zero) to six fractional digits (ADR-006; BR-NUM-03). In the application this is Brick Math: the exact product of the factors, then one division to scale 6 with `RoundingMode::HALF_UP`. Cost values are never negative, so half-up and ties-away-from-zero coincide.
- Cost values that involve multiplication, division or rounding are computed only by that application path, never in SQL: PostgreSQL chooses the scale of a `numeric` quotient itself, so `round(a * b / c, 6)` in SQL can round twice and differ from the single rounding in the last digit. Exact sums and differences of stored values — report totals, the prefix state of section 4.3 — may be computed in SQL, because `numeric` addition and subtraction are exact. The daily reconciliation recomputes with the same application path (SCHEMA guard G-03).
- **Issue:** allocation = round6(V × q / Q); the issue that takes Q to zero takes V exactly (BR-COST-11).
- **Returns — cumulative rule.** For an issue projection of amount A over issued quantity q, let R(k) be the sum of `returned_quantity` of the return allocations linked to it — saleable returns only — in ledger order, up to and including the k-th (R(0) = 0). The SaleLine's running total of returned quantity also counts not-saleable returns and is never used here. The k-th return restores round6(A × R(k) / q) − round6(A × R(k−1) / q). The sum restored after k returns is round6(A × R(k) / q), so several partial returns of one line leave no residue, the total restored never exceeds A, and when R(k) = q the total restored is exactly A.
- **Display.** Aggregation happens before rounding; cost figures are rounded to whole rupiah, half-up, only for report display, at the end (BR-NUM-03). For a figure that can be negative, such as a gross loss, half-up means ties away from zero, so −10.5 is shown as −11. A margin is a ratio; its display precision is set with the report design (RF-4, RF-5), with the same tie rule. Money amounts (prices, totals, payments, refunds) stay whole rupiah under BR-NUM-01 and are never derived from cost values.

### 6. What reads what

| Reader | Reads |
| --- | --- |
| Owner finance report and daily summary (ARC-24; BR-COST-30, BR-COST-35) | Issue projections of SALE_ISSUE movements by each Sale's business date, return projections by each return's posting date, current valuations. A report is complete only if none of those projections and no valuation it uses is UNKNOWN and no active "reporting incomplete" mark covers it (BR-COST-38; SCHEMA section 5.8). Every cost total is computed in the same statement as the count of UNKNOWN rows it covers, and is never shown as a complete total when that count is above zero: an SQL `SUM` ignores the null amount of an UNKNOWN row and would otherwise show it as zero (BR-DOM-08). It states its as-of time, which is its database snapshot, the time of the latest cost correction published before it, and how many products carry an active mark |
| Owner audit and drill-down | Facts and projections side by side: the cost known at the time of sale and the cost after correction for every line (D-060) |
| OPERATIONS_ADMIN | Never allocations, valuations or projections; only the purchase cost on receiving documents (D-033, D-047) |
| Receipts, cash session reports, labels, search results | No cost at all (BR-COST-08, BR-COST-31) |

### 7. Concurrency

The replay holds the product guard (TECHNICAL_RULES section 7, step 6) for its whole duration and writes projections and the valuation at step 7. Any other posting of the product — including a return that restores cost, which also takes the product guard of the returned product — waits within the lock timeout or is refused `SERVICE_BUSY` (BR-COST-26; RACE-11). Because MWA is per product and a return links only to an issue of the same product — which the schema enforces through the movement's keys to its line's product (SCHEMA section 5.7) — a replay never needs another product's guard.

## Invariants

The decided invariants of COSTING section 10, one by one:

| No. | Invariant | How the representation keeps it |
| --- | --- | --- |
| 1 | Original Sale and StockMovement facts are immutable | Replay writes only projection tables and the current valuation; movements, Sales and allocation facts are insert-only, and the runtime role has no update or delete privilege on them (SCHEMA section 10) |
| 2 | Cost correction evidence is append-only | Corrections are insert-only rows; the effective evidence amount is read from them; nothing is rewritten |
| 3 | The replay is bounded and deterministic | Product-sequence order on the logical ledger; one rounding rule; a movement cap and a transaction budget (section 4.3) |
| 4 | Publication is atomic | All projections, the valuation and the publication number are written in one transaction under the product guard |
| 5 | UNKNOWN is never zero | Status columns separate from amounts, with check constraints that forbid an amount for UNKNOWN and require one for KNOWN, in facts and projections alike |
| 6 | A saleable return restores the cost tied to the original sale issue exactly once; a later correction updates it consistently without a new return, movement or double recovery | The link, the per-line returned-quantity bound, the cumulative rule and recomputation from the link (section 3) |
| 7 | Corrected current reporting reflects the latest valid cost evidence | Reports read projections and current valuations, which every publication brings to the latest effective evidence (section 6) |
| 8 | Receipts and cash session reports never change | They hold no cost and are insert-only; no replay reads or writes them |
| 9 | V1 need not keep every earlier derived projection, and this never permits erasing an original fact or correction history | Projections are replaced, not versioned; facts and corrections are kept (D-060 declined option C) |

## Legacy statements listed in COSTING section 10

While T-16 was open these statements carried no authority on its parts (a) and (b). Their disposition now:

| Source | Statement | Disposition |
| --- | --- | --- |
| L-15 §Revision evidence tanpa menulis ulang sejarah | "Source dan alokasi original tetap dipertahankan" | **Adopted** — the source and the original allocation are kept as immutable facts (D-060) |
| same | "downstream allocation versions"; "dependent cost revisions" | **Rejected** — only the current projection is kept, not a version per replay (D-060 declined option C; BR-COST-29) |
| L-15 §Constraint replay dependent facts | "matched issue/return allocation revision references"; "Return mengacu pada cost revision source yang konsisten" | **Adopted as a link** — a return references its issue allocation, and both projections are recomputed together from consistent evidence (D-061). **Rejected as revision references** — no revisions are kept |
| L-15 §Pasangan reversal dihitung sekali | "original allocation75 tetap tersimpan" | **Adopted** — in the COSTING section 8 fixture the issue's fact keeps 75 and its projection becomes 50 after the reversal |
| L-02 entity table (InventoryValuation / IssueCost) | "alokasi issue immutable/versioned" | **Adopted** as to immutable (the fact); **rejected** as to versioned |
| L-P2 domain table (Cost) | "allocation/revision immutable" | **Adopted** as to the allocation (the fact); **rejected** as to revisions |
| L-P3 §Koreksi stok, cost evidence/replay, refund dan return | "valuation/allocation versions"; "Original source/allocation versions dipertahankan"; "mempertahankan alokasi cost yang cocok dengan fakta sumber dan revision yang konsisten" | **Adopted** as to keeping the original source and allocation, and as to the return's allocation matching consistent source facts (recomputed together); **rejected** as to keeping versions |
| L-P6 §Matriks race dan disiplin lock | "Source/allocation stabil dan versioned" | **Adopted** as to stable (immutable facts; the product guard during replay, RACE-11); **rejected** as to versioned |
| L-06 §Batas chronology dan sensitive recovery | "Source/allocation/return cost revision links"; "original sources/allocations tetap" | **Adopted** as to the return-to-issue link and keeping originals; **rejected** as to revision links |
| L-15 §Sale, refund dan return; L-P3; L-06 §Command fisik and §Koreksi, reversal dan return; L-17 §Refund/return minimal oleh Pemilik; L-08 suite FIN03 | A return restores the "original matched issue cost", "matched historical cost" or "original issue cost" once; "historical cost recovery"; "original cost UNKNOWN membuat return valuation tetap incomplete", "UNKNOWN tetap incomplete" | **Adopted** — the cost tied to the original sale issue, through the link, restored once; its value follows the latest cost evidence (D-061); a return whose linked issue is UNKNOWN stays incomplete until the cost is completed (BR-COST-22) |
| Legacy Build Units BU-INV-003 and BU-SALE-002 and their registry (LEGACY — INPUT ONLY) | The reversal fixture ("allocation75 original dipertahankan"); "Revision cost/allocation"; "revision cost allocation" of a return | **Adopted** as to the fixture (as above); **rejected** as to revisions. The Build Units stay input only for RF-7 |

The status of each parent legacy document is unchanged by this table; it is set in [SOURCE_OF_TRUTH](../00-governance/SOURCE_OF_TRUTH.md).

## Alternatives considered

| Alternative | Why not chosen |
| --- | --- |
| The allocation as a derived value only, overwritten by each replay (question 33, option B) | Declined by the Owner (D-060): the cost known at the time of sale would be lost per line, which is close to rewriting history |
| Every replay's projection kept as a version (question 33, option C) | Declined by the Owner (D-060): not required by D-034 (BR-COST-29); the most storage and complexity |
| A return frozen at its value at return time (question 34, option B) | Declined by the Owner (D-061): the valuation would become inconsistent after a correction, and D-034 and D-035 would need revalidation |
| A return valued at the moving average at return time, detached from the issue (question 34, option C) | Declined by the Owner (D-061): changes the meaning of cost of goods sold |
| One table per allocation with immutable and mutable columns separated by column privileges | Not chosen: separate tables give a table-level privilege boundary that is simpler to review and test, and match the Owner's "stored separately" (D-060) |
| A stored valuation snapshot after every movement, used as the replay's starting state | Not chosen: a second projection that must itself be kept consistent; the state before the start position is computed from the projections that already exist (section 4.3) |
| Replaying every correction from the product's first movement | Not chosen: D-034 replays from the affected cost source, and only the movements from that source onward are recomputed and bounded by the cap. The state before the source is still read over the history since the last zero point (section 4.3) |
| Rounding each partial return separately to six digits | Not chosen: several partial returns of one line could leave a residue or restore more than the issue projection; the cumulative rule cannot |

## Consequences

- Two rows per allocation: slightly more storage, accepted with D-060.
- The audit can show, per line, the cost at commit and the cost after correction (D-060).
- The schema carries composite keys that tie a return allocation to the issue allocation of the same SaleLine, and running totals that bound returned quantities (SCHEMA sections 5.8 and 5.9).
- The costing Task must prove by tests on real PostgreSQL: the L-15 examples (COSTING section 4); the reversal fixture (COSTING section 8); the D-061 worked example (10 units issued at 100, 2 returned, cost corrected to 120: return projection 240, net cost of goods sold 960); several partial returns of one line leaving no residue; UNKNOWN completed later through issue and linked return; a replay racing a posting and a return (RACE-11); failure injection during publication leaving every projection unchanged; a refused replay leaving every projection unchanged; the two reversal examples of section 4.1; a partial return after a not-saleable return of the same line; a value whose single rounding differs from SQL's double rounding (for example 84764889.521997 × 28655.202 / 79662.459, which rounds to 30490585.681776); the display of a negative figure with a tie; and, after a replay refused for its time budget, the mark's transaction on a fresh connection if the budget ended the session.

## Framework conformance

```text
ADR REQUIRED:      YES (REFOUNDATION_PLAN gives the representation of issue-cost allocations, T-16 c, its own ADR)
DEVIATION:         NONE
OWNER APPROVAL:    GIVEN — D-060 and D-061 decide parts (a) and (b) of T-16; the rounding rule of T-17 e is a technical
                   reading accepted for RF-3B planning (D-059) that changes no user-visible figure beyond BR-NUM-03 display
REASON:            exact, auditable cost of goods sold that follows the latest cost evidence without rewriting history
RISK:              a defect in the replay could publish wrong projections; mitigated by deterministic rules, atomic
                   publication under the product guard, the daily reconciliation of movements with balances and cost
                   projections (BR-INV-45) and the tests listed above
MIGRATION IMPACT:  none — no schema exists; the tables are new (SCHEMA section 5.8)
ROLLBACK IMPACT:   none before implementation; afterwards, facts are never rewritten, so a defective projection is repaired
                   by a controlled replay from facts (BR-INV-46)
```

## Verified sources

| Source | Checked | Fact used |
| --- | --- | --- |
| https://www.postgresql.org/docs/current/datatype-numeric.html | 5 October 2026 (documentation of PostgreSQL 18, observed, not a pin) | `numeric` is exact; when rounding, `numeric` rounds ties away from zero; a value with more fractional digits than the column's scale is rounded, so the application refuses excess scale first (ADR-006) |
| https://raw.githubusercontent.com/brick/math/master/src/RoundingMode.php | 5 October 2026 | `HALF_UP` behaves as `UP` (away from zero) when the discarded fraction is at least one half, otherwise as `DOWN` |

## Related

- [COSTING](../02-domain/COSTING.md) sections 4 to 8 and 10; [INVENTORY](../02-domain/INVENTORY.md) BR-INV-08, BR-INV-37, BR-INV-38; [SALES_CASH](../02-domain/SALES_CASH.md) BR-RFD-03, BR-RFD-11.
- [SCHEMA](../04-architecture/SCHEMA.md) sections 5.7 to 5.9 and 9; [TECHNICAL_RULES](../06-api-performance/TECHNICAL_RULES.md) RACE-10, RACE-11, TR-TIME-01; [ADR-006](ADR-006-exact-numbers.md).
- Resolves OPEN_ITEMS T-16 and, for rounding, T-17 e.
