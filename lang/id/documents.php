<?php

// Interface dictionary, file documents (ADR-005; COPY_DECK section 1.1):
// receipt, Nota Penjualan, session report, labels, CSV export headers. Its
// keys are copied verbatim from COPY_DECK by the first Task that uses them;
// TASK-018 adds the label's (COPY_DECK section 17.3); TASK-029 the receipt's
// that UXP-103 shows from the snapshot (section 17.1). The copy marker is the
// reprint's, TASK-031's.

return [
    'invoice' => [
        'title' => 'Nota Penjualan',
    ],
    'label' => [
        'sku' => 'SKU :sku',
    ],
    // The labels of a session's report as UXP-206 shows a CLOSED session (COPY_DECK sections 7.11
    // and 17.2; TASK-030); the print, the export and the drawer-funded refunds' rows are TASK-031's
    // and TASK-034's.
    'session_report' => [
        'title' => 'Laporan sesi kas',
        'register' => 'Laci kas',
        'responsible' => 'Penanggung jawab',
        'opened_at' => 'Dibuka',
        'cutoff' => 'Dihitung sampai',
        'closed_at' => 'Ditutup',
        'opening_cash' => 'Modal awal',
        'sale_count' => 'Jumlah penjualan',
        'sale_value' => 'Nilai penjualan',
        'cash_payments' => 'Pembayaran tunai',
        'transfer_payments' => 'Pembayaran transfer bank',
        'drawer_refunds' => 'Pengembalian dari laci kas',
        'expected_cash' => 'Uang seharusnya di laci',
        'physical_cash' => 'Uang fisik di laci',
        'variance' => 'Selisih',
        'closed_by' => 'Ditutup oleh',
        'close_reason' => 'Alasan penutupan',
        'notes' => 'Catatan',
    ],
    'receipt' => [
        'number' => 'No. :nomor',
        'time' => 'Waktu: :waktu',
        'cashier' => 'Kasir: :nama',
        'session' => 'Sesi kas: :nomor',
        'line_quantity' => ':jumlah :satuan × :harga',
        'sku' => 'SKU :sku',
        'total' => 'Total',
        'method' => 'Pembayaran: :metode',
        'tendered' => 'Uang diterima',
        'change' => 'Kembalian',
        'customer' => 'Pelanggan: :nama',
    ],
];
