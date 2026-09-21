# 17 — Sale, payment, documents and lightweight cash sessions

## Cash-session boundary

`CashRegister` is only a logical identity for a physical cash drawer/counter. Default V1 policy is **one drawer → one OPEN session → one responsible cashier**. It is not a warehouse, employee attendance record, work schedule, payroll period or complex retail shift engine. Simultaneous cashiers sharing one physical drawer, responsibility transfer inside a session, paid-in/paid-out and cash pickups are Later.

A `CashSession` has an immutable number, register, responsible cashier, openedAt, explicit nonnegative openingCash (Rp0 is valid), activityVersion and OPEN/CLOSED status. Opening locks the register, verifies no OPEN session for that register or cashier, records the count and audit/result atomically. One business may start with one configured register; supporting several physically distinct drawers uses the same uniqueness rule and is subject to G3 confirmation.

A cashier who leaves closes the session. Returning on the same date means opening a new session. Handover means close cashier A's session, then cashier B opens a new one. A session may span midnight; it remains one accountability document. Every Sale retains its server-derived Jakarta businessDate for period reporting independently of session identity.

## Atomic full-payment Sale

GOODS only; client draft → COMPLETED Sale. No Order/booking/DP/outstanding/reservation/staged fulfillment. Optional receipt customer name only, no required customer/CRM. One payment method per Sale: CASH or BANK_TRANSFER. QRIS/card/e-wallet/gateway are disabled future capabilities; extensible method code/label does not enable them. Every checkout actor needs their own eligible OPEN CashSession, including transfer-only sales, so commercial activity and responsibility have one stable boundary.

Server validates active actor/role, source intent, session/register responsibility, product/version, authoritative price, qty/unit/stock, totals and payment. Lock the OPEN session before product/stock writes and increment activityVersion with the Sale. No cashier price override/discount. Changed price asks for review, never silently changes payable total. CASH stores total/tender/change/applied amount: tender >= total, change = tender - total, applied = total. Transfer applies exact full total, records optional reference and cashier confirmation that funds were received; no gateway verification claim and no physical cash effect.

Final review confirms immediate goods handover. One transaction creates Sale/lines, full Payment, SALE_ISSUE, StockBalance, cost source/allocation or UNKNOWN, immutable ReceiptSnapshot, audit and durable command result. Fail anywhere → none committed. Prevent duplicate source intent even with different retry keys. No payment-only or stock-only success. Customer-bank actions happen outside DB; resolve uncertain record outcome before requesting repayment.

Stock insufficient/price stale/closed session is a definite rejection before payment or stock facts; review draft again with explicit new revision or a new session as applicable. Lost response is UNCERTAIN: freeze original envelope, query/retry same identity, do not create replacement. Status recovery of the original result must work after its session closes. Print after commit; print failure cannot undo Sale.

## Receipt and A4

Every Sale has unique immutable number/snapshot. Include frozen business name/logo/address/contact/header/footer/document identity, UTC time rendered in Jakarta, cashier and session number, items/SKU/unit/qty/unit price/line totals/total, method and cash tender/change. Retain identity/template assets for reproduction. Reprint same number/content with copy marker; no new Sale/Payment/movement.

Render thermal (actual 58/80 mm device decision at gate) and A4 Nota Penjualan / Commercial Invoice from same source, using browser print/PDF. No duplicate entry. No tax invoice/legal equivalence claim; G1 controls any tax/buyer-identity changes. Authorize receipt/history/reprint on each request; cashier own transactions, owner all. No private cost in document data.

## Minimal owner refund/return

Original Sale → eligible lines/qty/amount → mandatory reason/recent auth → review actual payout/physical goods → confirm. Lock original Sale and affected products. Cumulative refunded amount cannot exceed original paid value, line refunds cannot exceed original line value, return qty cannot exceed issued qty minus prior returns. Partial line-value refund permitted only within remaining monetary bound; no exchange/credit-account engine.

Append executed Refund evidence (CASH or BANK_TRANSFER, owner funds/reference), optional saleable RETURN_RECEIPT, historical cost recovery, audit and durable result in one transaction. Stock returned without additional money is allowed if original monetary refund already occurred; independently bounded to prevent double refund/return. Refund without physical return adds no stock; damaged/non-saleable return adds no saleable stock. Original Sale/Payment/receipt stay unchanged. Owner records correction document linked to original.

