# 17 — Sale, pembayaran, dokumen dan sesi kas

**Plan 2.0.** Spesifikasi Sale GOODS-only, dokumen immutable dan pertanggungjawaban drawer. Kontrak transaksi/idempotency mengikuti [02](02-ARCHITECTURE.md), permission/object scope [04](04-AUTH-RBAC-SECURITY.md), dan acceptance/bukti aktual [08](08-TESTING-ACCEPTANCE.md). Dokumen ini tidak mengklaim runtime atau hardware telah lulus.

## Batas CashRegister dan CashSession

CashRegister hanya identitas logical untuk drawer/counter kas fisik. Kebijakan V1 adalah **satu drawer → satu sesi OPEN → satu cashier yang bertanggung jawab**. Sesi kas bukan warehouse, attendance, jadwal kerja, periode payroll atau retail shift engine kompleks. Simultaneous shared drawer, transfer tanggung jawab di dalam sesi, paid-in/paid-out dan cash pickup berada di Later.

CashSession mempunyai nomor immutable, register, responsible cashier, openedAt, openingCash nonnegative yang eksplisit (Rp0 valid), activityVersion dan status OPEN/CLOSED. Buka sesi: Kasir/Pemilik sebagai dirinya sendiri memilih register berizin yang tersedia → menghitung/mengetik opening cash → review → confirm. Pembukaan mengunci register, memastikan tidak ada sesi OPEN untuk register maupun cashier tersebut, lalu commit sesi/count/audit/result secara atomik. Pemilik tidak mengimpersonasi cashier lain; Admin Operasional tidak memperoleh permission POS/session.

Bisnis boleh mulai dengan satu register yang dikonfigurasi. Beberapa drawer fisik yang benar-benar berbeda mengikuti constraint yang sama dan memerlukan konfirmasi G3. Cashier pergi/selesai bekerja menutup sesi. Kembali pada tanggal sama berarti membuka sesi baru; handover menutup sesi cashier A sebelum cashier B membuka sesi baru. Sesi boleh melintasi midnight dan tetap menjadi satu dokumen pertanggungjawaban. Setiap Sale menyimpan businessDate Jakarta yang dihitung server, terpisah dari identitas sesi.

## Sale atomik dengan pembayaran penuh

Client draft → Sale COMPLETED untuk GOODS, pembayaran penuh dan penyerahan langsung. Order/booking/DP/outstanding/reservation/staged fulfillment berada di luar V1. Nama pelanggan pada receipt opsional; tidak ada customer wajib atau CRM. Satu payment method per Sale: CASH atau BANK_TRANSFER. QRIS/card/e-wallet/gateway adalah future capability yang nonaktif; method code/label yang extensible tidak mengaktifkannya.

Setiap actor checkout, termasuk Pemilik dan transfer-only Sale, wajib mempunyai sesi OPEN miliknya yang eligible. Server memvalidasi active actor/role, source intent, register/session responsibility, product/version, authoritative selling price, qty/unit/stock, total dan payment. Kunci sesi OPEN sebelum product/stock writes; increment activityVersion bersama Sale. Kasir tidak melakukan price override/discount. Harga berubah memerlukan review ulang, bukan perubahan payable total diam-diam.

| Method | Fakta yang direkam |
| --- | --- |
| CASH | total/tender/change/applied; tender >= total, change = tender − total, applied = total |
| BANK_TRANSFER | Exact full total, optional reference dan konfirmasi cashier bahwa dana diterima; tidak mengubah physical cash atau mengklaim gateway verification |

Scan/Cari → Keranjang → qty → pilih pembayaran penuh → review pembayaran dan konfirmasi penyerahan langsung → confirm → Struk. Final confirmation membekukan source intent/key/payload. Satu transaksi membuat Sale/lines/full Payment/SALE_ISSUE/StockBalance/cost source dan allocation atau UNKNOWN/ReceiptSnapshot/audit/durable result. Gagal di bagian mana pun → tidak ada bagian transaksi yang commit. Source intent mencegah duplikasi walau retry memakai key berbeda; tidak ada keberhasilan payment-only atau stock-only.

Tindakan customer/bank berlangsung di luar transaksi database. Recording transfer memerlukan human confirmation; bila hasil recording tidak pasti, pulihkan command awal sebelum meminta customer membayar/transfer ulang.

### Penolakan, hasil tidak pasti dan recovery

Stok tidak cukup, stale price atau session CLOSED adalah definite rejection sebelum payment/stock facts. Review draft lagi dengan revision eksplisit atau sesi baru yang eligible sesuai sebab penolakan. Response hilang adalah UNCERTAIN: bekukan envelope awal dan lakukan status/retry identitas yang sama; jangan membuat replacement Sale atau menyimpulkan transaksi gagal dari timeout.

