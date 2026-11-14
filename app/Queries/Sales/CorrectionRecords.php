<?php

namespace App\Queries\Sales;

use App\Domain\Sales\Corrections\SaleCorrections;
use App\Queries\Inventory\Column as Read;
use App\Support\Numbers\Money;
use App\Support\Numbers\Quantity;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The corrections of a Sale for the Owner's pages (ROUTE_CONTRACTS RP-202, RP-203, RP-204; COPY_DECK sections
 * 7.7 to 7.9; INTERACTION_INVENTORY UXI-118, UXI-120, UXI-122, UXI-133; SALES_CASH BR-RFD-03, BR-RFD-05 to
 * BR-RFD-07, BR-RFD-12; SCHEMA section 5.9; TASK-034 scope item 3), read only and never a cost. Their
 * controller lets only the Owner reach them (CAP-22, CAP-24) and checks that the Sale exists.
 *
 * - A Sale's lines, one per product (D-067), named as its receipt froze them, each with its product's
 *   precision, its quantity and line total, and what remains refundable and returnable on it: the line total
 *   less the money refunded, and the quantity issued less every return, saleable or not, from the running
 *   totals that the corrections keep (BR-RFD-03).
 * - Its corrections, in the order they were recorded, each with its reason, the Owner's frozen name, its
 *   refund's payout — the method, the source, the reference and, for a drawer-funded refund, the session and
 *   its register, as the session's report froze the register once CLOSED — and, line by line, the money
 *   refunded and the goods returned, saleable or not (BR-RFD-04, BR-RFD-05).
 *
 * @phpstan-type SaleLine array{saleLineId: int, lineNo: int, name: string, sku: string, unit: string, quantityPrecision: int, quantity: string, lineTotal: string, remainingAmount: string, remainingQuantity: string}
 * @phpstan-type Drawer array{sessionId: int, sessionNumber: string, registerCode: string, registerLabel: string}
 * @phpstan-type Refund array{amount: string, method: string, fundingSource: string|null, reference: string, drawer: Drawer|null}
 * @phpstan-type Entry array{saleLineId: int, lineNo: int, name: string, unit: string, quantityPrecision: int, refundAmount: string, returnedSaleable: string, returnedNotSaleable: string}
 * @phpstan-type Correction array{id: int, recordedAt: string, reason: string, actor: string, refund: Refund|null, lines: list<Entry>}
 */
final class CorrectionRecords
{
    /** The corrections of one Sale that RP-202 lists at most: each refunds money or returns goods within the Sale's bounds. */
    public const int MAX_CORRECTIONS = 500;

    private const string RECORDED_AT = "to_char(c.recorded_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as recorded_at";

    /**
     * The Sale's lines in the order of the cart, with their remaining bounds.
     *
     * @return list<SaleLine>
     */
    public function lines(int $saleId): array
    {
        $snapshot = DB::table('receipt_snapshots')->where('sale_id', $saleId)->value('lines');
        $frozen = [];

        // By line number: the snapshot holds it as a text of decimal digits (SCHEMA section 5.9).
        foreach (SaleRecords::lines(is_string($snapshot) ? $snapshot : throw new LogicException('A Sale has its receipt snapshot (SCHEMA section 5.9).')) as $line) {
            $frozen[(int) $line['lineNo']] = $line;
        }

        $lines = [];

        foreach (DB::table('sale_lines as l')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->leftJoin('sale_line_correction_totals as t', 't.sale_line_id', '=', 'l.id')
            ->where('l.sale_id', $saleId)
            ->orderBy('l.line_no')
            ->get(['l.id', 'l.line_no', 'l.quantity', 'l.line_total', 'p.quantity_precision', 't.refunded_amount', 't.returned_quantity']) as $row) {
            $values = (array) $row;
            $lineNo = Read::int($values, 'line_no');
            $precision = Read::int($values, 'quantity_precision');
            $names = $frozen[$lineNo] ?? throw new LogicException('A Sale line is frozen in its receipt (SCHEMA section 5.9).');
            $total = self::money($values['line_total'] ?? null);
            $issued = Quantity::fromDatabase(Read::text($values, 'quantity'), $precision);
            $refunded = ($values['refunded_amount'] ?? null) === null ? Money::zero() : self::money($values['refunded_amount']);
            $returned = ($values['returned_quantity'] ?? null) === null ? Quantity::of('0', $precision) : Quantity::fromDatabase(Read::text($values, 'returned_quantity'), $precision);

            $lines[] = [
                'saleLineId' => Read::int($values, 'id'),
                'lineNo' => $lineNo,
                'name' => $names['name'],
                'sku' => $names['sku'],
                'unit' => $names['unit'],
                'quantityPrecision' => $precision,
                'quantity' => $issued->toCanonical(),
                'lineTotal' => $total->toCanonical(),
                'remainingAmount' => $total->minus($refunded)->toCanonical(),
                'remainingQuantity' => $issued->minus($returned)->toCanonical(),
            ];
        }

        return $lines;
    }

    /**
     * Whether anything of the Sale remains to refund or to return (UXI-118; BR-RFD-03).
     *
     * @param  list<SaleLine>  $lines
     */
    public static function anyRemaining(array $lines): bool
    {
        foreach ($lines as $line) {
            if (Money::of($line['remainingAmount'])->isPositive() || Quantity::of($line['remainingQuantity'], $line['quantityPrecision'])->isPositive()) {
                return true;
            }
        }

        return false;
    }

