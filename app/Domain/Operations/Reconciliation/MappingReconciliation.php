<?php

namespace App\Domain\Operations\Reconciliation;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The comparison of the active barcode mappings with the mapping facts (SCHEMA guard G-03, G-09 and
 * section 5.6; TASK-036 scope item 2), in the Operations module, which reads the Catalog's tables
 * (STACK_AND_MODULES section 2.1): each token's active mapping is its mapping without a retirement, of
 * which the facts allow at most one. A token differs when its active row names a retired mapping or
 * another mapping than that one, or when it has an unretired mapping and no active row. It reads in
 * the caller's transaction and writes nothing; a difference is reported as an incident, never blocked
 * (BR-INV-46).
 */
final class MappingReconciliation
{
    private const string DIFFERING = <<<'SQL'
        select t.id as token_id
        from barcode_tokens t
        left join barcode_active_mappings a on a.token_id = t.id
        left join lateral (
            select m.id from barcode_mappings m
            where m.token_id = t.id and not exists (select 1 from barcode_mapping_retirements r where r.mapping_id = m.id)
            order by m.id
            limit 2
        ) u on true
        where t.id > ? and t.id <= ?
        group by t.id, a.mapping_id
        having count(u.id) > 1 or a.mapping_id is distinct from min(u.id)
        order by t.id
        SQL;

    /**
     * The tokens after this one, at most $limit of them in key order, and among them those whose
     * active mapping differs from the facts; the last token read, null when none is left.
     *
     * @return array{last: int|null, differing: list<int>}
     */
    public function after(int $afterToken, int $limit): array
    {
        $keys = self::keys(DB::select('select id as token_id from barcode_tokens where id > ? order by id limit ?', [$afterToken, $limit]));
        $last = $keys === [] ? null : $keys[count($keys) - 1];

        if ($last === null) {
            return ['last' => null, 'differing' => []];
        }

        return ['last' => $last, 'differing' => self::keys(DB::select(self::DIFFERING, [$afterToken, $last]))];
    }

    /**
     * The mappings of this token without a retirement, with their products: at most one, which the
     * facts make active; more than one is a fact that should not exist, which no rebuild repairs.
     *
     * @return list<array{mapping: int, product: int}>
     */
    public function unretiredOf(int $token): array
    {
        $rows = DB::select(<<<'SQL'
            select m.id, m.product_id from barcode_mappings m
            where m.token_id = ? and not exists (select 1 from barcode_mapping_retirements r where r.mapping_id = m.id)
            order by m.id
            SQL, [$token]);
        $mappings = [];

        foreach ($rows as $row) {
            $values = (array) $row;
            $mapping = $values['id'] ?? null;
            $product = $values['product_id'] ?? null;

            if (! is_int($mapping) || ! is_int($product)) {
                throw new LogicException('A mapping has its key and its product.');
            }

            $mappings[] = ['mapping' => $mapping, 'product' => $product];
        }

        return $mappings;
    }

    /**
     * Whether this one token's active mapping differs from the facts.
     */
    public function differs(int $token): bool
    {
        return self::keys(DB::select(self::DIFFERING, [$token - 1, $token])) !== [];
    }

    /**
     * @param  array<array-key, mixed>  $rows
     * @return list<int>
     */
    private static function keys(array $rows): array
    {
        $keys = [];

        foreach ($rows as $row) {
            $key = is_object($row) ? ((array) $row)['token_id'] ?? null : null;
            $keys[] = is_int($key) ? $key : throw new LogicException('A key of the comparison is a whole number.');
        }

        return $keys;
    }
}
