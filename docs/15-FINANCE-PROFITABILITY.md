# 15 — HPP dan Laba Kotor

**Baseline P0–P11.** Laporan manajemen khusus Pemilik menggunakan perpetual moving weighted average (MWA) per produk yang saling dapat dipertukarkan dalam satu stock pool. HPP tidak memakai latest purchase price dan biaya unknown tidak dianggap nol. Laporan ini bukan akuntansi pajak atau Laba Bersih; permission/privacy mengikuti [04](04-AUTH-RBAC-SECURITY.md).

## Fakta sumber dan aritmetika

Acquisition evidence terhubung ke OPENING/RECEIPT/positive ADJUSTMENT: total landed acquisition amount, quantity, reference, actor dan status KNOWN atau UNKNOWN. Nilai nol eksplisit memerlukan evidence/reason yang dikonfirmasi Pemilik. Admin Operasional boleh mengirim evidence baru secara write-only; membaca riwayat atau mengoreksi cost tetap khusus Pemilik.

Untuk Q unit known dengan inventory value V, receipt q dengan total cost c menghasilkan:

`Q' = Q + q; V' = V + c; average = V'/Q'`

Issue q mengalokasikan `q × (V/Q)` menggunakan average sebelum issue dan mengurangi nilai tersebut. SALE_ISSUE maupun issue non-sale mengurangi inventory value; hanya alokasi SALE_ISSUE masuk sales HPP. Issue terakhir yang menghabiskan seluruh stock mengambil sisa V agar tidak meninggalkan rounding residue. Gunakan decimal cost arithmetic enam tempat, aggregate terlebih dahulu dan round tampilan laporan ke IDR pada akhir; tidak ada binary float.

Contoh: 10 unit berbiaya Rp100.000 + 10 unit berbiaya Rp140.000 → 20 unit/Rp240.000 → average Rp12.000. Jual 3 pada Rp20.000 → penjualan Rp60.000, HPP Rp36.000, Laba Kotor Rp24.000; sisa 17/Rp204.000. Manual issue 2 mengurangi inventory value Rp24.000 tanpa menambah sales HPP.

Seluruh source/cost allocations dicatat dalam transaksi inventory/Sale dengan posting sequence per produk. Receipt dengan cost yang hilang membuat valuation UNKNOWN dan HPP berikutnya yang terpengaruh incomplete. Alokasi issue historis yang sebelumnya KNOWN tetap valid. Bila stock mencapai nol, receipt baru yang seluruh cost-nya KNOWN dapat memulai pool KNOWN baru; periode lama dengan evidence hilang tetap incomplete.

## Revision evidence tanpa menulis ulang sejarah

Pemilik menambahkan evidence revision dengan reason. Di bawah product guard, deterministic replay yang dibatasi dari source terpengaruh menghitung valuation revision baru serta downstream allocation versions. Source dan alokasi original tetap dipertahankan. Publish revision/current projection baru secara atomik hanya setelah seluruh replay dan rekonsiliasi selesai. Posting bersamaan menunggu atau ditolak aman ketika produk busy.

Ukur kapasitas synchronous replay pada lingkungan target; reference awal 10.000 movements per produk, bukan hasil pengukuran atau jaminan. Bila timeout/cap akan terlampaui, hentikan koreksi dan tandai reporting terkait incomplete sampai tersedia prosedur recovery yang direview. Jangan mempublikasikan sebagian atau menebak cost. V1 tidak mencakup platform valuation asynchronous umum.

Standalone reversal me-replay logical cancellation atas source yang eligible. Reversal immutable mempertahankan physical inverse yang tepat, linked evidence dan dependent cost revisions. Tolak sampai replay dapat dilakukan aman; jangan memakai average terbaru secara naif untuk membatalkan receipt lama. Aturan stok/reversal mengikuti [06](06-INVENTORY-SPEC.md).

## Sale, refund dan return

Pembayaran penuh dan penyerahan GOODS langsung yang dikonfirmasi membentuk completed sales value serta matched SALE_ISSUE HPP bersama dalam transaksi [17](17-POS-SALES.md). Cash tender bukan sales value; applied payment adalah jumlah yang diterapkan pada Sale.

