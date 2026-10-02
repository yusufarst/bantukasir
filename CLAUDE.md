# Handoff coding — BantuKasir

Dokumen ini mengatur executor implementasi sesudah owner memilih dan mengotorisasi **satu Build Unit**. Paket P0–P11 adalah specification/blueprint; sesi rebaseline belum mengotorisasi coding aplikasi, BU pertama, push, merge, deploy atau perubahan infrastructure produksi.

## Executor yang diminta owner

- Primary coding executor: **Claude Opus 5.5**, effort **xhigh**.
- Eskalasi/additional adversarial reasoning: **Fable 5.1**, effort **xhigh**, selektif untuk security, money, stock, concurrency, migration/recovery yang exceptionally difficult/high-risk, atau bounded implementation/debug yang tidak dapat diselesaikan primary.

Nama tersebut adalah label/preferensi owner, bukan hasil verifikasi ketersediaan model. Jangan mengklaim model sudah digunakan, menjalankannya pada sesi planning, atau menggantinya dengan model lain secara diam-diam. Bila harness tidak menyediakan model yang diminta, laporkan ketersediaan aktual dan kebutuhan keputusan, sementara pekerjaan read-only independen dapat dilanjutkan. Build Units tetap portable bagi executor lain yang mampu.

## Intake wajib dan satu BU

1. Periksa `git status --short`, branch, HEAD, commit terbaru, staged/unstaged/untracked. Catat target non-secret dan perubahan unrelated; jangan menghapus atau merapikannya dengan reset/restore/clean.
2. Baca [AGENTS](AGENTS.md) → [00](docs/00-CURRENT-STATE.md) → [11](docs/11-BUILD-PLAN.md) → kartu BU yang dipilih di `docs/build-units/` → [01](docs/01-PRD.md) → [02](docs/02-ARCHITECTURE.md) → [10](docs/10-DECISIONS.md) dan seluruh READ kartu. UI juga membaca [DESIGN](DESIGN.md); acceptance [08](docs/08-TESTING-ACCEPTANCE.md), operasi [09](docs/09-DEPLOYMENT-OPS.md), traceability [CORE-MAP](docs/requirements/CORE-MAP.md), dan governance [P0](docs/planning/P0-GOVERNANCE.md).
3. Verifikasi dependency teknis, revision provider dan hold BU. NEXT adalah informasi handoff, bukan izin otomatis. Instruksi eksplisit `Execute BU-...` memilih satu BU dan scope pada lingkungan yang diizinkan; jangan menjalankan BU berikutnya sendiri.
4. Implementasikan kontrak repository. Jangan mendesain ulang produk, mengganti stack frozen, melonggarkan invariant/acceptance atau memperluas scope untuk membuat test lulus. Perubahan kontrak masuk canonical/change control dahulu; eskalasi keputusan bisnis mengikuti P0.

Plan 2.0, T01–T40, BK/UI/Gate lama dan prototype/evidence historis hanya input/provenance. Jangan memakai completion prototype sebagai bukti produksi atau mengikuti kartu task obsolete. [11](docs/11-BUILD-PLAN.md) menetapkan Build Units aktif, dependency, effort dan exact next unit.

## Scope, uang dan stok

