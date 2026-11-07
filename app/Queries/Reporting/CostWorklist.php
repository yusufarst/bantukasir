<?php

namespace App\Queries\Reporting;

use App\Queries\Inventory\Column as Read;
use App\Queries\Inventory\Keyset;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * RP-503, "Biaya perlu dilengkapi" (ROUTE_CONTRACTS RP-503; COPY_DECK section 10.4; BR-COST-03,
 * BR-COST-22, BR-COST-27, BR-COST-38; CAP-20; TASK-025 scope item 6): the cost sources that need the
 * Owner — each whose effective cost is UNKNOWN, never completed, and each that carries an active
 * "reporting incomplete" mark — newest first by the time the cost was recorded, then by internal key,
 * keyset-paged both ways (TR-PAGE-01), optionally of one product. Each row names its product, the
 * kind and time of its movement, and whether it is unknown, marked, or both.
 */
final class CostWorklist
{
    public const int PER_PAGE = 25;

    public const int MAX_PER_PAGE = 100;

    private const string RECORDED_AT = "to_char(e.recorded_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as recorded_at";

    /** An evidence whose effective cost is UNKNOWN: unknown when posted and never completed. */
    private const string UNKNOWN = "(e.cost_status = 'UNKNOWN' and not exists (select 1 from cost_corrections c where c.evidence_id = e.id))";

    /** An evidence with an active mark. */
    private const string MARKED = '(exists (select 1 from cost_incomplete_marks k where k.evidence_id = e.id and not exists (select 1 from cost_incomplete_mark_clearances x where x.mark_id = k.id)))';

    /**
     * @param  array{product: int|null, after: string|null, before: string|null, perPage: int}  $filters
     * @return array{rows: list<array{evidenceId: int, product: array{id: int, name: string, sku: string}, sourceType: string, recordedAt: string, unknown: bool, marked: bool}>, nextCursor: string|null, previousCursor: string|null}
     */
    public function list(array $filters): array
    {
        $backwards = $filters['before'] !== null;
        $cursor = $filters['before'] ?? $filters['after'];
        $query = self::scoped($filters)
            ->select(['e.id', 'e.product_id', 'p.name', 'p.sku', 'e.movement_type'])
            ->selectRaw(self::RECORDED_AT)
            ->selectRaw(self::UNKNOWN.' as unknown')
            ->selectRaw(self::MARKED.' as marked');

        if ($cursor !== null) {
            self::beyond($query, $cursor, $backwards ? '>' : '<');
        }

        $direction = $backwards ? 'asc' : 'desc';
        $rows = $query->orderBy('e.recorded_at', $direction)->orderBy('e.id', $direction)->limit($filters['perPage'] + 1)->get()->all();
        $more = count($rows) > $filters['perPage'];
        $rows = array_slice($rows, 0, $filters['perPage']);

        if ($backwards) {
            $rows = array_reverse($rows);
        }

        $data = [];

        foreach ($rows as $row) {
            $values = (array) $row;
            $data[] = [
                'evidenceId' => Read::int($values, 'id'),
                'product' => ['id' => Read::int($values, 'product_id'), 'name' => Read::text($values, 'name'), 'sku' => Read::text($values, 'sku')],
                'sourceType' => Read::text($values, 'movement_type'),
                'recordedAt' => Read::text($values, 'recorded_at'),
                'unknown' => Read::bool($values, 'unknown'),
                'marked' => Read::bool($values, 'marked'),
            ];
        }

        if ($data === []) {
            return ['rows' => [], 'nextCursor' => null, 'previousCursor' => null];
        }

        $first = [$data[0]['recordedAt'], $data[0]['evidenceId']];
        $last = [$data[count($data) - 1]['recordedAt'], $data[count($data) - 1]['evidenceId']];
        $hasNext = $backwards ? self::exists($filters, $last, '<') : $more;
        $hasPrevious = $backwards ? $more : self::exists($filters, $first, '>');

        return [
            'rows' => $data,
            'nextCursor' => $hasNext ? Keyset::encode($last) : null,
            'previousCursor' => $hasPrevious ? Keyset::encode($first) : null,
        ];
    }

    /**
     * The product of the filter, by its key, for the page's filter; null when none exists.
     *
     * @return array{id: int, name: string, sku: string}|null
     */
    public function product(int $productId): ?array
    {
        $row = DB::table('products')->where('id', $productId)->first(['id', 'name', 'sku']);

        if ($row === null) {
            return null;
        }

        $values = (array) $row;

        return ['id' => Read::int($values, 'id'), 'name' => Read::text($values, 'name'), 'sku' => Read::text($values, 'sku')];
    }

    /**
     * The cost sources that the worklist holds and the filter keeps, as `e` with their product `p`.
     *
     * @param  array{product: int|null, after: string|null, before: string|null, perPage: int}  $filters
     */
    private static function scoped(array $filters): Builder
    {
        $query = DB::table('acquisition_cost_evidence as e')
            ->join('products as p', 'p.id', '=', 'e.product_id')
            ->whereRaw('('.self::UNKNOWN.' or '.self::MARKED.')');

        if ($filters['product'] !== null) {
            $query->where('e.product_id', $filters['product']);
        }

        return $query;
    }

    /**
     * Keeps the rows beyond the position: older with `<`, newer with `>`.
     */
    private static function beyond(Builder $query, string $cursor, string $direction): void
    {
        $position = Keyset::decode($cursor, ['instant', 'int']);
        $bindings = [Keyset::text($position, 0), Keyset::int($position, 1)];
        $direction === '<'
            ? $query->whereRaw('(e.recorded_at, e.id) < (?::timestamptz, ?)', $bindings)
            : $query->whereRaw('(e.recorded_at, e.id) > (?::timestamptz, ?)', $bindings);
    }

    /**
     * @param  array{product: int|null, after: string|null, before: string|null, perPage: int}  $filters
     * @param  list<int|string>  $position
     */
    private static function exists(array $filters, array $position, string $direction): bool
    {
        $query = self::scoped($filters);
        self::beyond($query, Keyset::encode($position), $direction);

        return $query->exists();
    }
}
