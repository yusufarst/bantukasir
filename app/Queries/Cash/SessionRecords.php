<?php

namespace App\Queries\Cash;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Cash\Sessions;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Models\User;
use App\Policies\NotFound;
use App\Policies\ObjectScope;
use App\Support\Numbers\Money;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use JsonException;
use LogicException;

/**
 * The cash sessions for the pages of the Cash module (ROUTE_CONTRACTS RP-105, RP-205, RP-206;
 * INTERACTION_CONTRACTS section 3.2; SCHEMA section 5.10; BR-CASH-13, BR-CASH-19 to BR-CASH-21;
 * TASK-030 scope item 3). The scope (SEC-AZ-03, BR-CASH-21): a holder of CAP-21 reads only the
 * sessions they are responsible for, the Owner every session (CAP-22), each applied in the query that
 * reads the session, so that one outside the scope is NOT_FOUND exactly like a missing one
 * (TR-ERR-03). OPERATIONS_ADMIN has no access, which the routes' gate refuses.
 *
 * RP-205 lists them newest first, by opening time, then internal identifier, with keyset paging both
 * ways (TR-PAGE-01, TR-PAGE-02); `from` and `to` are days of Asia/Jakarta of the opening. A CLOSED
 * session shows its register as its report froze it (G-17), its close time and its variance; an OPEN
 * one its register as it is, which cannot change while it is OPEN (G-19). RP-206 reads one session
 * and, once CLOSED, its immutable report (BR-CASH-19); its running figures while OPEN come from the
 * Sales module's query, which the page's controller passes on (STK-10). RP-105 reads the viewer's
 * own OPEN session. Never a cost, a margin or a profit (BR-CASH-20).
 *
 * @phpstan-type SessionFilters array{status: string|null, from: string|null, to: string|null, register: int|null, responsible: int|null, after: string|null, before: string|null, perPage: int}
 * @phpstan-type SessionRow array{id: int, number: string, status: string, registerCode: string, registerLabel: string, responsible: string, openedAt: string, closedAt: string|null, variance: string|null}
 * @phpstan-type Figures array{openingCash: string, saleCount: int, saleValue: string, cashPayments: string, transferPayments: string, drawerRefunds: string, expectedCash: string}
 * @phpstan-type Count array{physicalCash: string, variance: string, closedBy: string, closeReason: string|null, notes: string|null}
 */
final class SessionRecords
{
    public const int PER_PAGE = 25;

    public const int MAX_PER_PAGE = 100;

    /** The rule of a cursor's parameter: base64url of the JSON of an instant and a key. */
    public const string CURSOR = 'regex:/^[A-Za-z0-9_-]{1,200}$/D';

    /** The people whose sessions the Owner's filter offers, bounded as the account list is (RP-602). */
    public const int PEOPLE_BOUND = 500;

    private const string OPENED_AT = "to_char(s.opened_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as opened_at";

    private const string CLOSED_AT = "to_char(c.closed_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as closed_at";

    /** An instant in UTC as these reads write one. */
    private const string INSTANT = '/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{6}Z$/D';

    private const string INSTANT_FORMAT = 'Y-m-d\TH:i:s.u\Z';

