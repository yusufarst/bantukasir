<?php

namespace App\Domain\Inventory;

use App\Domain\Audit\Actor;
use App\Support\Time\Clock;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The posting blocks of the daily reconciliation, in the Inventory module (SCHEMA section
 * 5.7; BR-INV-45, BR-INV-46; G-11). A product is blocked while it has a block without a
 * reopening. Both writers and every posting hold the product guard (TR §7.1 step 6), so a
 * block and a posting of one product serialize (RACE-24); this service takes no lock and
 * opens no transaction (TR-TX-01). Blocks and reopenings are only ever added.
 *
 * TASK-036 writes them from the reconciliation and from RC-16; until then the fixtures of
 * the tests call this service (TASK-021 scope item 7; EXC-45).
 */
final class PostingBlocks
{
    public function __construct(private readonly Clock $clock) {}

    /**
     * The products among these that have a block without a reopening, read under their
     * guards (G-11).
     *
     * @param  list<int>  $productIds
     * @return list<int>
     */
    public function blockedAmong(array $productIds): array
    {
        $rows = DB::table('inventory_posting_blocks', 'b')
            ->whereIn('b.product_id', $productIds)
            ->whereNotExists(static function (Builder $reopened): void {
                $reopened->from('inventory_posting_block_reopenings', 'r')->whereColumn('r.block_id', 'b.id');
            })
            ->distinct()
            ->pluck('b.product_id');
        $blocked = [];

        foreach ($rows as $product) {
            if (! is_int($product)) {
                throw new LogicException('A posting block names its product (SCHEMA section 5.7).');
            }

            $blocked[] = $product;
        }

        return $blocked;
    }

    /**
     * Adds a block of the product whose guard the caller holds, and returns its internal key.
     */
    public function block(int $productId, Actor $actor, string $detailCode, int $commandId): int
    {
        self::requireTransaction();

        return DB::table('inventory_posting_blocks')->insertGetId([
            'product_id' => $productId,
            'blocked_at' => $this->now(),
            'actor_kind' => $actor->kind,
            'actor_user_id' => $actor->userId,
            'actor_process' => $actor->process,
            'detail_code' => $detailCode,
            'command_id' => $commandId,
        ]);
    }

    /**
     * Records the reopening of a block, whose product's guard the caller holds; its primary
     * key allows one per block.
     */
    public function reopen(int $blockId, int $actorUserId, string $actorDisplayName, ?string $reason, int $commandId): void
    {
        self::requireTransaction();

        DB::table('inventory_posting_block_reopenings')->insert([
            'block_id' => $blockId,
            'reopened_at' => $this->now(),
            'actor_user_id' => $actorUserId,
            'actor_display_name' => $actorDisplayName,
            'reason' => $reason,
            'command_id' => $commandId,
        ]);
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s.uP');
    }

    private static function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A posting block is written inside the transaction of its command (TR-TX-01).');
        }
    }
}
