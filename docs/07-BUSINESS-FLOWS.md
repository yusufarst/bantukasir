# 07 — Business workflows

This document connects user responsibilities. [06](06-INVENTORY-SPEC.md) owns stock rules, [12](12-BARCODE-SCANNER.md) scanner interaction, [04](04-AUTH-RBAC-SECURITY.md) permissions and [13](13-NOTIFICATIONS.md) alerts.

## F01 — Initial onboarding

The operator prepares the environment; the owner activates their account/2FA, assigns staff roles and creates warehouse/location/unit/category/brand references. Product admin uploads and validates the master XLSX/CSV; owner reviews and applies the identity/minimum batch. Products initially have no stock, publication or active monitoring. Test scanners/labels with demo data first. [14](14-BULK-IMPORT.md) owns templates and atomicity.

Owner defines the legacy-system cutover and activates onboarding freeze. Staff count quantities/serials by location and prepare separate opening templates. Owner reviews and applies an atomic OPENING job. Reconcile physical totals and identities, activate monitoring for stocked SKUs and release freeze. Staging push/backup/restore acceptance precedes go-live. Owner records private opening cost evidence; unknown does not become zero.

Do not run two competing balance sources without an explicit cutover. Correct master/label mistakes before opening where possible. Tracking/unit changes after ledger use are not ordinary edits. Demo data never mixes with production stock.

## F02 — Receipt

1. Staff choose **Barang Masuk**, active destination, source/sender, reason and optional reference.
2. Scan quantity products or serialized items. Identify new serials against the correct product. Hold unknown goods for identification; never auto-create stock.
3. Correct the unsaved session. REGISTERED identities may be printed without changing stock.
4. Review physical goods, lines and context; confirm once. Server posts all or none.
5. A server document number confirms completion. **Transaksi Baru** clears lines and creates a new session, showing any retained context explicitly.

Lost responses follow the recovery contract. Returns use RETURN with the issue reference and verified identity. Damaged returned stock requires the quarantine/QC workflow when available; do not assume it is immediately issue-eligible.

## F03 — Issue

1. Staff choose source, destination/recipient, reason and work/document reference. Normal authorized issues need no owner approval.
2. Scan, correct and review identities/quantities.
3. Server validates current availability/serial state. Concurrent changes reject the whole document with actionable line errors, not a partial save.
4. After commit, show the document number. Atomic stock-health evaluation updates owner attention/inbox. Push delivery failure does not undo lawful stock posting.

Physical handover follows the warehouse SOP and posting evidence. If field conditions require another sequence, owner defines a documented SOP without removing recording. RFQ/quotation acceptance never directly reduces physical stock.

## F04 — Direct transfer

Staff choose distinct source/destination, reason, scan and confirm. Both locations must be handled in one operational activity. Paired legs commit together; total physical stock is unchanged. Any invalid destination/serial rejects all.

Do not pretend goods have arrived when travel and separate receipt are required. Implement staged transit first or limit Core operations to immediately verifiable direct transfers.

## F05 — Errors and correction

Before confirmation, staff edit/undo the draft. After posting, staff identify the document and explain the issue to the owner. Core has no formal approval request inbox.

Owner verifies physical facts, later movements and impact, then chooses an eligible full reversal or a count-based adjustment. Require recent authentication, reason/reference and current-state preview. Unsafe reversals fail with an explanation; investigate and correct actual facts. Original and corrective documents remain readable. No delete-transaction or edit-balance button.

## F06 — Owner stock attention

Owner receives inbox and opted-in push, then opens **Menipis/Habis**. Detail shows event snapshot, current state, location stock and movement/actor history. Owner may mark read or organize procurement outside Core.

Reading is not recovery. Receipt, reservation release or a lawful policy change triggers reevaluation. Normal stock resolves the episode; later decline can open a new episode. Owner never manually edits balances or closes alerts to synchronize the system. Period selection does not hide current OUT stock. Finance appears only with the verified sources under 15.

## F07 — Revoke accounts/devices

Owner disables departing staff. Backend revokes sessions/push access while preserving historical actor identity. Unsaved sessions cannot post with revoked permissions. Committed transactions remain. Shared-device users log out before account switching; drafts never transfer automatically between actors.

## F08 — Reservation and sales fulfillment (later)

Sales records an accepted deal and requests product/location/unit allocation. Reservation service holds availability. Warehouse scans issue against that reservation; onHand and reserved decrease together and fulfillment updates. Partial fulfillment retains the remainder; cancellation/expiry releases it without adding physical stock.

Sales cannot choose signed deltas or directly write movements. Failed allocation is not fulfillment. Backorder cannot become negative physical stock.

## F09 — Stock opname and approval (later)

Owner defines scope/time. System freezes locations before baseline capture. Staff count quantities/serials and submit. Owner reviews differences. Approved proposal is revalidated by hash/version at posting; adjustment and freeze release commit together. No staff self-approval. Cancel does not post differences.

Staff-proposed sensitive corrections require approval. Any future materiality threshold must come from actual owner policy, not an invented amount. Routine receipt/issue/direct transfer remain independent of approval.

## F10 — QC, warranty and service (later)

Staff record inspection results. Failed QC reduces eligibility through an audited transfer to quarantine; it does not delete physical stock. Warranty links a serial to terms/period. Service cases distinguish company property from customer custody. Only company-stock movements enter the inventory ledger. Closing a service case does not automatically sell or deliver goods.

## F11 — Public content, inquiry and sales (later)

Owner edits structured company/contact/hero/footer/SEO content and configured wa.me destination/templates in the internal CMS, previews the draft and publishes a specific revision. Product admins may prepare permitted product content; owner controls publication. Live content changes without code edits or redeploy. [02](02-ARCHITECTURE.md) owns validation, publication and cache boundaries.

Visitors search/compare published product projections and choose a configured WhatsApp link or RFQ. Track WhatsApp only as an optional outbound click, never a sent message, qualified lead or sale. No WhatsApp API/provider.

RFQ validates contact/requirements with anti-spam controls → sales qualification → lead → versioned quotation → deal → F08. Custom requests may be free-text needs without fake stock SKUs. Public attachments remain deferred until file controls exist. Confirmation is Indonesian and cannot reveal another applicant's data.

After shipment, verified acceptance creates the revenue fact. Scanning an issue alone does not recognize revenue.

## F12 — Incident and recovery

Staff see connection state and stop new posting when the previous result cannot be determined; preserve session references. Operator checks health/outbox/backup. Owner pauses operations if stock cannot be trusted. [09](09-DEPLOYMENT-OPS.md) governs restore, with posting and external delivery paused until reconciliation. Never fabricate success to appear responsive.

## F13 — Cost completeness and profitability

Core: owner records/verifies private receipt/opening evidence while staff work without prices. After Sales and valuation, accepted goods match cost allocations to support **Laba Kotor Penjualan Barang**. Pending data shows incompleteness, not estimated profit. Owner selects a period and drills into eligible orders/SKUs; sales staff see authorized selling prices without COGS/margin.

Physical return, credit/refund and cost correction may occur at different times. Each has its own event/reference. Recheck completeness before publishing a revised report under [15](15-FINANCE-PROFITABILITY.md). Refund does not add stock; stock input does not silently rewrite prior reports. Expense accounting/net profit is outside this scope.
