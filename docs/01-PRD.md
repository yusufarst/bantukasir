# 01 — Kebutuhan produk

Dokumen ini memiliki tujuan bisnis, cakupan, prioritas, dan ukuran keberhasilan. Mekanisme teknis dimiliki dokumen sesuai [indeks](../README.md).

## Masalah dan hasil yang diinginkan

Pemilik CV. Latansa Jogjakarta perlu mengetahui barang yang masuk/keluar, sisa dan lokasi stok, pelaksana, serta barang yang perlu ditindaklanjuti. Staf menjalankan pencatatan harian; pemilik tidak bertugas memperbarui jumlah setiap hari.

Keberhasilan utama: **dua staf dapat bekerja bersamaan, memindai dan menyimpan transaksi secara andal, sementara pemilik dapat mempercayai saldo dan memperoleh perhatian stok tanpa spam.**

| Pengguna awal | Tanggung jawab | Hasil yang dibutuhkan |
| --- | --- | --- |
| 1 pemilik, `SUPER_ADMIN` | Pengawasan, kebijakan, koreksi sensitif, pengelolaan akun | Daftar tindakan dan jejak alasan yang jelas |
| Staf gudang, `INVENTORY_ADMIN` | Penerimaan, pengeluaran, pemindahan | Scan cepat, koreksi sebelum simpan, konfirmasi akurat |
| Admin produk/penjualan, `PRODUCT_SALES_ADMIN` | Deskripsi produk dan kelak katalog/penjualan | Data produk tanpa akses biaya atau mutasi stok |

Awalnya kedua staf diasumsikan operator gudang dan memperoleh `INVENTORY_ADMIN`. Bila salah satunya hanya mengelola produk, berikan peran produk. Peran dapat digabung secara eksplisit oleh pemilik; jumlah akun tidak memaksakan pembagian peran yang tidak sesuai pekerjaan.

## Core MVP — batas rilis pertama

Termasuk:

- Akun individual, autentikasi, otorisasi, pengelolaan akses minimal, pemulihan akses, keamanan sesi.
- Master produk dengan SKU, satuan dasar, cara pelacakan, kategori/brand minimal, ambang minimum; gudang dan lokasi jamak.
- Barang kuantitas **dan identitas berserial minimal sejak awal**: penerimaan, pengeluaran, lokasi, riwayat. QC, garansi, servis ditunda.
- Ledger, saldo transaksional, saldo awal, barang masuk/keluar, transfer langsung atomik, koreksi/pembalikan oleh pemilik dengan alasan.
- Barcode internal, alias barcode produsen yang tidak ambigu, label cetak browser, scanner USB/Bluetooth HID, input manual, sesi scan–review–konfirmasi.
- Stok Normal/Menipis/Habis, episode perhatian, kotak notifikasi pemilik dan Web Push dengan persetujuan perangkat, dasbor operasional nyata.
- Audit sejak transaksi pertama; penelusuran stok dan ekspor riwayat sederhana dengan izin.
- UI responsif berbahasa Indonesia, prototipe dan dua gerbang review visual, pengujian risiko, demo deterministik.
- Backup terenkripsi di luar host, restore teruji, health dasar dan rilis pilot aman sebelum stok operasional digunakan.

Tidak termasuk: website/katalog publik, checkout, akuntansi/valuasi/FIFO, purchase order kompleks, reservasi penjualan, transfer bertahap/transit, opname terpandu, mesin persetujuan fleksibel, QC, garansi, servis, kamera scanner, offline mutation, multi-perusahaan, multi-mata uang, SaaS berbayar, integrasi WhatsApp API, atau analitik konversi yang belum memiliki sumber data.

Transfer langsung dan koreksi minimal masuk MVP karena lokasi dan kesalahan pencatatan sudah nyata pada hari pertama. Transfer dengan perjalanan/penerimaan terpisah menunggu fase lanjutan. Persetujuan umum tidak menjadi prasyarat setiap transaksi normal.

## Kemampuan dan penerimaan bisnis

