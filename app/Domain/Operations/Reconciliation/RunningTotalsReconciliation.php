<?php

namespace App\Domain\Operations\Reconciliation;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The comparison of the Sales' running totals with their refund and return lines (SCHEMA guard G-03
 * and section 5.9; G-06; TASK-036 scope item 2), in the Operations module, which reads the Sales
 * tables (STACK_AND_MODULES section 2.1). Each running total is a sum of whole rupiah or of exact
 * quantities, which PostgreSQL adds exactly: a Sale's refunded amount against its refunds, and each
 * line's refunded amount and returned quantity, saleable or not, against its refund and return lines.
 * A line without its row of totals counts as zero. It reads in the caller's transaction and writes
 * nothing; a difference is reported as an incident, never blocked (BR-INV-46).
 */
final class RunningTotalsReconciliation
{
    private const string DIFFERING = <<<'SQL'
        select t.sale_id
        from sale_correction_totals t
        where t.sale_id > ? and t.sale_id <= ?
          and t.refunded_amount <> coalesce((select sum(r.amount) from refunds r where r.sale_id = t.sale_id), 0)
        union
        select l.sale_id
        from sale_lines l
        left join sale_line_correction_totals lt on lt.sale_line_id = l.id
        where l.sale_id > ? and l.sale_id <= ?
          and (coalesce(lt.refunded_amount, 0) <> coalesce((select sum(rl.amount) from refund_lines rl where rl.sale_line_id = l.id), 0)
            or coalesce(lt.returned_quantity, 0) <> coalesce((select sum(rt.quantity) from return_lines rt where rt.sale_line_id = l.id), 0))
        order by 1
        SQL;

    /**
     * The Sales after this one, at most $limit of them in key order, and among them those whose
     * running totals differ from their lines; the last Sale read, null when none is left.
     *
     * @return array{last: int|null, differing: list<int>}
     */
    public function after(int $afterSale, int $limit): array
    {
        $keys = self::keys(DB::select('select id as sale_id from sales where id > ? order by id limit ?', [$afterSale, $limit]), 'sale_id');
        $last = $keys === [] ? null : $keys[count($keys) - 1];

        if ($last === null) {
            return ['last' => null, 'differing' => []];
        }

        return ['last' => $last, 'differing' => self::keys(DB::select(self::DIFFERING, [$afterSale, $last, $afterSale, $last]), 'sale_id')];
    }

    /**
     * Whether this one Sale's running totals differ from its lines.
     */
    public function differs(int $sale): bool
    {
        return self::keys(DB::select(self::DIFFERING, [$sale - 1, $sale, $sale - 1, $sale]), 'sale_id') !== [];
    }

    /**
     * @param  array<array-key, mixed>  $rows
     * @return list<int>
     */
    private static function keys(array $rows, string $column): array
    {
        $keys = [];

        foreach ($rows as $row) {
            $key = is_object($row) ? ((array) $row)[$column] ?? null : null;
            $keys[] = is_int($key) ? $key : throw new LogicException('A key of the comparison is a whole number.');
        }

        return $keys;
    }
}
