# 11 — Build Units, DAG, estimasi dan tracker

Baseline P0–P11 — **27 BU implementasi, 0/27 COMPLETE, semua NOT STARTED**. **P11 REOPENED_DRAFT / PENDING_P7_APPROVAL.** [P11](planning/P11-BUILD-UNITS.md) mempertahankan input P0–P6 yang dibekukan serta draf P8–P11 yang harus difinalisasi ulang setelah P7 COMPLETE; [CORE-MAP](requirements/CORE-MAP.md) memuat 73 requirement dan [08](08-TESTING-ACCEPTANCE.md) memuat 39 suite/0 FINAL PASS. Plan 2.0/T01–T40 menjadi [input historis](archive/plan2/README.md), bukan instruksi task pertama yang aktif.

## Eksekusi dan hold

Baca AGENTS → 00 → dokumen ini/kartu → 01/02/10 → READ specification; [CLAUDE](../CLAUDE.md) menyediakan handoff portable. Satu primary executor menjalankan satu **Execute BU-...** yang diotorisasi eksplisit; independent review boleh paralel, tanpa otomatis melanjutkan next. Otorisasi koreksi saat ini hanya docs/design-planning dan satu commit lokal yang aman, tanpa implementasi aplikasi. P7 harus menghasilkan desain aktual DesainPakeAI, approval owner exact revision serta DESIGN/kontrak token yang dinormalisasi; P8–P11 kemudian direview ulang/FROZEN sebelum Execute satu BU.

Artifact provider coding harus mempunyai scope/evidence PASS pada revision kompatibel. Backend COMPLETE berarti evidence backend bounded, bukan workflow client/produk FINAL. **Prasyarat seluruh coding: P7 COMPLETE → P8–P11 difinalisasi ulang/FROZEN → Execute satu BU eksplisit.** Provider planning P7/approved DESIGN dipisahkan dari coding dependency pada UI/DOC dan CORE-030/034/070; `approvedRevision: null` berarti owner approval belum tersedia. Kandidat aktual `sha256-b51d2975aa3a8a92` berstatus **WAITING OWNER VISUAL REVIEW, NOT COMPLETE**; compile dan browser checks bounded tercatat pada [evidence](evidence/p7-correction/REVIEW.md), termasuk validation/rights holds. Tidak ada acceptance runtime FINAL PASS. Backend/database/tests/ops independen secara teknis baru dapat dijadwalkan setelah gate paket tersebut.

Hold final/evidence test mengikuti [GAPS](planning/GAPS-CHANGES.md)/[08](08-TESTING-ACCEPTANCE.md). Label model owner: Claude Opus 5.5/xhigh; Fable 5.1/xhigh hanya untuk eskalasi bounded berisiko tinggi. Availability diverifikasi sebelum eksekusi; jangan mengklaim telah digunakan. RELEASE untuk exact target/revision/operation dan approval destruktif tetap terpisah.

## Estimasi dari nol

I = implementasi, V = verifikasi terarah wajib, E = evidence/docs (jam fokus). Boundary dan angka dirancang ulang berdasarkan scope aktual, integrasi Laravel, decimal presisi/race PostgreSQL/privasi/UI nyata/recovery/perangkat; bukan scaling 236h lama atau throughput token AI. Seluruh jam merupakan penilaian engineering, bukan benchmark/perjanjian. Pembelajaran framework/model, host aktual atau perubahan policy dapat menambah kerja; reestimasi sesudah evidence.

