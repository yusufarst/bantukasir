<?php

namespace App\Domain\Sales\Corrections;

use App\Support\Numbers\Money;
use App\Support\Numbers\Quantity;
use App\Support\Time\BusinessDate;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The Owner's corrections of a Sale and their running totals, in the Sales module (SCHEMA section 5.9;
 * SALES_CASH BR-RFD-01 to BR-RFD-17; D-035; TASK-033), the only writer of them (STK-12): the correction,
 * its refund and refund lines, its return lines, and the running totals of the Sale and of each line,
 * which it changes only by a relative increment in one statement, never by an absolute value computed
 * from an earlier read, so that their checks judge the true cumulative value (SCH-33). A correction, a
 * refund and a return line are only ever added; the original Sale, its lines, its Payment and its receipt
 * are never touched (BR-RFD-12). It opens no transaction of its own: the Action that calls it owns the
 * transaction and the locks (TR-TX-01), and calls the Inventory and Costing modules beside it (STK-11).
 *
 * The original Sale's lock is the update lock of its running-total row, created with the Sale, since the
 * runtime role cannot lock rows of the insert-only `sales` (SCHEMA section 7, TR §7.1 step 5): every
 * refund and return of the Sale takes it before it reads the running totals.
 */
final class SaleCorrections
{
    public const string SALEABLE = 'SALEABLE';

    public const string NOT_SALEABLE = 'NOT_SALEABLE';

    /** A correction's time as the pages and the report receive one: an instant in UTC with its microseconds. */
    private const string RECORDED_AT = "to_char(c.recorded_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as recorded_at";

    /**
     * The path of RP-204, UXP-204 "Pengembalian tercatat", of a correction of a Sale by their internal
     * keys (ROUTE_CONTRACTS RP-204; TASK-034): the result page of RC-05 and RC-06.
     */
    public static function recordedPath(int $saleId, int $correctionId): string
    {
        return '/sales/'.$saleId.'/corrections/'.$correctionId;
    }

    /**
     * The Sale's number, read without a lock, a fact that never changes (SCH-31); null when it does not
     * exist.
     */
    public function sale(int $saleId): ?CorrectedSale
    {
        $values = (array) DB::table('sales')->where('id', $saleId)->first(['id', 'sale_number']);

        if ($values === []) {
            return null;
        }

        $id = $values['id'] ?? null;
        $number = $values['sale_number'] ?? null;

        if (! is_int($id) || ! is_string($number)) {
            throw new LogicException('A Sale holds its columns (SCHEMA section 5.9).');
        }

        return new CorrectedSale($id, $number);
    }

    /**
     * The lines of the Sale as they were committed, by their internal keys, read without a lock: facts
     * that never change (SCH-31).
     *
     * @return array<int, SaleLineFacts>
     */
    public function linesOf(int $saleId): array
    {
        $lines = [];

        foreach (DB::table('sale_lines')->where('sale_id', $saleId)->orderBy('id')->get(['id', 'product_id', 'line_total', 'quantity']) as $row) {
            $values = (array) $row;
            $id = $values['id'] ?? null;
            $product = $values['product_id'] ?? null;
            $total = $values['line_total'] ?? null;
            $quantity = $values['quantity'] ?? null;

            if (! is_int($id) || ! is_int($product) || (! is_int($total) && ! is_string($total)) || ! is_string($quantity)) {
                throw new LogicException('A Sale line holds its columns (SCHEMA section 5.9).');
            }

            $lines[$id] = new SaleLineFacts($id, $product, Money::fromDatabase($total), $quantity);
        }

        return $lines;
    }

    /**
     * The original Sale's lock, step 5: its running-total row under the update lock, or null when the
     * Sale does not exist (SCHEMA section 7).
     */
    public function lockSale(int $saleId): ?SaleTotals
    {
        self::requireTransaction();

        $values = (array) DB::table('sale_correction_totals')->where('sale_id', $saleId)->lock('for no key update')->first(['sale_id', 'sale_total', 'refunded_amount']);

        if ($values === []) {
            return null;
        }

        $id = $values['sale_id'] ?? null;
        $total = $values['sale_total'] ?? null;
        $refunded = $values['refunded_amount'] ?? null;

        if (! is_int($id) || (! is_int($total) && ! is_string($total)) || (! is_int($refunded) && ! is_string($refunded))) {
            throw new LogicException('A running total holds its columns (SCHEMA section 5.9).');
        }

        return new SaleTotals($id, Money::fromDatabase($total), Money::fromDatabase($refunded));
    }

