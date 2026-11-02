<?php

namespace App\Domain\Inventory;

use App\Support\Numbers\Quantity;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The confirmed receiving documents and their lines, in the Inventory module (SCHEMA section
 * 5.7; BR-INV-12, BR-INV-16, BR-INV-17; D-069 (a)). A document exists only once it is
 * confirmed: an unconfirmed one lives in page memory, so nothing here holds a draft. A line
 * has no cost: its cost is the evidence of its movement, which is Costing's (D-047). Its
 * document date is stored apart and moves no fact (BR-INV-05). They are only ever added.
 */
final class Receivings
{
    /**
     * Adds a confirmed document, with its actor's name frozen (SCHEMA section 3.5), and
     * returns its internal key.
     */
    public function insertDocument(?string $reference, ?string $documentDate, int $lineCount, int $actorUserId, string $actorDisplayName, int $commandId, string $at): int
    {
        self::requireTransaction();

        return DB::table('receiving_documents')->insertGetId([
            'reference' => $reference,
            'document_date' => $documentDate,
            'line_count' => $lineCount,
            'confirmed_at' => $at,
            'actor_user_id' => $actorUserId,
            'actor_display_name' => $actorDisplayName,
            'command_id' => $commandId,
        ]);
    }

    /**
     * Adds a line, which must post its RECEIPT in this transaction: the deferred key refuses
     * a commit without it. It returns its internal key.
     */
    public function insertLine(int $documentId, int $lineNo, int $productId, Quantity $quantity): int
    {
        self::requireTransaction();

        return DB::table('receiving_lines')->insertGetId([
            'receiving_document_id' => $documentId,
            'line_no' => $lineNo,
            'product_id' => $productId,
            'quantity' => $quantity->toCanonical(),
        ]);
    }

    private static function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A receiving document is written inside the transaction of its Action (TR-TX-01).');
        }
    }
}
