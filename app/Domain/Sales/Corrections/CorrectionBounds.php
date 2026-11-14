<?php

namespace App\Domain\Sales\Corrections;

use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Support\Formatting\Format;
use App\Support\Numbers\Money;
use App\Support\Numbers\Quantity;

/**
 * The cumulative bounds of a Sale's corrections (SALES_CASH BR-RFD-03; SCHEMA section 5.9; G-06;
 * INTERACTION_CONTRACTS RC-05, RC-06 and section 1.6), checked under the original Sale's lock (TR §7.1
 * step 5), which every refund and return of the Sale takes, so that racing or repeated requests cannot
 * pass them (RACE-10): the money refunded on a line never above its line total, the quantity returned on
 * it, saleable or not, never above the quantity it issued, and the money refunded on the Sale never above
 * its total, which is its applied payment and never the tendered cash (BR-PAY-02). The money bound and the
 * quantity bound are separate: goods may come back after their money was refunded, with no more money
 * (BR-RFD-04). The running totals' checks refuse any excess in the database too (SCH-33).
 *
 * A refusal names every line it concerns, with what remains refundable and returnable (UXS-35), and its
 * message the first line's remainder.
 */
final class CorrectionBounds
{
    /**
     * @param  list<CorrectedLine>  $lines
     * @param  array<int, LineTotals>  $totals  the running totals of the lines that have them, by the Sale line's key
     *
     * @throws CommandRefused REFUND_BOUND_EXCEEDED or RETURN_BOUND_EXCEEDED
     */
    public static function require(array $lines, array $totals, SaleTotals $sale): void
    {
        $money = [];
        $goods = [];
        $moneyLeft = '';
        $goodsLeft = '';
        $refund = Money::zero();

        foreach ($lines as $line) {
            $held = $totals[$line->line->id] ?? null;
            $returned = $held === null ? Quantity::of('0', $line->precision) : Quantity::fromDatabase($held->returned, $line->precision);
            $remainingAmount = $line->line->lineTotal->minus($held === null ? Money::zero() : $held->refunded);
            $remainingQuantity = $line->issued()->minus($returned);
            $bounds = ['saleLineId' => $line->line->id, 'remainingAmount' => $remainingAmount->toCanonical(), 'remainingQuantity' => $remainingQuantity->toCanonical()];

            if ($line->refund->compareTo($remainingAmount) > 0) {
                $moneyLeft = $money === [] ? Format::money($remainingAmount) : $moneyLeft;
                $money[$line->index] = $bounds;
            }

            if ($line->returned()->compareTo($remainingQuantity) > 0) {
                $goodsLeft = $goods === [] ? Format::quantity($remainingQuantity, $line->unit) : $goodsLeft;
                $goods[$line->index] = $bounds;
            }

            $refund = $refund->plus($line->refund);
        }

        if ($money !== []) {
            throw new CommandRefused(ErrorCode::RefundBoundExceeded, params: ['uang' => $moneyLeft], lines: $money);
        }

        if ($goods !== []) {
            throw new CommandRefused(ErrorCode::ReturnBoundExceeded, params: ['jumlah' => $goodsLeft], lines: $goods);
        }

        // The Sale's own bound, which the lines' bounds imply while its total is the sum of its line
        // totals and its running totals the sums of their lines (G-01, G-06).
        if ($refund->compareTo($sale->remaining()) > 0) {
            throw new CommandRefused(ErrorCode::RefundBoundExceeded, params: ['uang' => Format::money($sale->remaining())]);
        }
    }
}
