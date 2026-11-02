<?php

namespace App\Queries\Inventory;

use App\Queries\Catalog\ProductRows;
use App\Queries\Inventory\Column as Read;
use App\Support\Numbers\Quantity;
use App\Support\Time\Clock;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * RD-02 and the first page of RP-401 (ROUTE_CONTRACTS RD-02, RP-401; INTERACTION_CONTRACTS
 * sections 3.2 and 3.3; BR-INV-40 to BR-INV-43; ARC-22; TASK-022 scope items 2 and 3): the
 * stock of the active products — product, SKU, on hand, minimum and state — with the numbers of
 * Habis and Menipis products and the as-of time, all from one statement over one snapshot, so
 * that the badge and the list agree (StockAttention). `q` finds a product by its active
 * barcode, its exact SKU, a SKU that starts with it or a name that holds it, without regard to
 * letter case through lower() in the database (SCH-14), as Product Search does; `state` and
 * `category` filter; the rows are in name order, then internal identifier, with the keyset
 * cursor `after` (TR-PAGE-01, TR-PAGE-02). No cost is ever read (CAP-14).
 */
final class StockList
{
    public const int PER_PAGE = 25;

    public const int MAX_PER_PAGE = 100;

    /** The states of INVENTORY section 10. */
    public const array STATES = ['OUT', 'LOW', 'NORMAL'];

    public function __construct(private readonly Clock $clock) {}

    /**
     * @return array{rows: list<array{id: int, name: string, sku: string, baseUnit: string, quantityPrecision: int, onHand: string, minimum: string, state: string}>, outCount: int, lowCount: int, asOf: string, nextCursor: string|null}
     */
    public function __invoke(string $q, ?string $state, ?int $category, ?string $after, int $perPage): array
    {
        $rows = StockAttention::products()
            ->select(['p.id', 'p.name', 'p.sku', 'p.base_unit', 'p.quantity_precision', 'p.minimum_stock', 'b.on_hand'])
            ->selectRaw(StockAttention::STATE.' as state');

        if ($q !== '') {
            $pattern = ProductRows::literal($q);

            $rows->where(static function (Builder $match) use ($q, $pattern): void {
                $match->whereRaw('p.id = '.ProductRows::TOKEN_PRODUCT, [$q])
                    ->orWhereRaw('lower(p.sku) = lower(?)', [$q])
                    ->orWhereRaw("lower(p.sku) like lower(?) || '%' escape '!'", [$pattern])
                    ->orWhereRaw("lower(p.name) like '%' || lower(?) || '%' escape '!'", [$pattern]);
            });
        }

        if ($state !== null) {
            $rows->whereRaw('('.StockAttention::STATE.') = ?', [$state]);
        }

        if ($category !== null) {
            $rows->where('p.category_id', $category);
        }

        if ($after !== null) {
            $position = Keyset::decode($after, ['text', 'int']);
            $rows->whereRaw('(p.name, p.id) > (?, ?)', [Keyset::text($position, 0), Keyset::int($position, 1)]);
        }

        $rows->orderBy('p.name')->orderBy('p.id')->limit($perPage + 1);

        // One statement: the counts of every active product, and beside them the page.
        $read = DB::query()
            ->fromSub(StockAttention::counts(), 'c')
            ->leftJoinLateral($rows, 'r')
            ->select(['c.out_count', 'c.low_count', 'r.id', 'r.name', 'r.sku', 'r.base_unit', 'r.quantity_precision', 'r.minimum_stock', 'r.on_hand', 'r.state'])
            ->orderBy('r.name')
            ->orderBy('r.id')
            ->get()
            ->all();

        $first = (array) ($read[0] ?? []);
        $page = [];

        foreach ($read as $row) {
            $values = (array) $row;

            if (($values['id'] ?? null) === null) {
                continue;
            }

            $precision = Read::int($values, 'quantity_precision');
            $onHand = Read::nullableText($values, 'on_hand');
            $page[] = [
                'id' => Read::int($values, 'id'),
                'name' => Read::text($values, 'name'),
                'sku' => Read::text($values, 'sku'),
                'baseUnit' => Read::text($values, 'base_unit'),
                'quantityPrecision' => $precision,
                'onHand' => ($onHand === null ? Quantity::of('0', $precision) : Quantity::fromDatabase($onHand, $precision))->toCanonical(),
                'minimum' => Quantity::fromDatabase(Read::text($values, 'minimum_stock'), $precision)->toCanonical(),
                'state' => Read::text($values, 'state'),
            ];
        }

        $more = count($page) > $perPage;
        $page = array_slice($page, 0, $perPage);
        $last = $page === [] ? null : $page[count($page) - 1];

        return [
            'rows' => $page,
            'outCount' => Read::int($first, 'out_count'),
            'lowCount' => Read::int($first, 'low_count'),
            'asOf' => $this->clock->now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'nextCursor' => $more && $last !== null ? Keyset::encode([$last['name'], $last['id']]) : null,
        ];
    }
}
