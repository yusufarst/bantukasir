# BU-DOC-001 — Code128 label, thermal/A4 nota dan immutable session exports

## STATUS DAN GOAL

**NOT STARTED / IMPLEMENTATION_NOT_STARTED.** Browser print/PDF dan CSV session aman dengan identitas/nomor original committed yang sama serta code label aktual. Kartu ini specification, bukan hasil implementasi. Eksekusi belum diotorisasi.

Estimasi dari nol: I4h / V4h / E1h = **9h**, tanpa owner/external waiting dan risk reserve. Metode di [11](../11-BUILD-PLAN.md).

## READ

[AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu ini → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → [12-BARCODE-SCANNER.md](../12-BARCODE-SCANNER.md) → [17-POS-SALES.md](../17-POS-SALES.md) → [04-AUTH-RBAC-SECURITY.md](../04-AUTH-RBAC-SECURITY.md) → [../DESIGN.md](../../DESIGN.md) → [08/DoD](../08-TESTING-ACCEPTANCE.md). [CLAUDE](../../CLAUDE.md) berlaku bagi executor.

## DEPENDENCY DAN HOLDS

Artifact: [BU-UI-001](BU-UI-001.md), [BU-READ-002](BU-READ-002.md). Setiap provider coding harus mempunyai evidence PASS yang dibekukan pada revision kompatibel.

Provider planning: [P7](../planning/P7-UX-DESIGN.md) COMPLETE dengan desain aktual DesainPakeAI, approval owner atas exact revision dan [DESIGN](../../DESIGN.md)/kontrak token yang dinormalisasi. Metadata: `planningProviders.phase: P7`, `requiredStatus: COMPLETE`, `approvedRevision: null` (approval belum tersedia). P7 bukan dependency coding. Paket P8–P11 saat ini REOPENED_DRAFT/PENDING_P7_APPROVAL; refinalisasi/FROZEN dan Execute eksplisit tetap wajib sebelum seluruh coding.

- P7/approved DESIGN prerequisite; paket P8–P11 difinalisasi ulang/FROZEN sebelum Execute; G1 legal/policy buyer dan media DEVICES aktual sebelum final

[Gap register](../planning/GAPS-CHANGES.md) memisahkan blueprint, owner facts, tests dan release authorization. Execute eksplisit satu BU tetap wajib; release/destruction/paid tools memerlukan scope authorization terpisah.

## REQUIREMENT DAN ACCEPTANCE

CORE-002, CORE-013, CORE-025, CORE-026, CORE-028, CORE-030, CORE-035, CORE-038, CORE-062, CORE-063. [CORE-MAP](../requirements/CORE-MAP.md) dan seluruh subklausul canonical tetap wajib.

Suite: POS03, SESSION07, SESSION08, SESSION10, IDENTITY01, AUTH02, SEC01, DEVICE01. Bukti delivery/verifikasi terarah ini tidak memberi product suite FINAL PASS otomatis.

## DATA, ACTION DAN UI BOUNDARY

Data/tabel: Consumer revision Receipt/SessionReport/Profile/aset/template original; request audit terpisah.

Browser print/PDF dan CSV session aman dengan identitas/nomor original committed yang sama serta code label aktual. Gunakan FormRequest/policy/Action/DTO sesuai P4/P5/P6; jangan serialize entity Eloquent privileged. Consumer UI memakai React/Inertia/shadcn native dengan kontrak approved; provider backend yang independen secara teknis dapat dijadwalkan tanpa menunggu consumer UI setelah seluruh paket planning dibekukan dan BU terotorisasi. Tidak ada capability Later.

## DO

- Verifikasi/pin license library label; quiet zone/mm/Code128/SKU terbaca tanpa biaya privat; token retired tetap dipertahankan.
- Thermal 58/80 mm dan A4 commercial nota dari ReceiptSnapshot yang sama sesuai policy client owner; identity/template/assets lama tetap sesudah config berubah/correction.
- Sale/session/media/reprint sendiri sesuai scope, copy marker, retry print failure tanpa mutation; quote/neutralisasi formula teks CSV mempertahankan variance angka negatif.

Risiko: Commercial nota bukan faktur pajak atau sertifikasi regulasi.

## VERIFY

- Snapshot sebelum dan sesudah profile/cost/session/refund berubah tetap identik; wrong role lewat request langsung/object/export/media ditolak; kasus CSV/text/logo XSS/path berbahaya.
- Browser print/render PDF/content CSV aktual/failure reprint; bukti perangkat ditunda ke BU-VERIFY-003. Penutupan dialog print bukan bukti tinta.

Delapan dimensi [DoD](../08-TESTING-ACCEPTANCE.md) mencatat applicability dan evidence. Jalankan unit/HTTP/PostgreSQL nyata/rollback/race/privilege/privacy/browser/device/recovery/performance sesuai risiko; pemeriksaan yang belum dijalankan tetap PENDING. Verifikasi target non-production sebelum fixture. Mock/screenshot tidak membuktikan transaksi atau perangkat.

## STOP / CHANGE CONTROL

STOP scope terdampak bila ada kontradiksi requirement, failure integrity/privacy/recovery, dependency belum terpenuhi, environment/biaya tool/destruction tidak diotorisasi, keputusan bisnis mendasar atau model executor yang diminta tidak tersedia. Catat gap/acceptance terdampak; kerjakan bagian independen yang masih diotorisasi. Jangan mengubah makna bisnis demi implementasi.

## UPDATE / COMMIT / NEXT

Catat path/revision/command/result/reviewer/fix/hold aktual; update kartu dan 11, serta 10 hanya untuk keputusan nyata, lalu **00 LAST**. COMPLETE memerlukan delivery bounded, checks dan completion holds terpenuhi; produk FINAL mengikuti 08.

Stage path exact yang disengaja dan review seluruh index; pertahankan pekerjaan tidak terkait. Commit lokal hanya sesudah BU diotorisasi eksplisit dan hasil koheren. Jangan push/merge/deploy/publish/beli atau melakukan destruction tanpa approval exact. STOP setelah closeout satu BU; jangan otomatis mengeksekusi berikutnya.
