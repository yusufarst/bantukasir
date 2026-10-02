# Aturan agent — Baseline P0–P11

Repository canonical adalah sumber kebenaran BantuKasir, codename internal untuk GOODS-only POS. Instruksi owner terbaru mengadopsi kerangka governance/planning/review MULTIPLECORP dari diskusi terlampir, **bukan domain bisnis, aset, token atau project desainnya**. Plan 2.0/T01–T40, Plan 1.1/Rxx/BKxx/UIxx/Gate A/B menjadi histori. Jangan mengubah upstream LATANSA atau menghapus pekerjaan lokal yang tidak terkait.

## Recovery, authority dan scope eksekusi

Periksa `git status --short`, branch, HEAD, commit terbaru serta staged/unstaged/untracked terlebih dahulu. Baca file ini → [00](docs/00-CURRENT-STATE.md) → [11](docs/11-BUILD-PLAN.md)/BU terpilih → [01](docs/01-PRD.md) → [02](docs/02-ARCHITECTURE.md) → [10](docs/10-DECISIONS.md) → specification kartu; UI membaca [DESIGN](DESIGN.md). Coding executor membaca [CLAUDE](CLAUDE.md). Memory chat atau laporan executor bukan evidence.

Planning P0 → P11 secara autonomous diotorisasi dalam sesi rebaseline ini, tanpa konfirmasi rutin antar fase. P0–P6 dipertahankan sebagai input yang dibekukan. **P7 wajib menghasilkan desain aktual melalui DesainPakeAI, approval visual owner eksplisit atas exact revision, serta DESIGN/kontrak token yang dinormalisasi. Baru setelah P7 COMPLETE, P8–P11 dapat difinalisasi ulang dan dibekukan.** Approval P7 adalah gate wajib, bukan konfirmasi fase rutin. [P0](docs/planning/P0-GOVERNANCE.md) mengatur AUTONOMOUS, PLANNER-DETERMINED PRODUCT SUPPORT dan OWNER_DECISION_REQUIRED. Capability tambahan memerlukan masalah konkret, alternatif lebih sederhana, biaya implementasi dan dampak operasi. Perubahan material pada makna bisnis, visibility, uang/stok, legalitas atau biaya nyata memerlukan keputusan owner.

Blueprint freeze membekukan specification yang direview; bukan bukti runtime, fakta client, implementation COMPLETE atau FINAL PASS. Untuk P7, review dokumen atau context teramati tidak mencukupi: freeze memerlukan desain aktual, approval visual owner atas revision yang tepat dan kontrak DESIGN yang dinormalisasi. Paket P8–P11 tetap draft sampai gate P7 terpenuhi. [Gap/change register](docs/planning/GAPS-CHANGES.md) memisahkan holds dan scope terdampak. Perubahan requirement masuk canonical/decision/mapping/BU/acceptance sebelum coding; buka kembali fase terdampak, review dan reestimasi.

Eksekusi nantinya memerlukan **P7 COMPLETE, P8–P11 difinalisasi ulang dan FROZEN**, lalu instruksi eksplisit **Execute BU-...** untuk satu unit dengan dependency artifact yang kompatibel dan PASS. Semua coding masih tidak diotorisasi; instruksi Execute tidak boleh melewati prasyarat paket tersebut. Satu primary coding executor; independent review boleh paralel. Jangan otomatis melanjutkan unit berikut. Label yang diminta owner: Claude Opus 5.5/effort xhigh; Fable 5.1/effort xhigh secara selektif untuk masalah bounded berisiko tinggi. Verifikasi availability saat handoff; jangan mengaku model telah digunakan atau menggantinya diam-diam.

Inspeksi dan stage path exact. Jangan `git add -A`, reset/clean/restore, menimpa source lama atau mencampur index yang tidak terkait. Git terkini adalah fakta fisik; klaim leftovers dalam histori tidak membuktikan file masih ada.

## Produk, integritas dan privasi

- GOODS saja, satu stock pool, tepat tiga role: SUPER_ADMIN/Pemilik, OPERATIONS_ADMIN/Admin Operasional, CASHIER/Kasir. Server auth/policies/object scope dan actor/config guards wajib.
- Product/category/barcode/search, receiving, opening khusus Pemilik, manual issue dengan reason non-sale, LOW/OUT. Pembuatan master tetap stok 0; unit/precision terkunci sesudah movement pertama.
- StockMovement immutable adalah kebenaran fisik; StockBalance projection sinkron nonnegative. Tidak ada direct balance edit atau stok negatif.
- Sale langsung dibayar penuh, satu CASH **atau** BANK_TRANSFER. Tidak ada mixed/split/partial/debt. Konfirmasi manusia atas penerimaan penuh dana transfer bukan settlement gateway.
- Sale/lines/full Payment/SALE_ISSUE/balance/cost atau UNKNOWN/ReceiptSnapshot/audit/durable result commit bersama. Scan hanya mengubah draft; print sesudah commit.
- Key/fingerprint/source intent/recovery epoch stabil mencegah duplikasi. UNCERTAIN/status yang tidak ditemukan/timeout bukan izin Sale pengganti, pembayaran ulang atau blind replay setelah restore. Pulihkan original, termasuk setelah CashSession CLOSED.
- MWA memakai perhitungan decimal presisi; UNKNOWN eksplisit; Laba Kotor bukan Laba Bersih. Cost evidence/allocations/revisions immutable; replay bounded dan all-or-nothing. Refund/return/correction menjaga original dan biaya historis; refund tanpa verified saleable return tidak menambah stok.
- Biaya perolehan/HPP/profit serta audit/security/recovery sensitif hanya Pemilik. Allowlist DTO staff mencakup HTML/Inertia props/shared props/flash/old input/history/errors/status/cache/log/media/export/browser storage. Biaya incoming Operations write-only, tanpa echo atau payload biaya pada recovery.
- CashSession ringan: satu drawer/satu responsible cashier/satu OPEN per register dan per cashier; opening cash eksplisit termasuk Rp0; session sendiri termasuk Pemilik/Sale transfer-only; OPEN → CLOSED race-safe dengan laporan immutable. Tidak ada reopen/responsibility overwrite/shared active drawer/paid-in/out.
- Identity/logo/template/accent BusinessProfile runtime bounded dan versioned; dokumen original mempertahankan revision/aset. Logo codename repository bukan authority default client.
- Future PaymentProvider seam hanya boundary arsitektur. Tidak mengimplementasikan callback/webhook/QRIS/asynchronous payment/gateway V1. Seluruh capability Later di 01 tetap tidak aktif.