| BU | Hasil | Dependency artifact | I | V | E | Total | Status |
| --- | --- | --- | ---: | ---: | ---: | ---: | --- |
| [BU-FOUND-001](build-units/BU-FOUND-001.md) | Fondasi Laravel, React dan harness PostgreSQL terisolasi | — | 4 | 3 | 1 | 8 | NOT STARTED |
| [BU-IAM-001](build-units/BU-IAM-001.md) | Autentikasi, lifecycle akun dan otorisasi tiga role | BU-FOUND-001 | 5 | 4 | 1 | 10 | NOT STARTED |
| [BU-CMD-001](build-units/BU-CMD-001.md) | Command identity, audit atomik dan original-result recovery | BU-IAM-001 | 5 | 4 | 1 | 10 | NOT STARTED |
| [BU-CFG-001](build-units/BU-CFG-001.md) | BusinessProfile, config guards dan logo revision privat | BU-CMD-001 | 4 | 3 | 1 | 8 | NOT STARTED |
| [BU-CAT-001](build-units/BU-CAT-001.md) | Master barang, harga, Product Search dan registry barcode | BU-CMD-001 | 5 | 4 | 1 | 10 | NOT STARTED |
| [BU-INV-001](build-units/BU-INV-001.md) | Immutable ledger, balance sinkron dan MWA UNKNOWN | BU-CAT-001 | 6 | 5 | 1 | 12 | NOT STARTED |
| [BU-INV-002](build-units/BU-INV-002.md) | Receiving, manual issue dan opening owner-only | BU-INV-001 | 5 | 4 | 1 | 10 | NOT STARTED |
| [BU-INV-003](build-units/BU-INV-003.md) | Owner adjustment, evidence revision dan reversal/replay berbatas | BU-INV-002 | 7 | 5 | 1 | 13 | NOT STARTED |
| [BU-CASH-001](build-units/BU-CASH-001.md) | Logical register dan pembukaan sesi kas sendiri | BU-CMD-001 | 3 | 3 | 1 | 7 | NOT STARTED |
| [BU-SALE-001](build-units/BU-SALE-001.md) | Sale full-payment atomik dan receipt snapshot | BU-CFG-001, BU-INV-002, BU-CASH-001 | 7 | 5 | 1 | 13 | NOT STARTED |
| [BU-CASH-002](build-units/BU-CASH-002.md) | Race-safe close dan immutable session report | BU-SALE-001 | 4 | 4 | 1 | 9 | NOT STARTED |
| [BU-SALE-002](build-units/BU-SALE-002.md) | Bounded owner refund dan verified physical return | BU-SALE-001, BU-INV-003 | 5 | 4 | 1 | 10 | NOT STARTED |
| [BU-READ-001](build-units/BU-READ-001.md) | Current stock, LOW/OUT dan safe operational history | BU-INV-002 | 3 | 3 | 1 | 7 | NOT STARTED |
| [BU-READ-002](build-units/BU-READ-002.md) | Owner finance, sesi dan audit reads dengan scope ketat | BU-SALE-002, BU-CASH-002 | 5 | 4 | 1 | 10 | NOT STARTED |
| [BU-OPS-001](build-units/BU-OPS-001.md) | Packaging self-host, Nginx TLS dan SQL privilege protection | BU-FOUND-001 | 4 | 3 | 1 | 8 | NOT STARTED |
| [BU-OPS-002](build-units/BU-OPS-002.md) | Reconciliation, independent backup dan restore tooling | BU-OPS-001, BU-SALE-002, BU-CASH-002 | 5 | 4 | 1 | 10 | NOT STARTED |
| [BU-UI-001](build-units/BU-UI-001.md) | Native shared shell, autentikasi dan account/config UI | BU-IAM-001, BU-CFG-001 | 4 | 3 | 1 | 8 | NOT STARTED |
| [BU-UI-002](build-units/BU-UI-002.md) | Product Operations lengkap: katalog, receipt, opening dan current stock | BU-UI-001, BU-CAT-001, BU-INV-002, BU-READ-001 | 7 | 5 | 1 | 13 | NOT STARTED |
| [BU-UI-003](build-units/BU-UI-003.md) | POS draft/scanner, full payment, open/close dan own-history UI | BU-UI-001, BU-SALE-001, BU-CASH-002 | 7 | 5 | 1 | 13 | NOT STARTED |
| [BU-UI-004](build-units/BU-UI-004.md) | Owner corrections, finance/session/audit dan system UI | BU-UI-001, BU-READ-002, BU-OPS-002 | 7 | 5 | 1 | 13 | NOT STARTED |
| [BU-DOC-001](build-units/BU-DOC-001.md) | Code128 label, thermal/A4 nota dan immutable session exports | BU-UI-001, BU-READ-002 | 4 | 4 | 1 | 9 | NOT STARTED |
| [BU-VERIFY-001](build-units/BU-VERIFY-001.md) | Final populated migration dan independent restore/recovery acceptance | BU-OPS-002, BU-DOC-001, BU-UI-004 | 0 | 7 | 1 | 8 | NOT STARTED |
| [BU-VERIFY-002](build-units/BU-VERIFY-002.md) | Final integrated business, RBAC/privacy, security dan UX acceptance | BU-UI-002, BU-UI-003, BU-UI-004, BU-DOC-001 | 0 | 8 | 1 | 9 | NOT STARTED |
| [BU-VERIFY-003](build-units/BU-VERIFY-003.md) | Final scanner dan label/receipt printer nyata | BU-UI-002, BU-UI-003, BU-DOC-001 | 0 | 5 | 1 | 6 | NOT STARTED |
| [BU-VERIFY-004](build-units/BU-VERIFY-004.md) | Final representative VPS performance dan bounded replay capacity | BU-UI-003, BU-UI-004, BU-OPS-001 | 0 | 5 | 1 | 6 | NOT STARTED |
| [BU-RELEASE-001](build-units/BU-RELEASE-001.md) | Readiness, cutover/UAT package dan owner gates | BU-VERIFY-001, BU-VERIFY-002, BU-VERIFY-003, BU-VERIFY-004 | 1 | 3 | 1 | 5 | NOT STARTED |
| [BU-RELEASE-002](build-units/BU-RELEASE-002.md) | Authorized production cutover, owner pilot dan handoff | BU-RELEASE-001 | 3 | 4 | 1 | 8 | NOT STARTED |