- GOODS-only, satu business-wide stock pool, tepat SUPER_ADMIN/Pemilik, OPERATIONS_ADMIN/Admin Operasional dan CASHIER/Kasir. Tidak menambah tenant/outlet/warehouse, services/order/DP, purchasing/CRM/HR, offline posting atau enterprise scope.
- Completed Sale mempunyai **satu pembayaran penuh CASH atau BANK_TRANSFER**. Transfer dikonfirmasi secara operasional sesuai workflow; tidak ada split/partial/credit, QRIS/gateway/webhook/asynchronous payment V1. Payment provider boundary masa depan tidak memberi izin mengimplementasikan provider.
- StockMovement immutable adalah fakta fisik; StockBalance projection sinkron nonnegative. Scan/search/create product hanya draft/master dan tidak menciptakan stok. Posting terjadi lewat command eksplisit, tidak direct balance editing.
- Sale/lines/full Payment/SALE_ISSUE/balance/cost facts/ReceiptSnapshot/audit/durable command result commit bersama. Cetak sesudah commit; print/reprint failure tidak memposting ulang.
- Source intent, idempotency key/fingerprint dan recovery epoch mempertahankan original-result recovery. Timeout/restore tidak membuktikan gagal commit dan tidak mengotorisasi Sale pengganti, pembayaran ulang atau blind replay.
- Refund/return/correction/reversal mempertahankan fakta asli serta linked compensating records. Refund tanpa barang fisik tidak menambah stok. MWA/UNKNOWN eksplisit; Laba Kotor bukan Laba Bersih.
- CashSession ringan: satu responsible cashier, satu OPEN per drawer, opening cash eksplisit termasuk Rp0, close race-safe OPEN → CLOSED dengan immutable report. Tidak ada reopen, responsibility overwrite, shared active drawer atau HR shift engine.

## Laravel/Inertia dan privasi response

Stack frozen mengikuti [02](docs/02-ARCHITECTURE.md): Laravel, Inertia, React, TypeScript, PostgreSQL, Nginx, shadcn/ui; dependency/versions aktual diverifikasi dan dipin saat foundation. Jangan menambah Redis/Valkey/worker/search service tanpa kebutuhan terukur dan keputusan canonical.

Server authentication, permission, object/actor/config guards wajib untuk route, mutation, query, status/recovery, print/export dan media. Hidden menu bukan keamanan. Actor berasal dari session; jangan mempercayai role/user/session/register ID client atau menerima mass assignment field privileged.

DTO staff memakai allowlist. Acquisition cost/evidence history/HPP/profit dan audit/security/recovery sensitif hanya Pemilik. Operations dapat mengirim **bukti biaya baru write-only**; bukan membaca nilai itu kembali. Inertia membutuhkan pemeriksaan props halaman, shared/lazy/deferred/partial props, serialized models, validation errors, redirect/flash/session, prefetch/history/cache dan reload/recovery. Jangan menaruh entity finance lengkap di shared props lalu menyembunyikannya dalam React.

Nilai biaya Operations tidak kembali melalui review response, sukses/error, status/duplicate/original result, history/search, export/media/cache/log atau browser recovery storage. Jangan menyimpan payload biaya ke localStorage/IndexedDB/telemetry. Bersihkan field sesudah commit terkonfirmasi; setelah reload pulihkan referensi command dan DTO aman, bukan sensitive request replay. Ketidakpastian hasil membekukan intent asli.

BusinessProfile runtime menetapkan identitas resmi client. Logo/nama BantuKasir repository bukan default produksi. Document snapshot mempertahankan revision/template/aset historis; media privat terotorisasi dan validasi upload mengikuti specification.

## Visual dan DesainPakeAI

Pipeline: `dpai` → approved **BantuKasir Design** revision → repository contract/tokens → shadcn/ui → UI produksi. Gunakan CLI global, bukan MCP. Setup/login tidak diulang setiap task. Sebelum context dan setiap mutation, verifikasi `dpai project current --pretty`: nama BantuKasir Design dan ID `eddb0e15-9909-44b3-8517-9239cadc65ef`; mismatch berarti STOP sebelum akses lanjut.

Jangan memilih, membaca, mengekspor, memutasi atau memakai token/aset/inspirasi project MULTIPLECORP. Revision context teramati bukan approval owner. Production UI consumer yang membutuhkan visual contract menunggu approval exact revision/rights; backend/database/tests independen tidak perlu menunggu visual. Mutation desain/layanan berbayar membutuhkan otorisasi scope.

## Verifikasi dan delapan dimensi DoD

Setiap BU membuktikan applicable **BUSINESS, UI/UX, BACKEND, DATABASE, SECURITY, DATA INTEGRITY, PERFORMANCE dan VERIFICATION** menurut [08](docs/08-TESTING-ACCEPTANCE.md). N/A memerlukan alasan risiko/scope; bukan alasan melewati acceptance terkait. Catat commands, revision source/schema/assets, target/dataset non-secret, hasil, gap, reviewer dan lokasi evidence. Hasil yang belum dijalankan tidak diberi PASS.

