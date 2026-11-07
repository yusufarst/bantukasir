<?php

namespace App\Queries\Inventory;

use App\Policies\NotFound;
use App\Policies\ObjectScope;
use App\Queries\Catalog\ProductRows;
use App\Queries\Costing\EffectiveCosts;
use App\Queries\Inventory\Column as Read;
use App\Support\Numbers\Quantity;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The confirmed receiving documents (ROUTE_CONTRACTS RP-404, RP-406; BR-INV-12 to BR-INV-17;
 * D-033, D-047, D-069 (a); CAP-11, CAP-12; TASK-022 scope item 1). RP-404 lists them newest
 * first, by confirmation time, then internal identifier, with keyset paging both ways; `from`
 * and `to` are days of Asia/Jakarta, and `q` finds a reference or a product of a line by its
 * SKU or name, without regard to letter case (SCH-14). There is no draft: a document exists
 * only once it is confirmed. RP-406 reads one document and its lines, each with its movement
 * and its purchase cost, which the Owner, OPERATIONS_ADMIN and the combined account may see
 * (CAP-12); a zero's evidence or reason is the Owner's (CAP-20). Since TASK-025 the cost shown is
 * the current one, with the cost at confirmation and the Owner's mark after a correction (D-047),
 * and, for the Owner alone, the correction history.
 */
final class ReceivingDocuments
{
    public const int PER_PAGE = 25;

    public const int MAX_PER_PAGE = 100;

    /** The most lines of a document (TECHNICAL_RULES section 11.2), which RP-406 shows whole. */
    public const int MAX_LINES = 500;

    private const string CONFIRMED_AT = "to_char(d.confirmed_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as confirmed_at";

