# 07 — Business workflows

| Flow | User sequence / outcome |
| --- | --- |
| Configure | Owner establishes users, BusinessProfile and policy gates; no public signup |
| Find/create | Scan/search name/SKU/barcode/category/brand; reuse existing; if absent operations/owner reviews similar matches then creates product and barcode |
| Receive | Barang Masuk → scan/search → qty + new cost evidence or UNKNOWN → source/reference → review → confirm; one RECEIPT |
| Open stock | Owner confirms G2/cutover, manual counts/costs → review → OPENING → reconcile; no balance editing |
| Manual issue | Barang Keluar → scan/search → qty → required non-sale reason → confirm; one MANUAL_ISSUE |
| Open cash session | Kasir → choose authorized available drawer → count/enter explicit opening cash (Rp0 allowed) → review → OPEN session; no Sale yet |
| Sell | Responsible cashier with OPEN session → Scan/Cari → Keranjang → qty → cash/transfer full payment → review/handover confirmation → atomic Sale attributed once to session → Struk |
| Print | Same committed snapshot → thermal or A4 Nota Penjualan → browser print/PDF; failure retries original number |
| Restock | Owner/operations current LOW/OUT → stock/history → receive; no automatic purchase |
| Correct | Owner original source → bounded refund/return or stock correction → reason/recent auth → confirm; originals retained |
| Close/handover | Sesi Kas Saya → stable activity version → count physical cash → close → immutable session report; cashier handover then opens a new session for the next cashier |
| Return same day | Previously closed cashier selects available drawer and opens a new session; old report remains immutable |
| Owner review | Choose period → sales/count/cash/transfer/qty/HPP/Laba Kotor + completeness; list session reports/variance and current LOW/OUT separately |
| Incident | Freeze affected posting → resolve durable original result or restore/reconcile evidence → owner-approved reopening |

Expected session cash = explicit opening cash + applied CASH Sale payments. BANK_TRANSFER never increases physical cash. No paid-in/paid-out in V1. Owner refunds use separate owner-controlled funds and appear separately; they do not silently alter session expected cash. Any Sale during counting increments the session version and requires recount. Once CLOSED, that session accepts no Sale; the cashier may open a new session on the same date. One drawer cannot have two active responsible sessions. Simultaneous shared-drawer use is Later. [17](17-POS-SALES.md) owns exact cutoff/retry and daily aggregation.

Unknown barcode keeps the token for search; cashier cannot create master/aliases. A scan never posts stock. Unknown payment/stock/save result freezes the original request for recovery; no repeat charging or replacement Sale by assumption.
