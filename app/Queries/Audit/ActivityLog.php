<?php

namespace App\Queries\Audit;

use App\Queries\Inventory\Column as Read;
use App\Queries\Inventory\Keyset;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use JsonException;

/**
 * The business audit as Riwayat aktivitas reads it (ROUTE_CONTRACTS RP-612; BR-AUD-02 to BR-AUD-04;
 * SECURITY_MODEL SEC-LOG-01, SEC-LOG-02, CAP-32; SCHEMA section 5.4; INTERACTION_INVENTORY UXI-377 to
 * UXI-380; TASK-042 scope items 1 and 3). The audit log views are the Reporting module's (STACK_AND_MODULES
 * section 2), which reads every domain module and writes none.
 *
 * The events, newest first, by time then internal identifier, with keyset paging both ways (TR-PAGE-01 to
 * TR-PAGE-03), filtered by days of Asia/Jakarta, the acting user, the action code, the product, the cash
 * session (its number) and the reference — the event's own, or the number of the Sale it names, since a
 * checkout records its Sale by its key. Each row carries the actor as the event froze it (SCHEMA section 3.5,
 * G-16): a deactivated or anonymized user's events keep the name of that moment, and a combined account's
 * events name its one account (AC-042-02). Its reference leads to the object's page where one exists
 * (UXI-379), else it is text. The safe change data is shown to every reader in scope; the Owner-only data
 * (BR-AUD-02, D-047) only when the reader is the Owner, and a reader who is not sees no event of the
 * category SECURITY (BR-AUD-04, AC-042-04). RP-612 is the Owner's alone (CAP-32); the two guards keep the
 * read itself within BR-AUD-04 whoever calls it.
 *
 * @phpstan-type Filters array{from: string|null, to: string|null, user: int|null, action: string|null, product: int|null, session: string|null, reference: string|null, after: string|null, before: string|null, perPage: int}
 * @phpstan-type Reference array{text: string|null, kind: string|null, id: int|null}
 * @phpstan-type Row array{id: int, occurredAt: string, action: string, actor: array{kind: string, name: string|null}, reference: Reference, reason: string|null, changes: list<array{key: string, value: string}>}
 */
final class ActivityLog
{
    public const int PER_PAGE = 25;

    /** The accounts that the filter "Pengguna" offers at most: a shop has a handful. */
    public const int MAX_USERS = 500;

    private const string OCCURRED_AT = "to_char(a.occurred_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as occurred_at";

    /** The subjects whose records belong to a cash session, whose page is the session's (UXP-206). */
    private const array SESSION_SUBJECTS = ['cash_session', 'cash_session_close', 'cash_session_review_note', 'cash_session_report'];

    /**
     * One page of events.
     *
     * @param  Filters  $filters
     * @return array{rows: list<Row>, nextCursor: string|null, previousCursor: string|null}
     */
    public function list(array $filters, bool $owner): array
    {
        $backwards = $filters['before'] !== null;
        $cursor = $filters['before'] ?? $filters['after'];
        $columns = ['a.id', 'a.action', 'a.actor_kind', 'a.actor_display_name', 'a.subject_type', 'a.subject_id', 'a.product_id', 'a.cash_session_id', 'a.reference', 'a.reason', 'a.change_data', 'sc.sale_id as correction_sale_id', 'rv.original_movement_id as reversal_movement_id'];

        if ($owner) {
            $columns[] = 'a.owner_only_data';
        }

        $query = self::scoped($filters, $owner)
            ->leftJoin('sale_corrections as sc', static function (JoinClause $join): void {
                $join->on('sc.id', '=', 'a.subject_id')->where('a.subject_type', '=', 'sale_correction');
            })
            ->leftJoin('reversals as rv', static function (JoinClause $join): void {
                $join->on('rv.id', '=', 'a.subject_id')->where('a.subject_type', '=', 'reversal');
            })
            ->select($columns)
            ->selectRaw(self::OCCURRED_AT);

        if ($cursor !== null) {
            self::beyond($query, $cursor, $backwards ? '>' : '<');
        }

        $direction = $backwards ? 'asc' : 'desc';
        $rows = $query->orderBy('a.occurred_at', $direction)->orderBy('a.id', $direction)->limit($filters['perPage'] + 1)->get()->all();
        $more = count($rows) > $filters['perPage'];
        $rows = array_slice($rows, 0, $filters['perPage']);

        if ($backwards) {
            $rows = array_reverse($rows);
        }

        if ($rows === []) {
            return ['rows' => [], 'nextCursor' => null, 'previousCursor' => null];
        }

        $values = array_map(static fn (object $row): array => (array) $row, $rows);
        $names = self::names($values);
        $data = array_map(fn (array $row): array => $this->row($row, $names, $owner), $values);

        $first = [$data[0]['occurredAt'], $data[0]['id']];
        $last = [$data[count($data) - 1]['occurredAt'], $data[count($data) - 1]['id']];
        $hasNext = $backwards ? self::exists($filters, $owner, $last, '<') : $more;
        $hasPrevious = $backwards ? $more : self::exists($filters, $owner, $first, '>');

        return [
            'rows' => array_values($data),
            'nextCursor' => $hasNext ? Keyset::encode($last) : null,
            'previousCursor' => $hasPrevious ? Keyset::encode($first) : null,
        ];
    }

