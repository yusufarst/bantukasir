<?php

namespace App\Queries\Inventory;

use App\Policies\NotFound;
use App\Queries\Inventory\Column as Read;
use App\Support\Numbers\Quantity;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * RP-402, the stock history (ROUTE_CONTRACTS RP-402; INTERACTION_CONTRACTS section 3.2;
 * BR-INV-02, BR-INV-44; TECHNICAL_RULES TR-PAGE-01, TR-PAGE-02; TASK-022 scope item 2): the
 * movements, newest first — in posting order within a product when the list is filtered to
 * one, otherwise, also when filtered to the products of an adjustment (TASK-024), by the
 * server's time, then internal identifier — with keyset paging both
 * ways. Each row: the time, the product, the type, the signed quantity in the product's unit,
 * who recorded it and its source within the viewer's scope. Operational history carries no
 * cost (CAP-14, BR-INV-44). Of the sources, the receiving document and the manual issue
 * (TASK-023) are those whose tables exist; the Tasks that create the other source tables add
 * theirs here (EXC-44).
 */
final class StockHistory
{
    public const int PER_PAGE = 25;

    public const int MAX_PER_PAGE = 100;

    /** The movement types of INVENTORY section 2, in its order. */
    public const array TYPES = ['OPENING', 'RECEIPT', 'SALE_ISSUE', 'MANUAL_ISSUE', 'ADJUSTMENT', 'REVERSAL', 'RETURN_RECEIPT'];

    /** Every instant as the pages receive it: UTC with microseconds. */
    private const string RECORDED_AT = "to_char(m.recorded_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as recorded_at";

    private const string DOCUMENT_AT = "to_char(d.confirmed_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as document_at";

    private const string ISSUE_AT = "to_char(i.confirmed_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as issue_at";

    /**
     * @param  array{products: list<int>, type: string|null, from: string|null, to: string|null, after: string|null, before: string|null, perPage: int}  $filters
     * @return array{rows: list<array<string, mixed>>, nextCursor: string|null, previousCursor: string|null}
     */
    public function __invoke(array $filters): array
    {
        $byProduct = count($filters['products']) === 1;
        $backwards = $filters['before'] !== null;
        $cursor = $filters['before'] ?? $filters['after'];
        $query = $this->scoped($filters);

        if ($cursor !== null) {
            self::beyond($query, $byProduct, $cursor, $backwards ? '>' : '<');
        }

        self::order($query, $byProduct, $backwards);
        $rows = $query->limit($filters['perPage'] + 1)->get()->all();
        $more = count($rows) > $filters['perPage'];
        $rows = array_slice($rows, 0, $filters['perPage']);

        if ($backwards) {
            $rows = array_reverse($rows);
        }

        $data = array_map(static fn (object $row): array => self::row((array) $row), $rows);

        if ($data === []) {
            return ['rows' => [], 'nextCursor' => null, 'previousCursor' => null];
        }

        $first = self::position($data[0], $byProduct);
        $last = self::position($data[count($data) - 1], $byProduct);
        $hasNext = $backwards ? $this->exists($filters, $byProduct, $last, '<') : $more;
        $hasPrevious = $backwards ? $more : $this->exists($filters, $byProduct, $first, '>');

        return [
            'rows' => $data,
            'nextCursor' => $hasNext ? Keyset::encode($last) : null,
            'previousCursor' => $hasPrevious ? Keyset::encode($first) : null,
        ];
    }

    /**
     * The products that the list is filtered to, named by the filter, in its order; a product that
     * does not exist is NOT_FOUND, never an empty history (TR-ERR-03).
     *
     * @param  list<int>  $ids
     * @return list<array{id: int, name: string, sku: string}>
     *
     * @throws NotFound
     */
    public function products(array $ids): array
    {
        $found = [];

        foreach (DB::table('products')->whereIn('id', $ids)->get(['id', 'name', 'sku']) as $row) {
            $values = (array) $row;
            $id = Read::int($values, 'id');
            $found[$id] = ['id' => $id, 'name' => Read::text($values, 'name'), 'sku' => Read::text($values, 'sku')];
        }

        $products = [];

        foreach ($ids as $id) {
            $products[] = $found[$id] ?? throw new NotFound;
        }

        return $products;
    }

