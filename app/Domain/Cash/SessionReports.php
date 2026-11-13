<?php

namespace App\Domain\Cash;

use App\Domain\Configuration\ProfileRevision;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The closes of cash sessions and their immutable reports, in the Cash module (SCHEMA section 5.10;
 * BR-CASH-13 to BR-CASH-20; G-05, G-17; DOCUMENT_CONTRACTS DC-02), the only writer of them (STK-12).
 * Each is only ever added: a close and its report are never changed or removed (BR-CASH-19). It opens
 * no transaction of its own: the Action that calls it owns the transaction and the locks (TR-TX-01),
 * and the session is CLOSED before its close is added (Sessions::close()).
 *
 * The report freezes, as they were at the close: the session's number, opening cash, opened time and
 * responsible user's name, which its key ties to the session; the close as recorded, which its key
 * ties to the close; the shop identity by reference to the BusinessProfile revision in force, with
 * that revision's logo, and the template version; the register's code and label; the completed
 * Sales and their applied payments, CASH and BANK_TRANSFER separately; the drawer-funded refunds of
 * the session, each separately (populated from TASK-034); and the internal keys of every source. It
 * holds no cost, margin, profit or security data (BR-CASH-20).
 */
final class SessionReports
{
    /** The kind of a close by the session's responsible user (SCHEMA section 5.10; RC-03). */
    public const string OWN = 'OWN';

    /** The kind of the Owner's close of another user's session, with its reason (SCHEMA section 5.10; RC-04; BR-CASH-18). */
    public const string OWNER_ASSISTED = 'OWNER_ASSISTED';

    /** The version of the template that renders a report of DC-02 (SCHEMA section 5.10; ARC-45). */
    public const string TEMPLATE = 'session_report.1';

    /**
     * Adds the close of the session, CLOSED: its time, its actor, the cutoff it was counted against
     * and the count (BR-CASH-13); an own close without a reason, an Owner-assisted one with the
     * Owner's reason, its actor the Owner while the responsible user stays (BR-CASH-18). The close's
     * deferred key refuses a commit without its report, and the session's presence key one without the
     * close (BR-CASH-17).
     */
    public function record(ClosingSession $session, CashCount $count, int $actorId, string $actorDisplayName, string $at, int $commandId, ?string $assistedReason = null): void
    {
        self::requireTransaction();

        DB::table('cash_session_closes')->insert([
            'session_id' => $session->id,
            'session_status' => Sessions::CLOSED,
            'closed_at' => $at,
            'close_kind' => $assistedReason === null ? self::OWN : self::OWNER_ASSISTED,
            'close_actor_user_id' => $actorId,
            'close_actor_display_name' => $actorDisplayName,
            'close_reason' => $assistedReason,
            'activity_version_at_close' => $session->activityVersion,
            'physical_cash' => $count->physical->toCanonical(),
            'expected_cash' => $count->expected->toCanonical(),
            'variance' => $count->variance->toCanonical(),
            'command_id' => $commandId,
        ]);
    }

    /**
     * Adds the report of the close just added (G-17): every figure of BR-CASH-20 as it was counted,
     * the drawer-funded refunds of the session — none before TASK-034 pays one — the keys of the
     * Sales, Payments and refunds it was derived from (BR-CASH-13), and the close's reason as the close
     * recorded it, an Owner-assisted close's (BR-CASH-20; G-17).
     *
     * @param  array{code: string, label: string}  $register
     */
    public function freeze(ClosingSession $session, array $register, ProfileRevision $revision, SessionSales $sales, CashCount $count, string $actorDisplayName, string $at, ?string $assistedReason = null): void
    {
        self::requireTransaction();

        DB::table('cash_session_reports')->insert([
            'session_id' => $session->id,
            'session_number' => $session->number,
            'opening_cash' => $session->openingCash->toCanonical(),
            'opened_at' => $session->openedAt,
            'responsible_display_name' => $session->responsibleDisplayName,
            'business_profile_revision_id' => $revision->id,
            'logo_file_id' => $revision->logoFileId,
            'template_version' => self::TEMPLATE,
            'register_code' => $register['code'],
            'register_label' => $register['label'],
            'closed_at' => $at,
            'cutoff_activity_version' => $session->activityVersion,
            'physical_cash' => $count->physical->toCanonical(),
            'expected_cash' => $count->expected->toCanonical(),
            'variance' => $count->variance->toCanonical(),
            'close_actor_display_name' => $actorDisplayName,
            'completed_sale_count' => $sales->count,
            'completed_sale_value' => $sales->value->toCanonical(),
            'cash_applied' => $sales->cashApplied->toCanonical(),
            'bank_transfer_applied' => $sales->transferApplied->toCanonical(),
            'drawer_refund_total' => 0,
            'drawer_refunds' => '[]',
            'source_ids' => json_encode(['saleIds' => $sales->saleIds, 'paymentIds' => $sales->paymentIds, 'drawerRefundIds' => []], JSON_THROW_ON_ERROR),
            'close_reason' => $assistedReason,
            'notes' => null,
        ]);
    }

    private static function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A close and its report are written inside the transaction of its Action (TR-TX-01).');
        }
    }
}
