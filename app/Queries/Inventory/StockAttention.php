<?php

namespace App\Queries\Inventory;

use App\Queries\Inventory\Column as Read;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Stock attention (INVENTORY section 10; BR-INV-40, BR-INV-41; ARC-22; TASK-022 scope item
 * 3): derived from the committed balance and the minimum stock of each active product, never
 * stored. A product without a balance has no movement, so nothing on hand, and is Habis; a
 * minimum of zero has no Menipis interval. The badge of RD-03 and the counts and rows of RD-02
 * use this one definition, so that they agree; RD-02 reads its counts and its rows in one
 * statement, over one snapshot. The Domain's StockState says the same of one product, and
 * tests/Feature/Inventory/StockAttentionTest.php proves the two equal.
 */
final class StockAttention
{
    /** The state of a row of products `p` with its balance `b`, as INVENTORY section 10 derives it. */
    public const string STATE = "case when coalesce(b.on_hand, 0) = 0 then 'OUT' when coalesce(b.on_hand, 0) <= p.minimum_stock then 'LOW' else 'NORMAL' end";

    /**
     * The active products with their balances, as `p` and `b`.
     */
    public static function products(): Builder
    {
        return DB::table('products', 'p')
            ->leftJoin('stock_balances as b', 'b.product_id', '=', 'p.id')
            ->where('p.active', true);
    }

    /**
     * The numbers of Habis and Menipis products, as one row of `out_count` and `low_count`.
     */
    public static function counts(): Builder
    {
        return self::products()
            ->selectRaw("count(*) filter (where (".self::STATE.") = 'OUT') as out_count")
            ->selectRaw("count(*) filter (where (".self::STATE.") = 'LOW') as low_count");
    }

    /**
     * The badge's number: the products that are Habis or Menipis (UXI-006).
     */
    public function count(): int
    {
        $row = (array) self::counts()->first();

        return Read::int($row, 'out_count') + Read::int($row, 'low_count');
    }
}
