# 05 — Sistem desain dan persetujuan visual

Dokumen ini memiliki seluruh aturan UI, bahasa, prototipe, dan bukti review. Belum ada prototipe atau desain yang disetujui. Arahan visual di sini adalah batas perancangan, bukan kontrak visual final.

## Karakter dan struktur informasi

Formal, tenang, matang, profesional untuk operasi B2B/B2G. Utamakan tabel, tipografi, ruang, hierarki, dan status yang jelas. Jangan mengandalkan kartu/pill berlebihan, kaca/gradient, sudut sangat bulat, atau animasi dekoratif. Dasbor adalah alat keputusan; layar gudang adalah alat kerja.

Hipotesis token untuk prototipe: latar netral hangat/putih, teks arang, aksen biru tua, hijau untuk berhasil, amber untuk perhatian, merah untuk habis/galat. Font sans-serif mudah dibaca dengan angka tabular pada jumlah; keluarga final dipilih melalui review dan lisensi self-host yang sesuai. Body dasar 16 px, skala jarak 4/8/12/16/24/32 px, radius kecil 4–8 px. Token final baru dikunci pada HTML yang disetujui; React mengikuti token tersebut.

Navigasi internal awal menurut izin: Dasbor, Produk, Inventaris, Barang Masuk, Barang Keluar, Transfer Stok, Riwayat Stok, Notifikasi; Pengaturan/Audit/Sistem untuk owner. Serial ditelusuri dari produk/riwayat, bukan menu kompleks tanpa kebutuhan. Aksi harian scan tidak tersembunyi dalam submenu. Situs publik kelak memakai pola sendiri yang tetap konsisten brand.

## Bahasa dan format

**Semua teks yang dilihat pengguna wajib Bahasa Indonesia**, termasuk login/reset/2FA, tooltip, pagination, validation, toast, audit, grafik, print label, file laporan, title/metadata halaman, loading, empty/error, offline dan 404/500. Pesan bawaan library/Zod tidak boleh lolos sebagai English mentah.

| Identifier / istilah | Label pengguna |
| --- | --- |
| Dashboard / Inventory / Warehouse | Dasbor / Inventaris / Gudang |
| RECEIPT / ISSUE / TRANSFER | Barang Masuk / Barang Keluar / Transfer Stok |
| ADJUSTMENT / REVERSAL / OPENING | Penyesuaian Stok / Pembalikan / Saldo Awal |
| NORMAL / LOW / OUT | Normal / Menipis / Habis |
| Low-stock notification / Out-of-stock notification | Stok Menipis / Stok Habis |
| REGISTERED / IN_STOCK / ISSUED | Terdaftar / Tersedia di Gudang / Sudah Keluar |
| DRAFT / PUBLISHED / ARCHIVED | Draf / Terbit / Diarsipkan |
| SUPER_ADMIN / INVENTORY_ADMIN / PRODUCT_SALES_ADMIN | Pemilik / Admin Inventaris / Admin Produk dan Penjualan |
| Login / Logout / Save / Retry | Masuk / Keluar / Simpan / Coba Lagi |

Pemetaan menjadi satu modul presentasi, bukan literal enum tersebar. Nama brand/model, SKU, RFQ, QC, barcode, USB, Bluetooth, Wi-Fi, PLC, dan serial produsen boleh tetap aslinya. Jangan menerjemahkan identitas scan. Locale `id-ID`; waktu tampil WIB, tanggal tidak ambigu, satuan selalu ditulis. Contoh `19 Sep 2026, 09.15 WIB`, `12,500 m` hanya bila precision tiga memang relevan; bedakan bilangan desimal dari ribuan dalam input.

## Komponen kanonis dan keadaan wajib

- App shell: navigasi berizin, breadcrumb ringkas, identitas pengguna, status koneksi. Mobile memakai navigasi yang tidak menghalangi scan.
- Tabel: header jelas, angka rata kanan, satuan, sorting/filter nyata, pagination, kolom tindakan stabil; tidak membungkus semua nilai sebagai pill. Sticky header hanya jika membantu. Mobile boleh detail berjenjang, tidak menyembunyikan qty/lokasi kritis.
- Form: label permanen, petunjuk, galat inline dan ringkasan; data dipertahankan setelah validation error. Wajib ditandai dengan teks, tidak warna saja.
- Status: ikon + label + warna. “Belum ada data”, “Tidak ada hasil untuk filter ini”, “Gagal memuat”, dan angka nol adalah keadaan berbeda.
- Konfirmasi posting: jenis, sumber/tujuan, jumlah baris/item, serial unik, reason/reference. Tombol “Simpan Barang Keluar”, bukan “OK”. Satu tombol utama; disable saat menunggu dengan teks yang jujur.
- Feedback scan: “Ditambahkan ke sesi, belum disimpan” dibedakan dari “Transaksi berhasil disimpan”. Beep dapat dimatikan dan tidak menjadi satu-satunya feedback.
- Notifikasi menampilkan status kini serta snapshot saat kejadian bila sudah berubah; tombol “Tandai Dibaca” tidak mengartikan stok sudah pulih.

