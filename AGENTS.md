# Aturan agent — Plan 2.0

Dokumentasi repository adalah sumber kebenaran **Plan 2.0**. BantuKasir hanya codename internal untuk POS GOODS-only; nama/logo client produksi berasal dari BusinessProfile runtime. Plan 1.1, R01–R18, BK01–BK36, UI01–UI04, Gate A/B dan workflow executor lama adalah riwayat, bukan prasyarat aktif. Jangan mengubah upstream LATANSA atau menghapus pekerjaan lokal yang tidak terkait.

## Pemulihan konteks dan batas eksekusi

Periksa `git status --short`, branch, HEAD, commit terbaru, staged/unstaged/untracked sebelum bekerja. Baca berurutan: file ini → [00](docs/00-CURRENT-STATE.md) → task aktif/berikut di [11](docs/11-BUILD-PLAN.md) beserta kartu `docs/tasks/Txx.md` → [01](docs/01-PRD.md) → [02](docs/02-ARCHITECTURE.md) → [10](docs/10-DECISIONS.md) → [DESIGN](DESIGN.md) untuk UI → spesifikasi yang dirujuk kartu. Jangan mengandalkan ingatan chat atau menganggap laporan executor sebagai bukti.

Instruksi “Execute Txx” memilih **satu task** dan mengotorisasi scope implementasinya pada lingkungan yang diizinkan. Satu executor aktif; pekerjaan independen boleh dijalankan saat task lain menunggu owner bila dependency teknisnya memenuhi [11](docs/11-BUILD-PLAN.md). Jangan otomatis melanjutkan task berikutnya. Perubahan requirement masuk dokumen canonical sebelum eksekusi.

Kondisi fisik awal: prototype dan evidence BK03 masih staged/unstaged; jangan mencampurnya dengan task baru. Inspeksi dan stage path task secara eksplisit. Jangan `git add -A`, reset/clean/restore atau menimpa source lama demi merapikan status.

## Produk, integritas dan privasi

- GOODS saja, satu stock pool, Sale langsung dibayar penuh CASH/BANK_TRANSFER. Tidak ada Order/booking/DP/services/reservation/warehouse/HR atau shift engine kompleks.
- Tepat SUPER_ADMIN/Pemilik, OPERATIONS_ADMIN/Admin Operasional, CASHIER/Kasir. Server auth, backend RBAC, scope objek dan actor/config guard wajib; menu tersembunyi bukan keamanan.
- StockMovement immutable adalah kebenaran fisik; StockBalance projection sinkron. Tidak ada direct balance editing atau negative stock.
- Sale, lines, full Payment, SALE_ISSUE, balance/cost facts, ReceiptSnapshot, audit dan durable command result commit bersama. Scan hanya mengubah draft; cetak setelah commit.
- Key/fingerprint/source intent/recovery epoch menjaga duplikasi dan original-result recovery. Timeout tidak mengotorisasi replacement Sale, pembayaran ulang atau blind replay setelah restore.
- Fakta payment/history append-only; koreksi/refund/return mempertahankan original. Refund tanpa barang fisik tidak menambah stok.
- MWA, UNKNOWN eksplisit, Laba Kotor bukan Laba Bersih. Acquisition cost/HPP/profit dan audit/security/recovery sensitif hanya Pemilik. DTO staff memakai allowlist, termasuk error/status/cache/log/media/export. Biaya baru Operations write-only.
- CashSession ringan: satu drawer, satu responsible cashier, satu OPEN; opening cash eksplisit termasuk Rp0, close race-safe OPEN → CLOSED dengan immutable report. Tidak ada reopen, responsibility overwrite, shared active drawer atau paid-in/out.
- BusinessProfile/config/logo runtime dibatasi, terotorisasi dan versioned; dokumen membekukan identity/template/assets asli. Logo codename repository bukan default client produksi.

## Desain dan tooling

Pipeline aktif: **DesainPakeAI → foundation/revision yang dipilih dan disetujui owner → kontrak/token repository → shadcn/ui → Next.js native**. [DESIGN](DESIGN.md) menetapkan boundary; backend/database/tests tidak menunggu visual. T24 dan UI berikutnya memerlukan T23 COMPLETE dengan approval revision yang tepat. Tidak ada rebuild melalui beberapa aplikasi prototype.

Gunakan CLI global `dpai`, bukan MCP. Sebelum context dan terutama sebelum setiap mutation, verifikasi `dpai project current --pretty`: **BantuKasir Design / eddb0e15-9909-44b3-8517-9239cadc65ef**. Simpan revision context, jangan kredensial. **MULTIPLECORP tidak boleh dipilih, dibaca, diekspor, dimutasi atau dipakai sebagai inspirasi/fallback/aset/token.** Project mismatch berarti STOP sebelum akses lanjut. Mutation desain, layanan berbayar dan pembelian memerlukan otorisasi scope; setup user tidak diulang setiap task. 21st.dev/Impeccable tidak lagi menjadi requirement, gate atau tooling aktif.

## Database produksi dilindungi — seluruh agent

Tanpa approval owner untuk **operasi destruktif yang EXACT**, jangan DROP DATABASE/SCHEMA/populated tables, TRUNCATE, mass DELETE, reset/recreate/seed demo produksi, hapus ledger/Sale/Payment/AuditEvent atau menulis ulang fakta historis, hapus DB/Docker volume/persistent directory, maupun menjalankan `docker compose down -v`. “Fix”, “deploy” dan “run migration” bukan approval destruktif.

Gunakan forward/additive migrations: expand → migrate/backfill → verify → contract; rehearsal pada non-production populated yang jelas; backup/restore siap sebelum risiko tinggi; transactional migration bila sesuai; rekonsiliasi sesudahnya. Bila destruction tampak perlu, STOP dan laporkan alasan, data berisiko, alternatif aman, migration/backfill, backup/restore, rollback/recovery. Reset fixture hanya pada DB test yang diidentifikasi dan diotorisasi. Runbook: [09](docs/09-DEPLOYMENT-OPS.md).

## Penutupan task

Penuhi VERIFY/ACCEPTANCE kartu: unit, PostgreSQL nyata untuk constraints/rollback/races, RBAC/privasi, lint/typecheck/build, browser/mobile/a11y, dan device/recovery/performance bila relevan. Mock/screenshot bukan bukti transaksi; early tests bukan FINAL PASS [08](docs/08-TESTING-ACCEPTANCE.md). Jangan mengarang hasil.

Catat revision/commands/target non-secret/hasil/holds. Update [11](docs/11-BUILD-PLAN.md), [10](docs/10-DECISIONS.md) hanya untuk keputusan nyata, lalu **[00](docs/00-CURRENT-STATE.md) LAST**. Commit lokal task hanya setelah hasil koheren, checks lulus dan path scope bersih; preservasi index unrelated. Jangan push/merge/deploy/publish/beli tanpa otorisasi eksplisit. Target biaya tambahan sekitar Rp0 tidak boleh menghapus kebutuhan independent backup; catat biaya nyata.

Rebaseline ini hanya mengotorisasi **satu local docs-only commit**, lalu STOP. Belum ada otorisasi menjalankan T01 atau task coding lain.
