# P11 — Paket fase dan Build Units baru

Baseline P0–P11, 2 Oktober 2026. **27 Build Units implementasi, 73 Core requirement, 39 acceptance, 0 FINAL PASS; aplikasi dan seluruh unit NOT STARTED.** Status P11 **REOPENED_DRAFT / PENDING_P7_APPROVAL**. Blueprint terdahulu tidak memenuhi gate desain aktual P7 dan bukan owner approval atau production readiness.

## Register P0–P10

| Fase | Specification canonical | Completion blueprint / batas |
| --- | --- | --- |
| P0 Governance/fondasi | [P0](P0-GOVERNANCE.md) | Kontrak governance dikoreksi: P7 actual design + exact owner approval + DESIGN normalized sebelum freeze P8–P11 atau coding. |
| P1 Produk/scope/acceptance | [P1](P1-PRODUCT.md) | FROZEN_INPUT; specification bisnis/stack/security dipertahankan byte-identical. Review terdahulu pada [evidence](../evidence/rebaseline-p0-p11/REVIEW.md) merupakan riwayat input, bukan bukti runtime. |
| P2 Domain/invariant bisnis | [P2](P2-DOMAIN.md) | FROZEN_INPUT; specification bisnis/stack/security dipertahankan byte-identical. Review terdahulu pada [evidence](../evidence/rebaseline-p0-p11/REVIEW.md) merupakan riwayat input, bukan bukti runtime. |
| P3 Workflow kritis | [P3](P3-CRITICAL-WORKFLOWS.md) | FROZEN_INPUT bisnis; kalimat format presentasi tanggal/waktu disupersede oleh arahan Owner D-018. Invariant bisnis, UTC/Jakarta dan query tetap. Review terdahulu pada [evidence](../evidence/rebaseline-p0-p11/REVIEW.md) merupakan riwayat input, bukan bukti runtime. |
| P4 Stack/database/arsitektur aplikasi | [P4](P4-ARCHITECTURE.md) | FROZEN_INPUT; specification bisnis/stack/security dipertahankan byte-identical. Review terdahulu pada [evidence](../evidence/rebaseline-p0-p11/REVIEW.md) merupakan riwayat input, bukan bukti runtime. |
| P5 Security/auth/authorization | [P5](P5-SECURITY.md) | FROZEN_INPUT; specification bisnis/stack/security dipertahankan byte-identical. Review terdahulu pada [evidence](../evidence/rebaseline-p0-p11/REVIEW.md) merupakan riwayat input, bukan bukti runtime. |
| P6 Concurrency/API/performance | [P6](P6-CONCURRENCY-API.md) | FROZEN_INPUT; specification bisnis/stack/security dipertahankan byte-identical. Review terdahulu pada [evidence](../evidence/rebaseline-p0-p11/REVIEW.md) merupakan riwayat input, bukan bukti runtime. |
| P7 UX/IA/design contract | [P7](P7-UX-DESIGN.md) | WAITING OWNER VISUAL REVIEW; kandidat aktual `sha256-bef01d649ea91b2b` telah melalui checks bounded pada [evidence](../evidence/p7-owner-r1/REVIEW.md). Approval exact revision masih null, rights/validation holds terbuka. NOT COMPLETE; bukan native/runtime PASS. |
| P8 Testing/DoD | [P8](P8-QUALITY.md) | REOPENED_DRAFT / PENDING_P7_APPROVAL; draf valid dipertahankan, refinalisasi/review/freeze hanya setelah P7 COMPLETE. Bukti runtime/release belum tersedia. |
| P9 Infrastructure/backup/monitoring | [P9](P9-OPERATIONS.md) | REOPENED_DRAFT / PENDING_P7_APPROVAL; draf valid dipertahankan, refinalisasi/review/freeze hanya setelah P7 COMPLETE. Bukti runtime/release belum tersedia. |
| P10 Release/migration/cutover/UAT | [P10](P10-RELEASE-UAT.md) | REOPENED_DRAFT / PENDING_P7_APPROVAL; draf valid dipertahankan, refinalisasi/review/freeze hanya setelah P7 COMPLETE. Bukti runtime/release belum tersedia. |

