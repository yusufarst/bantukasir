# P0 — Governance dan fondasi planning

Dokumen ini menetapkan kerangka planning BantuKasir **P0–P11**, berdasarkan instruksi owner terbaru. Kerangka governance, review dan kualitas dari diskusi MULTIPLECORP diadaptasi; domain, aset, token maupun project desain MULTIPLECORP tidak menjadi sumber produk BantuKasir. Plan 2.0 merupakan input historis yang dipetakan, bukan baseline task yang wajib dipertahankan.

Paket ini menghasilkan specification dan Build Units. **Coding aplikasi belum diotorisasi.** Otorisasi sesi rebaseline hanya mencakup planning, dokumentasi, pemeriksaan read-only yang relevan, dan satu commit planning lokal bila keadaan repository aman. Tidak ada izin menjalankan Build Unit pertama, push, merge, deploy, pembelian atau perubahan infrastructure produksi.

## Sumber kebenaran dan pemulihan konteks

Repository canonical menyimpan requirement, keputusan, acceptance dan kontinuitas. Hirarki interpretasi: instruksi owner eksplisit terbaru → specification canonical yang berlaku → decision log → current state dan evidence yang dapat diverifikasi. Instruksi owner baru yang mengubah kontrak dicatat dahulu pada dokumen canonical; jangan mempertahankan specification lama sebagai alasan mengabaikannya.

Chat lama, memory agent, scratchpad, laporan executor dan output design AI bukan authority pengganti. Evidence harus dapat diperiksa pada revision/target yang sesuai. Current state tidak boleh mengubah requirement atau memberikan approval yang tidak ada.

Sebelum bekerja, periksa branch, HEAD, commit terbaru dan seluruh staged/unstaged/untracked. Baca [AGENTS](../../AGENTS.md) → [00](../00-CURRENT-STATE.md) → [11](../11-BUILD-PLAN.md) → kartu Build Unit terpilih bila eksekusi telah diotorisasi → [01](../01-PRD.md) → [02](../02-ARCHITECTURE.md) → [10](../10-DECISIONS.md) → specification yang dirujuk; pekerjaan UI juga membaca [DESIGN](../../DESIGN.md). Jangan merapikan index dengan reset/restore/clean atau menimpa pekerjaan yang tidak terkait.

## Scope V1 minimum

Core hanya GOODS-only POS, satu business-wide stock pool dan tepat tiga role: SUPER_ADMIN/Pemilik, OPERATIONS_ADMIN/Admin Operasional, CASHIER/Kasir. Scope mandatory mengikuti [01](../01-PRD.md) dan pemetaan [CORE-MAP](../requirements/CORE-MAP.md): katalog/category/barcode/search, receiving, opening stock, manual issue, LOW/OUT, immutable StockMovement, StockBalance projection, MWA HPP, owner-only Laba Kotor, CashRegister/CashSession, Sale pembayaran penuh CASH atau BANK_TRANSFER, thermal receipt, A4 commercial nota, bounded refund/return/correction, audit, backup/recovery dan runtime BusinessProfile.

Satu completed Sale mempunyai satu metode pembayaran penuh. Tidak ada split, partial, credit atau gateway V1. Future provider readiness adalah boundary arsitektur, bukan callback/webhook/QRIS/asynchronous payment scope.

Tidak menambahkan multi-outlet, warehouse, transfer lokasi, purchasing/PO/supplier engine, CRM, loyalty, promotion engine, price book, expenses, HR/payroll/attendance, shift engine kompleks, reservation/service/booking, DP/debt, offline posting, kitchen/order display atau capability enterprise secara default. Daftar contoh dalam diskusi bukan requirement. BantuKasir adalah codename internal; identitas client produksi berasal dari BusinessProfile runtime.

## Tiga kelas keputusan

| Kelas | Otoritas dan batas | Catatan yang diperlukan |
| --- | --- | --- |
| AUTONOMOUS | Planner menyelesaikan keputusan teknis biasa: stack yang dievaluasi, modul/schema/index, transaction/lock/idempotency, API/validation, security controls, IA/komposisi UI, testing, monitoring, backup dan sequencing. Tetap menjaga invariant serta scope yang diminta | Keputusan berpengaruh dicatat di [10](../10-DECISIONS.md) dengan alasan, alternatif, tradeoff, affected requirement/acceptance dan kondisi reopening |
| PLANNER-DETERMINED PRODUCT SUPPORT | Capability pendukung hanya bila konkret diperlukan untuk correctness, security, integrity atau operasi nyata yang telah disetujui, dan scope sederhana yang ada tidak menyelesaikannya | Problem prevented; why existing simpler scope cannot solve it; alternatives considered; why selected; implementation cost; operational/user impact; traceability; kelas keputusan. Tidak menyamarkan perubahan kebijakan bisnis sebagai detail teknis |
| OWNER_DECISION_REQUIRED | STOP pekerjaan yang bergantung pada keputusan yang mengubah makna uang/stok, pricing/tax/legal, otoritas/visibility, workflow bisnis material, biaya recurring signifikan, penghapusan requested scope atau kebijakan produksi irreversible | Pertanyaan konkret, fakta yang diketahui/belum diketahui, pilihan dan dampak, affected scope/BU/acceptance, serta bukti keputusan owner sesudah tersedia |

