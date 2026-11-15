<?php

namespace App\Domain\Inventory\Onboarding;

use App\Support\Numbers\Quantity;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The Owner's onboarding entries, in the Inventory module (SCHEMA section 5.7, `opening_stock_entries`,
 * `opening_stock_lines`, `onboarding_zero_counts`, `onboarding_zero_count_withdrawals`; BR-INV-25 to
 * BR-INV-27, BR-INV-47, BR-INV-48; D-087), the only writer of them (STK-12). An entry exists only once
 * it is confirmed, with its positive rows as lines, each posting its OPENING, and its zero rows as zero
 * counts on the checklist, which post nothing. Every correction before the release only adds: a zero
 * count's withdrawal, and, after the reversal of an OPENING, one explicit replacement line naming the
 * line it replaces (D-087). Nothing is updated or removed. It opens no transaction of its own: the
 * Action owns the transaction and the locks (TR-TX-01).
 *
 * Which entry is effective for a product — at most one (G-22) — is read here by entriesOf(), which the
 * Actions call under the product's guard, every writer of these tables, of the product's movements and
 * of their reversals holding it, and which the pages call without a lock.
 */
final class OpeningStock
{
    /**
     * The facts that decide each product's entry, in one statement: its latest zero count without a
     * withdrawal; its OPENING without a reversal, with its line; its latest opening line with that
     * line's OPENING; and whether a movement of another type than OPENING and REVERSAL exists.
     */
    private const string ENTRIES = <<<'SQL'
        select p.id as product_id,
            z.id as zero_count_id,
            e.line_id as effective_line_id, e.movement_id as effective_movement_id,
            l.line_id as latest_line_id, l.movement_id as latest_movement_id,
            exists (select 1 from stock_movements o where o.product_id = p.id and o.movement_type not in ('OPENING', 'REVERSAL')) as other_movement
        from products p
        left join lateral (
            select c.id from onboarding_zero_counts c
            where c.product_id = p.id and not exists (select 1 from onboarding_zero_count_withdrawals w where w.zero_count_id = c.id)
            order by c.id desc limit 1
        ) z on true
        left join lateral (
            select ol.id as line_id, om.id as movement_id
            from opening_stock_lines ol join stock_movements om on om.opening_line_id = ol.id
            where ol.product_id = p.id and not exists (select 1 from reversals r where r.original_movement_id = om.id)
            order by ol.id desc limit 1
        ) e on true
        left join lateral (
            select ol.id as line_id, om.id as movement_id
            from opening_stock_lines ol join stock_movements om on om.opening_line_id = ol.id
            where ol.product_id = p.id
            order by ol.id desc limit 1
        ) l on true
        where p.id = any(?::bigint[])
        SQL;

    /**
     * Adds a confirmed entry of this many rows, positive and zero, with its actor's name frozen
     * (SCHEMA section 3.5), and returns its internal key.
     */
    public function insertEntry(int $rowCount, int $actorUserId, string $actorDisplayName, int $commandId, string $at): int
    {
        self::requireTransaction();

        return DB::table('opening_stock_entries')->insertGetId([
            'row_count' => $rowCount,
            'confirmed_at' => $at,
            'actor_user_id' => $actorUserId,
            'actor_display_name' => $actorDisplayName,
            'command_id' => $commandId,
        ]);
    }

    /**
     * Adds the line of a positive row, which must post its OPENING in this transaction: the deferred
     * key refuses a commit without it. A replacement names the reversed line it replaces, with that
     * line's OPENING and the Owner's reason (D-087). It returns the line's internal key.
     */
    public function insertLine(int $entryId, int $rowNo, int $productId, Quantity $quantity, string $countReference, ?OpeningLine $replaces = null, ?string $reason = null): int
    {
        self::requireTransaction();

        if (! $quantity->isPositive()) {
            throw new LogicException('An opening line puts a positive quantity into stock (BR-INV-25).');
        }

        if (($replaces === null) !== ($reason === null) || ($replaces !== null && $replaces->productId !== $productId)) {
            throw new LogicException('A replacement names the reversed line of its own product, with its reason (D-087).');
        }

        return DB::table('opening_stock_lines')->insertGetId([
            'opening_stock_entry_id' => $entryId,
            'row_no' => $rowNo,
            'product_id' => $productId,
            'quantity' => $quantity->toCanonical(),
            'count_reference' => $countReference,
            'replaces_line_id' => $replaces?->id,
            'replaces_movement_id' => $replaces?->movementId,
            'replacement_reason' => $reason,
        ]);
    }

