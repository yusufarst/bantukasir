# BU-VERIFY-001 — Final populated migration dan independent restore/recovery acceptance

## STATUS DAN GOAL

**NOT STARTED / IMPLEMENTATION_NOT_STARTED.** FINAL DB01/REC01/REC02 pada candidate terintegrasi, destination/key backup aktual dan review gap. Kartu ini specification, bukan hasil implementasi. Eksekusi belum diotorisasi.

Estimasi dari nol: I0h / V7h / E1h = **8h**, tanpa owner/external waiting dan risk reserve. Metode di [11](../11-BUILD-PLAN.md).

## READ

[AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu ini → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → [09-DEPLOYMENT-OPS.md](../09-DEPLOYMENT-OPS.md) → [08-TESTING-ACCEPTANCE.md](../08-TESTING-ACCEPTANCE.md) → [planning/P10-RELEASE-UAT.md](../planning/P10-RELEASE-UAT.md) → [08/DoD](../08-TESTING-ACCEPTANCE.md). [CLAUDE](../../CLAUDE.md) berlaku bagi executor.

## DEPENDENCY DAN HOLDS

Artifact: [BU-OPS-002](BU-OPS-002.md), [BU-DOC-001](BU-DOC-001.md), [BU-UI-004](BU-UI-004.md). Setiap provider harus mempunyai evidence PASS yang dibekukan pada revision kompatibel. **Gate global sebelum seluruh coding:** [P7](../planning/P7-UX-DESIGN.md) COMPLETE dengan desain aktual DesainPakeAI, approval visual owner exact revision dan [DESIGN](../../DESIGN.md)/kontrak token yang dinormalisasi; lalu P8–P11 difinalisasi ulang/FROZEN dan owner mengotorisasi Execute satu BU eksplisit. Paket saat ini REOPENED_DRAFT/PENDING_P7_APPROVAL; coding belum diotorisasi. P7 adalah provider planning, bukan dependency BU.

- G4 WAJIB untuk FINAL, target/access/key independen aktual; tanpa destruction produksi

[Gap register](../planning/GAPS-CHANGES.md) memisahkan blueprint, owner facts, tests dan release authorization. Execute eksplisit satu BU tetap wajib; release/destruction/paid tools memerlukan scope authorization terpisah.

## REQUIREMENT DAN ACCEPTANCE

CORE-065, CORE-066. [CORE-MAP](../requirements/CORE-MAP.md) dan seluruh subklausul canonical tetap wajib.

Suite: DB01, REC01, REC02. Final point hanya untuk suite yang dipetakan pada 08; seluruh consumer dan applicable holds diperlukan.

## DATA, ACTION DAN UI BOUNDARY

Data/tabel: Schema final pada database populated terisolasi; original dipertahankan; restore ke target baru.

FINAL DB01/REC01/REC02 pada candidate terintegrasi, destination/key backup aktual dan review gap. Gunakan FormRequest/policy/Action/DTO sesuai P4/P5/P6; jangan serialize entity Eloquent privileged. Consumer UI memakai React/Inertia/shadcn native dengan kontrak approved; provider backend yang independen secara teknis dapat dijadwalkan tanpa menunggu implementasi consumer UI setelah gate global terpenuhi dan BU terotorisasi; ini bukan izin coding saat paket planning masih draft. Tidak ada capability Later.

## DO

- Forward migration expand/backfill/verify/contract direview pada populated target terisolasi; snapshot/count/rekonsiliasi/privilege denial serta rollback/recovery readiness.
- Backup database/aset/template terenkripsi independen aktual/checksum; restore ke target baru, seluruh source/projection/snapshot/actor/session/command cocok.
- Revoke + recovery epoch baru, stale intent ditolak, evidence gap setelah cutoff direview; catat RPO/RTO aktual dan penerimaan owner/G4.

Risiko: G4 OPEN mempertahankan WAITING; simulasi restore tidak membuat COMPLETE.

## VERIFY

- Migration/restore/rekonsiliasi/epoch nyata dan DDL/mutation fakta runtime terlarang; tanpa command destruktif produksi.
- Rerun evidence terdampak hanya bila perubahan kode/data/config signifikan; fixture/mock tidak menutup FINAL.

Delapan dimensi [DoD](../08-TESTING-ACCEPTANCE.md) mencatat applicability dan evidence. Jalankan unit/HTTP/PostgreSQL nyata/rollback/race/privilege/privacy/browser/device/recovery/performance sesuai risiko; pemeriksaan yang belum dijalankan tetap PENDING. Verifikasi target non-production sebelum fixture. Mock/screenshot tidak membuktikan transaksi atau perangkat.

## STOP / CHANGE CONTROL

STOP scope terdampak bila ada kontradiksi requirement, failure integrity/privacy/recovery, dependency belum terpenuhi, environment/biaya tool/destruction tidak diotorisasi, keputusan bisnis mendasar atau model executor yang diminta tidak tersedia. Catat gap/acceptance terdampak; kerjakan bagian independen yang masih diotorisasi. Jangan mengubah makna bisnis demi implementasi.

## UPDATE / COMMIT / NEXT

Catat path/revision/command/result/reviewer/fix/hold aktual; update kartu dan 11, serta 10 hanya untuk keputusan nyata, lalu **00 LAST**. COMPLETE memerlukan delivery bounded, checks dan completion holds terpenuhi; produk FINAL mengikuti 08.

Stage path exact yang disengaja dan review seluruh index; pertahankan pekerjaan tidak terkait. Commit lokal hanya sesudah BU diotorisasi eksplisit dan hasil koheren. Jangan push/merge/deploy/publish/beli atau melakukan destruction tanpa approval exact. STOP setelah closeout satu BU; jangan otomatis mengeksekusi berikutnya.
