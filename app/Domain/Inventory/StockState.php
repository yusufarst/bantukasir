<?php

namespace App\Domain\Inventory;

use App\Support\Numbers\Quantity;

/**
 * The stock attention of an active product (INVENTORY section 10; BR-INV-40; ARC-22):
 * derived from its current balance and minimum stock, never stored. With a minimum of 5, on
 * hand 6 is NORMAL, 5 and 1 are LOW and 0 is OUT; a minimum of zero has no LOW interval, and
 * a new active product with stock 0 is OUT.
 */
enum StockState: string
{
    case Out = 'OUT';
    case Low = 'LOW';
    case Normal = 'NORMAL';

    public static function of(Quantity $onHand, Quantity $minimum): self
    {
        if ($onHand->isZero()) {
            return self::Out;
        }

        return $onHand->compareTo($minimum) <= 0 ? self::Low : self::Normal;
    }
}
