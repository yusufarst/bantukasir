# 17 — Sale, payment, documents and daily reconciliation

## Atomic full-payment Sale

GOODS only; client draft → COMPLETED Sale. No Order/booking/DP/outstanding/reservation/staged fulfillment. Optional receipt customer name only, no required customer/CRM. One payment method per Sale: CASH or BANK_TRANSFER. QRIS/card/e-wallet/gateway are disabled future capabilities; extensible method code/label does not enable them.

Server validates active actor/role, source intent, day guard, product/version, authoritative price, qty/unit/stock, totals and payment. No cashier price override/discount. Changed price asks for review, never silently changes payable total. CASH stores total/tender/change/applied amount: tender >= total, change = tender - total, applied = total. Transfer applies exact full total, records optional reference and cashier confirmation that funds were received; no gateway verification claim.

Final review confirms immediate goods handover. One transaction creates Sale/lines, full Payment, SALE_ISSUE, StockBalance, cost source/allocation or UNKNOWN, immutable ReceiptSnapshot, audit and durable command result. Fail anywhere → none committed. Prevent duplicate source intent even with different retry keys. No payment-only or stock-only success. Customer-bank actions happen outside DB; resolve uncertain record outcome before requesting repayment.

Stock insufficient/price stale is a definite rejection; review draft again with explicit new revision. Lost response is UNCERTAIN: freeze original envelope, query/retry same identity, do not create replacement. Status recovery must work even after day finalization. Print after commit; print failure cannot undo Sale.

## Receipt and A4

Every Sale has unique immutable number/snapshot. Include frozen business name/logo/address/contact/header/footer/document identity, UTC time rendered in Jakarta, cashier, items/SKU/unit/qty/unit price/line totals/total, method and cash tender/change. Retain identity/template assets for reproduction. Reprint same number/content with copy marker; no new Sale/Payment/movement.

Render thermal (actual 58/80 mm device decision at gate) and A4 Nota Penjualan / Commercial Invoice from same source, using browser print/PDF. No duplicate entry. No tax invoice/legal equivalence claim; G1 controls any tax/buyer-identity changes. Authorize receipt/history/reprint on each request; cashier own transactions, owner all. No private cost in document data.

## Minimal owner refund/return

Original Sale → eligible lines/qty/amount → mandatory reason/recent auth → review actual payout/physical goods → confirm. Lock original Sale and affected products. Cumulative refunded amount cannot exceed original paid value, line refunds cannot exceed original line value, return qty cannot exceed issued qty minus prior returns. Partial line-value refund permitted only within remaining monetary bound; no exchange/credit-account engine.

Append executed Refund evidence (CASH or BANK_TRANSFER, owner funds/reference), optional saleable RETURN_RECEIPT, historical cost recovery, audit and durable result in one transaction. Stock returned without additional money is allowed if original monetary refund already occurred; independently bounded to prevent double refund/return. Refund without physical return adds no stock; damaged/non-saleable return adds no saleable stock. Original Sale/Payment/receipt stay unchanged. Owner records correction document linked to original.

Owner payouts use separate owner-controlled funds, never cashier collection. Track payout actor/time/method distinctly in owner reports. If cashier drawer payouts/shared cash are actually required, STOP at G3 and replan rather than silently subtract them from expected daily cash.

## Daily cashier reconciliation — no shift engine

Identity: **cashier + businessDate**, date computed by server in Asia/Jakarta, never client backdated. Exactly one final report per identity. No register/opening float/paid-in/out/OPEN-CLOSING-CLOSED shift lifecycle.

Expected cash = sum of applied CASH payments of completed Sales attributed to that cashier/date.
Variance = physical cash - expected cash.
Transfer does not enter expected cash. Physical count means retained sales cash only, excluding any separately held change float. No shared drawer or intermediate cash removal in this baseline. G3 must confirm the operating model before BK28/pilot; otherwise do not pretend this report reconciles a drawer.

### Safe cutoff with versioned count

1. Every checkout acquires unique CashierDay row lock before recording activity. Under that lock capture server businessDate/time immediately before writes; reject/retry definite date rollover before creating facts. If finalized, reject new Sale for that day for both CASH and BANK_TRANSFER. Increment activityVersion atomically with Sale.
2. Cashier requests a daily count preview containing day + activityVersion + safe own totals. No persistent closing state or DB lock during counting. User pauses checkout, counts sales cash, enters physical amount and confirms against that version. Blind counting is not a V1 requirement.
3. Finalization takes the same day lock. If activityVersion changed, reject with **Ada transaksi baru; hitung ulang kas**, return current scope and require new count/confirmation. Never silently accept a stale count.
4. With matching version, derive all source Sale/Payment IDs and totals in a consistent read, create immutable DailyCashierReportSnapshot + count/variance/cutoff + finalized marker + audit + command result atomically. Unique day/cashier report guards different duplicate keys; same count/version recovers existing report, conflicting count returns conflict, never overwrites.
5. Racing Sale that obtains lock first is included and invalidates prior count. Finalization that obtains lock first makes later Sale reject before payment/stock recording. Therefore no successful Sale disappears from daily scope.
6. After finalization that cashier cannot sell again on that date. New server business date has its own guard. No reopen button or owner override appends Sales into a frozen day. Forgotten prior-day reports may finalize later using immutable date attribution; they never absorb next-day sales. At midnight re-fetch date/version and recount if collecting a different day's cash. Operational separation of days is G3.

Resolve uncertain checkout results before counting; durable status still returns already committed result after finalization. A count mistake gets owner-authored review note linked to immutable report, not overwritten physical count/variance. Need for reopened/multiple sessions requires a scope decision. No hidden report revision that erases original facts.

### Snapshot, history and export

Snapshot: report ID/number, frozen business identity/template, cashier/date, finalizedAt/cutoff/activityVersion, source IDs, completed Sale count/value, cash/transfer applied totals, expected/physical/variance, safe note. Owner refunds remain separately linked context, never rewrite original collection totals.

Cashier only own report/history; owner all; operations no report access. Reauthorize listing/detail/print/PDF/CSV/reprint/file URLs. Allowlist excludes acquisition cost/HPP/evidence/margin/profit/security data. CSV quotes fields, preserves IDR/date context, neutralizes formula/control prefixes in text without turning negative numeric variance into a formula.

Browser print/PDF/CSV/export failure leaves finalization committed; retry renders same snapshot. Export/reprint creates no financial/stock transaction. Audit print/export request separately; browser print-dialog close is not proof of ink.