JSON [registry](build-units/registry.json) menyimpan jam/dependency/status/commands/data/action/risiko exact. Kartu mempunyai READ/requirement/acceptance/tables/actions/UI/permission/races/VERIFY/STOP/UPDATE/COMMIT/NEXT; klausul canonical tetap berlaku. Registry mencatat planningProviders P7 dan approvedRevision null pada UI/DOC, selain coding deps. Pengurangan satu BU memindahkan tanggung jawab desain/approval pertama sepenuhnya ke P7; scope bisnis dan final owners tidak dikurangi.

## Dependency DAG

```mermaid
flowchart TD
P0_P6["P0–P6: input frozen"] --> P7_COMPLETE["P7: actual design + exact owner approval + DESIGN normalized; belum COMPLETE"]
P7_COMPLETE --> P8_P11_FROZEN["P8–P11: refinalisasi/freeze; saat ini draft"]
P8_P11_FROZEN --> EXPLICIT_EXECUTE["Execute satu BU eksplisit; belum diotorisasi"]
EXPLICIT_EXECUTE --> BU_FOUND_001
BU_FOUND_001["BU-FOUND-001"]
BU_FOUND_001 --> BU_IAM_001
BU_IAM_001 --> BU_CMD_001
BU_CMD_001 --> BU_CFG_001
BU_CMD_001 --> BU_CAT_001
BU_CAT_001 --> BU_INV_001
BU_INV_001 --> BU_INV_002
BU_INV_002 --> BU_INV_003
BU_CMD_001 --> BU_CASH_001
BU_CFG_001 --> BU_SALE_001
BU_INV_002 --> BU_SALE_001
BU_CASH_001 --> BU_SALE_001
BU_SALE_001 --> BU_CASH_002
BU_SALE_001 --> BU_SALE_002
BU_INV_003 --> BU_SALE_002
BU_INV_002 --> BU_READ_001
BU_SALE_002 --> BU_READ_002
BU_CASH_002 --> BU_READ_002
BU_FOUND_001 --> BU_OPS_001
BU_OPS_001 --> BU_OPS_002
BU_SALE_002 --> BU_OPS_002
BU_CASH_002 --> BU_OPS_002
BU_IAM_001 --> BU_UI_001
BU_CFG_001 --> BU_UI_001
BU_UI_001 --> BU_UI_002
BU_CAT_001 --> BU_UI_002
BU_INV_002 --> BU_UI_002
BU_READ_001 --> BU_UI_002
BU_UI_001 --> BU_UI_003
BU_SALE_001 --> BU_UI_003
BU_CASH_002 --> BU_UI_003
BU_UI_001 --> BU_UI_004
BU_READ_002 --> BU_UI_004
BU_OPS_002 --> BU_UI_004
BU_UI_001 --> BU_DOC_001
BU_READ_002 --> BU_DOC_001
BU_OPS_002 --> BU_VERIFY_001
BU_DOC_001 --> BU_VERIFY_001
BU_UI_004 --> BU_VERIFY_001
BU_UI_002 --> BU_VERIFY_002
BU_UI_003 --> BU_VERIFY_002
BU_UI_004 --> BU_VERIFY_002
BU_DOC_001 --> BU_VERIFY_002
BU_UI_002 --> BU_VERIFY_003
BU_UI_003 --> BU_VERIFY_003
BU_DOC_001 --> BU_VERIFY_003
BU_UI_003 --> BU_VERIFY_004
BU_UI_004 --> BU_VERIFY_004
BU_OPS_001 --> BU_VERIFY_004
BU_VERIFY_001 --> BU_RELEASE_001
BU_VERIFY_002 --> BU_RELEASE_001
BU_VERIFY_003 --> BU_RELEASE_001
BU_VERIFY_004 --> BU_RELEASE_001
BU_RELEASE_001 --> BU_RELEASE_002
```

