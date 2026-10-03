# P1 — Produk, scope dan acceptance

Status: blueprint specification; lihat register review dan freeze pada [P11](P11-BUILD-UNITS.md). Otoritas bisnis berada di [PRD01](../01-PRD.md), [CORE-MAP](../requirements/CORE-MAP.md) dan [acceptance08](../08-TESTING-ACCEPTANCE.md).

## Hasil dan batas

V1 mendukung alur Scan/Cari → draft → satu pembayaran penuh → Sale atomik → receipt; receiving, opening stock dan manual issue; stok akurat; biaya/profit Pemilik yang jujur; akuntabilitas kas ringan; serta recovery. Produk hanya GOODS, dengan satu stock pool dan tepat tiga role. Fixture ATK hanya digunakan untuk test atau lingkungan non-production.

Seluruh scope mandatory terbaru dipetakan ke 73 baris Core. Tidak menambahkan multi-outlet, warehouse, transfer lokasi, supplier/PO engine, CRM, loyalty, promotion, price book, expenses, HR, reservation, services, DP/debt, pembayaran partial/split, offline posting atau capability enterprise. Capability pendukung hanya ditambahkan bila ada kebutuhan konkret yang tidak dapat diselesaikan alternatif paling sederhana; catat kelas keputusan sesuai P0.

## Pembayaran sebagai keputusan owner

Satu completed Sale mempunyai tepat satu pembayaran penuh CASH atau BANK_TRANSFER. Pembayaran campuran, partial, installment, debt dan beberapa instrumen pembayaran dilarang. Transfer mencatat konfirmasi manusia bahwa dana penuh diterima sesuai workflow final; konfirmasi ini bukan verifikasi provider. Kesiapan gateway masa depan hanya berupa boundary metode/provider dan extension additive, tanpa implementasi gateway, callback atau state asynchronous.

## Penerimaan dan fakta belum tersedia

Definisi correctness, security, integrity dan failure tidak menunggu approval visual. G1 untuk nota/tax/buyer; G2 untuk master/unit/opening/cost/serial; G3 untuk drawer fisik dan funding; G4 untuk backup aktual/RPO/RTO; serta DESIGN, perangkat dan release tetap menjadi [holds](GAPS-CHANGES.md). Blueprint tidak menganggap fixture sebagai fakta bisnis nyata. Jika fakta client bertentangan dengan scope minimum, buka kembali P1 dan domain terdampak sebelum eksekusi nyata; jangan menambah fitur diam-diam.

PASS planning memerlukan sumber requirement yang jelas, seluruh Core dipetakan ke BU/acceptance, serta batas scope dan non-scope yang tidak ambigu. Implementasi dan acceptance produk seluruhnya NOT STARTED.