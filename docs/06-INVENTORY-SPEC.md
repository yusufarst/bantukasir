# 06 — Inventory accounting contract

Canonical rules for stock arithmetic, posting, concurrency, correction, reservation, transfer and stock opname. UI/ORM balance manipulation MUST NOT replace this contract. [13](13-NOTIFICATIONS.md) owns stock-health transitions.

## Quantities and invariants

Only GOODS products may enter this contract; SERVICE has no movement, stock balance, serial position or health row. For goods product p and location l:

```text
onHand(p,l)    = SUM(committed signed ledger legs for p,l)
reserved(p,l)  = SUM(active reservation remaining for p,l) [Core = 0]
available(p,l) = onHand(p,l) - reserved(p,l)
physical(p)   = SUM(onHand across all company-stock locations)
eligible(p)   = SUM(available in active, issue-eligible STORAGE locations)
```

Opening is always a ledger movement with count/import reference and actor. UI receipt/issue quantities are positive; the server determines signs. Reject zero/negative input, excess precision, NaN/infinity and overflow. Use exact numeric(18,3), at most 15 integer digits, JSON decimal strings. Parse Indonesian input explicitly; no floating-point or ambiguous grouping separators.

| ID | Mandatory invariant |
| --- | --- |
| I01 | Ledger and posting audit are immutable; runtime cannot UPDATE/DELETE/TRUNCATE them |
| I02 | Committed balance projection equals ledger aggregation |
| I03 | onHand and available are nonnegative at every location; no owner override |
| I04 | One serial has at most one on-hand unit/location and cannot leave twice |
| I05 | One logical command produces at most one posting, including retries/lost responses |
| I06 | Header, legs, balances, serial positions, audit, health/episode/inbox/outbox and receipt commit or roll back together |
| I07 | Transfer preserves product quantity: sum of signed legs is zero |
| I08 | Actor, permission, time, reason/reference are traceable and server-validated |

## Movement types

| Type / UI label | Legs | Preconditions |
| --- | --- | --- |
| OPENING / Saldo Awal | +q at location | Owner; product/location has no prior ledger; unique batch/count reference. New or REGISTERED serials only. Later additions use receipt/adjustment |
| RECEIPT / Barang Masuk | +q | Authorized staff; source/sender and reason category required, document number optional. New/REGISTERED serials; ISSUED return requires RETURN reason and issue reference |
| ISSUE / Barang Keluar | −q | Recipient/destination or internal reference and reason; sufficient available; each serial IN_STOCK at source |
| TRANSFER / Transfer Stok | −q source, +q destination | Distinct active locations, same product/unit, balanced legs, sufficient unreserved source, one commit |
| ADJUSTMENT / Penyesuaian Stok | Verified signed difference | Owner in Core; count, reason/reference and expected balance version. Server derives delta; stale version requires recount/review |
| REVERSAL / Pembalikan | Inverse of every original leg | Owner; one original, full reversal only, current physical/state prerequisites satisfied |

Interactive commands support up to 200 logical lines; transfer produces at most 400 legs. Group quantity scans by product/location; each serial remains a separate line. Authorized opening-import worker has a bounded exception of 5,000 logical lines under [14](14-BULK-IMPORT.md). Interactive endpoints cannot override their limit. Both paths use one confirmation, one transaction and one receipt; no silent splitting.

Only POSTED movements exist in the ledger. Reversed display state is derived from reversal links. Draft/pending sessions do not participate in stock aggregation.

## Serialized identity and position

REGISTERED is labeled identity without stock. Receipt may atomically create item, barcode, ledger and position. Printing before receipt requires audited registration first; printing never proves physical receipt.

- Internal ID/barcode is globally unique. Optional manufacturer serial is unique per product after outer trim/uppercase; preserve punctuation and leading zeros plus raw display. Do not resolve collisions by appending random suffixes. A truly case-sensitive model requires an explicit policy revision before import.
- Receipt +1: REGISTERED → IN_STOCK. Issue −1: IN_STOCK → ISSUED, location null. Transfer changes location with paired legs and stable item ID.
- Verified RETURN receipt reuses the ISSUED item and references its latest issue. Never create a second item for a known identity.
- Serial adjustment names each specific item. Loss removes identified items; found stock requires verified identity. No anonymous +3 serial adjustment.
- Check lastLineId/version under lock. Owner manufacturer-serial correction is audited and still subject to uniqueness.
- Core onHand is STORAGE; non-saleable/damaged commercial returns cannot enter saleable stock until quarantine is implemented. Later quarantine/transit moves use transfer legs: physical quantity remains, eligible availability changes. Status and location must agree.

