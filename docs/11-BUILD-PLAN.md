# 11 — Rencana pembangunan dan progres resmi

**Versi rencana: 1.0 — 19 September 2026.** Satu-satunya pelacak progres build. Semua baris implementasi belum dimulai. Tugas sekarang berakhir setelah commit baseline dokumentasi; langkah berikutnya **review dokumentasi owner**, bukan menjalankan P01 otomatis.

## Status, klasifikasi, dan perhitungan

| Status | Makna | Kontribusi selesai |
| --- | --- | ---: |
| `[ ]` | BELUM DIMULAI | 0 |
| `[~]` | SEDANG DIKERJAKAN | 0 |
| `[T]` | MENUNGGU VERIFIKASI TEKNIS | 0 |
| `[V]` | MENUNGGU REVIEW VISUAL OWNER | 0 |
| `[x]` | SELESAI & TERVERIFIKASI | 1 |
| `[!]` | TERBLOKIR | 0 |

`[BE]` backend/database/integrasi tanpa dampak visual; `[FE]` frontend/prototipe; `[FS]` fitur dengan frontend dan backend. FE/FS wajib bukti review sesuai [05](05-DESIGN-SYSTEM.md). Hanya satu baris `[~]` dalam satu waktu. Bila blocker, simpan sebab, kebutuhan pembuka, dan tugas independen berikutnya; jangan menyebut pekerjaan belum mendapat giliran sebagai terblokir.

**55 tugas = 11 fase × 5 tugas.** Progres implementasi = `100 × jumlah [x] / 55`, ditampilkan dua desimal. Progres Core = `100 × [x] pada P01–P06 / 30`. Progres fase = `100 × [x] fase / 5`. FE prototype adalah deliverable terpisah dan hanya dapat selesai setelah approval HTML; integrasi tampilannya mempunyai baris sendiri dan approval final. `[V]` tidak mendapat kredit parsial.

Task count mengukur deliverable selesai, **bukan** perkiraan durasi, biaya, atau kompleksitas. Bila scope perlu dipecah/ditambah, naikkan versi rencana, catat alasan dan denominator lama/baru; jangan memecah pekerjaan selesai demi menaikkan persentase. Hilangnya fitur wajib tidak boleh dipakai mengecilkan denominator.

| Ukuran saat baseline | Nilai terverifikasi |
| --- | --- |
| Baseline dokumentasi (di luar build) | 1/1 paket teknis; review dokumentasi owner menunggu |
| Implementasi keseluruhan | **0/55 = 0,00%** |
| Core MVP | **0/30 = 0,00%** |
| Tugas build aktif | Tidak ada |
| Tugas build berikutnya setelah izin owner | P01.1 |
| Blocker teknis baseline | Tidak ada yang ditemukan |
| Gerbang sebelum implementasi | Review dokumentasi dan instruksi owner untuk mulai |

Setiap baris memiliki dependency dan acceptance. Kolom Bukti diisi saat dikerjakan: commit, hasil tes ringkas dan tautan REVIEW bila visual. Jangan menulis “PASS” tanpa bukti; `—` saat ini berarti belum tersedia.

## P01 — Fondasi, identitas, dan akses (Core)

Prasyarat: owner meninjau baseline dan mengizinkan implementasi. Tujuan: lingkungan, auth dan izin dapat dipercaya sebelum fitur stok.

| ID | Status | Kelas | Deliverable / acceptance | Dependensi | Bukti |
| --- | --- | --- | --- | --- | --- |
| P01.1 | [ ] | [BE] | Scaffold setelah izin, versi/lockfile, modul/config validation, PostgreSQL dev/test, baseline audit storage dan DB roles | Review owner | — |
| P01.2 | [ ] | [BE] | Better Auth, role/permission, bootstrap/invite/reset/2FA/session revocation; security negative tests | P01.1 | — |
| P01.3 | [ ] | [FE] | HTML auth/pemulihan/2FA dan app shell berizin; browser + Gerbang A | P01.1 | — |
| P01.4 | [ ] | [FS] | Implementasi pola auth/shell approved, real auth, keyboard/mobile dan Gerbang B | P01.2, P01.3 | — |
| P01.5 | [ ] | [BE] | CI/scripts lint/typecheck/unit/integration/E2E/build/secret check, environment guard dan auth smoke | P01.2, P01.4 | — |