    /**
     * One page of the sessions in the viewer's scope (RP-205).
     *
     * @param  SessionFilters  $filters
     * @return array{rows: list<SessionRow>, nextCursor: string|null, previousCursor: string|null}
     *
     * @throws CommandRefused VALIDATION_FAILED for a cursor that is not one of this list
     */
    public function list(User $viewer, array $filters): array
    {
        $backwards = $filters['before'] !== null;
        $cursor = $filters['before'] ?? $filters['after'];
        $query = self::scoped($viewer, $filters)
            ->join('cash_registers as r', 'r.id', '=', 's.register_id')
            ->leftJoin('cash_session_closes as c', 'c.session_id', '=', 's.id')
            ->leftJoin('cash_session_reports as rep', 'rep.session_id', '=', 's.id')
            ->select(['s.id', 's.session_number', 's.status', 's.responsible_display_name', 'c.variance'])
            ->selectRaw('coalesce(rep.register_code, r.code) as register_code, coalesce(rep.register_label, r.label) as register_label')
            ->selectRaw(self::OPENED_AT)
            ->selectRaw(self::CLOSED_AT);

        if ($cursor !== null) {
            self::beyond($query, $cursor, $backwards ? '>' : '<');
        }

        $direction = $backwards ? 'asc' : 'desc';
        $rows = $query->orderBy('s.opened_at', $direction)->orderBy('s.id', $direction)->limit($filters['perPage'] + 1)->get()->all();
        $more = count($rows) > $filters['perPage'];
        $rows = array_slice($rows, 0, $filters['perPage']);

        if ($backwards) {
            $rows = array_reverse($rows);
        }

        $data = [];

        foreach ($rows as $row) {
            $values = (array) $row;
            $variance = $values['variance'] ?? null;
            $data[] = [
                'id' => self::int($values, 'id'),
                'number' => self::text($values, 'session_number'),
                'status' => self::text($values, 'status'),
                'registerCode' => self::text($values, 'register_code'),
                'registerLabel' => self::text($values, 'register_label'),
                'responsible' => self::text($values, 'responsible_display_name'),
                'openedAt' => self::text($values, 'opened_at'),
                'closedAt' => self::nullableText($values, 'closed_at'),
                'variance' => $variance === null ? null : self::money($variance),
            ];
        }

        if ($data === []) {
            return ['rows' => [], 'nextCursor' => null, 'previousCursor' => null];
        }

        $first = [$data[0]['openedAt'], $data[0]['id']];
        $last = [$data[count($data) - 1]['openedAt'], $data[count($data) - 1]['id']];
        $hasNext = $backwards ? self::exists($viewer, $filters, $last, '<') : $more;
        $hasPrevious = $backwards ? $more : self::exists($viewer, $filters, $first, '>');

        return [
            'rows' => $data,
            'nextCursor' => $hasNext ? self::encode($last) : null,
            'previousCursor' => $hasPrevious ? self::encode($first) : null,
        ];
    }

    /**
     * One session in the viewer's scope (RP-206): its identity and state, whether the viewer is its
     * responsible user, its opening cash, and, once CLOSED, the figures and the count of its report
     * as they were frozen (BR-CASH-19, BR-CASH-20).
     *
     * @return array{session: array{id: int, number: string, status: string, registerCode: string, registerLabel: string, responsible: string, openedAt: string, closedAt: string|null, own: bool}, openingCash: Money, report: array{figures: Figures, count: Count}|null}
     *
     * @throws NotFound
     */
    public function detail(mixed $identifier, User $viewer): array
    {
        $key = ObjectScope::key($identifier);
        $row = $key === null ? null : self::scoped($viewer)
            ->join('cash_registers as r', 'r.id', '=', 's.register_id')
            ->leftJoin('cash_session_closes as c', 'c.session_id', '=', 's.id')
            ->leftJoin('cash_session_reports as rep', 'rep.session_id', '=', 's.id')
            ->where('s.id', $key)
            ->select([
                's.id', 's.session_number', 's.status', 's.responsible_user_id', 's.responsible_display_name', 's.opening_cash', 'r.code', 'r.label',
                'rep.session_id as report_id', 'rep.register_code', 'rep.register_label', 'rep.completed_sale_count', 'rep.completed_sale_value', 'rep.cash_applied',
                'rep.bank_transfer_applied', 'rep.drawer_refund_total', 'rep.expected_cash', 'rep.physical_cash', 'rep.variance', 'rep.close_actor_display_name',
                'rep.close_reason', 'rep.notes',
            ])
            ->selectRaw(self::OPENED_AT)
            ->selectRaw(self::CLOSED_AT)
            ->first();

        if ($row === null) {
            throw new NotFound;
        }

        $values = (array) $row;
        $opening = Money::fromDatabase(self::amount($values, 'opening_cash'));
        $closed = ($values['report_id'] ?? null) !== null;
        $report = null;

        if ($closed) {
            $report = [
                'figures' => [
                    'openingCash' => $opening->toCanonical(),
                    'saleCount' => self::int($values, 'completed_sale_count'),
                    'saleValue' => self::money($values['completed_sale_value'] ?? null),
                    'cashPayments' => self::money($values['cash_applied'] ?? null),
                    'transferPayments' => self::money($values['bank_transfer_applied'] ?? null),
                    'drawerRefunds' => self::money($values['drawer_refund_total'] ?? null),
                    'expectedCash' => self::money($values['expected_cash'] ?? null),
                ],
                'count' => [
                    'physicalCash' => self::money($values['physical_cash'] ?? null),
                    'variance' => self::money($values['variance'] ?? null),
                    'closedBy' => self::text($values, 'close_actor_display_name'),
                    'closeReason' => self::nullableText($values, 'close_reason'),
                    'notes' => self::nullableText($values, 'notes'),
                ],
            ];
        }

        return [
            'session' => [
                'id' => self::int($values, 'id'),
                'number' => self::text($values, 'session_number'),
                'status' => self::text($values, 'status'),
                'registerCode' => self::text($values, $closed ? 'register_code' : 'code'),
                'registerLabel' => self::text($values, $closed ? 'register_label' : 'label'),
                'responsible' => self::text($values, 'responsible_display_name'),
                'openedAt' => self::text($values, 'opened_at'),
                'closedAt' => self::nullableText($values, 'closed_at'),
                'own' => self::int($values, 'responsible_user_id') === $viewer->id,
            ],
            'openingCash' => $opening,
            'report' => $report,
        ];
    }

