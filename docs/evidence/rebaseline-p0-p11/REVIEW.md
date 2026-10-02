# Bukti review rebaseline P0–P11

2 Oktober 2026, Asia/Jakarta. **Bukti planning/dokumentasi; bukan bukti implementasi aplikasi atau approval visual/produksi.** Parent intake: `c4a145d5018a18d1335171fae362314133970a26`, branch `plan/bantukasir-pos-v1`. Instruksi owner terbaru mengotorisasi paket P0–P11 dan satu commit planning lokal; tidak mengotorisasi eksekusi Build Unit.

## Intake dan preservasi

`git status --short`, branch, HEAD, commit terbaru serta staged/unstaged/untracked diperiksa sebelum perubahan. Intake aktual **0 staged, 0 unstaged, 0 untracked**. Klaim leftovers BK03 pada current-state sebelumnya adalah riwayat; source UI03/evidence yang disebut tidak ada pada intake ini. Tidak ada reset/restore/clean atau rekonstruksi file tersebut. Lineage c4a145d → f6c9c58 → 1f62e98 dipertahankan.

Snapshot ignored `.local/rebaseline-p0-p11/before/` merekam dokumen awal, manifest 144 tracked files dan SHA-256 85 source/aset/evidence yang dilindungi. [Manifest preservasi](PRESERVATION.json) menyimpan hash non-secret agar pemeriksaan dapat diulang. Seluruh 85 file cocok pada pemeriksaan paket. Source UI01/UI02, CSS/JavaScript, binary/font/logo/license, `.env.example` dan evidence Impeccable historis tidak diubah. Hanya README registry prototype/brand diperbarui.

40 kartu T01–T40 dipindahkan ke `docs/archive/plan2/tasks/`, diberi banner historis dan link relatif yang sesuai. Canonical Plan 2.0 tetap dapat ditelusuri pada Git parent. Requirement serta 37 acceptance valid diserap; ID/boundary/estimasi lama tidak dipertahankan sebagai rencana aktif.

## Review yang benar-benar dilakukan

Review adalah satu paket lintas fase yang berkembang melalui temuan dan koreksi. Tiga reviewer internal bekerja pada area berbeda; ini **bukan klaim 12 sesi independen terpisah**. Penulis dan reviewer dibedakan; primary planner memeriksa kontribusi reviewer yang juga menulis dokumen fase. Claude Opus 5.5/Fable 5.1 adalah label handoff yang diminta, tidak digunakan atau diverifikasi tersedia dalam sesi ini.

| Area/fase | Penulisan dan pemeriksaan aktual | Hasil blueprint / batas |
| --- | --- | --- |
| P0 governance, continuity dan otorisasi | `governance_rebaseline_review` menulis P0/CLAUDE; primary memeriksa authority, holds, satu commit dan larangan coding. Governance reviewer memeriksa canonical/package lain | Kontrak otorisasi dan checkpoint koheren; tidak ada approval coding/produksi |
| P1–P3 scope, domain dan workflow | Primary menulis canonical dan P1/P2; `domain_rebaseline_review` menulis P3. Primary memeriksa P3; domain/governance reviewer memeriksa requirement canonical, roles, uang/stok/session/recovery dan mapping | Scope minimum dipertahankan; no split/partial/gateway V1; fakta client tetap bounded holds |
| P4 pilihan stack dan struktur | `stack_rebaseline_review` membandingkan A/B dengan sumber primer dan menulis P4; primary memilih B dan memeriksa tradeoff. Domain reviewer memeriksa P4 terhadap02/04/06/15/17 dan Build Units | Laravel/Inertia/React/TypeScript/PostgreSQL/Nginx dibekukan sebagai blueprint; versi/advisory/host belum dipin atau diuji |
| P5–P6 security, API dan concurrency | Primary menulis kontrak, governance reviewer memeriksa threat/control/defaults; domain reviewer melakukan adversarial check privasi/history/reversal/guard/idempotency | Temuan HIGH/MEDIUM diperbaiki; real PostgreSQL/browser proofs tetap NOT STARTED |
| P7 IA/interaction/design boundary | Primary dan governance reviewer memeriksa role-safe IA, shell/cart/account/period states, accessibility dan exact visual approval boundary | Blueprint IA/interaction koheren; belum ada token final/visual parity/owner visual approval |
| P8 acceptance/DoD | Primary mengonsolidasikan39 suite; domain/governance reviewer memeriksa provider, final point dan early-vs-final evidence | 39 suite NOT STARTED; 0 FINAL PASS |
| P9–P10 operasi/recovery/release | Primary/governance reviewer memeriksa target guard, independent backup, populated rehearsal, epoch/gap, readiness dan exact release/destruction authorization | Runbook generic dibekukan; destination/key/host/devices/RPO/RTO/pilot belum dibuktikan |
| P11 requirements/Build Units/estimasi | Primary membuat28 BU/73Core/DAG; domain reviewer memeriksa mapping/dependency/total/critical path. Governance reviewer memeriksa clarity/holds/DoD dan boundary unit | Boundary provider/consumer koheren; semua BU NOT STARTED, pertama belum diotorisasi |