## P02 — Master, lokasi, dan identitas barang (Core)

| ID | Status | Kelas | Deliverable / acceptance | Dependensi | Bukti |
| --- | --- | --- | --- | --- | --- |
| P02.1 | [ ] | [BE] | Product/SKU/satuan/precision/tracking, category/brand, lokasi, version guard, permission CRUD | P01.5 | — |
| P02.2 | [ ] | [BE] | Registry barcode, register serial tanpa stok, uniqueness/normalisasi/resolver, label payload | P02.1 | — |
| P02.3 | [ ] | [FE] | HTML master/lokasi/identitas dan label cetak; state CRUD/permission + Gerbang A | P02.1 | — |
| P02.4 | [ ] | [FS] | UI master/lokasi/identitas dan label ke backend; cetak uji + Gerbang B | P02.2, P02.3 | — |
| P02.5 | [ ] | [BE] | Demo master aman/deterministik, seed environment guard; belum seed saldo tanpa command | P02.2 | — |

## P03 — Ledger atomik dan perhatian stok (Core)

State/episode/outbox dimajukan ke transaksi inti agar tidak ditempel setelah integritas posting selesai. Delivery ke perangkat/UI berada di P05.

| ID | Status | Kelas | Deliverable / acceptance | Dependensi | Bukti |
| --- | --- | --- | --- | --- | --- |
| P03.1 | [ ] | [BE] | Skema ledger/balance/receipt, constraint dan guard lock, immutable DB permissions; migrations reviewed | P02.5 | — |
| P03.2 | [ ] | [BE] | Command receipt/issue/transfer, serial transitions, idempotensi dan status recovery; atomic audit | P03.1 | — |
| P03.3 | [ ] | [BE] | Policy minimum, health episode, inbox/outbox terintegrasi transaksi dan perubahan setting; transisi NOT01–05 | P03.2 | — |
| P03.4 | [ ] | [BE] | Opening, owner adjustment/reversal dan safe rejection; tidak ada edit saldo; demo movement sah | P03.3 | — |
| P03.5 | [ ] | [BE] | Suite multi-connection race/rollback/property/idempotency/serial + oracle rekonsiliasi seluruh ledger | P03.4 | — |

## P04 — Operasi gudang dan scanner (Core)

| ID | Status | Kelas | Deliverable / acceptance | Dependensi | Bukti |
| --- | --- | --- | --- | --- | --- |
| P04.1 | [ ] | [FE] | HTML masuk/keluar/transfer, scanning/review/error/uncertain/mobile, demo saja + Gerbang A | P03.5 | — |
| P04.2 | [ ] | [FE] | Input HID/reducer/queue/fokus/duplicate/session recovery sesuai pola approved; browser tests + Gerbang B | P04.1 | — |
| P04.3 | [ ] | [FS] | Hubungkan scanner ke resolver/command/status; end-to-end USB/Bluetooth dan error jaringan + Gerbang B | P04.2, P03.5 | — |
| P04.4 | [ ] | [FE] | HTML saldo/lokasi/detail serial/riwayat/koreksi owner; empty/error/filter + Gerbang A | P03.5 | — |
| P04.5 | [ ] | [FS] | Saldo/riwayat/serial/koreksi dan export aman real data, re-auth/izin + Gerbang B | P04.4 | — |

## P05 — Notifikasi dan dasbor pemilik (Core)