    /**
     * One session in the viewer's scope for the Owner-assisted close (RP-207; TASK-032): its number and
     * state, its register as it is — it cannot change while the session is OPEN (G-19) — its responsible
     * user, the activity version that the close compares, and its opening cash. NOT_FOUND outside the
     * scope (TR-ERR-03).
     *
     * @return array{id: int, number: string, open: bool, registerCode: string, registerLabel: string, responsible: string, responsibleUserId: int, activityVersion: int, openingCash: Money}
     *
     * @throws NotFound
     */
    public function forAssistedClose(mixed $identifier, User $viewer): array
    {
        $key = ObjectScope::key($identifier);
        $row = $key === null ? null : self::scoped($viewer)
            ->join('cash_registers as r', 'r.id', '=', 's.register_id')
            ->where('s.id', $key)
            ->first(['s.id', 's.session_number', 's.status', 's.responsible_user_id', 's.responsible_display_name', 's.activity_version', 's.opening_cash', 'r.code', 'r.label']);

        if ($row === null) {
            throw new NotFound;
        }

        $values = (array) $row;

        return [
            'id' => self::int($values, 'id'),
            'number' => self::text($values, 'session_number'),
            'open' => self::text($values, 'status') === Sessions::OPEN,
            'registerCode' => self::text($values, 'code'),
            'registerLabel' => self::text($values, 'label'),
            'responsible' => self::text($values, 'responsible_display_name'),
            'responsibleUserId' => self::int($values, 'responsible_user_id'),
            'activityVersion' => self::int($values, 'activity_version'),
            'openingCash' => Money::fromDatabase(self::amount($values, 'opening_cash')),
        ];
    }

    /**
     * The viewer's own OPEN session for its close preview (RP-105), or null: its number and register,
     * the activity version that the close compares, and its opening cash.
     *
     * @return array{id: int, number: string, registerCode: string, registerLabel: string, activityVersion: int, openingCash: Money}|null
     */
    public function openOf(int $userId): ?array
    {
        $row = DB::table('cash_sessions as s')
            ->join('cash_registers as r', 'r.id', '=', 's.register_id')
            ->where('s.responsible_user_id', $userId)
            ->where('s.status', Sessions::OPEN)
            ->first(['s.id', 's.session_number', 's.activity_version', 's.opening_cash', 'r.code', 'r.label']);

        if ($row === null) {
            return null;
        }

        $values = (array) $row;

        return [
            'id' => self::int($values, 'id'),
            'number' => self::text($values, 'session_number'),
            'registerCode' => self::text($values, 'code'),
            'registerLabel' => self::text($values, 'label'),
            'activityVersion' => self::int($values, 'activity_version'),
            'openingCash' => Money::fromDatabase(self::amount($values, 'opening_cash')),
        ];
    }

    /**
     * The people responsible for any session, for the Owner's filter "Penanggung jawab" (UXI-136):
     * their current names, by name, then internal identifier.
     *
     * @return list<array{id: int, displayName: string}>
     */
    public function responsibles(): array
    {
        $people = [];

        foreach (DB::table('users as u')
            ->whereIn('u.id', DB::table('cash_sessions')->select('responsible_user_id'))
            ->orderBy('u.display_name')
            ->orderBy('u.id')
            ->limit(self::PEOPLE_BOUND)
            ->get(['u.id', 'u.display_name']) as $row) {
            $values = (array) $row;
            $people[] = ['id' => self::int($values, 'id'), 'displayName' => self::text($values, 'display_name')];
        }

        return $people;
    }

