# BU-SALE-001 — Sale pembayaran penuh atomik dan receipt snapshot

## STATUS DAN GOAL

**NOT STARTED / IMPLEMENTATION_NOT_STARTED.** Sale GOODS atomik dengan satu pembayaran penuh CASH atau BANK_TRANSFER, recovery original result dan read Sale/history sendiri sesuai scope. Kartu ini specification, bukan hasil implementasi. Eksekusi belum diotorisasi.

Estimasi dari nol: I7h / V5h / E1h = **13h**, tanpa owner/external waiting dan risk reserve. Metode di [11](../11-BUILD-PLAN.md).

## READ

[AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu ini → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → [17-POS-SALES.md](../17-POS-SALES.md) → [15-FINANCE-PROFITABILITY.md](../15-FINANCE-PROFITABILITY.md) → [02-ARCHITECTURE.md](../02-ARCHITECTURE.md) → [planning/P6-CONCURRENCY-API.md](../planning/P6-CONCURRENCY-API.md) → [08/DoD](../08-TESTING-ACCEPTANCE.md). [CLAUDE](../../CLAUDE.md) berlaku bagi executor.

## DEPENDENCY DAN HOLDS

Artifact: [BU-CFG-001](BU-CFG-001.md), [BU-INV-002](BU-INV-002.md), [BU-CASH-001](BU-CASH-001.md). Setiap provider harus mempunyai evidence PASS yang dibekukan pada revision kompatibel. **Gate global sebelum seluruh coding:** [P7](../planning/P7-UX-DESIGN.md) COMPLETE dengan desain aktual DesainPakeAI, approval visual owner exact revision dan [DESIGN](../../DESIGN.md)/kontrak token yang dinormalisasi; lalu P8–P11 difinalisasi ulang/FROZEN dan owner mengotorisasi Execute satu BU eksplisit. Paket saat ini REOPENED_DRAFT/PENDING_P7_APPROVAL; coding belum diotorisasi. P7 adalah provider planning, bukan dependency BU.

- G3 untuk workflow fisik penerimaan dana serta G1 dokumen client sebelum pilot

[Gap register](../planning/GAPS-CHANGES.md) memisahkan blueprint, owner facts, tests dan release authorization. Execute eksplisit satu BU tetap wajib; release/destruction/paid tools memerlukan scope authorization terpisah.

## REQUIREMENT DAN ACCEPTANCE

CORE-002, CORE-003, CORE-010, CORE-011, CORE-012, CORE-013, CORE-016, CORE-021, CORE-030, CORE-036, CORE-039, CORE-040, CORE-048, CORE-053, CORE-054, CORE-055, CORE-056, CORE-057. [CORE-MAP](../requirements/CORE-MAP.md) dan seluruh subklausul canonical tetap wajib.

Suite: POS01, POS02, POS03, FIN01, OPEN01, SESSION03, AUTH02, AUTH03, IDENTITY01, PAYEXT01, PERF01. Bukti delivery/verifikasi terarah ini tidak memberi product suite FINAL PASS otomatis.

## DATA, ACTION DAN UI BOUNDARY

Data/tabel: Sale/lines, satu full Payment, SALE_ISSUE, IssueCost matched/UNKNOWN, ReceiptSnapshot dan activityVersion session.

Sale GOODS atomik dengan satu pembayaran penuh CASH atau BANK_TRANSFER, recovery original result dan read Sale/history sendiri sesuai scope. Gunakan FormRequest/policy/Action/DTO sesuai P4/P5/P6; jangan serialize entity Eloquent privileged. Consumer UI memakai React/Inertia/shadcn native dengan kontrak approved; provider backend yang independen secara teknis dapat dijadwalkan tanpa menunggu implementasi consumer UI setelah gate global terpenuhi dan BU terotorisasi; ini bukan izin coding saat paket planning masih draft. Tidak ada capability Later.

## DO

- Wajib session OPEN sendiri yang eligible untuk Kasir/Pemilik, termasuk Sale transfer-only; validasi server atas harga/version/active/stock/quantity/total; tanpa override/split.
- Sale/lines/Payment/issue/balance/cost/snapshot/audit/result atomik; satu full applied payment, CASH tender/change dan BANK_TRANSFER dengan konfirmasi manusia atas penerimaan penuh dana.
- Capture identity/template/assets saat commit; boundary provider masa depan terpisah dari core, tanpa callback/webhook/status asynchronous; otorisasi ulang status original setelah CLOSED.
- Sediakan read API list/detail/status/receipt Sale milik actor (seluruhnya bagi Pemilik berizin), dengan pagination/DTO operasional tanpa cost leak. Close/report history milik actor disediakan BU-CASH-002.

Risiko: Recording bank transfer bukan settlement bank; uncertainty tidak mengizinkan permintaan pembayaran ulang.

## VERIFY

- Aritmetika pembayaran penuh, mixed/partial/metode unknown ditolak, unit terakhir/dua cashier, race freeze/revoke/config/price, failure setiap write atomik, source sama/key berbeda/response hilang/recovery setelah CLOSED.
- composer test:unit; composer test:postgres; composer test:race; count/rekonsiliasi sebelum dan sesudah serta result DTO aman.
- Request langsung atas ID Sale/receipt tebakan dan read finance/cost oleh staff ditolak; list/detail Sale sendiri/status original tetap benar setelah CLOSED.

Delapan dimensi [DoD](../08-TESTING-ACCEPTANCE.md) mencatat applicability dan evidence. Jalankan unit/HTTP/PostgreSQL nyata/rollback/race/privilege/privacy/browser/device/recovery/performance sesuai risiko; pemeriksaan yang belum dijalankan tetap PENDING. Verifikasi target non-production sebelum fixture. Mock/screenshot tidak membuktikan transaksi atau perangkat.

## STOP / CHANGE CONTROL

STOP scope terdampak bila ada kontradiksi requirement, failure integrity/privacy/recovery, dependency belum terpenuhi, environment/biaya tool/destruction tidak diotorisasi, keputusan bisnis mendasar atau model executor yang diminta tidak tersedia. Catat gap/acceptance terdampak; kerjakan bagian independen yang masih diotorisasi. Jangan mengubah makna bisnis demi implementasi.

## UPDATE / COMMIT / NEXT

Catat path/revision/command/result/reviewer/fix/hold aktual; update kartu dan 11, serta 10 hanya untuk keputusan nyata, lalu **00 LAST**. COMPLETE memerlukan delivery bounded, checks dan completion holds terpenuhi; produk FINAL mengikuti 08.

Stage path exact yang disengaja dan review seluruh index; pertahankan pekerjaan tidak terkait. Commit lokal hanya sesudah BU diotorisasi eksplisit dan hasil koheren. Jangan push/merge/deploy/publish/beli atau melakukan destruction tanpa approval exact. STOP setelah closeout satu BU; jangan otomatis mengeksekusi berikutnya.
