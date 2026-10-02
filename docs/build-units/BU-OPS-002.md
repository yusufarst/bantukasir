# BU-OPS-002 — Reconciliation, independent backup dan restore tooling

## STATUS DAN GOAL

**NOT STARTED / IMPLEMENTATION_NOT_STARTED.** Rekonsiliasi harian, backup database + media/template terenkripsi serta workflow restore/epoch terisolasi yang dikendalikan. Kartu ini specification, bukan hasil implementasi. Eksekusi belum diotorisasi.

Estimasi dari nol: I5h / V4h / E1h = **10h**, tanpa owner/external waiting dan risk reserve. Metode di [11](../11-BUILD-PLAN.md).

## READ

[AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu ini → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → [09-DEPLOYMENT-OPS.md](../09-DEPLOYMENT-OPS.md) → [06-INVENTORY-SPEC.md](../06-INVENTORY-SPEC.md) → [15-FINANCE-PROFITABILITY.md](../15-FINANCE-PROFITABILITY.md) → [08/DoD](../08-TESTING-ACCEPTANCE.md). [CLAUDE](../../CLAUDE.md) berlaku bagi executor.

## DEPENDENCY DAN HOLDS

Artifact: [BU-OPS-001](BU-OPS-001.md), [BU-SALE-002](BU-SALE-002.md), [BU-CASH-002](BU-CASH-002.md). Setiap provider harus mempunyai evidence PASS yang dibekukan pada revision kompatibel. **Gate global sebelum seluruh coding:** [P7](../planning/P7-UX-DESIGN.md) COMPLETE dengan desain aktual DesainPakeAI, approval visual owner exact revision dan [DESIGN](../../DESIGN.md)/kontrak token yang dinormalisasi; lalu P8–P11 difinalisasi ulang/FROZEN dan owner mengotorisasi Execute satu BU eksplisit. Paket saat ini REOPENED_DRAFT/PENDING_P7_APPROVAL; coding belum diotorisasi. P7 adalah provider planning, bukan dependency BU.

- G4 destination/key/cost independen dan measured RPO/RTO yang diterima owner untuk final

[Gap register](../planning/GAPS-CHANGES.md) memisahkan blueprint, owner facts, tests dan release authorization. Execute eksplisit satu BU tetap wajib; release/destruction/paid tools memerlukan scope authorization terpisah.

## REQUIREMENT DAN ACCEPTANCE

CORE-002, CORE-007, CORE-031, CORE-032, CORE-033, CORE-066. [CORE-MAP](../requirements/CORE-MAP.md) dan seluruh subklausul canonical tetap wajib.

Suite: INV01, REC01, REC02, DB01, AUTH02. Bukti delivery/verifikasi terarah ini tidak memberi product suite FINAL PASS otomatis.

## DATA, ACTION DAN UI BOUNDARY

Data/tabel: Status operasional/incident rekonsiliasi aman; fakta bisnis original read-only.

Rekonsiliasi harian, backup database + media/template terenkripsi serta workflow restore/epoch terisolasi yang dikendalikan. Gunakan FormRequest/policy/Action/DTO sesuai P4/P5/P6; jangan serialize entity Eloquent privileged. Consumer UI memakai React/Inertia/shadcn native dengan kontrak approved; provider backend yang independen secara teknis dapat dijadwalkan tanpa menunggu implementasi consumer UI setelah gate global terpenuhi dan BU terotorisasi; ini bukan izin coding saat paket planning masih draft. Tidak ada capability Later.

## DO

- Bandingkan seluruh relationship ledger/balance/cost/Sale/payment/receipt/session; mismatch memblokir writes terdampak, dengan incident Pemilik dan replay yang dikendalikan.
- Jadwalkan backup terenkripsi independen dengan manifest/checksum/media/aset/template immutable yang direferensikan; recovery key/access terpisah dan status Pemilik aman, bukan sukses statis.
- Restore ke target terisolasi baru dengan original dipertahankan; validasi/revoke/ubah epoch/review gap/measured RPO/RTO; target guard untuk operasi privileged serta perlindungan destruction database.

Risiko: Copy pada VPS yang sama bukan backup independen; exit code restore sukses bukan rekonsiliasi bisnis.

## VERIFY

- Aset corrupt/hilang, backup stale, disk dan incident terdeteksi; status runtime tanpa secret; backup nyata kecil + targeted isolated restore, populated final di BU-VERIFY-001.
- Command backup/restore/rekonsiliasi terisolasi dengan target eksplisit; bandingkan manifest/jumlah source, tanpa blind replay setelah epoch berubah.

Delapan dimensi [DoD](../08-TESTING-ACCEPTANCE.md) mencatat applicability dan evidence. Jalankan unit/HTTP/PostgreSQL nyata/rollback/race/privilege/privacy/browser/device/recovery/performance sesuai risiko; pemeriksaan yang belum dijalankan tetap PENDING. Verifikasi target non-production sebelum fixture. Mock/screenshot tidak membuktikan transaksi atau perangkat.

## STOP / CHANGE CONTROL

STOP scope terdampak bila ada kontradiksi requirement, failure integrity/privacy/recovery, dependency belum terpenuhi, environment/biaya tool/destruction tidak diotorisasi, keputusan bisnis mendasar atau model executor yang diminta tidak tersedia. Catat gap/acceptance terdampak; kerjakan bagian independen yang masih diotorisasi. Jangan mengubah makna bisnis demi implementasi.

## UPDATE / COMMIT / NEXT

Catat path/revision/command/result/reviewer/fix/hold aktual; update kartu dan 11, serta 10 hanya untuk keputusan nyata, lalu **00 LAST**. COMPLETE memerlukan delivery bounded, checks dan completion holds terpenuhi; produk FINAL mengikuti 08.

Stage path exact yang disengaja dan review seluruh index; pertahankan pekerjaan tidak terkait. Commit lokal hanya sesudah BU diotorisasi eksplisit dan hasil koheren. Jangan push/merge/deploy/publish/beli atau melakukan destruction tanpa approval exact. STOP setelah closeout satu BU; jangan otomatis mengeksekusi berikutnya.
