<?php

namespace App\Queries\Inventory;

use App\Policies\NotFound;
use App\Policies\ObjectScope;
use App\Queries\Inventory\Column as Read;
use App\Support\Numbers\Quantity;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The confirmed manual issues (ROUTE_CONTRACTS RP-407, RP-409; INTERACTION_CONTRACTS section 3.2;
 * BR-INV-18 to BR-INV-22; CAP-13; TASK-023 scope item 4). RP-407 lists them newest first, by
 * confirmation time, then internal identifier, with keyset paging both ways; `from` and `to` are
 * days of Asia/Jakarta, and `reason` one of the five of BR-INV-19. RP-409 reads one issue and its
 * lines, each with its movement. Neither carries a cost: an allocation is the Owner's (CAP-20),
 * and its reports are TASK-040's (ADR-008 section 6).
 */
final class ManualIssueRecords
{
    public const int PER_PAGE = 25;

    public const int MAX_PER_PAGE = 100;

    /** The most lines of an issue (TECHNICAL_RULES section 11.2), which RP-409 shows whole. */
    public const int MAX_LINES = 200;

    private const string CONFIRMED_AT = "to_char(i.confirmed_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as confirmed_at";

    /**
     * @param  array{from: string|null, to: string|null, reason: string|null, after: string|null, before: string|null, perPage: int}  $filters
     * @return array{rows: list<array{id: int, confirmedAt: string, reasonCode: string, lineCount: int, actorDisplayName: string}>, nextCursor: string|null, previousCursor: string|null}
     */
    public function list(array $filters): array
    {
        $backwards = $filters['before'] !== null;
        $cursor = $filters['before'] ?? $filters['after'];
        $query = self::scoped($filters)->select(['i.id', 'i.reason_code', 'i.line_count', 'i.actor_display_name'])->selectRaw(self::CONFIRMED_AT);

        if ($cursor !== null) {
            self::beyond($query, $cursor, $backwards ? '>' : '<');
        }

        $direction = $backwards ? 'asc' : 'desc';
        $rows = $query->orderBy('i.confirmed_at', $direction)->orderBy('i.id', $direction)->limit($filters['perPage'] + 1)->get()->all();
        $more = count($rows) > $filters['perPage'];
        $rows = array_slice($rows, 0, $filters['perPage']);

        if ($backwards) {
            $rows = array_reverse($rows);
        }

        $data = [];

        foreach ($rows as $row) {
            $values = (array) $row;
            $data[] = [
                'id' => Read::int($values, 'id'),
                'confirmedAt' => Read::text($values, 'confirmed_at'),
                'reasonCode' => Read::text($values, 'reason_code'),
                'lineCount' => Read::int($values, 'line_count'),
                'actorDisplayName' => Read::text($values, 'actor_display_name'),
            ];
        }

        if ($data === []) {
            return ['rows' => [], 'nextCursor' => null, 'previousCursor' => null];
        }

        $first = [$data[0]['confirmedAt'], $data[0]['id']];
        $last = [$data[count($data) - 1]['confirmedAt'], $data[count($data) - 1]['id']];
        $hasNext = $backwards ? self::exists($filters, $last, '<') : $more;
        $hasPrevious = $backwards ? $more : self::exists($filters, $first, '>');

        return [
            'rows' => $data,
            'nextCursor' => $hasNext ? Keyset::encode($last) : null,
            'previousCursor' => $hasPrevious ? Keyset::encode($first) : null,
        ];
    }

    /**
     * One issue with its lines, in their order.
     *
     * @return array{issue: array{id: int, confirmedAt: string, reasonCode: string, note: string|null, lineCount: int, actorDisplayName: string}, lines: list<array<string, mixed>>}
     *
     * @throws NotFound
     */
    public function detail(mixed $identifier): array
    {
        $key = ObjectScope::key($identifier);
        $row = $key === null ? null : DB::table('manual_issues as i')
            ->where('i.id', $key)
            ->select(['i.id', 'i.reason_code', 'i.note', 'i.line_count', 'i.actor_display_name'])
            ->selectRaw(self::CONFIRMED_AT)
            ->first();

        if ($row === null) {
            throw new NotFound;
        }

        $values = (array) $row;
        $lines = DB::table('manual_issue_lines as l')
            ->join('stock_movements as m', 'm.manual_issue_line_id', '=', 'l.id')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->where('l.manual_issue_id', Read::int($values, 'id'))
            ->orderBy('l.line_no')
            ->limit(self::MAX_LINES)
            ->get(['l.line_no', 'l.product_id', 'p.name', 'p.sku', 'm.id as movement_id', 'l.quantity', 'm.quantity_precision', 'm.base_unit'])
            ->all();

        return [
            'issue' => [
                'id' => Read::int($values, 'id'),
                'confirmedAt' => Read::text($values, 'confirmed_at'),
                'reasonCode' => Read::text($values, 'reason_code'),
                'note' => Read::nullableText($values, 'note'),
                'lineCount' => Read::int($values, 'line_count'),
                'actorDisplayName' => Read::text($values, 'actor_display_name'),
            ],
            'lines' => array_values(array_map(static fn (object $line): array => self::line((array) $line), $lines)),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    private static function line(array $values): array
    {
        $precision = Read::int($values, 'quantity_precision');

        return [
            'lineNo' => Read::int($values, 'line_no'),
            'product' => ['id' => Read::int($values, 'product_id'), 'name' => Read::text($values, 'name'), 'sku' => Read::text($values, 'sku')],
            'movementId' => Read::int($values, 'movement_id'),
            'quantity' => Quantity::fromDatabase(Read::text($values, 'quantity'), $precision)->toCanonical(),
            'baseUnit' => Read::text($values, 'base_unit'),
            'quantityPrecision' => $precision,
        ];
    }

    /**
     * The issues that the filters keep, as `i`.
     *
     * @param  array{from: string|null, to: string|null, reason: string|null, after: string|null, before: string|null, perPage: int}  $filters
     */
    private static function scoped(array $filters): Builder
    {
        $query = DB::table('manual_issues as i');

        if ($filters['from'] !== null) {
            $query->whereRaw("i.confirmed_at >= (?::date)::timestamp at time zone 'Asia/Jakarta'", [$filters['from']]);
        }

        if ($filters['to'] !== null) {
            $query->whereRaw("i.confirmed_at < ((?::date) + 1)::timestamp at time zone 'Asia/Jakarta'", [$filters['to']]);
        }

        if ($filters['reason'] !== null) {
            $query->where('i.reason_code', $filters['reason']);
        }

        return $query;
    }

    /**
     * Keeps the issues beyond the position: older with `<`, newer with `>`.
     */
    private static function beyond(Builder $query, string $cursor, string $direction): void
    {
        $position = Keyset::decode($cursor, ['instant', 'int']);
        $bindings = [Keyset::text($position, 0), Keyset::int($position, 1)];
        $direction === '<'
            ? $query->whereRaw('(i.confirmed_at, i.id) < (?::timestamptz, ?)', $bindings)
            : $query->whereRaw('(i.confirmed_at, i.id) > (?::timestamptz, ?)', $bindings);
    }

    /**
     * @param  array{from: string|null, to: string|null, reason: string|null, after: string|null, before: string|null, perPage: int}  $filters
     * @param  list<int|string>  $position
     */
    private static function exists(array $filters, array $position, string $direction): bool
    {
        $query = self::scoped($filters);
        self::beyond($query, Keyset::encode($position), $direction);

        return $query->exists();
    }
}
