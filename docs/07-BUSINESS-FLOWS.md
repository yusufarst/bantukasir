# 07 — Business workflows

Owns user-visible cross-module flows. 17 owns commercial commands; 06 owns physical inventory.

## F01 — Configure/onboard

Owner configures BusinessProfile, users/roles, locations/registers, payment/receipt policy and basic references. Operations prepares goods/services, prices and barcodes. Product import creates master only; opening stock is a separate reviewed ledger workflow.

## F02 — Receive goods

Admin Operasional: `Barang Masuk → scan/search → quantity/serial → destination/reference → review → confirm`.

Confirmation posts RECEIPT, updates balances/attention and audit. Services cannot be received.

## F03 — Fast counter sale

Cashier: `Kasir → scan/search → cart → Bayar → confirm → receipt`.

Normal immediate order requires no customer. Full payment is recorded; GOODS fulfill immediately; only actually completed counter SERVICE may complete immediately. Printer failure only affects printing/reprint.

## F04 — Order / booking / DP

Cashier or Operations selects **Pesanan / DP**:
1. identify/create customer;
2. add GOODS/SERVICE;
3. set schedule/due date only when needed;
4. choose unpaid, DP/partial or full payment;
5. reserve GOODS where appropriate;
6. create service job/schedule where appropriate;
7. confirm.

Confirmation does not pretend fulfillment occurred.

## F05 — Reservation/partial fulfillment

Order 10 → reserve 10 → onHand unchanged, available -10.

Later: hand over 4 → ISSUE 4; then 3 → ISSUE 3; final 3 → ISSUE 3. Each command is idempotent/auditable and remaining reservation follows remaining obligation.

## F06 — Service job

`Booked → Scheduled → In Progress → Completed`.

Milestones may represent survey, preparation, execution, cleanup and handover. Progress events are append-only. Progress does not move stock or imply payment.

Company-stock material genuinely consumed for a job uses a separate authorized GOODS issue with service-cost reference.

## F07 — Later payments

Open order → **Tambah Pembayaran**. DP, second payment and final payment remain separate records. Outstanding balance derives from effective order total, payments and refunds/credits.

Cash payment requires the actor's own open register shift.

## F08 — Changes/cancellation

Drafts are editable within permission. After confirmation, material changes use a recorded revision. Never overwrite fulfilled quantities, old payments or prior service progress.

Whole-order cancellation is allowed only when obligations can still be safely cancelled. Otherwise cancel bounded remaining work and use refund/return/correction flows for facts already performed.

## F09 — Refund/return

Owner authorizes Core refunds with reason/re-authentication. Money refund and physical return are distinct. Refund without returned goods creates no stock movement; verified saleable return posts linked RETURN RECEIPT; service refund never creates stock.

## F10 — Restock

Owner/Operations act on LOW/OUT attention. Read state does not resolve it. Receipt or valid policy change reevaluates health. Suggested target never auto-purchases.

## F11 — Cashier shift

Open assigned register with counted float. Cash events derive from actual cash payments/refunds plus authorized paid-in/out.

Use **Kasir → Shift Saya → Tutup Shift → Laporan Shift**. Closing establishes a stable cutoff, blocks new shift-attributed operations and resolves original uncertain attempts before blind physical counting. Confirm count → atomic close/snapshot → show expected/physical/variance → print/PDF/CSV. Difference is retained/reviewed, never silently balanced. Own shift history reuses the immutable snapshot; failure retries never create transactions. [17](17-POS-SALES.md) owns the sequence, separate commercial/payment/cash sections and per-shift (not calendar-day) attribution.

## F12 — Owner review

Period: Hari Ini, 7 Hari, Bulan Ini, Bulan Lalu, Rentang Tanggal.

Keep distinct: nilai pesanan, pembayaran masuk, sisa tagihan, recognized revenue, HPP/direct service cost, laba kotor, active/late orders/jobs, LOW/OUT and cashier variance. Incomplete cost is explicit. Authorized owner drill-down shows each shift close status, variance and original report; later corrections are separate linked facts.

## F13 — Incident/recovery

Stop affected posting when integrity/result is uncertain. Resolve using durable command/reference and real evidence. Never re-charge/re-pay/re-issue by assumption. Restore isolated, reconcile orders/payments/ledger/reservations/jobs/shifts/receipts, then reopen.
