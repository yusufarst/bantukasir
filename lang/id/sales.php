<?php

// Interface dictionary, file sales (ADR-005; COPY_DECK section 1.1): till,
// payment, receipt, Sales, refunds and returns. Its keys are copied verbatim
// from COPY_DECK by the first Task that uses them; TASK-003 adds none. TASK-027
// adds the title of the Kasir destination (UXI-002; section 4.3). TASK-029 adds
// the till, the payment screen and the receipt page (sections 4.1, 7.1 to 7.3
// and 15).

return [
    'till' => [
        'title' => 'Kasir',
        'session' => 'Sesi kas :nomor · :laci',
        'search' => 'Cari atau scan produk',
        'search_hint' => 'Scan barcode, atau ketik nama atau SKU',
        'add_to_cart' => 'Tambah ke keranjang',
        'add_to_cart_named' => 'Tambah :produk ke keranjang',
        'result_stock' => 'Stok :jumlah',
        'resolve_payment' => 'Cek pembayaran yang belum pasti',
    ],
    'cart' => [
        'title' => 'Keranjang',
        'empty' => 'Keranjang masih kosong. Scan atau cari produk.',
        'line_total' => 'Subtotal',
        'total' => 'Total',
        'quantity' => 'Jumlah',
        'increase' => 'Tambah satu :satuan :produk',
        'decrease' => 'Kurangi satu :satuan :produk',
        'remove' => 'Hapus dari keranjang',
        'remove_named' => 'Hapus :produk dari keranjang',
        'clear' => 'Kosongkan keranjang',
        'clear_confirm' => [
            'title' => 'Kosongkan keranjang?',
            'body' => 'Semua baris di keranjang dihapus. Belum ada yang tersimpan.',
        ],
        'accept_new_prices' => 'Setuju harga baru',
        'pay' => 'Bayar',
        'disabled_empty' => 'Keranjang masih kosong.',
        'disabled_price_review' => 'Setujui harga baru dulu.',
        'disabled_onboarding' => 'Penjualan berhenti selama masa input stok awal.',
        'disabled_unresolved' => 'Ada pembayaran yang statusnya belum pasti. Cek statusnya dulu.',
        'disabled_invalid' => 'Perbaiki baris yang ditandai dulu.',
        'decrease_disabled' => 'Jumlah tidak bisa dikurangi lagi. Hapus barisnya jika tidak jadi dibeli.',
    ],
    'payment' => [
        'title' => 'Pembayaran',
        'total' => 'Total',
        'method' => 'Metode pembayaran',
        'method_cash' => 'Tunai',
        'method_transfer' => 'Transfer bank',
        'tendered' => 'Uang diterima',
        'exact_cash' => 'Uang Pas',
        'change' => 'Kembalian',
        'transfer_help' => 'Pastikan dana sudah masuk ke rekening toko, misalnya dari aplikasi bank toko, notifikasi di perangkat toko, atau mutasi rekening. Tangkapan layar atau ponsel pelanggan saja tidak cukup.',
        'reference' => 'Referensi pembayaran',
        'customer_name' => 'Nama pelanggan',
        'customer_name_help' => 'Dicetak di struk.',
        'final_cash' => 'Selesaikan',
        'final_transfer' => 'Dana sudah diterima — Selesaikan',
        'back_to_cart' => 'Kembali ke keranjang',
        'back_disabled' => 'Tunggu sampai hasil pembayaran diketahui.',
        'disabled_tender' => 'Uang diterima kurang :uang.',
        'restart_from_cart' => 'Mulai lagi dari keranjang',
    ],
    'receipt' => [
        'title' => 'Struk',
        'saved' => 'Penjualan :nomor tersimpan.',
        'new_sale' => 'Transaksi baru',
        'search_hint' => 'Scan produk berikutnya untuk memulai transaksi baru.',
        'size' => 'Ukuran struk',
        'size_58' => '58 mm',
        'size_80' => '80 mm',
        'size_a4' => 'A4',
    ],
];
