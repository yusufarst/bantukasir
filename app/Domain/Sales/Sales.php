<?php

namespace App\Domain\Sales;

use App\Domain\Configuration\ProfileRevision;
use App\Support\Numbers\Money;
use App\Support\Time\BusinessDate;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The Sales and their records, in the Sales module (SCHEMA section 5.9; BR-SALE-01 to BR-SALE-09,
 * BR-PAY-01 to BR-PAY-03, BR-DOC-01), the only writer of them (STK-12): the Sale, its lines, its one
 * Payment, its receipt snapshot and the running total that guards its later corrections. Each is
 * only ever added: a Sale is never changed, and nothing here updates or removes a row. It opens no
 * transaction of its own: the Action that calls it owns the transaction and the locks (TR-TX-01).
 *
 * The number is taken inside the committing transaction at TR §7.1 step 8, after every guard has
 * passed and immediately before the Sale is inserted, so that a definite refusal consumes no number
 * (SCH-21, TR-TX-10). Nothing serializes checkouts for numbering: no counter row, no advisory lock,
 * no table lock (SCH-25). A gap left by a rolled-back checkout is valid (SCH-26).
 */
final class Sales
{
    /** The version of the templates that render a receipt of DC-01 (SCHEMA section 5.9; ARC-45). */
    public const string RECEIPT_TEMPLATE = 'receipt.1';

    /**
     * The next value of the installation's Sale counter (SCH-20, SCH-23).
     */
    public function nextCounter(): int
    {
        self::requireTransaction();

        $counter = DB::scalar("select pg_catalog.nextval('sale_number_seq')");

        if (! is_int($counter)) {
            throw new LogicException('The Sale number sequence gives a whole number (SCH-20).');
        }

        return $counter;
    }

    /**
     * Adds the Sale, numbered by its business date and counter (SCH-22), and returns its internal
     * key. Its Payment, receipt snapshot and running total follow in this transaction: the deferred
     * keys refuse a commit without them (BR-SALE-17).
     */
    public function insertSale(string $number, int $counter, BusinessDate $date, string $at, int $sessionId, int $actorUserId, string $actorDisplayName, Money $total, int $lineCount, ?string $customerName, int $commandId): int
    {
        self::requireTransaction();

        return DB::table('sales')->insertGetId([
            'sale_number' => $number,
            'sale_number_seq' => $counter,
            'business_date' => $date->toString(),
            'committed_at' => $at,
            'cash_session_id' => $sessionId,
            'actor_user_id' => $actorUserId,
            'actor_display_name' => $actorDisplayName,
            'total_amount' => $total->toCanonical(),
            'line_count' => $lineCount,
            'customer_name' => $customerName,
            'command_id' => $commandId,
        ]);
    }

    /**
     * Adds a line of the Sale, at the price revision it applies, and returns its internal key. Its
     * SALE_ISSUE follows in this transaction: the deferred key refuses a commit without it
     * (BR-SALE-09).
     */
    public function insertLine(int $saleId, PricedLine $line): int
    {
        self::requireTransaction();

        return DB::table('sale_lines')->insertGetId([
            'sale_id' => $saleId,
            'line_no' => $line->lineNo,
            'product_id' => $line->product->guarded->id,
            'selling_price_revision_id' => $line->product->priceRevisionId,
            'unit_price' => $line->product->price->toCanonical(),
            'quantity' => $line->quantity->toCanonical(),
            'line_total' => $line->lineTotal->toCanonical(),
        ]);
    }

    /**
     * Adds the Sale's one Payment (BR-PAY-01).
     */
    public function insertPayment(int $saleId, SalePayment $payment): void
    {
        self::requireTransaction();

        DB::table('payments')->insert([
            'sale_id' => $saleId,
            'method' => $payment->method,
            'applied_amount' => $payment->applied->toCanonical(),
            'tendered_amount' => $payment->tendered?->toCanonical(),
            'change_amount' => $payment->change?->toCanonical(),
            'reference' => $payment->reference,
        ]);
    }

    /**
     * Adds the Sale's receipt snapshot (BR-DOC-01; DOCUMENT_CONTRACTS DC-01; G-17): the number,
     * total, session, cashier, method and cash amounts of the Sale and its Payment, which its keys
     * tie to them; the shop identity by reference to the revision in force, with that revision's
     * logo; the template version; the issue time; the customer name; and every line, frozen. It never
     * holds a cost or a profit (BR-COST-08).
     *
     * @param  list<PricedLine>  $lines
     */
    public function insertReceipt(int $saleId, string $number, Money $total, int $sessionId, string $sessionNumber, string $cashierDisplayName, ProfileRevision $revision, string $at, SalePayment $payment, ?string $customerName, array $lines): void
    {
        self::requireTransaction();

        DB::table('receipt_snapshots')->insert([
            'sale_id' => $saleId,
            'sale_number' => $number,
            'total_amount' => $total->toCanonical(),
            'cash_session_id' => $sessionId,
            'cashier_display_name' => $cashierDisplayName,
            'business_profile_revision_id' => $revision->id,
            'logo_file_id' => $revision->logoFileId,
            'template_version' => self::RECEIPT_TEMPLATE,
            'issued_at' => $at,
            'cash_session_number' => $sessionNumber,
            'payment_method' => $payment->method,
            'tendered_amount' => $payment->tendered?->toCanonical(),
            'change_amount' => $payment->change?->toCanonical(),
            'customer_name' => $customerName,
            'lines' => json_encode(array_map(static fn (PricedLine $line): array => $line->frozen(), $lines), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Adds the Sale's running total of corrections, nothing refunded yet: the guard row that a later
     * refund or return locks (SCHEMA sections 5.9 and 7, TR §7.1 step 5; BR-RFD-03).
     */
    public function insertCorrectionTotals(int $saleId, Money $total, string $at): void
    {
        self::requireTransaction();

        DB::table('sale_correction_totals')->insert([
            'sale_id' => $saleId,
            'sale_total' => $total->toCanonical(),
            'refunded_amount' => 0,
            'updated_at' => $at,
        ]);
    }

    /**
     * The Sale's number and cashier, read without a lock, a fact that never changes (SCH-31); null
     * when it does not exist.
     */
    public function reference(int $saleId): ?SaleReference
    {
        $row = DB::table('sales')->where('id', $saleId)->first(['id', 'sale_number', 'actor_user_id']);

        if ($row === null) {
            return null;
        }

        $values = (array) $row;
        $id = $values['id'] ?? null;
        $number = $values['sale_number'] ?? null;
        $actor = $values['actor_user_id'] ?? null;

        if (! is_int($id) || ! is_string($number) || ! is_int($actor)) {
            throw new LogicException('A Sale holds its columns (SCHEMA section 5.9).');
        }

        return new SaleReference($id, $number, $actor);
    }

    private static function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A Sale is written inside the transaction of its Action (TR-TX-01).');
        }
    }
}
