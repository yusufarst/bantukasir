<?php

namespace App\Domain\Inventory;

use App\Support\Numbers\Quantity;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The confirmed manual issues and their lines, in the Inventory module (SCHEMA section 5.7;
 * BR-INV-18 to BR-INV-22): goods that leave stock for a reason other than a sale, with one of
 * the five reasons of BR-INV-19. An issue exists only once it is confirmed, and is only ever
 * added: it is never a Sale, a Payment or a receipt (BR-INV-22). A line has no cost: its cost
 * is the allocation of its movement, which is Costing's (ADR-008 section 2).
 */
final class ManualIssues
{
    /** The reasons of BR-INV-19, in their order (COPY_DECK section 9.1). */
    public const array REASONS = ['DAMAGED', 'INTERNAL_USE', 'LOST', 'SAMPLE', 'OTHER'];

    /**
     * Adds a confirmed issue, with its actor's name frozen (SCHEMA section 3.5), and returns its
     * internal key.
     */
    public function insertIssue(string $reasonCode, ?string $note, int $lineCount, int $actorUserId, string $actorDisplayName, int $commandId, string $at): int
    {
        self::requireTransaction();

        if (! in_array($reasonCode, self::REASONS, true)) {
            throw new LogicException('A reason of BR-INV-19 was validated before the Action.');
        }

        return DB::table('manual_issues')->insertGetId([
            'reason_code' => $reasonCode,
            'note' => $note,
            'line_count' => $lineCount,
            'confirmed_at' => $at,
            'actor_user_id' => $actorUserId,
            'actor_display_name' => $actorDisplayName,
            'command_id' => $commandId,
        ]);
    }

    /**
     * Adds a line of this positive quantity, which must post its MANUAL_ISSUE in this
     * transaction: the deferred key refuses a commit without it. It returns its internal key.
     */
    public function insertLine(int $issueId, int $lineNo, int $productId, Quantity $quantity): int
    {
        self::requireTransaction();

        return DB::table('manual_issue_lines')->insertGetId([
            'manual_issue_id' => $issueId,
            'line_no' => $lineNo,
            'product_id' => $productId,
            'quantity' => $quantity->toCanonical(),
        ]);
    }

    private static function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A manual issue is written inside the transaction of its Action (TR-TX-01).');
        }
    }
}
