# BU-SALE-002 — Bounded owner refund dan verified physical return

## STATUS DAN GOAL

**NOT STARTED / IMPLEMENTATION_NOT_STARTED.** Correction uang/quantity oleh Pemilik dengan batas terpisah; original Sale/Payment/receipt immutable. Kartu ini specification, bukan hasil implementasi. Eksekusi belum diotorisasi.

Estimasi dari nol: I5h / V4h / E1h = **10h**, tanpa owner/external waiting dan risk reserve. Metode di [11](../11-BUILD-PLAN.md).

## READ

[AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu ini → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → [17-POS-SALES.md](../17-POS-SALES.md) → [15-FINANCE-PROFITABILITY.md](../15-FINANCE-PROFITABILITY.md) → [06-INVENTORY-SPEC.md](../06-INVENTORY-SPEC.md) → [08/DoD](../08-TESTING-ACCEPTANCE.md). [CLAUDE](../../CLAUDE.md) berlaku bagi executor.

## DEPENDENCY DAN HOLDS

Artifact: [BU-SALE-001](BU-SALE-001.md), [BU-INV-003](BU-INV-003.md). Setiap provider harus mempunyai evidence PASS yang dibekukan pada revision kompatibel. Seluruh UI produksi menunggu BU-DESIGN-001 COMPLETE dengan approval owner atas exact revision.

- G3 dana/praktik refund Pemilik sebelum acceptance payout nyata

[Gap register](../planning/GAPS-CHANGES.md) memisahkan blueprint, owner facts, tests dan release authorization. Execute eksplisit satu BU tetap wajib; release/destruction/paid tools memerlukan scope authorization terpisah.

## REQUIREMENT DAN ACCEPTANCE

CORE-002, CORE-003, CORE-014, CORE-018, CORE-022, CORE-025, CORE-059, CORE-060. [CORE-MAP](../requirements/CORE-MAP.md) dan seluruh subklausul canonical tetap wajib.

Suite: RET01, FIN03, SESSION04, SESSION07, AUTH02, AUTH03. Bukti delivery/verifikasi terarah ini tidak memberi product suite FINAL PASS otomatis.

## DATA, ACTION DAN UI BOUNDARY

Data/tabel: Evidence Refund yang executed, fakta Return serta matched RETURN_RECEIPT/revision cost allocation.

Correction uang/quantity oleh Pemilik dengan batas terpisah; original Sale/Payment/receipt immutable. Gunakan FormRequest/policy/Action/DTO sesuai P4/P5/P6; jangan serialize entity Eloquent privileged. Consumer UI memakai React/Inertia/shadcn native dengan kontrak approved; provider backend yang independen tidak menunggu visual. Tidak ada capability Later.

## DO

- Reason/recent authentication/payout executed terkonfirmasi dari dana Pemilik; lock Sale original dan product; batas monetary/line/quantity tersisa terpisah.
- Refund-only tidak menambah stok atau membalik HPP; verified saleable return memulihkan matched historical cost sekali; UNKNOWN tetap incomplete; damaged tidak menjadi usable stock.
- Append correction/audit/result atomik; return setelah refund tetap dalam batas quantity; tanpa exchange/credit/drawer paid-out engine.

Risiko: Payout nyata berlangsung di luar database; result recording UNCERTAIN dipulihkan, bukan pembayaran kedua.

## VERIFY

- Race refund/return kumulatif/duplikasi/key berbeda, batas uang/quantity terpisah, rollback failure, saleable/damaged/refund-only dan konsistensi revision biaya original.
- composer test:unit; composer test:postgres; composer test:race; snapshot original tidak berubah.

Delapan dimensi [DoD](../08-TESTING-ACCEPTANCE.md) mencatat applicability dan evidence. Jalankan unit/HTTP/PostgreSQL nyata/rollback/race/privilege/privacy/browser/device/recovery/performance sesuai risiko; pemeriksaan yang belum dijalankan tetap PENDING. Verifikasi target non-production sebelum fixture. Mock/screenshot tidak membuktikan transaksi atau perangkat.

## STOP / CHANGE CONTROL

STOP scope terdampak bila ada kontradiksi requirement, failure integrity/privacy/recovery, dependency belum terpenuhi, environment/biaya tool/destruction tidak diotorisasi, keputusan bisnis mendasar atau model executor yang diminta tidak tersedia. Catat gap/acceptance terdampak; kerjakan bagian independen yang masih diotorisasi. Jangan mengubah makna bisnis demi implementasi.

## UPDATE / COMMIT / NEXT

Catat path/revision/command/result/reviewer/fix/hold aktual; update kartu dan 11, serta 10 hanya untuk keputusan nyata, lalu **00 LAST**. COMPLETE memerlukan delivery bounded, checks dan completion holds terpenuhi; produk FINAL mengikuti 08.

Stage path exact yang disengaja dan review seluruh index; pertahankan pekerjaan tidak terkait. Commit lokal hanya sesudah BU diotorisasi eksplisit dan hasil koheren. Jangan push/merge/deploy/publish/beli atau melakukan destruction tanpa approval exact. STOP setelah closeout satu BU; jangan otomatis mengeksekusi berikutnya.