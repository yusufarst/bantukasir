<?php

namespace App\Queries\Inventory;

use App\Domain\Inventory\ReversalSource;
use App\Policies\NotFound;
use App\Policies\ObjectScope;
use App\Queries\Inventory\Column as Read;
use App\Support\Numbers\Quantity;
use Illuminate\Support\Facades\DB;

/**
 * The reversal of movements as the pages read it (ROUTE_CONTRACTS RP-403, RP-406, RP-409 and RP-411;
 * COPY_DECK sections 9.4 and 9.12; BR-INV-34 to BR-INV-36; CAP-14, CAP-17; TASK-026 scope item 4):
 * which movements are reversed, the links between a source and its REVERSAL, and RP-411's preview —
 * the source, its exact inverse and the stock now and after. No cost is read.
 */
final class Reversibility
{
    private const string RECORDED_AT = "to_char(m.recorded_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as recorded_at";

    /**
     * Those of these movements that a reversal cancelled.
     *
     * @param  list<int>  $movementIds
     * @return list<int>
     */
    public function reversedAmong(array $movementIds): array
    {
        if ($movementIds === []) {
            return [];
        }

        $ids = [];

        foreach (DB::table('reversals')->whereIn('original_movement_id', $movementIds)->pluck('original_movement_id') as $id) {
            if (is_int($id)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * The lines of a document, each with whether its movement is reversed, for "Batalkan mutasi" per
     * line (UXI-250, UXI-266), the Owner's.
     *
     * @param  array<array-key, array<string, mixed>>  $lines  each with its `movementId`
     * @return list<array<string, mixed>>
     */
    public function withReversed(array $lines): array
    {
        $ids = [];

        foreach ($lines as $line) {
            $id = $line['movementId'] ?? null;
            $ids[] = is_int($id) ? $id : 0;
        }

        $reversed = $this->reversedAmong($ids);

        return array_values(array_map(static fn (array $line): array => [...$line, 'reversed' => in_array($line['movementId'] ?? null, $reversed, true)], $lines));
    }

    /**
     * The links of UXP-403 (COPY_DECK section 9.4, `inventory.movement.reversed_by` and
     * `inventory.movement.reverses`): the REVERSAL that cancelled this movement, and the movement that
     * this REVERSAL cancels, each with its time.
     *
     * @return array{reversedBy: array{id: int, at: string}|null, reverses: array{id: int, at: string}|null}
     */
    public function links(int $movementId): array
    {
        $reversedBy = DB::table('reversals as r')->join('stock_movements as m', 'm.reversal_id', '=', 'r.id')->where('r.original_movement_id', $movementId)->select(['m.id'])->selectRaw(self::RECORDED_AT)->first();
        $reverses = DB::table('stock_movements as v')->join('reversals as r', 'r.id', '=', 'v.reversal_id')->join('stock_movements as m', 'm.id', '=', 'r.original_movement_id')->where('v.id', $movementId)->select(['m.id'])->selectRaw(self::RECORDED_AT)->first();

        return ['reversedBy' => self::link($reversedBy), 'reverses' => self::link($reverses)];
    }

    /**
     * RP-411: the movement to reverse — its type, product, signed quantity, time, and its document
     * for the way back — its exact inverse, the stock now and after, and whether it is already
     * reversed. A movement that does not exist, or whose type no reversal may cancel (BR-INV-36), is
     * NOT_FOUND (TR-ERR-03).
     *
     * @return array{source: array<string, mixed>}
     *
     * @throws NotFound
     */
    public function preview(mixed $identifier): array
    {
        $key = ObjectScope::key($identifier);
        $row = $key === null ? null : DB::table('stock_movements as m')
            ->join('products as p', 'p.id', '=', 'm.product_id')
            ->leftJoin('stock_balances as b', 'b.product_id', '=', 'm.product_id')
            ->leftJoin('receiving_lines as l', 'l.id', '=', 'm.receiving_line_id')
            ->leftJoin('manual_issue_lines as il', 'il.id', '=', 'm.manual_issue_line_id')
            ->where('m.id', $key)
            ->whereIn('m.movement_type', ReversalSource::ELIGIBLE)
            ->select(['m.id', 'm.product_id', 'm.movement_type', 'm.quantity', 'm.quantity_precision', 'm.base_unit', 'p.name', 'p.sku', 'b.on_hand', 'l.receiving_document_id', 'il.manual_issue_id'])
            ->selectRaw(self::RECORDED_AT)
            ->selectRaw('exists (select 1 from reversals r where r.original_movement_id = m.id) as reversed')
            ->first();

        if ($row === null) {
            throw new NotFound;
        }

        $values = (array) $row;
        $precision = Read::int($values, 'quantity_precision');
        $quantity = Quantity::fromDatabase(Read::text($values, 'quantity'), $precision);
        $onHand = Quantity::fromDatabase(Read::text($values, 'on_hand'), $precision);
        $document = Read::nullableInt($values, 'receiving_document_id') ?? Read::nullableInt($values, 'manual_issue_id');

        return [
            'source' => [
                'movementId' => Read::int($values, 'id'),
                'type' => Read::text($values, 'movement_type'),
                'quantity' => $quantity->toCanonical(),
                'inverse' => $quantity->negated()->toCanonical(),
                'baseUnit' => Read::text($values, 'base_unit'),
                'quantityPrecision' => $precision,
                'recordedAt' => Read::text($values, 'recorded_at'),
                'product' => ['id' => Read::int($values, 'product_id'), 'name' => Read::text($values, 'name'), 'sku' => Read::text($values, 'sku')],
                'documentId' => $document,
                'stockNow' => $onHand->toCanonical(),
                'stockAfter' => $onHand->plus($quantity->negated())->toCanonical(),
                'reversed' => Read::bool($values, 'reversed'),
            ],
        ];
    }

    /**
     * @return array{id: int, at: string}|null
     */
    private static function link(?object $row): ?array
    {
        if ($row === null) {
            return null;
        }

        $values = (array) $row;

        return ['id' => Read::int($values, 'id'), 'at' => Read::text($values, 'recorded_at')];
    }
}