    /**
     * The running totals of the lines that have them, by the Sale line's key, under the original Sale's
     * lock that the caller holds: every writer of them holds it too.
     *
     * @param  list<int>  $saleLineIds
     * @return array<int, LineTotals>
     */
    public function lineTotals(array $saleLineIds): array
    {
        self::requireTransaction();

        $totals = [];

        foreach (DB::table('sale_line_correction_totals')->whereIn('sale_line_id', $saleLineIds)->orderBy('sale_line_id')->get(['sale_line_id', 'refunded_amount', 'returned_quantity']) as $row) {
            $values = (array) $row;
            $line = $values['sale_line_id'] ?? null;
            $refunded = $values['refunded_amount'] ?? null;
            $returned = $values['returned_quantity'] ?? null;

            if (! is_int($line) || (! is_int($refunded) && ! is_string($refunded)) || ! is_string($returned)) {
                throw new LogicException('A line\'s running total holds its columns (SCHEMA section 5.9).');
            }

            $totals[$line] = new LineTotals($line, Money::fromDatabase($refunded), $returned);
        }

        return $totals;
    }

    /**
     * Adds the correction of the Sale, dated by its posting (BR-COST-36; G-18), with its reason and the
     * frozen name of its actor (G-16), and returns its internal key.
     */
    public function insertCorrection(int $saleId, BusinessDate $date, string $reason, string $at, int $actorUserId, string $actorDisplayName, int $commandId): int
    {
        self::requireTransaction();

        return DB::table('sale_corrections')->insertGetId([
            'sale_id' => $saleId,
            'business_date' => $date->toString(),
            'reason' => $reason,
            'recorded_at' => $at,
            'actor_user_id' => $actorUserId,
            'actor_display_name' => $actorDisplayName,
            'command_id' => $commandId,
        ]);
    }

    /**
     * Adds the correction's refund with its payout (BR-RFD-05, BR-RFD-06), and returns its internal key.
     * Its amount is the sum of its lines (G-06).
     */
    public function insertRefund(int $correctionId, int $saleId, Money $amount, Payout $payout): int
    {
        self::requireTransaction();

        return DB::table('refunds')->insertGetId([
            'correction_id' => $correctionId,
            'sale_id' => $saleId,
            'amount' => $amount->toCanonical(),
            'payout_method' => $payout->method,
            'funding_source' => $payout->fundingSource,
            'cash_session_id' => $payout->cashSessionId,
            'payout_reference' => $payout->reference,
        ]);
    }

    /**
     * Adds a line of the refund, the money refunded on one line of the same Sale.
     */
    public function insertRefundLine(int $refundId, int $saleId, int $saleLineId, Money $amount): int
    {
        self::requireTransaction();

        return DB::table('refund_lines')->insertGetId([
            'refund_id' => $refundId,
            'sale_id' => $saleId,
            'sale_line_id' => $saleLineId,
            'amount' => $amount->toCanonical(),
        ]);
    }

    /**
     * Adds a return line, the quantity returned of one line of the same Sale and its product, in one
     * condition; a saleable one posts its RETURN_RECEIPT in this transaction, which its presence key
     * requires, and a not-saleable one none (BR-INV-39).
     */
    public function insertReturnLine(int $correctionId, int $saleId, int $saleLineId, int $productId, Quantity $quantity, string $condition): int
    {
        self::requireTransaction();

        if (! in_array($condition, [self::SALEABLE, self::NOT_SALEABLE], true) || ! $quantity->isPositive()) {
            throw new LogicException('A return line returns a positive quantity, saleable or not (SCHEMA section 5.9).');
        }

        return DB::table('return_lines')->insertGetId([
            'correction_id' => $correctionId,
            'sale_id' => $saleId,
            'sale_line_id' => $saleLineId,
            'product_id' => $productId,
            'quantity' => $quantity->toCanonical(),
            'condition' => $condition,
        ]);
    }

    /**
     * Adds the money a correction refunds to the Sale's running total, a relative increment (SCH-33).
     */
    public function addRefunded(int $saleId, Money $amount, string $at): void
    {
        self::requireTransaction();

        $changed = DB::update('update sale_correction_totals set refunded_amount = refunded_amount + ?::bigint, updated_at = ?::timestamptz where sale_id = ?', [$amount->toCanonical(), $at, $saleId]);

        if ($changed !== 1) {
            throw new LogicException('A Sale has its running total (SCHEMA section 5.9).');
        }
    }

