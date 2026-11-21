<?php

namespace App\Queries\Audit;

use App\Queries\Inventory\Column as Read;
use App\Queries\Inventory\Keyset;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The security log as Riwayat keamanan reads it (ROUTE_CONTRACTS RP-613; SECURITY_MODEL SEC-LOG-03,
 * SEC-LOG-05, CAP-32; SCHEMA section 5.4; INTERACTION_INVENTORY UXI-382 to UXI-384; TASK-042 scope item 2),
 * the Owner's alone. The entries, newest first, by time then internal identifier, with keyset paging both
 * ways, filtered by days of Asia/Jakarta, the event type and the user — the entries the account acted in or
 * that concern it, so that a refused sign-in to an account, whose actor is not yet signed in, is found by
 * that account. Each entry carries its event, its actor, the account it concerns, its outcome and its
 * client address, never its request identifier, route, a secret, a token or a password (SEC-LOG-03,
 * SEC-CH-08). The log freezes no name (SCHEMA section 3.5), so a user is shown by the account's name now:
 * after an anonymization, its neutral label (D-043).
 *
 * @phpstan-type Filters array{from: string|null, to: string|null, type: string|null, user: int|null, after: string|null, before: string|null, perPage: int}
 * @phpstan-type Row array{id: int, occurredAt: string, type: string, actor: array{kind: string, name: string|null}, subject: string|null, outcome: string, address: string|null}
 */
final class SecurityLogEntries
{
    public const int PER_PAGE = 25;

    private const string OCCURRED_AT = "to_char(s.occurred_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as occurred_at";

    /**
     * One page of entries.
     *
     * @param  Filters  $filters
     * @return array{rows: list<Row>, nextCursor: string|null, previousCursor: string|null}
     */
    public function list(array $filters): array
    {
        $backwards = $filters['before'] !== null;
        $cursor = $filters['before'] ?? $filters['after'];
        $query = self::scoped($filters)
            ->leftJoin('users as au', 'au.id', '=', 's.actor_user_id')
            ->leftJoin('users as su', 'su.id', '=', 's.subject_user_id')
            ->select(['s.id', 's.event_type', 's.actor_kind', 's.outcome_code', 'au.display_name as actor_name', 'su.display_name as subject_name'])
            ->selectRaw(self::OCCURRED_AT)
            ->selectRaw('host(s.client_address) as client_address');

        if ($cursor !== null) {
            self::beyond($query, $cursor, $backwards ? '>' : '<');
        }

        $direction = $backwards ? 'asc' : 'desc';
        $rows = $query->orderBy('s.occurred_at', $direction)->orderBy('s.id', $direction)->limit($filters['perPage'] + 1)->get()->all();
        $more = count($rows) > $filters['perPage'];
        $rows = array_slice($rows, 0, $filters['perPage']);

        if ($backwards) {
            $rows = array_reverse($rows);
        }

        $data = [];

        foreach ($rows as $row) {
            $values = (array) $row;
            $kind = Read::text($values, 'actor_kind');
            $data[] = [
                'id' => Read::int($values, 'id'),
                'occurredAt' => Read::text($values, 'occurred_at'),
                'type' => Read::text($values, 'event_type'),
                'actor' => ['kind' => $kind, 'name' => $kind === 'USER' ? Read::nullableText($values, 'actor_name') : null],
                'subject' => Read::nullableText($values, 'subject_name'),
                'outcome' => Read::text($values, 'outcome_code'),
                'address' => Read::nullableText($values, 'client_address'),
            ];
        }

        if ($data === []) {
            return ['rows' => [], 'nextCursor' => null, 'previousCursor' => null];
        }

        $first = [$data[0]['occurredAt'], $data[0]['id']];
        $last = [$data[count($data) - 1]['occurredAt'], $data[count($data) - 1]['id']];
        $hasNext = $backwards ? self::exists($filters, $last, '<') : $more;
        $hasPrevious = $backwards ? $more : self::exists($filters, $first, '>');

        return [
            'rows' => $data,
            'nextCursor' => $hasNext ? Keyset::encode($last) : null,
            'previousCursor' => $hasPrevious ? Keyset::encode($first) : null,
        ];
    }

    /**
     * The entries that the filters keep, as `s`.
     *
     * @param  Filters  $filters
     */
    private static function scoped(array $filters): Builder
    {
        $query = DB::table('security_log_entries as s');

        if ($filters['from'] !== null) {
            $query->whereRaw("s.occurred_at >= (?::date)::timestamp at time zone 'Asia/Jakarta'", [$filters['from']]);
        }

        if ($filters['to'] !== null) {
            $query->whereRaw("s.occurred_at < ((?::date) + 1)::timestamp at time zone 'Asia/Jakarta'", [$filters['to']]);
        }

        if ($filters['type'] !== null) {
            $query->where('s.event_type', $filters['type']);
        }

        if ($filters['user'] !== null) {
            $user = $filters['user'];
            $query->where(static function (Builder $inner) use ($user): void {
                $inner->where('s.actor_user_id', $user)->orWhere('s.subject_user_id', $user);
            });
        }

        return $query;
    }

    /**
     * Keeps the entries beyond the position: older with `<`, newer with `>`.
     */
    private static function beyond(Builder $query, string $cursor, string $direction): void
    {
        $position = Keyset::decode($cursor, ['instant', 'int']);
        $bindings = [Keyset::text($position, 0), Keyset::int($position, 1)];
        $direction === '<'
            ? $query->whereRaw('(s.occurred_at, s.id) < (?::timestamptz, ?)', $bindings)
            : $query->whereRaw('(s.occurred_at, s.id) > (?::timestamptz, ?)', $bindings);
    }

    /**
     * @param  Filters  $filters
     * @param  list<int|string>  $position
     */
    private static function exists(array $filters, array $position, string $direction): bool
    {
        $query = self::scoped($filters);
        self::beyond($query, Keyset::encode($position), $direction);

        return $query->exists();
    }
}
