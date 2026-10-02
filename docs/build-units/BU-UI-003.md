# BU-UI-003 — POS draft/scanner, full payment, open/close dan own-history UI

## STATUS DAN GOAL

**NOT STARTED / IMPLEMENTATION_NOT_STARTED.** Desktop/tablet/mobile Kasir terfokus dengan cart/quantity/payment/identitas session ergonomis dan recovery original. Kartu ini specification, bukan hasil implementasi. Eksekusi belum diotorisasi.

Estimasi dari nol: I7h / V5h / E1h = **13h**, tanpa owner/external waiting dan risk reserve. Metode di [11](../11-BUILD-PLAN.md).

## READ

[AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu ini → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → [17-POS-SALES.md](../17-POS-SALES.md) → [12-BARCODE-SCANNER.md](../12-BARCODE-SCANNER.md) → [04-AUTH-RBAC-SECURITY.md](../04-AUTH-RBAC-SECURITY.md) → [../DESIGN.md](../../DESIGN.md) → [08/DoD](../08-TESTING-ACCEPTANCE.md). [CLAUDE](../../CLAUDE.md) berlaku bagi executor.

## DEPENDENCY DAN HOLDS

Artifact: [BU-UI-001](BU-UI-001.md), [BU-SALE-001](BU-SALE-001.md), [BU-CASH-002](BU-CASH-002.md). Setiap provider harus mempunyai evidence PASS yang dibekukan pada revision kompatibel. Seluruh UI produksi menunggu BU-DESIGN-001 COMPLETE dengan approval owner atas exact revision.

- DESIGN prerequisite; G3 praktik nyata; DEVICES final

[Gap register](../planning/GAPS-CHANGES.md) memisahkan blueprint, owner facts, tests dan release authorization. Execute eksplisit satu BU tetap wajib; release/destruction/paid tools memerlukan scope authorization terpisah.

## REQUIREMENT DAN ACCEPTANCE

CORE-002, CORE-006, CORE-011, CORE-012, CORE-013, CORE-019, CORE-020, CORE-021, CORE-022, CORE-023, CORE-024, CORE-028, CORE-034, CORE-035, CORE-039, CORE-052, CORE-053, CORE-054, CORE-055, CORE-057, CORE-058, CORE-067. [CORE-MAP](../requirements/CORE-MAP.md) dan seluruh subklausul canonical tetap wajib.

Suite: SEARCH03, POS01, POS02, POS03, SESSION01, SESSION02, SESSION03, SESSION04, SESSION05, SESSION06, SESSION10, AUTH02, UX01, PAYEXT01, DEVICE01. Bukti delivery/verifikasi terarah ini tidak memberi product suite FINAL PASS otomatis.

## DATA, ACTION DAN UI BOUNDARY

Data/tabel: Consumer session OPEN/Sale/close/status/history sendiri; draft hanya di client.

Desktop/tablet/mobile Kasir terfokus dengan cart/quantity/payment/identitas session ergonomis dan recovery original. Gunakan FormRequest/policy/Action/DTO sesuai P4/P5/P6; jangan serialize entity Eloquent privileged. Consumer UI memakai React/Inertia/shadcn native dengan kontrak approved; provider backend yang independen tidak menunggu visual. Tidak ada capability Later.

## DO

- NO_SESSION dengan opening float 0 eksplisit; identitas session OPEN milik actor tetap; ordered HID scan/manual/IME/quantity plus-minus/remove-undo/token unknown dipertahankan; pause capture pada dialog/count.
- Satu metode penuh CASH atau BANK_TRANSFER dengan konfirmasi dana nyata, review harga authoritative; scanner Enter tidak membayar; bekukan submit intent dan pulihkan original melalui status/reload saat UNCERTAIN.
- Close preview/count/activityVersion/recount/laporan CLOSED immutable/history sendiri; aksi penting mobile terjangkau, cart panjang/keyboard/drawer tidak terpotong.

Risiko: Cart mobile ergonomis bukan offline queue, payment gateway atau capability POS tambahan.

## VERIFY

- Checkout nyata sukses/definite rejection/stok terakhir/harga stale/closed/timeout/double click/reload original lalu link print; CASH tender/change dan BANK_TRANSFER tanpa dampak laci.
- Own-session menolak actor lain, race close melawan Sale PostgreSQL serta state browser, scan partial/repeated/CRLF/focus/order/zoom 200%/lima viewport; perangkat nyata final terpisah.

Delapan dimensi [DoD](../08-TESTING-ACCEPTANCE.md) mencatat applicability dan evidence. Jalankan unit/HTTP/PostgreSQL nyata/rollback/race/privilege/privacy/browser/device/recovery/performance sesuai risiko; pemeriksaan yang belum dijalankan tetap PENDING. Verifikasi target non-production sebelum fixture. Mock/screenshot tidak membuktikan transaksi atau perangkat.

## STOP / CHANGE CONTROL

STOP scope terdampak bila ada kontradiksi requirement, failure integrity/privacy/recovery, dependency belum terpenuhi, environment/biaya tool/destruction tidak diotorisasi, keputusan bisnis mendasar atau model executor yang diminta tidak tersedia. Catat gap/acceptance terdampak; kerjakan bagian independen yang masih diotorisasi. Jangan mengubah makna bisnis demi implementasi.

## UPDATE / COMMIT / NEXT

Catat path/revision/command/result/reviewer/fix/hold aktual; update kartu dan 11, serta 10 hanya untuk keputusan nyata, lalu **00 LAST**. COMPLETE memerlukan delivery bounded, checks dan completion holds terpenuhi; produk FINAL mengikuti 08.

Stage path exact yang disengaja dan review seluruh index; pertahankan pekerjaan tidak terkait. Commit lokal hanya sesudah BU diotorisasi eksplisit dan hasil koheren. Jangan push/merge/deploy/publish/beli atau melakukan destruction tanpa approval exact. STOP setelah closeout satu BU; jangan otomatis mengeksekusi berikutnya.