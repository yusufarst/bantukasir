# 02 — Arsitektur dan model domain

**Plan 2.0.** Dokumen ini adalah kontrak target, bukan bukti bahwa runtime, schema atau migration telah diimplementasikan. Arsitektur tetap modular monolith: satu aplikasi Next.js/TypeScript dan PostgreSQL, Drizzle untuk migration, Zod untuk validasi, Better Auth self-hosted, RBAC server dan laporan internal. Saat implementasi, verifikasi kompatibilitas versi serta advisory keamanan dan pin dependency/lockfile. Core tidak memerlukan worker umum, queue, search eksternal atau infrastruktur pengiriman notifikasi.

Modul: identitas/akses; konfigurasi; katalog/search/barcode; inventory/costing; Sale/payment/dokumen; sesi kas/rekonsiliasi; laporan/audit; operasi/recovery. Struktur yang diusulkan: `src/modules/<domain>`, `src/components`, `src/app`, `db`, `tests`, `ops`; ini bukan klaim bahwa source tersebut sudah ada.

## Model domain

Satu bisnis dan satu stock pool untuk GOODS yang saling dapat dipertukarkan. BantuKasir adalah codename proyek; identitas bisnis berasal dari BusinessProfile runtime, bukan nama/logo klien yang ditanam dalam source.

| Entity | Kontrak dan constraint |
| --- | --- |
| User / AuthSession | Tepat SUPER_ADMIN / Pemilik, OPERATIONS_ADMIN / Admin Operasional dan CASHIER / Kasir; akun individual; nonaktifkan actor yang direferensikan riwayat, jangan hapus identitas historisnya |
| BusinessProfileRevision | Revision immutable untuk nama/identitas, logo, alamat/kontak, header/footer dan accent; pointer revision aktif |
| Product / Category | SKU ternormalisasi unik, nama, kategori, brand opsional, base unit, precision, selling price, minimumStock, active dan version; base unit/precision terkunci setelah movement pertama agar arti ledger historis tetap |
| SellingPriceRevision | Amount, actor dan waktu; sumber harga SaleLine; tidak ada override harga oleh Kasir |
| Barcode | Token unik global untuk alias manufacturer/internal; satu produk; kode yang dinonaktifkan tidak dipakai ulang |
| StockMovement / Line | Signed quantity, produk, type, source, reason, actor, waktu server dan urutan posting per produk; immutable |
| StockBalance | Satu row per produk, onHand nonnegative dan version; projection sinkron |
| AcquisitionCostEvidence | Sumber receipt/opening/positive adjustment, amount diketahui atau UNKNOWN, reference dan revision khusus Pemilik |
| InventoryValuation / IssueCost | Projection MWA serta alokasi issue immutable/versioned atau UNKNOWN; terhubung dengan evidence revision |
| Sale / SaleLine | Nomor/source intent unik, actor/cashier, CashSession wajib, waktu UTC dan businessDate Jakarta; item/unit/qty/price/total immutable |
| Payment | Tepat satu pembayaran penuh per Sale COMPLETED; CASH atau BANK_TRANSFER; tender/change untuk CASH |
| ReceiptSnapshot | Unik per Sale; nomor asli, identity/template revision, lines/payment yang aman; tanpa biaya privat |
| Refund / Return | Terhubung ke Sale/lines asli, qty/amount dibatasi, actor/reason, metode/reference payout yang telah dilakukan dan optional return movement |
| CashRegister | Identitas minimal drawer/counter fisik: code/label unik dan active; bukan warehouse, daftar attendance atau register engine kompleks |
| CashSession | Nomor unik, register, responsible cashier, OPEN/CLOSED, waktu buka/tutup, openingCash nonnegative yang eksplisit, activityVersion dan close result; satu OPEN per register dan per cashier |
| CashSessionReportSnapshot | Unik per sesi CLOSED; identity/template, source IDs/version/cutoff, count/sales/cash/transfer/opening/expected/physical/variance serta close actor/reason immutable |
| CommandReceipt | Scoped key/fingerprint unik, source intent, durable result dan recovery epoch |
| AuditEvent | Append-only actor/waktu/action/source/command/reason/perubahan aman; rincian biaya khusus Pemilik |

Draft adalah envelope client yang dapat diedit, bukan kebenaran stok atau pembayaran. Checkout yang berhasil langsung membuat Sale COMPLETED: transisi konseptual DRAFT → COMPLETED. Total refund/return berasal dari fakta koreksi append-only; tidak ada state pembayaran sebagian/tertunda atau penimpaan fakta yang sudah selesai.

### Angka dan waktu

Base-unit quantity memakai precision 0–3 desimal yang dikonfirmasi melalui G2. Gunakan decimal arithmetic, bukan binary float. Selling price dan payable total adalah integer IDR rupiah: hitung `qty × unitPrice`, round half-up sekali per line, lalu jumlahkan lines. Cost mempertahankan enam tempat desimal dan penanganan residual menurut [15](15-FINANCE-PROFITABILITY.md). Validasi batas, precision dan overflow.

Waktu posting berasal dari server UTC; businessDate dihitung dalam Asia/Jakarta. Tanggal dokumen opsional disimpan terpisah dan tidak membolehkan backdating fakta fisik/finansial. Batas periode laporan memakai rentang UTC half-open dari tanggal lokal Jakarta.

### Constraint database

