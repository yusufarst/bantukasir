# BU-UI-002 — Product Operations lengkap: katalog, receipt, opening dan current stock

## STATUS DAN GOAL

**NOT STARTED / IMPLEMENTATION_NOT_STARTED.** Search/list/detail/form/duplikasi, preview barcode, barang masuk/manual issue/opening Pemilik serta LOW/OUT/history aman per role. Kartu ini specification, bukan hasil implementasi. Eksekusi belum diotorisasi.

Estimasi dari nol: I7h / V5h / E1h = **13h**, tanpa owner/external waiting dan risk reserve. Metode di [11](../11-BUILD-PLAN.md).

## READ

[AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu ini → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → [06-INVENTORY-SPEC.md](../06-INVENTORY-SPEC.md) → [12-BARCODE-SCANNER.md](../12-BARCODE-SCANNER.md) → [04-AUTH-RBAC-SECURITY.md](../04-AUTH-RBAC-SECURITY.md) → [../DESIGN.md](../../DESIGN.md) → [08/DoD](../08-TESTING-ACCEPTANCE.md). [CLAUDE](../../CLAUDE.md) berlaku bagi executor.

## DEPENDENCY DAN HOLDS

Artifact: [BU-UI-001](BU-UI-001.md), [BU-CAT-001](BU-CAT-001.md), [BU-INV-002](BU-INV-002.md), [BU-READ-001](BU-READ-001.md). Setiap provider harus mempunyai evidence PASS yang dibekukan pada revision kompatibel. Seluruh UI produksi menunggu BU-DESIGN-001 COMPLETE dengan approval owner atas exact revision.

- DESIGN prerequisite; G2 untuk acceptance onboarding nyata

[Gap register](../planning/GAPS-CHANGES.md) memisahkan blueprint, owner facts, tests dan release authorization. Execute eksplisit satu BU tetap wajib; release/destruction/paid tools memerlukan scope authorization terpisah.

## REQUIREMENT DAN ACCEPTANCE

CORE-002, CORE-004, CORE-005, CORE-007, CORE-008, CORE-010, CORE-015, CORE-034, CORE-035, CORE-042, CORE-043, CORE-044, CORE-045, CORE-046, CORE-050. [CORE-MAP](../requirements/CORE-MAP.md) dan seluruh subklausul canonical tetap wajib.

Suite: SEARCH01, SEARCH02, INV01, INV02, OPEN01, STOCK01, AUTH02, UX01, DEVICE01. Bukti delivery/verifikasi terarah ini tidak memberi product suite FINAL PASS otomatis.

## DATA, ACTION DAN UI BOUNDARY

Data/tabel: Consumer command/query catalog/inventory; browser bukan authority stok.

Search/list/detail/form/duplikasi, preview barcode, barang masuk/manual issue/opening Pemilik serta LOW/OUT/history aman per role. Gunakan FormRequest/policy/Action/DTO sesuai P4/P5/P6; jangan serialize entity Eloquent privileged. Consumer UI memakai React/Inertia/shadcn native dengan kontrak approved; provider backend yang independen tidak menunggu visual. Tidak ada capability Later.

## DO

- Demo ATK hanya terisolasi; error duplikasi/stok/version dari server nyata, search-first create stok 0; tanpa gambar/import/warehouse.
- State review/confirm receiving/manual issue/opening berbeda; biaya Operations write-only tanpa echo pada review/error/flash/history/restore; reason non-sale wajib.
- Draft/confirm mobile terjangkau, refresh restock current ≤30 detik/focus/mutation, stale bukan empty; opening hanya Pemilik dengan count/zero checklist/rekonsiliasi.

Risiko: Tanpa simulasi stok client atau product create yang otomatis memposting receipt.

## VERIFY

- Draft browser → review → confirm → fakta benar-benar tersimpan → history; duplicate/stale/denied/recovery reference receiving UNCERTAIN setelah reload tanpa cost tersimpan; opening 101/baris gagal.
- Lima viewport/touch/focus/zoom 200%; test cost leak pada props/body/error/storage network aktual; command PostgreSQL terarah dan npm run test:e2e.

Delapan dimensi [DoD](../08-TESTING-ACCEPTANCE.md) mencatat applicability dan evidence. Jalankan unit/HTTP/PostgreSQL nyata/rollback/race/privilege/privacy/browser/device/recovery/performance sesuai risiko; pemeriksaan yang belum dijalankan tetap PENDING. Verifikasi target non-production sebelum fixture. Mock/screenshot tidak membuktikan transaksi atau perangkat.

## STOP / CHANGE CONTROL

STOP scope terdampak bila ada kontradiksi requirement, failure integrity/privacy/recovery, dependency belum terpenuhi, environment/biaya tool/destruction tidak diotorisasi, keputusan bisnis mendasar atau model executor yang diminta tidak tersedia. Catat gap/acceptance terdampak; kerjakan bagian independen yang masih diotorisasi. Jangan mengubah makna bisnis demi implementasi.

## UPDATE / COMMIT / NEXT

Catat path/revision/command/result/reviewer/fix/hold aktual; update kartu dan 11, serta 10 hanya untuk keputusan nyata, lalu **00 LAST**. COMPLETE memerlukan delivery bounded, checks dan completion holds terpenuhi; produk FINAL mengikuti 08.

Stage path exact yang disengaja dan review seluruh index; pertahankan pekerjaan tidak terkait. Commit lokal hanya sesudah BU diotorisasi eksplisit dan hasil koheren. Jangan push/merge/deploy/publish/beli atau melakukan destruction tanpa approval exact. STOP setelah closeout satu BU; jangan otomatis mengeksekusi berikutnya.