## Temuan dan koreksi

| Temuan | Severity | Koreksi specification dan pemeriksaan ulang |
| --- | --- | --- |
| History/cache Inertia sesudah Pemilik logout dapat mengembalikan finance kepada staff melalui Back/Forward/bfcache | HIGH | 04/P4/P5 menetapkan enkripsi history dengan lifecycle key, clear/rotate saat actor berubah, pembersihan remembered/query cache, private/no-store dan revalidasi sebelum render. Input biaya receiving tetap tidak pernah dipersist meskipun terenkripsi. IAM menyediakan kontrol HTTP; UI1 menguji browser shell; VERIFY2 menguji seluruh consumer finance lintas role. Domain reviewer memeriksa ulang; AUTH02/SEC01 belum diuji aplikasi |
| Ambiguitas reversal dapat menghitung original dan inverse dua kali dalam valuation atau hanya memeriksa balance terkini | HIGH | 15/INV3 menetapkan physical ledger original+inverse; valuation logical cancellation berpasangan tepat sekali, validasi seluruh chronology/prefix, atomic publication dan retained allocation revisions. Fixture analitis safe OPEN10@100 + RECEIPT10@200 − ISSUE5 menghasilkan physical15/value225; reversal receipt menghasilkan physical5/value50 dan revised issue50 dengan original75 tetap. Prefix unsafe RECEIPT10→ISSUE10→RECEIPT20 ditolak walau balance akhir cukup. Ini analisis specification, bukan test runtime |
| UI Kasir bergantung pada own-history/report yang belum dialokasikan ke provider domain | MEDIUM | BU-SALE-001 menyediakan own Sale/status/receipt reads; BU-CASH-002 menyediakan own-session/report/history. UI3 tidak menunggu finance Pemilik; DAG tetap valid |
| Konfigurasi register Pemilik kurang eksplisit pada consumer UI | MEDIUM | CASH1/UI4 memuat bounded code/label/active configuration, recent-auth/audit dan no-OPEN guard; identitas history dipertahankan. CORE-051 menghubungkan UI4 |
| Core device/performance belum menghubungkan seluruh producer ke final evidence | MEDIUM | CORE-035/036 menambahkan producer UI/catalog/inventory/sales/reporting/ops yang relevan dan VERIFY3/4; acceptance tags serta kartu diselaraskan |
| IAM meminta proof browser/finance sebelum UI tersedia | MEDIUM | COMPLETE IAM memakai server flags/key/headers/DTO HTTP proof; browser shell/akun/config milik UI1; final full finance Pemilik→staff milik VERIFY2. Tidak menambah dependency cycle atau menurunkan AUTH02 |
| Hold readiness berpotensi meminta approval deployment sebelum paket review tersedia | MEDIUM | RELEASE001 mewajibkan readiness facts dan evidence teknis, tetapi dapat COMPLETE sebelum otorisasi deployment. Approval exact RELEASE menahan start/COMPLETE RELEASE002 saja. Governance reviewer menemukan ambiguitas; primary menyelaraskan kartu/registry dengan P10 yang menempatkan otorisasi sebagai langkah terakhir |
| Detail threat/config/link/editorial tidak konsisten | LOW | HSTS/CSP syntax diperjelas, limiter identity AND IP AND global, BANK_TRANSFER enum konsisten, opening Pemilik saja, READ provider dibedakan, folder02/P4 diselaraskan, active Txx refs dibersihkan dan link archive dikoreksi |

Adversarial recheck domain menyatakan PASS bounded sesudah perbaikan HIGH/MEDIUM pada areanya. Governance review memeriksa authority/holds/DoD dan kejelasan28 kartu; primary memperbaiki klarifikasi readiness terakhir sesuai P10, lalu validator memeriksa sinkronisasi. Blueprint tidak mempunyai unresolved contradiction dalam cakupan pemeriksaan ini; bukti implementasi tetap diperlukan pada masing-masing BU.

