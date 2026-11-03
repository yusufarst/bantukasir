<?php

namespace App\Queries\Inventory;

use App\Policies\NotFound;
use App\Policies\ObjectScope;
use App\Queries\Inventory\Column as Read;
use App\Support\Numbers\Cost;
use App\Support\Numbers\Quantity;
use Illuminate\Support\Facades\DB;

/**
 * RP-403, one movement (ROUTE_CONTRACTS RP-403; BR-INV-02, BR-INV-44; CAP-14, CAP-20; TASK-022
 * scope item 2): its type, product, signed quantity, server time, who recorded it, its reference
 * and its source — a receiving document, or a manual issue with its reason (TASK-023) — read in
 * one statement; a movement is the whole shop's, so a missing one is the
 * only NOT_FOUND (TR-ERR-03). The cost evidence of an OPENING, a RECEIPT or a positive ADJUSTMENT
 * is the Owner's alone (CAP-20; UXI-225), read only when the controller asks for it. Its
 * corrections come with TASK-025, which adds them here.
 */
final class MovementDetail
{
    private const string RECORDED_AT = "to_char(m.recorded_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as recorded_at";

    private const string DOCUMENT_AT = "to_char(d.confirmed_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as document_at";

    private const string ISSUE_AT = "to_char(i.confirmed_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as issue_at";

    /**
     * The movement; a MANUAL_ISSUE's source is its issue, and its reason that of the issue
     * (BR-INV-19; TASK-023).
     *
     * @return array{id: int, type: string, quantity: string, baseUnit: string, quantityPrecision: int, recordedAt: string, product: array{id: int, name: string, sku: string}, actorDisplayName: string, reference: string|null, reasonCode: string|null, source: array{kind: string, id: int, at: string}|null}
     *
     * @throws NotFound
     */
    public function movement(mixed $identifier): array
    {
        $key = ObjectScope::key($identifier);
        $row = $key === null ? null : DB::table('stock_movements as m')
            ->join('products as p', 'p.id', '=', 'm.product_id')
            ->join('users as u', 'u.id', '=', 'm.actor_user_id')
            ->leftJoin('receiving_lines as l', 'l.id', '=', 'm.receiving_line_id')
            ->leftJoin('receiving_documents as d', 'd.id', '=', 'l.receiving_document_id')
            ->leftJoin('manual_issue_lines as il', 'il.id', '=', 'm.manual_issue_line_id')
            ->leftJoin('manual_issues as i', 'i.id', '=', 'il.manual_issue_id')
            ->where('m.id', $key)
            ->select(['m.id', 'm.product_id', 'm.movement_type', 'm.quantity', 'm.quantity_precision', 'm.base_unit', 'p.name', 'p.sku', 'u.display_name', 'd.id as document_id', 'd.reference', 'i.id as issue_id', 'i.reason_code'])
            ->selectRaw(self::RECORDED_AT)
            ->selectRaw(self::DOCUMENT_AT)
            ->selectRaw(self::ISSUE_AT)
            ->first();

        if ($row === null) {
            throw new NotFound;
        }

        $values = (array) $row;
        $precision = Read::int($values, 'quantity_precision');
        $document = Read::nullableInt($values, 'document_id');
        $issue = Read::nullableInt($values, 'issue_id');

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
                $document !== null => ['kind' => 'RECEIVING', 'id' => $document, 'at' => Read::text($values, 'document_at')],
                $issue !== null => ['kind' => 'MANUAL_ISSUE', 'id' => $issue, 'at' => Read::text($values, 'issue_at')],
                default => null,
            },
        ];
    }

    /**
     * The movement's acquisition cost evidence, for the Owner (CAP-20): its status — KNOWN, a zero
     * with its evidence or reason, or UNKNOWN — and its total; null for a movement that carries
     * none (COSTING BR-COST-02 to BR-COST-04).
     *
     * @return array{status: string, total: string|null, zeroNote: string|null}|null
     */
    public function evidence(int $movementId): ?array
    {
        $row = DB::table('acquisition_cost_evidence')
            ->where('movement_id', $movementId)
            ->first(['cost_status', 'total_amount', 'zero_cost_note']);

        if ($row === null) {
            return null;
        }

        $values = (array) $row;
        $status = Read::text($values, 'cost_status');
        $amount = Read::nullableText($values, 'total_amount');
        $cost = Cost::fromDatabase($amount, $status);

        return [
            'status' => $status,
            'total' => $cost->isKnown() ? $cost->toCanonical() : null,
            'zeroNote' => Read::nullableText($values, 'zero_cost_note'),
        ];
    }
}