Graf/tabel/register berasal dari data yang sama: 27 ID unik, seluruh dependency tersedia, tanpa cycle. Topological order yang valid:
`BU-FOUND-001 → BU-IAM-001 → BU-CMD-001 → BU-CFG-001 → BU-CAT-001 → BU-INV-001 → BU-INV-002 → BU-INV-003 → BU-CASH-001 → BU-SALE-001 → BU-CASH-002 → BU-SALE-002 → BU-READ-001 → BU-READ-002 → BU-OPS-001 → BU-OPS-002 → BU-UI-001 → BU-UI-002 → BU-UI-003 → BU-UI-004 → BU-DOC-001 → BU-VERIFY-001 → BU-VERIFY-002 → BU-VERIFY-003 → BU-VERIFY-004 → BU-RELEASE-001 → BU-RELEASE-002`.
Desain aktual dan approval pertama dilakukan pada planning P7 sebelum refinalisasi/freeze P8–P11, bukan bersamaan dengan coding backend. Eksekusi pertama tetap BU-FOUND-001 hanya setelah seluruh gate paket terpenuhi serta instruksi owner; jangan memilih next otomatis. Edge P0–P6/P7/P8–P11/Execute merupakan gate planning, bukan dependency BU atau bukti bahwa gate telah lulus.

## Engineering, critical path dan kalender

**Estimasi adalah judgment engineering, bukan jam yang sudah dikerjakan.** Scope planning P7 menggantikan pekerjaan desain/approval yang sebelumnya ditunda; angka di bawah tidak memberi kredit COMPLETE. Draf estimasi akan ditinjau kembali setelah exact revision disetujui dan dampaknya dipetakan.

| Planning sebelum coding | Rincian | Jam |
| --- | --- | ---: |
| P7 desain aktual | Authoring desain 20; review 8; normalisasi DESIGN/token 4 | 32 |
| P8–P11 refinalisasi | Quality 3; operasi 1; release 1; mapping/DAG 3 | 8 |
| **Planning total** | Wajib sebelum paket implementation FROZEN | **40** |

| Komponen implementasi 27 BU | Jam |
| --- | ---: |
| Implementasi (I) | 110 |
| Verifikasi (V) | 116 |
| Evidence/docs (E) | 27 |
| **Implementasi engineering total** | **253** |
| **Gabungan planning + implementasi** | **293** |
| Reserve planning | 8 |
| Reserve implementasi | 56 |
| **Reserve total terpisah** | **64** |
| **Maximum allowance jika seluruh reserve terpakai** | **357** |

Critical dependency path teknis implementasi berbobot I + V + E dengan resource independen tetap **128h**:
`BU-FOUND-001 → BU-IAM-001 → BU-CMD-001 → BU-CAT-001 → BU-INV-001 → BU-INV-002 → BU-SALE-001 → BU-SALE-002 → BU-READ-002 → BU-UI-004 → BU-VERIFY-002 → BU-RELEASE-001 → BU-RELEASE-002`.
Ada path dengan durasi sama pada branch Sale/correction. Ditambah prerequisite planning 40h, dependency lower bound gabungan menjadi **168h**. Waktu menunggu owner approval exact revision belum diketahui dan dikeluarkan dari angka ini. Lower bound resource-independen bukan durasi kalender satu executor.