## Idempotency and uncertain outcomes

Freeze the final client envelope before sending: random UUID key, UUID sourceSessionId, actor-bound session and canonical business payload. Server computes SHA-256 over canonical UTF-8 JSON with fingerprintVersion=1. Stable field order, equivalent decimal normalization, grouped/sorted quantity pairs and sorted serial identities remove meaningless differences. Include type, sourceSessionId, locations, reasons/references and new-serial metadata. Exclude the key, browser time and meaningless scan order. Never trust a client hash. Preserve historical fingerprint rules across upgrades.

Standalone inventory CommandReceipt and movement are 1:1. POS uses SaleCommandReceipt and optional composed goods movement under [17](17-POS-SALES.md); never an independently callable second stock command. Unique actor/key and actor/sourceSession prevent duplicate logical commands. Create receipt inside the posting transaction and complete its result before commit; no durable PROCESSING receipt in Core.

1. A concurrent duplicate waits on the first unique claim/commit.
2. Existing equal fingerprint returns the original committed result. Different payload/session produces 409 IDEMPOTENCY_CONFLICT.
3. The same source session with a new key still cannot create another movement. Return the original if equivalent; otherwise conflict.
4. Definitive domain rejection rolls back receipt and posting. Edited drafts after rejection use a new revision/key, never the same key with changed payload.
5. Timeout, disconnect, 5xx or missing receipt means **unknown**, not definitive failure. Keep the frozen envelope; check status/retry the exact original key. If the first attempt rolled back, retry safely starts again.
6. Expired authentication requires the same actor to sign in before recovery. Another account cannot submit the previous actor's envelope or inspect its result.

Successful receipts live as long as the ledger. No 24-hour expiry permitting replay. Recheck result-read authorization after role revocation; direct unauthorized users to the owner without exposing details or creating another transaction.

## Transaction and global lock order

