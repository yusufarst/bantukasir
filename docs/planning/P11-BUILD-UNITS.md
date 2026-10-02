# P11 — Paket fase dan Build Units baru

Baseline P0–P11, 2 Oktober 2026. **28 Build Units baru, 73 Core requirement, 39 acceptance, 0 FINAL PASS; aplikasi dan seluruh unit NOT STARTED.** Blueprint tidak berarti owner visual approval atau production readiness.

## Register P0–P10

| Fase | Specification canonical | Completion blueprint / batas |
| --- | --- | --- |
| P0 Governance/fondasi | [P0](P0-GOVERNANCE.md) | BLUEPRINT_FROZEN_WITH_HOLDS; review/fix aktual pada [evidence](../evidence/rebaseline-p0-p11/REVIEW.md). Fakta owner/eksternal hanya menahan bagian terkait. |
| P1 Produk/scope/acceptance | [P1](P1-PRODUCT.md) | BLUEPRINT_FROZEN_WITH_HOLDS; review/fix aktual pada [evidence](../evidence/rebaseline-p0-p11/REVIEW.md). Fakta owner/eksternal hanya menahan bagian terkait. |
| P2 Domain/invariant bisnis | [P2](P2-DOMAIN.md) | BLUEPRINT_FROZEN_WITH_HOLDS; review/fix aktual pada [evidence](../evidence/rebaseline-p0-p11/REVIEW.md). Fakta owner/eksternal hanya menahan bagian terkait. |
| P3 Workflow kritis | [P3](P3-CRITICAL-WORKFLOWS.md) | BLUEPRINT_FROZEN_WITH_HOLDS; review/fix aktual pada [evidence](../evidence/rebaseline-p0-p11/REVIEW.md). Fakta owner/eksternal hanya menahan bagian terkait. |
| P4 Stack/database/arsitektur aplikasi | [P4](P4-ARCHITECTURE.md) | BLUEPRINT_FROZEN_WITH_HOLDS; review/fix aktual pada [evidence](../evidence/rebaseline-p0-p11/REVIEW.md). Fakta owner/eksternal hanya menahan bagian terkait. |
| P5 Security/auth/authorization | [P5](P5-SECURITY.md) | BLUEPRINT_FROZEN_WITH_HOLDS; review/fix aktual pada [evidence](../evidence/rebaseline-p0-p11/REVIEW.md). Fakta owner/eksternal hanya menahan bagian terkait. |
| P6 Concurrency/API/performance | [P6](P6-CONCURRENCY-API.md) | BLUEPRINT_FROZEN_WITH_HOLDS; review/fix aktual pada [evidence](../evidence/rebaseline-p0-p11/REVIEW.md). Fakta owner/eksternal hanya menahan bagian terkait. |
| P7 UX/IA/design contract | [P7](P7-UX-DESIGN.md) | BLUEPRINT_FROZEN_WITH_HOLDS; review/fix aktual pada [evidence](../evidence/rebaseline-p0-p11/REVIEW.md). Approval exact visual revision masih DESIGN PENDING; consumer UI tertahan. |
| P8 Testing/DoD | [P8](P8-QUALITY.md) | BLUEPRINT_FROZEN_WITH_HOLDS; review/fix aktual pada [evidence](../evidence/rebaseline-p0-p11/REVIEW.md). Fakta owner/eksternal hanya menahan bagian terkait. |
| P9 Infrastructure/backup/monitoring | [P9](P9-OPERATIONS.md) | BLUEPRINT_FROZEN_WITH_HOLDS; review/fix aktual pada [evidence](../evidence/rebaseline-p0-p11/REVIEW.md). Fakta owner/eksternal hanya menahan bagian terkait. |
| P10 Release/migration/cutover/UAT | [P10](P10-RELEASE-UAT.md) | BLUEPRINT_FROZEN_WITH_HOLDS; review/fix aktual pada [evidence](../evidence/rebaseline-p0-p11/REVIEW.md). Otorisasi release/pilot belum tersedia. |

Author → self-review → independent/adversarial review → fix → validator dokumen → blueprint freeze. Review paket lintas fase dipetakan per area; jangan mengaku 12 sesi review terpisah. P0–P10 cukup matang untuk menyusun Build Units setelah kontrak teknis jelas. Client/desain/perangkat/backup/deploy aktual yang pending dicatat pada [GAPS](GAPS-CHANGES.md), bukan dijawab secara fiktif.

## Prinsip boundary baru

BU dipilih dari hasil bounded yang dapat dibuktikan: provider backend/domain/ops terpisah dari consumer visual, lalu UI workflow nyata dan verifikasi final terintegrasi. Operational read stok terpisah dari finance Pemilik agar katalog/receiving tidak menunggu finance/refund; read Sale sendiri/report disediakan provider domain. Abstraksi command action hanya melayani consumer nyata, bukan platform enterprise.

T01–T40 diarsipkan sebagai input historis; requirement dan 37 acceptance yang valid diserap, tanpa mempertahankan boundary/ID/estimasi lama. SEC01/PAYEXT01 baru menutup jalur ancaman/boundary metode-provider tanpa scope bisnis tambahan.

## Traceability dan sequencing

[CORE-MAP](../requirements/CORE-MAP.md) memetakan setiap 73 requirement → BU implementasi + suite/final point. [08](../08-TESTING-ACCEPTANCE.md) menjaga subklausul dan consumer/bukti final. [11](../11-BUILD-PLAN.md) memuat DAG/estimasi/status exact; [registry JSON](../build-units/registry.json)/[core JSON](../requirements/core.json) menjadi input validator. Seluruh dependency adalah artifact teknis kompatibel; UI mensyaratkan approved BU-DESIGN-001 COMPLETE secara tegas.

Unit pertama exact: [BU-FOUND-001 — Fondasi Laravel, React dan harness PostgreSQL terisolasi](../build-units/BU-FOUND-001.md), 8h (I4/V3/E1), tanpa dependency. **Belum diotorisasi/dijalankan.** Primary handoff Claude Opus 5.5/xhigh dan Fable 5.1/xhigh selektif; label permintaan, tanpa klaim tersedia/dijalankan. Sesi ini berhenti sesudah satu commit planning lokal, bukan otomatis implementasi.