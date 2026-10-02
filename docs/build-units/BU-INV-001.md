# BU-INV-001 — Immutable ledger, balance sinkron dan MWA UNKNOWN

## STATUS DAN GOAL

**NOT STARTED / IMPLEMENTATION_NOT_STARTED.** Primitive posting fisik/biaya dengan decimal presisi dan stok nonnegative untuk seluruh consumer. Kartu ini specification, bukan hasil implementasi. Eksekusi belum diotorisasi.

Estimasi dari nol: I6h / V5h / E1h = **12h**, tanpa owner/external waiting dan risk reserve. Metode di [11](../11-BUILD-PLAN.md).

## READ

[AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu ini → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → [02-ARCHITECTURE.md](../02-ARCHITECTURE.md) → [06-INVENTORY-SPEC.md](../06-INVENTORY-SPEC.md) → [15-FINANCE-PROFITABILITY.md](../15-FINANCE-PROFITABILITY.md) → [08/DoD](../08-TESTING-ACCEPTANCE.md). [CLAUDE](../../CLAUDE.md) berlaku bagi executor.

## DEPENDENCY DAN HOLDS

Artifact: [BU-CAT-001](BU-CAT-001.md). Setiap provider harus mempunyai evidence PASS yang dibekukan pada revision kompatibel. Seluruh UI produksi menunggu BU-DESIGN-001 COMPLETE dengan approval owner atas exact revision.

Tidak ada hold khusus untuk engineering non-production yang independen.

[Gap register](../planning/GAPS-CHANGES.md) memisahkan blueprint, owner facts, tests dan release authorization. Execute eksplisit satu BU tetap wajib; release/destruction/paid tools memerlukan scope authorization terpisah.

## REQUIREMENT DAN ACCEPTANCE

CORE-002, CORE-007, CORE-008, CORE-016, CORE-017, CORE-040, CORE-047, CORE-048. [CORE-MAP](../requirements/CORE-MAP.md) dan seluruh subklausul canonical tetap wajib.

Suite: INV01, INV02, FIN01, FIN02, AUTH02. Bukti delivery/verifikasi terarah ini tidak memberi product suite FINAL PASS otomatis.

## DATA, ACTION DAN UI BOUNDARY

Data/tabel: StockMovement/lines/posting sequence, StockBalance, AcquisitionCostEvidence, ValuationRevision/IssueCost.

Primitive posting fisik/biaya dengan decimal presisi dan stok nonnegative untuk seluruh consumer. Gunakan FormRequest/policy/Action/DTO sesuai P4/P5/P6; jangan serialize entity Eloquent privileged. Consumer UI memakai React/Inertia/shadcn native dengan kontrak approved; provider backend yang independen tidak menunggu visual. Tidak ada capability Later.

## DO

- Append movement, update balance/cost, audit/result secara atomik; aggregate produk duplikat dan lock ID stabil; constraints/privileges storage melindungi original.
- Implementasikan numeric/BigDecimal canonical string, precision quantity 0–3, half-up per baris IDR integer, cost enam desimal/residual; pin batas schema dan tolak overflow.
- MWA receipt/issue dan valuation non-sale; UNKNOWN berbeda dari zero; allocation lama yang known tetap valid; stok 0 → receipt known hanya memulai pool baru, tanpa latest price.

Risiko: Projection dapat direbuild dari fakta melalui rekonsiliasi berizin; tidak ada direct business balance editing.

## VERIFY

- Contoh MWA pada 15, fractional/zero/residual/overflow, chronology UNKNOWN, aggregate line duplikat, race issue unit terakhir dan rollback setiap write.
- composer test:unit; composer test:postgres; composer test:race; rekonsiliasi signed ledger/balance/value sebelum dan sesudah.

Delapan dimensi [DoD](../08-TESTING-ACCEPTANCE.md) mencatat applicability dan evidence. Jalankan unit/HTTP/PostgreSQL nyata/rollback/race/privilege/privacy/browser/device/recovery/performance sesuai risiko; pemeriksaan yang belum dijalankan tetap PENDING. Verifikasi target non-production sebelum fixture. Mock/screenshot tidak membuktikan transaksi atau perangkat.

## STOP / CHANGE CONTROL

STOP scope terdampak bila ada kontradiksi requirement, failure integrity/privacy/recovery, dependency belum terpenuhi, environment/biaya tool/destruction tidak diotorisasi, keputusan bisnis mendasar atau model executor yang diminta tidak tersedia. Catat gap/acceptance terdampak; kerjakan bagian independen yang masih diotorisasi. Jangan mengubah makna bisnis demi implementasi.

## UPDATE / COMMIT / NEXT

Catat path/revision/command/result/reviewer/fix/hold aktual; update kartu dan 11, serta 10 hanya untuk keputusan nyata, lalu **00 LAST**. COMPLETE memerlukan delivery bounded, checks dan completion holds terpenuhi; produk FINAL mengikuti 08.

Stage path exact yang disengaja dan review seluruh index; pertahankan pekerjaan tidak terkait. Commit lokal hanya sesudah BU diotorisasi eksplisit dan hasil koheren. Jangan push/merge/deploy/publish/beli atau melakukan destruction tanpa approval exact. STOP setelah closeout satu BU; jangan otomatis mengeksekusi berikutnya.