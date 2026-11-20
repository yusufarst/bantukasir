<?php

// Interface dictionary, file reporting (ADR-005; COPY_DECK section 1.1): Owner
// home, daily summary, finance report. Its keys are copied verbatim from
// COPY_DECK by the first Task that uses them; TASK-003 adds none.

return [
    // The report figures and the completeness of Laporan keuangan (TASK-040; COPY_DECK section 10.1).
    'figure' => [
        'gross_sales' => 'Penjualan bruto',
        'gross_sales_help' => 'Nilai penjualan selesai sebelum pengembalian uang.',
        'refunds' => 'Pengembalian uang',
        'net_sales' => 'Penjualan neto',
        'net_sales_help' => 'Penjualan bruto dikurangi pengembalian uang.',
        'sale_count' => 'Jumlah penjualan',
        'payments_cash' => 'Pembayaran tunai',
        'payments_transfer' => 'Pembayaran transfer bank',
        'refund_payouts_cash' => 'Pengembalian dibayar tunai',
        'refund_payouts_transfer' => 'Pengembalian dibayar transfer bank',
        'quantity_sold' => 'Barang terjual',
        'quantity_returned' => 'Barang diretur',
        'cogs' => 'HPP',
        'cogs_help' => 'Harga pokok penjualan: biaya barang yang terjual, dikurangi biaya barang retur yang layak jual.',
        'gross_profit' => 'Laba kotor',
        'gross_profit_help' => 'Penjualan neto dikurangi HPP. Biaya usaha lain tidak termasuk.',
        'margin' => 'Margin',
        'margin_help' => 'Laba kotor dibagi penjualan neto. Hanya tampil bila data biaya lengkap dan penjualan neto lebih dari Rp0.',
    ],
    'as_of' => 'Data per :waktu WIB',
    'complete' => 'Data biaya lengkap',
    'unknown_lines' => 'Baris dengan biaya belum diketahui: :jumlah',
    'marked_products' => 'Produk dengan laporan ditandai belum lengkap: :jumlah',
    'last_correction' => 'Koreksi biaya terakhir: :waktu',
    'no_correction' => 'Belum ada koreksi biaya',
    'margin_hidden_zero' => 'Margin tidak ditampilkan karena penjualan neto Rp0 atau kurang.',
    // The period choice of UXP-502 (TASK-040; COPY_DECK section 10.3).
    'period' => [
        'label' => 'Periode',
        'today' => 'Hari Ini',
        'last_7_days' => '7 Hari',
        'this_month' => 'Bulan Ini',
        'last_month' => 'Bulan Lalu',
        'custom' => 'Rentang Kustom',
        'start' => 'Dari tanggal',
        'end' => 'Sampai tanggal',
        'range' => ':awal – :akhir',
    ],
    // UXP-502 Laporan keuangan (TASK-040; COPY_DECK sections 4.1 and 10.3).
    'finance' => [
        'title' => 'Laporan keuangan',
        'trend' => 'Tren',
        'trend_label' => 'Grafik tren :ukuran',
        'trend_point' => ':tanggal: :nilai',
        'show_table' => 'Tampilkan sebagai tabel',
        'show_chart' => 'Tampilkan sebagai grafik',
        'table_date' => 'Tanggal',
        'empty' => 'Belum ada penjualan pada periode ini.',
    ],
];
