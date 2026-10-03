# P2 — Model domain dan invariant bisnis

Model, constraints, angka dan waktu canonical berada di [02](../02-ARCHITECTURE.md); inventori fisik di [06](../06-INVENTORY-SPEC.md), MWA presisi di [15](../15-FINANCE-PROFITABILITY.md), Sale/payment/CashSession di [17](../17-POS-SALES.md), serta token/scanner di [12](../12-BARCODE-SCANNER.md). Ini specification, bukan schema yang sudah dijalankan.

| Boundary | Fakta dan invariant |
| --- | --- |
| Identity/config | Actor dari session, tepat tiga role, guard disable/revoke, serta revision BusinessProfile/aset/template immutable |
| Catalog | SKU/token ternormalisasi unik; pembuatan produk tetap dengan stok 0; revision harga; unit/precision terkunci setelah movement |
| Inventory | Ledger bertanda dan posting sequence immutable → balance sinkron nonnegative; tipe opening/receipt/manual issue/sale issue/adjustment/reversal/return |
| Cost | Evidence KNOWN/UNKNOWN dan allocation/revision immutable; MWA/residual enam desimal; replay deterministik, bounded dan all-or-nothing |
| Sale/payment | GOODS Sale langsung dibayar penuh; satu Payment CASH atau BANK_TRANSFER; session OPEN milik actor sendiri; seluruh fakta/snapshot/audit/result atomik |
| Cash | Satu OPEN per register DAN per cashier; opening 0 eksplisit; actor penanggung jawab tetap; close dengan shared lock/version; laporan immutable sesuai keadaan saat ditutup |
| Correction | Original dipertahankan; batas refund uang dan return quantity terpisah; biaya historis dipulihkan sekali; alasan Pemilik dan recent authentication |
| Read/recovery | DTO staff aman; Laba Kotor hanya Pemilik dengan asOf/completeness; source intent/key/result/epoch; backup independen dan rekonsiliasi |

## Conservation dan pemilik constraint

Delta fisik harus mempunyai movement; balance sama dengan ledger dan tidak diedit langsung. Money/payment/source/receipt tidak boleh diam-diam ditimpa atau dihapus. Return tanpa barang fisik layak jual yang terverifikasi tidak memulihkan stok; HPP pada refund-only tetap. Fakta original tetap menjelaskan setiap projection dan correction.

Uniqueness/check/FK/partial index database dilengkapi transaksi dan lock berurutan untuk guard aggregate; model observer atau state client bukan boundary. Privilege runtime menolak mutation fakta historis. Writer opening freeze, actor, config, product, Sale dan session berbagi guard untuk menjaga correctness pada race.

Perhitungan presisi: quantity mengikuti precision unit 0–3, IDR integer dibulatkan half-up sekali per baris, dan biaya memakai enam desimal; representasi canonical menggunakan decimal string/BigDecimal, tanpa float. BU schema mem-pin precision penyimpanan, magnitude dan maksimum request yang koheren serta menolak overflow. Detail batas tersebut adalah kontrak teknis yang diuji, bukan kebijakan mengizinkan stok negatif. BusinessDate memakai Jakarta, waktu server UTC, periode query half-open, serta snapshot/template/aset original dipertahankan.

PASS planning memerlukan invariant canonical dan pemilik constraint/test untuk setiap entity, typed movement, correction, money dan relationship session. Schema/test aktual belum tersedia.