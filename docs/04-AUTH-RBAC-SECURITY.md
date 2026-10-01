# 04 — Autentikasi, RBAC, dan privasi

**Plan 2.0.** Dokumen ini menetapkan kontrak keamanan, bukan bukti bahwa autentikasi atau backend sudah tersedia. [11 — Rencana dan task](11-BUILD-PLAN.md) menetapkan pemilik implementasi/verifikasi; [08 — Penerimaan](08-TESTING-ACCEPTANCE.md) menetapkan bukti kelulusan.

## Otorisasi server dan tiga role tetap

Setiap request memerlukan pemeriksaan autentikasi, permission backend, dan cakupan objek. Berlaku untuk mutation, pencarian, riwayat, status command/recovery, cetak ulang, PDF, CSV, dan media. Menyembunyikan menu atau memakai route demo tidak membuktikan keamanan. Tidak ada pembuat role dinamis.

| Kemampuan | SUPER_ADMIN / Pemilik | OPERATIONS_ADMIN / Admin Operasional | CASHIER / Kasir |
| --- | --- | --- | --- |
| Cari/scan melalui DTO produk operasional | Ya | Ya | Ya |
| Produk/kategori/master harga jual/barcode/label | Ya | Ya; harga nonnegatif, perubahan diaudit | Tidak |
| Receiving/manual issue/stok/restock/riwayat operasional | Ya | Ya | Tidak |
| Opening/adjustment/reversal/koreksi sensitif | Ya | Tidak | Tidak |
| POS tunai/transfer; buka/tutup/riwayat/laporan sesi sendiri | Ya | Tidak | Ya |
| Biaya perolehan/HPP/Laba Kotor | Ya | Tidak | Tidak |
| Refund/return dan review laporan semua kasir | Ya | Tidak | Tidak |
| Pengguna/config/security/audit/recovery | Ya | Tidak | Tidak |

Cakupan kasir berasal dari actor yang terautentikasi, bukan `cashierId`, role, session ID, atau register ID yang dipercaya dari client. Pemilik yang melakukan checkout memakai CashSession aktif miliknya sendiri dan tidak menyamar sebagai kasir lain. Kasir hanya membuka laci yang diizinkan dan tersedia sebagai dirinya sendiri, menutup sesinya sendiri, serta membaca Sale dan laporan sesi miliknya sendiri. Operations tidak mendapat permission checkout atau CashSession.

Pemilik mengelola logical register, membaca seluruh laporan, dan dapat melakukan assisted close dengan hitungan fisik, autentikasi terbaru, serta alasan wajib. Tindakan ini mempertahankan kasir penanggung jawab awal dan mencatat actor penutup. Kontrak race, cutoff, dan snapshot mengikuti [17 — POS dan sesi kas](17-POS-SALES.md).

## Batas input biaya Operations

Receiving wajib mempunyai bukti biaya atau status `UNKNOWN`. Operations boleh mengirim **bukti biaya baru dari dokumen barang masuk melalui input write-only**. Permission ini tidak memberi akses baca biaya historis, riwayat evidence, valuation, HPP, margin, atau profit. Operations dapat memilih “Biaya perlu dilengkapi pemilik”; pemilik melengkapi evidence melalui koreksi yang diaudit.

Nilai sensitif yang dikirim tidak boleh kembali melalui response, review hasil, pencarian, riwayat, status/recovery command, ekspor, cache bersama, error, atau log. Bersihkan field biaya setelah commit terkonfirmasi. Jangan menyimpan payload biaya di localStorage, IndexedDB, browser recovery storage, telemetry, atau log. Setelah reload, recovery memakai referensi command server dan DTO yang diizinkan, bukan replay payload biaya yang disimpan di browser. Draft yang hasilnya belum pasti mengikuti recovery intent asli; jangan membuat command pengganti. [06 — Inventori](06-INVENTORY-SPEC.md) dan [15 — Biaya dan profit](15-FINANCE-PROFITABILITY.md) mengatur posting serta kelengkapan biaya.

## Autentikasi dan penjagaan mutation

Gunakan autentikasi self-hosted dengan Better Auth sebagai arah awal; kompatibilitas versi, advisory keamanan, adapter, dan cookie/session behavior diverifikasi serta dipin saat task foundation. Tidak ada kriptografi password/token buatan sendiri.

- Akun individual; hashing password melalui library yang tervalidasi; cookie `HttpOnly`, `Secure`, dan `SameSite` yang sesuai; server session, trusted origin/CSRF protection, serta rate limit login/reset.
- Tidak ada public signup. Bootstrap pemilik adalah prosedur operator privat sekali pakai dengan target lingkungan yang diverifikasi; tidak ada password bawaan atau demo user di production.
- Invite/reset menggunakan link single-use dengan expiry dan penyampaian aman; tidak memerlukan layanan email berbayar. Token tidak masuk log, screenshot, atau repository.
- Disable akun, reset password, dan revoke membatalkan session serta hak menulis yang relevan. Perubahan role/izin tidak boleh meninggalkan izin lama pada cache atau session yang dipakai untuk mutation.
- Refund, pengelolaan pengguna, recovery, konfigurasi sensitif, dan assisted close oleh pemilik memerlukan autentikasi terbaru. Batas waktu dan mekanisme diputuskan serta diuji dalam task autentikasi, bukan diasumsikan dari UI.
- TOTP/2FA bukan scope V1. Boundary adapter autentikasi memungkinkan penambahan kemudian.

