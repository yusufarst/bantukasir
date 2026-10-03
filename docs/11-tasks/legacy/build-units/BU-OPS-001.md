# BU-OPS-001 — Packaging self-host, Nginx TLS dan SQL privilege protection

## STATUS DAN GOAL

**NOT STARTED / IMPLEMENTATION_NOT_STARTED.** Compose PHP-FPM/Nginx/PostgreSQL non-production, TLS/renewal, health dan konfigurasi keamanan. Kartu ini specification, bukan hasil implementasi. Eksekusi belum diotorisasi.

Estimasi dari nol: I4h / V3h / E1h = **8h**, tanpa owner/external waiting dan risk reserve. Metode di [11](../11-BUILD-PLAN.md).

## READ

[AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu ini → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → [09-DEPLOYMENT-OPS.md](../09-DEPLOYMENT-OPS.md) → [planning/P4-ARCHITECTURE.md](../planning/P4-ARCHITECTURE.md) → [04-AUTH-RBAC-SECURITY.md](../04-AUTH-RBAC-SECURITY.md) → [08/DoD](../08-TESTING-ACCEPTANCE.md). [CLAUDE](../../CLAUDE.md) berlaku bagi executor.

## DEPENDENCY DAN HOLDS

Artifact: [BU-FOUND-001](BU-FOUND-001.md). Setiap provider harus mempunyai evidence PASS yang dibekukan pada revision kompatibel. **Gate global sebelum seluruh coding:** [P7](../planning/P7-UX-DESIGN.md) COMPLETE dengan desain aktual DesainPakeAI, approval visual owner exact revision dan [DESIGN](../../DESIGN.md)/kontrak token yang dinormalisasi; lalu P8–P11 difinalisasi ulang/FROZEN dan owner mengotorisasi Execute satu BU eksplisit. Paket saat ini REOPENED_DRAFT/PENDING_P7_APPROVAL; coding belum diotorisasi. P7 adalah provider planning, bukan dependency BU.

- Target non-production terotorisasi untuk TLS aktual; RELEASE tidak otomatis

[Gap register](../planning/GAPS-CHANGES.md) memisahkan blueprint, owner facts, tests dan release authorization. Execute eksplisit satu BU tetap wajib; release/destruction/paid tools memerlukan scope authorization terpisah.

## REQUIREMENT DAN ACCEPTANCE

CORE-031, CORE-036, CORE-038, CORE-065, CORE-068. [CORE-MAP](../requirements/CORE-MAP.md) dan seluruh subklausul canonical tetap wajib.

Suite: DB01, SEC01, PERF01. Bukti delivery/verifikasi terarah ini tidak memberi product suite FINAL PASS otomatis.

## DATA, ACTION DAN UI BOUNDARY

Data/tabel: Role database runtime/migration/test/backup dan application grant; grant khusus domain ditambahkan setiap BU schema.

Compose PHP-FPM/Nginx/PostgreSQL non-production, TLS/renewal, health dan konfigurasi keamanan. Gunakan FormRequest/policy/Action/DTO sesuai P4/P5/P6; jangan serialize entity Eloquent privileged. Consumer UI memakai React/Inertia/shadcn native dengan kontrak approved; provider backend yang independen secara teknis dapat dijadwalkan tanpa menunggu implementasi consumer UI setelah gate global terpenuhi dan BU terotorisasi; ini bukan izin coding saat paket planning masih draft. Tidak ada capability Later.

## DO

- Database privat, webroot hanya public, APP_DEBUG false, validasi secret/credential SQL terpisah serta readiness test tanpa data/leak.
- Rehearsal TLS/ACME provisioning/renewal pada target terisolasi terotorisasi; kompatibilitas header/CSP dan resource request bounded; tanpa SSR/Redis/worker umum.
- Template permission menolak DROP dan mutation tabel immutable; BU schema wajib menambah grant/negative test; hanya non-production sampai release diotorisasi.

Risiko: Packaging bukan approval deploy. Jangan expose PostgreSQL, .env, vendor, debug route atau direktori backup.

## VERIFY

- Boot Compose terisolasi, serangan URL/secret/static path, rehearsal header/cookie/TLS renewal, DDL runtime terlarang; fixture data pada target test eksplisit.
- composer analyse; npm run build; health container/config terisolasi; TLS aktual bila target tersedia, PENDING bila access belum ada.

Delapan dimensi [DoD](../08-TESTING-ACCEPTANCE.md) mencatat applicability dan evidence. Jalankan unit/HTTP/PostgreSQL nyata/rollback/race/privilege/privacy/browser/device/recovery/performance sesuai risiko; pemeriksaan yang belum dijalankan tetap PENDING. Verifikasi target non-production sebelum fixture. Mock/screenshot tidak membuktikan transaksi atau perangkat.

## STOP / CHANGE CONTROL

STOP scope terdampak bila ada kontradiksi requirement, failure integrity/privacy/recovery, dependency belum terpenuhi, environment/biaya tool/destruction tidak diotorisasi, keputusan bisnis mendasar atau model executor yang diminta tidak tersedia. Catat gap/acceptance terdampak; kerjakan bagian independen yang masih diotorisasi. Jangan mengubah makna bisnis demi implementasi.

## UPDATE / COMMIT / NEXT

Catat path/revision/command/result/reviewer/fix/hold aktual; update kartu dan 11, serta 10 hanya untuk keputusan nyata, lalu **00 LAST**. COMPLETE memerlukan delivery bounded, checks dan completion holds terpenuhi; produk FINAL mengikuti 08.

Stage path exact yang disengaja dan review seluruh index; pertahankan pekerjaan tidak terkait. Commit lokal hanya sesudah BU diotorisasi eksplisit dan hasil koheren. Jangan push/merge/deploy/publish/beli atau melakukan destruction tanpa approval exact. STOP setelah closeout satu BU; jangan otomatis mengeksekusi berikutnya.