Refund tanpa return mengurangi sales value dengan linked refund amount, tidak membalik HPP atau menambah stock. Return fisik saleable yang diverifikasi memulihkan original matched issue cost dan membalik sales HPP untuk quantity tersebut; original cost UNKNOWN membuat return valuation tetap incomplete. Refund money dan return qty dibatasi terpisah agar cost tidak dipulihkan dua kali. Damaged/non-saleable return tidak menambah usable stock.

Laporan menampilkan penjualan bruto dan executed refund secara terpisah:

- Penjualan neto = completed sales bruto − refund.
- HPP neto = alokasi Sale − cost dari verified saleable returns.
- Laba Kotor = penjualan neto − HPP neto.

Sale/Payment/receipt original tetap immutable. Refund/return tampil pada server posting date dengan reference original; tidak ada hidden backdating. Kerugian non-sale dan variance kas adalah fakta operasional terpisah. Hasil ini tetap laba kotor penjualan, bukan laba bersih.

## Periode, kelengkapan dan read snapshot

Server memakai batas Asia/Jakarta yang dikonversi ke rentang UTC half-open:

| Periode | Batas |
| --- | --- |
| Hari Ini | Tanggal lokal hari ini |
| 7 Hari | Hari ini dan enam tanggal sebelumnya |
| Bulan Ini | Awal bulan berjalan sampai sekarang |
| Bulan Lalu | Seluruh bulan kalender sebelumnya |
| Custom Range | Tanggal pilihan inklusif, diimplementasikan dengan exclusive next-day end |

Metrik mencakup penjualan bruto/refund/neto, jumlah transaksi COMPLETED, applied cash/transfer dan payout terpisah, qty sold/returned, HPP/Laba Kotor. Margin opsional hanya tersedia bila cost complete dan penjualan neto >0. LOW/OUT adalah stok terkini, bukan metrik yang mengikuti filter periode.

Read snapshot laporan konsisten memuat asOf/timezone/evidence revision/completeness serta unknown line count. Revenue/payment totals boleh tampil ketika HPP/Laba Kotor/margin menampilkan **Data biaya belum lengkap**. Jangan mengecualikan unknown-cost atau line merugi untuk membuat profit terlihat lengkap. Query gagal adalah error, bukan nol. Valuation laporan dapat berubah hanya melalui evidence revisions yang dinyatakan; ReceiptSnapshot dan CashSessionReportSnapshot tidak berubah.

Rekap harian Pemilik menghitung financial totals sekali dari source facts dan menampilkan session variance terpisah menurut [17](17-POS-SALES.md). Opening cash atau variance bukan penjualan/profit; jangan menambahkan total sesi di atas Sale yang sama. Session cash report tidak memuat private cost/profit. AR/AP, Laba Bersih, expenses/payroll, service finance dan tax accounting tetap di luar Core. Acceptance numeric/UNKNOWN/replay/period/privacy mengikuti [08](08-TESTING-ACCEPTANCE.md).


## Constraint replay dependent facts

Sebelum publication, replay memvalidasi seluruh affected chronology, signed physical prefix, known/UNKNOWN pool boundaries dan matched issue/return allocation revision references. Return mengacu pada cost revision source yang konsisten dan tidak memulihkan cost dua kali. Unsafe chronology, inconsistent links atau cap/timeout menolak publication seluruh revision; retained originals/current published revision tetap valid dan affected reporting dinyatakan incomplete.


## Pasangan reversal dihitung sekali

Physical ledger selalu mempertahankan original + inverse REVERSAL. Valuation revision replay membatalkan source original secara logical dan melewati inverse pasangannya; jangan menghapus original lalu menerapkan inverse sekali lagi. Semua pasangan/source/return allocation links diperiksa sebelum satu atomic publication.

Fixture analytical untuk test INV-003: OPEN10 dengan totalcost100 + RECEIPT10 totalcost200 + ISSUE5 menghasilkan physical15/value225/issuecost75. Full reverse RECEIPT10 yang eligible menghasilkan physical5/value50/revised issuecost50; original allocation75 tetap tersimpan. Invalid prefix: RECEIPT10→ISSUE10→RECEIPT20/current20; physical inverse10 tampak current10 nonnegative, tetapi logical cancellation membuat historical issue tanpa stock. Tolak seluruh reversal/replay sebelum commit, tanpa movement/publication parsial. Ini test specification, bukan test aplikasi yang sudah dijalankan.