Target aksesibilitas desain setara WCAG 2.2 AA untuk keyboard/kontras/form: fokus terlihat, label semantik, aria-live yang tidak membacakan daftar panjang tiap scan, kontras teks normal ≥4,5:1, kontrol sentuh utama ≥44 px, zoom 200% tetap bisa dipakai, hormati reduced motion. Status jangan hanya merah/hijau. Verifikasi browser 360 px, 768 px, 1280 px dan monitor kerja nyata. Keyboard tidak boleh terjebak dalam modal atau otomatis memposting dengan Enter scanner.

## Struktur prototipe

```text
prototypes/README.md                  indeks status dan tautan review
prototypes/<pattern-id>/
  index.html                         dapat dibuka mandiri / server statis lokal
  styles.css                         token lokal, tanpa build aplikasi
  demo.js                            opsional, hanya state demo deterministik
  assets/                            aset lokal yang boleh dipublikasikan
  REVIEW.md                          status, revisi, cakupan state dan bukti
```

Tidak ada panggilan API, cookie produksi, analytics eksternal, credential, atau koneksi DB. Hindari dependensi CDN agar review dapat diulang. Dummy state berlabel “Data Demo”, jangan mengaku angka tersebut stok LATANSA.

State prototipe: `PROPOSED` (Usulan), `UNDER_REVIEW` (Menunggu Review), `APPROVED` (Disetujui), `SUPERSEDED` (Digantikan). Status ini milik **artefak desain**, berbeda dari status tugas build. `REVIEW.md` minimal berisi pattern ID, revision/commit atau content hash, halaman/state, viewport, hasil browser check, tanggal, keputusan, ringkasan feedback, dan rujukan persetujuan owner. Tidak boleh mengarang persetujuan. Perubahan bermakna setelah approval menghasilkan revisi baru UNDER_REVIEW; versi lama tetap dapat ditelusuri di Git.

## Alur wajib dan dua gerbang

1. Definisikan requirement dan kelas `[FE]`/`[FS]`, state normal/kosong/loading/error/offline/permission/konfirmasi yang relevan.
2. Buat HTML/CSS standalone; verifikasi di browser nyata dengan keyboard dan viewport sasaran.
3. Minta pemilik menilai revisi konkret. Tugas desain berstatus `[V] MENUNGGU REVIEW VISUAL OWNER` sampai persetujuan eksplisit. Revisi HTML sesuai feedback.
4. **Gerbang A:** owner menyetujui HTML. Simpan bukti pada REVIEW. Baru boleh mengimplementasikan pola signifikan itu dalam Next.js.
5. Hubungkan backend/data, lakukan tes dan browser check. Hasil produksi tidak boleh diam-diam menyimpang dari kontrak HTML.
6. **Gerbang B:** owner menilai implementasi aktual dengan seluruh state relevan. Sampai disetujui, tugas FE/FS tetap `[V]`; setelah disetujui dan teknis lengkap menjadi `[x]`.

HTML prototipe dapat menjadi tugas FE tersendiri yang `[x]` setelah Gerbang A; hal itu **tidak** membuat implementasi fiturnya selesai. Baris build memisahkan tugas desain dan integrasi supaya dua persetujuan tidak tertukar. BE tanpa dampak visual boleh selesai sesudah bukti teknis.

Perubahan minor yang hanya memperbaiki typo/spacing kecil atau memakai komponen approved dengan struktur, interaksi, dan state sama dapat melewati pembuatan HTML baru. Perubahan hierarki, navigasi, form, perilaku scan, layout responsif, workflow atau state signifikan tetap memerlukan prototipe/revisi. Sebut pattern ID yang digunakan; jika ragu, klasifikasikan signifikan. Review visual hasil FE tetap wajib.

## Daftar kontrak visual yang direncanakan

Core: autentikasi dan pemulihan akses; shell; master produk/lokasi; tabel saldo/detail serial; label cetak; sesi Barang Masuk/Keluar/Transfer; koreksi owner/riwayat; notifikasi; dasbor; health/backup. Lanjutan: reservasi/opname/approval; QC/garansi/servis; landing/katalog/detail/perbandingan; RFQ/sales; laporan. Urutan dan status hanya dipelihara dalam [11](11-BUILD-PLAN.md), bukan diklaim sudah tersedia di sini.
