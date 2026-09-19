# 07 — Alur bisnis

Dokumen ini merangkai tanggung jawab pengguna. Aturan stok dimiliki [06](06-INVENTORY-SPEC.md), interaksi scan [12](12-BARCODE-SCANNER.md), izin [04](04-AUTH-RBAC-SECURITY.md), dan notifikasi [13](13-NOTIFICATIONS.md). Jangan membuat versi algoritme terpisah dari alur berikut.

## F01 — Persiapan operasional pertama

Operator deployment menyiapkan lingkungan aman dan owner melakukan aktivasi akun/2FA. Owner membuat akun staf dengan peran tepat, gudang/lokasi, SKU/satuan/tracking dan minimum. Admin produk dapat melengkapi konten. Owner/staf mencetak label dan menguji scanner dengan demo lebih dahulu.

Untuk cutover, owner menetapkan waktu berhenti pencatatan lama, staf menghitung barang/serial per lokasi, lalu owner memposting OPENING dengan batch dan referensi hitung. Cek total serta identitas, aktifkan monitoring SKU yang disimpan, uji notifikasi perangkat owner, backup dan restore lingkungan uji. Mulai operasi baru hanya setelah gate pilot [11](11-BUILD-PLAN.md) terpenuhi. Jangan menjalankan dua sumber saldo paralel tanpa aturan cutover.

Kesalahan master/label diperbaiki sebelum opening bila memungkinkan. Setelah ledger tersedia, perubahan tracking/satuan bukan edit biasa. Data demo tidak digabung dengan saldo produksi.

## F02 — Barang Masuk

1. Staf masuk, memilih Barang Masuk, lokasi penerimaan aktif, sumber/pengirim, alasan dan referensi bila ada.
2. Staf memindai produk kuantitas atau item serial. Item baru berserial diidentifikasi pada produk yang benar. Barang tak dikenal ditahan untuk identifikasi; tidak otomatis menambah stok.
3. Sesi menampilkan barang **belum disimpan**. Staf mengoreksi qty, duplikat, atau baris salah. Label baru dapat dicetak dari identitas REGISTERED; stok tetap belum berubah.
4. Staf membuka review, memeriksa fisik dan ringkasan, lalu mengonfirmasi sekali. Server memvalidasi dan memposting seluruh dokumen.
5. Nomor transaksi server membuktikan selesai. Tombol Transaksi Baru mempertahankan konteks yang dipilih secara jelas, mengosongkan barang dan membuat sesi baru.

Jika koneksi gagal setelah kirim, sesi masuk hasil belum pasti dan mengikuti pemulihan 12. Jika receipt merupakan pengembalian, pilih reason RETURN dan rujukan pengeluaran; verifikasi unit yang kembali. Pengembalian dari pelanggan dengan kerusakan memerlukan alur karantina setelah modul QC tersedia, bukan otomatis layak dikeluarkan.

## F03 — Barang Keluar

1. Staf memilih lokasi sumber, tujuan/penerima, alasan dan referensi pekerjaan/dokumen. Persetujuan owner tidak diperlukan untuk pengeluaran normal yang diizinkan.
2. Scan kuantitas/item serial, koreksi sesi, review identitas dan jumlah.
3. Server memeriksa available dan status serial terkini. Jika stok berubah karena operator lain, seluruh dokumen ditolak; staf melihat baris bermasalah dan mereview ulang, tidak menerima penyimpanan parsial.
4. Setelah commit, staf melihat nomor dokumen; low-stock evaluator secara atomik memperbarui perhatian dan inbox owner. Kegagalan push tidak membatalkan transaksi yang telah sah.

Barang fisik diserahkan mengikuti prosedur gudang setelah bukti posting. Bila kondisi lapangan mengharuskan urutan berbeda, owner menetapkan SOP dan referensi, bukan menghilangkan pencatatan. Saldo stok tidak otomatis turun saat RFQ/penawaran diterima kelak.

## F04 — Transfer lokasi langsung

Staf memilih asal dan tujuan berbeda, mencatat alasan, memindai serta mengonfirmasi. Kedua lokasi harus dapat ditangani dalam satu aktivitas operasional. Server memposting dua kaki atomik; owner melihat total tetap dan distribusi lokasi berubah. Tujuan gagal/serial salah berarti seluruh transfer batal.

Jika perpindahan membutuhkan perjalanan dan penerimaan terpisah, jangan memakai transfer langsung seolah barang sudah tiba. Aktifkan alur transit fase P07 dahulu atau batasi operasional MVP pada transfer langsung yang dapat dikonfirmasi saat itu.

## F05 — Kesalahan dan koreksi

Sebelum finalisasi, staf edit/urungkan draf. Sesudah posting, staf membuka nomor transaksi dan melaporkan alasan kepada owner; MVP belum memiliki kotak permintaan persetujuan formal. Owner memeriksa fisik, riwayat lanjutan, dan dampak; memilih pembalikan penuh untuk salah pencatatan yang memenuhi prasyarat, atau penyesuaian berdasarkan hitung nyata. Re-auth, alasan dan referensi diwajibkan.

