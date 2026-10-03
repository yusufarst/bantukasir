# 12 — Product Search, barcode dan scanner

**Baseline P0–P11.** Spesifikasi ini berlaku pada seluruh workflow yang memakai pencarian/scan; tidak mengklaim implementasi atau pengujian hardware sudah selesai.

## Satu Product Search yang dipakai bersama

Service canonical dipakai oleh POS, receiving, manual issue, product management, stock lookup dan barcode/label. Lakukan exact barcode index lookup lebih dahulu; dukung SKU exact/prefix, nama case-insensitive, kategori dan optional brand filter. Gunakan PostgreSQL B-tree unique indexes untuk barcode/SKU serta native name index yang diukur, misalnya pg_trgm bila tersedia dan dapat dibenarkan; tanpa search eksternal.

Pagination server dibatasi: default 25, maksimum 100, urutan nama/ID stabil, context filters dan pembatalan request stale. Jangan mengambil seluruh master ke browser. Authorization membentuk operational DTO allowlist berisi identitas/nama/unit/selling price/permitted stock, tanpa cost. Produk active dapat dipilih untuk posting; hasil historis/nonaktif ditandai read-only.

Sebelum membuat produk, cari exact barcode/SKU dan tampilkan similar-name candidates dari service yang sama. Setelah review, wajib ada konfirmasi **Tetap buat produk baru**. Fuzzy match adalah warning; exact barcode/SKU collision adalah hard rejection database, termasuk saat create berlomba. Receiving melanjutkan dengan produk existing/new yang dipilih. Product creation tidak menerima barang atau menciptakan stock. Permission mengikuti [04](04-AUTH-RBAC-SECURITY.md).

## Identitas token

Alias manufacturer dan kode internal berbagi token yang unik global. Perlakukan token sebagai string: pertahankan leading zeros, case dan punctuation; hapus hanya terminator HID/outer ASCII spaces, tolak internal controls/newlines, panjang 1–128 printable ASCII. Validasi check digit untuk declared EAN/UPC. Alias tidak menyembunyikan pack conversion.

Server menghasilkan token internal Code 128 dengan `BK-P-` ditambah 16 karakter uppercase Crockford Base32, disertai uniqueness retry. Namespace `BK-` reserved sehingga alias manufacturer tidak boleh mengklaimnya. Namespace teknis ini bukan identitas bisnis runtime. Jangan memakai ulang retired code atau mengganti kode saat branding berubah. Kode legacy yang benar-benar terbukti melalui G2 mempertahankan identitasnya; repository warisan bukan bukti adanya registry barcode live. Barcode tidak mengandung biaya atau executable command.

## Input scan dan draft

USB/Bluetooth HID adalah input keyboard; tidak ada proprietary driver/WebUSB integration. Konfigurasikan suffix Enter, dedicated scan field dan manual Enter fallback. Jangan memakai timing heuristic untuk menetapkan validitas barcode. Setiap kode lengkap yang diulang menambah satu base unit ke draft; CR/LF kosong dan key-repeat tidak menambah unit. Pertahankan urutan hasil scan meskipun lookup selesai dalam urutan berbeda. Fractional unit memakai quantity edit eksplisit. Tampilkan delta/undo; feedback scan tidak mengklaim stok telah disimpan.

Pause capture saat dialog/review atau penghitungan kas dan jangan mengambil focus dari input lain. Capture POS memerlukan actor terautentikasi dengan CashSession OPEN miliknya yang eligible. Scan tidak membuka, menyerahkan atau menutup sesi. Token unknown tetap tersedia untuk search; hanya actor berizin yang dapat membuat master/alias, tanpa automatic stock. Scanner Enter tidak boleh mengonfirmasi pembayaran/posting/session close. Lookup gagal mempertahankan token. Offline menghentikan posting; tidak ada offline mutation queue. Camera scanning berada di Later.

Pada final confirmation, bekukan source intent/key/payload. State request: SUBMITTING → COMMITTED yang terkonfirmasi atau UNCERTAIN. UNCERTAIN membekukan edit/penggantian dan hanya mengizinkan status/retry command awal. Simpan recovery reference minimal per-tab yang scoped ke actor, tanpa staff cost input, payload privat atau secret. Reload mengotorisasi ulang; stale draft biasa memvalidasi kembali product/price. Identitas uncertain tidak pernah kedaluwarsa menjadi Sale baru. [02](02-ARCHITECTURE.md) dan [17](17-POS-SALES.md) mengatur recovery dan atomic posting.

## Label dan bukti perangkat

Gunakan library Code 128 open-source yang lisensinya diverifikasi, SVG hitam di atas putih, quiet zones, ukuran nyata dalam mm serta code/name/SKU yang terbaca. Label tidak memuat purchase cost. Print melalui browser/OS; kandidat 50×30 mm memerlukan konfirmasi printer nyata. Print request bukan bukti tinta. Reprint memakai kode yang sama dan request-nya diaudit.

Acceptance [08](08-TESTING-ACCEPTANCE.md) mencakup manual/IME input, duplicate scan cepat, CR/LF, disconnect/partial input, lookup stale/terlambat, focus/virtual keyboard, USB dan Bluetooth HID, keterbacaan label hasil cetak serta receipt printer nyata. Simulasi browser tidak dapat memberikan credit bukti hardware; catat device/driver/media serta hasil aktual dan gap yang belum diuji.
