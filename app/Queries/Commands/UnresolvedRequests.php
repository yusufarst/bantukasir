<?php

namespace App\Queries\Commands;

use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\Operations;
use App\DTO\Commands\UnresolvedRequestData;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * RD-04, the request receipts that are not resolved — PENDING or HELD
 * (ROUTE_CONTRACTS RD-04; INTERACTION_CONTRACTS sections 3.2 and 3.6;
 * ADR-009 section 2; TASK-008 scope item 6): the viewer's own, or, for the
 * Owner with `scope=all`, every user's with the user's display name, which
 * this query reads from the Access module's accounts. Ordered by received
 * time, a page of at most 100 rows with the opaque keyset cursors of its
 * boundary rows, `nextCursor` and `previousCursor` (IC-03), which never widen
 * the scope (RT-08, TR-PAGE-03); never a payload.
 */
final class UnresolvedRequests
{
    /**
     * @return array{rows: list<array<string, mixed>>, nextCursor: string|null, previousCursor: string|null}
     */
    public function __invoke(User $viewer, bool $everyUser, ?string $after, ?string $before, int $perPage): array
    {
        $query = $this->scoped($viewer, $everyUser)->selectRaw(<<<'SQL'
            r.id, r.actor_user_id, r.operation, r.source_intent_id::text as source_intent_id,
            r.idempotency_key::text as idempotency_key, coalesce(r.recovery_epoch = e.epoch, false) as current_epoch,
            to_char(r.received_at at time zone 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') as received_at, r.outcome
            SQL);

        if ($before !== null) {
            [$receivedAt, $id] = self::decode($before);
            $query->whereRaw('(r.received_at, r.id) < (?::timestamptz, ?)', [$receivedAt, $id])->orderByDesc('r.received_at')->orderByDesc('r.id');
        } else {
            if ($after !== null) {
                [$receivedAt, $id] = self::decode($after);
                $query->whereRaw('(r.received_at, r.id) > (?::timestamptz, ?)', [$receivedAt, $id]);
            }

            $query->orderBy('r.received_at')->orderBy('r.id');
        }

        $rows = $query->limit($perPage + 1)->get()->all();
        $more = count($rows) > $perPage;
        $rows = array_slice($rows, 0, $perPage);

        if ($before !== null) {
            $rows = array_reverse($rows);
        }

        $names = $everyUser ? self::names($rows) : [];
        $data = [];
        $positions = [];

        foreach ($rows as $row) {
            $values = (array) $row;
            $operation = self::text($values['operation']);
            $actor = is_int($values['actor_user_id']) ? $values['actor_user_id'] : 0;
            $positions[] = [self::text($values['received_at']), is_int($values['id']) ? $values['id'] : 0];

            $data[] = (new UnresolvedRequestData(
                $operation,
                Operations::exists($operation) ? Operations::familyOf($operation) : 'unknown',
                self::text($values['source_intent_id']),
                self::text($values['idempotency_key']),
                $values['current_epoch'] === true ? 'current' : 'earlier',
                self::text($values['received_at']),
                self::text($values['outcome']),
                $actor,
                $names[$actor] ?? null,
            ))->toArray($everyUser);
        }

        if ($positions === []) {
            return ['rows' => [], 'nextCursor' => null, 'previousCursor' => null];
        }

        $first = $positions[0];
        $last = $positions[count($positions) - 1];
        $hasNext = $before !== null ? $this->exists($viewer, $everyUser, '>', $last) : $more;
        $hasPrevious = $before !== null ? $more : $this->exists($viewer, $everyUser, '<', $first);

        return [
            'rows' => $data,
            'nextCursor' => $hasNext ? self::encode($last) : null,
            'previousCursor' => $hasPrevious ? self::encode($first) : null,
        ];
    }

    private function scoped(User $viewer, bool $everyUser): Builder
    {
        return DB::table('command_request_receipts as r')
            ->leftJoin('recovery_epoch as e', 'e.id', '=', DB::raw('1'))
            ->whereIn('r.outcome', ['PENDING', 'HELD'])
            ->when(! $everyUser, static fn (Builder $query): Builder => $query->where('r.actor_user_id', $viewer->id));
    }

    /**
     * Whether an unresolved receipt in scope lies beyond the position.
     *
     * @param  array{string, int}  $position
     */
    private function exists(User $viewer, bool $everyUser, string $direction, array $position): bool
    {
        $query = $this->scoped($viewer, $everyUser);

        $direction === '>'
            ? $query->whereRaw('(r.received_at, r.id) > (?::timestamptz, ?)', $position)
            : $query->whereRaw('(r.received_at, r.id) < (?::timestamptz, ?)', $position);

        return $query->exists();
    }

    /**
     * @param  array{string, int}  $position
     */
    private static function encode(array $position): string
    {
        return rtrim(strtr(base64_encode(json_encode($position, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /**
     * @return array{string, int}
     */
    private static function decode(string $cursor): array
    {
        $json = base64_decode(strtr($cursor, '-_', '+/'), true);
        $position = is_string($json) ? json_decode($json, true, 2) : null;

        if (! is_array($position) || count($position) !== 2 || ! array_is_list($position)
            || ! is_string($position[0]) || preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{6}Z$/', $position[0]) !== 1
            || ! is_int($position[1]) || $position[1] < 1) {
            throw CommandRefused::of(ErrorCode::ValidationFailed);
        }

        return [$position[0], $position[1]];
    }

    /**
     * @param  list<object>  $rows
     * @return array<int, string>
     */
    private static function names(array $rows): array
    {
        $ids = array_values(array_unique(array_map(static fn (object $row): int => is_int($row->actor_user_id ?? null) ? $row->actor_user_id : 0, $rows)));
        $names = [];

        foreach (User::query()->whereIn('id', $ids)->get(['id', 'display_name']) as $user) {
            $names[$user->id] = $user->display_name;
        }

        return $names;
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
