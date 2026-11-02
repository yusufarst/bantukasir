<?php

// Interface dictionary, file costing (ADR-005; COPY_DECK section 1.1): cost
// worklist, correction and history, cost fields. Its keys are copied verbatim
// from COPY_DECK by the first Task that uses them; TASK-003 adds none. TASK-022
// adds the cost fields of receiving and the Owner's cost evidence (COPY_DECK
// sections 9.1, 9.4 and 9.7).

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
    ],
    'evidence' => [
        'title' => 'Bukti biaya',
        'total' => 'Total biaya',
    ],
    'status' => [
        'known' => 'Biaya diketahui',
        'unknown' => 'Biaya perlu dilengkapi pemilik',
        'zero' => 'Biaya nol (berbukti)',
    ],
];
