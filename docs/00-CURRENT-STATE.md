# 00 — Kondisi lokal dan handoff

**Plan 2.0 — 2 Oktober 2026, Asia/Jakarta. Ditulis LAST** setelah konsolidasi canonical, 40 task cards, 37 acceptance, audit independen dan bukti rebaseline. Ini perubahan dokumentasi/perencanaan; **tidak ada coding aplikasi pada sesi ini**.

| Item | Kondisi terverifikasi |
| --- | --- |
| Repository / branch | yusufarst/bantukasir / `plan/bantukasir-pos-v1` |
| Lineage lokal | Parent sebelum rebaseline `f6c9c58fbb250739141f13a04fafab1588134b7c` — approved BK02; sebelumnya `1f62e98` approved UI01 R6. HEAD sesudah rebaseline diperiksa melalui Git; tidak reset ke remote |
| Plan aktif | **Plan 2.0, 40 Core tasks; 0/40 COMPLETE, semua NOT STARTED** |
| Acceptance produk | **37 suite; 0/37 FINAL PASS**, ownership/final point di08 |
| Executor / next | Tidak ada coding aktif; kandidat pertama **T01**, memerlukan instruksi eksplisit “Execute T01” |
| Runtime fisik | Belum ada package manifest/lockfile, src/db/tests/ops aplikasi produksi; prototype/evidence bukan runtime produksi |
| Engineering | **236h**: I118/V94,5/evidence23,5; critical dependency93h dengan resource independen |
| Kalender satu executor | 29,5 hari×8h /5,9 minggu×40h; reserve48h →284h/35,5hari/7,1minggu, belum elapsed waiting |
| Identitas | BantuKasir codename; nama/logo resmi client runtime masih menunggu owner |
| Visual | DesainPakeAI source ready; approval exact revision/rights **PENDING**. Visual lama superseded |
| Otorisasi sesi ini | Satu local docs-only commit. Tidak mengotorisasi T01, production UI, push/merge/deploy/purchase/infrastructure/DB operation |

## Jalur recovery task

Baca [AGENTS](../AGENTS.md) → file ini → [11](11-BUILD-PLAN.md)/kartu task terpilih → [01](01-PRD.md) → [02](02-ARCHITECTURE.md) → [10](10-DECISIONS.md) → [DESIGN](../DESIGN.md) untuk UI → READ khusus kartu. Handoff lama sudah digabung; tidak perlu membaca16 atau proposal task lama sebagai instruksi.

[11](11-BUILD-PLAN.md) memuat graf exact, per-task Implementation/Verification/Evidence/Total dan hold eksternal terpisah. Backend/database/tests berjalan tanpa approval visual bila independen. T24/UI produksi wajib menunggu T23 COMPLETE dengan approved contract; task consumer hanya memakai evidence teknis provider yang lulus dan kompatibel. Satu executor aktif, tidak otomatis lanjut.

## Pekerjaan lokal yang dipertahankan

Sebelum edit, keadaan lokal mempunyai 8 staged entries, 4 unstaged tracked dan 209 untracked. docs00/11 termasuk staging lama dan docs11 mempunyai perubahan worktree. Keduanya sudah disimpan dalam snapshot sebelum diganti canonical sesuai otorisasi rebaseline.

Pekerjaan unrelated BK03 yang harus tetap berada di luar commit dokumen:

- staged `.impeccable/review/bk03/REVIEW.md` dan `TOOLS.md`;
- staged `prototypes/ui03/index.html`, `script.js`, `shell.js`, `styles.css`;
- perubahan unstaged `script.js`, `shell.js`, `styles.css` di UI03;
- 209 original untracked, termasuk `.agents/` dan evidence/helper historis.

Snapshot ignored `.local/rebaseline-snapshot/20261001-plan2/` menyimpan HEAD/status/index original, git binary patches, byte copies docs00/11/source/evidence, manifest SHA-256 protected dan untracked. Enam OID BK03 staged, tiga source unstaged, 90 protected source/aset/evidence dan seluruh 209 untracked cocok dengan snapshot pada audit final. Dua registry README termasuk perubahan dokumentasi yang diotorisasi; binary/logo/fonts/license tidak berubah.

