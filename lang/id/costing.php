<?php

// Interface dictionary, file costing (ADR-005; COPY_DECK section 1.1): cost
// worklist, correction and history, cost fields. Its keys are copied verbatim
// from COPY_DECK by the first Task that uses them; TASK-003 adds none. TASK-022
// adds the cost fields of receiving and the Owner's cost evidence (COPY_DECK
// sections 9.1, 9.4 and 9.7). TASK-025 adds the cost pages, the corrected cost
// of a receiving and the Owner's corrections (COPY_DECK sections 4.1, 9.4, 9.7,
// 10.4 to 10.6 and 15).

return [
    // RC-07's refusal of an explicit zero from staff (TASK-021; COPY_DECK section 15).
    'field' => [
        'zero_disabled' => 'Biaya 0 hanya bisa diisi Pemilik. Pilih Biaya belum diketahui; Pemilik akan melengkapinya.',
        'line_cost' => 'Biaya',
        'line_cost_help' => 'Total biaya perolehan untuk jumlah ini, termasuk biaya sampai barang diterima.',
        'cost_unknown' => 'Biaya belum diketahui',
        'cost_unknown_help' => 'Pemilik akan melengkapinya nanti.',
        'zero_evidence' => 'Bukti atau alasan biaya nol',
        'purchase_cost' => 'Biaya',
        'cost_now' => 'Biaya sekarang',
        'cost_at_confirmation' => 'Biaya saat dikonfirmasi',
        'cost_was_unknown' => 'Saat dikonfirmasi: biaya belum diketahui',
        'correction_history' => 'Riwayat koreksi',
        'correction_entry' => ':lama → :baru · :waktu · oleh :nama · alasan: :alasan',
    ],
    'evidence' => [
        'title' => 'Bukti biaya',
        'total' => 'Total biaya',
        'corrections' => 'Koreksi',
    ],
    'status' => [
        'known' => 'Biaya diketahui',
        'unknown' => 'Biaya perlu dilengkapi pemilik',
        'zero' => 'Biaya nol (berbukti)',
        'marked_incomplete' => 'Laporan ditandai belum lengkap',
    ],
    'worklist' => [
        'title' => 'Biaya perlu dilengkapi',
        'intro' => 'Biaya yang belum diketahui dan produk yang laporannya ditandai belum lengkap. Lengkapi agar HPP dan laba kotor lengkap.',
        'filter_product' => 'Produk',
        'col_product' => 'Produk',
        'col_source' => 'Sumber biaya',
        'col_status' => 'Status',
        'source_entry' => ':jenis :waktu',
        'empty' => 'Semua biaya sudah lengkap.',
    ],
    'correction' => [
        'title_complete' => 'Lengkapi biaya',
        'title_correct' => 'Koreksi biaya',
        'current' => 'Biaya sekarang: :biaya',
        'history' => 'Riwayat',
        'new_cost' => 'Biaya baru',
        'new_cost_help' => 'Total biaya perolehan untuk jumlah pada sumber ini.',
        'reason' => 'Alasan',
        'review_title' => 'Tinjau koreksi biaya',
        'review_body' => 'Biaya lama :lama → biaya baru :baru. HPP, nilai persediaan, dan laba kotor produk ini dihitung ulang.',
        'confirm' => 'Konfirmasi koreksi',
        'success' => 'Biaya disimpan. Laporan sudah dihitung ulang.',
        'disabled' => 'Isi biaya baru dan alasan.',
    ],
    'history' => [
        'title' => 'Riwayat biaya',
        'corrections' => 'Koreksi',
        'average_cost' => 'Biaya rata-rata',
        'inventory_value' => 'Nilai persediaan',
        'evidence_entry' => ':jenis :waktu · :jumlah · :biaya',
    ],
    // UXI-226, UXI-249, UXI-314 and UXI-324 while a cost correction is uncertain (COPY_DECK section 15).
    'disabled_unresolved' => 'Ada koreksi biaya yang statusnya belum pasti. Cek statusnya dulu.',
];
