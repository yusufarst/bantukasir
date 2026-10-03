# P10 — Migration, release, cutover dan UAT

**Status: REOPENED_DRAFT / PENDING_P7_APPROVAL; RELEASE NOT AUTHORIZED.** Draf release/UAT yang valid dipertahankan; tidak COMPLETE atau FROZEN. [P7](P7-UX-DESIGN.md) harus menghasilkan desain aktual DesainPakeAI, approval visual owner eksplisit atas exact revision serta [DESIGN](../../DESIGN.md)/kontrak token yang dinormalisasi, kemudian P8–P9 difinalisasi ulang dan dibekukan sebelum P10 direview/diterima ulang. Sesuaikan UAT role/state/document/device dengan kontrak visual approved; freeze P10 baru membuka finalisasi P11. Seluruh coding dan product tests tetap NOT STARTED/tidak diotorisasi; approval visual bukan izin deployment atau bukti runtime FINAL PASS. [09](../09-DEPLOYMENT-OPS.md), [08](../08-TESTING-ACCEPTANCE.md), [gap register](GAPS-CHANGES.md) dan [11](../11-BUILD-PLAN.md) menetapkan gate aktual.

## Readiness sebelum meminta otorisasi

1. Candidate terintegrasi dengan revision source/schema/aset/config yang tepat. Seluruh 39 suite mencapai FINAL sesuai final point, kecuali RELEASE01 yang membutuhkan pilot aktual. Perubahan signifikan setelah evidence memerlukan rerun checks terdampak.
2. Approval DESIGN untuk exact revision/rights; G1 commercial nota/legal/buyer; G2 master/unit/barcode/volume opening/evidence aktual; G3 drawer/float/handover/funding refund; G4 backup/key/access/cost independen dan measured RPO/RTO; serta DEVICES aktual diterima tanpa blocker integrity/privacy/recovery.
3. VPS/domain/access/bootstrap secret/TLS renewal/resource capacity tujuan terverifikasi dengan credential privat. Siapkan operasi deployment exact/revision/migration/cutover/count/rekonsiliasi/rollback plan konkret untuk review owner.
4. Bukti populated isolated migration, real restore/recovery epoch, perangkat fisik dan performance tersedia. Training owner/operator, contact incident dan recovery access independen siap serta disimpan privat.
5. Readiness BU-RELEASE-001 menghasilkan paket review; otorisasi owner untuk exact target/revision/operation membuka BU-RELEASE-002. Readiness/commit/Execute BU coding biasa bukan izin deploy.

## Cutover dan fallback

Verifikasi approval dan ambil independent encrypted backup/snapshot manifest sebelum migration berisiko; gunakan forward/additive saja. Count/master/bootstrap client dilakukan saat freeze bisnis yang diotorisasi. Opening manual memakai review bounded/zero checklist/rekonsiliasi, tanpa demo seed/reset produksi. Jangan mengarang volume master/count atau memakai fixture ATK sebagai data nyata.

Setelah count/cost/barcode/register/profile/identity/rekonsiliasi siap, owner melepas onboarding flag dan menjalankan pilot role nyata. Jangan menahan database lock saat orang menghitung atau menunggu printer/bank. Jika integrity/privacy/recovery gagal, hentikan writes terdampak, pertahankan original dan gunakan runbook recovery. Rollback software hanya bila kompatibilitas schema/backward aman; setelah money/stock writes, jangan memulihkan backup lebih lama secara buta. Gunakan forward fix/correction atau incident recovery terisolasi dengan rekonsiliasi gap, bukan penulisan ulang fakta.

## UAT/pilot yang terukur

| Actor | Evidence aktual |
| --- | --- |
| Pemilik | Identitas client/users/register, opening Pemilik/cost UNKNOWN/correction/funding refund, Laba Kotor/period/rekap/laporan sesuai keadaan saat ditutup/audit/status |
| Admin Operasional | Katalog search-first dengan stok 0, barcode duplikat, biaya receiving write-only, manual issue dengan alasan wajib, LOW/OUT/history aman; penolakan route sensitif |
| Kasir | Opening float 0 sendiri, USB/Bluetooth HID/manual fallback/quantity minus, CASH tender/change/konfirmasi BANK_TRANSFER penuh, original recovery UNCERTAIN, close/recount/handover baru, receipt/report sendiri |
| Operator/Pemilik | Thermal/A4/label dengan scan fisik/reprint identitas lama, tanpa fakta duplikat; rekonsiliasi stock/cash/transfer/Sale/payment/cost/session/audit; independent backup + restore access |

Catat equipment/OS/browser/media/target/revision/commands/results/known issues/approval owner aktual dan biaya nyata. Commercial nota bukan faktur pajak legal. RELEASE01 FINAL hanya setelah deployment/pilot aktual yang diotorisasi serta owner acceptance. Status 00 LAST mencatat operasi yang benar-benar terjadi.

Perlindungan database tetap berlaku: destruction memerlukan approval EXACT tersendiri; deploy/migrate/fix generik tidak mengizinkan DROP/TRUNCATE/mass delete/reset/down-v/penulisan ulang history.
