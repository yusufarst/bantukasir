<?php

namespace App\Queries\Sales;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Models\User;
use App\Policies\NotFound;
use App\Policies\ObjectScope;
use App\Queries\Inventory\Column as Read;
use App\Queries\Inventory\Keyset;
use App\Support\Numbers\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The Sales for RP-201, UXP-201 "Penjualan", and RP-202, UXP-202 "Detail penjualan" (ROUTE_CONTRACTS
 * RP-201, RP-202; INTERACTION_CONTRACTS section 3.2; BR-DOC-06, BR-SALE-03; REQ-SALE-12; TASK-031 scope
 * item 4). The scope (SEC-AZ-03, SEC-AZ-06): a holder of CAP-21 reads only the Sales they made, the
 * Owner every Sale (CAP-22), applied in the query that reads the Sale, so that one outside the scope is
 * NOT_FOUND exactly like a missing one (TR-ERR-03). OPERATIONS_ADMIN has no access, which the routes'
 * gate refuses.
 *
 * RP-201 lists them newest first, by the commit's time, then internal identifier, with keyset paging
 * both ways (TR-PAGE-01, TR-PAGE-02): the number, the time, the total, the payment method and the
 * cashier, the user who made the Sale (BR-DOC-01). `from` and `to` are business dates of Asia/Jakarta
 * (G-18), `number` a Sale's number exactly, and the Owner's `cashier` and `register` the user who made
 * it and the register of its session. RP-202 reads one Sale: its lines as its receipt snapshot froze
 * them, one per product (D-067), its payment and its session. Never a cost or a profit (BR-COST-08).
 *
 * @phpstan-type SaleFilters array{from: string|null, to: string|null, number: string|null, cashier: int|null, register: int|null, after: string|null, before: string|null, perPage: int}
 * @phpstan-type SaleRow array{id: int, number: string, committedAt: string, total: string, method: string, cashier: string}
 */
final class SaleRecords
{
    public const int PER_PAGE = 25;

    public const int MAX_PER_PAGE = 100;

    /** The people whose Sales the Owner's filter offers, bounded as the account list is (RP-602). */
    public const int PEOPLE_BOUND = 500;

    /** The lines of a Sale at most (TECHNICAL_RULES section 11.2). */
    private const int MAX_LINES = 200;

    private const string COMMITTED_AT = "to_char(s.committed_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as committed_at";

    /** A cursor's shape: the commit's time and the key. */
    private const array CURSOR_SHAPE = ['instant', 'int'];

