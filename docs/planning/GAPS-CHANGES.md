# Gap register dan change control

Koreksi P7: P0–P6 tetap frozen input; **P7 desain aktual + OWNER VISUAL APPROVAL exact revision + DESIGN dinormalisasi** wajib sebelum refinalisasi/freeze P8–P11. Draft valid dipreservasi, tetapi seluruh coding belum diotorisasi. Sesudah paket frozen, holds fakta client menahan bagian yang terdampak, bukan otomatis seluruh engineering independen. Fakta yang belum ada tidak dianggap approval.

| ID / kelas | Status | Fakta/evidence berikut | Scope yang tertahan / owner |
| --- | --- | --- | --- |
| DESIGN / OWNER_VISUAL | WAITING OWNER VISUAL REVIEW | Kandidat V1 BantuKasir Design `sha256-b51d2975aa3a8a92`, page `p7-v1`; [checks bounded/holds](../evidence/p7-correction/REVIEW.md). Approval owner exact revision, entitlement/rights/font/aset dan normalisasi final masih required; approvedRevision null | P7 COMPLETE, kemudian freeze ulang P8–P11 dan otorisasi coding; owner/design planner. Bukan BU implementasi |
| P7-VALIDATION / TECHNICAL | PARTIAL / NOT VERIFIED | Lima lebar shell/role serta targeted interaction checks lulus; actual zoom200%, screen reader, software keyboard dan full rendered AA belum terverifikasi. Foundation design lint0errors/39warnings dicatat terpisah dari compiler | Exit P7 COMPLETE dan final DESIGN freeze; planner/verifier. Owner visual approval sendiri tidak mengubah Not verified menjadi PASS |
| G1 / BUSINESS_FACT | OPEN | Policy commercial nota/tax legal/identitas buyer nyata | Acceptance dokumen client/cutover BU-DOC/RELEASE; owner |
| G2 / BUSINESS_FACT | OPEN | Base unit/precision/katalog-volume opening/barcode/evidence biaya/kebutuhan serial; kapasitas onboarding manual | Master/opening/cutover nyata, bukan fixture backend terisolasi; owner/client |
| G3 / BUSINESS_FACT | OPEN | Label drawer aktual/praktik float 0/single responsibility/handover/count dan dana refund Pemilik terpisah | Workflow kas/refund fisik/pilot; owner |
| G4 / EXTERNAL + OWNER | OPEN | Destination/key/access operator/cost independen, restore nyata, measured RPO/RTO/data gap yang diterima | REC01/REC02 FINAL dan pilot; target RPO 6 jam/RTO 4 jam masih proposal; owner/operator |
| DEVICES / EXTERNAL | PENDING | Witness USB/Bluetooth HID/label/thermal/A4 printer/media/driver aktual | DEVICE01 FINAL dan acceptance operasional; owner/operator |
| TARGET / EXTERNAL | PENDING | VPS/network/non-production access/TLS renewal/host capacity tujuan aktual | Benchmark representatif/bukti operasi; owner/operator; tanpa production write |
| EXECUTOR / EXTERNAL | NOT VERIFIED | Ketersediaan Claude Opus 5.5/xhigh dan Fable 5.1/xhigh selektif sesuai permintaan pada harness aktual | Pemilihan handoff sebelum BU terotorisasi; user/operator; tanpa fallback diam-diam |
| RELEASE / AUTHORIZATION | NOT AUTHORIZED | Approval exact target/revision/migration/deploy/cutover eksplisit serta acceptance pilot aktual | BU-RELEASE-002 saja; owner |
| TESTS / IMPLEMENTATION | NOT STARTED | Seluruh 39 suite final pada revision terkini, migration/race/privacy/UI/perangkat/restore/performance | Acceptance produk; coding executor/verifier; bukan gap keputusan owner |

## Keputusan pendukung minimum

Audit/result atomik, source intent/epoch, DTO aman/biaya write-only, batas input/security headers/session guards, ledger/rekonsiliasi/revision biaya dan backup independen diperlukan untuk correctness/security/operability. Ini tidak menambah warehouse/multi-tenant/customer/PO/expenses/HR/payment engine. [10](../10-DECISIONS.md) mencatat alasan dan kelas keputusan.

## Alur perubahan

Catat proposal → sumber owner/evidence teknis → kelas keputusan → requirement/fase/BU/suite/data/ops terdampak → alternatif lebih sederhana/biaya/dampak operasional → review/fix → versi freeze → update mapping/DAG/estimasi/acceptance → 00 LAST. Perubahan scope/makna bisnis memerlukan OWNER_DECISION_REQUIRED sebelum eksekusi terdampak; keputusan teknis biasa bersifat autonomous dengan alasan.

Jangan membuat timestamp/check/approval fiktif. Perubahan requirement/invariant/sumber visual/version/target yang membatalkan evidence harus ditandai dan diuji ulang pada revision terkini yang terdampak. Acceptance historis tetap menjadi histori; jangan memindahkannya sebagai PASS baru.

Gap teknis dari independent review dicatat pada [review evidence](../evidence/rebaseline-p0-p11/REVIEW.md) dengan status fix/verified; reviewer tidak menutup gate owner.