Owner payouts use separate owner-controlled funds, never an OPEN cashier drawer in the V1 baseline. Track payout actor/time/method distinctly in owner reports. If drawer-funded refunds/cash pickups/paid-in/out or shared active cash responsibility are actually required, STOP at G3 and replan rather than silently changing session expected cash.

## Laporan Sesi Kas — primary reconciliation

Expected physical cash uses only modeled drawer facts:

`expectedCash = openingCash + applied CASH payments in the session`

`variance = physicalCash - expectedCash`

BANK_TRANSFER never increases expected physical cash. Owner-funded refunds remain separate. No paid-in/paid-out, cash pickup, drawer-funded refund or hidden balancing adjustment exists in V1. Opening cash is required as an explicit nonnegative count; enter Rp0 when the business intentionally starts empty. G3 confirms that this matches real drawer practice before BK28/pilot.

### Race-safe close without a CLOSING state

1. Cashier requests a close preview for the own OPEN session. Server returns session identity, current activityVersion and allowed operational totals. No database lock stays open while cash is counted. Blind counting is not a V1 requirement.
2. User pauses checkout, counts the physical drawer, enters physicalCash and submits the frozen session ID + activityVersion + command identity. Scanner Enter cannot close the session.
3. Close locks the same session used by checkout. If it is already CLOSED, an equivalent retry returns the original result; a conflicting payload returns conflict. If activityVersion changed, reject with **Ada transaksi baru; hitung ulang kas** and leave the session OPEN.
4. With matching version, derive source Sale/Payment IDs, totals and expectedCash; persist physical count, variance, cutoff/closedAt, immutable CashSessionReportSnapshot, CLOSED status, audit and durable result atomically. Snapshot/audit failure leaves the session OPEN.
5. A racing Sale that locks first commits once and increments the version, forcing recount. Close that locks first commits CLOSED, so the Sale then rejects before payment/stock facts. A successful Sale cannot disappear from the session.
6. Resolve uncertain checkout results by their original identity before close. Already committed original results remain recoverable after CLOSED. An owner-assisted close follows the same transaction with physical count, recent authentication and mandatory reason; it never changes the responsible cashier.

There is no reopen or responsibility-edit action. A count mistake receives a linked owner review note, never overwritten physical/variance facts. Another working period, even on the same business date, starts a new session. Handover closes A before B opens. Partial unique constraints prevent overlapping responsible sessions for one register or cashier.

### Immutable session snapshot and export

Snapshot contains report/session number, frozen business identity/template, register and cashier identities, openedAt/cutoff/closedAt, openingCash, source IDs/activityVersion, completed transaction count/value, cash and transfer applied totals, expectedCash, physicalCash, variance, close actor/reason and safe notes. It is the as-closed accountability document, not a second Sale/payment source.

Cashier reads/prints/exports own sessions only; owner reads all; operations has no report access. Reauthorize listing/detail/print/PDF/CSV/reprint/file URLs. Allowlist excludes acquisition cost/HPP/evidence/margin/profit/security data. CSV quotes fields, preserves IDR/timezone/session context, neutralizes formula/control prefixes in text without turning negative numeric variance into a formula.

Browser print/PDF/CSV/export failure leaves CLOSED committed; retry renders the same snapshot. Export/reprint creates no Sale/Payment/StockMovement/session mutation. Audit the request separately; browser print-dialog close is not proof of ink.

## Rekap Harian owner

Owner daily recap uses Asia/Jakarta half-open date boundaries. Sales, payment, quantity, HPP and gross profit totals derive once from source Sale/payment/cost facts by each Sale's businessDate. Do not sum session snapshot sales/payment totals on top of those sources. List sessions relevant to the date with cashier/register/open-close times/status and per-session variance; a session spanning midnight remains one session and is listed under its close date (or active section until closed) with its full span visible.

Later refund/correction never rewrites a closed session snapshot. Daily financial totals reflect source correction facts according to [15](15-FINANCE-PROFITABILITY.md), while the original as-closed session row/report stays reproducible and linked to later context.