    /**
     * Adds the money a correction refunds on a line and the quantity it returns there, saleable or not,
     * to the line's running total: the line's first correction inserts the row with them, a later one
     * adds them by a relative increment (SCH-33). Whether the row exists was read under the original
     * Sale's lock, which every writer of it holds.
     */
    public function addToLine(CorrectedLine $line, bool $stored, string $at): void
    {
        self::requireTransaction();

        if (! $stored) {
            DB::table('sale_line_correction_totals')->insert([
                'sale_line_id' => $line->line->id,
                'line_total' => $line->line->lineTotal->toCanonical(),
                'issued_quantity' => $line->line->quantity,
                'refunded_amount' => $line->refund->toCanonical(),
                'returned_quantity' => $line->returned()->toCanonical(),
                'updated_at' => $at,
            ]);

            return;
        }

        $changed = DB::update(
            'update sale_line_correction_totals set refunded_amount = refunded_amount + ?::bigint, returned_quantity = returned_quantity + ?::numeric, updated_at = ?::timestamptz where sale_line_id = ?',
            [$line->refund->toCanonical(), $line->returned()->toCanonical(), $at, $line->line->id],
        );

        if ($changed !== 1) {
            throw new LogicException('A line\'s running total read under the Sale\'s lock exists.');
        }
    }

    /**
     * The Sale of a committed correction, a fact read without a lock; null when it does not exist.
     */
    public function saleOf(int $correctionId): ?int
    {
        $sale = DB::table('sale_corrections')->where('id', $correctionId)->value('sale_id');

        return is_int($sale) ? $sale : null;
    }

    /**
     * The ordinal of the next correction of the Sale whose lock the caller holds: one more than the
     * corrections the Sale holds (INTERACTION_CONTRACTS RC-05; BR-RFD-05; D-087). Every correction of the
     * Sale, drawer-funded or not, takes that lock before it is added, so none can be added meanwhile and
     * the ordinal of a drawer-funded payout's reference `<Sale number>/<k>` is never given twice.
     */
    public function nextOrdinal(int $saleId): int
    {
        self::requireTransaction();

        return DB::table('sale_corrections')->where('sale_id', $saleId)->count() + 1;
    }

    /**
     * The drawer-funded refunds paid from a cash session, in the order they were recorded: the Sales
     * module's query of a session's only drawer outflow (STACK_AND_MODULES STK-10; BR-CASH-07, BR-CASH-12,
     * BR-CASH-20, BR-RFD-08; G-05; D-035; TASK-034), through the partial index of the drawer-funded refunds
     * of a session (SCHEMA section 11). Each with its internal key, its Sale's number, its payout's
     * reference, its amount, its correction's time as an instant in UTC and the Owner's name frozen with it
     * (G-16). A close and RC-05 read it under the session's lock, which every drawer-funded refund of the
     * session takes too (RACE-13, RACE-14); a page reads it without a lock.
     *
     * @return list<array{id: int, saleNumber: string, reference: string, amount: Money, recordedAt: string, owner: string}>
     */
    public function drawerRefundsOf(int $sessionId): array
    {
        $refunds = [];

        foreach (DB::table('refunds as r')
            ->join('sale_corrections as c', 'c.id', '=', 'r.correction_id')
            ->join('sales as s', 's.id', '=', 'r.sale_id')
            ->where('r.cash_session_id', $sessionId)
            ->where('r.funding_source', Payout::DRAWER)
            ->orderBy('c.recorded_at')
            ->orderBy('r.id')
            ->select(['r.id', 's.sale_number', 'r.payout_reference', 'r.amount', 'c.actor_display_name'])
            ->selectRaw(self::RECORDED_AT)
            ->get() as $row) {
            $values = (array) $row;
            $id = $values['id'] ?? null;
            $number = $values['sale_number'] ?? null;
            $reference = $values['payout_reference'] ?? null;
            $amount = $values['amount'] ?? null;
            $owner = $values['actor_display_name'] ?? null;
            $at = $values['recorded_at'] ?? null;

            if (! is_int($id) || ! is_string($number) || ! is_string($reference) || (! is_int($amount) && ! is_string($amount)) || ! is_string($owner) || ! is_string($at)) {
                throw new LogicException('A drawer-funded refund holds its columns (SCHEMA section 5.9).');
            }

            $refunds[] = ['id' => $id, 'saleNumber' => $number, 'reference' => $reference, 'amount' => Money::fromDatabase($amount), 'recordedAt' => $at, 'owner' => $owner];
        }

        return $refunds;
    }

    private static function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A correction is locked and written inside the transaction of its Action (TR-TX-01).');
        }
    }
}
