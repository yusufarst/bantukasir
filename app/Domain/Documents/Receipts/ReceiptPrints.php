<?php

namespace App\Domain\Documents\Receipts;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The print requests of a receipt (RC-44, RP-103P; DOCUMENT_CONTRACTS DC-01; BR-DOC-04, BR-AUD-06;
 * TASK-031 scope item 2): a print request is the committed command, whose only record is its audit
 * event PRINT about the Sale (subject `sale`), with the Sale's number as its reference and its cash
 * session as its filter (BR-AUD-04), holding the size asked for and whether it is a copy. The first
 * print request of a Sale is the original; every later one, by anyone, is a copy and prints "Salinan"
 * first at the top. RP-103P reads that event, and rendering it is a read (TR-HTTP-02).
 *
 * Whether a request is the first is decided under a lock of the Sale's prints, a transaction-level
 * advisory lock keyed by the Sale (serialize()): two first requests of one Sale at the same moment
 * commit one after the other, and the second sees the first, so that exactly one is the original.
 * The lock writes nothing and guards no business fact, which a print never touches; it waits within
 * the transaction's lock timeout, after which the command answers SERVICE_BUSY (TR-RETRY-02).
 */
final class ReceiptPrints
{
    public const string AUDIT_ACTION = 'PRINT';

    public const string AUDIT_SUBJECT = 'sale';

    /** The sizes of RC-44's payload (INTERACTION_CONTRACTS, "RC-44 to RC-47"). */
    public const array SIZES = ['58', '80', 'A4'];

    /** The sizes of RP-103P's parameter (ROUTE_CONTRACTS RP-103P). */
    public const array PAGE_SIZES = ['58', '80', 'a4'];

    /** The namespace of the lock of a Sale's prints, hashed with the Sale's key. */
    private const string LOCK_SPACE = 'document.print_receipt';

    /**
     * The address of RP-103P for a print request of a Sale, in its size.
     */
    public static function path(int $sale, int $printRequest, string $size): string
    {
        return '/sales/'.(string) $sale.'/receipt/print/'.(string) $printRequest.'?size='.self::pageSize($size);
    }

    /**
     * RP-103P's size for RC-44's: `A4` is `a4`.
     */
    public static function pageSize(string $size): string
    {
        return match ($size) {
            '58', '80' => $size,
            'A4' => 'a4',
            default => throw new LogicException('RC-44 takes 58, 80 or A4.'),
        };
    }

    /**
     * Waits for, then holds to the end of the transaction, the lock of the Sale's prints.
     */
    public function serialize(int $sale): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('The lock of a Sale\'s prints is held by the transaction of its print request.');
        }

        DB::select('select pg_catalog.pg_advisory_xact_lock(pg_catalog.hashtextextended(?::text, ?::bigint))', [self::LOCK_SPACE, $sale]);
    }

    /**
     * Whether a print request of the Sale is committed, by anyone: a new one is then a copy. The
     * Sale's number is the event's reference, which the audit's index of references serves.
     */
    public function printed(int $sale, string $number): bool
    {
        return DB::table('audit_events')
            ->where('reference', $number)
            ->where('action', self::AUDIT_ACTION)
            ->where('subject_type', self::AUDIT_SUBJECT)
            ->where('subject_id', $sale)
            ->exists();
    }

    /**
     * The print request of RP-103P: the committed RC-44 of this Sale by this actor, with whether it
     * is a copy; null for any other command, Sale or actor, which the page answers NOT_FOUND.
     */
    public function find(int $sale, int $printRequest, int $actor): ?ReceiptPrint
    {
        $row = DB::table('audit_events')
            ->where('command_id', $printRequest)
            ->where('action', self::AUDIT_ACTION)
            ->where('subject_type', self::AUDIT_SUBJECT)
            ->where('subject_id', $sale)
            ->where('actor_user_id', $actor)
            ->selectRaw("(change_data->>'copy')::boolean as copy, change_data->>'size' as size")
            ->first();

        if ($row === null) {
            return null;
        }

        $values = (array) $row;
        $copy = $values['copy'] ?? null;
        $size = $values['size'] ?? null;

        if (! is_bool($copy) || ! is_string($size) || ! in_array($size, self::SIZES, true)) {
            throw new LogicException('The audit event of a print request holds its size and whether it is a copy (RC-44).');
        }

        return new ReceiptPrint($copy, $size);
    }
}
