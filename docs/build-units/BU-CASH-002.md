# BU-CASH-002 — Race-safe close dan immutable session report

## STATUS DAN GOAL

**NOT STARTED / IMPLEMENTATION_NOT_STARTED.** Close dengan preview/count/activityVersion, assisted close Pemilik beralasan dan laporan sesuai keadaan saat ditutup. Kartu ini specification, bukan hasil implementasi. Eksekusi belum diotorisasi.

Estimasi dari nol: I4h / V4h / E1h = **9h**, tanpa owner/external waiting dan risk reserve. Metode di [11](../11-BUILD-PLAN.md).

## READ

[AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu ini → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → [17-POS-SALES.md](../17-POS-SALES.md) → [04-AUTH-RBAC-SECURITY.md](../04-AUTH-RBAC-SECURITY.md) → [08/DoD](../08-TESTING-ACCEPTANCE.md). [CLAUDE](../../CLAUDE.md) berlaku bagi executor.

## DEPENDENCY DAN HOLDS

Artifact: [BU-SALE-001](BU-SALE-001.md). Setiap provider harus mempunyai evidence PASS yang dibekukan pada revision kompatibel. Seluruh UI produksi menunggu BU-DESIGN-001 COMPLETE dengan approval owner atas exact revision.

- G3 untuk praktik count/handover fisik

[Gap register](../planning/GAPS-CHANGES.md) memisahkan blueprint, owner facts, tests dan release authorization. Execute eksplisit satu BU tetap wajib; release/destruction/paid tools memerlukan scope authorization terpisah.

## REQUIREMENT DAN ACCEPTANCE

CORE-003, CORE-019, CORE-020, CORE-022, CORE-023, CORE-024, CORE-025, CORE-028, CORE-051, CORE-052, CORE-058, CORE-059. [CORE-MAP](../requirements/CORE-MAP.md) dan seluruh subklausul canonical tetap wajib.

Suite: SESSION01, SESSION02, SESSION04, SESSION05, SESSION06, SESSION07, SESSION10, AUTH03. Bukti delivery/verifikasi terarah ini tidak memberi product suite FINAL PASS otomatis.

## DATA, ACTION DAN UI BOUNDARY

Data/tabel: CashSessionReportSnapshot, result CLOSED dan linked owner review notes.

Close dengan preview/count/activityVersion, assisted close Pemilik beralasan dan laporan sesuai keadaan saat ditutup. Gunakan FormRequest/policy/Action/DTO sesuai P4/P5/P6; jangan serialize entity Eloquent privileged. Consumer UI memakai React/Inertia/shadcn native dengan kontrak approved; provider backend yang independen tidak menunggu visual. Tidak ada capability Later.

## DO

- Preview tanpa human database lock; session lock/version sama dengan checkout; version stale memerlukan recount dan session tetap OPEN.
- Commit physical/expected/variance/cutoff/source IDs/snapshot/CLOSED/audit/result atomik; close ekuivalen dengan key berbeda mengembalikan result awal; count konflik ditolak.
- Expected cash berasal dari opening + applied CASH; transfer/refund Pemilik terpisah; tanpa reopen/CLOSING/penimpaan tanggung jawab oleh Pemilik; review note terpisah.
- Sediakan read API list/detail/history laporan session milik actor dan seluruhnya bagi Pemilik berizin dengan pagination/DTO aman. UI POS tidak bergantung pada finance Pemilik untuk history session sendiri.

Risiko: Close tidak boleh kehilangan Sale sukses atau mengubah atribusi original.

## VERIFY

- Sale menang melawan close atau close menang melawan Sale, close ekuivalen/konflik concurrent, version stale, rollback failure snapshot, recovery UNCERTAIN setelah CLOSED, midnight/kembali pada tanggal sama/handover.
- composer test:postgres; composer test:race; referensi expected Rp120.000/variance −Rp2.000 dan bukti snapshot immutable.
- Request langsung history/report session cashier lain ditolak; read own/all-owner berizin benar setelah CLOSED dan snapshot immutable tetap sama.

Delapan dimensi [DoD](../08-TESTING-ACCEPTANCE.md) mencatat applicability dan evidence. Jalankan unit/HTTP/PostgreSQL nyata/rollback/race/privilege/privacy/browser/device/recovery/performance sesuai risiko; pemeriksaan yang belum dijalankan tetap PENDING. Verifikasi target non-production sebelum fixture. Mock/screenshot tidak membuktikan transaksi atau perangkat.

## STOP / CHANGE CONTROL

STOP scope terdampak bila ada kontradiksi requirement, failure integrity/privacy/recovery, dependency belum terpenuhi, environment/biaya tool/destruction tidak diotorisasi, keputusan bisnis mendasar atau model executor yang diminta tidak tersedia. Catat gap/acceptance terdampak; kerjakan bagian independen yang masih diotorisasi. Jangan mengubah makna bisnis demi implementasi.

## UPDATE / COMMIT / NEXT

Catat path/revision/command/result/reviewer/fix/hold aktual; update kartu dan 11, serta 10 hanya untuk keputusan nyata, lalu **00 LAST**. COMPLETE memerlukan delivery bounded, checks dan completion holds terpenuhi; produk FINAL mengikuti 08.

Stage path exact yang disengaja dan review seluruh index; pertahankan pekerjaan tidak terkait. Commit lokal hanya sesudah BU diotorisasi eksplisit dan hasil koheren. Jangan push/merge/deploy/publish/beli atau melakukan destruction tanpa approval exact. STOP setelah closeout satu BU; jangan otomatis mengeksekusi berikutnya.