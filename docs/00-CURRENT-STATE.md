# 00 — Kondisi lokal dan handoff

**Baseline P0–P11 — 2 Oktober 2026, Asia/Jakarta. Ditulis LAST** setelah specification, review/fix, mapping/DAG/estimasi dan validasi paket. Status planning **BLUEPRINT_FROZEN_WITH_HOLDS**; implementasi aplikasi belum dimulai. Freeze bukan approval visual owner, penerimaan fakta client atau bukti runtime produksi.

| Item | Fakta dan batas terkini |
| --- | --- |
| Repository / branch | yusufarst/bantukasir / `plan/bantukasir-pos-v1` |
| Lineage | Parent paket ini `c4a145d5018a18d1335171fae362314133970a26` (Plan 2.0); sebelumnya `f6c9c58` approved BK02 dan `1f62e98` approved UI01 R6. SHA hasil commit paket dibaca dari Git; tidak reset ke remote |
| Baseline aktif | Specification P0–P10 dan **28 Build Units baru; 0/28 COMPLETE, semua NOT STARTED** |
| Traceability / acceptance | **73 Core requirement → BU + acceptance; 39 suite, 0/39 FINAL PASS** |
| Stack dibekukan | **Laravel + Inertia + React/TypeScript + PostgreSQL + Nginx**, shadcn/ui, PHP-FPM, Vite build-time, session/cache-limiter database-backed |
| Runtime fisik | Belum ada package/composer manifest, lockfile, app/schema/runtime produksi yang diinisialisasi. Prototype/evidence historis bukan production application |
| Engineering dari nol | **259h = implementasi113 / verifikasi118 / evidence28**; reserve56h terpisah, total315h bila seluruh reserve terpakai |
| Critical path | **128h**, dependency lower bound dengan resource independen, tanpa waiting; bukan kalender satu executor |
| Kalender satu executor | Sekitar6,5 minggu×40h tanpa reserve; sekitar7,9 minggu dengan reserve. Owner/external waiting nyata ditambahkan terpisah, belum dapat diestimasi |
| Executor berikut | Label yang diminta owner Claude Opus 5.5/xhigh; Fable 5.1/xhigh selektif. Availability belum diverifikasi; tidak dijalankan pada sesi planning |
| Unit pertama | **BU-FOUND-001**, 8h (I4/V3/E1), tanpa dependency; belum diotorisasi/dijalankan |
| Otorisasi sesi | Satu commit lokal planning/docs-only sesudah final scope/checks lulus, lalu STOP. Tidak mengotorisasi coding aplikasi, push/merge/deploy/purchase atau operasi produksi |

## Pemulihan konteks dan eksekusi

Periksa Git aktual, lalu baca [AGENTS](../AGENTS.md) → file ini → [11](11-BUILD-PLAN.md)/kartu BU terpilih → [01](01-PRD.md) → [02](02-ARCHITECTURE.md) → [10](10-DECISIONS.md) → READ specification kartu. Executor membaca [CLAUDE](../CLAUDE.md); UI membaca [DESIGN](../DESIGN.md). [P11](planning/P11-BUILD-UNITS.md) merekam freeze lintas fase; [CORE-MAP](requirements/CORE-MAP.md) dan [08](08-TESTING-ACCEPTANCE.md) menjaga traceability/acceptance.

Instruksi eksplisit **Execute BU-...** memilih satu unit; NEXT bukan izin otomatis. Periksa dependency artifact/PASS/revision/holds dan model harness sebelum coding. Backend/database/tests/ops non-production yang independen tidak menunggu visual; seluruh UI produksi menunggu **BU-DESIGN-001 COMPLETE dengan owner approval exact revision dan rights**. Backend COMPLETE hanya membuktikan delivery bounded, bukan product FINAL PASS.

Paket readiness BU-RELEASE-001 diselesaikan untuk review konkret sebelum meminta izin deployment. Approval exact RELEASE hanya membuka BU-RELEASE-002; readiness, commit dan Execute coding biasa tidak mengotorisasi operasi produksi.

## Kebenaran fisik dan preservasi

