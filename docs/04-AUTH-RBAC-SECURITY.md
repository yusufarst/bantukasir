# 04 — Autentikasi, izin, dan keamanan

Otorisasi wajib pada backend setiap akses. Boundary data publik dimiliki [02](02-ARCHITECTURE.md); transaksi/audit posting di [06](06-INVENTORY-SPEC.md); operasi secret dan retensi di [09](09-DEPLOYMENT-OPS.md).

## Autentikasi dan akun

Gunakan Better Auth self-hosted dengan adapter PostgreSQL/Drizzle, password hashing bawaan library, dan sesi server-side di database. Jangan menulis algoritme auth/kriptografi sendiri. Tidak ada pendaftaran publik; undangan akun hanya oleh owner, token sekali pakai dan kedaluwarsa. Library, adapter, opsi mematikan signup, reset dan 2FA wajib diverifikasi pada versi yang dikunci di P01 sebelum auth dinyatakan selesai.

Keputusan LATANSA: cookie sesi `HttpOnly`, `Secure` di deployment HTTPS, `SameSite=Lax`, host-only; cookie cache auth dimatikan supaya pencabutan segera terlihat. Batas sesi awal delapan jam tanpa perpanjangan melewati batas absolut; perangkat gudang tidak memakai opsi “ingat saya”. Auth terkini dan user aktif diperiksa pada semua command. Dokumentasi dasar: [session management](https://better-auth.com/docs/concepts/session-management) dan [email/password](https://better-auth.com/docs/authentication/email-password).

Owner menggunakan TOTP sebelum go-live; recovery codes disimpan offline oleh owner. Gunakan [fitur 2FA library](https://better-auth.com/docs/plugins/2fa), bukan shared OTP buatan aplikasi. Re-auth maksimal lima menit diperlukan untuk mengubah role, menonaktifkan akun, mengubah pengaturan keamanan dan tindakan koreksi owner. Staf dapat memakai TOTP, tetapi tidak memerlukan ponsel pribadi untuk setiap scan. Alur auth lengkap tetap berbahasa Indonesia melalui mapping pesan/halaman sendiri.

Bootstrap owner: operator deployment yang berwenang menjalankan proses sekali pakai melalui terminal tepercaya dan input tersembunyi; nonaktifkan bootstrap setelah user pertama dibuat. Tidak ada endpoint publik bootstrap, akun demo berpassword tetap, atau password dalam command history/Git. Undangan/reset staf menghasilkan tautan sekali pakai melalui antarmuka owner setelah verifikasi identitas; delivery manual melalui kanal privat, tidak perlu SMTP. Token berlaku 30 menit, disimpan hashed bila dikelola aplikasi, dan tidak tercatat dalam access log. Kredensial tidak pernah ditampilkan kembali. Owner kehilangan akses memakai recovery code; pemulihan darurat oleh operator host hanya setelah verifikasi pemilik, dicatat, mencabut sesi lama dan memaksa pendaftaran 2FA ulang.

Kebijakan awal password minimum 12 karakter, dukung password manager/paste, batas maksimum aman library, tidak memotong diam-diam. Rate limit login/reset berdasarkan akun dan sumber tanpa membocorkan apakah email terdaftar. Respons umum: “Email atau kata sandi tidak sesuai.” Tidak ada shared account dua staf.

## Permission yang dipakai layanan

Role adalah kumpulan permission tetap; satu user boleh memiliki lebih dari satu role atas keputusan owner. Default deny. `SUPER_ADMIN` tidak mempunyai hak mengabaikan invariant stok. Nilai tabel berlaku MVP kecuali bertanda L; tidak berarti semua tombol harus muncul sejak fondasi.

| Permission / tindakan | SUPER_ADMIN | INVENTORY_ADMIN | PRODUCT_SALES_ADMIN |
| --- | :---: | :---: | :---: |
| `users.manage`, `roles.assign`, `security.manage` | Ya | — | — |
| `products.readInternal` tanpa biaya/supplier privat | Ya | Ya | Ya |
| `products.writeContent` nama/deskripsi/kategori/brand | Ya | — | Ya |
| `products.createInventoryIdentity` SKU/satuan/tracking awal | Ya | — | — |
| `products.archive`, `stockPolicy.manage` minimum/monitor | Ya | — | — |
| `products.publish` (L) | Ya | — | Ya |
| `locations.manage` | Ya | — | — |
| `inventory.read`, `serial.read` posisi/riwayat | Ya | Ya | — |
| `availability.read` tersedia agregat per produk | Ya | Ya | Ya |
| `inventory.receive`, `inventory.issue`, `inventory.transfer` | Ya | Ya | — |
| `barcode.resolve`, `barcode.print`, `serial.register` | Ya | Ya | — |
| `barcode.manageAliases`, `serial.correctIdentity` | Ya | — | — |
| `inventory.opening`, `inventory.adjust`, `inventory.reverse` | Ya | — | — |
| `inventory.exportHistory` tanpa biaya | Ya | Ya | — |
| `audit.readAll`, `ops.read`, `ownerDashboard.read` | Ya | — | — |
| `notification.readOwn`, `notification.readOwnState`, `push.manageOwn` | Ya | Ya | Ya |
| `sales.manage` RFQ/leads/penawaran (L) | Ya | — | Ya |
| `reservation.request/releaseOwn` (L, scope sales) | Ya | — | Ya |
| `correction.request`, `opname.count` (L) | Ya | Ya | — |
| `approval.decide`, `opname.approve` (L) | Ya | — | — |

Warehouse scope MVP mencakup seluruh lokasi aktif perusahaan bagi role inventory; tidak ada multi-tenant atau pembatasan per gudang yang setengah diterapkan. Jika kelak diperkenalkan scope, semua query, barcode lookup, ekspor, command dan background action ikut memeriksanya. Admin produk dapat mengubah konten tetapi tidak mengganti tracking/satuan atau memberi diri izin stok. Pembuatan identitas produk dilakukan owner karena jarang dan menentukan akuntansi.

Menu “Riwayat Stok” staf boleh menampilkan pelaksana operasi yang relevan, bukan audit keamanan lengkap. Biaya/margin/supplier privat belum dimodelkan MVP; bila ditambahkan, permission baru eksplisit, bukan otomatis diwarisi `readInternal`.

## Penegakan

1. Adapter HTTP memvalidasi cookie/origin/CSRF dan input.
2. Service mengambil actor server-side dan permission aktif dari DB, memeriksa tindakan **serta objek** (termasuk ownership inbox, push endpoint, session receipt).
3. Transaksi stok mengulang pemeriksaan di bawah user guard sebelum posting, sebagaimana urutan lock [06](06-INVENTORY-SPEC.md).
4. Repository menerima scope terverifikasi; response DTO memakai allowlist. Middleware/layout hanya membantu navigasi, tidak menggantikan langkah ini.

Worker menggunakan service actor khusus dan command terbatas, bukan akun owner/password owner. Job tidak dapat mengubah role atau memposting koreksi arbitrer. Satu owner aktif harus selalu tersisa; penonaktifan/demotion/2FA reset menggunakan transaksi dengan guard pengelolaan owner untuk mencegah dua request menghapus owner terakhir.

Core tidak memerlukan approval transaksi rutin. Koreksi owner adalah eksekusi berizin dengan re-auth dan alasan; itu **bukan** klaim pemisahan maker-checker. Fase approval memungkinkan permintaan staf dan keputusan owner. Owner-initiated emergency correction tetap diberi label khusus dan diaudit; jangan mengaku ada reviewer kedua bila hanya satu owner.

## Ancaman dan kontrol

| Risiko | Kontrol wajib dan verifikasi |
| --- | --- |
| IDOR/role bypass | Negative test tiap permission, ID objek milik user lain, perubahan role saat sesi masih terbuka |
| CSRF, request dari origin lain | Validasi Origin terhadap konfigurasi, perlindungan CSRF library/custom endpoint yang sesuai; semua mutasi POST, cookie aman |
| XSS / injection | React escaping, sanitasi konten katalog, CSP bertahap, query parameterized Drizzle; raw SQL hanya parameterized/reviewed |
| Credential brute force | Rate limit terukur, audit gagal masuk, tidak ada pesan enumerasi akun; header IP hanya dipercaya dari Caddy |
| Barcode/payload berbahaya | Input panjang/charset terbatas, tidak mengeksekusi URL/HTML, tidak menulis raw payload ke log, batas ukuran dokumen |
| Replay dan race | Receipt unique, lock dan invariant DB; pengujian concurrency di PostgreSQL nyata |
| Kebocoran cache | Internal no-store, cache publik terpisah, service worker tidak cache response auth/stok, uji dua user bergantian |
| Upload/SSRF kelak | Tidak fetch URL arbitrer dari input; validasi tipe, batas file, decode/re-encode gambar, akses objek privat berizin |
| CSV formula injection | Ekspor mengamankan sel awalan formula/control, quoting benar, whitelist kolom; uji aplikasi spreadsheet |
| Ketergantungan rentan | Lockfile, tinjau advisory/dependensi pada upgrade dan release; jangan mencatat “aman” hanya karena build lolos |
| DB/runtime bocor | Least privilege, port DB privat, credential migrator terpisah, rotasi, backup terenkripsi, prosedur insiden |

## Audit yang dapat dipercaya

Field minimum: `eventId`, `schemaVersion`, `occurredAt` server, actorId atau serviceActor, action internal, entityType/entityId, requestId, reason/reference, safe before/after, outcome. Immutable untuk bisnis yang berhasil; event posting harus gagal bersama transaksi bila audit tidak tersimpan. Before/after stok terdapat pada kaki ledger dan ditautkan dari audit, tidak disalin sebagai payload besar.

Login gagal/akses ditolak tidak berada dalam transaksi stok yang rollback: catat pada security log terpisah, rate limited, tanpa password/token/raw body. Bila sink log keamanan terganggu, alert ops; login biasa tidak menulis event bisnis palsu. Perubahan privilege/keamanan harus gagal tertutup bila audit wajib tidak bisa ditulis.

UI merender template berversi, misalnya “Petugas Gudang Demo mencatat 2 unit Barang Contoh keluar dari Gudang Utama.” Kode seperti `INVENTORY.ISSUE.POSTED` hanya untuk diagnostik berizin. Hindari memasukkan data pribadi yang tidak perlu. DB administrator masih dapat memodifikasi database secara teknis; append-only runtime adalah kontrol aplikasi, bukan bukti antiperusakan absolut. Backup, log operasi terpisah, dan pembatasan admin melengkapi kontrol.

## Kebijakan repositori publik

Rahasia hanya environment/secure runtime, tidak ada fallback, tidak ada secret di `NEXT_PUBLIC_*`. `.env.example` berisi nama kosong; nilai wajib yang hilang menyebabkan startup komponen terkait gagal dengan nama variabel saja. Gambar/screenshot/test fixture harus demo. Jangan memasukkan dump, token push, recovery code, password hash produksi, IP/host rahasia, atau kontak pelanggan.

Jika secret pernah dikomit: hentikan penyebaran, cabut/rotasi, audit pemakaian, lalu koordinasikan pembersihan sejarah; menghapus baris pada commit berikutnya saja tidak cukup. Jangan menyalin secret ke laporan insiden publik.
