<?php

namespace Tests\Performance;

use App\Actions\Operations\RunReconciliation;
use App\Domain\Operations\Reconciliation\ReconciliationRuns;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Performance\Dataset\ShopBuilder;

/**
 * The plan of one run of the PERF01 scenarios (TASK-043 scope item 2), read by the load tool: the
 * people, each with a new password set through a one-time link as the fixtures set one (EXC-63; it is
 * made at run time, written only to the ignored folder storage/app/perf and never printed); the
 * registers and any session left OPEN; the products of the carts, the tokens of the lookups, the Sale
 * lines that the refunds take; the product of 10,000 movements and the cost source whose correction
 * replays them; the names of the CSV import; and the reconciliation before the run.
 */
final class Plan
{
    /** The products of the carts, of which a 200-line Sale takes 200. */
    private const int TILL_PRODUCTS = 600;

    /**
     * @param  array<mixed>  $dataset  the summary that the dataset's build wrote
     * @return array<string, mixed>
     */
    public static function make(int $run, int $runs, int $seconds, array $dataset): array
    {
        TokoContoh::build(DB::connection());

        $heavy = is_int($dataset['heavyProduct'] ?? null) ? $dataset['heavyProduct'] : throw new LogicException('The dataset names its product of 10,000 movements.');
        // The reconciliation of every product, timed: whether a run finishes within its budget on the
        // provisional dataset is TASK-036's finding F-9, for TASK-043 and TASK-048.
        $started = microtime(true);
        $before = app(RunReconciliation::class)(null, ReconciliationRuns::SCHEDULE)->outcome;
        $beforeMs = (int) round((microtime(true) - $started) * 1000);
        $registers = DB::table('cash_registers')->orderBy('code')->pluck('id', 'code')->all();
        $people = [];

        foreach (['owner' => 'Hendra', 'cashierA' => ShopBuilder::CASHIERS[0], 'cashierB' => ShopBuilder::CASHIERS[1]] as $role => $person) {
            $account = TokoContoh::person($person);
            $open = DB::table('cash_sessions')->where('responsible_user_id', $account['id'])->where('status', 'OPEN')->value('id');
            $people[$role] = ['email' => $account['email'], 'password' => $account['password'], 'openSession' => is_int($open) ? $open : null];
        }

        return [
            'run' => $run,
            'runs' => $runs,
            'seconds' => $seconds,
            'startedAt' => now('Asia/Jakarta')->toIso8601String(),
            'dataset' => $dataset,
            'people' => $people,
            'registers' => ['cashierA' => $registers['LACI-1'] ?? null, 'cashierB' => $registers['LACI-2'] ?? null, 'close' => $registers['LACI-3'] ?? null],
            'till' => self::till($heavy),
            'tokens' => self::tokens($heavy),
            'searchTerms' => ['Pulpen', 'Buku', 'Sabun', 'Kopi', 'Beras', 'Merah', 'Isi Ulang'],
            'refunds' => self::refunds($heavy, $run),
            'replay' => self::replay($heavy),
            'import' => ['category' => 'Kategori 01', 'prefix' => sprintf('IMP%02d', $run), 'rows' => 5000],
            'reconciliationBefore' => $before,
            'reconciliationBeforeMs' => $beforeMs,
        ];
    }

    /**
     * The active products with stock for the carts, never the product of 10,000 movements, whose replay
     * the cap of BR-COST-28 bounds.
     *
     * @return list<array{id: int, revision: int, price: int}>
     */
    private static function till(int $heavy): array
    {
        $rows = DB::table('products as p')
            ->join('selling_price_revisions as r', 'r.id', '=', 'p.current_price_revision_id')
            ->join('stock_balances as b', 'b.product_id', '=', 'p.id')
            ->where('p.active', true)
            ->where('p.id', '<>', $heavy)
            ->where('b.on_hand', '>=', 40)
            ->orderBy('p.id')
            ->limit(self::TILL_PRODUCTS)
            ->get(['p.id', 'r.id as revision', 'r.amount']);
        $till = [];

        foreach ($rows as $row) {
            if (is_int($row->id) && is_int($row->revision) && is_int($row->amount)) {
                $till[] = ['id' => $row->id, 'revision' => $row->revision, 'price' => $row->amount];
            }
        }

        return $till;
    }

    /**
     * Active tokens of other products than the product of 10,000 movements, for the exact lookup.
     *
     * @return list<string>
     */
    private static function tokens(int $heavy): array
    {
        $tokens = DB::table('barcode_active_mappings as a')
            ->join('barcode_tokens as t', 't.id', '=', 'a.token_id')
            ->where('a.product_id', '<>', $heavy)
            ->orderBy('t.id')
            ->limit(500)
            ->pluck('t.token')
            ->all();

        return array_values(array_filter($tokens, is_string(...)));
    }

    /**
     * Sale lines of other products than the product of 10,000 movements, of Sales not yet corrected,
     * each refunded in full by bank transfer without a return: a run takes its own share.
     *
     * @return list<array{saleId: int, saleLineId: int, amount: int}>
     */
    private static function refunds(int $heavy, int $run): array
    {
        $rows = DB::table('sale_lines as l')
            ->where('l.product_id', '<>', $heavy)
            ->whereNotExists(static fn (Builder $query): Builder => $query->from('sale_corrections as c')->whereColumn('c.sale_id', 'l.sale_id'))
            ->where('l.line_no', 2)
            ->orderByDesc('l.sale_id')
            ->offset(($run - 1) * 200)
            ->limit(200)
            ->get(['l.sale_id', 'l.id', 'l.line_total']);
        $refunds = [];

        foreach ($rows as $row) {
            if (is_int($row->sale_id) && is_int($row->id) && is_int($row->line_total) && $row->line_total > 0) {
                $refunds[] = ['saleId' => $row->sale_id, 'saleLineId' => $row->id, 'amount' => $row->line_total];
            }
        }

        return $refunds;
    }

    /**
     * The cost source of the first receipt of the product of 10,000 movements, whose correction replays
     * every movement after it, with the number of its latest correction and its amount now.
     *
     * @return array{evidenceId: int, correctionNo: int, amount: string}|null
     */
    private static function replay(int $heavy): ?array
    {
        $row = DB::table('acquisition_cost_evidence as e')
            ->join('stock_movements as m', 'm.id', '=', 'e.movement_id')
            ->where('e.product_id', $heavy)
            ->orderBy('m.product_sequence')
            ->first(['e.id', 'e.total_amount']);

        if ($row === null || ! is_int($row->id) || ! is_string($row->total_amount)) {
            return null;
        }

        $latest = DB::table('cost_corrections')->where('evidence_id', $row->id)->orderByDesc('correction_no')->first(['correction_no', 'new_amount']);
        $amount = $latest !== null && is_string($latest->new_amount) ? $latest->new_amount : $row->total_amount;

        return ['evidenceId' => $row->id, 'correctionNo' => $latest !== null && is_int($latest->correction_no) ? $latest->correction_no : 0, 'amount' => explode('.', $amount)[0]];
    }
}