Recovery mengotorisasi ulang dan hasil awal yang sudah commit tetap dapat ditemukan sesudah sesinya CLOSED. Scanner Enter tidak mengonfirmasi pembayaran. Reload mempertahankan recovery reference minimal tanpa secret atau payload privat; ordinary stale draft memvalidasi ulang produk/harga. Printing dilakukan sesudah commit; printer failure tidak membatalkan Sale. Restore/recovery epoch mengikuti [09](09-DEPLOYMENT-OPS.md), tanpa blind replay intent lama.

## Receipt thermal dan A4

Setiap Sale mempunyai satu nomor dan ReceiptSnapshot immutable. Snapshot memuat business name/logo/address/contact/header/footer/document identity yang dibekukan dari BusinessProfile runtime, identity/template/asset revision, waktu UTC yang dirender di Jakarta, cashier dan nomor sesi, item/SKU/unit/qty/unit price/line total/total, method serta tender/change CASH. BantuKasir hanya codename; perubahan nama/logo klien runtime tidak mengubah identitas dokumen lama.

Pertahankan asset/template yang direferensikan agar dokumen dapat direproduksi. Reprint menggunakan nomor/content yang sama dengan copy marker, tanpa Sale/Payment/movement baru. Receipt/history/reprint/print/PDF/media request diotorisasi setiap kali: Kasir hanya transaksi sendiri, Pemilik seluruhnya. Document DTO tidak memuat acquisition cost/HPP/profit.

Thermal dan A4 Nota Penjualan / Commercial Invoice memakai snapshot yang sama melalui browser print/PDF, tanpa entry ulang. Ukuran thermal nyata 58/80 mm harus dikonfirmasi bersama device/media. Commercial nota tidak mengklaim legal equivalence dengan faktur pajak; G1 mengatur setiap perubahan tax/buyer-identity sebelum pilot. Print request atau penutupan print dialog bukan bukti tinta. Print failure/reprint tidak menggandakan fakta bisnis.

## Refund/return minimal oleh Pemilik

Alur: pilih Sale original → line/qty/amount yang eligible → reason wajib/recent authentication → review payout yang benar-benar dilakukan dan kondisi barang fisik → confirm. Kunci Sale original serta produk terkait menurut urutan [02](02-ARCHITECTURE.md). Total refunded amount kumulatif tidak boleh melebihi paid value original; line refund tidak boleh melebihi line value; return qty tidak boleh melebihi issued qty dikurangi prior returns. Partial line-value refund hanya dalam remaining monetary bound. Tidak ada exchange/credit-account engine.

Dalam satu transaksi, append executed Refund evidence (CASH/BANK_TRANSFER, owner funds/reference), optional saleable RETURN_RECEIPT, historical cost recovery, audit dan durable result. Qty dan money dibatasi terpisah di bawah lock agar retry/request berlomba tidak melakukan refund/return dua kali. Barang dapat dikembalikan tanpa tambahan money bila monetary refund original sudah dilakukan. Refund tanpa physical return tidak menambah stock; damaged/non-saleable return tidak menambah saleable stock. Sale/Payment/receipt original tetap immutable; dokumen koreksi terhubung ke original.

Payout Pemilik memakai dana terpisah yang dikendalikan Pemilik, bukan drawer Kasir OPEN. Actor/waktu/method payout tampil terpisah dalam laporan Pemilik. Jika praktik nyata memerlukan drawer-funded refund, paid-in/out, cash pickup atau shared active cash responsibility, hentikan alur terkait pada G3 dan replan; jangan mengubah expectedCash secara diam-diam. Matched historical cost/UNKNOWN dan dampak HPP mengikuti [15](15-FINANCE-PROFITABILITY.md).

## Laporan Sesi Kas dan expected cash

Expected physical cash hanya berasal dari fakta drawer yang dimodelkan:

`expectedCash = openingCash + applied CASH payments dalam sesi`

`variance = physicalCash - expectedCash`

BANK_TRANSFER tidak pernah menambah expected physical cash. Owner-funded refunds terpisah. Tidak ada paid-in/out, cash pickup, drawer-funded refund atau hidden balancing adjustment dalam V1. Opening cash wajib sebagai count nonnegative yang eksplisit; masukkan Rp0 bila bisnis memang mulai kosong. G3 harus mengonfirmasi drawer/label, opening-float practice, satu responsible cashier tanpa simultaneous sharing dan funding refund Pemilik sebelum penerimaan alur sesi kas/pilot.

