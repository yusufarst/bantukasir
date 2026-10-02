# 10 — Keputusan efektif baseline P0–P11

2 Oktober 2026. Register ini menggantikan keputusan eksekusi Plan 2.0/P2-Dxx, tanpa menghapus histori atau invariant valid. Instruksi owner terbaru mengotorisasi autonomous planning P0–P11, pilihan stack A/B dari prinsip pertama, Build Units baru dan satu commit planning lokal. Tidak ada izin coding aplikasi atau operasi produksi.

| ID | Kelas / sumber | Keputusan efektif dan alasan |
| --- | --- | --- |
| D-001 | OWNER_DECISION_REQUIRED → RESOLVED, instruksi terbaru | Adopsi kerangka governance/planning/review/quality/DoD/continuity/BU dari diskusi; tidak menyalin domain/aset/token/project MULTIPLECORP. P0–P11 autonomous tanpa pertanyaan rutin antar fase |
| D-002 | OWNER, scope mandatory | V1 minimum: GOODS, satu stock pool, tepat tiga role, catalog/search/barcode/receipt/opening/manual issue/LOW-OUT, ledger/balance/MWA/Laba Kotor Pemilik/akuntabilitas kas/full Sale/dokumen/correction/audit/recovery/branding runtime; Later tidak aktif |
| D-003 | OWNER, policy pembayaran eksplisit | Satu completed Sale = satu pembayaran penuh CASH **atau** BANK_TRANSFER. Tanpa mixed/split/partial/debt/beberapa instrumen. Transfer dikonfirmasi manusia; boundary provider masa depan hanya arsitektur, tanpa callback/QRIS/webhook/state asynchronous V1 |
| D-004 | AUTONOMOUS, review khusus produk | Pilih modular monolith Laravel/Inertia/React/TypeScript/PostgreSQL/Nginx dari nol. Integrasi auth/validation/policies/HTTP + database tests memberi keuntungan foundation; A full TypeScript/Caddy tetap layak, tetapi kalah dalam integrasi aplikasi privat ini. PHP + TypeScript/Composer + NPM/FPM/TLS renewal menambah maintenance. [P4](planning/P4-ARCHITECTURE.md) mencatat sumber resmi/tradeoff/penilaian estimasi kecepatan. Stack dibekukan sesudah review paket; versi dipin pada BU-FOUND-001 |
| D-005 | AUTONOMOUS, infrastructure minimum | Session/cache-limiter database-backed, Vite pada build time, PHP-FPM/Nginx/PostgreSQL privat dan scheduler command. Tanpa SSR Node/Redis/queue/Octane/microservices secara default. Independent backup tetap wajib |
| D-006 | AUTONOMOUS, integritas | Ledger immutable/balance sinkron nonnegative; Sale lengkap atomik + snapshot/audit/result; MWA decimal presisi/UNKNOWN; source intent/key/fingerprint/epoch; guard berurutan yang sesuai serta retry transaksi bounded |
| D-007 | AUTONOMOUS, otoritas/privasi | Policies/object scope/pemeriksaan actor/config aktual + allowlist DTO per role pada seluruh HTML/Inertia/flash/old input/history/errors/status/cache/log/media/export/storage. Biaya incoming Operations write-only; Kasir tanpa pengelolaan master |
| D-008 | AUTONOMOUS, semantik correction | Original dipertahankan; batas refund uang/return quantity fisik terpisah; biaya historis dipulihkan sekali; alasan Pemilik/recent authentication, revision cost/allocation/replay bounded/publikasi all-or-nothing; chronology tidak aman ditolak |
| D-009 | AUTONOMOUS, akuntabilitas | Register/session ringan: satu OPEN per register DAN per cashier; session sendiri termasuk Pemilik/Sale transfer-only; opening 0 eksplisit; shared lock/activityVersion close/snapshot CLOSED immutable. Tanpa reopen/penimpaan tanggung jawab/shared drawer/paid-out |
| D-010 | OWNER arah desain + AUTONOMOUS kontrak | Exact approved BantuKasir Design revision/rights DPAI → token semantic repository → shadcn/React Inertia native. Codename/logo sementara; identitas client BusinessProfile versioned. MULTIPLECORP Design dilarang disentuh. Context teramati bukan approval |
| D-011 | OWNER policy executor | Primary handoff Claude Opus 5.5/xhigh; eskalasi Fable 5.1/xhigh selektif untuk risiko tinggi yang bounded. Label permintaan bukan klaim tersedia/dijalankan. BU portable tanpa memory chat; availability diverifikasi saat eksekusi |
| D-012 | AUTONOMOUS, redesign delivery | 28 BU baru dari boundary risiko/provider-consumer; 73 Core terpetakan; 39 suite/0 FINAL PASS. Engineering baru 259h (I113/V118/E28) + reserve 56h; critical path lower bound 128h. Operational read independen agar UI inventory tidak menunggu finance. Tidak mempertahankan ID T/boundary/236h lama |
| D-013 | AUTONOMOUS, pendukung keamanan | Threat-path model P5 + SEC01; Argon2id/guard aktual/session regeneration/revoke/CSRF/header/CSP/upload/DTO/advisory test. Recent auth 15 menit/inactivity 30 menit/absolute 12 jam/rate bounds adalah default teknis yang direview terhadap G3/host aktual |
| D-014 | AUTONOMOUS, change/freeze | Freeze blueprint specification sesudah self/independent review/fix/validation; visual/client/hardware/backup tests/release yang pending tidak ditutup. Buka kembali fase/requirement/BU/suite/DAG/estimasi terdampak bila evidence berubah |
| D-015 | OWNER otorisasi + keselamatan | Satu commit planning lokal hanya dengan scope path exact yang aman; tidak menjalankan BU pertama. Coding berikutnya memerlukan satu BU eksplisit; otorisasi push/deploy/pembelian/destruction exact terpisah |
| D-016 | AUTONOMOUS, preservasi | Input Plan 2.0 pada Git c4a145d dan 40 kartu diarsipkan; intake terkini bersih, klaim BK03 lama menjadi histori. Hash 85 source/aset/evidence yang dilindungi tidak berubah; tanpa cleanup/reconstruct pekerjaan yang tidak terkait |