    /**
     * The corrections of the Sale, in the order they were recorded, or the one named of them; its lines
     * named as the Sale's lines are.
     *
     * @param  list<SaleLine>  $saleLines
     * @return list<Correction>
     */
    public function corrections(int $saleId, array $saleLines, ?int $correctionId = null): array
    {
        $query = DB::table('sale_corrections as c')
            ->leftJoin('refunds as r', 'r.correction_id', '=', 'c.id')
            ->leftJoin('cash_sessions as s', 's.id', '=', 'r.cash_session_id')
            ->leftJoin('cash_registers as g', 'g.id', '=', 's.register_id')
            ->leftJoin('cash_session_reports as rep', 'rep.session_id', '=', 's.id')
            ->where('c.sale_id', $saleId);

        if ($correctionId !== null) {
            $query->where('c.id', $correctionId);
        }

        $rows = $query
            ->orderBy('c.recorded_at')
            ->orderBy('c.id')
            ->limit(self::MAX_CORRECTIONS)
            ->select(['c.id', 'c.reason', 'c.actor_display_name', 'r.id as refund_id', 'r.amount', 'r.payout_method', 'r.funding_source', 'r.payout_reference', 's.id as session_id', 's.session_number'])
            ->selectRaw('coalesce(rep.register_code, g.code) as register_code, coalesce(rep.register_label, g.label) as register_label')
            ->selectRaw(self::RECORDED_AT)
            ->get()
            ->all();

        $ids = [];
        $refundIds = [];

        foreach ($rows as $row) {
            $values = (array) $row;
            $ids[] = Read::int($values, 'id');
            $refund = Read::nullableInt($values, 'refund_id');

            if ($refund !== null) {
                $refundIds[] = $refund;
            }
        }

        [$money, $goods] = self::linesOf($ids, $refundIds);
        $named = [];

        foreach ($saleLines as $line) {
            $named[$line['saleLineId']] = $line;
        }

        $corrections = [];

        foreach ($rows as $row) {
            $values = (array) $row;
            $id = Read::int($values, 'id');
            $refundId = Read::nullableInt($values, 'refund_id');
            $entries = [];

            foreach ($named as $saleLine => $line) {
                $refunded = $refundId === null ? null : ($money[$refundId][$saleLine] ?? null);
                $saleable = $goods[$id][$saleLine][SaleCorrections::SALEABLE] ?? null;
                $notSaleable = $goods[$id][$saleLine][SaleCorrections::NOT_SALEABLE] ?? null;

                if ($refunded === null && $saleable === null && $notSaleable === null) {
                    continue;
                }

                $entries[] = [
                    'saleLineId' => $saleLine,
                    'lineNo' => $line['lineNo'],
                    'name' => $line['name'],
                    'unit' => $line['unit'],
                    'quantityPrecision' => $line['quantityPrecision'],
                    'refundAmount' => $refunded ?? '0',
                    'returnedSaleable' => $saleable === null ? '0' : Quantity::fromDatabase($saleable, $line['quantityPrecision'])->toCanonical(),
                    'returnedNotSaleable' => $notSaleable === null ? '0' : Quantity::fromDatabase($notSaleable, $line['quantityPrecision'])->toCanonical(),
                ];
            }

            $corrections[] = [
                'id' => $id,
                'recordedAt' => Read::text($values, 'recorded_at'),
                'reason' => Read::text($values, 'reason'),
                'actor' => Read::text($values, 'actor_display_name'),
                'refund' => $refundId === null ? null : [
                    'amount' => self::money($values['amount'] ?? null)->toCanonical(),
                    'method' => Read::text($values, 'payout_method'),
                    'fundingSource' => Read::nullableText($values, 'funding_source'),
                    'reference' => Read::text($values, 'payout_reference'),
                    'drawer' => Read::nullableInt($values, 'session_id') === null ? null : [
                        'sessionId' => Read::int($values, 'session_id'),
                        'sessionNumber' => Read::text($values, 'session_number'),
                        'registerCode' => Read::text($values, 'register_code'),
                        'registerLabel' => Read::text($values, 'register_label'),
                    ],
                ],
                'lines' => $entries,
            ];
        }

        return $corrections;
    }

    /**
     * The money refunded per refund and Sale line, and the quantities returned per correction, Sale line
     * and condition, as stored.
     *
     * @param  list<int>  $correctionIds
     * @param  list<int>  $refundIds
     * @return array{array<int, array<int, string>>, array<int, array<int, array<string, string>>>}
     */
    private static function linesOf(array $correctionIds, array $refundIds): array
    {
        $money = [];
        $goods = [];

        foreach ($refundIds === [] ? [] : DB::table('refund_lines')->whereIn('refund_id', $refundIds)->get(['refund_id', 'sale_line_id', 'amount']) as $row) {
            $values = (array) $row;
            $money[Read::int($values, 'refund_id')][Read::int($values, 'sale_line_id')] = self::money($values['amount'] ?? null)->toCanonical();
        }

        foreach ($correctionIds === [] ? [] : DB::table('return_lines')->whereIn('correction_id', $correctionIds)->get(['correction_id', 'sale_line_id', 'quantity', 'condition']) as $row) {
            $values = (array) $row;
            $goods[Read::int($values, 'correction_id')][Read::int($values, 'sale_line_id')][Read::text($values, 'condition')] = Read::text($values, 'quantity');
        }

        return [$money, $goods];
    }

    private static function money(mixed $value): Money
    {
        if (! is_int($value) && ! is_string($value)) {
            throw new LogicException('A correction holds its amounts as whole rupiah (SCHEMA section 5.9).');
        }

        return Money::fromDatabase($value);
    }
}