## Penggunaan tooling desain aktual

Skill global `C:/Users/Yusuf Setiawan/.agents/skills/desainpakeai/SKILL.md` dibaca. Cek awal `dpai --version` di sandbox gagal karena command tidak ditemukan. Instalasi global yang sudah ada ditemukan melalui lokasi npm user; sesi memakai PATH ke lokasi tersebut. **Tidak ada install/setup/login ulang.**

| Command aktual | Hasil non-secret |
| --- | --- |
| `dpai --version` | 0.2.2 |
| `dpai project current --pretty` sebelum context | BantuKasir Design / `eddb0e15-9909-44b3-8517-9239cadc65ef` |
| `dpai context --pretty` | Revision `sha256-53257e5efa2e29e32cfb03e3b83fae1fc464cb27d4d08c9dfa3def4b2e799241`; prototype `c8384f74-cc1b-4335-a385-2d0fac66d668`, `single-html@1` |
| `dpai --help` dan `dpai guide get --topic prototype-to-codebase --pretty` | Panduan authenticated dibaca: semantic mapping, native target writer, foundation-first, fixture bukan backend, parity dan gap yang jujur |

Tidak ada mutation, export, MCP, pembelian/usage berbayar atau akses project MULTIPLECORP. Context teramati tidak memberikan visual approval/rights. [DESIGN](../../../DESIGN.md) mengatur exact source revision → semantic contract → native React/Inertia/shadcn; prototype HTML tidak menjadi runtime produksi.

Sumber primer perbandingan framework dan Inertia tercantum pada [P4](../../planning/P4-ARCHITECTURE.md). Perkiraan penghematan B 8–16h adalah penilaian engineering dengan caveat, bukan hasil benchmark.

## Pemeriksaan dokumen dan batas bukti

Validator Node lokal ignored dijalankan dengan `node .local/rebaseline-p0-p11/validate.cjs --pre-state` sebelum CURRENT_STATE ditulis terakhir. Hasil aktual paket pada [VALIDATION](VALIDATION.json) memeriksa JSON, IDs, Core→BU+acceptance, cards, link lokal, UTF-8/mojibake/credential patterns, DAG, per-unit hours/tabel/edges, hash85 dan larangan inisialisasi aplikasi. Mode tersebut secara eksplisit mengecualikan old00 yang akan diganti; pemeriksaan final read-only menyertakan00.

`git diff --check` dijalankan dan lulus. Peringatan konversi LF→CRLF berasal dari konfigurasi Git Windows, bukan whitespace failure. Sesudah00LAST, closeout wajib mengulang validator, `git diff --check`, `git diff --cached --check`, exact staged scope/encoding/protected hash dan parent/status sesudah satu commit. Perintah yang belum dijalankan tidak dianggap lulus oleh paragraf ini; hasil closeout dibaca dari current state/laporan Git.

Tidak ada package/composer manifest, schema/runtime aplikasi yang diinisialisasi. Tidak menjalankan PHP/TypeScript/app unit tests, PostgreSQL races, browser/mobile/a11y, physical devices, restore/migration, performance atau deployment. **0/39 FINAL PASS dan 0/28 BU COMPLETE.**

Engineering dari nol **259h = I113/V118/E28**, reserve56h terpisah (315h bila seluruhnya terpakai). DAG28 node acyclic; longest dependency path128h merupakan lower bound resource-independen, bukan kalender satu executor. Rencana satu executor sekitar6,5 minggu×40h tanpa reserve, sekitar7,9 minggu dengan reserve, ditambah waiting nyata yang belum dapat diestimasi.

## Holds dan langkah berikut

[GAPS](../../planning/GAPS-CHANGES.md): DESIGN PENDING; G1–G4 OPEN; DEVICES/TARGET PENDING; EXECUTOR NOT VERIFIED; RELEASE NOT AUTHORIZED. RPO6h/RTO4h masih proposal. Freeze blueprint tidak menutup holds tersebut.

Unit pertama tepat **[BU-FOUND-001](../../build-units/BU-FOUND-001.md)**, 8h (I4/V3/E1), belum diotorisasi/dijalankan. Sesi berhenti setelah satu commit planning lokal yang aman; tidak otomatis coding/push/merge/deploy atau execute unit berikutnya.
