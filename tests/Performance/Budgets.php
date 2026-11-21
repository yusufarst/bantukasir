<?php

namespace Tests\Performance;

/**
 * The proposed budgets of TECHNICAL_RULES section 13.1 (PROPOSED — not accepted; PERF01, whose final
 * point is TASK-048 on the intended server), each with the metric of the scenarios that measures it
 * (tests/Performance/scenarios/perf01.js) and the statistic its budget names: the 95th percentile of
 * the time to the answer, or, for the two operations with a 30-second budget, the longest. The
 * operation's name is the section's own, so that a test compares the two tables (AC-043-02).
 */
final class Budgets
{
    /**
     * @var array<string, array{operation: string, statistic: 'p(95)'|'max', milliseconds: int}>
     */
    public const array METRICS = [
        'op_barcode_lookup' => ['operation' => 'Exact barcode lookup', 'statistic' => 'p(95)', 'milliseconds' => 500],
        'op_product_search' => ['operation' => 'Product Search page', 'statistic' => 'p(95)', 'milliseconds' => 700],
        'op_sale_50_lines' => ['operation' => 'Sale commit, 50 lines', 'statistic' => 'p(95)', 'milliseconds' => 2000],
        'op_sale_200_lines' => ['operation' => 'Sale commit, 200 lines (the maximum)', 'statistic' => 'p(95)', 'milliseconds' => 5000],
        'op_session_close' => ['operation' => 'Cash session close', 'statistic' => 'p(95)', 'milliseconds' => 2000],
        'op_refund_commit' => ['operation' => 'Refund or return commit', 'statistic' => 'p(95)', 'milliseconds' => 2000],
        'op_report_period' => ['operation' => 'Ordinary period report (up to 31 days)', 'statistic' => 'p(95)', 'milliseconds' => 2000],
        'op_report_max_range' => ['operation' => 'Report over the maximum range (366 days)', 'statistic' => 'p(95)', 'milliseconds' => 10000],
        'op_stock_attention' => ['operation' => 'Stock attention refresh', 'statistic' => 'p(95)', 'milliseconds' => 500],
        'op_page_visit' => ['operation' => 'Server time of an ordinary page visit', 'statistic' => 'p(95)', 'milliseconds' => 500],
        'op_csv_import' => ['operation' => 'CSV import confirmation, 5,000 rows', 'statistic' => 'max', 'milliseconds' => 30000],
        'op_cost_replay' => ['operation' => 'Cost-correction replay, 10,000 movements of one product', 'statistic' => 'max', 'milliseconds' => 30000],
    ];

    /** The load during the measurement (TECHNICAL_RULES section 13.1; L-P6; PERF01). */
    public const string LOAD = 'Two cashiers checking out concurrently while the Owner runs a report';
}
