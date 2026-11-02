<?php

namespace App\Queries\Inventory;

use App\Domain\Access\Grant;
use App\Domain\Inventory\StockLedger;
use App\Domain\Inventory\StockState;
use App\DTO\Inventory\ProductStockData;
use App\Support\Numbers\Quantity;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The stock of the products that a Catalog read answers (ROUTE_CONTRACTS RD-01, RP-302,
 * RP-304; INTERACTION_CONTRACTS section 3.1; BR-CAT-07; ARC-22). The Inventory module reads
 * the Catalog's rows and never the other way round (STK-13), so the web layer gives a page of
 * Product Search to this read, which adds the stock that the role may see in one bounded
 * statement over the balance and the minimum: a product with no balance has no movement, and
 * so nothing on hand (BR-CAT-03).
 */
final class ProductStock
{
    public function __construct(private readonly StockLedger $ledger) {}

    /**
     * A page of RD-01 with each row's stock, as the viewer's cell of CAP-14 allows: all of it
     * for a full grant, the quantity alone for the cashier's restricted one, none otherwise.
     *
     * @param  array{rows: list<array<string, mixed>>, page: int, perPage: int, hasMore: bool}  $page
     * @return array{rows: list<array<string, mixed>>, page: int, perPage: int, hasMore: bool}
     */
    public function added(array $page, Grant $grant): array
    {
        if ($grant !== Grant::Full && $grant !== Grant::Restricted) {
            return $page;
        }

        $ids = [];

        foreach ($page['rows'] as $row) {
            $id = $row['id'] ?? null;

            if (! is_int($id)) {
                throw new LogicException('A result of Product Search names its product.');
            }

            $ids[] = $id;
        }

        $stocks = $ids === [] ? [] : self::stocksOf($ids);
        $rows = [];

        foreach ($page['rows'] as $index => $row) {
            [$onHand, $minimum] = $stocks[$ids[$index]] ?? throw new LogicException('A product of a result has its row.');
            $state = ($row['active'] ?? null) === true ? StockState::of($onHand, $minimum) : null;
            $stock = $grant === Grant::Full
                ? ProductStockData::withAttention($onHand->toCanonical(), $minimum->toCanonical(), $state)
                : ProductStockData::atCheckout($onHand->toCanonical());
            $rows[] = [...$row, 'stock' => $stock->toArray()];
        }

        $page['rows'] = $rows;

        return $page;
    }

    /**
     * Whether a product's unit and precision are locked: it has a movement (BR-CAT-07).
     */
    public function unitLocked(int $productId): bool
    {
        return $this->ledger->hasMovement($productId);
    }

    /**
     * The quantity on hand and the minimum of each product, in one statement.
     *
     * @param  non-empty-list<int>  $ids
     * @return array<int, array{Quantity, Quantity}>
     */
    private static function stocksOf(array $ids): array
    {
        $rows = DB::table('products', 'p')
            ->leftJoin('stock_balances as b', 'b.product_id', '=', 'p.id')
            ->whereIn('p.id', $ids)
            ->get(['p.id', 'p.quantity_precision', 'p.minimum_stock', 'b.on_hand']);
        $stocks = [];

        foreach ($rows as $row) {
            $values = (array) $row;
            $id = $values['id'] ?? null;
            $precision = $values['quantity_precision'] ?? null;
            $minimum = $values['minimum_stock'] ?? null;
            $onHand = $values['on_hand'] ?? null;

            if (! is_int($id) || ! is_int($precision) || ! is_string($minimum)) {
                throw new LogicException('A product row holds its columns (SCHEMA section 5.6).');
            }

            $stocks[$id] = [is_string($onHand) ? Quantity::fromDatabase($onHand, $precision) : Quantity::of('0', $precision), Quantity::fromDatabase($minimum, $precision)];
        }

        return $stocks;
    }
}
