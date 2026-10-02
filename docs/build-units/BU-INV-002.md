# BU-INV-002 — Receiving, manual issue dan opening owner-only

## STATUS DAN GOAL

**NOT STARTED / IMPLEMENTATION_NOT_STARTED.** Command server dengan confirm eksplisit untuk barang masuk/issue non-sale/opening manual, tanpa import. Kartu ini specification, bukan hasil implementasi. Eksekusi belum diotorisasi.

Estimasi dari nol: I5h / V4h / E1h = **10h**, tanpa owner/external waiting dan risk reserve. Metode di [11](../11-BUILD-PLAN.md).

## READ

[AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu ini → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → [06-INVENTORY-SPEC.md](../06-INVENTORY-SPEC.md) → [04-AUTH-RBAC-SECURITY.md](../04-AUTH-RBAC-SECURITY.md) → [15-FINANCE-PROFITABILITY.md](../15-FINANCE-PROFITABILITY.md) → [08/DoD](../08-TESTING-ACCEPTANCE.md). [CLAUDE](../../CLAUDE.md) berlaku bagi executor.

## DEPENDENCY DAN HOLDS

Artifact: [BU-INV-001](BU-INV-001.md). Setiap provider harus mempunyai evidence PASS yang dibekukan pada revision kompatibel. Seluruh UI produksi menunggu BU-DESIGN-001 COMPLETE dengan approval owner atas exact revision.

- G2 dan cutover terotorisasi sebelum count/source client nyata

[Gap register](../planning/GAPS-CHANGES.md) memisahkan blueprint, owner facts, tests dan release authorization. Execute eksplisit satu BU tetap wajib; release/destruction/paid tools memerlukan scope authorization terpisah.

## REQUIREMENT DAN ACCEPTANCE

CORE-002, CORE-003, CORE-005, CORE-007, CORE-008, CORE-010, CORE-043, CORE-044, CORE-045, CORE-050. [CORE-MAP](../requirements/CORE-MAP.md) dan seluruh subklausul canonical tetap wajib.

Suite: INV01, INV02, OPEN01, AUTH02, AUTH03, SEARCH02. Bukti delivery/verifikasi terarah ini tidak memberi product suite FINAL PASS otomatis.

## DATA, ACTION DAN UI BOUNDARY

Data/tabel: Source RECEIPT/MANUAL_ISSUE/OPENING, onboarding flag/zero checklist dan cost evidence.

Command server dengan confirm eksplisit untuk barang masuk/issue non-sale/opening manual, tanpa import. Gunakan FormRequest/policy/Action/DTO sesuai P4/P5/P6; jangan serialize entity Eloquent privileged. Consumer UI memakai React/Inertia/shadcn native dengan kontrak approved; provider backend yang independen tidak menunggu visual. Tidak ada capability Later.

## DO

- Receiving untuk produk existing/search-first dengan quantity/evidence biaya baru/UNKNOWN/reference; Operations write-only tanpa echo/flash/history/log/storage.
- Manual issue wajib reason non-sale dan quantity/onHand valid; jangan dijadikan pengganti Sale.
- Onboarding freeze Pemilik memakai shared guard; ≤100 baris atomik/tanpa ledger sebelumnya/sekali per produk; zero checklist tanpa movement; lepas flag setelah rekonsiliasi direview.

Risiko: Cost input UI berizin tidak memberi staff permission baca atau payload biaya pada recovery client.

## VERIFY

- Role ditolak, tanpa echo sensitif di seluruh response/error/session, reason hilang, 101/baris invalid rollback seluruh command, race opening key sama/berbeda dan consumer freeze melawan Sale.
- composer test:postgres; composer test:race; privasi HTTP response/old input/session flash aktual; recovery receiving UNCERTAIN tanpa biaya tersimpan.

Delapan dimensi [DoD](../08-TESTING-ACCEPTANCE.md) mencatat applicability dan evidence. Jalankan unit/HTTP/PostgreSQL nyata/rollback/race/privilege/privacy/browser/device/recovery/performance sesuai risiko; pemeriksaan yang belum dijalankan tetap PENDING. Verifikasi target non-production sebelum fixture. Mock/screenshot tidak membuktikan transaksi atau perangkat.

## STOP / CHANGE CONTROL

STOP scope terdampak bila ada kontradiksi requirement, failure integrity/privacy/recovery, dependency belum terpenuhi, environment/biaya tool/destruction tidak diotorisasi, keputusan bisnis mendasar atau model executor yang diminta tidak tersedia. Catat gap/acceptance terdampak; kerjakan bagian independen yang masih diotorisasi. Jangan mengubah makna bisnis demi implementasi.

## UPDATE / COMMIT / NEXT

Catat path/revision/command/result/reviewer/fix/hold aktual; update kartu dan 11, serta 10 hanya untuk keputusan nyata, lalu **00 LAST**. COMPLETE memerlukan delivery bounded, checks dan completion holds terpenuhi; produk FINAL mengikuti 08.

Stage path exact yang disengaja dan review seluruh index; pertahankan pekerjaan tidak terkait. Commit lokal hanya sesudah BU diotorisasi eksplisit dan hasil koheren. Jangan push/merge/deploy/publish/beli atau melakukan destruction tanpa approval exact. STOP setelah closeout satu BU; jangan otomatis mengeksekusi berikutnya.