    /**
     * @param  array{from: string|null, to: string|null, q: string, after: string|null, before: string|null, perPage: int}  $filters
     * @return array{rows: list<array{id: int, confirmedAt: string, reference: string|null, lineCount: int, actorDisplayName: string}>, nextCursor: string|null, previousCursor: string|null}
     */
    public function list(array $filters): array
    {
        $backwards = $filters['before'] !== null;
        $cursor = $filters['before'] ?? $filters['after'];
        $query = self::scoped($filters)->select(['d.id', 'd.reference', 'd.line_count', 'd.actor_display_name'])->selectRaw(self::CONFIRMED_AT);

        if ($cursor !== null) {
            self::beyond($query, $cursor, $backwards ? '>' : '<');
        }

        $direction = $backwards ? 'asc' : 'desc';
        $rows = $query->orderBy('d.confirmed_at', $direction)->orderBy('d.id', $direction)->limit($filters['perPage'] + 1)->get()->all();
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
                'reference' => Read::nullableText($values, 'reference'),
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
     * One document with its lines, in their order, each with its purchase cost as it is now and as
     * it was at confirmation, and whether the Owner corrected or completed it (D-047; BR-INV-15;
     * TASK-025); `withZeroNotes` adds, for the Owner (CAP-20), a zero's evidence or reason, the cost
     * source and its number of corrections for "Koreksi biaya" (UXI-249), and its correction history
     * (UXI-247), which no other role sees.
     *
     * @return array{document: array{id: int, confirmedAt: string, reference: string|null, documentDate: string|null, lineCount: int, actorDisplayName: string}, lines: list<array<string, mixed>>}
     *
     * @throws NotFound
     */
    public function detail(mixed $identifier, bool $withZeroNotes): array
    {
        $key = ObjectScope::key($identifier);
        $row = $key === null ? null : DB::table('receiving_documents as d')
            ->where('d.id', $key)
            ->select(['d.id', 'd.reference', 'd.line_count', 'd.actor_display_name'])
            ->selectRaw(self::CONFIRMED_AT)
            ->selectRaw("to_char(d.document_date, 'YYYY-MM-DD') as document_date")
            ->first();

        if ($row === null) {
            throw new NotFound;
        }

        $values = (array) $row;
        $lines = DB::table('receiving_lines as l')
            ->join('stock_movements as m', 'm.receiving_line_id', '=', 'l.id')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->join('acquisition_cost_evidence as e', 'e.movement_id', '=', 'm.id')
            ->where('l.receiving_document_id', Read::int($values, 'id'))
            ->orderBy('l.line_no')
            ->limit(self::MAX_LINES)
            ->select(['l.line_no', 'l.product_id', 'p.name', 'p.sku', 'm.id as movement_id', 'm.quantity', 'm.quantity_precision', 'm.base_unit', 'e.id as evidence_id', 'e.cost_status', 'e.total_amount', 'e.zero_cost_note'])
            ->selectRaw(EffectiveCosts::CORRECTED_AMOUNT)
            ->selectRaw(EffectiveCosts::CORRECTED_NOTE)
            ->selectRaw(EffectiveCosts::CORRECTION_NO)
            ->get()
            ->all();
        $history = $withZeroNotes ? EffectiveCosts::history(array_values(array_map(static fn (object $line): int => Read::int((array) $line, 'evidence_id'), $lines))) : [];

        return [
            'document' => [
                'id' => Read::int($values, 'id'),
                'confirmedAt' => Read::text($values, 'confirmed_at'),
                'reference' => Read::nullableText($values, 'reference'),
                'documentDate' => Read::nullableText($values, 'document_date'),
                'lineCount' => Read::int($values, 'line_count'),
                'actorDisplayName' => Read::text($values, 'actor_display_name'),
            ],
            'lines' => array_values(array_map(static fn (object $line): array => self::line((array) $line, $withZeroNotes, $history), $lines)),
        ];
    }

    /**
     * A line with its purchase cost as it is now — the Owner's correction or completion, if any —
     * and at confirmation, both for every holder of CAP-12; the rest for the Owner alone.
     *
     * @param  array<array-key, mixed>  $values
     * @param  array<int, list<array<string, mixed>>>  $history
     * @return array<string, mixed>
     */
    private static function line(array $values, bool $withZeroNotes, array $history): array
    {
        $precision = Read::int($values, 'quantity_precision');
        $evidence = Read::int($values, 'evidence_id');
        $correctionNo = EffectiveCosts::correctionNo($values);
        $line = [
            'lineNo' => Read::int($values, 'line_no'),
            'product' => ['id' => Read::int($values, 'product_id'), 'name' => Read::text($values, 'name'), 'sku' => Read::text($values, 'sku')],
            'movementId' => Read::int($values, 'movement_id'),
            'quantity' => Quantity::fromDatabase(Read::text($values, 'quantity'), $precision)->toCanonical(),
            'baseUnit' => Read::text($values, 'base_unit'),
            'quantityPrecision' => $precision,
            'cost' => EffectiveCosts::current($values),
            'corrected' => $correctionNo > 0,
            'confirmedCost' => EffectiveCosts::confirmed($values),
        ];

        if ($withZeroNotes) {
            $line['zeroNote'] = EffectiveCosts::zeroNote($values);
            $line['evidenceId'] = $evidence;
            $line['correctionNo'] = $correctionNo;
            $line['corrections'] = $history[$evidence] ?? [];
        }

        return $line;
    }

    /**
     * The documents that the filters keep, as `d`.
     *
     * @param  array{from: string|null, to: string|null, q: string, after: string|null, before: string|null, perPage: int}  $filters
     */
    private static function scoped(array $filters): Builder
    {
        $query = DB::table('receiving_documents as d');

        if ($filters['from'] !== null) {
            $query->whereRaw("d.confirmed_at >= (?::date)::timestamp at time zone 'Asia/Jakarta'", [$filters['from']]);
        }

        if ($filters['to'] !== null) {
            $query->whereRaw("d.confirmed_at < ((?::date) + 1)::timestamp at time zone 'Asia/Jakarta'", [$filters['to']]);
        }

        if ($filters['q'] !== '') {
            $pattern = ProductRows::literal($filters['q']);

            $query->where(static function (Builder $match) use ($pattern): void {
                $match->whereRaw("lower(d.reference) like '%' || lower(?) || '%' escape '!'", [$pattern])
                    ->orWhereExists(static function (Builder $line) use ($pattern): void {
                        $line->selectRaw('1')
                            ->from('receiving_lines as ql')
                            ->join('products as qp', 'qp.id', '=', 'ql.product_id')
                            ->whereColumn('ql.receiving_document_id', 'd.id')
                            ->where(static function (Builder $product) use ($pattern): void {
                                $product->whereRaw("lower(qp.sku) like '%' || lower(?) || '%' escape '!'", [$pattern])
                                    ->orWhereRaw("lower(qp.name) like '%' || lower(?) || '%' escape '!'", [$pattern]);
                            });
                    });
            });
        }

        return $query;
    }

    /**
     * Keeps the documents beyond the position: older with `<`, newer with `>`.
     */
    private static function beyond(Builder $query, string $cursor, string $direction): void
    {
        $position = Keyset::decode($cursor, ['instant', 'int']);
        $bindings = [Keyset::text($position, 0), Keyset::int($position, 1)];
        $direction === '<'
            ? $query->whereRaw('(d.confirmed_at, d.id) < (?::timestamptz, ?)', $bindings)
            : $query->whereRaw('(d.confirmed_at, d.id) > (?::timestamptz, ?)', $bindings);
    }

    /**
     * @param  array{from: string|null, to: string|null, q: string, after: string|null, before: string|null, perPage: int}  $filters
     * @param  list<int|string>  $position
     */
    private static function exists(array $filters, array $position, string $direction): bool
    {
        $query = self::scoped($filters);
        self::beyond($query, Keyset::encode($position), $direction);

        return $query->exists();
    }
}