Contoh: opening Rp20.000 + applied CASH Rp100.000 + BANK_TRANSFER Rp50.000 dengan physical Rp118.000 menghasilkan expected Rp120.000 dan variance −Rp2.000.

### Close yang aman saat request berlomba

1. Kasir meminta preview close untuk sesi OPEN sendiri. Server mengembalikan identitas sesi, activityVersion sekarang dan operational totals yang diizinkan. Tidak ada lock database selama cash dihitung; blind counting bukan requirement V1.
2. User menghentikan checkout sementara, menghitung physical drawer, mengisi physicalCash dan mengirim session ID + activityVersion + command identity yang dibekukan. Scan capture pause; scanner Enter tidak menutup sesi.
3. Close mengunci sesi yang sama dengan checkout. Jika sudah CLOSED, equivalent retry termasuk key berbeda mengembalikan hasil awal; payload konflik tidak menimpa hasil. Jika activityVersion berubah, tolak dengan **Ada transaksi baru; hitung ulang kas** dan pertahankan OPEN.
4. Dengan version yang cocok, turunkan source Sale/Payment IDs, totals dan expectedCash; commit physical count, variance, cutoff/closedAt, CashSessionReportSnapshot immutable, CLOSED, audit dan durable result secara atomik. Kegagalan snapshot/audit/result meninggalkan sesi OPEN.
5. Sale yang memperoleh lock lebih dahulu commit sekali dan menambah version sehingga close memerlukan recount. Close yang lebih dahulu commit CLOSED membuat Sale berikutnya ditolak sebelum payment/stock facts. Sale yang sukses tidak boleh hilang dari laporan sesi.
6. Selesaikan UNCERTAIN checkout melalui identitas awal sebelum close. Original result yang telah commit tetap recoverable sesudah CLOSED. Owner-assisted close memakai transaksi yang sama dengan current permission, physical count, recent authentication dan reason wajib; responsible cashier original tidak berubah.

Tidak ada state persisten CLOSING, reopen atau responsibility-edit. Count mistake mendapat linked owner review note, bukan penimpaan physical/variance facts. Periode kerja berikutnya memakai sesi baru walaupun businessDate sama. Partial unique constraints mencegah sesi responsible yang bertumpuk per register/per cashier; handover menutup A sebelum membuka B.

### Snapshot immutable dan export

Snapshot memuat report/session number, business identity/template/asset yang dibekukan, register/cashier identities, openedAt/cutoff/closedAt, openingCash, source IDs/activityVersion, completed transaction count/value, applied cash/transfer totals, expectedCash, physicalCash, variance, close actor/reason dan safe notes. Snapshot adalah dokumen accountability saat close, bukan sumber Sale/payment kedua.

Kasir hanya membaca/print/export sesi sendiri; Pemilik seluruh sesi; Admin Operasional tidak mendapat report access. Otorisasi ulang listing/detail/print/PDF/CSV/reprint/file URL. Allowlist tidak memuat acquisition cost/HPP/evidence/margin/profit atau security data. CSV mengutip fields, mempertahankan IDR/timezone/session context dan menetralkan formula/control prefixes pada text tanpa mengubah negative numeric variance menjadi formula.

Browser print/PDF/CSV/export failure tidak membatalkan CLOSED. Retry merender snapshot yang sama; export/reprint tidak membuat atau mengubah Sale/Payment/StockMovement/session. Request audit direkam terpisah. Browser print-dialog close bukan bukti tinta.

## Rekap Harian Pemilik

Rekap harian memakai batas Asia/Jakarta half-open. Sales/payment/qty/HPP/Laba Kotor dihitung sekali dari source Sale/payment/cost facts berdasarkan businessDate setiap Sale. Jangan menjumlahkan sales/payment session snapshot di atas sumber yang sama. Opening cash dan variance bukan revenue/profit.

Tampilkan sesi yang relevan dengan cashier/register/waktu buka-tutup/status/per-session variance. Sesi lintas midnight tetap satu sesi, ditampilkan pada close date atau active section sampai ditutup, dengan seluruh rentang waktunya terlihat. Returning same-day dan handover menghasilkan beberapa report yang berbeda tanpa double counting.

Refund/koreksi kemudian tidak menulis ulang closed session snapshot. Financial totals mengikuti server posting date/cost evidence revision menurut [15](15-FINANCE-PROFITABILITY.md), sementara report as-closed tetap dapat direproduksi dan terhubung ke context koreksi berikutnya. Error/stale/UNKNOWN/denied dan recovery pada seluruh surface terkait harus mempunyai bukti awal dan bukti final sesuai [08](08-TESTING-ACCEPTANCE.md).
