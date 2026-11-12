<?php

namespace App\Queries\Inventory;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Models\User;
use App\Policies\NotFound;
use App\Policies\ObjectScope;
use App\Queries\Costing\EffectiveCosts;
use App\Queries\Inventory\Column as Read;
use App\Support\Numbers\Quantity;
use Illuminate\Support\Facades\DB;

/**
 * RP-403, one movement (ROUTE_CONTRACTS RP-403; BR-INV-02, BR-INV-44; CAP-14, CAP-20; TASK-022
 * scope item 2): its type, product, signed quantity, server time, who recorded it, its reference
 * and its source — a receiving document, or a manual issue with its reason (TASK-023) — read in
 * one statement; a movement is the whole shop's, so a missing one is the
 * only NOT_FOUND (TR-ERR-03). The cost evidence of an OPENING, a RECEIPT or a positive ADJUSTMENT
 * is the Owner's alone (CAP-20; UXI-225), read only when the controller asks for it, as it is
 * after the Owner's corrections (TASK-025).
 */
final class MovementDetail
{
    private const string RECORDED_AT = "to_char(m.recorded_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as recorded_at";

    private const string DOCUMENT_AT = "to_char(d.confirmed_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as document_at";

    private const string ISSUE_AT = "to_char(i.confirmed_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as issue_at";

    private const string SALE_AT = "to_char(s.committed_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as sale_at";

    /**
     * The movement; a MANUAL_ISSUE's source is its issue, and its reason that of the issue
     * (BR-INV-19; TASK-023). A SALE_ISSUE's source is its Sale with its number (UXI-224; TASK-031),
     * whose key leads to RP-202 only for a viewer in the Sale's scope — the Owner (CAP-22), or the
     * Sale's own cashier with CAP-21 — and is null for anyone else, for whom the source is its text
     * without a link (SEC-AZ-03). Every source carries its number, null but for a Sale's.
     *
     * @return array{id: int, type: string, quantity: string, baseUnit: string, quantityPrecision: int, recordedAt: string, product: array{id: int, name: string, sku: string}, actorDisplayName: string, reference: string|null, reasonCode: string|null, source: array{kind: string, id: int|null, at: string, number: string|null}|null}
     *
     * @throws NotFound
     */
    public function movement(mixed $identifier, User $viewer): array
    {
        $key = ObjectScope::key($identifier);
        $row = $key === null ? null : DB::table('stock_movements as m')
            ->join('products as p', 'p.id', '=', 'm.product_id')
            ->join('users as u', 'u.id', '=', 'm.actor_user_id')
            ->leftJoin('receiving_lines as l', 'l.id', '=', 'm.receiving_line_id')
            ->leftJoin('receiving_documents as d', 'd.id', '=', 'l.receiving_document_id')
            ->leftJoin('manual_issue_lines as il', 'il.id', '=', 'm.manual_issue_line_id')
            ->leftJoin('manual_issues as i', 'i.id', '=', 'il.manual_issue_id')
            ->leftJoin('sale_lines as sl', 'sl.id', '=', 'm.sale_line_id')
            ->leftJoin('sales as s', 's.id', '=', 'sl.sale_id')
            ->where('m.id', $key)
            ->select([
                'm.id', 'm.product_id', 'm.movement_type', 'm.quantity', 'm.quantity_precision', 'm.base_unit', 'p.name', 'p.sku', 'u.display_name', 'd.id as document_id', 'd.reference',
                'i.id as issue_id', 'i.reason_code', 's.id as sale_id', 's.sale_number', 's.actor_user_id as sale_actor_id',
            ])
            ->selectRaw(self::RECORDED_AT)
            ->selectRaw(self::DOCUMENT_AT)
            ->selectRaw(self::ISSUE_AT)
            ->selectRaw(self::SALE_AT)
            ->first();

        if ($row === null) {
            throw new NotFound;
        }

        $values = (array) $row;
        $precision = Read::int($values, 'quantity_precision');
        $document = Read::nullableInt($values, 'document_id');
        $issue = Read::nullableInt($values, 'issue_id');
        $sale = Read::nullableInt($values, 'sale_id');
        $ownSale = $sale !== null && Read::int($values, 'sale_actor_id') === $viewer->id && Capabilities::allows($viewer, Capability::Cap21);
        $linkedSale = $sale !== null && ($ownSale || Capabilities::allows($viewer, Capability::Cap22)) ? $sale : null;

        return [
            'id' => Read::int($values, 'id'),
            'type' => Read::text($values, 'movement_type'),
            'quantity' => Quantity::fromDatabase(Read::text($values, 'quantity'), $precision)->toCanonical(),
            'baseUnit' => Read::text($values, 'base_unit'),
            'quantityPrecision' => $precision,
            'recordedAt' => Read::text($values, 'recorded_at'),
            'product' => ['id' => Read::int($values, 'product_id'), 'name' => Read::text($values, 'name'), 'sku' => Read::text($values, 'sku')],
            'actorDisplayName' => Read::text($values, 'display_name'),
            'reference' => Read::nullableText($values, 'reference'),
            'reasonCode' => Read::nullableText($values, 'reason_code'),
            'source' => match (true) {
                $document !== null => ['kind' => 'RECEIVING', 'id' => $document, 'at' => Read::text($values, 'document_at'), 'number' => null],
                $issue !== null => ['kind' => 'MANUAL_ISSUE', 'id' => $issue, 'at' => Read::text($values, 'issue_at'), 'number' => null],
                $sale !== null => ['kind' => 'SALE', 'id' => $linkedSale, 'at' => Read::text($values, 'sale_at'), 'number' => Read::text($values, 'sale_number')],
                default => null,
            },
        ];
    }

    /**
     * The movement's acquisition cost evidence, for the Owner (CAP-20): its status — KNOWN, a zero
     * with its evidence or reason, or UNKNOWN — and its total, both as they are now, after the
     * Owner's corrections, with how many there were and the cost source for "Koreksi biaya"
     * (UXI-225, UXI-226; TASK-025); null for a movement that carries none (COSTING BR-COST-02 to
     * BR-COST-04, BR-COST-20).
     *
     * @return array{evidenceId: int, status: string, total: string|null, zeroNote: string|null, corrections: int}|null
     */
    public function evidence(int $movementId): ?array
    {
        $row = DB::table('acquisition_cost_evidence as e')
            ->where('e.movement_id', $movementId)
            ->select(['e.id', 'e.cost_status', 'e.total_amount', 'e.zero_cost_note'])
            ->selectRaw(EffectiveCosts::CORRECTED_AMOUNT)
            ->selectRaw(EffectiveCosts::CORRECTED_NOTE)
            ->selectRaw(EffectiveCosts::CORRECTION_NO)
            ->first();

        if ($row === null) {
            return null;
        }

        $values = (array) $row;
        $current = EffectiveCosts::current($values);

        return [
            'evidenceId' => Read::int($values, 'id'),
            'status' => $current['status'],
            'total' => $current['amount'],
            'zeroNote' => EffectiveCosts::zeroNote($values),
            'corrections' => EffectiveCosts::correctionNo($values),
        ];
    }
}