Mutation kritis memeriksa actor aktif, permission, dan konfigurasi yang relevan kembali dalam transaksi di bawah guard yang sama dengan writer disable/config. Urutan lock mengikuti [02 — Arsitektur](02-ARCHITECTURE.md). Request yang lolos middleware sebelum akun dinonaktifkan tidak otomatis boleh commit setelah disable. Writer konfigurasi tidak boleh mengubah prasyarat command yang sedang commit tanpa guard bersama. Jangan melakukan human/network wait dalam transaksi. Recovery hasil asli tetap memeriksa otorisasi saat ini dan cakupan objek sebelum mengembalikan DTO.

## DTO, cache, riwayat, dan ekspor

Susun DTO melalui allowlist terpisah untuk cakupan operasional, dokumen kasir, dan finance pemilik. Jangan mengambil entity lengkap lalu hanya menyembunyikan field di UI. Staff tidak menerima biaya pembelian/evidence history, HPP, margin/profit, audit/security tak terbatas, maupun detail recovery. Payload HTML/server-rendered, API, hasil command, state client, cache, error, dan unduhan harus mengikuti batas yang sama.

Tidak ada URL laporan sensitif publik atau cache response sensitif bersama. Request dokumen/media dan unduhan memeriksa ulang actor serta objek; mengetahui ID, nomor struk, atau URL bukan permission. Scope own-Sale/own-session berlaku juga pada cetak ulang, status, PDF, dan CSV. Pemilik mempunyai akses lebih luas melalui permission eksplisit. CSV memakai allowlist kolom dan penanganan formula berbahaya sesuai kontrak penerimaan; snapshot tidak diubah akibat kegagalan ekspor.

## Audit dan identitas historis

Perubahan bisnis penting yang berhasil diaudit dalam transaksi yang sama: Sale/payment/stock, product/price, config/user, correction, serta buka/tutup sesi. Audit wajib ikut rollback bila command gagal. Log/Riwayat pemilik mendukung filter tanggal/user/type/product/session/reference. Operations membaca riwayat stok yang aman; kasir hanya riwayat Sale/laporan sesi sendiri. Pertahankan identitas actor historis dan fakta asli meskipun akun dinonaktifkan atau profil berubah.

Log memakai ID dan error aman. Jangan merekam password, invite/reset token, auth secret, session cookie, credential, atau payload penuh yang berisi biaya pribadi. Akses audit/security/recovery tetap owner-only; mekanisme pencatatan kegagalan keamanan tidak boleh menjadi jalur kebocoran.

## Konfigurasi, identitas client, dan media privat

“BantuKasir” hanya codename internal. Identitas aplikasi produksi berasal dari runtime BusinessProfile: nama bisnis, logo resmi client, alamat, kontak, dan identitas struk/dokumen. Repository logo bukan identitas client produksi. Snapshot dokumen mempertahankan revision BusinessProfile/logo saat transaksi, termasuk setelah profil berubah.

Validasi environment saat startup tanpa menampilkan nilai secret. Credential aplikasi, database, backup, dan access key DesainPakeAI tetap di konfigurasi privat; key DesainPakeAI milik user tidak disalin ke repository, docs, log, screenshot, atau variabel `NEXT_PUBLIC`. Source design/revision mengikuti [DESIGN](../DESIGN.md), terpisah dari storage logo client produksi.

Upload logo hanya PNG/JPEG/WebP terverifikasi, maksimal 5 MiB dan 16 megapixel; validasi isi, re-encode, dan hapus metadata. Tolak executable/SVG/HTML dan arbitrary remote URL fetching. Gunakan storage privat dengan key acak immutable, revision yang tetap dapat direferensikan snapshot, download terotorisasi, serta manifest/checksum. Jangan menghapus aset yang masih direferensikan receipt/report. [09 — Operasi dan recovery](09-DEPLOYMENT-OPS.md) mengatur penyimpanan dan pemulihannya.

Kelulusan membutuhkan bukti backend RBAC dan object authorization, termasuk request langsung lintas actor/role, race disable/config, recovery, DTO/cache/log/media/ekspor tanpa kebocoran, dan recent-auth actions. Screenshot atau mock client tidak memberi FINAL PASS; ownership serta acceptance final berada di [11](11-BUILD-PLAN.md) dan [08](08-TESTING-ACCEPTANCE.md).
