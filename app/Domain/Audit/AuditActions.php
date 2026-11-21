<?php

namespace App\Domain\Audit;

/**
 * The action codes of the business audit and the label key of each (INTERACTION_CONTRACTS section 8;
 * COPY_DECK section 11.14; TASK-042 scope item 1, AC-042-03). The codes are the allowlist of the filter
 * "Jenis" of Riwayat aktivitas (RP-612 `action`); the codes that section 8 marks "Proposed (section 11)"
 * take the labels that TASK-002 approved in COPY_DECK section 11.14. The interface holds the same map
 * (`resources/js/lib/audit/activity.ts`), and tests/Unit/Audit/AuditActionsTest.php holds both to the
 * documents and to every code that the Actions write.
 */
final class AuditActions
{
    /** @var array<string, string> */
    public const array LABELS = [
        'SALE_CHECKOUT' => 'audit.action.sale',
        'CASH_SESSION_OPEN' => 'audit.action.open_session',
        'CASH_SESSION_CLOSE' => 'audit.action.close_session',
        'CASH_SESSION_CLOSE_ASSISTED' => 'audit.action.assisted_close',
        'SALE_CORRECTION' => 'audit.action.refund',
        'RECEIVING_CONFIRM' => 'audit.action.receipt',
        'MANUAL_ISSUE_CONFIRM' => 'audit.action.manual_issue',
        'ADJUSTMENT_CONFIRM' => 'audit.action.adjustment',
        'REVERSAL_CONFIRM' => 'audit.action.reversal',
        'OPENING_STOCK_CONFIRM' => 'audit.action.opening_stock',
        'OPENING_STOCK_REPLACE' => 'audit.action.opening_stock_replace',
        'ZERO_COUNT_WITHDRAW' => 'audit.action.zero_count_withdraw',
        'ONBOARDING_START' => 'audit.action.onboarding_start',
        'ONBOARDING_RELEASE' => 'audit.action.onboarding_release',
        'COST_CORRECTION' => 'audit.action.cost_correction',
        'COST_INCOMPLETE_MARK' => 'audit.action.cost_incomplete_mark',
        'RECONCILIATION_BLOCK' => 'audit.action.reconciliation_block',
        'POSTING_REOPEN' => 'audit.action.reopen',
        'PRODUCT_CREATE' => 'audit.action.product_create',
        'PRODUCT_EDIT' => 'audit.action.product_edit',
        'PRICE_CHANGE' => 'audit.action.price_change',
        'PRODUCT_DEACTIVATE' => 'audit.action.product_deactivate',
        'PRODUCT_REACTIVATE' => 'audit.action.product_reactivate',
        'CATEGORY_CREATE' => 'audit.action.category_create',
        'CATEGORY_RENAME' => 'audit.action.category_rename',
        'CATEGORY_DEACTIVATE' => 'audit.action.category_deactivate',
        'CATEGORY_REACTIVATE' => 'audit.action.category_reactivate',
        'PHOTO_CHANGE' => 'audit.action.photo_change',
        'PHOTO_REMOVE' => 'audit.action.photo_remove',
        'BARCODE_ADD' => 'audit.action.barcode_add',
        'BARCODE_GENERATE' => 'audit.action.barcode_generate',
        'BARCODE_RETIRE' => 'audit.action.barcode_retire',
        'BARCODE_CORRECTION' => 'audit.action.barcode_correction',
        'CSV_IMPORT' => 'audit.action.csv_import',
        'PROFILE_CHANGE' => 'audit.action.profile_change',
        'REGISTER_CREATE' => 'audit.action.register_create',
        'REGISTER_CHANGE' => 'audit.action.register_change',
        'USER_CREATE' => 'audit.action.user_create',
        'ROLE_CHANGE' => 'audit.action.role_change',
        'DETAILS_CHANGE' => 'audit.action.details_change',
        'USER_DEACTIVATE' => 'audit.action.user_deactivate',
        'USER_REACTIVATE' => 'audit.action.user_reactivate',
        'USER_ANONYMIZE' => 'audit.action.user_anonymize',
        'LINK_ISSUE' => 'audit.action.link_issue',
        'LINK_REVOKE' => 'audit.action.link_revoke',
        'LINK_CONSUME' => 'audit.action.link_consume',
        'PASSWORD_CHANGE' => 'audit.action.password_change',
        'OWNER_RECOVERY' => 'audit.action.owner_recovery',
        'OWNER_REPLACEMENT' => 'audit.action.owner_replacement',
        'REVIEW_NOTE_ADD' => 'audit.action.review_note',
        'PRINT' => 'audit.action.print',
        'EXPORT' => 'audit.action.export',
        'ONBOARDING_RESTORE_HOLD' => 'audit.action.onboarding_restore_hold',
        'RESTORE_RECONCILIATION' => 'audit.action.restore_reconciliation',
        'PROJECTION_REBUILT' => 'audit.action.projection_rebuilt',
    ];

    /**
     * Whether a text is one of the action codes of section 8.
     */
    public static function isCode(mixed $code): bool
    {
        return is_string($code) && array_key_exists($code, self::LABELS);
    }
}