| ID | Status | Kelas | Deliverable / acceptance | Dependensi | Bukti |
| --- | --- | --- | --- | --- | --- |
| P05.1 | [ ] | [BE] | Worker outbox lease/retry/dedup, subscription Web Push dan delivery access checks | P04.5, P03.3 | — |
| P05.2 | [ ] | [BE] | Query snapshot dashboard/inbox/read-state, KPI nyata dan batas WIB; tidak ada metrik palsu | P05.1 | — |
| P05.3 | [ ] | [FE] | HTML notifikasi/push onboarding/dasbor/setting minimum; semua state + Gerbang A | P05.2 | — |
| P05.4 | [ ] | [FS] | UI approved ke query/policy nyata, polling/recovery dan Gerbang B | P05.3 | — |
| P05.5 | [ ] | [FS] | E2E inbox/izin push/escalation/recovery dan provider uji, Gerbang B; uji perangkat owner aktual pada P06.5 | P05.4 | — |

## P06 — Operasi, pemulihan, dan pilot Core

**Tidak ada data stok produksi sebelum fase ini diterima.** Deploy staging diperlukan untuk uji perangkat; go-live harus menunggu bukti restore dan penerimaan Core.

| ID | Status | Kelas | Deliverable / acceptance | Dependensi | Bukti |
| --- | --- | --- | --- | --- | --- |
| P06.1 | [ ] | [BE] | Compose/Caddy image pinning, network/secrets, backup off-host terenkripsi, health endpoints dan worker supervision | P05.5 | — |
| P06.2 | [ ] | [BE] | Restore nyata terisolasi + RPO/RTO terukur, rekonsiliasi/maintenance dan penanganan outbox restore | P06.1 | — |
| P06.3 | [ ] | [FE] | HTML status sistem/backup/insiden dengan data demo berlabel + Gerbang A | P06.1 | — |
| P06.4 | [ ] | [FS] | Health/backup UI real data, unknown bukan sukses, akses owner + Gerbang B | P06.2, P06.3 | — |
| P06.5 | [ ] | [FS] | Full Core gate 08, hardware/printer/mobile, push tab tertutup pada perangkat owner di HTTPS staging, owner final review, asumsi/SOP/cutover dan penerimaan pilot | P06.4, seluruh P01–P05 | — |

Core selesai hanya jika 30/30 `[x]`, perangkat/channel diterima, dan tidak ada blocker keselamatan. Hasil test bagus tidak melewati gerbang visual atau restore.

## P07 — Reservasi, transit, opname, dan persetujuan (lanjutan)

| ID | Status | Kelas | Deliverable / acceptance | Dependensi | Bukti |
| --- | --- | --- | --- | --- | --- |
| P07.1 | [ ] | [BE] | Reservasi/events/expiry/partial fulfillment dan health tersedia; race tests | P06.5 | — |
| P07.2 | [ ] | [BE] | Transfer transit dispatch/partial receive/return/loss, ledger dan identity invariants | P07.1 | — |
| P07.3 | [ ] | [BE] | Approval proposal/version/one-use, opname freeze/count/adjustment, negative tests | P07.2 | — |
| P07.4 | [ ] | [FE] | HTML alur lanjutan gudang/persetujuan dan state konflik + Gerbang A | P07.3 | — |
| P07.5 | [ ] | [FS] | Integrasi alur approved, E2E lintas modul dan Gerbang B | P07.4 | — |

## P08 — QC, garansi dan servis (lanjutan)

| ID | Status | Kelas | Deliverable / acceptance | Dependensi | Bukti |
| --- | --- | --- | --- | --- | --- |
| P08.1 | [ ] | [BE] | QC record immutable, karantina dan eligibility terintegrasi ledger/health | P07.5 | — |
| P08.2 | [ ] | [BE] | Garansi/service case, custody barang pelanggan terpisah dari stok perusahaan | P08.1 | — |
| P08.3 | [ ] | [FE] | HTML QC/garansi/servis/detail item, exception states + Gerbang A | P08.2 | — |
| P08.4 | [ ] | [FS] | Implementasi approved dan real service/QC flow + Gerbang B | P08.3 | — |
| P08.5 | [ ] | [BE] | Cross-module tests custody/availability/audit/privacy dan backup metadata baru | P08.4 | — |