Use READ COMMITTED and a pessimistic product guard for every stock/health writer. Product locks intentionally serialize same-SKU operations across locations, preventing duplicate aggregate episodes for this small operation. Browser checks are advisory. References: [PostgreSQL locks](https://www.postgresql.org/docs/current/explicit-locking.html), [isolation](https://www.postgresql.org/docs/current/transaction-iso.html).

One connection/transaction; do not parallelize dependent queries across connections:

1. Validate payload limits, origin/CSRF, session and initial permission. Ignore browser actor claims.
2. BEGIN and claim the unique receipt. On conflict, return the existing authorized result/hash outcome.
3. Shared-lock user/assignment guard and recheck active permission. Revocation writes the same guard: an already-locked command may finish before revocation; subsequent commands fail. Shared-lock referenced master records in reference-type/ID order when needed.
4. Shared-lock all location IDs in order. Validate active warehouse/type and onboarding/opname freeze. Only authorized OPENING for the matching onboarding cutover may write an onboarding-frozen location. Location deactivation/freeze uses the same exclusive guard. Every writer, including transfer/correction, checks it.
5. FOR UPDATE product guards in increasing ID order. Guards exist from product creation. Tracking, threshold, monitoring, deactivation and health workers share them.
6. Create missing zero balance rows under unique product/location keys, then lock pairs in order. Lock serial IDs in order, then relevant reservation/approval documents. Never acquire product locks after serial locks.
7. Read current balances/positions/versions after locking. Validate every line, scope, reason, source operation and available stock, including both transfer sides. Never wait for human input.
8. Insert header/legs; synchronously update balances and positions with before/after values. Allocate monotonic inventorySequence **once per product per movement**, stored on all its legs; lineNo orders within the document. This is separate from master edit version. Gaps are acceptable. Evaluate health once per product using the document's final balances. Write episode/event/inbox/outbox/audit and receipt result.
9. Deferred constraint validation checks leg shapes, product/serial agreement, balanced transfer, serial positions and touched balances before commit. Runtime cannot disable these triggers. Tests also compare full historical aggregation.
10. COMMIT before success response. External delivery runs afterward.

Threshold changes lock user → product. Location deactivation locks user → location then checks emptiness; stock writers already hold shared location locks. Product deactivation locks user → product and checks balances/reservations/open operations.

Global order for all writers: optional import job → command receipt → user → business/policy/reference guards → shift/register → cart/existing sale/refund aggregate → locations → products → balances → serials → reservation/approval if relevant. Standalone inventory/import skips commercial guards. POS in 17 supplies the same connection/transaction to this service; no nested commit. Commands skip unused classes. Future modules MUST NOT introduce reversed lock order.

Two users issuing the final unit: A commits zero; B then reads zero and fails. Opposing multi-product transfers acquire the same ordered product guards.

Initial interactive lock_timeout 3 seconds, statement timeout 10 seconds; measure in R04. Known rolled-back deadlock 40P01/serialization 40001 may retry the entire transaction up to three times with short jitter and the same key. Do not blindly retry identity/stock conflicts. Lost commit response follows unknown-outcome recovery.

## Imports and financial boundary

Product import creates master/aliases/audit only. Opening import adapts to OPENING with manifest, receipt and ImportCommit under 14. Core onboarding freeze is persistent cutover control, not the full Stock Opname module. Audit freeze activation/release. Owner releases only after reconciliation; later corrective commands follow ordinary rules after release. SUPER_ADMIN has no implicit freeze bypass.

Physical stock responses contain no financial amounts. Core supplies private cost-evidence links and inventorySequence. Core costing/COGS are versioned financial projections under [15](15-FINANCE-PROFITABILITY.md). Unknown costs do not invalidate lawful physical receipt; they block profit claims. A physical reversal does not silently rewrite published financial reports.

## Reversal and correction

Core supports one full reversal per original, enforced by unique originalMovementId. Cannot reverse a reversal; later corrections use reasoned adjustments with cross-references.

Preview inverse effect against current stock. Apply original locations/products/quantities exactly. Consumed receipts or transfers whose destination stock has left may fail reversal. Investigate and record actual corrective movements; never change inverse targets to pass validation or allow negative stock.

For serials, every item must still be at the original result and its lastLineId must reference that original movement. If it moved later, reject reversal even if it returned to the same location. Preserve before/after status/location snapshots including originally REGISTERED items. Reversal restores prior status/location, but lastLineId points to the new reversal leg. Initial receipt reversal returns REGISTERED; RETURN receipt reversal returns ISSUED.

Issue reversal requires verified recording error/physical return, not a desire to change a report. Normal sale-linked commercial return uses RECEIPT/RETURN through 17 with cumulative original-sale allocation checks. Sale-generated issue/return cannot be independently reversed by this endpoint; use the commercial correction command so revenue/payment/stock remain linked. Reason/reference mandatory; document references suffice in Core, attachments later.

## Reservation — later

Reservation changes available, not physical ledger quantity. Create/change/release/expiry writes immutable reservation events, reserved projection, health and audit in the same guarded transaction. remaining = allocated − fulfilled − released; all components nonnegative. Serial reservations identify units with unique active allocation.

Fulfillment references the reservation and reduces onHand and reserved together, avoiding a second availability reduction. Ordinary issue uses unreserved stock and cannot select reserved serials. Partial fulfillment continues until remaining zero. Ordinary transfer cannot move reserved stock; release/reserve explicitly or design a later atomic relocation command.

Expiry and fulfillment compete under identical locks. Expired-reservation issue fails for review, never silently falls back to free stock. Availability changes only when expiry commits, not from a second timestamp-based interpretation.

## Transit, opname and approval — later

Staged transfer dispatches STORAGE → TRANSIT, then receives TRANSIT → destination STORAGE. Each document is atomic/idempotent. Outstanding = dispatched − received − returned, with separately recorded authorized loss adjustments reducing receivable outstanding. Partial receipt cannot exceed remaining outstanding. Never shrink an old dispatch to hide loss. Transit remains physical company stock but is excluded from available/reservable stock.

Stock opname uses a location **freeze scope**. Activate under location locks; all ordinary writers reject clearly. Do not hold a database transaction throughout human counting. Snapshot balances/serial set after freeze; count, submit, owner review, then atomically post approved adjustment and release freeze. Only the matching approved opname posting may consume its freeze. Watchdog warns about stale freezes; no automatic release. Cancel audits and releases without posting. Changed submitted counts create a new proposal revision and invalidate prior approval.

Approval binds proposal hash, baseline/version, actor, expiry and single consumption. Revalidate current permissions/balances/version at posting; approval does not guarantee stock remains. Core owner correction needs no general approval engine.

## Reconciliation

Read-only checks compare full ledger aggregation to balances, serial positions, reservation events/remaining and health. Mismatch opens an incident, blocks affected posting and alerts the owner. Never auto-write to hide corruption.

If ledger is correct and projections are wrong, rebuild only in authorized maintenance with backup, operational audit and before/after checks, without changing ledger. Business errors in ledger require corrective movements. [09](09-DEPLOYMENT-OPS.md) owns restore procedure.
