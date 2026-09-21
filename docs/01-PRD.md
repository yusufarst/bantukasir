# 01 — BantuKasir product requirements

**Plan 1.1.** One configurable business, GOODS only, IDR, Asia/Jakarta dates. Production POS in approximately one month is a target subject to capacity/gates in [11](11-BUILD-PLAN.md). No runtime capability is implemented.

## Outcomes and Core V1

| Client problem | Required deliverable |
| --- | --- |
| Goods enter/leave unrecorded | Search-first receiving/opening, reasoned manual issue, immutable stock ledger |
| Sales notes are manual | Atomic fully paid Sale, thermal receipt and A4 Nota Penjualan / Commercial Invoice with browser PDF |
| Restock happens too late | minimumStock; current LOW/OUT list/badge/dashboard |
| Sales/HPP/profit are unknown | Owner Hari Ini, 7 Hari, Bulan Ini, Bulan Lalu, Custom Range; truthful MWA HPP and Laba Kotor |
| Products hard to find | One PostgreSQL Product Search shared by POS, receiving, issue, catalog, stock and labels |
| Mixed barcode availability | Manufacturer aliases, internal Code 128, labels, USB/Bluetooth HID and manual input |
| Cash cannot be reconciled | Lightweight accountable cash sessions with opening cash, cash/transfer activity, physical count, variance, immutable report and PDF/CSV/reprint |
| Owner lacks accountability | Filterable log of sales, payments, stock, corrections, master/security/config changes |
| Client identity varies | Runtime name, logo, address, contact, optional email, receipt header/footer, document identity and constrained accent |

Core includes individual accounts, backend RBAC, safe reset/invite, no public signup, secure sessions, rate limiting, disable/revoke, HTTPS, environment/secret safety, minimal owner refund/return/correction, independent backup/restore and actual device/pilot acceptance. No TOTP/2FA in V1.

## Exactly three roles

- SUPER_ADMIN / Pemilik: full access, private costs/reports/logs, users/configuration/recovery and sensitive corrections/refunds.
- OPERATIONS_ADMIN / Admin Operasional: catalog/category/permitted selling prices, barcode/labels, receiving, manual issue, stock/restock and operational history. No private HPP/profit or security/recovery administration.
- CASHIER / Kasir: open/close an authorized own cash session, search/scan, POS, cash/transfer recording, receipt/reprint, own transactions and own session reports. No receiving, adjustment, master/price editing, private finance, users/settings/audit or history deletion.

[04](04-AUTH-RBAC-SECURITY.md) defines the receiving cost-input boundary.

## UX

Premium, elegant, restrained, fast, professional. Cashier: **Buka Sesi Kas → Scan/Cari → Keranjang → Bayar → Struk → Tutup Sesi saat selesai/serah-terima**. No customer required. Operations: what needs receiving/restocking today? Owner: what needs attention and how is business doing? One approved design system; no decorative dashboard. Loading/empty/error/denied/stale/unknown-result states are deliverables.

## Later / excluded from V1

SERVICE and mixed goods/service; ServiceJob/scheduling/milestones/progress/completion/cost; booking/DP/partial or later payment/outstanding; reservation/partial fulfillment; CRM beyond optional receipt name; warehouse/location/transfers/transit; attendance/HR/payroll shifts; simultaneous multi-cashier sharing of one drawer; paid-in/paid-out; mid-session responsibility transfer; Web Push/episodes/outbox; camera/offline posting; serialized inventory unless G2 proves essential; pack conversion/batch/expiry/consignment; advanced opname; QC/warranty/repair custody; purchasing/PO/supplier AP; public website/catalog/CMS/RFQ/leads/quotation/WhatsApp attribution; promotions/discount engine/loyalty; gateway/QRIS/card/e-wallet; full accounting/net profit/expenses/payroll/tax accounting; multi-company/multi-currency; enterprise import/XLSX and simple CSV import (manual opening first). These are unestimated backlog topics, not hidden Plan 1.1 tasks.

## Policy gates

G1: tax/legal invoice policy, mandatory buyer details and treatment before pilot; commercial nota is not a formal tax invoice. G2: actual units/opening/barcodes/cost availability and serial need before affected data entry. G3: identify actual physical drawer(s)/labels, confirm explicit opening-float practice, one responsible cashier per active drawer, no simultaneous sharing, and how owner cash refunds are physically funded. G4: actual independent backup destination and accepted measured RPO/RTO before production. Planning can finish with gates open; execution stops at the relevant gate.
