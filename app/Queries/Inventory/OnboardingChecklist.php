<?php

namespace App\Queries\Inventory;

use App\Queries\Inventory\Column as Read;
use App\Support\Numbers\Quantity;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The onboarding checklist of RP-412 (ROUTE_CONTRACTS RP-412; COPY_DECK section 9.13, UXI-287;
 * INTERACTION_INVENTORY UXI-287 to UXI-291; BR-INV-26 to BR-INV-28, BR-INV-47; D-087; TASK-035 scope item
 * 5). A product is on it when it has an effective onboarding entry — counted, with its OPENING's quantity
 * and cost status, or counted zero — or when it is active, without such an entry and without a movement
 * of another type than its OPENINGs and their REVERSALs, so that it is still to be counted: never
 * counted, its zero count withdrawn, or its OPENING reversed and waiting for its replacement. A product
 * that holds other movements entered stock otherwise and is not on the list (BR-INV-26). The rows are
 * in name order, then internal identifier, paged both ways with keyset cursors (TECHNICAL_RULES
 * section 11.4, TR-PAGE-01 to TR-PAGE-03); the counts of the release review cover every product of the
 * list (BR-INV-28). The cost is its status only, never an amount (COPY_DECK UXI-287, "Status biaya").
 */
final class OnboardingChecklist
{
    public const int PER_PAGE = 50;

    public const string COUNTED = 'counted';

    public const string NOT_COUNTED = 'not_counted';

    /** The filter of RP-412 (UXI-287). */
    public const array FILTERS = [self::COUNTED, self::NOT_COUNTED];

    /** The states of a row of the checklist. */
    public const string ROW_COUNTED = 'COUNTED';

    public const string ROW_ZERO_COUNTED = 'ZERO_COUNTED';

    public const string ROW_REPLACEABLE = 'REPLACEABLE';

    public const string ROW_NOT_COUNTED = 'NOT_COUNTED';

    /** The product's zero count without a withdrawal. */
    private const string ZERO_COUNT = '(select c.id from onboarding_zero_counts c where c.product_id = p.id and not exists (select 1 from onboarding_zero_count_withdrawals w where w.zero_count_id = c.id) order by c.id desc limit 1)';

    /** The product's opening line whose OPENING no reversal cancelled. */
    private const string EFFECTIVE_LINE = '(select ol.id from opening_stock_lines ol join stock_movements om on om.opening_line_id = ol.id where ol.product_id = p.id and not exists (select 1 from reversals r where r.original_movement_id = om.id) order by ol.id desc limit 1)';

    /** The product's latest opening line. */
    private const string LATEST_LINE = '(select ol.id from opening_stock_lines ol where ol.product_id = p.id order by ol.id desc limit 1)';

    /** A movement of the product of another type than OPENING and REVERSAL. */
    private const string OTHER_MOVEMENT = "exists (select 1 from stock_movements o where o.product_id = p.id and o.movement_type not in ('OPENING', 'REVERSAL'))";

    /** The effective cost status of the evidence `e`: KNOWN after a correction, otherwise as posted. */
    private const string COST_STATUS = "case when e.id is null then null when exists (select 1 from cost_corrections k where k.evidence_id = e.id) then 'KNOWN' else e.cost_status end";

    private const string IS_COUNTED = '(f.zero_count_id is not null or f.line_id is not null)';

    private const string IS_NOT_COUNTED = '(f.active and f.zero_count_id is null and f.line_id is null and not f.other_movement)';

    private const string ON_LIST = '(f.zero_count_id is not null or f.line_id is not null or (f.active and not f.other_movement))';

    /**
     * One page of the checklist with this filter, after or before a row.
     *
     * @return array{rows: list<array<string, mixed>>, nextCursor: string|null, previousCursor: string|null}
     */
    public function page(?string $filter, ?string $after, ?string $before): array
    {
        $backwards = $before !== null;
        $cursor = $before ?? $after;
        $query = self::rows($filter);

        if ($cursor !== null) {
            $position = Keyset::decode($cursor, ['text', 'int']);
            $query->whereRaw($backwards ? '(f.name, f.id) < (?, ?)' : '(f.name, f.id) > (?, ?)', [Keyset::text($position, 0), Keyset::int($position, 1)]);
        }

        $direction = $backwards ? 'desc' : 'asc';
        $rows = $query->orderBy('f.name', $direction)->orderBy('f.id', $direction)->limit(self::PER_PAGE + 1)->get()->all();
        $more = count($rows) > self::PER_PAGE;
        $rows = array_slice($rows, 0, self::PER_PAGE);

        if ($backwards) {
            $rows = array_reverse($rows);
        }

        $data = [];

        foreach ($rows as $row) {
            $data[] = self::row((array) $row);
        }

        if ($data === []) {
            return ['rows' => [], 'nextCursor' => null, 'previousCursor' => null];
        }

        $first = self::position($data[0]);
        $last = self::position($data[count($data) - 1]);
        $hasNext = $backwards ? self::beyond($filter, $last, '>') : $more;
        $hasPrevious = $backwards ? $more : self::beyond($filter, $first, '<');

        return [
            'rows' => $data,
            'nextCursor' => $hasNext ? Keyset::encode($last) : null,
            'previousCursor' => $hasPrevious ? Keyset::encode($first) : null,
        ];
    }