    /**
     * The accounts of the filter "Pengguna": every account, whatever its status, by its name now.
     *
     * @return list<array{id: int, displayName: string}>
     */
    public function users(): array
    {
        $rows = DB::table('users')->orderBy('display_name')->orderBy('id')->limit(self::MAX_USERS)->get(['id', 'display_name'])->all();

        return array_values(array_map(static function (object $row): array {
            $values = (array) $row;

            return ['id' => Read::int($values, 'id'), 'displayName' => Read::text($values, 'display_name')];
        }, $rows));
    }

    /**
     * The product of the filter "Produk", by its name now, or null when none has the key.
     *
     * @return array{id: int, name: string, sku: string}|null
     */
    public function product(?int $product): ?array
    {
        $row = $product === null ? null : DB::table('products')->where('id', $product)->first(['id', 'name', 'sku']);

        if ($row === null) {
            return null;
        }

        $values = (array) $row;

        return ['id' => Read::int($values, 'id'), 'name' => Read::text($values, 'name'), 'sku' => Read::text($values, 'sku')];
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  array{sale: array<int, string>, session: array<int, string>, product: array<int, string>, user: array<int, string>, receiving: array<int, string|null>}  $names
     * @return Row
     */
    private function row(array $values, array $names, bool $owner): array
    {
        $kind = Read::text($values, 'actor_kind');
        $changes = self::pairs(Read::text($values, 'change_data'));

        if ($owner) {
            $ownerOnly = Read::nullableText($values, 'owner_only_data');
            $changes = [...$changes, ...($ownerOnly === null ? [] : self::pairs($ownerOnly))];
        }

        return [
            'id' => Read::int($values, 'id'),
            'occurredAt' => Read::text($values, 'occurred_at'),
            'action' => Read::text($values, 'action'),
            'actor' => ['kind' => $kind, 'name' => $kind === 'USER' ? Read::text($values, 'actor_display_name') : null],
            'reference' => self::reference($values, $names),
            'reason' => Read::nullableText($values, 'reason'),
            'changes' => $changes,
        ];
    }

    /**
     * The reference of an event (UXI-379): the object's page — a Sale, a cash session, a product, a stock
     * movement, a receiving document, a manual issue or a user — with the object's number or name; an
     * object without a page is the event's reference as text, or the name of its product or session.
     *
     * @param  array<array-key, mixed>  $values
     * @param  array{sale: array<int, string>, session: array<int, string>, product: array<int, string>, user: array<int, string>, receiving: array<int, string|null>}  $names
     * @return Reference
     */
    private static function reference(array $values, array $names): array
    {
        $type = Read::nullableText($values, 'subject_type');
        $subject = Read::nullableInt($values, 'subject_id');
        $own = Read::nullableText($values, 'reference');
        $product = Read::nullableInt($values, 'product_id');
        $session = Read::nullableInt($values, 'cash_session_id');
        $correctedSale = Read::nullableInt($values, 'correction_sale_id');
        $reversed = Read::nullableInt($values, 'reversal_movement_id');
        $productName = $product === null ? null : ($names['product'][$product] ?? null);
        $sessionNumber = $session === null ? null : ($names['session'][$session] ?? null);

        return match (true) {
            $type === 'sale' && $subject !== null => self::link('sale', $subject, $own ?? $names['sale'][$subject] ?? null),
            $type === 'sale_correction' && $correctedSale !== null => self::link('sale', $correctedSale, $own ?? $names['sale'][$correctedSale] ?? null),
            $type === 'reversal' && $reversed !== null => self::link('movement', $reversed, $own ?? $productName),
            $type === 'receiving_document' && $subject !== null => self::link('receiving', $subject, $own ?? $names['receiving'][$subject] ?? null),
            $type === 'manual_issue' && $subject !== null => self::link('issue', $subject, $own ?? $productName),
            $type === 'user' && $subject !== null => self::link('user', $subject, $names['user'][$subject] ?? null),
            in_array($type, self::SESSION_SUBJECTS, true) && $session !== null => self::link('session', $session, $own ?? $sessionNumber),
            ($type === 'product' || $type === 'barcode_mapping') && $product !== null => self::link('product', $product, $productName ?? $own),
            default => ['text' => $own ?? $productName ?? $sessionNumber, 'kind' => null, 'id' => null],
        };
    }

    /**
     * @return Reference
     */
    private static function link(string $kind, int $id, ?string $text): array
    {
        return ['text' => $text, 'kind' => $kind, 'id' => $id];
    }

    /**
     * The numbers and names of the objects that one page of events names, each read once by its keys.
     *
     * @param  list<array<array-key, mixed>>  $rows
     * @return array{sale: array<int, string>, session: array<int, string>, product: array<int, string>, user: array<int, string>, receiving: array<int, string|null>}
     */
    private static function names(array $rows): array
    {
        $keys = ['sale' => [], 'session' => [], 'product' => [], 'user' => [], 'receiving' => []];

        foreach ($rows as $row) {
            $type = Read::nullableText($row, 'subject_type');
            $subject = Read::nullableInt($row, 'subject_id');
            $keys['product'][] = Read::nullableInt($row, 'product_id');
            $keys['session'][] = Read::nullableInt($row, 'cash_session_id');
            $keys['sale'][] = $type === 'sale' ? $subject : Read::nullableInt($row, 'correction_sale_id');
            $keys['user'][] = $type === 'user' ? $subject : null;
            $keys['receiving'][] = $type === 'receiving_document' ? $subject : null;
        }

        return [
            'sale' => self::texts(self::column('sales', 'sale_number', $keys['sale'])),
            'session' => self::texts(self::column('cash_sessions', 'session_number', $keys['session'])),
            'product' => self::texts(self::column('products', 'name', $keys['product'])),
            'user' => self::texts(self::column('users', 'display_name', $keys['user'])),
            'receiving' => self::column('receiving_documents', 'reference', $keys['receiving']),
        ];
    }

    /**
     * One text column of the rows of a table with these keys, by key; at most one page's worth.
     *
     * @param  list<int|null>  $keys
     * @return array<int, string|null>
     */
    private static function column(string $table, string $column, array $keys): array
    {
        $ids = [];

        foreach ($keys as $key) {
            if ($key !== null && ! in_array($key, $ids, true)) {
                $ids[] = $key;
            }
        }

        if ($ids === []) {
            return [];
        }

        $found = [];

        foreach (DB::table($table)->whereIn('id', $ids)->get(['id', $column])->all() as $row) {
            $values = (array) $row;
            $found[Read::int($values, 'id')] = Read::nullableText($values, $column);
        }

        return $found;
    }

    /**
     * @param  array<int, string|null>  $values
     * @return array<int, string>
     */
    private static function texts(array $values): array
    {
        $texts = [];

        foreach ($values as $key => $value) {
            if ($value !== null) {
                $texts[$key] = $value;
            }
        }

        return $texts;
    }

    /**
     * The change data of an event as pairs of a field and its value, in the order it was written: a text
     * as it is, any other value as JSON. Never a secret: the writer refuses one (BR-AUD-05).
     *
     * @return list<array{key: string, value: string}>
     */
    private static function pairs(string $json): array
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (! is_array($data)) {
            return [];
        }

        $pairs = [];

        foreach ($data as $key => $value) {
            $pairs[] = [
                'key' => (string) $key,
                'value' => is_string($value) ? $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
        }

        return $pairs;
    }

    /**
     * The events that the filters and the reader's scope keep, as `a`.
     *
     * @param  Filters  $filters
     */
    private static function scoped(array $filters, bool $owner): Builder
    {
        $query = DB::table('audit_events as a');

        if (! $owner) {
            $query->where('a.category', 'BUSINESS');
        }

        if ($filters['from'] !== null) {
            $query->whereRaw("a.occurred_at >= (?::date)::timestamp at time zone 'Asia/Jakarta'", [$filters['from']]);
        }

        if ($filters['to'] !== null) {
            $query->whereRaw("a.occurred_at < ((?::date) + 1)::timestamp at time zone 'Asia/Jakarta'", [$filters['to']]);
        }

        if ($filters['user'] !== null) {
            $query->where('a.actor_user_id', $filters['user']);
        }

        if ($filters['action'] !== null) {
            $query->where('a.action', $filters['action']);
        }

        if ($filters['product'] !== null) {
            $query->where('a.product_id', $filters['product']);
        }

        if ($filters['session'] !== null) {
            $session = DB::table('cash_sessions')->where('session_number', $filters['session'])->value('id');
            is_int($session) ? $query->where('a.cash_session_id', $session) : $query->whereRaw('false');
        }

        if ($filters['reference'] !== null) {
            $reference = $filters['reference'];
            $sale = DB::table('sales')->where('sale_number', $reference)->value('id');
            $query->where(static function (Builder $inner) use ($reference, $sale): void {
                $inner->where('a.reference', $reference);

                if (is_int($sale)) {
                    $inner->orWhere(static function (Builder $named) use ($sale): void {
                        $named->where('a.subject_type', 'sale')->where('a.subject_id', $sale);
                    });
                }
            });
        }

        return $query;
    }

    /**
     * Keeps the events beyond the position: older with `<`, newer with `>`.
     */
    private static function beyond(Builder $query, string $cursor, string $direction): void
    {
        $position = Keyset::decode($cursor, ['instant', 'int']);
        $bindings = [Keyset::text($position, 0), Keyset::int($position, 1)];
        $direction === '<'
            ? $query->whereRaw('(a.occurred_at, a.id) < (?::timestamptz, ?)', $bindings)
            : $query->whereRaw('(a.occurred_at, a.id) > (?::timestamptz, ?)', $bindings);
    }

    /**
     * @param  Filters  $filters
     * @param  list<int|string>  $position
     */
    private static function exists(array $filters, bool $owner, array $position, string $direction): bool
    {
        $query = self::scoped($filters, $owner);
        self::beyond($query, Keyset::encode($position), $direction);

        return $query->exists();
    }
}
