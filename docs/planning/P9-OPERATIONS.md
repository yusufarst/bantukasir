# P9 — Infrastructure, backup, monitoring dan arsitektur deployment

Canonical [09](../09-DEPLOYMENT-OPS.md), runtime terpilih [P4](P4-ARCHITECTURE.md) dan kontrol ancaman [P5](P5-SECURITY.md). Aplikasi self-hosted privat berada pada VPS tujuan; fakta host/domain/access/capacity aktual yang belum tersedia tidak membenarkan klaim siap deploy.

PHP-FPM + Nginx/PostgreSQL privat melalui Compose, Vite pada build time, rehearsal TLS/ACME renewal, tanpa SSR Node/Redis/queue secara default. Identity runtime/migration/test/backup terpisah; BU domain memberi privilege tabel immutable; webroot public-only/debug off/secret privat. Command/reconciliation/backup terjadwal memakai OS scheduler sederhana, tanpa platform worker generik.

Original money/stock/history dilindungi. Gunakan forward/additive migration dengan populated rehearsal terisolasi, checksum/rekonsiliasi dan independent backup/restore readiness sebelum risiko. Backup database beserta media/template yang dipertahankan dienkripsi ke host/account/storage independen. Recovery key/access independen dan benar-benar tersedia bagi owner/operator. Target Rp0 tidak membenarkan backup hanya di VPS yang sama; biaya nyata dicatat pada G4.

Restore selalu ke target/storage baru dengan original dipertahankan. Rekonsiliasi source/projection/snapshot/aset/session/command/audit, revoke + epoch baru dan review gap setelah cutoff wajib. Result yang tidak ada pada restore bukan bukti belum pernah committed. RPO 6 jam/RTO 4 jam masih proposal; acceptance final memerlukan pengukuran aktual dan penerimaan owner G4.

Monitoring menunjukkan status aman bagi Pemilik: backup/restore terakhir, usia/error/disk/mismatch aktual; unverified bukan sukses. Pemeriksaan availability independen dari VPS diperlukan untuk mendeteksi host down; jalur eksternal yang belum tersedia dicatat sebagai gap. [09](../09-DEPLOYMENT-OPS.md) memegang retensi/incident/reopen/destruction guard dan verifikasi target operasional.

PASS blueprint memerlukan runbook/target guard/requirement/pemilik test yang jelas; target aktual, backup key, restore/drill, perangkat dan deployment tidak diklaim selesai.