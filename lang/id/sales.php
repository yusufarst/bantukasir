<?php

// Interface dictionary, file sales (ADR-005; COPY_DECK section 1.1): till,
// payment, receipt, Sales, refunds and returns. Its keys are copied verbatim
// from COPY_DECK by the first Task that uses them; TASK-003 adds none. TASK-027
// adds the title of the Kasir destination (UXI-002; section 4.3), which leads
// to Buka sesi kas until TASK-029 delivers the till.

return [
    'till' => [
        'title' => 'Kasir',
    ],
];