    /**
     * Adds the zero count of a product on the checklist, which posts no movement (BR-INV-27), with
     * its count reference if the Owner gave one, and returns its internal key.
     */
    public function insertZeroCount(int $productId, ?string $countReference, int $actorUserId, string $actorDisplayName, int $commandId, string $at): int
    {
        self::requireTransaction();

        return DB::table('onboarding_zero_counts')->insertGetId([
            'product_id' => $productId,
            'count_reference' => $countReference,
            'recorded_at' => $at,
            'actor_user_id' => $actorUserId,
            'actor_display_name' => $actorDisplayName,
            'command_id' => $commandId,
        ]);
    }

    /**
     * Adds the withdrawal of a zero count: the zero count stays, and its product may be counted
     * again (BR-INV-27; D-087). Its primary key refuses a second withdrawal of it.
     */
    public function withdraw(ZeroCount $zeroCount, string $reason, int $actorUserId, string $actorDisplayName, int $commandId, string $at): void
    {
        self::requireTransaction();

        DB::table('onboarding_zero_count_withdrawals')->insert([
            'zero_count_id' => $zeroCount->id,
            'product_id' => $zeroCount->productId,
            'reason' => $reason,
            'recorded_at' => $at,
            'actor_user_id' => $actorUserId,
            'actor_display_name' => $actorDisplayName,
            'command_id' => $commandId,
        ]);
    }

    /**
     * The entry of each of these products that exists, by its key (G-22, G-24; INTERACTION_CONTRACTS
     * section 3.1).
     *
     * @param  list<int>  $productIds
     * @return array<int, OnboardingEntry>
     */
    public function entriesOf(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $entries = [];

        foreach (DB::select(self::ENTRIES, ['{'.implode(',', array_map(strval(...), $productIds)).'}']) as $row) {
            $values = (array) $row;
            $product = $values['product_id'] ?? null;
            $other = $values['other_movement'] ?? null;

            if (! is_int($product) || ! is_bool($other)) {
                throw new LogicException('A product has its onboarding facts.');
            }

            $entries[$product] = OnboardingEntry::decide(
                $product,
                self::key($values, 'zero_count_id'),
                self::key($values, 'effective_line_id'),
                self::key($values, 'effective_movement_id'),
                self::key($values, 'latest_line_id'),
                self::key($values, 'latest_movement_id'),
                $other,
            );
        }

        return $entries;
    }

    /**
     * A zero count with its product and whether it is withdrawn, or null when it does not exist. A read
     * of what may change between two calls: the Actions read it again under the product's guard.
     *
     * @phpstan-impure
     */
    public function zeroCount(int $id): ?ZeroCount
    {
        $row = DB::table('onboarding_zero_counts as z')
            ->where('z.id', $id)
            ->select(['z.id', 'z.product_id'])
            ->selectRaw('exists (select 1 from onboarding_zero_count_withdrawals w where w.zero_count_id = z.id) as withdrawn')
            ->first();

        if ($row === null) {
            return null;
        }

        $values = (array) $row;
        $product = $values['product_id'] ?? null;
        $withdrawn = $values['withdrawn'] ?? null;

        if (! is_int($product) || ! is_bool($withdrawn)) {
            throw new LogicException('A zero count names its product.');
        }

        return new ZeroCount($id, $product, $withdrawn);
    }

    /**
     * An opening line with its product, its OPENING, whether a reversal cancelled that OPENING and
     * whether a later line replaces it, or null when it does not exist. A read of what may change
     * between two calls: the Action reads it again under the product's guard.
     *
     * @phpstan-impure
     */
    public function line(int $id): ?OpeningLine
    {
        $row = DB::table('opening_stock_lines as l')
            ->join('stock_movements as m', 'm.opening_line_id', '=', 'l.id')
            ->where('l.id', $id)
            ->select(['l.product_id', 'm.id as movement_id'])
            ->selectRaw('exists (select 1 from reversals r where r.original_movement_id = m.id) as reversed')
            ->selectRaw('exists (select 1 from opening_stock_lines n where n.replaces_line_id = l.id) as replaced')
            ->first();

        if ($row === null) {
            return null;
        }

        $values = (array) $row;
        $product = $values['product_id'] ?? null;
        $movement = $values['movement_id'] ?? null;
        $reversed = $values['reversed'] ?? null;
        $replaced = $values['replaced'] ?? null;

        if (! is_int($product) || ! is_int($movement) || ! is_bool($reversed) || ! is_bool($replaced)) {
            throw new LogicException('An opening line posted its OPENING (SCHEMA section 5.7).');
        }

        return new OpeningLine($id, $product, $movement, $reversed, $replaced);
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private static function key(array $values, string $column): ?int
    {
        $value = $values[$column] ?? null;

        return is_int($value) ? $value : null;
    }

    private static function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('An onboarding entry is written inside the transaction of its Action (TR-TX-01).');
        }
    }
}