    /**
     * One page of the Sales in the viewer's scope (RP-201).
     *
     * @param  SaleFilters  $filters
     * @return array{rows: list<SaleRow>, nextCursor: string|null, previousCursor: string|null}
     */
    public function list(User $viewer, array $filters): array
    {
        $backwards = $filters['before'] !== null;
        $cursor = $filters['before'] ?? $filters['after'];
        $query = self::scoped($viewer, $filters)
            ->join('payments as p', 'p.sale_id', '=', 's.id')
            ->select(['s.id', 's.sale_number', 's.total_amount', 's.actor_display_name', 'p.method'])
            ->selectRaw(self::COMMITTED_AT);

        if ($cursor !== null) {
            self::beyond($query, $cursor, $backwards ? '>' : '<');
        }

        $direction = $backwards ? 'asc' : 'desc';
        $rows = $query->orderBy('s.committed_at', $direction)->orderBy('s.id', $direction)->limit($filters['perPage'] + 1)->get()->all();
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
                'number' => Read::text($values, 'sale_number'),
                'committedAt' => Read::text($values, 'committed_at'),
                'total' => self::money($values, 'total_amount'),
                'method' => Read::text($values, 'method'),
                'cashier' => Read::text($values, 'actor_display_name'),
            ];
        }

        if ($data === []) {
            return ['rows' => [], 'nextCursor' => null, 'previousCursor' => null];
        }

        $first = [$data[0]['committedAt'], $data[0]['id']];
        $last = [$data[count($data) - 1]['committedAt'], $data[count($data) - 1]['id']];
        $hasNext = $backwards ? self::exists($viewer, $filters, $last, '<') : $more;
        $hasPrevious = $backwards ? $more : self::exists($viewer, $filters, $first, '>');

        return [
            'rows' => $data,
            'nextCursor' => $hasNext ? Keyset::encode($last) : null,
            'previousCursor' => $hasPrevious ? Keyset::encode($first) : null,
        ];
    }

    /**
     * One Sale in the viewer's scope (RP-202): its number, time, cashier, customer, total and session;
     * its lines as its receipt snapshot froze them, in the order of the cart; its payment.
     *
     * @return array{sale: array{id: int, number: string, committedAt: string, cashier: string, customerName: string|null, total: string, sessionId: int, sessionNumber: string}, lines: list<array{lineNo: string, sku: string, name: string, unit: string, quantity: string, unitPrice: string, lineTotal: string}>, payment: array{method: string, tendered: string|null, change: string|null, reference: string|null}}
     *
     * @throws NotFound
     */
    public function detail(mixed $identifier, User $viewer): array
    {
        $key = ObjectScope::key($identifier);
        $row = $key === null ? null : self::scoped($viewer)
            ->join('payments as p', 'p.sale_id', '=', 's.id')
            ->join('receipt_snapshots as rs', 'rs.sale_id', '=', 's.id')
            ->where('s.id', $key)
            ->select([
                's.id', 's.sale_number', 's.actor_display_name', 's.customer_name', 's.total_amount', 's.cash_session_id', 'rs.cash_session_number', 'rs.lines',
                'p.method', 'p.tendered_amount', 'p.change_amount', 'p.reference',
            ])
            ->selectRaw(self::COMMITTED_AT)
            ->first();

        if ($row === null) {
            throw new NotFound;
        }

        $values = (array) $row;

        return [
            'sale' => [
                'id' => Read::int($values, 'id'),
                'number' => Read::text($values, 'sale_number'),
                'committedAt' => Read::text($values, 'committed_at'),
                'cashier' => Read::text($values, 'actor_display_name'),
                'customerName' => Read::nullableText($values, 'customer_name'),
                'total' => self::money($values, 'total_amount'),
                'sessionId' => Read::int($values, 'cash_session_id'),
                'sessionNumber' => Read::text($values, 'cash_session_number'),
            ],
            'lines' => self::lines(Read::text($values, 'lines')),
            'payment' => [
                'method' => Read::text($values, 'method'),
                'tendered' => ($values['tendered_amount'] ?? null) === null ? null : self::money($values, 'tendered_amount'),
                'change' => ($values['change_amount'] ?? null) === null ? null : self::money($values, 'change_amount'),
                'reference' => Read::nullableText($values, 'reference'),
            ],
        ];
    }

    /**
     * The people who made a Sale, for the Owner's filter "Kasir" (UXI-114): their current names, by
     * name, then internal identifier.
     *
     * @return list<array{id: int, displayName: string}>
     */
    public function cashiers(): array
    {
        $people = [];

        foreach (DB::table('users as u')
            ->whereIn('u.id', DB::table('sales')->select('actor_user_id'))
            ->orderBy('u.display_name')
            ->orderBy('u.id')
            ->limit(self::PEOPLE_BOUND)
            ->get(['u.id', 'u.display_name']) as $row) {
            $values = (array) $row;
            $people[] = ['id' => Read::int($values, 'id'), 'displayName' => Read::text($values, 'display_name')];
        }

        return $people;
    }

    /**
     * The Sales in the viewer's scope that the filters keep, as `s`.
     *
     * @param  SaleFilters|null  $filters
     */
    private static function scoped(User $viewer, ?array $filters = null): Builder
    {
        $query = DB::table('sales as s');

        // A holder of CAP-21 reaches the Sales they made; anyone else none, whom the gate refuses anyway.
        if (! Capabilities::allows($viewer, Capability::Cap22)) {
            $query->where('s.actor_user_id', Capabilities::allows($viewer, Capability::Cap21) ? $viewer->id : 0);
        }

        if ($filters === null) {
            return $query;
        }

        if ($filters['from'] !== null) {
            $query->where('s.business_date', '>=', $filters['from']);
        }

        if ($filters['to'] !== null) {
            $query->where('s.business_date', '<=', $filters['to']);
        }

        if ($filters['number'] !== null) {
            $query->where('s.sale_number', $filters['number']);
        }

        if ($filters['cashier'] !== null) {
            $query->where('s.actor_user_id', $filters['cashier']);
        }

        if ($filters['register'] !== null) {
            $query->whereIn('s.cash_session_id', DB::table('cash_sessions')->select('id')->where('register_id', $filters['register']));
        }

        return $query;
    }

    /**
     * Keeps the Sales beyond the position: older with `<`, newer with `>`. The cursor is read again
     * under the viewer's scope and never grants access (TR-PAGE-03); one not of this list is
     * VALIDATION_FAILED.
     */
    private static function beyond(Builder $query, string $cursor, string $direction): void
    {
        $position = Keyset::decode($cursor, self::CURSOR_SHAPE);
        $bindings = [Keyset::text($position, 0), Keyset::int($position, 1)];
        $direction === '<'
            ? $query->whereRaw('(s.committed_at, s.id) < (?::timestamptz, ?)', $bindings)
            : $query->whereRaw('(s.committed_at, s.id) > (?::timestamptz, ?)', $bindings);
    }

    /**
     * @param  SaleFilters  $filters
     * @param  array{string, int}  $position
     */
    private static function exists(User $viewer, array $filters, array $position, string $direction): bool
    {
        $query = self::scoped($viewer, $filters);
        self::beyond($query, Keyset::encode($position), $direction);

        return $query->exists();
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private static function money(array $values, string $name): string
    {
        $value = $values[$name] ?? null;

        if (! is_int($value) && ! is_string($value)) {
            throw new LogicException(sprintf('The column %s holds whole rupiah (SCHEMA section 5.9).', $name));
        }

        return Money::fromDatabase($value)->toCanonical();
    }

    /**
     * The frozen lines, in the order of the cart, every value a string (SCHEMA section 5.9); the refund
     * pages name a Sale's lines by them too (TASK-034).
     *
     * @return list<array{lineNo: string, sku: string, name: string, unit: string, quantity: string, unitPrice: string, lineTotal: string}>
     */
    public static function lines(string $json): array
    {
        $decoded = json_decode($json, true, 4, JSON_THROW_ON_ERROR);

        if (! is_array($decoded) || ! array_is_list($decoded) || count($decoded) > self::MAX_LINES) {
            throw new LogicException('A receipt holds its lines as a list (SCHEMA section 5.9).');
        }

        $lines = [];

        foreach ($decoded as $line) {
            $values = is_array($line) ? $line : [];
            $frozen = [];

            foreach (['lineNo', 'sku', 'name', 'unit', 'quantity', 'unitPrice', 'lineTotal'] as $field) {
                $value = $values[$field] ?? null;

                if (! is_string($value)) {
                    throw new LogicException('A frozen line holds every value as a string (SCHEMA section 5.9).');
                }

                $frozen[$field] = $value;
            }

            /** @var array{lineNo: string, sku: string, name: string, unit: string, quantity: string, unitPrice: string, lineTotal: string} $frozen */
            $lines[] = $frozen;
        }

        return $lines;
    }
}
