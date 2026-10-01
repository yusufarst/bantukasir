# 09 — Operasi, data production, dan recovery

**Plan 2.0.** Ini kontrak operasi; sesi rebaseline tidak membuat runtime, deployment, migration, atau backup job. [11 — Rencana dan task](11-BUILD-PLAN.md) menetapkan ownership dan hold; [08 — Penerimaan](08-TESTING-ACCEPTANCE.md) menetapkan bukti yang wajib selesai sebelum release.

## Database production dilindungi untuk semua agent dan operator

Production berisi catatan bisnis yang bernilai. Tanpa persetujuan eksplisit pemilik untuk **OPERASI DESTRUKTIF YANG PERSIS**, dilarang:

- `DROP DATABASE`, `DROP SCHEMA`, atau drop tabel production yang berisi data;
- `TRUNCATE` atau mass delete catatan bisnis;
- reset/recreate production, seed demo data, atau destructive reset script/migration yang disamarkan sebagai maintenance;
- menghapus volume PostgreSQL/Docker production, direktori DB persisten, atau menjalankan `docker compose down -v` terhadap production;
- menghapus StockMovement, completed Sale, Payment, ReceiptSnapshot, snapshot laporan sesi, AuditEvent, atau fakta biaya/history;
- diam-diam menulis ulang fakta stok atau keuangan historis.

Instruksi umum “fix”, “deploy”, atau “run migration” bukan persetujuan destruktif. Jika operasi destruktif tampak diperlukan, STOP dan jelaskan alasan, data yang berisiko, alternatif aman, rencana migration/backfill, prasyarat backup/restore, serta rollback/recovery. Reset non-production memerlukan target yang diidentifikasi positif dan otorisasi task. Sebelum mengubah data, periksa environment, host, database, volume/storage, dan tujuan command; nama folder tidak membuktikan bahwa target aman.

Default adalah migration additive/forward: expand → migrate/backfill → verify → contract. Rehearsal wajib memakai lingkungan non-production terisolasi yang sudah berisi data representatif. Sebelum migration berisiko, siapkan backup terverifikasi dan kesiapan restore; gunakan transaksi migration bila memungkinkan. Setelahnya verifikasi row count, constraints, integritas sumber, dan rekonsiliasi bisnis. Contract/destructive change yang sudah direview tetap memerlukan persetujuan operasi yang persis.

Role SQL runtime tidak boleh drop schema maupun update/delete fakta bisnis immutable. Migration credential terpisah dan tidak tersedia untuk web runtime; backup memakai credential tersendiri dengan akses minimum yang dibutuhkan. Pemisahan role dan privilege benar-benar diuji, termasuk denial DDL dan perubahan history dari role runtime. Jangan mengandalkan disiplin aplikasi sebagai satu-satunya perlindungan database.

## Runtime, konfigurasi, dan biaya

Gunakan VPS/domain yang ada; Docker Compose berisi app non-root, PostgreSQL privat, Caddy/HTTPS, dan scheduled backup/reconciliation commands. Tidak ada port DB publik. Pin versi image/dependency yang kompatibel; hindari tag production `latest`. Pisahkan database, volume, secret, dan storage dev/test/staging/production. Migration berjalan sekali sebagai controlled job, bukan berlomba pada startup beberapa web process. Detail transaksi dan lock mengikuti [02 — Arsitektur](02-ARCHITECTURE.md).

Target tambahan biaya software recurring sekitar Rp0: self-hosted auth/PostgreSQL, pencarian native, browser print/PDF, CSV first-party, barcode open-source, dan restock dalam aplikasi. Layanan auth/DB/search/queue/analytics/PDF/notifikasi atau kredit/template berbayar memerlukan persetujuan eksplisit. Storage backup di luar VPS dapat berbiaya; catat biaya nyata dan kapasitas berkelanjutan. Backup pada host yang sama tidak memenuhi independensi.

