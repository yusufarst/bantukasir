<?php

// Interface dictionary, file costing (ADR-005; COPY_DECK section 1.1): cost
// worklist, correction and history, cost fields. Its keys are copied verbatim
// from COPY_DECK by the first Task that uses them; TASK-003 adds none.

return [
    // RC-07's refusal of an explicit zero from staff (TASK-021; COPY_DECK section 15).
    'field' => [
        'zero_disabled' => 'Biaya 0 hanya bisa diisi Pemilik. Pilih Biaya belum diketahui; Pemilik akan melengkapinya.',
    ],
];
