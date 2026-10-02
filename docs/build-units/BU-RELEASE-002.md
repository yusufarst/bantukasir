# BU-RELEASE-002 — Authorized production cutover, owner pilot dan handoff

## STATUS DAN GOAL

**NOT STARTED / IMPLEMENTATION_NOT_STARTED.** Operasi release exact yang disetujui, pilot workflow/rekonsiliasi role operasional serta acceptance produksi owner. Kartu ini specification, bukan hasil implementasi. Eksekusi belum diotorisasi.

Estimasi dari nol: I3h / V4h / E1h = **8h**, tanpa owner/external waiting dan risk reserve. Metode di [11](../11-BUILD-PLAN.md).

## READ

[AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu ini → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → [planning/P10-RELEASE-UAT.md](../planning/P10-RELEASE-UAT.md) → [09-DEPLOYMENT-OPS.md](../09-DEPLOYMENT-OPS.md) → [08-TESTING-ACCEPTANCE.md](../08-TESTING-ACCEPTANCE.md) → [08/DoD](../08-TESTING-ACCEPTANCE.md). [CLAUDE](../../CLAUDE.md) berlaku bagi executor.

## DEPENDENCY DAN HOLDS

Artifact: [BU-RELEASE-001](BU-RELEASE-001.md). Setiap provider harus mempunyai evidence PASS yang dibekukan pada revision kompatibel. Seluruh UI produksi menunggu BU-DESIGN-001 COMPLETE dengan approval owner atas exact revision.

- Otorisasi RELEASE explicit exact WAJIB; destruction TIDAK tersirat

[Gap register](../planning/GAPS-CHANGES.md) memisahkan blueprint, owner facts, tests dan release authorization. Execute eksplisit satu BU tetap wajib; release/destruction/paid tools memerlukan scope authorization terpisah.

## REQUIREMENT DAN ACCEPTANCE

CORE-037, CORE-073. [CORE-MAP](../requirements/CORE-MAP.md) dan seluruh subklausul canonical tetap wajib.

Suite: RELEASE01. RELEASE01 final sesudah actual authorized pilot dan owner acceptance.

## DATA, ACTION DAN UI BOUNDARY

Data/tabel: Target produksi aktual terotorisasi owner; hanya forward/additive, history dipertahankan.

Operasi release exact yang disetujui, pilot workflow/rekonsiliasi role operasional serta acceptance produksi owner. Gunakan FormRequest/policy/Action/DTO sesuai P4/P5/P6; jangan serialize entity Eloquent privileged. Consumer UI memakai React/Inertia/shadcn native dengan kontrak approved; provider backend yang independen tidak menunggu visual. Tidak ada capability Later.

## DO

- Periksa approval exact target/revision/migration/operation; independent backup/restore readiness; tanpa operasi destruktif tanpa approval exact terpisah.
- Cutover identity/master/opening manual dengan freeze/count/rekonsiliasi/enable yang diotorisasi; jangan seed demo/reset produksi; lalu pilot seluruh role/Sale/session/dokumen/refund bila sesuai.
- Rekonsiliasi stock/cash/transfer/Sale/payment/cost/session/report/audit/backup fisik, performance target terukur, acceptance owner serta handoff access/private key.

Risiko: Commit planning/Execute coding task generik/izin migration bukan approval deploy produksi atau destruction.

## VERIFY

- Witness pilot/device/dokumen/reprint/backup aktual terotorisasi dengan evidence versi release candidate; rollback hanya memakai strategi aman setelah writes.
- Catat biaya/RPO/RTO/incident tersisa nyata dan acceptance owner; gate gagal menghentikan release terdampak.

Delapan dimensi [DoD](../08-TESTING-ACCEPTANCE.md) mencatat applicability dan evidence. Jalankan unit/HTTP/PostgreSQL nyata/rollback/race/privilege/privacy/browser/device/recovery/performance sesuai risiko; pemeriksaan yang belum dijalankan tetap PENDING. Verifikasi target non-production sebelum fixture. Mock/screenshot tidak membuktikan transaksi atau perangkat.

## STOP / CHANGE CONTROL

STOP scope terdampak bila ada kontradiksi requirement, failure integrity/privacy/recovery, dependency belum terpenuhi, environment/biaya tool/destruction tidak diotorisasi, keputusan bisnis mendasar atau model executor yang diminta tidak tersedia. Catat gap/acceptance terdampak; kerjakan bagian independen yang masih diotorisasi. Jangan mengubah makna bisnis demi implementasi.

## UPDATE / COMMIT / NEXT

Catat path/revision/command/result/reviewer/fix/hold aktual; update kartu dan 11, serta 10 hanya untuk keputusan nyata, lalu **00 LAST**. COMPLETE memerlukan delivery bounded, checks dan completion holds terpenuhi; produk FINAL mengikuti 08.

Stage path exact yang disengaja dan review seluruh index; pertahankan pekerjaan tidak terkait. Commit lokal hanya sesudah BU diotorisasi eksplisit dan hasil koheren. Jangan push/merge/deploy/publish/beli atau melakukan destruction tanpa approval exact. STOP setelah closeout satu BU; jangan otomatis mengeksekusi berikutnya.