# BU-IAM-001 — Autentikasi, lifecycle akun dan otorisasi tiga role

## STATUS DAN GOAL

**NOT STARTED / IMPLEMENTATION_NOT_STARTED.** Server session/Fortify, private owner bootstrap, invite/reset/revoke, own-account dan capability/object policies tepat tiga role. Kartu ini specification, bukan hasil implementasi. Eksekusi belum diotorisasi.

Estimasi dari nol: I5h / V4h / E1h = **10h**, tanpa owner/external waiting dan risk reserve. Metode di [11](../11-BUILD-PLAN.md).

## READ

[AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu ini → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → [04-AUTH-RBAC-SECURITY.md](../04-AUTH-RBAC-SECURITY.md) → [planning/P5-SECURITY.md](../planning/P5-SECURITY.md) → [08/DoD](../08-TESTING-ACCEPTANCE.md). [CLAUDE](../../CLAUDE.md) berlaku bagi executor.

## DEPENDENCY DAN HOLDS

Artifact: [BU-FOUND-001](BU-FOUND-001.md). Provider harus mempunyai evidence compatible PASS yang dibekukan. **Gate global sebelum seluruh coding:** [P7](../planning/P7-UX-DESIGN.md) COMPLETE dengan desain aktual DesainPakeAI, approval visual owner exact revision dan [DESIGN](../../DESIGN.md)/kontrak token yang dinormalisasi; lalu P8–P11 difinalisasi ulang/FROZEN dan owner mengotorisasi Execute satu BU eksplisit. Paket saat ini REOPENED_DRAFT/PENDING_P7_APPROVAL; coding belum diotorisasi. P7 adalah provider planning, bukan dependency BU.

Tidak ada hold khusus untuk engineering non-production yang independen.

[Gap register](../planning/GAPS-CHANGES.md) memisahkan blueprint, owner facts, tests dan release authorization. Execute eksplisit satu BU tetap wajib; release/destruction/paid tools memerlukan scope authorization terpisah.

## REQUIREMENT DAN ACCEPTANCE

CORE-001, CORE-002, CORE-003, CORE-038, CORE-041, CORE-068. [CORE-MAP](../requirements/CORE-MAP.md) dan seluruh subklausul canonical tetap wajib.

Suite: AUTH01, AUTH02, AUTH03, SEC01. Delivery/targeted proof ini tidak memberi product suite FINAL PASS otomatis.

## DATA, ACTION DAN UI BOUNDARY

Data/tables: User, library auth/session/token dan actor guard; identitas historis dipertahankan.

Server session/Fortify, private owner bootstrap, invite/reset/revoke, own-account dan capability/object policies tepat tiga role. Gunakan FormRequest/policy/Action/DTO sesuai P4/P5/P6; jangan serialize privileged Eloquent entity. UI consumer memakai native React/Inertia/shadcn approved contract; provider backend yang independen secara teknis dapat dijadwalkan tanpa menunggu implementasi consumer UI setelah gate global terpenuhi dan BU terotorisasi; ini bukan izin coding saat paket planning masih draft. Tidak ada capability Later.

## DO

- Implementasikan bootstrap privat tanpa default account, token invite/reset expiring single-use, account disable/revoke, password/session hardening dan recent auth.
- Terapkan role dan cakupan server pada direct routes/media/status; actor berasal session; Operations tidak POS dan Cashier tidak product-management.
- Gunakan guard actor bersama dengan command consumer; seragamkan login/reset error untuk enumeration, regenerate session dan deny mass assignment/unsafe redirects.
- Sediakan kontrol server Inertia untuk enkripsi history terautentikasi, lifecycle clear/rotate key pada logout/pergantian actor/revoke yang teramati, serta respons sensitif private/no-store. Uji kontrak server dengan HTTP tanpa menunggu UI produksi. Consumer remembered forms/query cache/Back/Forward/bfcache dibangun pada BU-UI-001; biaya receiving write-only tidak pernah dipersist meskipun dienkripsi.

Risiko: Fortify menyediakan mekanisme; policies, disable races dan safe DTO tetap kode domain yang wajib diuji.

## VERIFY

- Negative/positive HTTP tests semua role, ID tebakan, session fixation/expiry/CSRF/origin/rate-limit/token reuse/revoke, recent auth dan unsafe body fields.
- composer test:unit; composer test:postgres; composer test:race untuk harness revoke-vs-command; composer analyse. COMPLETE unit ini memakai bukti server terarah, tanpa menunggu browser UI yang belum dibuat.
- Uji flag history encryption, signal clear/key lifecycle, headers, session regeneration/revocation dan DTO aman melalui HTTP. Bukti browser pergantian actor milik BU-UI-001; bukti lengkap finance Pemilik→staff milik BU-VERIFY-002. Bukti server awal bukan AUTH02 FINAL PASS.

Delapan dimensi [DoD](../08-TESTING-ACCEPTANCE.md) mencatat applicability dan evidence. Jalankan unit/HTTP/PostgreSQL nyata/rollback/race/privilege/privacy/browser/device/recovery/performance sesuai risiko; pemeriksaan yang belum dijalankan tetap PENDING. Verifikasi target non-production sebelum fixture. Mock/screenshot tidak membuktikan transaksi atau perangkat.

## STOP / CHANGE CONTROL

STOP affected scope pada kontradiksi requirement, integrity/privacy/recovery failure, unmet dependency, unauthorized environment/tool cost/destruction, fundamental business decision atau requested executor model yang tak tersedia. Catat gap/affected acceptance dan kerjakan bagian independen yang masih authorized. Jangan mengubah business meaning demi implementasi.

## UPDATE / COMMIT / NEXT

Catat actual paths/revision/commands/results/reviewer/fixes/holds; update kartu dan11,10 hanya keputusan nyata, **00 LAST**. COMPLETE memerlukan bounded delivery+checks+completion holds; product FINAL mengikuti08.

Stage exact intended paths dan review seluruh index; preserve unrelated work. Local commit hanya sesudah BU explicitly authorized dan koheren. Jangan push/merge/deploy/publish/purchase atau destruction tanpa approval exact. STOP setelah closeout satu BU; tidak otomatis execute berikutnya.
