<?php

namespace App\Queries\Costing;

use App\Policies\NotFound;
use App\Policies\ObjectScope;
use App\Queries\Inventory\Column as Read;
use App\Support\Numbers\Cost;
use App\Support\Numbers\Quantity;
use Illuminate\Support\Facades\DB;

/**
 * RP-505, "Riwayat biaya" of one product (ROUTE_CONTRACTS RP-505; COPY_DECK section 10.6; BR-COST-06,
 * BR-COST-09, BR-COST-13, BR-COST-20, BR-COST-27, BR-COST-38; CAP-20, the Owner's alone; TASK-025
 * scope item 6): its current valuation — the inventory value and the stock, and the average cost,
 * one division of the two rounded once (ADR-008 section 5), none while the value is UNKNOWN or the
 * stock is zero — whether it carries an active mark, its cost sources and its corrections, the
 * newest MAX_ROWS of each, so that the page stays bounded; the older are reached through the stock
 * history (RP-402, RP-403). A product that does not exist is NOT_FOUND (TR-ERR-03).
 */
final class CostHistory
{
    /** The most cost sources, and the most corrections, that the page shows. */
    public const int MAX_ROWS = 100;

    private const string EVIDENCE_AT = "to_char(e.recorded_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as recorded_at";

    private const string SOURCE_AT = "to_char(e.recorded_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as source_recorded_at";

    private const string CORRECTED_AT = "to_char(c.recorded_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as corrected_at";

    private const string PRODUCT_MARKED = 'exists (select 1 from cost_incomplete_marks k where k.product_id = p.id and not exists (select 1 from cost_incomplete_mark_clearances x where x.mark_id = k.id)) as marked';

    /**
     * @return array{product: array{id: int, name: string, sku: string, baseUnit: string, quantityPrecision: int}, valuation: array{status: string, value: string|null, averageCost: string|null, onHand: string}|null, marked: bool, evidence: list<array<string, mixed>>, corrections: list<array<string, mixed>>}
     *
     * @throws NotFound
     */
    public function of(mixed $identifier): array
    {
        $key = ObjectScope::key($identifier);
        $row = $key === null ? null : DB::table('products as p')
            ->leftJoin('inventory_valuations as v', 'v.product_id', '=', 'p.id')
            ->leftJoin('stock_balances as b', 'b.product_id', '=', 'p.id')
            ->where('p.id', $key)
            ->select(['p.id', 'p.name', 'p.sku', 'p.base_unit', 'p.quantity_precision', 'v.value_status', 'v.value', 'b.on_hand'])
            ->selectRaw(self::PRODUCT_MARKED)
            ->first();

        if ($row === null) {
            throw new NotFound;
        }

        $values = (array) $row;
        $product = Read::int($values, 'id');
        $precision = Read::int($values, 'quantity_precision');

        return [
            'product' => ['id' => $product, 'name' => Read::text($values, 'name'), 'sku' => Read::text($values, 'sku'), 'baseUnit' => Read::text($values, 'base_unit'), 'quantityPrecision' => $precision],
            'valuation' => self::valuation($values, $precision),
            'marked' => Read::bool($values, 'marked'),
            'evidence' => self::evidence($product, $precision),
            'corrections' => self::corrections($product),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array{status: string, value: string|null, averageCost: string|null, onHand: string}|null
     */
    private static function valuation(array $values, int $precision): ?array
    {
        $status = Read::nullableText($values, 'value_status');

        if ($status === null) {
            return null;
        }

        $value = Cost::fromDatabase(Read::nullableText($values, 'value'), $status);
        $onHand = Quantity::fromDatabase(Read::nullableText($values, 'on_hand') ?? '0', $precision);
        $average = $value->isKnown() && $onHand->isPositive() ? Cost::quotient($value->amount(), $onHand->toBigDecimal())->toCanonical() : null;

        return [
            'status' => $value->isKnown() ? 'KNOWN' : 'UNKNOWN',
            'value' => $value->isKnown() ? $value->toCanonical() : null,
            'averageCost' => $average,
            'onHand' => $onHand->toCanonical(),
        ];
    }

    /**
     * The product's newest cost sources, each as it is now.
     *
     * @return list<array<string, mixed>>
     */
    private static function evidence(int $product, int $precision): array
    {
        $rows = DB::table('acquisition_cost_evidence as e')
            ->where('e.product_id', $product)
            ->orderByDesc('e.recorded_at')
            ->orderByDesc('e.id')
            ->limit(self::MAX_ROWS)
            ->select(['e.id', 'e.movement_id', 'e.movement_type', 'e.quantity', 'e.cost_status', 'e.total_amount', 'e.zero_cost_note'])
            ->selectRaw(self::EVIDENCE_AT)
            ->selectRaw(EffectiveCosts::CORRECTED_AMOUNT)
            ->selectRaw(EffectiveCosts::CORRECTED_NOTE)
            ->selectRaw(EffectiveCosts::CORRECTION_NO)
            ->selectRaw(EffectiveCosts::MARKED)
            ->get();
        $evidence = [];

        foreach ($rows as $row) {
            $values = (array) $row;
            $evidence[] = [
                'evidenceId' => Read::int($values, 'id'),
                'movementId' => Read::int($values, 'movement_id'),
                'sourceType' => Read::text($values, 'movement_type'),
                'recordedAt' => Read::text($values, 'recorded_at'),
                'quantity' => Quantity::fromDatabase(Read::text($values, 'quantity'), $precision)->toCanonical(),
                'cost' => EffectiveCosts::current($values),
                'zeroNote' => EffectiveCosts::zeroNote($values),
                'correctionNo' => EffectiveCosts::correctionNo($values),
                'marked' => Read::bool($values, 'marked'),
            ];
        }

        return $evidence;
    }

    /**
     * The product's newest corrections, each with its cost source.
     *
     * @return list<array<string, mixed>>
     */
    private static function corrections(int $product): array
    {
        $rows = DB::table('cost_corrections as c')
            ->join('acquisition_cost_evidence as e', 'e.id', '=', 'c.evidence_id')
            ->where('c.product_id', $product)
            ->orderByDesc('c.recorded_at')
            ->orderByDesc('c.id')
            ->limit(self::MAX_ROWS)
            ->select(['c.evidence_id', 'c.correction_no', 'c.old_status', 'c.old_amount', 'c.new_amount', 'c.zero_cost_note', 'c.reason', 'c.actor_display_name', 'e.movement_type'])
            ->selectRaw(self::CORRECTED_AT)
            ->selectRaw(self::SOURCE_AT)
            ->get();
        $corrections = [];

        foreach ($rows as $row) {
            $values = (array) $row;
            $old = Cost::fromDatabase(Read::nullableText($values, 'old_amount'), Read::text($values, 'old_status'));
            $corrections[] = [
                'evidenceId' => Read::int($values, 'evidence_id'),
                'correctionNo' => Read::int($values, 'correction_no'),
                'sourceType' => Read::text($values, 'movement_type'),
                'sourceRecordedAt' => Read::text($values, 'source_recorded_at'),
                'oldAmount' => $old->isKnown() ? $old->toCanonical() : null,
                'newAmount' => Cost::fromDatabase(Read::text($values, 'new_amount'))->toCanonical(),
                'zeroNote' => Read::nullableText($values, 'zero_cost_note'),
                'reason' => Read::text($values, 'reason'),
                'recordedAt' => Read::text($values, 'corrected_at'),
                'actorDisplayName' => Read::text($values, 'actor_display_name'),
            ];
        }

        return $corrections;
    }
}