## P09 — Website perusahaan dan katalog (lanjutan)

| ID | Status | Kelas | Deliverable / acceptance | Dependensi | Bukti |
| --- | --- | --- | --- | --- | --- |
| P09.1 | [ ] | [BE] | Publishing/master safe projection, image/spec upload aman dan cache boundary | P08.5 | — |
| P09.2 | [ ] | [BE] | Search/filter/detail/comparison queries, public allowlist dan pagination | P09.1 | — |
| P09.3 | [ ] | [FE] | HTML corporate home/katalog/detail/comparison/responsive + Gerbang A | P09.2 | — |
| P09.4 | [ ] | [FS] | Implementasi approved real public data, SEO dan WhatsApp link aman + Gerbang B | P09.3 | — |
| P09.5 | [ ] | [BE] | Leak/cache/public authorization dan performance audit; sentinel secret-field tests | P09.4 | — |

## P10 — RFQ, leads, penawaran dan sales (lanjutan)

| ID | Status | Kelas | Deliverable / acceptance | Dependensi | Bukti |
| --- | --- | --- | --- | --- | --- |
| P10.1 | [ ] | [BE] | RFQ intake/anti-spam/PII policy, link click attribution tanpa klaim conversion palsu | P09.5 | — |
| P10.2 | [ ] | [BE] | Lead/quotation revision/deal dan permission sales, integrasi reservation service | P10.1, P07.1 | — |
| P10.3 | [ ] | [FE] | HTML form RFQ/follow-up/penawaran/deal dan privacy states + Gerbang A | P10.2 | — |
| P10.4 | [ ] | [FS] | Frontend sales approved/public RFQ, audit real data + Gerbang B | P10.3 | — |
| P10.5 | [ ] | [FS] | End-to-end deal → reservasi → scan keluar/partial/cancel, cross-role owner review | P10.4, P07.5 | — |

## P11 — Laporan lanjutan dan pematangan produksi

Hardening dasar sudah wajib P06; fase ini untuk perluasan platform, bukan alasan menunda keamanan Core.

| ID | Status | Kelas | Deliverable / acceptance | Dependensi | Bukti |
| --- | --- | --- | --- | --- | --- |
| P11.1 | [ ] | [BE] | Laporan/export lintas modul, retention/arsip dan permission sesuai sumber data nyata | P10.5 | — |
| P11.2 | [ ] | [FE] | HTML laporan/filter/drill-down/ekspor dan system views lanjutan + Gerbang A | P11.1 | — |
| P11.3 | [ ] | [FS] | Laporan approved ke query nyata, audit akses dan Gerbang B | P11.2 | — |
| P11.4 | [ ] | [BE] | Load/profile data aktual, patch/hardening, restore diperluas, capacity/monitoring review | P11.3 | — |
| P11.5 | [ ] | [FS] | Full regression platform, final owner acceptance, runbook/operator handover dan rilis terverifikasi | P11.4 | — |

## Aturan pembaruan pelacak

Update hanya baris yang benar-benar berubah, lalu hitung ulang ringkasan dari status baris. Catat tanggal, commit/bukti dan blocker spesifik saat ada. Jika FE sedang menunggu pemilik, pertahankan `[V]`; pekerjaan BE independen boleh dipilih sebagai satu tugas aktif berikutnya. Jangan menghapus gate hanya karena proses review lama.

Setelah baseline, prioritas fase lanjutan boleh ditata ulang atas kebutuhan bisnis melalui keputusan baru tanpa mengubah hard invariant/Core. Urutan awal memilih kelengkapan operasi sebelum ekspansi akuisisi pelanggan; P08 bukan dependensi teknis mutlak katalog, melainkan prioritas bisnis yang dapat direvisi.
