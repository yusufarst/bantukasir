# BU-VERIFY-004 — Final representative VPS performance dan bounded replay capacity

## STATUS DAN GOAL

**NOT STARTED / IMPLEMENTATION_NOT_STARTED.** p95 lookup/search/Sale 50 baris/report, dua cashier dan kapasitas replay sinkron all-or-nothing. Kartu ini specification, bukan hasil implementasi. Eksekusi belum diotorisasi.

Estimasi dari nol: I0h / V5h / E1h = **6h**, tanpa owner/external waiting dan risk reserve. Metode di [11](../11-BUILD-PLAN.md).

## READ

[AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu ini → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → [08-TESTING-ACCEPTANCE.md](../08-TESTING-ACCEPTANCE.md) → [15-FINANCE-PROFITABILITY.md](../15-FINANCE-PROFITABILITY.md) → [planning/P6-CONCURRENCY-API.md](../planning/P6-CONCURRENCY-API.md) → [08/DoD](../08-TESTING-ACCEPTANCE.md). [CLAUDE](../../CLAUDE.md) berlaku bagi executor.

## DEPENDENCY DAN HOLDS

Artifact: [BU-UI-003](BU-UI-003.md), [BU-UI-004](BU-UI-004.md), [BU-OPS-001](BU-OPS-001.md). Setiap provider harus mempunyai evidence PASS yang dibekukan pada revision kompatibel. Seluruh UI produksi menunggu BU-DESIGN-001 COMPLETE dengan approval owner atas exact revision.

- Host/network non-production representatif yang diotorisasi

[Gap register](../planning/GAPS-CHANGES.md) memisahkan blueprint, owner facts, tests dan release authorization. Execute eksplisit satu BU tetap wajib; release/destruction/paid tools memerlukan scope authorization terpisah.

## REQUIREMENT DAN ACCEPTANCE

CORE-036, CORE-072. [CORE-MAP](../requirements/CORE-MAP.md) dan seluruh subklausul canonical tetap wajib.

Suite: PERF01. Final point hanya untuk suite yang dipetakan pada 08; seluruh consumer dan applicable holds diperlukan.

## DATA, ACTION DAN UI BOUNDARY

Data/tabel: Dataset/index representatif terisolasi; tanpa seed/reset client nyata.

p95 lookup/search/Sale 50 baris/report, dua cashier dan kapasitas replay sinkron all-or-nothing. Gunakan FormRequest/policy/Action/DTO sesuai P4/P5/P6; jangan serialize entity Eloquent privileged. Consumer UI memakai React/Inertia/shadcn native dengan kontrak approved; provider backend yang independen tidak menunggu visual. Tidak ada capability Later.

## DO

- Seed fixture hanya pada database test yang diidentifikasi/diotorisasi: 5.000 product/100.000 movement/dua cashier; catat host/network/config/run count/distribution.
- Ukur target barcode ≤500 ms/search ≤700 ms/Sale 50 baris ≤2 s/report ≤2 s; ukur referensi replay 10.000 movement untuk waktu/memori/cap/concurrency/fallback incomplete.
- Verifikasi count/ledger/balance/cost/snapshot sebelum dan sesudah; selidiki failure tanpa melonggarkan lock/privasi/atomicity.

Risiko: Target throughput tidak mengotorisasi infrastructure tambahan atau jalan pintas keamanan.

## VERIFY

- Benchmark/query plan/resource usage aktual yang repeatable serta rekonsiliasi; target bukan SLA diterima atau klaim hasil sampai diukur.
- Target tidak representatif tetap PENDING; tuning hanya bottleneck aktual bounded, rerun checks terdampak.

Delapan dimensi [DoD](../08-TESTING-ACCEPTANCE.md) mencatat applicability dan evidence. Jalankan unit/HTTP/PostgreSQL nyata/rollback/race/privilege/privacy/browser/device/recovery/performance sesuai risiko; pemeriksaan yang belum dijalankan tetap PENDING. Verifikasi target non-production sebelum fixture. Mock/screenshot tidak membuktikan transaksi atau perangkat.

## STOP / CHANGE CONTROL

STOP scope terdampak bila ada kontradiksi requirement, failure integrity/privacy/recovery, dependency belum terpenuhi, environment/biaya tool/destruction tidak diotorisasi, keputusan bisnis mendasar atau model executor yang diminta tidak tersedia. Catat gap/acceptance terdampak; kerjakan bagian independen yang masih diotorisasi. Jangan mengubah makna bisnis demi implementasi.

## UPDATE / COMMIT / NEXT

Catat path/revision/command/result/reviewer/fix/hold aktual; update kartu dan 11, serta 10 hanya untuk keputusan nyata, lalu **00 LAST**. COMPLETE memerlukan delivery bounded, checks dan completion holds terpenuhi; produk FINAL mengikuti 08.

Stage path exact yang disengaja dan review seluruh index; pertahankan pekerjaan tidak terkait. Commit lokal hanya sesudah BU diotorisasi eksplisit dan hasil koheren. Jangan push/merge/deploy/publish/beli atau melakukan destruction tanpa approval exact. STOP setelah closeout satu BU; jangan otomatis mengeksekusi berikutnya.