Stage allowlist **68 path dokumentasi**: 17 rewrites canonical/registry, 40 cards, 1 evidence dan 10 deletions obsolete; commit melalui exact paths `--only`, bukan seluruh index. Sesudah commit, enam staged/ tiga unstaged BK03 serta209 untracked tetap dipertahankan; periksa hash dan status sebelum task baru. Jangan blindly stage `.local/`, `.agents/` atau evidence lama. [Registry prototype](../prototypes/README.md) menetapkan source lama sebagai referensi state/fixture, bukan visual authority.

## DesainPakeAI terkini

CLI global `dpai 0.2.2`, skill global tersedia, authenticated project/context dapat dibaca. Setup metadata ignored `.local/desainpakeai/setup.json`; tidak ada access key dalam docs/repository. Harness codex terdeteksi otomatis; tidak perlu override ulang.

Verifikasi read-only ulang 2 Oktober:

- **BantuKasir Design / eddb0e15-9909-44b3-8517-9239cadc65ef**;
- **Provenance Thread / alpha.1**;
- revision **`sha256-53257e5efa2e29e32cfb03e3b83fae1fc464cb27d4d08c9dfa3def4b2e799241`**;
- prototype `c8384f74-cc1b-4335-a385-2d0fac66d668`, `single-html@1`.

Tidak ada mutation/export/MCP atau akses MULTIPLECORP. Project hanya BantuKasir Design; periksa ulang sebelum setiap mutation dan STOP pada mismatch. Context observed bukan approval. Template leads/target/tim tidak membuka scope GOODS POS. [DESIGN](../DESIGN.md) mengatur approval/lisensi/source-to-semantic-token/native mapping.

## Kontrak dan holds aktif

Core tetap GOODS-only satu pool, tepat tiga role, immutable ledger/nonnegative synchronous stock, atomic full Sale dan durable original-result recovery. Server role/object/config guards, DTO staff allowlist/write-only cost, MWA/UNKNOWN/append-only corrections, race-safe CashSession snapshots, runtime identity, actual devices, forward populated migration, independent backup/isolated restore/recoveryEpoch semuanya wajib.

Plan 1.1/36 tasks/154h, R01–R18/172h, Gate A/B, UI01–04 dan executor/tooling visual lama adalah riwayat. Tidak ada completion produksi atau visual approval baru dari artefak lama. Spesifikasi aktif:01/02/04/06/08/09/10/11/12/15/17 +AGENTS/DESIGN dan cards.

| Gate | Status |
| --- | --- |
| DESIGN | Source/context READY; final selection/rights/owner visual approval PENDING |
| G1 | OPEN — tax/legal nota/buyer policy |
| G2 | OPEN — unit/opening volume/barcode/cost/serial reality |
| G3 | OPEN — drawer/float/responsibility/handover/owner refund funds |
| G4 | OPEN — independent destination/key/access/cost/measured RPO/RTO; 6h/4h masih proposal |
| DEVICES | PENDING — actual USB/Bluetooth HID, label/receipt printers |
| RELEASE | NOT AUTHORIZED — target/VPS/domain/secrets, exact deployment approval dan pilot |

Hold berlaku hanya pada pekerjaan/penerimaan yang membutuhkan fakta tersebut; independent non-production engineering tidak perlu idle. Tidak ada izin reset/demo seed/destructive production dari dokumen/task. [09](09-DEPLOYMENT-OPS.md) tetap mewajibkan approval operasi EXACT dan forward/recovery safeguards.

## Validasi dan langkah berikut

[Bukti rebaseline](evidence/plan2-validation.md) mencatat audit/migrasi kebutuhan/commands dan preservasi. Validator memeriksa link, encoding/credential, 40 cards, DAG/236h/93h, 37 acceptance/final owners serta index/worktree/untracked hashes. Whitespace working/staged lulus; setelah penulisan LAST ini ulangi seluruh link dan exact staged scope, lalu parent/scope/preservation sesudah commit. App tests/browser/devices/migration/restore/performance tidak dijalankan dan tidak diklaim lulus.

Task coding pertama adalah **[T01 — Fondasi aplikasi dan PostgreSQL terisolasi](tasks/T01.md)**, 7h (4 implementasi/2 verifikasi/1 docs), tanpa visual UI produksi. Sesi rebaseline berhenti sesudah local documentation commit; **jangan execute T01 sampai owner memberi instruksi eksplisit**.
