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
