# Registry prototype historis

**Baseline P0–P11.** Visual/UI workflow lama superseded. Prototype tetap disimpan karena state, ATK fixtures, permission examples, interaksi dan failure examples membantu desain/pengujian baru. Prototype tidak menjadi sumber visual/token produksi atau bukti server RBAC, transaksi PostgreSQL, perangkat dan recovery.

| Artifact | Fakta lokal/historis | Pemakaian yang dibolehkan |
| --- | --- | --- |
| [UI01](ui01/PATTERNS.md) | R6 approved historis di commit `1f62e98` | Referensi state/shell/accessibility; approval tidak dibawa ke visual baru |
| [UI02](ui02/README.md) | BK02 approved historis pada commit `f6c9c58` | Referensi workflow receiving/duplicate/barcode/role-safe history; bukan production implementation |
| UI03 | Riwayat BK03 pernah memiliki partial source/evidence; intake terbaru bersih dan direktori UI03 tidak ada. Tidak merekonstruksi atau menebak siapa yang mengubahnya | Jika recovered melalui sumber historis yang sah, hanya referensi behavior; bukan COMPLETE atau approval |
| UI04 | Tidak ada bundle lokal | Tidak perlu dibuat; laporan Pemilik produksi mengikuti BU-READ-002/BU-UI-004/BU-DOC-001 |

Evidence historis dalam `.impeccable/review/` dipertahankan agar pekerjaan lokal tidak hilang. Tool tersebut sudah keluar dari workflow aktif. Metadata active design/surfaces lama sudah tidak ada pada intake sesi ini; provenance aktif di [DESIGN](../DESIGN.md).

Pipeline sekarang DesainPakeAI owner-approved source → semantic contract repository → shadcn/ui → React/Inertia native. Jangan mengimpor CSS/logo/old routing prototype sebagai fondasi produksi atau membangun ulang empat aplikasi prototype. [11](../docs/11-BUILD-PLAN.md) dan [00](../docs/00-CURRENT-STATE.md) adalah current truth; claims Plan1.1/GateA/B dalam dokumen per-prototype/evidence lama hanya histori, bukan instruksi aktif.