Intake rebaseline ini bersih: **0 staged / 0 unstaged / 0 untracked**, HEAD c4a145d. Leftovers BK03 yang disebut current-state historis sebelumnya tidak ada pada intake aktual ini. Tidak menebak actor/proses yang mengubahnya, tidak merekonstruksi source UI03 dan tidak menjalankan reset/clean/restore.

Snapshot ignored `.local/rebaseline-p0-p11/before/` menyimpan dokumen awal dan manifest. **85 source/aset/evidence yang dilindungi cocok byte-for-byte dengan SHA-256 awal**, termasuk UI01/UI02, binary/logo/font/license, `.env.example` dan evidence Impeccable historis. [PRESERVATION](evidence/rebaseline-p0-p11/PRESERVATION.json) menyimpan hash non-secret; snapshot/helper ignored bukan deliverable staging.

40 kartu Plan 2.0 diarsipkan pada [archive](archive/plan2/README.md) dengan banner historis dan link relatif yang benar; canonical lama tersedia melalui Git parent. Requirement valid diserap ke baseline baru. Stage exact hanya root/canonical documentation, P0–P11/gap, Core/BU registries/cards, archive dan latest evidence rebaseline. Jangan stage `.local/`, `.agents/`, source prototype/aset atau unrelated work; review seluruh index sebelum commit.

Plan 2.0/T01–T40, Plan 1.1/Rxx/BKxx/UIxx/Gate A/B dan workflow/tooling executor lama adalah histori. Approval UI01/UI02 historis tidak menjadi approval desain produksi baru. [Registry prototype](../prototypes/README.md) hanya mengizinkan referensi state/fixture/perilaku yang relevan.

## DesainPakeAI aktual dan visual hold

Skill global tersedia dan dibaca; CLI global yang sudah terpasang dipakai sebagai **`dpai 0.2.2`**. Cek awal gagal karena PATH sandbox; lokasi npm global user dipakai dalam sesi. Tidak install/setup/login ulang, tidak menyimpan credential dalam repository.

`dpai project current --pretty` diverifikasi sebelum `dpai context --pretty`:

- **BantuKasir Design / `eddb0e15-9909-44b3-8517-9239cadc65ef`**;
- context revision **`sha256-53257e5efa2e29e32cfb03e3b83fae1fc464cb27d4d08c9dfa3def4b2e799241`**;
- prototype **`c8384f74-cc1b-4335-a385-2d0fac66d668`**, `single-html@1`;
- panduan authenticated prototype-to-codebase dibaca melalui CLI untuk semantic/native mapping.

Ini context teramati, bukan approval/lisensi/visual parity. Tidak ada mutation/export/MCP, layanan berbayar/pembelian atau akses project MULTIPLECORP. Verifikasi project lagi sebelum context dan setiap mutation yang nantinya diotorisasi; mismatch berarti STOP. Kerangka governance MULTIPLECORP dari diskusi owner tidak mengizinkan penggunaan domain/aset/token/project desainnya.

Pipeline: DesainPakeAI exact owner-approved BantuKasir Design revision → repository semantic contract/tokens → shadcn/ui → React/Inertia native. Tidak ship prototype HTML/iframe atau membangun ulang bundle UI lama. BantuKasir codename internal; nama/logo client memakai BusinessProfile runtime versioned, dengan dokumen original membekukan identity/template/aset.

## Kontrak inti dan holds

GOODS-only satu stock pool dan tepat tiga role. Product create tidak membuat stok; immutable StockMovement/projection sinkron nonnegative; biaya Operations write-only; staff DTO allowlist seluruh kanal, termasuk Inertia/history/flash/errors/cache/storage. MWA/UNKNOWN eksplisit; Laba Kotor owner-only, bukan Laba Bersih. Auth history encrypted dan key/cache lifecycle mencegah finance lama terlihat setelah pergantian actor; input biaya write-only tetap tidak dipersist meskipun terenkripsi.

Sale langsung selesai dengan satu full CASH **atau** BANK_TRANSFER; tanpa split/mixed/partial/debt/gateway V1. Future provider seam hanya boundary arsitektur. Sale/lines/full Payment/issue/balance/cost/snapshot/audit/result commit bersama. Stable source/key/fingerprint/epoch mempertahankan original recovery; timeout/restore bukan izin replacement Sale atau pembayaran ulang.

