# P8 — Testing, security tests dan Definition of Done

**Status: REOPENED_DRAFT / PENDING_P7_APPROVAL.** Draf acceptance/DoD yang valid dipertahankan. P8 tidak COMPLETE atau FROZEN sampai [P7](P7-UX-DESIGN.md) mempunyai desain aktual DesainPakeAI, approval visual owner atas exact revision serta [DESIGN](../../DESIGN.md)/kontrak token yang dinormalisasi. Setelah P7 COMPLETE, sesuaikan cakupan state/role/responsive/a11y dengan kontrak approved, lakukan review/validation ulang, lalu freeze P8 sebelum melanjutkan freeze fase berikut. Seluruh coding tetap tidak diotorisasi selama paket P8–P11 belum dibekukan ulang; runtime tests seluruhnya masih NOT STARTED/PENDING.

Canonical [08](../08-TESTING-ACCEPTANCE.md) memuat 39 suite/0 FINAL PASS, 73 requirement yang dipetakan dan delapan dimensi DoD. Jalur ancaman [P5](P5-SECURITY.md) dan race [P6](P6-CONCURRENCY-API.md) menjadi input test, bukan sekadar daftar pemeriksaan dalam prose.

Provider menambahkan suite terarah sesuai feature dan rollback/race PostgreSQL aktual dekat implementasi. Consumer melengkapi bukti HTTP/browser/storage/export/role; verifikasi final menilai release candidate terintegrasi. Mock/prototype/screenshot hanya membuktikan scope yang memang diukur; tidak membuktikan constraint, hardware, restore atau performance.

Pemilik suite dan final point eksplisit pada 08/CORE-MAP. BU verifikasi final BU-VERIFY-001 sampai BU-VERIFY-004 tidak memberikan credit implementasi. Rework defect signifikan memakai reserve/reestimasi, bukan dianggap gratis. Seluruh applicable checks yang belum dijalankan tetap PENDING; N/A beralasan hanya untuk dimensi yang tidak relevan.

Author melakukan self-review, lalu reviewer independen/adversarial dengan temuan material, fix dan verify. Security/money/stock/recovery dapat memerlukan review kedua bila risiko/temuan membenarkannya; review harus mempunyai bukti. Catat version/advisory/license, command/target/dataset/revision/result yang tepat, holds yang diketahui dan evidence yang invalid setelah perubahan.

Checks rebaseline docs-only: UTF-8/secret/whitespace/link Markdown lokal; kelengkapan requirement → BU → suite; graf tanpa cycle/dependency tersedia/perhitungan critical path; konsistensi fase/review; supersession historis; preservasi source/aset; exact scope index dan parent satu commit. Jangan mengklaim app test yang belum dijalankan sebagai PASS.
