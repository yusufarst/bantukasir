# BU-READ-001 — Current stock, LOW/OUT dan safe operational history

## STATUS DAN GOAL

**NOT STARTED / IMPLEMENTATION_NOT_STARTED.** Query stok/restock/history operasional awal untuk consumer UI inventory tanpa menunggu finance Pemilik. Kartu ini specification, bukan hasil implementasi. Eksekusi belum diotorisasi.

Estimasi dari nol: I3h / V3h / E1h = **7h**, tanpa owner/external waiting dan risk reserve. Metode di [11](../11-BUILD-PLAN.md).

## READ

[AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu ini → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → [06-INVENTORY-SPEC.md](../06-INVENTORY-SPEC.md) → [04-AUTH-RBAC-SECURITY.md](../04-AUTH-RBAC-SECURITY.md) → [12-BARCODE-SCANNER.md](../12-BARCODE-SCANNER.md) → [08/DoD](../08-TESTING-ACCEPTANCE.md). [CLAUDE](../../CLAUDE.md) berlaku bagi executor.

## DEPENDENCY DAN HOLDS

Artifact: [BU-INV-002](BU-INV-002.md). Setiap provider harus mempunyai evidence PASS yang dibekukan pada revision kompatibel. **Gate global sebelum seluruh coding:** [P7](../planning/P7-UX-DESIGN.md) COMPLETE dengan desain aktual DesainPakeAI, approval visual owner exact revision dan [DESIGN](../../DESIGN.md)/kontrak token yang dinormalisasi; lalu P8–P11 difinalisasi ulang/FROZEN dan owner mengotorisasi Execute satu BU eksplisit. Paket saat ini REOPENED_DRAFT/PENDING_P7_APPROVAL; coding belum diotorisasi. P7 adalah provider planning, bukan dependency BU.

Tidak ada hold khusus untuk engineering non-production yang independen.

[Gap register](../planning/GAPS-CHANGES.md) memisahkan blueprint, owner facts, tests dan release authorization. Execute eksplisit satu BU tetap wajib; release/destruction/paid tools memerlukan scope authorization terpisah.

## REQUIREMENT DAN ACCEPTANCE

CORE-002, CORE-007, CORE-015, CORE-046. [CORE-MAP](../requirements/CORE-MAP.md) dan seluruh subklausul canonical tetap wajib.

Suite: STOCK01, INV01, AUTH02. Bukti delivery/verifikasi terarah ini tidak memberi product suite FINAL PASS otomatis.

## DATA, ACTION DAN UI BOUNDARY

Data/tabel: Read index pada movement/current balance/product minimum; tanpa ledger baru.

Query stok/restock/history operasional awal untuk consumer UI inventory tanpa menunggu finance Pemilik. Gunakan FormRequest/policy/Action/DTO sesuai P4/P5/P6; jangan serialize entity Eloquent privileged. Consumer UI memakai React/Inertia/shadcn native dengan kontrak approved; provider backend yang independen secara teknis dapat dijadwalkan tanpa menunggu implementasi consumer UI setelah gate global terpenuhi dan BU terotorisasi; ini bukan izin coding saat paket planning masih draft. Tidak ada capability Later.

## DO

- Baca onHand/minimum committed dalam query konsisten; NORMAL/LOW/OUT, minimum 0/produk baru stok 0/filter inactive dan last-refreshed time.
- History operasional memakai pagination/DTO aman untuk Pemilik/Operations; Kasir hanya stok yang diizinkan, bukan history pengelolaan movement.
- Refresh consumer setelah mutation/focus/≤30 detik saat aktif; failure/stale/mismatch dinyatakan jelas, bukan zero/empty/Normal fiktif.

Risiko: Provider read operasional tidak bergantung pada finance/refund/report session agar UI inventory tidak tertahan consumer lain.

## VERIFY

- Threshold 5: 6 NORMAL, 5/1 LOW, 0 OUT; minimum 0/inactive/product baru 0, receipt/perubahan threshold/read/query failure; history/error/cache tanpa cost.
- composer test:postgres; HTTP negative role/object tests dan query plan bounded; consumer browser BU-UI-002.

Delapan dimensi [DoD](../08-TESTING-ACCEPTANCE.md) mencatat applicability dan evidence. Jalankan unit/HTTP/PostgreSQL nyata/rollback/race/privilege/privacy/browser/device/recovery/performance sesuai risiko; pemeriksaan yang belum dijalankan tetap PENDING. Verifikasi target non-production sebelum fixture. Mock/screenshot tidak membuktikan transaksi atau perangkat.

## STOP / CHANGE CONTROL

STOP scope terdampak bila ada kontradiksi requirement, failure integrity/privacy/recovery, dependency belum terpenuhi, environment/biaya tool/destruction tidak diotorisasi, keputusan bisnis mendasar atau model executor yang diminta tidak tersedia. Catat gap/acceptance terdampak; kerjakan bagian independen yang masih diotorisasi. Jangan mengubah makna bisnis demi implementasi.

## UPDATE / COMMIT / NEXT

Catat path/revision/command/result/reviewer/fix/hold aktual; update kartu dan 11, serta 10 hanya untuk keputusan nyata, lalu **00 LAST**. COMPLETE memerlukan delivery bounded, checks dan completion holds terpenuhi; produk FINAL mengikuti 08.

Stage path exact yang disengaja dan review seluruh index; pertahankan pekerjaan tidak terkait. Commit lokal hanya sesudah BU diotorisasi eksplisit dan hasil koheren. Jangan push/merge/deploy/publish/beli atau melakukan destruction tanpa approval exact. STOP setelah closeout satu BU; jangan otomatis mengeksekusi berikutnya.