## Stack dan desain

Stack dipilih setelah comparison/review: **Laravel + Inertia + React/TypeScript + PostgreSQL + Nginx**, shadcn/ui/Tailwind, PHP-FPM, Vite pada build time dan session database. Modular monolith memakai policies/FormRequests/Actions/DTO aman dan decimal presisi; tanpa SSR Node/Redis/queue/microservices secara default. [P4](docs/planning/P4-ARCHITECTURE.md) mencatat alasan, alternatif, testing/ops/maintenance. Pin versi/advisory/license kompatibel yang terverifikasi dalam BU-FOUND-001; freeze stack dibuka hanya berdasarkan evidence/change control.

Pipeline: **P7 desain aktual DesainPakeAI → approval visual owner atas exact BantuKasir Design revision → DESIGN/kontrak/token semantic repository yang dinormalisasi → P8–P11 difinalisasi ulang dan FROZEN → shadcn/ui → UI produksi React/Inertia native**. Tidak menunda desain atau approval pertama ke Build Unit implementasi. Backend/database/tests/ops yang independen secara teknis dapat dijadwalkan tanpa menunggu consumer UI hanya setelah paket planning dibekukan dan satu BU diotorisasi; prinsip ini bukan izin coding selama P7/P8–P11 belum selesai.

Gunakan CLI global `dpai`, bukan MCP; setup user tidak diulang tiap task. Sebelum context dan setiap mutation yang diotorisasi, `dpai project current --pretty` harus menunjukkan **BantuKasir Design / eddb0e15-9909-44b3-8517-9239cadc65ef**. Simpan revision, bukan credential. MULTIPLECORP tidak boleh dipilih/dibaca/diekspor/dimutasi atau dipakai sebagai inspirasi visual/fallback/aset/token. Mismatch → STOP sebelum akses lanjut. Mutation desain/layanan berbayar/pembelian memerlukan otorisasi scope. 21st.dev/Impeccable bukan tooling/gate aktif.

## Database produksi dilindungi

Tanpa approval owner untuk operasi destruktif **EXACT**, jangan DROP database/schema/populated tables, TRUNCATE, mass DELETE, reset/recreate/demo seed produksi, hapus ledger/Sale/Payment/AuditEvent/history, tulis ulang fakta historis, hapus database/Docker volume/persistent directory atau `docker compose down -v`. Fix/deploy/run migration bukan approval destruktif.

Gunakan forward/additive expand → migrate/backfill → verify → contract, populated rehearsal terisolasi, independent backup/restore readiness, transaksi bila sesuai dan rekonsiliasi. Jika destruction tampak perlu, STOP: jelaskan data berisiko, alasan, alternatif, migration/backfill, backup/restore dan rollback/recovery. Reset fixture hanya pada test database yang diidentifikasi dan diotorisasi. [09](docs/09-DEPLOYMENT-OPS.md) adalah runbook.

## DoD, evidence dan closeout

Penuhi delapan dimensi [08](docs/08-TESTING-ACCEPTANCE.md): BUSINESS, UI/UX, BACKEND, DATABASE, SECURITY, DATA INTEGRITY, PERFORMANCE, VERIFICATION. Gunakan PostgreSQL nyata untuk constraints/rollback/races; privasi role lintas channel; PHP static checks dan TypeScript lint/typecheck/build; browser/mobile/a11y; perangkat/restore/performance aktual bila relevan. Mock/screenshot/bukti awal bukan FINAL PASS; jangan mengarang hasil.

Catat revision/commands/target non-secret/dataset/results/reviewer/fixes/holds; update 11 dan 10 untuk keputusan nyata, lalu **00 LAST**. Stage path yang disengaja dan review seluruh index. Commit lokal task hanya untuk scope terotorisasi, hasil koheren dan checks lulus. Jangan push/merge/deploy/publish/beli tanpa otorisasi eksplisit. Target biaya tambahan sekitar Rp0 tidak menghapus independent backup; catat biaya nyata.

Sesi koreksi ini mengotorisasi **satu commit lokal docs/design-planning** bila scope aman, lalu STOP. P7 yang belum memperoleh approval visual owner tetap IN PROGRESS atau WAITING OWNER VISUAL APPROVAL sesuai evidence; P8–P11 tetap REOPENED_DRAFT/PENDING_P7_APPROVAL. Tidak mengotorisasi coding aplikasi, BU-FOUND-001 atau unit lain, infrastructure/database produksi, push maupun deploy.