Planner tidak meminta izin rutin untuk tiap fase atau pilihan teknis. Jika klasifikasi support ternyata mengubah business meaning, gunakan OWNER_DECISION_REQUIRED. Missing fundamental business fact, kontradiksi nyata dan operasi destruktif yang memerlukan exact approval juga mengharuskan STOP pada jalur terkait; jangan mengarang jawabannya. Pekerjaan planning independen boleh berlanjut hanya bila tidak mengasumsikan fakta yang hilang.

## Alur planning P0–P11

| Fase | Kontrak keluaran |
| --- | --- |
| P0 | Governance, authority, source truth, scope fence, review/freeze/change control dan handoff |
| P1 | Product scope minimum, Core requirements dan acceptance bisnis |
| P2 | Domain model, lifecycle, conserved facts dan invariant uang/stok |
| P3 | Workflow kritis beserta failure, uncertain result dan recovery |
| P4 | Perbandingan stack, pilihan yang direview, modul, schema dan deployment boundary |
| P5 | Threat model, authentication, authorization, privacy dan kontrol keamanan |
| P6 | Transaction/lock/idempotency/API/performance dan retry/stale semantics |
| P7 | IA/UX, pipeline visual bounded, responsive/a11y dan design approval boundary |
| P8 | Testing, delapan dimensi DoD, acceptance/evidence serta final points |
| P9 | Infrastructure, independent backup, restore, monitoring dan runbook |
| P10 | Release/migration/cutover/UAT serta fakta/approval eksternal yang diperlukan |
| P11 | Build Units baru dari specification cukup matang, requirement mapping, DAG, critical path, effort dan waiting |

AUTO planning P0 → P11 diotorisasi tanpa pertanyaan antar fase normal. Gate fase memeriksa kelengkapan specification, keputusan yang diperlukan, review dan konsistensi. Fase berikutnya tidak boleh memakai unresolved contradiction atau asumsi bisnis mendasar sebagai fakta. P11 tidak mempertahankan T01–T40 hanya karena sudah ada; serap requirement/acceptance valid dan bentuk boundary Build Unit yang optimal dari paket baru.

Siklus tiap fase: baseline/intake → analisis/specification → self-review → independent adversarial review → koreksi → validasi → freeze/checkpoint dokumentasi → continuity. Reviewer berbeda dari penulis specification. Review satu paket dapat mencakup beberapa fase bila coverage dan temuan tiap fase dicatat; jangan mengaku review terpisah yang tidak dilakukan. Security, money, stock, concurrency, migration/recovery mendapat review tambahan secara selektif berdasarkan risiko atau temuan unresolved.

Checkpoint fase adalah rekaman specification/review/continuity, bukan izin membuat commit berulang. Sesi ini mengotorisasi **satu commit planning lokal di akhir** setelah paket koheren dan verifikasi scope lulus.

## Freeze specification berbeda dari approval dan hasil runtime

| Dimensi | Makna dan bukti |
| --- | --- |
| Blueprint/specification reviewed atau frozen | Kontrak dan alternatif direview, temuan diselesaikan atau dibatasi sebagai dependency/hold eksplisit. Tidak membuktikan aplikasi sudah berjalan |
| Owner decision/approval | Bukti owner terhadap policy, visual revision, biaya atau release yang tepat. Review agent tidak menggantikannya |
| Implementation | NOT STARTED sampai BU dipilih dan diotorisasi. COMPLETE memerlukan seluruh applicable acceptance/DoD dan evidence |
| Technical evidence | Hasil command/test nyata pada revision/target yang sesuai. Evidence awal tidak otomatis FINAL PASS terintegrasi |
| External/physical facts | Client data, perangkat, destination/key backup, target VPS dan measured RPO/RTO hanya menjadi VERIFIED jika ada bukti aktual |
| Release authorization | Izin exact target/revision/operation tersendiri; blueprint freeze, visual approval maupun technical PASS tidak memberikan izin deployment |