    /**
     * The sessions in the viewer's scope that the filters keep, as `s`.
     *
     * @param  SessionFilters|null  $filters
     */
    private static function scoped(User $viewer, ?array $filters = null): Builder
    {
        $query = DB::table('cash_sessions as s');

        if (! Capabilities::allows($viewer, Capability::Cap22)) {
            $query->where('s.responsible_user_id', $viewer->id);
        }

        if ($filters === null) {
            return $query;
        }

        if ($filters['status'] !== null) {
            $query->where('s.status', $filters['status']);
        }

        if ($filters['from'] !== null) {
            $query->whereRaw("s.opened_at >= (?::date)::timestamp at time zone 'Asia/Jakarta'", [$filters['from']]);
        }

        if ($filters['to'] !== null) {
            $query->whereRaw("s.opened_at < ((?::date) + 1)::timestamp at time zone 'Asia/Jakarta'", [$filters['to']]);
        }

        if ($filters['register'] !== null) {
            $query->where('s.register_id', $filters['register']);
        }

        if ($filters['responsible'] !== null) {
            $query->where('s.responsible_user_id', $filters['responsible']);
        }

        return $query;
    }

    /**
     * Keeps the sessions beyond the position: older with `<`, newer with `>`.
     */
    private static function beyond(Builder $query, string $cursor, string $direction): void
    {
        $bindings = self::decode($cursor);
        $direction === '<'
            ? $query->whereRaw('(s.opened_at, s.id) < (?::timestamptz, ?)', $bindings)
            : $query->whereRaw('(s.opened_at, s.id) > (?::timestamptz, ?)', $bindings);
    }

    /**
     * @param  SessionFilters  $filters
     * @param  array{string, int}  $position
     */
    private static function exists(User $viewer, array $filters, array $position, string $direction): bool
    {
        $query = self::scoped($viewer, $filters);
        self::beyond($query, self::encode($position), $direction);

        return $query->exists();
    }

    /**
     * The opaque cursor of a boundary row: base64url of the JSON of its opening time and key.
     *
     * @param  array{string, int}  $position
     */
    private static function encode(array $position): string
    {
        return rtrim(strtr(base64_encode(json_encode($position, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
    }

    /**
     * The position of a cursor, read again under the viewer's scope, never granting access
     * (TR-PAGE-03): an instant of the calendar written as these reads write one, and a key of at least
     * 1. Anything else — a day that does not exist among them — is VALIDATION_FAILED, never a server
     * error.
     *
     * @return array{string, int}
     *
     * @throws CommandRefused VALIDATION_FAILED
     */
    private static function decode(string $cursor): array
    {
        $json = base64_decode(strtr($cursor, '-_', '+/'), true);

        try {
            $position = is_string($json) ? json_decode($json, true, 2, JSON_THROW_ON_ERROR) : null;
        } catch (JsonException) {
            $position = null;
        }

        $instant = is_array($position) && array_is_list($position) && count($position) === 2 ? $position[0] : null;
        $key = is_array($position) && array_is_list($position) && count($position) === 2 ? $position[1] : null;

        if (! is_string($instant) || ! is_int($key) || $key < 1 || preg_match(self::INSTANT, $instant) !== 1) {
            throw CommandRefused::of(ErrorCode::ValidationFailed);
        }

        $read = DateTimeImmutable::createFromFormat('!'.self::INSTANT_FORMAT, $instant, new DateTimeZone('UTC'));

        if ($read === false || $read->format(self::INSTANT_FORMAT) !== $instant || $read->format('Y') === '0000') {
            throw CommandRefused::of(ErrorCode::ValidationFailed);
        }

        return [$instant, $key];
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private static function int(array $values, string $name): int
    {
        $value = $values[$name] ?? null;

        return is_int($value) ? $value : throw new LogicException(sprintf('The column %s holds a whole number (SCHEMA section 5.10).', $name));
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private static function text(array $values, string $name): string
    {
        $value = $values[$name] ?? null;

        return is_string($value) ? $value : throw new LogicException(sprintf('The column %s holds a text (SCHEMA section 5.10).', $name));
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private static function nullableText(array $values, string $name): ?string
    {
        return ($values[$name] ?? null) === null ? null : self::text($values, $name);
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private static function amount(array $values, string $name): int|string
    {
        $value = $values[$name] ?? null;

        return is_int($value) || is_string($value) ? $value : throw new LogicException(sprintf('The column %s holds whole rupiah (SCHEMA section 5.10).', $name));
    }

    /** A stored amount as the pages receive one: its canonical string of whole rupiah. */
    private static function money(mixed $value): string
    {
        if (! is_int($value) && ! is_string($value)) {
            throw new LogicException('A session holds its amounts as whole rupiah (SCHEMA section 5.10).');
        }

        return Money::fromDatabase($value)->toCanonical();
    }
}