Jika pembalikan tidak aman karena stok telah digunakan atau serial berpindah lagi, sistem menjelaskan penolakan. Owner menyelidiki dan membuat koreksi yang benar-benar menggambarkan kondisi fisik. Riwayat asli dan pembalikannya selalu dapat dibuka. Tidak ada tombol hapus transaksi atau edit angka saldo.

## F06 — Pemilik menangani stok

Owner memperoleh inbox dan push jika perangkat diaktifkan, lalu membuka daftar Menipis/Habis. Detail menunjukkan snapshot saat event, state saat ini, stok per lokasi, pergerakan dan pelaksana. Owner dapat menandai dibaca, menghubungi pengadaan melalui proses bisnis di luar MVP, atau melihat barang yang masih ada di lokasi lain.

Membaca tidak memulihkan stok. Receipt/release reservation atau perubahan kebijakan sah memicu evaluasi lagi. Ketika stok Normal, kondisi perhatian ditutup otomatis; episode baru baru dapat dibuka saat turun kembali. Jangan memaksa owner mengedit stok atau menutup alert satu per satu untuk menyinkronkan sistem.

## F07 — Pencabutan akun/perangkat

Owner menonaktifkan staf yang tidak lagi bertugas; backend mencabut sesi dan akses push, audit menyimpan identitas pelaksana lama. Sesi gudang yang belum diposting tidak dapat dikirim dengan izin lama. Transaksi yang sudah commit tetap ada. Browser bersama harus keluar dari akun sebelum dipakai orang lain; draf tidak ditransfer otomatis antaractor.

## F08 — Reservasi dan pengiriman penjualan (lanjutan)

Admin penjualan mencatat deal diterima → meminta alokasi produk/lokasi/unit → reservation service menahan available → gudang melihat referensi pemenuhan → staf memindai Barang Keluar terhadap reservasi → onHand dan reserved dikurangi bersamaan → status fulfillment diperbarui. Partial fulfillment menyisakan reserved yang belum dipenuhi; batal/kedaluwarsa melepas sisanya, tidak menambah fisik.

Sales tidak memilih stock delta atau membuat movement langsung. Reservasi gagal karena stok tidak cukup bukan deal yang otomatis terpenuhi. Backorder bukan stok negatif.

## F09 — Opname dan persetujuan (lanjutan)

Owner menetapkan scope dan waktu; lokasi dibekukan oleh sistem sebelum baseline. Staf menghitung fisik, memasukkan identitas serial dan qty, submit proposal; owner menilai discrepancy. Proposal disetujui tetap diperiksa versi/hash saat posting. Adjustment dan pembukaan freeze commit bersama; owner melihat before/after dan hasil akhir. Staf tidak dapat menyetujui proposal sendiri. Pembatalan tidak memposting selisih.

Persetujuan awal untuk semua koreksi yang diajukan staf; batas “besar” kelak harus berasal dari data/aturan owner, bukan angka nominal asumsi. Tindakan rutin masuk/keluar/transfer tetap tanpa approval. Rincian concurrency/freeze dimiliki 06.

## F10 — QC, garansi dan servis (lanjutan)

Petugas mencatat hasil QC item; gagal QC mengubah ketersediaan melalui perpindahan ke karantina yang diaudit. Hasil QC tidak otomatis menghapus stok. Garansi menghubungkan unit dengan periode/ketentuan. Service case membedakan barang milik perusahaan dari barang titipan pelanggan; hanya pergerakan stok milik perusahaan yang masuk ledger inventaris. Menyelesaikan servis tidak berarti otomatis menjual/menyerahkan barang.

## F11 — Website, WhatsApp, RFQ dan sales (lanjutan)

Pengunjung melihat produk terbit dari proyeksi aman, mencari/membandingkan, kemudian memilih WhatsApp atau mengirim RFQ. WhatsApp click membuka link dengan teks produk aman; klik hanya boleh dicatat sebagai klik, **bukan** bukti pesan terkirim, lead sah, atau penjualan. Tidak ada integrasi API berbayar otomatis.

RFQ menerima kontak dan kebutuhan dengan validasi/anti-spam → admin sales menilai → lead → penawaran berversi → deal → F08. Produk custom dapat berupa kebutuhan bebas tanpa membuat SKU persediaan palsu. Upload publik ditunda sampai kontrol file tersedia. Konfirmasi publik berbahasa Indonesia dan tidak mengungkap status/PII pemohon lain.

## F12 — Gangguan dan pemulihan

Staf melihat status koneksi, menghentikan posting baru bila tidak dapat memverifikasi hasil, dan mencatat referensi sesi untuk penelusuran. Operator teknis memeriksa health/outbox/backup; owner memutuskan penghentian operasi jika kepercayaan saldo terganggu. Restore mengikuti [09](09-DEPLOYMENT-OPS.md), dengan posting ditutup dan external delivery dijeda sampai rekonsiliasi selesai. Aplikasi tidak mengarang sukses untuk mempertahankan kesan lancar.
