<?php

namespace App\Queries\Inventory;

use App\Policies\NotFound;
use App\Policies\ObjectScope;
use App\Queries\Inventory\Column as Read;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The reads of the reconciliation incidents (ROUTE_CONTRACTS RP-414, RP-415 and RD-03; INTERACTION_CONTRACTS
 * section 3.2; SCHEMA section 5.7; BR-INV-45, BR-INV-46; CAP-18; TASK-036 scope item 3), the Owner's
 * alone. An incident is a posting block: open — "Mutasi diblokir" — while it has no reopening, and
 * reopened once it has one. RP-414 lists them newest first, with the product, the time it was found and
 * its state, paged forward by a keyset cursor (TR-PAGE-01 to TR-PAGE-03); RP-415 reads one, with what did
 * not match — the block's detail code — and the blocked product; RD-03 counts the open ones. No cost and
 * no business data beyond the product's name and SKU.
 */
final class Incidents
{
    public const int PER_PAGE = 25;

    public const string BLOCKED = 'BLOCKED';

    public const string REOPENED = 'REOPENED';

    /** The states of UXI-301 (COPY_DECK section 9.15). */
    public const array STATES = [self::BLOCKED, self::REOPENED];

    private const string DETECTED_AT = "to_char(b.blocked_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as detected_at";

    private const string REOPENED_AT = "to_char(r.reopened_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as reopened_at";

    /**
     * One page of the incidents, newest first, of one state or of both.
     *
     * @param  array{status: string|null, after: string|null, perPage: int}  $filters
     * @return array{rows: list<array{id: int, detectedAt: string, status: string, product: array{id: int, name: string}}>, nextCursor: string|null, previousCursor: null}
     */
    public function list(array $filters): array
    {
        $query = self::scoped($filters['status'])
            ->select(['b.id', 'p.id as product_id', 'p.name as product_name'])
            ->selectRaw(self::DETECTED_AT)
            ->selectRaw('r.block_id is not null as reopened');

        if ($filters['after'] !== null) {
            $position = Keyset::decode($filters['after'], ['instant', 'int']);
            $query->whereRaw('(b.blocked_at, b.id) < (?::timestamptz, ?)', [Keyset::text($position, 0), Keyset::int($position, 1)]);
        }

        $rows = $query->orderByDesc('b.blocked_at')->orderByDesc('b.id')->limit($filters['perPage'] + 1)->get()->all();
        $more = count($rows) > $filters['perPage'];
        $data = [];

        foreach (array_slice($rows, 0, $filters['perPage']) as $row) {
            $values = (array) $row;
            $data[] = [
                'id' => Read::int($values, 'id'),
                'detectedAt' => Read::text($values, 'detected_at'),
                'status' => Read::bool($values, 'reopened') ? self::REOPENED : self::BLOCKED,
                'product' => ['id' => Read::int($values, 'product_id'), 'name' => Read::text($values, 'product_name')],
            ];
        }

        $last = $data === [] ? null : $data[count($data) - 1];

        return ['rows' => $data, 'nextCursor' => $more && $last !== null ? Keyset::encode([$last['detectedAt'], $last['id']]) : null, 'previousCursor' => null];
    }

    /**
     * One incident: when it was found, what did not match, the blocked product and, once reopened,
     * when.
     *
     * @return array{id: int, detectedAt: string, detailCode: string, status: string, reopenedAt: string|null, product: array{id: int, name: string, sku: string}}
     *
     * @throws NotFound
     */
    public function detail(mixed $identifier): array
    {
        $key = ObjectScope::key($identifier);
        $row = $key === null ? null : self::scoped(null)
            ->where('b.id', $key)
            ->select(['b.id', 'b.detail_code', 'p.id as product_id', 'p.name as product_name', 'p.sku as product_sku'])
            ->selectRaw(self::DETECTED_AT)
            ->selectRaw(self::REOPENED_AT)
            ->first();

        if ($row === null) {
            throw new NotFound;
        }

        $values = (array) $row;
        $reopenedAt = Read::nullableText($values, 'reopened_at');

        return [
            'id' => Read::int($values, 'id'),
            'detectedAt' => Read::text($values, 'detected_at'),
            'detailCode' => Read::text($values, 'detail_code'),
            'status' => $reopenedAt === null ? self::BLOCKED : self::REOPENED,
            'reopenedAt' => $reopenedAt,
            'product' => ['id' => Read::int($values, 'product_id'), 'name' => Read::text($values, 'product_name'), 'sku' => Read::text($values, 'product_sku')],
        ];
    }

    /**
     * The number of open incidents: the blocks without a reopening (RD-03; UXI-017).
     */
    public function openCount(): int
    {
        return self::scoped(self::BLOCKED)->count();
    }

    /**
     * The blocks as `b`, with their products as `p` and their reopenings as `r`, of one state or of both.
     */
    private static function scoped(?string $status): Builder
    {
        $query = DB::table('inventory_posting_blocks as b')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->leftJoin('inventory_posting_block_reopenings as r', 'r.block_id', '=', 'b.id');

        return match ($status) {
            self::BLOCKED => $query->whereNull('r.block_id'),
            self::REOPENED => $query->whereNotNull('r.block_id'),
            default => $query,
        };
    }
}
