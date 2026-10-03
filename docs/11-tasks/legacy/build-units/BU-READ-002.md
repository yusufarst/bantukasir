# BU-READ-002 — Owner finance, sesi dan audit reads dengan scope ketat

## STATUS DAN GOAL

**NOT STARTED / IMPLEMENTATION_NOT_STARTED.** Read finance/audit/seluruh session Pemilik dan dokumen/history Kasir sendiri, dengan periode/pagination bounded. Kartu ini specification, bukan hasil implementasi. Eksekusi belum diotorisasi.

Estimasi dari nol: I5h / V4h / E1h = **10h**, tanpa owner/external waiting dan risk reserve. Metode di [11](../11-BUILD-PLAN.md).

## READ

[AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu ini → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → [15-FINANCE-PROFITABILITY.md](../15-FINANCE-PROFITABILITY.md) → [17-POS-SALES.md](../17-POS-SALES.md) → [06-INVENTORY-SPEC.md](../06-INVENTORY-SPEC.md) → [04-AUTH-RBAC-SECURITY.md](../04-AUTH-RBAC-SECURITY.md) → [08/DoD](../08-TESTING-ACCEPTANCE.md). [CLAUDE](../../CLAUDE.md) berlaku bagi executor.

## DEPENDENCY DAN HOLDS

Artifact: [BU-SALE-002](BU-SALE-002.md), [BU-CASH-002](BU-CASH-002.md). Setiap provider harus mempunyai evidence PASS yang dibekukan pada revision kompatibel. **Gate global sebelum seluruh coding:** [P7](../planning/P7-UX-DESIGN.md) COMPLETE dengan desain aktual DesainPakeAI, approval visual owner exact revision dan [DESIGN](../../DESIGN.md)/kontrak token yang dinormalisasi; lalu P8–P11 difinalisasi ulang/FROZEN dan owner mengotorisasi Execute satu BU eksplisit. Paket saat ini REOPENED_DRAFT/PENDING_P7_APPROVAL; coding belum diotorisasi. P7 adalah provider planning, bukan dependency BU.

Tidak ada hold khusus untuk engineering non-production yang independen.

[Gap register](../planning/GAPS-CHANGES.md) memisahkan blueprint, owner facts, tests dan release authorization. Execute eksplisit satu BU tetap wajib; release/destruction/paid tools memerlukan scope authorization terpisah.

## REQUIREMENT DAN ACCEPTANCE

CORE-002, CORE-017, CORE-018, CORE-025, CORE-027, CORE-028, CORE-029, CORE-036, CORE-049, CORE-059, CORE-064, CORE-069. [CORE-MAP](../requirements/CORE-MAP.md) dan seluruh subklausul canonical tetap wajib.

Suite: FIN02, FIN03, AUDIT01, SESSION07, SESSION09, SESSION10, AUTH02, PERF01. Bukti delivery/verifikasi terarah ini tidak memberi product suite FINAL PASS otomatis.

## DATA, ACTION DAN UI BOUNDARY

Data/tabel: Read index/view dari source immutable serta projection current; bukan ledger alternatif.

Read finance/audit/seluruh session Pemilik dan dokumen/history Kasir sendiri, dengan periode/pagination bounded. Gunakan FormRequest/policy/Action/DTO sesuai P4/P5/P6; jangan serialize entity Eloquent privileged. Consumer UI memakai React/Inertia/shadcn native dengan kontrak approved; provider backend yang independen secara teknis dapat dijadwalkan tanpa menunggu implementasi consumer UI setelah gate global terpenuhi dan BU terotorisasi; ini bukan izin coding saat paket planning masih draft. Tidak ada capability Later.

## DO

- Snapshot laporan konsisten dengan asOf/timezone/cost revision/completeness; HPP/profit UNKNOWN tidak ditampilkan sebagai angka lengkap, revenue tetap jujur; tanggal half-open Jakarta.
- Total harian dari Sale/payment dihitung sekali; variance/opening session bukan revenue; midnight span/laporan as-closed dipertahankan; correction kemudian memakai tanggal posting.
- Otorisasi ulang read Sale/session sendiri, history/export/media/status; staff memakai allowlist DTO, Pemilik mempunyai grant eksplisit.

Risiko: Stok historis tidak dibuat dari metrik sales/current LOW; snapshot konsisten tidak memakai statistik response dari waktu berbeda.

## VERIFY

- Batas periode/custom/month/leap/midnight/beberapa session tanpa double count, UNKNOWN/refund/revision, query failure bukan zero, ID tebakan ditolak pada setiap channel read/status.
- composer test:postgres; query plan; test DTO/error/cache aman; performance final BU-VERIFY-004.

Delapan dimensi [DoD](../08-TESTING-ACCEPTANCE.md) mencatat applicability dan evidence. Jalankan unit/HTTP/PostgreSQL nyata/rollback/race/privilege/privacy/browser/device/recovery/performance sesuai risiko; pemeriksaan yang belum dijalankan tetap PENDING. Verifikasi target non-production sebelum fixture. Mock/screenshot tidak membuktikan transaksi atau perangkat.

## STOP / CHANGE CONTROL

STOP scope terdampak bila ada kontradiksi requirement, failure integrity/privacy/recovery, dependency belum terpenuhi, environment/biaya tool/destruction tidak diotorisasi, keputusan bisnis mendasar atau model executor yang diminta tidak tersedia. Catat gap/acceptance terdampak; kerjakan bagian independen yang masih diotorisasi. Jangan mengubah makna bisnis demi implementasi.

## UPDATE / COMMIT / NEXT

Catat path/revision/command/result/reviewer/fix/hold aktual; update kartu dan 11, serta 10 hanya untuk keputusan nyata, lalu **00 LAST**. COMPLETE memerlukan delivery bounded, checks dan completion holds terpenuhi; produk FINAL mengikuti 08.

Stage path exact yang disengaja dan review seluruh index; pertahankan pekerjaan tidak terkait. Commit lokal hanya sesudah BU diotorisasi eksplisit dan hasil koheren. Jangan push/merge/deploy/publish/beli atau melakukan destruction tanpa approval exact. STOP setelah closeout satu BU; jangan otomatis mengeksekusi berikutnya.