Satu executor memerlukan estimasi **293h = 36,625 hari × 8h = 7,325 minggu × 40h** untuk planning dan implementasi. Maximum allowance 357h setara 44,625 hari × 8h atau 8,925 minggu × 40h bila seluruh reserve dipakai. Implementasi saja 253h; planning 40h tidak dihitung lagi sebagai jam BU. Waiting owner/perangkat/access/policy/key/deploy aktual menambah elapsed time, tanpa estimasi fiktif. Tidak ada janji produksi dalam satu bulan.

Reserve planning 8h terpisah dari reserve implementasi 56h: framework/auth/DTO types/advisory 8; stock/money/concurrency/replay 18; visual native/mobile 12; hardware 6; migration/restore/TLS/performance 12. Reserve merupakan allowance risiko, bukan credit implementasi atau entitlement scope. Defect signifikan pada verifikasi final tidak dianggap gratis.

## Waiting eksternal terpisah

P7/DESIGN untuk desain aktual serta approval exact revision/rights dan normalisasi; gate ini menahan freeze P8–P11 dan seluruh coding. G1 tax/buyer; G2 unit/catalog/opening/evidence biaya; G3 drawer/funding; G4 independent backup/access/measured RPO/RTO; DEVICES; TARGET terotorisasi/availability EXECUTOR; serta RELEASE. Tidak ada estimasi waiting tanpa fakta Pemilik/provider. Backend/database/tests/ops non-production yang independen secara teknis boleh berjalan hanya setelah P7 COMPLETE, paket P8–P11 FROZEN dan satu BU diotorisasi eksplisit; acceptance client/produksi nyata berhenti sesuai gate terdampak.

## Status, evidence dan closeout

NOT STARTED, IN PROGRESS, WAITING TECHNICAL CHECK, WAITING OWNER REVIEW, WAITING EXTERNAL, BLOCKED, COMPLETE. Catat delivery bounded + commands/target/dataset/revision/results + review/fix + applicable holds. COMPLETE BU tidak otomatis produk FINAL; final point suite pada 08: 33 terintegrasi di BU-VERIFY-002, tiga database/recovery di BU-VERIFY-001, hardware di BU-VERIFY-003, performance di BU-VERIFY-004 dan release aktual di BU-RELEASE-002.

Self-review + independent/adversarial review diperlukan sesuai area; review kedua untuk risiko tinggi bila temuan/risiko membenarkannya. Race/rollback/constraints/permission PostgreSQL aktual, checks PHP/TypeScript/browser lima viewport + zoom 200%, perangkat fisik/restore/benchmark mengikuti risiko. Checks belum dijalankan tetap PENDING, bukan PASS.

Update BU/11/evidence, keputusan nyata pada 10, lalu 00 LAST. Stage path exact/review index/hash yang dilindungi; commit lokal task hanya bila terotorisasi, koheren dan checks PASS. Tidak ada izin tersirat push/merge/deploy/publish/pembelian/reset destruktif. STOP setelah BU aktif; sesi koreksi STOP setelah satu commit docs/design-planning lokal yang aman.

## Preservasi dan scope perubahan planning

Riwayat intake rebaseline terdahulu: branch plan/bantukasir-pos-v1/HEAD c4a145d, 0 staged/0 unstaged/0 untracked. Leftovers BK03 yang disebut dokumen sebelumnya tidak ada pada intake ini; tidak menebak actor/proses dan tidak merekonstruksinya. Hash 85 file source/aset/evidence yang dilindungi tercatat pada snapshot ignored .local/rebaseline-p0-p11/before; binary/source prototype/aset tidak diubah. README register historis diperbarui untuk fakta aktual dan pipeline terkini.

40 kartu lama dipindahkan ke archive/plan2/tasks dengan banner historis/adaptasi link relatif; canonical sebelum rebaseline tersedia pada Git c4a145d. Requirement valid diserap, tanpa implementasi kode aplikasi/database/file runtime. [Validation/review](evidence/rebaseline-p0-p11/REVIEW.md) mencatat command/temuan aktual/preservasi. Freeze terdahulu pada HEAD 5e100aa dikoreksi karena desain/approval P7 sempat ditunda. Intake koreksi: branch yang sama, HEAD 5e100aa dan plan-p0-p11-review.patch untracked yang dipertahankan. Tidak ada credit coding dari kedua paket. Pada closeout koreksi, 00 diperbarui LAST setelah dokumentasi/evidence aktual; satu commit docs/design-planning tidak mengotorisasi implementasi.