Wajib ada uniqueness SKU/barcode/source intent, satu Payment dan ReceiptSnapshot per Sale, uniqueness movement-source, CashSession wajib per Sale, satu report per sesi CLOSED, partial uniqueness sesi OPEN per register/per cashier, balance/openingCash/physicalCash nonnegative serta qty/amount yang valid. Pemeriksaan aggregate dengan lock membatasi total refund/return kumulatif. Constraint dan transaction guard harus bersama-sama mempertahankan kontrak, termasuk saat request berlomba.

Warehouse, StockLocation, Order, ServiceJob, Reservation, attendance Shift dan payroll scheduling tidak ada dalam V1. CashRegister/CashSession hanya untuk pertanggungjawaban drawer.

## Kontrak command dan idempotency

Command kritis wajib mempunyai actor terautentikasi dan berizin, envelope tervalidasi, source intent ID, idempotency key stabil, fingerprint server, transaksi database, audit dan durable result. Scope key mencakup actor + operation. Key/payload sama mengembalikan hasil awal; key sama/payload berubah menghasilkan konflik. Source intent unik mencegah Sale duplikat ketika retry memakai key berbeda. CommandReceipt dipertahankan bersama fakta bisnis; recovery memeriksa kembali permission/object scope.

Hasil tidak pasti memulihkan command awal, bukan membuat pengganti. Setelah restore, recovery epoch mencegah intent browser lama dieksekusi sebagai pekerjaan baru; hasil yang tidak ada di backup tidak membuktikan transaksi awal tidak pernah commit. [09](09-DEPLOYMENT-OPS.md) mengatur pemeriksaan interval data yang mungkin hilang.

### Urutan lock dan batas transaksi

Gunakan READ COMMITTED dan satu urutan lock bersama:

`command identity → actor/config guards (urutan stabil) → logical register → CashSession bila relevan → Sale asli untuk koreksi → product guards terurut ID → balance/cost projection → append facts/result`

Lewati guard yang tidak relevan, jangan membalik urutan. Penulis disable/revoke/config memakai guard yang sama sehingga perubahan permission/config tidak berlomba melewati validasi command. Pembukaan sesi mengunci register; checkout dan close mengunci sesi OPEN yang sama sebelum business writes. Retry deadlock dibatasi dan menggunakan identitas command awal. Tidak ada penantian manusia, printer atau network di dalam transaksi.

Command inventory menambahkan movement/cost facts, memperbarui projection balance/cost, audit dan result secara atomik. Completed Sale membuat Sale/lines/full Payment/SALE_ISSUE/balance/cost source serta alokasi atau UNKNOWN/ReceiptSnapshot/audit/result bersama. CASH dan BANK_TRANSFER teratribusi pada sesi; hanya CASH menambah expected drawer cash. Gagal di batas mana pun me-rollback seluruh fakta. Tidak ada endpoint pembayaran terpisah yang dapat meninggalkan Sale tidak lengkap. Transfer adalah bukti penerimaan yang dikonfirmasi manusia, bukan settlement bank atomik; pulihkan hasil tidak pasti sebelum meminta transfer ulang.

Close memeriksa activityVersion di bawah lock sesi, kemudian commit physical count, expectedCash, variance, cutoff, snapshot immutable, CLOSED, audit dan result bersama. Tidak diperlukan state persisten CLOSING. [06](06-INVENTORY-SPEC.md), [15](15-FINANCE-PROFITABILITY.md) dan [17](17-POS-SALES.md) mengatur rincian domain.

## Batas read dan projection

Satu Product Search memakai index PostgreSQL dan pagination server terbatas; matching logic terpisah dari context filter/role DTO. Jangan mengambil seluruh master ke browser. Payload/cache staff tidak memuat biaya privat. LOW/OUT berasal dari current balance/minimumStock, tanpa sumber kebenaran notifikasi tambahan. [12](12-BARCODE-SCANNER.md) mengatur identitas dan scan.

Setiap history/status/print/PDF/CSV/media request diotorisasi ulang. Laporan finansial Pemilik membaca snapshot database yang konsisten dan menampilkan cost completeness. Rekap harian menghitung periode dari source Sale/payment/cost facts satu kali, lalu menampilkan session snapshots/variance tanpa menjumlahkan sales/payment sesi untuk kedua kalinya. ReceiptSnapshot dan CashSessionReportSnapshot tetap immutable meskipun konfigurasi atau evidence cost berubah.

## Runtime, asset dan operasi

BusinessProfile runtime memiliki identity/contact/document configuration dan accent yang dibatasi menurut [DESIGN](../DESIGN.md). Revision logo memakai storage privat yang dikonfigurasi, key immutable dan reference dokumen yang dipertahankan; receipt/report membekukan identity/template/asset revision untuk reproduksi. Validasi upload dan privacy mengikuti [04](04-AUTH-RBAC-SECURITY.md).

Browser thermal/A4/PDF dan CSV yang aman mendukung dokumen tanpa layanan berbayar. Compose menargetkan aplikasi/PostgreSQL/Caddy serta command backup/rekonsiliasi terjadwal; PostgreSQL privat, HTTPS dan credential runtime/migration/backup terpisah. Tidak ada secret dalam client payload/source publik. Target biaya software tambahan mendekati Rp0 tidak menghapus kebutuhan backup independen dan biaya storage yang nyata. Semua maintenance, migration, release dan restore mengikuti [09](09-DEPLOYMENT-OPS.md). Acceptance teknis dan bukti per surface mengikuti [08](08-TESTING-ACCEPTANCE.md).
