# Bukti rebaseline Plan 2.0

Sesi **1–2 Oktober 2026**, dokumentasi/perencanaan saja. Parent lokal sebelum rebaseline: `f6c9c58fbb250739141f13a04fafab1588134b7c`, branch `plan/bantukasir-pos-v1`. Tidak ada aplikasi, migration, database, browser UI, actual device, restore atau benchmark dijalankan dalam sesi ini.

## Pemeriksaan yang sudah dijalankan sebelum penulisan00 LAST

| Pemeriksaan | Hasil aktual |
| --- | --- |
| Task IDs, headings, dependency | 40 ID unik, masing-masing 13 heading yang diwajibkan; seluruh dependency ada; DAG tanpa cycle |
| Estimasi terhitung | I118h + V94,5h + evidence23,5h =236h; critical-path93h; reserve48h terpisah |
| Acceptance | 37 ID unik, ownership implementasi/verifikasi dan final point; semua NOT STARTED, 0 FINAL PASS |
| Tautan lokal pre-final | 1.032 tautan tersedia pada canonical baru dan Markdown tracked yang dipertahankan;00 lama dikecualikan sampai ditulis terakhir |
| Encoding / credential | UTF-8 strict, tanpa mojibake atau replacement character; tanpa access key DesainPakeAI |
| Whitespace | `git diff --check` dan `git diff --cached --check` exit0; advisory normalisasi LF/CRLF bukan failure |
| Preservasi worktree | 90 source/aset/evidence protected SHA-256 cocok snapshot; assets/brand/README.md dikecualikan eksplisit karena metadata direwrite |
| Preservasi untracked | Seluruh209 file original untracked tetap ada dan SHA-256 sama; termasuk .agents dan evidence/helper historis |
| Preservasi index | Enam object staged BK03 mempunyai OID persis snapshot; tiga source BK03 unstaged juga byte-identik |
| Metadata lokal | Snapshot/helper/setup berada di .local yang Git-ignored; tidak disiapkan untuk commit |

Validator lokal ignored: `.local/rebaseline-snapshot/20261001-plan2/validate.py`, dijalankan melalui bundled Python. Hasil mesin di `validation-result.json` dalam direktori yang sama; helper ini bukan deliverable aplikasi atau dependency produksi. Sesudah00 LAST, ulangi validator seluruh dokumen serta mode staged, whitespace dan exact scope sebelum commit; sesudah commit ulangi parent/scope/index/hash. Hasil closeout final dicatat dalam laporan sesi, tanpa menambah perubahan docs sesudah00.

## Audit independen dan migrasi kebutuhan

Audit domain/keamanan/ops memeriksa spesifikasi lama terhadap canonical baru. Atomicity, source intent, role/privacy, cost UNKNOWN/replay, refund/return, CashSession races/snapshot, protected migrations, independent restore dan recoveryEpoch tetap utuh. Temuan unit/precision lock sesudah movement pertama sudah masuk02/T05/08; catatan kesalahan count append-only masuk17/T17/T31/08.

Audit graph/task memeriksa40cards dan estimasi/dependency. Audit final lintas canonical memeriksa visual source pending, permission/protected DB/recovery serta scope tanpa perlu app coding. Seluruh ownership08 berlaku meskipun shortlist acceptance kartu tidak mengulang setiap ID.

Dokumen03/05/07/13/14/16 dan PRODUCT digabung ke spec canonical/AGENTS/DESIGN/tasks setelah dibaca; old active Impeccable design/surfaces metadata dihapus setelah quality constraints dipindahkan. [11](../11-BUILD-PLAN.md) memuat tabel migrasi. Source, binary, license dan evidence prototype tetap disimpan; [registry](../../prototypes/README.md) menyatakannya histori saja.

## DesainPakeAI read-only

Perintah aktual: `dpai --version`, `dpai project current --pretty`, lalu `dpai context --pretty` hanya setelah nama dan ID cocok. Verifikasi ulang pada 2 Oktober tetap:

- CLI 0.2.2;
- BantuKasir Design / `eddb0e15-9909-44b3-8517-9239cadc65ef`;
- Provenance Thread / alpha.1;
- revision `sha256-53257e5efa2e29e32cfb03e3b83fae1fc464cb27d4d08c9dfa3def4b2e799241`.

Tidak ada design mutation/export, MCP atau akses MULTIPLECORP. Approval visual exact revision dan entitlement/template/font/assets rights belum terbukti. [DESIGN](../../DESIGN.md) menyimpan provenance/approval boundary; context template tidak mengaktifkan leads/target/tim.

## Scope commit yang diotorisasi

Allowlist dokumentasi: **68 path** =17 file canonical/registry direwrite +40 task cards +1 evidence ini +10 obsolete file dihapus. Tidak memasukkan6 path existing staged BK03 atau3 source unstaged. Stage exact allowlist; gunakan `git commit --only -- <exact paths>` untuk menjaga unrelated index. Snapshot original docs00/11/index/patch/worktree sudah dibuat sebelum edit.

Commit ini tidak mengotorisasi T01, production UI, push, merge, deploy, pembelian atau perubahan infrastructure/data. [00](../00-CURRENT-STATE.md) adalah handoff terakhir; task pertama baru dapat dijalankan setelah instruksi eksplisit “Execute T01”.