CashSession ringan satu responsible cashier/OPEN per register dan cashier; opening0 eksplisit; close race-safe dan report immutable. Refund/return/correction mempertahankan original; refund tanpa barang fisik tidak menambah stok. Reversal/replay memvalidasi chronology dan pair tepat sekali; publikasi all-or-nothing. Guard role/object/actor/config, forward migration, independent backup/isolated restore/reconciliation/gap wajib.

| Gate | Status aktual dan bagian yang tertahan |
| --- | --- |
| DESIGN | PENDING — selection/rights/owner visual approval exact revision; BU-DESIGN-001 COMPLETE dan seluruh UI produksi |
| G1 | OPEN — tax/legal commercial nota/buyer policy; acceptance dokumen client/cutover |
| G2 | OPEN — unit/precision/master/opening volume/barcode/evidence/serial reality; data bisnis/cutover nyata |
| G3 | OPEN — drawer/float/single responsibility/handover/count/funding refund Pemilik; workflow fisik/pilot |
| G4 | OPEN — independent destination/key/access/cost dan measured RPO/RTO; REC final/pilot. RPO6h/RTO4h masih proposal |
| DEVICES | PENDING — actual USB/Bluetooth HID, label/thermal/A4 printer/media/driver; hardware final |
| TARGET | PENDING — actual authorized host/network/TLS/capacity; representatif performance/ops evidence |
| EXECUTOR | NOT VERIFIED — availability label model yang diminta pada harness tujuan sebelum handoff |
| RELEASE | NOT AUTHORIZED — exact target/revision/operation deploy/cutover serta owner pilot |
| TESTS | NOT STARTED — seluruh39 suite; bukan gap keputusan owner dan bukan product PASS |

[GAPS](planning/GAPS-CHANGES.md) membatasi scope terdampak. Blueprint freeze tidak menutup gate owner/eksternal. Fakta baru yang mengubah bisnis membuka kembali fase/BU/acceptance/estimasi terkait; jangan mengarang fakta client. Database produksi dilindungi menurut [09](09-DEPLOYMENT-OPS.md): approval destruction EXACT terpisah, tanpa DROP/TRUNCATE/mass delete/reset/demo seed/down-v atau historical rewrite.

## Bukti dan penutupan

[REVIEW](evidence/rebaseline-p0-p11/REVIEW.md) mencatat reviewer aktual, temuan HIGH/MEDIUM/fix, boundary proof, source riset dan tooling. [VALIDATION](evidence/rebaseline-p0-p11/VALIDATION.json) adalah hasil pre-CURRENT_STATE aktual: **350 checks PASS**, DAG28 acyclic, Core73/suite39, mapping/card/registry/table/edges/effort/link/UTF-8/no credential patterns serta hash85. Mode itu mengecualikan old00; sesudah penulisan LAST ini ulangi read-only validator yang mencakup00.

`git diff --check` lulus sebelum00; LF→CRLF warning Git Windows bukan failure. Finalisasi masih wajib memeriksa validator penuh, working/staged whitespace, staged UTF-8/exact scope, protected hashes dan parent/status sesudah satu commit lokal. SHA commit/result final dapat diverifikasi dari Git dan laporan closeout; jangan menafsirkan bukti pre-state sebagai klaim perintah staging/commit yang belum dijalankan.

App unit/HTTP/PostgreSQL/races/browser/mobile/a11y/perangkat/migration/restore/performance/pilot tidak dijalankan atau diklaim PASS dalam planning ini. Tidak ada coding, produksi, push, merge atau deploy.

Berikutnya tepat **[BU-FOUND-001 — Fondasi Laravel, React dan harness PostgreSQL terisolasi](build-units/BU-FOUND-001.md)**, **8h (I4/V3/E1)**. Sesi ini STOP setelah satu commit dokumentasi lokal yang aman. **Jangan execute BU-FOUND-001 sampai owner memberi otorisasi eksplisit.**