Konfigurasi tiap proses mencakup app origin, runtime DB URL, auth secret/origin, storage root, serta setting operasional yang diperlukan. Migration DB URL hanya diberikan kepada controlled migration job; backup destination/credential dan encryption recipient hanya diberikan kepada proses backup. Private restore key disimpan dan dapat diakses secara independen dari VPS. Validasi setting wajib tanpa silent production defaults atau secret `NEXT_PUBLIC`. `.env.example` warisan belum menjadi kontrak implementasi tervalidasi; task foundation/packaging dalam [11](11-BUILD-PLAN.md) wajib menyelaraskannya. Credential, alamat yang mengandung secret, dan data production tidak masuk Git/log/screenshot. Access key DesainPakeAI tetap pada konfigurasi privat user dan bukan dependency runtime production.

“BantuKasir” adalah codename internal. Runtime BusinessProfile menyimpan identitas resmi client; source aplikasi tidak diubah untuk mengganti client. Logo produksi memakai storage privat dan immutable revision yang direferensikan receipt/report, dengan manifest DB dan checksum. Pertahankan revision yang masih direferensikan; tidak ada public document storage. Upload maksimal 5 MiB/16 megapixel, PNG/JPEG/WebP terverifikasi, re-encode dan strip metadata; tolak executable/SVG/HTML serta remote URL fetching. [04 — Keamanan](04-AUTH-RBAC-SECURITY.md) mengatur akses dan privasi; [DESIGN](../DESIGN.md) mengatur sumber visual.

## Release dan migration

Urutan release: bukti gate dan tests/build → immutable image → backup terverifikasi → populated migration rehearsal → deployment window yang diotorisasi → controlled migration → reconciliation/readiness/role smoke → pilot pemilik. Gunakan maintenance/write-stop bila perubahan schema memerlukannya. Rehearsal memeriksa rollback transaction atau forward repair dan kompatibilitas versi, bukan hanya keberhasilan schema pada database kosong.

Code rollback hanya aman dengan schema yang kompatibel. Jika tidak, lakukan forward repair atau recovery terisolasi yang sudah direhearsal; jangan mengganti masalah release dengan reset data. Baris task atau kelulusan test tidak mengizinkan push, merge, deploy, cutover, atau perubahan infrastructure. Otorisasi release/pilot dicatat secara eksplisit; state terkini mengikuti [00](00-CURRENT-STATE.md).

## Backup independen dan gate G4

Pemilik memilih destination independen dan menerima RPO/RTO hasil pengukuran sebelum production. Target awal yang diusulkan **RPO ≤6 jam / RTO ≤4 jam BELUM DISETUJUI** dan bukan fakta client. Jika tidak sesuai kebutuhan, evaluasi dump lebih sering/WAL/PITR dan ubah scope serta kapasitas sebelum pilot; jangan mengklaim kemampuan yang belum diuji.

Backup terjadwal memakai consistent PostgreSQL dump, dienkripsi sebelum transfer keluar VPS, dengan pemeriksaan checksum/archive dan manifest yang mencatat snapshot cutoff, schema/app version, serta referenced media. Asset/logo dan manifest harus konsisten dengan snapshot DB; receipt/session snapshot dan referensi file harus dapat direstore. Ekspor/PDF bukan backup.

Retensi awal: minimal tujuh hari copy tiap enam jam, empat copy mingguan, dan tiga copy bulanan, tunduk pada kapasitas dan policy pemilik. Jangan menghapus copy baik terakhir yang terverifikasi hanya karena upload baru tersedia. Destination harus independen dari host/volume VPS; akses akun/device dan recovery key tersedia melalui jalur terpisah yang benar-benar dapat digunakan pemilik/operator.

Lakukan restore nyata ke lingkungan terisolasi sebelum pilot, setiap bulan, dan setelah perubahan besar schema/storage. Exit code backup sukses atau upload hijau tidak memberikan kelulusan restore. View Sistem pemilik menampilkan backup/restore sukses terakhir, usia, failure, disk, dan status rekonsiliasi tanpa secret. Status yang belum dibuktikan adalah “Belum Terverifikasi”. Pemeriksaan availability dari host/device independen dibutuhkan untuk mendeteksi VPS mati; jika jalur itu belum ada, catat gap monitoring dan hold terkait, jangan menyebut monitoring lengkap.