    /**
     * The movements that the filters keep, with what a row shows.
     *
     * @param  array{products: list<int>, type: string|null, from: string|null, to: string|null, after: string|null, before: string|null, perPage: int}  $filters
     */
    private function scoped(array $filters): Builder
    {
        $query = DB::table('stock_movements as m')
            ->join('products as p', 'p.id', '=', 'm.product_id')
            ->join('users as u', 'u.id', '=', 'm.actor_user_id')
            ->leftJoin('receiving_lines as l', 'l.id', '=', 'm.receiving_line_id')
            ->leftJoin('receiving_documents as d', 'd.id', '=', 'l.receiving_document_id')
            ->leftJoin('manual_issue_lines as il', 'il.id', '=', 'm.manual_issue_line_id')
            ->leftJoin('manual_issues as i', 'i.id', '=', 'il.manual_issue_id')
            ->select(['m.id', 'm.product_id', 'm.product_sequence', 'm.movement_type', 'm.quantity', 'm.quantity_precision', 'm.base_unit', 'p.name', 'p.sku', 'u.display_name', 'd.id as document_id', 'i.id as issue_id'])
            ->selectRaw(self::RECORDED_AT)
            ->selectRaw(self::DOCUMENT_AT)
            ->selectRaw(self::ISSUE_AT);

        if ($filters['products'] !== []) {
            $query->whereIn('m.product_id', $filters['products']);
        }

        if ($filters['type'] !== null) {
            $query->where('m.movement_type', $filters['type']);
        }

        // A business date is a day of Asia/Jakarta (ARC-51).
        if ($filters['from'] !== null) {
            $query->whereRaw("m.recorded_at >= (?::date)::timestamp at time zone 'Asia/Jakarta'", [$filters['from']]);
        }

        if ($filters['to'] !== null) {
            $query->whereRaw("m.recorded_at < ((?::date) + 1)::timestamp at time zone 'Asia/Jakarta'", [$filters['to']]);
        }

        return $query;
    }

    /**
     * Keeps the rows beyond the position: older with `<`, newer with `>`.
     */
    private static function beyond(Builder $query, bool $byProduct, string $cursor, string $direction): void
    {
        if ($byProduct) {
            $sequence = Keyset::int(Keyset::decode($cursor, ['int']), 0);
            $direction === '<' ? $query->where('m.product_sequence', '<', $sequence) : $query->where('m.product_sequence', '>', $sequence);

            return;
        }

        $position = Keyset::decode($cursor, ['instant', 'int']);
        $bindings = [Keyset::text($position, 0), Keyset::int($position, 1)];
        $direction === '<'
            ? $query->whereRaw('(m.recorded_at, m.id) < (?::timestamptz, ?)', $bindings)
            : $query->whereRaw('(m.recorded_at, m.id) > (?::timestamptz, ?)', $bindings);
    }

    /**
     * Newest first; a backward page is read oldest first, then turned round.
     */
    private static function order(Builder $query, bool $byProduct, bool $backwards): void
    {
        $direction = $backwards ? 'asc' : 'desc';

        if ($byProduct) {
            $query->orderBy('m.product_sequence', $direction);

            return;
        }

        $query->orderBy('m.recorded_at', $direction)->orderBy('m.id', $direction);
    }

    /**
     * Whether a movement that the filters keep lies beyond the position.
     *
     * @param  array{products: list<int>, type: string|null, from: string|null, to: string|null, after: string|null, before: string|null, perPage: int}  $filters
     * @param  list<int|string>  $position
     */
    private function exists(array $filters, bool $byProduct, array $position, string $direction): bool
    {
        $query = $this->scoped($filters);
        self::beyond($query, $byProduct, Keyset::encode($position), $direction);

        return $query->exists();
    }

    /**
     * The sort key of a row.
     *
     * @param  array<string, mixed>  $row
     * @return list<int|string>
     */
    private static function position(array $row, bool $byProduct): array
    {
        $id = Read::int($row, 'id');
        $sequence = Read::int($row, 'productSequence');
        $at = Read::text($row, 'recordedAt');

        return $byProduct ? [$sequence] : [$at, $id];
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    private static function row(array $values): array
    {
        $precision = Read::int($values, 'quantity_precision');
        $document = Read::nullableInt($values, 'document_id');
        $issue = Read::nullableInt($values, 'issue_id');

        return [
            'id' => Read::int($values, 'id'),
            'productSequence' => Read::int($values, 'product_sequence'),
            'recordedAt' => Read::text($values, 'recorded_at'),
            'type' => Read::text($values, 'movement_type'),
            'quantity' => Quantity::fromDatabase(Read::text($values, 'quantity'), $precision)->toCanonical(),
            'baseUnit' => Read::text($values, 'base_unit'),
            'quantityPrecision' => $precision,
            'product' => ['id' => Read::int($values, 'product_id'), 'name' => Read::text($values, 'name'), 'sku' => Read::text($values, 'sku')],
            'actorDisplayName' => Read::text($values, 'display_name'),
            'source' => match (true) {
                $document !== null => ['kind' => 'RECEIVING', 'id' => $document, 'at' => Read::text($values, 'document_at')],
                $issue !== null => ['kind' => 'MANUAL_ISSUE', 'id' => $issue, 'at' => Read::text($values, 'issue_at')],
                default => null,
            },
        ];
    }
}
