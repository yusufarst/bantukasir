# 09 — Deployment dan operasi

Desain target, belum ada host/Compose/backup/job yang berjalan. Pelaksana teknis mengoperasikan sistem; owner tidak perlu menjalankan PostgreSQL secara manual. Menambah VPS/storage berbayar memerlukan keputusan pembelian terpisah, bukan dianggap gratis karena software open-source.

## Topologi dan rilis

Satu VPS Linux menjalankan Docker Compose: Caddy, web Next.js, worker dari image yang sama, PostgreSQL, dan job backup terjadwal. Hanya Caddy mengekspos 80/443. Port DB tidak terbuka internet; web/worker terhubung jaringan internal. Data Postgres dan file persisten di volume terpisah dengan permission minimum. Aplikasi/worker non-root, image version/digest terkunci; jangan menggunakan tag `latest` untuk produksi.

Caddy menangani HTTPS otomatis dan reverse proxy; domain/email sertifikat memakai konfigurasi operator yang tidak perlu dipublikasikan. Redirect HTTPS, header keamanan dan batas request body ditetapkan saat deploy. Jalur auth/stock tidak di-cache proxy. Pengaturan trusted proxy memastikan client tak bisa memalsukan IP lewat header langsung.

MVP tidak memerlukan cluster/Kubernetes. Pisahkan environment development, test, staging/pilot, production beserta DB, key, push subscription dan backup destination. Staging tidak mengirim notifikasi produksi. Database migration dijalankan sebagai pekerjaan tunggal sebelum trafik versi baru; web startup tidak berlomba menjalankan migration.

Urutan rilis kelak: review/quality gate → build image immutable → backup tervalidasi → maintenance bila perubahan incompatible → migration terkontrol → start web/worker → readiness → smoke test → buka trafik → pantau error/stock invariant. Gunakan expand/contract untuk perubahan skema bertahap. Rollback kode hanya jika schema compatible; destructive migration membutuhkan restore/recovery plan, bukan menjalankan down migration tanpa menilai data.

## Konfigurasi dan secret

[.env.example](../.env.example) hanya key dengan nilai kosong. Tabel ini mendefinisikan komponen yang membutuhkan masing-masing key. Nilai nyata berada di secret store/mount file environment pada host privat dengan permission ketat, tidak di compose yang dikomit atau screenshot. Startup memvalidasi tipe/URL/origin/secret strength, log hanya **nama** variabel yang hilang.

| Variabel | Komponen / persyaratan | Sifat |
| --- | --- | --- |
| `NODE_ENV` | web/worker, pilihan development/test/production tervalidasi | Nonsecret |
| `APP_ORIGIN` | web, satu origin resmi; HTTPS produksi; allowlist CSRF/tautan push | Nonsecret, konfigurasi deployment |
| `DATABASE_URL` | web/worker, akun runtime least privilege | Secret |
| `MIGRATION_DATABASE_URL` | job migration saja, pemilik schema terpisah | Secret; tidak di environment web |
| `BETTER_AUTH_SECRET` | web/auth; entropi sesuai library versi terkunci | Secret; tidak ada fallback |
| `BETTER_AUTH_URL` | web/auth; konsisten dengan origin resmi | Nonsecret |
| `PUSH_ENABLED` | worker/web, boolean eksplisit; false hanya mode pengembangan atau degradasi tercatat | Nonsecret; tidak default diam-diam |
| `VAPID_PUBLIC_KEY` | push enabled; dikirim ke client hanya lewat endpoint yang diperlukan | Public key, bukan private secret |
| `VAPID_PRIVATE_KEY` | worker jika push enabled | Secret |
| `VAPID_SUBJECT` | worker jika push enabled; kontak valid operator | Konfigurasi privat, jangan demo kontak asli di Git |
| `BACKUP_DATABASE_URL` | job backup; akun khusus read akses backup sesuai kebutuhan | Secret |
| `BACKUP_DESTINATION` | backup job; tujuan di luar host melalui konfigurasi aman | Perlakukan privat, tanpa embedded credential dalam log |
| `BACKUP_ENCRYPTION_RECIPIENT` | backup job; public recipient key enkripsi backup | Public key, tetapi nilai deployment tidak di repo |
| `BACKUP_DECRYPTION_KEY_FILE` | restore operator saja; path file private key terproteksi | Secret file; tidak dipasang di web/worker/backup rutin |
| `POSTGRES_DB`, `POSTGRES_USER` | bootstrap container DB dari template deploy kelak | Konfigurasi privat |
| `POSTGRES_PASSWORD_FILE` | bootstrap DB; path mounted secret yang diberikan Docker | Secret file, tidak dikomit |

