# BU-UI-004 — Owner corrections, finance/session/audit dan system UI

## STATUS DAN GOAL

**NOT STARTED / IMPLEMENTATION_NOT_STARTED.** Workspace Pemilik kaya informasi, form correction bounded, profit/trend/filter waktu yang jujur serta seluruh session/audit/status sistem. Kartu ini specification, bukan hasil implementasi. Eksekusi belum diotorisasi.

Estimasi dari nol: I7h / V5h / E1h = **13h**, tanpa owner/external waiting dan risk reserve. Metode di [11](../11-BUILD-PLAN.md).

## READ

[AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu ini → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → [06-INVENTORY-SPEC.md](../06-INVENTORY-SPEC.md) → [15-FINANCE-PROFITABILITY.md](../15-FINANCE-PROFITABILITY.md) → [17-POS-SALES.md](../17-POS-SALES.md) → [09-DEPLOYMENT-OPS.md](../09-DEPLOYMENT-OPS.md) → [../DESIGN.md](../../DESIGN.md) → [08/DoD](../08-TESTING-ACCEPTANCE.md). [CLAUDE](../../CLAUDE.md) berlaku bagi executor.

## DEPENDENCY DAN HOLDS

Artifact: [BU-UI-001](BU-UI-001.md), [BU-READ-002](BU-READ-002.md), [BU-OPS-002](BU-OPS-002.md). Setiap provider harus mempunyai evidence PASS yang dibekukan pada revision kompatibel. Seluruh UI produksi menunggu BU-DESIGN-001 COMPLETE dengan approval owner atas exact revision.

- DESIGN prerequisite; G3 praktik refund; G4 acceptance operasional aktual

[Gap register](../planning/GAPS-CHANGES.md) memisahkan blueprint, owner facts, tests dan release authorization. Execute eksplisit satu BU tetap wajib; release/destruction/paid tools memerlukan scope authorization terpisah.

## REQUIREMENT DAN ACCEPTANCE

CORE-002, CORE-009, CORE-014, CORE-017, CORE-018, CORE-025, CORE-027, CORE-028, CORE-029, CORE-034, CORE-049, CORE-051, CORE-060, CORE-061, CORE-069. [CORE-MAP](../requirements/CORE-MAP.md) dan seluruh subklausul canonical tetap wajib.

Suite: INV03, RET01, FIN02, FIN03, AUDIT01, SESSION07, SESSION09, SESSION10, AUTH02, UX01, AUTH03. Bukti delivery/verifikasi terarah ini tidak memberi product suite FINAL PASS otomatis.

## DATA, ACTION DAN UI BOUNDARY

Data/tabel: Consumer correction/report/status operasional; fakta original tidak diedit.

Workspace Pemilik kaya informasi, form correction bounded, profit/trend/filter waktu yang jujur serta seluruh session/audit/status sistem. Gunakan FormRequest/policy/Action/DTO sesuai P4/P5/P6; jangan serialize entity Eloquent privileged. Consumer UI memakai React/Inertia/shadcn native dengan kontrak approved; provider backend yang independen tidak menunggu visual. Tidak ada capability Later.

## DO

- Reason/recent authentication/eligibility refund-return/reversal; reversal tanpa field quantity/count yang tidak relevan; summary fisik before/after adjustment berbeda dari fakta historis original.
- Custom Range dengan Apply/Cancel/validasi/range half-open nyata; line chart trend + data accessible, UNKNOWN tanpa profit fiktif; source sales dihitung sekali/variance terpisah.
- Consumer session/history sendiri yang diizinkan aman bagi staff; backup/rekonsiliasi/status Pemilik memakai evidence aktual dan Belum Terverifikasi bila belum diukur; reuse UI1 untuk account/user/tindakan config.
- Sediakan form register code/label/active Pemilik yang memakai API BU-CASH-001, recent authentication dan audit. Tolak perubahan saat register mempunyai session OPEN; tanpa shift/drawer-sharing engine.

Risiko: Dashboard Pemilik kaya informasi dengan semantik tepat; template tidak mengaktifkan CRM/PO/expenses.

## VERIFY

- Correction append aktual/original tidak berubah, batas uang/quantity, replay ditolak/incomplete, beberapa session/midnight tanpa double count, custom cross-year/leap/Apply-Cancel.
- Staff ditolak pada network/data/export/media aktual; tabel panjang/legend/focus/lima viewport/zoom 200%; failure/stale status tidak tampak sukses.
- Test create/edit/active register Pemilik, wrong role/direct ID/recent authentication; race writer register melawan session open dan identitas historis dipertahankan.

Delapan dimensi [DoD](../08-TESTING-ACCEPTANCE.md) mencatat applicability dan evidence. Jalankan unit/HTTP/PostgreSQL nyata/rollback/race/privilege/privacy/browser/device/recovery/performance sesuai risiko; pemeriksaan yang belum dijalankan tetap PENDING. Verifikasi target non-production sebelum fixture. Mock/screenshot tidak membuktikan transaksi atau perangkat.

## STOP / CHANGE CONTROL

STOP scope terdampak bila ada kontradiksi requirement, failure integrity/privacy/recovery, dependency belum terpenuhi, environment/biaya tool/destruction tidak diotorisasi, keputusan bisnis mendasar atau model executor yang diminta tidak tersedia. Catat gap/acceptance terdampak; kerjakan bagian independen yang masih diotorisasi. Jangan mengubah makna bisnis demi implementasi.

## UPDATE / COMMIT / NEXT

Catat path/revision/command/result/reviewer/fix/hold aktual; update kartu dan 11, serta 10 hanya untuk keputusan nyata, lalu **00 LAST**. COMPLETE memerlukan delivery bounded, checks dan completion holds terpenuhi; produk FINAL mengikuti 08.

Stage path exact yang disengaja dan review seluruh index; pertahankan pekerjaan tidak terkait. Commit lokal hanya sesudah BU diotorisasi eksplisit dan hasil koheren. Jangan push/merge/deploy/publish/beli atau melakukan destruction tanpa approval exact. STOP setelah closeout satu BU; jangan otomatis mengeksekusi berikutnya.