## Runbook restore dan interval yang hilang

1. Hentikan writes, deklarasikan incident dan snapshot cutoff, lalu pertahankan sumber asli read-only.
2. Verifikasi/dekripsi backup pilihan, cocokkan app/DB version, lalu restore ke database baru dan storage root terisolasi. Jangan menghancurkan sumber asli.
3. Verifikasi role SQL, extension, constraints, manifest, dan checksum semua referenced asset; pastikan recovery key serta akses independen benar-benar tersedia.
4. Rekonsiliasi lifecycle CashRegister/CashSession; atribusi Sale/session; SaleLine/Payment/SALE_ISSUE/ReceiptSnapshot; seluruh jumlah movement/balance; acquisition/valuation revision; refund/return; scope/cutoff/opening/expected/physical/variance/snapshot sesi; durable command receipts/source intent; dan audit. Active-session guard yang konflik, CLOSED tanpa tepat satu snapshot, orphan Sale, atau stok tidak cocok memblokir reopening.
5. Revoke session hasil restore dan tetapkan recovery epoch baru. Intent browser lama tidak boleh otomatis dieksekusi sebagai pekerjaan baru. Periksa cash/transfer, dokumen yang sudah tercetak, dan stok fisik sesudah cutoff. Hilangnya hasil command pada backup tidak membuktikan bahwa transaksi asli tidak pernah commit.
6. Pemilik menyelesaikan interval yang hilang dari evidence melalui prosedur correction/recovery append-only yang diotorisasi. Jangan mengedit balance dengan SQL, replay payment secara buta, atau membuat Sale pengganti berdasarkan asumsi.
7. Verifikasi auth/search/scan/posting/report/print, role/object scope, epoch behavior, dan jalur recovery independen. Catat RPO/RTO yang benar-benar terukur serta data gap. Pemilik menyetujui traffic reopening.

Recovery tidak boleh otomatis menganggap keadaan setelah cutoff dapat direkonstruksi. Bila evidence cash/transfer/stock tidak lengkap, catat kasus belum terselesaikan dan tahan affected posting; escalation kepada pemilik adalah bagian runbook. [06](06-INVENTORY-SPEC.md), [15](15-FINANCE-PROFITABILITY.md), dan [17](17-POS-SALES.md) mengatur fakta domain yang harus direkonsiliasi.

## Maintenance, retensi, dan bukti

Rekonsiliasi stok/cost/source/session berjalan read-only setiap hari; failure memblokir affected posting dan memerlukan penanganan tercatat. Warning usia backup memakai jadwal plus grace yang dikonfigurasi; warning disk pada 20% ruang bebas, critical pada 10%. Structured log memakai ID dan error aman, tanpa payload sensitif penuh.

Tidak ada auto-delete ledger/Sale/Payment/audit/receipt/session report/cost revision atau identity asset yang masih direferensikan. Log debug operasional 30 hari dan security access log 90 hari mengikuti policy incident/privacy yang disetujui. Token auth kedaluwarsa mengikuti retensi library tanpa menghapus actor historis. Tidak ada klaim kepatuhan hukum otomatis.

Runbook host/key/contact nyata tetap privat dan dapat diakses pemilik/operator. G4 mencakup destination independen, biaya sustainable, recovery access, restore drill, RPO/RTO diterima, dan gap monitoring. Kelulusan akhir memerlukan populated migration, privilege denial, checksum/media restore, rekonsiliasi, recovery epoch dan recovery gap, ukuran waktu nyata, serta deployment/pilot authorization sesuai [08](08-TESTING-ACCEPTANCE.md) dan [11](11-BUILD-PLAN.md). Dokumen ini tidak mengklaim check tersebut sudah lulus.
