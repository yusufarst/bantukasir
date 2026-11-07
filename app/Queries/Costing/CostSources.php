<?php

namespace App\Queries\Costing;

use App\Policies\NotFound;
use App\Policies\ObjectScope;
use App\Queries\Inventory\Column as Read;
use App\Support\Numbers\Quantity;
use Illuminate\Support\Facades\DB;

/**
 * RP-504, one cost source for its correction or completion (ROUTE_CONTRACTS RP-504; COPY_DECK
 * section 10.5; BR-COST-02, BR-COST-20 to BR-COST-22; CAP-19; TASK-025 scope item 6): its product,
 * the kind, time and quantity of its movement, its value as it is now and as it was posted, the
 * number of its latest correction — which RC-15 expects as reviewed (G-15) — whether it carries an
 * active mark, and its correction history, newest first. A cost source that does not exist is
 * NOT_FOUND (TR-ERR-03).
 */
final class CostSources
{
    private const string RECORDED_AT = "to_char(e.recorded_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as recorded_at";

    /**
     * @return array{source: array<string, mixed>, corrections: list<array{correctionNo: int, oldAmount: string|null, newAmount: string, zeroNote: string|null, reason: string, recordedAt: string, actorDisplayName: string}>}
     *
     * @throws NotFound
     */
    public function detail(mixed $identifier): array
    {
        $key = ObjectScope::key($identifier);
        $row = $key === null ? null : DB::table('acquisition_cost_evidence as e')
            ->join('stock_movements as m', 'm.id', '=', 'e.movement_id')
            ->join('products as p', 'p.id', '=', 'e.product_id')
            ->where('e.id', $key)
            ->select(['e.id', 'e.product_id', 'p.name', 'p.sku', 'e.movement_id', 'e.movement_type', 'e.quantity', 'm.base_unit', 'm.quantity_precision', 'e.cost_status', 'e.total_amount', 'e.zero_cost_note'])
            ->selectRaw(self::RECORDED_AT)
            ->selectRaw(EffectiveCosts::CORRECTED_AMOUNT)
            ->selectRaw(EffectiveCosts::CORRECTED_NOTE)
            ->selectRaw(EffectiveCosts::CORRECTION_NO)
            ->selectRaw(EffectiveCosts::MARKED)
            ->first();

        if ($row === null) {
            throw new NotFound;
        }

        $values = (array) $row;
        $evidence = Read::int($values, 'id');
        $precision = Read::int($values, 'quantity_precision');

        return [
            'source' => [
                'evidenceId' => $evidence,
                'product' => ['id' => Read::int($values, 'product_id'), 'name' => Read::text($values, 'name'), 'sku' => Read::text($values, 'sku')],
                'movementId' => Read::int($values, 'movement_id'),
                'sourceType' => Read::text($values, 'movement_type'),
                'recordedAt' => Read::text($values, 'recorded_at'),
                'quantity' => Quantity::fromDatabase(Read::text($values, 'quantity'), $precision)->toCanonical(),
                'baseUnit' => Read::text($values, 'base_unit'),
                'quantityPrecision' => $precision,
                'cost' => EffectiveCosts::current($values),
                'zeroNote' => EffectiveCosts::zeroNote($values),
                'confirmedCost' => EffectiveCosts::confirmed($values),
                'correctionNo' => EffectiveCosts::correctionNo($values),
                'marked' => Read::bool($values, 'marked'),
            ],
            'corrections' => EffectiveCosts::history([$evidence])[$evidence] ?? [],
        ];
    }
}