Paket planning boleh membekukan kontrak generik yang benar sambil mencatat fakta client atau approval visual/device/release yang belum tersedia. Tulis hold, affected BU dan acceptance secara spesifik; jangan mengklaim seluruh fase/business policy approved atau seluruh produk COMPLETE. Jika fakta belum tersedia membuat keputusan dasar V1 tidak dapat ditentukan, catat OWNER_DECISION_REQUIRED, bukan membekukan asumsi.

Stack hasil evaluasi dan review diperlakukan frozen sampai evidence baru memerlukan reopening. Pilihan B pada [02](../02-ARCHITECTURE.md) adalah Laravel + Inertia + React + TypeScript + PostgreSQL + Nginx; alasan dan alternatif tetap disimpan di dokumen arsitektur/decision log. Tidak menambah queue/cache service tanpa kebutuhan terukur.

## Gap register dan change control

[GAPS-CHANGES](GAPS-CHANGES.md) mencatat ID, jenis gap/change, requirement/fase/BU/acceptance terdampak, fakta/evidence yang ada, hasil review, blocking scope, pemilik, next action dan status. Bedakan gap specification, engineering/test belum dikerjakan, owner decision, serta external fact yang belum tersedia. Status PENDING tidak boleh dibaca sebagai PASS.

Perubahan kontrak mengikuti: identifikasi → klasifikasi otoritas → impact analysis → keputusan → revisi canonical → review/validation → freeze baru → update Build Units/acceptance/DAG/effort/continuity. Affected evidence yang sudah final ditandai perlu rerun; jangan memodifikasi evidence lama diam-diam. Scope removal atau perubahan bisnis material memerlukan keputusan owner. Histori keputusan tetap dapat ditelusuri, tidak ditulis ulang sebagai approval retroaktif.

## Engineering, waktu tunggu dan biaya

[11](../11-BUILD-PLAN.md) dan P11 memisahkan implementation, verification, evidence/docs, risk reserve, dependency critical path dan kapasitas satu executor. Estimasi dibangun dari nol berdasarkan scope baru; angka lama bukan target atau batas. Critical path resource-independen bukan durasi kalender satu executor.

Owner/external waiting, perangkat nyata, visual approval, data/policy client, credential/target dan release authorization dipisahkan dari engineering. Ketidaktersediaan dependency menahan consumer terkait, bukan otomatis semua pekerjaan. Target biaya software recurring sekitar Rp0 tidak menghapus independent backup atau membenarkan keamanan/integritas yang lebih lemah; biaya nyata dan approval layanan dicatat.

## Handoff dan pengamanan penutupan

Eksekusi nantinya mengikuti [CLAUDE](../../CLAUDE.md) dan kartu di `docs/build-units/`, mulai dari [BU-FOUND-001](../build-units/BU-FOUND-001.md) hanya sesudah owner mengotorisasi. Label executor yang diminta owner: Claude Opus 5.5, effort xhigh; eskalasi selektif Fable 5.1, effort xhigh. Ini preferensi handoff, bukan klaim model tersedia atau izin menjalankan executor sekarang.

Setiap BU harus cukup eksplisit bagi executor lain yang mampu: purpose, dependencies, requirement, schema/actions/workflow/UI, role/object/DTO, validation, concurrency/idempotency, audit, performance, tests, acceptance, delapan dimensi DoD dan holds. Tidak otomatis melanjutkan semua BU.

Penutupan planning memverifikasi encoding/link, konsistensi scope/security/design boundary, Core → BU → acceptance, DAG, effort, independent review serta exact staged scope. App/device/restore/performance checks yang belum berjalan tetap NOT STARTED/PENDING. Update [11](../11-BUILD-PLAN.md), [10](../10-DECISIONS.md) hanya untuk keputusan nyata, lalu **[00](../00-CURRENT-STATE.md) LAST**. Stage path yang disengaja; jangan `git add -A`, blind staging `.local/`/`.agents/` atau menyertakan pekerjaan unrelated.

Tidak ada destructive production approval dalam planning atau kartu BU. [09](../09-DEPLOYMENT-OPS.md) tetap mewajibkan operasi exact yang disetujui owner, alternatif aman, populated rehearsal, independent backup/restore readiness dan recovery sebelum tindakan berisiko. Sesi rebaseline STOP sesudah satu commit planning lokal yang aman; tidak mengeksekusi BU pertama.
