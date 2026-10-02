# BU-VERIFY-003 — Final scanner dan label/receipt printer nyata

## STATUS DAN GOAL

**NOT STARTED / IMPLEMENTATION_NOT_STARTED.** Bukti fisik USB/Bluetooth HID/manual fallback, scan label serta thermal/A4/reprint. Kartu ini specification, bukan hasil implementasi. Eksekusi belum diotorisasi.

Estimasi dari nol: I0h / V5h / E1h = **6h**, tanpa owner/external waiting dan risk reserve. Metode di [11](../11-BUILD-PLAN.md).

## READ

[AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu ini → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → [12-BARCODE-SCANNER.md](../12-BARCODE-SCANNER.md) → [17-POS-SALES.md](../17-POS-SALES.md) → [08-TESTING-ACCEPTANCE.md](../08-TESTING-ACCEPTANCE.md) → [08/DoD](../08-TESTING-ACCEPTANCE.md). [CLAUDE](../../CLAUDE.md) berlaku bagi executor.

## DEPENDENCY DAN HOLDS

Artifact: [BU-UI-002](BU-UI-002.md), [BU-UI-003](BU-UI-003.md), [BU-DOC-001](BU-DOC-001.md). Setiap provider harus mempunyai evidence PASS yang dibekukan pada revision kompatibel. **Gate global sebelum seluruh coding:** [P7](../planning/P7-UX-DESIGN.md) COMPLETE dengan desain aktual DesainPakeAI, approval visual owner exact revision dan [DESIGN](../../DESIGN.md)/kontrak token yang dinormalisasi; lalu P8–P11 difinalisasi ulang/FROZEN dan owner mengotorisasi Execute satu BU eksplisit. Paket saat ini REOPENED_DRAFT/PENDING_P7_APPROVAL; coding belum diotorisasi. P7 adalah provider planning, bukan dependency BU.

- DEVICES aktual wajib; konfirmasi media 58/80 mm/label

[Gap register](../planning/GAPS-CHANGES.md) memisahkan blueprint, owner facts, tests dan release authorization. Execute eksplisit satu BU tetap wajib; release/destruction/paid tools memerlukan scope authorization terpisah.

## REQUIREMENT DAN ACCEPTANCE

CORE-035, CORE-071. [CORE-MAP](../requirements/CORE-MAP.md) dan seluruh subklausul canonical tetap wajib.

Suite: DEVICE01. Final point hanya untuk suite yang dipetakan pada 08; seluruh consumer dan applicable holds diperlukan.

## DATA, ACTION DAN UI BOUNDARY

Data/tabel: Witness hardware/media/OS/browser aktual; hanya data test terisolasi yang diidentifikasi.

Bukti fisik USB/Bluetooth HID/manual fallback, scan label serta thermal/A4/reprint. Gunakan FormRequest/policy/Action/DTO sesuai P4/P5/P6; jangan serialize entity Eloquent privileged. Consumer UI memakai React/Inertia/shadcn native dengan kontrak approved; provider backend yang independen secara teknis dapat dijadwalkan tanpa menunggu implementasi consumer UI setelah gate global terpenuhi dan BU terotorisasi; ini bukan izin coding saat paket planning masih draft. Tidak ada capability Later.

## DO

- Catat scanner/printer/driver/browser/media/code/revision; buktikan repeated/partial/disconnect/CRLF/IME/focus/dialog/count guard.
- Print Code128/quiet zone dan scan nyata dengan identitas label benar; thermal/A4/reprint snapshot lama nyata terbaca/aman/tanpa fakta bisnis duplikat.
- Keyboard/cart touch/payment/count mobile pada device aktual; jangan mengklaim emulasi sebagai hardware PASS.

Risiko: Penutupan dialog print browser bukan cetak fisik.

## VERIFY

- Scan dan dokumen fisik dengan witness terkait exact revision; count source Sale/payment/stock tidak menambah fakta.
- Gap perangkat/perangkat tidak tersedia tetap WAITING EXTERNAL; acceptance operator dicatat.

Delapan dimensi [DoD](../08-TESTING-ACCEPTANCE.md) mencatat applicability dan evidence. Jalankan unit/HTTP/PostgreSQL nyata/rollback/race/privilege/privacy/browser/device/recovery/performance sesuai risiko; pemeriksaan yang belum dijalankan tetap PENDING. Verifikasi target non-production sebelum fixture. Mock/screenshot tidak membuktikan transaksi atau perangkat.

## STOP / CHANGE CONTROL

STOP scope terdampak bila ada kontradiksi requirement, failure integrity/privacy/recovery, dependency belum terpenuhi, environment/biaya tool/destruction tidak diotorisasi, keputusan bisnis mendasar atau model executor yang diminta tidak tersedia. Catat gap/acceptance terdampak; kerjakan bagian independen yang masih diotorisasi. Jangan mengubah makna bisnis demi implementasi.

## UPDATE / COMMIT / NEXT

Catat path/revision/command/result/reviewer/fix/hold aktual; update kartu dan 11, serta 10 hanya untuk keputusan nyata, lalu **00 LAST**. COMPLETE memerlukan delivery bounded, checks dan completion holds terpenuhi; produk FINAL mengikuti 08.

Stage path exact yang disengaja dan review seluruh index; pertahankan pekerjaan tidak terkait. Commit lokal hanya sesudah BU diotorisasi eksplisit dan hasil koheren. Jangan push/merge/deploy/publish/beli atau melakukan destruction tanpa approval exact. STOP setelah closeout satu BU; jangan otomatis mengeksekusi berikutnya.
