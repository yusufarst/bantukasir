# BU-INV-003 — Owner adjustment, evidence revision dan reversal/replay berbatas

## STATUS DAN GOAL

**NOT STARTED / IMPLEMENTATION_NOT_STARTED.** Correction Pemilik dengan reason/recent authentication yang mempertahankan original dan publikasi revision atomik. Kartu ini specification, bukan hasil implementasi. Eksekusi belum diotorisasi.

Estimasi dari nol: I7h / V5h / E1h = **13h**, tanpa owner/external waiting dan risk reserve. Metode di [11](../11-BUILD-PLAN.md).

## READ

[AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu ini → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → [06-INVENTORY-SPEC.md](../06-INVENTORY-SPEC.md) → [15-FINANCE-PROFITABILITY.md](../15-FINANCE-PROFITABILITY.md) → [04-AUTH-RBAC-SECURITY.md](../04-AUTH-RBAC-SECURITY.md) → [08/DoD](../08-TESTING-ACCEPTANCE.md). [CLAUDE](../../CLAUDE.md) berlaku bagi executor.

## DEPENDENCY DAN HOLDS

Artifact: [BU-INV-002](BU-INV-002.md). Setiap provider harus mempunyai evidence PASS yang dibekukan pada revision kompatibel. **Gate global sebelum seluruh coding:** [P7](../planning/P7-UX-DESIGN.md) COMPLETE dengan desain aktual DesainPakeAI, approval visual owner exact revision dan [DESIGN](../../DESIGN.md)/kontrak token yang dinormalisasi; lalu P8–P11 difinalisasi ulang/FROZEN dan owner mengotorisasi Execute satu BU eksplisit. Paket saat ini REOPENED_DRAFT/PENDING_P7_APPROVAL; coding belum diotorisasi. P7 adalah provider planning, bukan dependency BU.

Tidak ada hold khusus untuk engineering non-production yang independen.

[Gap register](../planning/GAPS-CHANGES.md) memisahkan blueprint, owner facts, tests dan release authorization. Execute eksplisit satu BU tetap wajib; release/destruction/paid tools memerlukan scope authorization terpisah.

## REQUIREMENT DAN ACCEPTANCE

CORE-002, CORE-003, CORE-009, CORE-017, CORE-018, CORE-036, CORE-061. [CORE-MAP](../requirements/CORE-MAP.md) dan seluruh subklausul canonical tetap wajib.

Suite: INV03, FIN02, FIN03, AUTH02, AUTH03, PERF01. Bukti delivery/verifikasi terarah ini tidak memberi product suite FINAL PASS otomatis.

## DATA, ACTION DAN UI BOUNDARY

Data/tabel: Revision cost/allocation, link source ADJUSTMENT/REVERSAL dan pointer current valuation.

Correction Pemilik dengan reason/recent authentication yang mempertahankan original dan publikasi revision atomik. Gunakan FormRequest/policy/Action/DTO sesuai P4/P5/P6; jangan serialize entity Eloquent privileged. Consumer UI memakai React/Inertia/shadcn native dengan kontrak approved; provider backend yang independen secara teknis dapat dijadwalkan tanpa menunggu implementasi consumer UI setelah gate global terpenuhi dan BU terotorisasi; ini bukan izin coding saat paket planning masih draft. Tidak ada capability Later.

## DO

- Revision cost evidence Pemilik dan replay deterministik bounded; posting product menunggu atau ditolak saat busy; publikasi all-or-nothing.
- Standalone full reversal yang eligible hanya sekali dengan inverse fisik tepat/link source/cost revision; tolak direct reversal SALE_ISSUE/RETURN_RECEIPT, balance negatif atau chronology dependent invalid.
- Adjustment positif memerlukan cost/UNKNOWN; rekonsiliasi current/historical; fallback cap/timeout incomplete tanpa publikasi partial.
- Pasangan original + REVERSAL dihitung tepat sekali: ledger fisik mempertahankan original dan inverse; replay valuation membatalkan source logical lalu melewati inverse pasangannya. Jangan menjumlahkan cancellation dan inverse untuk kedua kali; validasi seluruh prefix dependent dan link revision biaya.

Risiko: Referensi 10.000 movement bukan jaminan terukur; replay tidak aman/melebihi cap ditolak dan reporting incomplete.

## VERIFY

- Race reversal key berbeda, chronology receipt/issue/return dependent, byte/fakta original dipertahankan, failure replay/publication, posting concurrent dan permission/recent authentication.
- composer test:unit; composer test:postgres; composer test:race; evidence memori/durasi bounded final pada BU-VERIFY-004.
- Fixture: OPEN10/cost100 + RECEIPT10/cost200 + ISSUE5 → physical15/value225/issuecost75. Reverse RECEIPT10 → physical5/value50/revised issuecost50, allocation75 original dipertahankan. Receipt10→issue10→receipt20/current20: reversal pertama current10 tetapi prefix logical invalid; tolak dan rollback seluruh fakta/publication.

Delapan dimensi [DoD](../08-TESTING-ACCEPTANCE.md) mencatat applicability dan evidence. Jalankan unit/HTTP/PostgreSQL nyata/rollback/race/privilege/privacy/browser/device/recovery/performance sesuai risiko; pemeriksaan yang belum dijalankan tetap PENDING. Verifikasi target non-production sebelum fixture. Mock/screenshot tidak membuktikan transaksi atau perangkat.

## STOP / CHANGE CONTROL

STOP scope terdampak bila ada kontradiksi requirement, failure integrity/privacy/recovery, dependency belum terpenuhi, environment/biaya tool/destruction tidak diotorisasi, keputusan bisnis mendasar atau model executor yang diminta tidak tersedia. Catat gap/acceptance terdampak; kerjakan bagian independen yang masih diotorisasi. Jangan mengubah makna bisnis demi implementasi.

## UPDATE / COMMIT / NEXT

Catat path/revision/command/result/reviewer/fix/hold aktual; update kartu dan 11, serta 10 hanya untuk keputusan nyata, lalu **00 LAST**. COMPLETE memerlukan delivery bounded, checks dan completion holds terpenuhi; produk FINAL mengikuti 08.

Stage path exact yang disengaja dan review seluruh index; pertahankan pekerjaan tidak terkait. Commit lokal hanya sesudah BU diotorisasi eksplisit dan hasil koheren. Jangan push/merge/deploy/publish/beli atau melakukan destruction tanpa approval exact. STOP setelah closeout satu BU; jangan otomatis mengeksekusi berikutnya.
