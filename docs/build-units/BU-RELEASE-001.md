# BU-RELEASE-001 — Readiness, cutover/UAT package dan owner gates

## STATUS DAN GOAL

**NOT STARTED / IMPLEMENTATION_NOT_STARTED.** Pemeriksaan candidate final terkini/seluruh hold, pengamanan rollback/recovery serta paket review deploy exact. Kartu ini specification, bukan hasil implementasi. Eksekusi belum diotorisasi.

Estimasi dari nol: I1h / V3h / E1h = **5h**, tanpa owner/external waiting dan risk reserve. Metode di [11](../11-BUILD-PLAN.md).

## READ

[AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu ini → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → [planning/P10-RELEASE-UAT.md](../planning/P10-RELEASE-UAT.md) → [09-DEPLOYMENT-OPS.md](../09-DEPLOYMENT-OPS.md) → [08-TESTING-ACCEPTANCE.md](../08-TESTING-ACCEPTANCE.md) → [08/DoD](../08-TESTING-ACCEPTANCE.md). [CLAUDE](../../CLAUDE.md) berlaku bagi executor.

## DEPENDENCY DAN HOLDS

Artifact: [BU-VERIFY-001](BU-VERIFY-001.md), [BU-VERIFY-002](BU-VERIFY-002.md), [BU-VERIFY-003](BU-VERIFY-003.md), [BU-VERIFY-004](BU-VERIFY-004.md). Setiap provider harus mempunyai evidence PASS yang dibekukan pada revision kompatibel. **Gate global sebelum seluruh coding:** [P7](../planning/P7-UX-DESIGN.md) COMPLETE dengan desain aktual DesainPakeAI, approval visual owner exact revision dan [DESIGN](../../DESIGN.md)/kontrak token yang dinormalisasi; lalu P8–P11 difinalisasi ulang/FROZEN dan owner mengotorisasi Execute satu BU eksplisit. Paket saat ini REOPENED_DRAFT/PENDING_P7_APPROVAL; coding belum diotorisasi. P7 adalah provider planning, bukan dependency BU.

- Seluruh readiness gate yang berlaku, termasuk DESIGN/G1–G4/DEVICES/TARGET dan evidence teknis, wajib terpenuhi. Otorisasi deployment RELEASE bukan syarat COMPLETE paket readiness ini; approval exact RELEASE menahan start/COMPLETE BU-RELEASE-002 saja.

[Gap register](../planning/GAPS-CHANGES.md) memisahkan blueprint, owner facts, tests dan release authorization. Execute eksplisit satu BU tetap wajib; release/destruction/paid tools memerlukan scope authorization terpisah.

## REQUIREMENT DAN ACCEPTANCE

CORE-037, CORE-073. [CORE-MAP](../requirements/CORE-MAP.md) dan seluruh subklausul canonical tetap wajib.

Suite: RELEASE01. Bukti delivery/verifikasi terarah ini tidak memberi product suite FINAL PASS otomatis.

## DATA, ACTION DAN UI BOUNDARY

Data/tabel: Readiness matrix/cutover manifest; tanpa mutation produksi aktual.

Pemeriksaan candidate final terkini/seluruh hold, pengamanan rollback/recovery serta paket review deploy exact. Gunakan FormRequest/policy/Action/DTO sesuai P4/P5/P6; jangan serialize entity Eloquent privileged. Consumer UI memakai React/Inertia/shadcn native dengan kontrak approved; provider backend yang independen secara teknis dapat dijadwalkan tanpa menunggu implementasi consumer UI setelah gate global terpenuhi dan BU terotorisasi; ini bukan izin coding saat paket planning masih draft. Tidak ada capability Later.

## DO

- Konfirmasi DESIGN/G1/G2/G3/G4/DEVICES dan seluruh suite FINAL kecuali RELEASE aktual; tidak ada risiko integrity/privacy/recovery unresolved.
- Siapkan training/skenario UAT identitas/master/opening/count/device/report/backup/recovery aktual dan verifikasi label harness operator.
- Siapkan exact target/revision/operation serta checklist migration/cutover/rollback dan permintaan otorisasi sebagai langkah terakhir; readiness bukan deployment.

Risiko: Jangan memberi RELEASE01 FINAL untuk persiapan saja.

## VERIFY

- Dry-run cutover/rekonsiliasi terisolasi; seluruh manifest/revision/link evidence/training/runbook direview owner.
- Readiness COMPLETE saja tidak memberikan access/write produksi; RELEASE01 tetap PENDING sampai unit berikut diotorisasi.

Delapan dimensi [DoD](../08-TESTING-ACCEPTANCE.md) mencatat applicability dan evidence. Jalankan unit/HTTP/PostgreSQL nyata/rollback/race/privilege/privacy/browser/device/recovery/performance sesuai risiko; pemeriksaan yang belum dijalankan tetap PENDING. Verifikasi target non-production sebelum fixture. Mock/screenshot tidak membuktikan transaksi atau perangkat.

## STOP / CHANGE CONTROL

STOP scope terdampak bila ada kontradiksi requirement, failure integrity/privacy/recovery, dependency belum terpenuhi, environment/biaya tool/destruction tidak diotorisasi, keputusan bisnis mendasar atau model executor yang diminta tidak tersedia. Catat gap/acceptance terdampak; kerjakan bagian independen yang masih diotorisasi. Jangan mengubah makna bisnis demi implementasi.

## UPDATE / COMMIT / NEXT

Catat path/revision/command/result/reviewer/fix/hold aktual; update kartu dan 11, serta 10 hanya untuk keputusan nyata, lalu **00 LAST**. COMPLETE memerlukan delivery bounded, checks dan completion holds terpenuhi; produk FINAL mengikuti 08.

Stage path exact yang disengaja dan review seluruh index; pertahankan pekerjaan tidak terkait. Commit lokal hanya sesudah BU diotorisasi eksplisit dan hasil koheren. Jangan push/merge/deploy/publish/beli atau melakukan destruction tanpa approval exact. STOP setelah closeout satu BU; jangan otomatis mengeksekusi berikutnya.