    /**
     * The counts of the release review (UXI-289; BR-INV-28): the products counted, those still to be
     * counted, and the counted rows whose cost is still unknown.
     *
     * @return array{counted: int, notCounted: int, unknownCost: int}
     */
    public function counts(): array
    {
        $row = (array) self::joined()
            ->selectRaw('count(*) filter (where '.self::IS_COUNTED.') as counted')
            ->selectRaw('count(*) filter (where '.self::IS_NOT_COUNTED.') as not_counted')
            ->selectRaw("count(*) filter (where f.line_id is not null and (".self::COST_STATUS.") = 'UNKNOWN') as unknown_cost")
            ->first();

        return ['counted' => Read::int($row, 'counted'), 'notCounted' => Read::int($row, 'not_counted'), 'unknownCost' => Read::int($row, 'unknown_cost')];
    }

    /**
     * The facts of every product that decide its row, as `f`.
     */
    private static function facts(): Builder
    {
        return DB::table('products', 'p')
            ->select(['p.id', 'p.name', 'p.sku', 'p.base_unit', 'p.quantity_precision', 'p.active'])
            ->selectRaw(self::ZERO_COUNT.' as zero_count_id')
            ->selectRaw(self::EFFECTIVE_LINE.' as line_id')
            ->selectRaw(self::LATEST_LINE.' as latest_line_id')
            ->selectRaw(self::OTHER_MOVEMENT.' as other_movement');
    }

    /**
     * The facts with the effective OPENING's line `l`, its movement `m` and its evidence `e`.
     */
    private static function joined(): Builder
    {
        return DB::query()
            ->fromSub(self::facts(), 'f')
            ->leftJoin('opening_stock_lines as l', 'l.id', '=', 'f.line_id')
            ->leftJoin('stock_movements as m', 'm.opening_line_id', '=', 'l.id')
            ->leftJoin('acquisition_cost_evidence as e', 'e.movement_id', '=', 'm.id');
    }

    /**
     * The rows of the list with this filter, unordered.
     */
    private static function rows(?string $filter): Builder
    {
        $query = self::joined()
            ->select(['f.id', 'f.name', 'f.sku', 'f.base_unit', 'f.quantity_precision', 'f.active', 'f.zero_count_id', 'f.line_id', 'f.latest_line_id', 'l.quantity', 'm.id as movement_id'])
            ->selectRaw(self::COST_STATUS.' as cost_status');

        return $query->whereRaw(match ($filter) {
            self::COUNTED => self::IS_COUNTED,
            self::NOT_COUNTED => self::IS_NOT_COUNTED,
            default => self::ON_LIST,
        });
    }

    /**
     * Whether a row of the list lies beyond this position, in this direction of the order.
     *
     * @param  list<int|string>  $position
     */
    private static function beyond(?string $filter, array $position, string $direction): bool
    {
        return self::rows($filter)->whereRaw($direction === '>' ? '(f.name, f.id) > (?, ?)' : '(f.name, f.id) < (?, ?)', $position)->exists();
    }

    /**
     * @param  array<array-key, mixed>  $row
     * @return list<int|string>
     */
    private static function position(array $row): array
    {
        $product = $row['product'] ?? null;
        $product = is_array($product) ? $product : [];

        return [Read::text($product, 'name'), Read::int($product, 'id')];
    }

    /**
     * A row of the checklist: the product; its state; for a counted product its OPENING's quantity, cost
     * status and movement, for a zero count its key.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    private static function row(array $values): array
    {
        $precision = Read::int($values, 'quantity_precision');
        $zeroCount = Read::nullableInt($values, 'zero_count_id');
        $line = Read::nullableInt($values, 'line_id');
        $quantity = Read::nullableText($values, 'quantity');
        $state = match (true) {
            $zeroCount !== null => self::ROW_ZERO_COUNTED,
            $line !== null => self::ROW_COUNTED,
            Read::nullableInt($values, 'latest_line_id') !== null => self::ROW_REPLACEABLE,
            default => self::ROW_NOT_COUNTED,
        };
        $counted = $state === self::ROW_COUNTED;

        return [
            'product' => [
                'id' => Read::int($values, 'id'),
                'name' => Read::text($values, 'name'),
                'sku' => Read::text($values, 'sku'),
                'baseUnit' => Read::text($values, 'base_unit'),
                'quantityPrecision' => $precision,
                'active' => Read::bool($values, 'active'),
            ],
            'state' => $state,
            'quantity' => $counted && $quantity !== null ? Quantity::fromDatabase($quantity, $precision)->toCanonical() : null,
            'cost' => $counted ? Read::nullableText($values, 'cost_status') : null,
            'movementId' => $counted ? Read::nullableInt($values, 'movement_id') : null,
            'zeroCountId' => $zeroCount,
        ];
    }
}
