<?php

// Interface dictionary, file inventory (ADR-005; COPY_DECK section 1.1):
// stock, history, receiving, manual issue, adjustment, reversal, onboarding,
// incidents. Its keys are copied verbatim from COPY_DECK by the first Task
// that uses them; TASK-003 adds none. TASK-022 adds those of the stock and
// receiving pages (COPY_DECK sections 4.1, 9.1 to 9.7 and 15).

return [
    'state' => [
        'out' => 'Habis',
        'low' => 'Menipis',
        'normal' => 'Normal',
    ],
    'type' => [
        'opening' => 'Stok awal',
        'receipt' => 'Barang masuk',
        'sale_issue' => 'Keluar karena penjualan',
        'manual_issue' => 'Barang keluar',
        'adjustment' => 'Penyesuaian stok',
        'reversal' => 'Pembatalan',
        'return_receipt' => 'Retur masuk stok',
    ],
    'line' => [
        'product' => 'Produk',
        'quantity' => 'Jumlah',
        'search' => 'Cari atau scan produk',
        'search_hint' => 'Scan barcode, atau ketik nama atau SKU',
    ],
    'stock' => [
        'title' => 'Stok',
        'search' => 'Cari produk',
        'filter_state' => 'Keadaan stok',
        'col_product' => 'Produk',
        'col_stock' => 'Stok',
        'col_state' => 'Keadaan',
        'empty' => 'Belum ada produk.',
    ],
    'history' => [
        'title' => 'Riwayat stok',
        'filter_product' => 'Produk',
        'filter_type' => 'Jenis mutasi',
        'filter_date' => 'Tanggal',
        'col_time' => 'Waktu',
        'col_product' => 'Produk',
        'col_type' => 'Jenis',
        'col_quantity' => 'Jumlah',
        'col_actor' => 'Oleh',
        'col_source' => 'Sumber',
        'empty' => 'Belum ada mutasi stok.',
    ],
    'movement' => [
        'title' => 'Detail mutasi stok',
        'subtitle' => ':jenis · :produk',
        'type' => 'Jenis',
        'product' => 'Produk',
        'quantity' => 'Jumlah',
        'time' => 'Waktu',
        'actor' => 'Oleh',
        'reference' => 'Referensi',
        'source' => 'Sumber',
        'source_receipt' => 'Barang masuk :waktu',
    ],
    'receipts' => [
        'title' => 'Barang masuk',
        'filter_date' => 'Tanggal',
        'search' => 'Cari referensi atau produk',
        'col_time' => 'Waktu',
        'col_reference' => 'Referensi',
        'col_lines' => 'Baris',
        'col_actor' => 'Oleh',
        'empty' => 'Belum ada barang masuk.',
    ],
    'receipt_new' => [
        'title' => 'Catat barang masuk',
        'draft_note' => 'Draf ini hanya ada di halaman ini. Jika halaman dimuat ulang atau ditutup, atau sesi masuk berakhir, draf hilang. Pecah barang masuk yang panjang menjadi beberapa dokumen.',
        'document_date' => 'Tanggal dokumen',
        'document_date_help' => 'Hanya catatan. Waktu barang masuk tetap waktu konfirmasi.',
        'review_title' => 'Tinjau barang masuk',
        'review_lines' => ':jumlah baris.',
        'review_unknown' => ':jumlah baris dengan biaya belum diketahui.',
        'review_note' => 'Setelah dikonfirmasi, stok bertambah dan dokumen ini tidak bisa diubah. Biayanya hanya bisa dikoreksi Pemilik.',
        'confirm' => 'Konfirmasi barang masuk',
        'success' => 'Barang masuk tercatat.',
        'disabled' => 'Tambahkan minimal satu baris yang lengkap. Paling banyak 500 baris.',
    ],
    'receipt_detail' => [
        'title' => 'Detail barang masuk',
        'subtitle' => 'Barang masuk :waktu',
    ],
    'disabled_unresolved_posting' => 'Ada barang masuk, barang keluar, atau penyesuaian yang statusnya belum pasti. Cek statusnya dulu.',
];