Urutan wajib: **P0–P6 frozen input → P7 desain aktual DesainPakeAI + approval visual owner exact revision + DESIGN/token dinormalisasi → P7 COMPLETE → P8–P11 refinalisasi/review/FROZEN → Execute satu BU eksplisit**. Draft BU/acceptance tidak handoff-ready sebelum gate ini. Author → self-review → independent/adversarial review → fix → validator dokumen mendahului freeze fase terkait. Review paket terdahulu merupakan riwayat, bukan bukti approval desain. Fakta client/perangkat/backup/deploy aktual tetap dicatat pada [GAPS](GAPS-CHANGES.md), tanpa jawaban fiktif.

## Prinsip boundary baru

Seluruh tanggung jawab desain/approval pertama diserap P7 planning; tidak ada BU desain implementasi. UI/DOC memiliki planning provider P7/approved DESIGN dengan approvedRevision null, yang dipisahkan dari deps coding. BU dipilih dari hasil bounded yang dapat dibuktikan: provider backend/domain/ops terpisah secara teknis dari consumer visual setelah seluruh paket dibekukan, lalu UI workflow nyata dan verifikasi final terintegrasi. Operational read stok terpisah dari finance Pemilik agar katalog/receiving tidak menunggu finance/refund; read Sale sendiri/report disediakan provider domain. Abstraksi command action hanya melayani consumer nyata, bukan platform enterprise.

T01–T40 diarsipkan sebagai input historis; requirement dan 37 acceptance yang valid diserap, tanpa mempertahankan boundary/ID/estimasi lama. SEC01/PAYEXT01 baru menutup jalur ancaman/boundary metode-provider tanpa scope bisnis tambahan.

## Traceability dan sequencing

[CORE-MAP](../requirements/CORE-MAP.md) memetakan setiap 73 requirement → BU implementasi + suite/final point. [08](../08-TESTING-ACCEPTANCE.md) menjaga subklausul dan consumer/bukti final. [11](../11-BUILD-PLAN.md) memuat DAG/estimasi/status exact; [registry JSON](../build-units/registry.json)/[core JSON](../requirements/core.json) menjadi input validator. Seluruh dependency BU adalah artifact coding teknis kompatibel. Metadata planningProviders P7 pada UI/DOC dan CORE-030/034/070 mensyaratkan exact approved revision, desain aktual serta DESIGN/token dinormalisasi sebelum coding; approval belum tersedia. 73 requirement, 39 suite dan seluruh pemilik FINAL tetap sama.

Unit pertama exact: [BU-FOUND-001 — Fondasi Laravel, React dan harness PostgreSQL terisolasi](../build-units/BU-FOUND-001.md), 8h (I4/V3/E1), tanpa dependency BU tetapi tetap menunggu gate planning P7 COMPLETE dan P8–P11 FROZEN. **Belum diotorisasi/dijalankan.** Primary handoff Claude Opus 5.5/xhigh dan Fable 5.1/xhigh selektif; label permintaan, tanpa klaim tersedia/dijalankan. Sesi koreksi berhenti sesudah satu commit docs/design-planning lokal yang aman; tidak otomatis implementasi.

## Estimasi draf dan dependency waktu

P7 actual design **32h**: authoring desain 20, review 8, normalisasi DESIGN/token 4. P8–P11 refinalisasi **8h**: quality 3, operasi 1, release 1, mapping/DAG 3. Planning total **40h**; 27 BU implementasi **253h (I110/V116/E27)**; gabungan **293h**. Reserve planning 8 + implementasi 56 = **64h** terpisah; maximum allowance **357h** jika seluruh reserve dipakai.

Critical dependency path teknis implementasi tetap **128h**; ditambah prerequisite planning 40h menjadi lower bound **168h**, dengan owner waiting belum diketahui dan dikeluarkan. Satu executor: 293h = **36,625 hari × 8h / 7,325 minggu × 40h**. Seluruh angka adalah judgment estimasi, bukan jam aktual atau janji kalender; draf wajib diperiksa ulang setelah P7 disetujui.