Credential transport ke storage backup memakai mount credential/file pada job sesuai adapter terpilih, bukan ditanam di `BACKUP_DESTINATION`. Adapter/destination nyata dipilih ketika lingkungan disiapkan; akses ke destination dan key pemulihan harus terbukti sebelum pilot. Tidak membuat daftar key penyedia SaaS spekulatif.

Konfigurasi wajib komponen yang hilang membuat komponen gagal siap; fitur yang sengaja dimatikan menunjukkan status “Belum Aktif”, bukan berhasil. Jika PUSH_ENABLED=false, inventory tetap dapat berjalan pada development; penerimaan Core tetap tunduk gate kanal owner pada 13. Secret tidak pernah memakai prefix `NEXT_PUBLIC_`; browser hanya mendapatkan VAPID public key yang memang publik.

## Backup yang dapat dipulihkan

Asumsi sasaran pilot: **RPO maksimal 6 jam, RTO maksimal 4 jam**, perlu persetujuan owner dan pengukuran restore. Ini toleransi kehilangan data, bukan jaminan yang telah terbukti. Jika enam jam transaksi tak dapat diterima, tingkatkan ke WAL archiving/PITR sebelum go-live dan catat keputusan baru.

- Jadwalkan logical dump PostgreSQL custom format setiap 6 jam. Enkripsi sebelum salin keluar host; lakukan checksum dan verifikasi keterbacaan arsip. Simpan hasil job, waktu snapshot, checksum, ukuran, DB/app schema version, tujuan dan exit status tersanitasi.
- Dump adalah snapshot DB konsisten; bukan copy volume Postgres hidup. Cluster roles/privileges/extensions perlu dicatat dan direstorasi terpisah. Rujukan dasar: [PostgreSQL SQL dump](https://www.postgresql.org/docs/current/backup-dump.html). Pemilihan jadwal/retensi berikut adalah kebijakan LATANSA.
- Retensi awal: seluruh backup 6-jam untuk 7 hari, satu per minggu untuk 4 minggu, satu per bulan untuk 3 bulan. Jangan menghapus backup terakhir yang diketahui valid karena upload terbaru belum terverifikasi. Kapasitas dan biaya storage diukur.
- Minimal satu salinan terenkripsi berada pada media/akun/host terpisah dari VPS dan diuji aksesnya. Volume lain pada VPS yang sama tidak cukup. Batasi credential backup agar tidak mudah menghapus semua salinan; versioning/immutability bila destination mendukung.
- Kunci dekripsi disimpan terpisah dari VPS, dengan akses pemulihan oleh owner/operator berwenang. Kehilangan key berarti backup tidak dapat dipakai; latihan restore harus memakai salinan key recovery sebenarnya melalui kanal privat.
- Saat aset file diperkenalkan: gunakan objek immutable/content-addressed, backup file dan manifest yang sesuai DB snapshot; jangan delete fisik sebelum retention aman. Untuk perubahan file nonimmutable, freeze upload singkat selama koordinasi snapshot. DB dump saja tidak memulihkan gambar/lampiran.

## Runbook restore

1. Nyatakan insiden dan waktu cut-off ke owner, hentikan posting serta worker external delivery. Jaga sumber lama read-only untuk investigasi; jangan menimpa satu-satunya salinan.
2. Pilih backup tervalidasi, cocokkan checksum/version, dekripsi di host pemulihan terisolasi dengan permission ketat. Catat snapshot time dan potensi rentang transaksi hilang.
3. Siapkan PostgreSQL compatible, role/extension/privilege dan storage file sesuai manifest. Restore ke **database baru**, fail pada galat; jangan menyembunyikan kegagalan parsial.
4. Jalankan integrity check: ledger ↔ balance, posisi serial, reservation, state/episode/event/outbox, unique constraints dan jumlah dokumen. Jalankan ANALYZE serta smoke read/write terkontrol di lingkungan uji.
5. Cabut semua sesi hasil restore, evaluasi rotasi credential bila insiden keamanan. Review pending outbox dari snapshot; default suppress push lama sebelum cut-off agar tidak mengirim ulang kejadian usang. Pertahankan inbox/episode historis; restart delivery hanya setelah rekonsiliasi owner.
6. Rekonsiliasi bukti fisik/dokumen transaksi setelah snapshot. Gunakan command opening/corrective yang tepat dan referensi insiden; jangan mengganti saldo dengan SQL. Restore snapshot lama dapat kehilangan receipt baru juga, sehingga replay setelah cut-off harus diputuskan dari bukti transaksi, tidak dianggap aman otomatis.
7. Owner mengonfirmasi selisih yang tersisa dan operator mengalihkan trafik; uji auth/scan/commit/alert, baru buka posting. Catat RPO aktual, RTO aktual, backup yang digunakan, hasil verifikasi dan tindak lanjut.

Uji restore penuh sebelum pilot, tiap bulan, dan setelah perubahan skema/storage/backup mayor. Keberhasilan job backup tanpa latihan restore tidak memenuhi gate.

## Health, observabilitas, dan jadwal

Liveness minimal menyatakan proses hidup tanpa konfigurasi sensitif. Readiness memeriksa DB reachable/schema cocok dan konfigurasi; endpoint detail dibatasi jaringan/izin. Halaman owner menampilkan last successful backup/restore drill, heartbeat worker, usia outbox, kegagalan pengiriman, rekonsiliasi terakhir dan kapasitas disk. Nilai tak tersedia berlabel “Belum Terverifikasi”, bukan hijau.

| Pemeriksaan | Jadwal awal / kondisi perhatian | Tindakan |
| --- | --- | --- |
| Worker heartbeat | Setiap menit; tak terlihat >3 menit | Cek worker/log, jangan menulis ulang movement |
| Outbox | Continuous polling; pending tertua >5 menit | Cek provider/lease/config, inbox tetap sumber perhatian |
| Rekonsiliasi read-only | Harian dan setelah restore | Selisih membekukan posting scope dan memicu insiden |
| Backup | Setiap 6 jam; gagal atau sukses terakhir >7 jam | Alert, perbaiki, jangan menghapus backup valid terakhir |
| Disk | Peringatan <20% bebas, kritis <10% | Tambah ruang/retensi aman; kapasitas ditinjau sebelum DB penuh |
| Restore drill | Bulanan / perubahan besar | Ukur RPO/RTO, laporkan kegagalan sebagai blocker rilis |
| Dependency/security updates | Tinjau berkala dan sebelum release | Terapkan lewat branch/test, tanpa auto-update produksi buta |

Structured log memiliki requestId/commandId/jobId, durasi, kode galat dan actor ID seperlunya; tanpa body utuh, password, cookie, barcode serial mentah atau credential URL. Metrik dashboard tidak menyamakan jumlah log dengan transaksi sah.

Jika seluruh VPS mati, aplikasi di VPS itu tidak dapat mengirim alert. Sebelum produksi, operator menyiapkan pemeriksaan eksternal dari perangkat/host independen yang sudah tersedia atau layanan yang disetujui; bila belum tersedia, cantumkan celah deteksi downtime dan jangan mengklaim monitoring penuh. Tidak menambahkan SaaS berbayar diam-diam.

## Retensi dan perawatan data

| Data | Kebijakan awal |
| --- | --- |
| Ledger, actor historis, audit bisnis, receipt idempotensi, episode/event | Tidak dihapus otomatis; dipertahankan selama platform beroperasi, arsip tetap dapat ditelusuri |
| UserNotification | Tampilkan/arsip setelah 90 hari; episode OPEN tetap mudah diakses; event bisnis tidak dihapus |
| Delivery attempts/debug logs | 30 hari, galat ringkas untuk diagnosis; event dan hasil final tetap tertaut |
| Security access/login log | 90 hari dengan pembatasan akses; insiden terkait ditahan sampai selesai |
| Session/token kedaluwarsa | Bersihkan berkala sesuai library; tidak menghapus audit actor |
| Draf browser | Sesuai 12; tidak ada retensi draf sebagai ledger |
| Data sales/PII masa depan | Tetapkan tujuan/retensi/penghapusan sebelum mengumpulkan; tidak memakai retensi ledger sebagai alasan menyimpan semua PII selamanya |

Ini kebijakan operasional awal, bukan pernyataan kepatuhan hukum. Review kebutuhan kontraktual/perusahaan sebelum retensi diubah. Purge/arsip sensitif harus terjadwal, berizin dan diaudit; jangan memberi tombol hapus riwayat transaksi.

## Insiden dan perubahan operator

Runbook minimum: hentikan posting jika integritas diragukan, amankan backup dan log privat, identifikasi scope, koreksi melalui jalur resmi, verifikasi ulang, baru buka operasi. Owner menentukan tindakan bisnis; operator teknis menjalankan recovery. Daftar kontak/operator/host/key disimpan privat di luar Git. Serah-terima operator mencakup akses backup dan latihan pemulihan, bukan memberikan password produksi di dokumen proyek.
