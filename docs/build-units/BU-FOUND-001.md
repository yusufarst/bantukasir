# BU-FOUND-001 — Fondasi Laravel, React dan harness PostgreSQL terisolasi

## STATUS DAN GOAL

**NOT STARTED / IMPLEMENTATION_NOT_STARTED.** Fondasi modular monolith Laravel/Inertia/React/TypeScript/shadcn, toolchain Composer/npm, PostgreSQL non-production dan guard target test. Kartu ini specification, bukan hasil implementasi. Eksekusi belum diotorisasi.

Estimasi dari nol: I4h / V3h / E1h = **8h**, tanpa owner/external waiting dan risk reserve. Metode di [11](../11-BUILD-PLAN.md).

## READ

[AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu ini → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → [02-ARCHITECTURE.md](../02-ARCHITECTURE.md) → [04-AUTH-RBAC-SECURITY.md](../04-AUTH-RBAC-SECURITY.md) → [09-DEPLOYMENT-OPS.md](../09-DEPLOYMENT-OPS.md) → [planning/P4-ARCHITECTURE.md](../planning/P4-ARCHITECTURE.md) → [08/DoD](../08-TESTING-ACCEPTANCE.md). [CLAUDE](../../CLAUDE.md) berlaku bagi executor.

## DEPENDENCY DAN HOLDS

Artifact: tidak ada. Setiap provider harus mempunyai evidence PASS yang dibekukan pada revision kompatibel. Seluruh UI produksi menunggu BU-DESIGN-001 COMPLETE dengan approval owner atas exact revision.

Tidak ada hold khusus untuk engineering non-production yang independen.

[Gap register](../planning/GAPS-CHANGES.md) memisahkan blueprint, owner facts, tests dan release authorization. Execute eksplisit satu BU tetap wajib; release/destruction/paid tools memerlukan scope authorization terpisah.

## REQUIREMENT DAN ACCEPTANCE

CORE-031, CORE-038. [CORE-MAP](../requirements/CORE-MAP.md) dan seluruh subklausul canonical tetap wajib.

Suite: DB01, SEC01. Bukti delivery/verifikasi terarah ini tidak memberi product suite FINAL PASS otomatis.

## DATA, ACTION DAN UI BOUNDARY

Data/tabel: Schema auth/session library dan tabel harness test minimum; belum schema bisnis.

Fondasi modular monolith Laravel/Inertia/React/TypeScript/shadcn, toolchain Composer/npm, PostgreSQL non-production dan guard target test. Gunakan FormRequest/policy/Action/DTO sesuai P4/P5/P6; jangan serialize entity Eloquent privileged. Consumer UI memakai React/Inertia/shadcn native dengan kontrak approved; provider backend yang independen tidak menunggu visual. Tidak ada capability Later.

## DO

- Verifikasi versi kompatibel/advisory/lisensi, pin composer.lock dan satu npm lockfile; gunakan starter React resmi sebagai scaffold yang diperiksa, nonaktifkan signup/2FA/teams yang bukan Core.
- Sediakan scripts lint/analyse/unit/PostgreSQL/race/typecheck/build/E2E, environment validation dan health tanpa secret; PHP-FPM runtime, Vite build-time, SSR nonaktif.
- Pisahkan credential runtime/migration/test. Siapkan harness dua koneksi/proses, fixture committed dan failure injection; buktikan transaksi/rollback sederhana pada PostgreSQL nyata.

Risiko: Scaffold resmi bukan implementasi domain; default SQLite/RefreshDatabase bukan bukti race.

## VERIFY

- Fresh isolated startup/build, konfigurasi invalid ditolak tanpa secret, koneksi dan rollback PostgreSQL nyata; test runner menolak target production.
- composer validate; composer lint; composer analyse; composer test:unit; composer test:postgres; npm run lint; npm run typecheck; npm run build. Scripts harus tersedia dari BU ini.

Delapan dimensi [DoD](../08-TESTING-ACCEPTANCE.md) mencatat applicability dan evidence. Jalankan unit/HTTP/PostgreSQL nyata/rollback/race/privilege/privacy/browser/device/recovery/performance sesuai risiko; pemeriksaan yang belum dijalankan tetap PENDING. Verifikasi target non-production sebelum fixture. Mock/screenshot tidak membuktikan transaksi atau perangkat.

## STOP / CHANGE CONTROL

STOP scope terdampak bila ada kontradiksi requirement, failure integrity/privacy/recovery, dependency belum terpenuhi, environment/biaya tool/destruction tidak diotorisasi, keputusan bisnis mendasar atau model executor yang diminta tidak tersedia. Catat gap/acceptance terdampak; kerjakan bagian independen yang masih diotorisasi. Jangan mengubah makna bisnis demi implementasi.

## UPDATE / COMMIT / NEXT

Catat path/revision/command/result/reviewer/fix/hold aktual; update kartu dan 11, serta 10 hanya untuk keputusan nyata, lalu **00 LAST**. COMPLETE memerlukan delivery bounded, checks dan completion holds terpenuhi; produk FINAL mengikuti 08.

Stage path exact yang disengaja dan review seluruh index; pertahankan pekerjaan tidak terkait. Commit lokal hanya sesudah BU diotorisasi eksplisit dan hasil koheren. Jangan push/merge/deploy/publish/beli atau melakukan destruction tanpa approval exact. STOP setelah closeout satu BU; jangan otomatis mengeksekusi berikutnya.