## Alasan PLANNER-DETERMINED PRODUCT SUPPORT

Mekanisme pendukung berikut diperlukan untuk correctness produk minimum; bukan capability enterprise baru. Effort terintegrasi sudah dimasukkan dalam estimasi unit, bukan fee/provider usage yang dianggap approved.

| Keputusan | Masalah yang dicegah / alasan | Alternatif lebih sederhana dan mengapa gagal | Biaya implementasi / dampak operasional |
| --- | --- | --- | --- |
| Durable identity/result/epoch/atomic audit | Sale/payment/stock ganda saat retry/restore; race actor/config dan hasil tidak pasti yang tidak dapat dipulihkan | Tombol disabled/key di memory/menu tersembunyi tidak melindungi reload/dua proses/restore | BU-CMD-001 10h + test consumer domain; result/status server dan review incident, tanpa layanan eksternal |
| Revision cost evidence/replay bounded/rekonsiliasi | Profit keliru/history ditimpa/invers tidak aman/biaya dipulihkan dua kali | Harga terbaru/direct balance/history edit menghilangkan conserved facts | INV003 13h + READ/ops/final tests; correction tidak aman/melebihi cap ditolak atau incomplete; workflow alasan Pemilik |
| Onboarding freeze/once/zero checklist | Opening bersamaan dengan Sale/stok duplikat/movement zero palsu | Count spreadsheet manual tanpa guard tidak menyediakan fakta atomik | Termasuk INV002 10h/UI consumer; count freeze bisnis eksplisit, tanpa human database lock/import engine |
| Independent backup/restore target baru/epoch | Kehilangan host/volume/akun, aset corrupt dan blind replay setelah restore | Copy pada VPS yang sama/exit code sukses bukan recoverability independen | OPS002 10h/final VERIFY001 8h; destination/key/operator/cost aktual G4, target Rp0 tidak menutup requirement |

## Gate owner/eksternal

[Gap register](planning/GAPS-CHANGES.md) memegang status canonical: DESIGN PENDING; G1–G4 OPEN; DEVICES/TARGET PENDING; EXECUTOR NOT VERIFIED; RELEASE NOT AUTHORIZED. RPO 6 jam/RTO 4 jam adalah proposal, bukan SLA yang diterima. Penutupan gate mencatat tanggal/owner/evidence/target revision/requirement terdampak; credential/private key/contact privat tidak masuk dokumen publik.

Jika kebutuhan aktual memerlukan multi-warehouse/serial/import/drawer-funded refund/tanggung jawab bersama/tax accounting material, klasifikasikan OWNER_DECISION_REQUIRED dan replan scope terdampak dahulu. Kontrak minimum terkini tidak dibypass oleh template desain/fixture/default library atau kalender.

Keputusan baru memakai D-017 dan seterusnya hanya untuk pilihan kontrak nyata; status staging/no-commit sementara bukan keputusan produk. Exact context revision dan hasil read tooling berada pada 00/evidence, bukan visual approval.