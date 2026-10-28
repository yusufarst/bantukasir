<?php

namespace App\Domain\Commands;

use LogicException;

/**
 * The server's fixed list of command operations (ROUTE_CONTRACTS section 6,
 * RC-01 to RC-48, and the scheduled SC-01), each with its family of
 * INTERACTION_CONTRACTS section 1.4 (ADR-007 section 1; TASK-008 scope item
 * 8). An envelope names one of them; anything else is refused. The family is
 * the identifier of its label key `commands.family.<family>` (COPY_DECK
 * section 14.1); print, reprint and export have no label and are exempt from
 * the block of NC-13 (`print`).
 *
 * Each operation's route, FormRequest and Action are added by its own Task.
 * The test harness may add one more operation, its probe, only in the testing
 * environment (allowForTesting); the release build has no such caller.
 */
final class Operations
{
    /** The operation version of every V1 operation (INTERACTION_CONTRACTS section 1.1). */
    public const int VERSION = 1;

    /** @var array<string, string> operation => family */
    public const array FAMILIES = [
        'sale.checkout' => 'sale',
        'cash_session.open' => 'open_session',
        'cash_session.close' => 'close_session',
        'cash_session.close_assisted' => 'close_session',
        'sale_correction.drawer_funded' => 'refund_drawer',
        'sale_correction.other' => 'refund_other',
        'receiving.confirm' => 'stock_posting',
        'manual_issue.confirm' => 'stock_posting',
        'adjustment.confirm' => 'stock_posting',
        'reversal.confirm' => 'reversal',
        'opening_stock.confirm' => 'opening_stock',
        'onboarding.start' => 'onboarding',
        'onboarding.release' => 'onboarding',
        'onboarding.withdraw_zero_count' => 'opening_stock',
        'cost_correction.confirm' => 'cost_correction',
        'posting_block.reopen' => 'reopen',
        'product.create' => 'product_create',
        'product.update' => 'product_edit',
        'product.deactivate' => 'product_edit',
        'product.reactivate' => 'product_edit',
        'category.create' => 'category',
        'category.rename' => 'category',
        'category.deactivate' => 'category',
        'category.reactivate' => 'category',
        'product_photo.set' => 'photo',
        'product_photo.remove' => 'photo',
        'barcode.add' => 'barcode',
        'barcode.generate' => 'barcode',
        'barcode.retire' => 'barcode',
        'barcode.correct_misassignment' => 'barcode_correction',
        'csv_import.confirm' => 'csv_import',
        'business_profile.change' => 'profile',
        'cash_register.create' => 'register',
        'cash_register.update' => 'register',
        'user.create' => 'users',
        'user.update' => 'users',
        'owner.update_details' => 'users',
        'user.deactivate' => 'users',
        'user.reactivate' => 'users',
        'user.anonymize' => 'users',
        'user.issue_link' => 'users',
        'user.revoke_link' => 'users',
        'review_note.add' => 'review_note',
        'document.print_receipt' => 'print',
        'document.print_session_report' => 'print',
        'document.export_session_report' => 'print',
        'document.print_labels' => 'print',
        'opening_stock.replace' => 'opening_stock',
        // SC-01, the scheduled posting block (INTERACTION_CONTRACTS section 7),
        // in the family of the reconciliation's block or reopening.
        'posting_block.block' => 'reopen',
    ];

    /**
     * The operations that a process runs, never a user through an RC route:
     * their intents are derived, not sent, so a user's status check never
     * resolves or fences them (TASK-008 review finding R-03).
     */
    public const array SCHEDULED = ['posting_block.block'];

    /** @var array<string, string> the probe operations of the test harness */
    private static array $testing = [];

    public static function exists(string $operation): bool
    {
        return array_key_exists($operation, self::FAMILIES) || array_key_exists($operation, self::$testing);
    }

    /**
     * Whether a user submits the operation through its route (RC-01 to
     * RC-48, or the probe), so that its intent may be checked (RC-50).
     */
    public static function submittedByUsers(string $operation): bool
    {
        return self::exists($operation) && ! in_array($operation, self::SCHEDULED, true);
    }

    public static function familyOf(string $operation): string
    {
        return self::FAMILIES[$operation] ?? self::$testing[$operation]
            ?? throw new LogicException(sprintf('%s is not an operation of the server\'s list (ROUTE_CONTRACTS section 6).', $operation));
    }

    /**
     * Adds a probe operation of the test harness (TASK-008 scope item 9); it
     * refuses outside the testing environment.
     */
    public static function allowForTesting(string $operation, string $family): void
    {
        if (! app()->environment('testing')) {
            throw new LogicException('A probe operation exists only in the testing environment.');
        }

        if (array_key_exists($operation, self::FAMILIES) || preg_match('/^testing\.[a-z][a-z0-9_]{0,40}$/', $operation) !== 1) {
            throw new LogicException(sprintf('%s is no probe operation (testing.<name>).', $operation));
        }

        self::$testing[$operation] = $family;
    }
}