| ID | Kebutuhan | Bukti bisnis yang harus tersedia |
| --- | --- | --- |
| B01 | Catat masuk/keluar tanpa pemilik sebagai operator rutin | Staf berizin menyelesaikan sesi scan sendiri |
| B02 | Saldo dipercaya | Setiap perubahan dapat ditelusuri ke ledger, pelaksana, waktu, lokasi, alasan/referensi |
| B03 | Dua staf serentak aman | Perebutan stok terakhir tidak menghasilkan saldo negatif atau transaksi ganda |
| B04 | Perhatian stok proaktif | Ambang 5: 6→5 satu peringatan; 5→4 tidak duplikat; 3→20 pulih; 20→5 episode baru |
| B05 | Barang berserial tidak tertukar | Satu item tidak dapat keluar dua kali atau hadir di dua lokasi |
| B06 | Pemilik melihat tindakan yang relevan | Menipis/habis, pergerakan harian, aktivitas dan status sistem berasal dari query nyata |
| B07 | Koneksi gagal tidak memberi kepastian palsu | Sesi menunjukkan hasil belum pasti dan dapat dipulihkan tanpa menggandakan mutasi |
| B08 | Pekerjaan dapat dipulihkan dari insiden | Restore menghasilkan ledger/saldo/serial/episode yang konsisten |
| B09 | Aman untuk repositori publik | Tidak ada secret atau data operasional dalam commit; data privat tidak bocor ke proyeksi publik |

Skenario uji teknis dan target kinerja ada di [08](08-TESTING-ACCEPTANCE.md). Nilai sasaran adalah kriteria penerimaan, bukan klaim hasil pengukuran saat ini.

## Dasbor pemilik

Urutan informasi: tindakan stok → ringkasan operasi hari ini → transaksi terbaru → kesehatan operasional. KPI awal:

- Total SKU aktif: jumlah produk aktif, bukan jumlah unit.
- Stok Menipis/Habis: jumlah produk dipantau pada masing-masing status kanonis [13](13-NOTIFICATIONS.md); dua kelompok tidak tumpang tindih.
- Barang Masuk/Keluar Hari Ini: jumlah dokumen `RECEIPT`/`ISSUE` yang diposting pada hari bisnis WIB. Saldo awal, transfer, penyesuaian, dan pembalikan tidak dihitung sebagai penerimaan/pengeluaran normal. Dokumen yang dibalik tetap tampak dengan penanda dan tautan pembalikannya.
- Rincian pergerakan per produk/satuan boleh ditampilkan; jangan menjumlah meter dan unit menjadi satu angka kuantitas.
- Daftar perhatian: produk, stok tersedia, minimum, waktu perubahan, tindakan lihat lokasi/riwayat. Riwayat notifikasi dibaca terpisah dari kondisi stok yang belum pulih.

Dashboard memuat `diperbarui pada`, keadaan kosong, memuat, gagal, dan data kedaluwarsa. Nol hanya untuk query berhasil dengan hasil nol. Jangan merender metrik RFQ, omzet, QC, persetujuan, atau backup sebelum modul/sumber datanya tersedia.

## Pengembangan setelah MVP

Reservasi dan pemenuhan pesanan → transfer transit/opname/persetujuan → QC/garansi/servis → situs publik/katalog aman → RFQ/leads/penawaran → laporan lanjutan dan skala operasi. Urutan resmi dan dependensinya dimiliki [11](11-BUILD-PLAN.md).

## Asumsi bisnis yang dapat dikonfigurasi

Satu badan usaha, bahasa `id-ID`, hari bisnis `Asia/Jakarta`, stok negatif selalu dilarang, tanpa backdating ledger, satuan dasar per SKU, tanpa konversi kemasan pada MVP. Tidak ada kebutuhan pelacakan batch/kedaluwarsa yang telah dinyatakan. Konfirmasi saat review dokumentasi; kebutuhan batch wajib didesain sebelum stok terkait diimpor. Asumsi bernomor dan keputusan penggantinya ada di [10](10-DECISIONS.md).
