# 07 — Business workflows

| Flow | User sequence / outcome |
| --- | --- |
| Configure | Owner establishes users, BusinessProfile and policy gates; no public signup |
| Find/create | Scan/search name/SKU/barcode/category/brand; reuse existing; if absent operations/owner reviews similar matches then creates product and barcode |
| Receive | Barang Masuk → scan/search → qty + new cost evidence or UNKNOWN → source/reference → review → confirm; one RECEIPT |
| Open stock | Owner confirms G2/cutover, manual counts/costs → review → OPENING → reconcile; no balance editing |
| Manual issue | Barang Keluar → scan/search → qty → required non-sale reason → confirm; one MANUAL_ISSUE |
| Sell | Kasir → Scan/Cari → Keranjang → qty → cash/transfer full payment → review/handover confirmation → atomic Sale → Struk |
| Print | Same committed snapshot → thermal or A4 Nota Penjualan → browser print/PDF; failure retries original number |
| Restock | Owner/operations current LOW/OUT → stock/history → receive; no automatic purchase |
| Correct | Owner original source → bounded refund/return or stock correction → reason/recent auth → confirm; originals retained |
| Daily report | Kasir → Laporan Saya → current business day → stable activity version → count physical cash → finalize → immutable report/PDF/CSV/history |
| Owner review | Choose period → sales/count/cash/transfer/qty/HPP/Laba Kotor + completeness; current LOW/OUT separately |
| Incident | Freeze affected posting → resolve durable original result or restore/reconcile evidence → owner-approved reopening |

Expected daily cash includes only applied CASH Sale payments; transfer never increases cash. Cashier counts only their retained sales cash, excluding change float. Owner refunds use separate owner funds and appear separately; this operating assumption is a G3 gate, not a hidden shift engine. Any sale during counting invalidates the count version and requires recount. After finalization no further Sale for that cashier/day. [17](17-POS-SALES.md) owns exact cutoff/retry behavior.

Unknown barcode keeps the token for search; cashier cannot create master/aliases. A scan never posts stock. Unknown payment/stock/save result freezes the original request for recovery; no repeat charging or replacement Sale by assumption.