- Jalankan backend tests dan static analysis yang diwajibkan, frontend lint/typecheck/build, serta dependency/security checks relevan dengan versi aktual.
- Gunakan PostgreSQL nyata untuk constraints, rollback/failure injection, locks/races, idempotency dan recovery. Mock/screenshot tidak membuktikan transaksi atomik, immutable history atau last-unit concurrency.
- Uji ketiga role/request langsung/object scope; privasi semua props/error/cache/log/document/export/status/recovery; disable/config races dan recent-auth bila relevan.
- UI diuji desktop/mobile 375/440/768/1024/1440, keyboard/touch/focus, 200% zoom, loading/empty/error/denied/stale/UNKNOWN/submitting/definite rejected/uncertain state yang berlaku.
- Scanner/printer/label aktual, populated migration, real independent backup/isolated restore, measured performance dan owner UAT diperlukan pada BU/final point terkait; emulator/fixture tidak menggantikannya.
- Lakukan self-review → independent/adversarial review → fix → rerun checks yang terdampak. Critical money/stock/security/concurrency/recovery mendapat review tambahan secara selektif; catat penggunaan model/reviewer aktual, jangan mengarangnya.

Evidence awal dan BU completion tidak otomatis FINAL PASS lintas seluruh consumer. Evidence final menggunakan revision terintegrasi yang sesuai; perubahan setelahnya membutuhkan rerun terkait. External/owner holds tetap terbuka sampai bukti aktual tersedia. COMPLETE hanya setelah applicable acceptance/DoD/holds yang menentukan penerimaan unit terpenuhi.

## Database dan operasi produksi dilindungi

Tanpa approval owner untuk **operasi destruktif EXACT**, jangan DROP DATABASE/SCHEMA/populated tables, TRUNCATE, mass DELETE, reset/recreate/seed demo produksi, menghapus ledger/Sale/Payment/audit/snapshot atau menulis ulang history, menghapus DB/Docker volume/persistent directory, maupun menjalankan `docker compose down -v`. “Fix”, “deploy” dan “run migration” bukan approval tersebut.

Gunakan forward/additive migration, populated rehearsal pada target non-production yang teridentifikasi, runtime/migration/backup credential terpisah, independent backup/restore readiness serta business reconciliation. Jika destruction tampak diperlukan: STOP, laporkan exact action/data risk, alternatif aman, migration/backfill, backup/restore dan rollback/recovery. Jangan membuat reset script atau mengeksekusi target hanya karena nama folder tampak test.

Target biaya tambahan sekitar Rp0 tidak menghapus backup independen. Backup DB+referenced media/template, encryption/key/access independence, isolated restore, session revoke/recovery epoch dan missing-interval reconciliation mengikuti [09](docs/09-DEPLOYMENT-OPS.md). Tidak ada host/DB/secret/purchase/deployment authorization otomatis dari BU atau kelulusan tests.

## Closeout dan handoff berikutnya

Periksa `git diff --check`, staged diff check dan exact staged/untracked scope. Stage hanya path BU/evidence/docs yang disengaja, preservasi index unrelated, jangan `git add -A` atau blind staging `.local/`/`.agents/`/evidence obsolete. Update [11](docs/11-BUILD-PLAN.md), keputusan nyata di [10](docs/10-DECISIONS.md), gap/change register dan evidence, lalu **[00](docs/00-CURRENT-STATE.md) LAST**.

Commit lokal implementasi hanya bila BU terotorisasi, hasil koheren, applicable checks lulus dan scope bersih. Approval planning satu commit tidak mengotorisasi coding. Jangan push/merge/deploy/publish/beli tanpa instruksi eksplisit. Laporkan exact BU/status, perubahan, commands/hasil/evidence, holds, scope Git, commit bila ada dan next candidate. **STOP setelah BU terpilih; jangan otomatis mengerjakan next.**
