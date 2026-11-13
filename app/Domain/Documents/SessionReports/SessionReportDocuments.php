<?php

namespace App\Domain\Documents\SessionReports;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Configuration\BusinessProfiles;
use App\Domain\Documents\Templates;
use App\Models\User;
use App\Policies\NotFound;
use App\Policies\ObjectScope;
use App\Queries\Inventory\Column as Read;
use App\Support\Numbers\Money;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The session report of a CLOSED session as its document, DC-02, and its CSV, DC-03, read from the
 * CashSessionReportSnapshot only, never from current records (BR-CASH-19, BR-CASH-20, BR-CASH-22;
 * SCHEMA section 5.10; TASK-031 scope items 2 and 3): the session's number, register, responsible
 * user, the opened time, the cutoff and the closed time, the opening cash, the completed Sales and
 * their applied CASH and BANK_TRANSFER, the drawer-funded refunds, expected and physical cash, the
 * variance, the close actor, the reason and the safe notes, with the shop's name and document
 * identity of the BusinessProfile revision the report froze, and the template version. Never a cost,
 * a margin or a profit. Times as instants in UTC for the page and, for the CSV, as `YYYY-MM-DD HH:MM`
 * in Asia/Jakarta.
 *
 * The scope (SEC-AZ-03, BR-CASH-21, BR-DOC-06): the session's responsible user while they hold
 * CAP-21, and the Owner, who reaches every session (CAP-22); anyone else, like a session that does
 * not exist or is still OPEN and so has no report, is NOT_FOUND (TR-ERR-03).
 *
 * The drawer-funded refunds are none until TASK-034 pays the first (SessionReports::freeze); a report
 * that holds one is refused here, so that their rows and the CSV's table, whose header has no key in
 * COPY_DECK section 17.2 yet, are added by that Task (evidence of TASK-031).
 *
 * @phpstan-type Report array{sessionId: int, number: string, registerCode: string, registerLabel: string, responsible: string, openedAt: string, cutoffAt: string, closedAt: string, openingCash: string, saleCount: int, saleValue: string, cashPayments: string, transferPayments: string, drawerRefunds: string, expectedCash: string, physicalCash: string, variance: string, closedBy: string, closeReason: string|null, notes: string|null, template: string}
 * @phpstan-type LocalTimes array{openedAt: string, cutoffAt: string, closedAt: string}
 */
final class SessionReportDocuments
{
    private const string COLUMNS = <<<'SQL'
        to_char(rep.opened_at at time zone 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') as opened_at,
        to_char(rep.closed_at at time zone 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US"Z"') as closed_at,
        to_char(rep.opened_at at time zone 'Asia/Jakarta', 'YYYY-MM-DD HH24:MI') as opened_local,
        to_char(rep.closed_at at time zone 'Asia/Jakarta', 'YYYY-MM-DD HH24:MI') as closed_local,
        jsonb_array_length(rep.drawer_refunds) as refund_count
        SQL;

    public function __construct(private readonly BusinessProfiles $profiles) {}

    /**
     * The report of the session in the viewer's scope, its issuer, and its times in Asia/Jakarta.
     *
     * @return array{report: Report, issuer: array{name: string, identity: string|null}, local: LocalTimes}
     *
     * @throws NotFound
     */
    public function forViewer(mixed $identifier, User $viewer): array
    {
        $key = ObjectScope::key($identifier);
        $row = $key === null ? null : DB::table('cash_session_reports as rep')
            ->join('cash_sessions as s', 's.id', '=', 'rep.session_id')
            ->where('rep.session_id', $key)
            ->select([
                'rep.session_id', 'rep.session_number', 'rep.register_code', 'rep.register_label', 'rep.responsible_display_name', 'rep.opening_cash',
                'rep.completed_sale_count', 'rep.completed_sale_value', 'rep.cash_applied', 'rep.bank_transfer_applied', 'rep.drawer_refund_total',
                'rep.expected_cash', 'rep.physical_cash', 'rep.variance', 'rep.close_actor_display_name', 'rep.close_reason', 'rep.notes', 'rep.template_version',
                'rep.business_profile_revision_id', 's.responsible_user_id',
            ])
            ->selectRaw(self::COLUMNS)
            ->first();

        if ($row === null) {
            throw new NotFound;
        }

        $values = (array) $row;
        $own = Read::int($values, 'responsible_user_id') === $viewer->id && Capabilities::allows($viewer, Capability::Cap21);

        if (! $own && ! Capabilities::allows($viewer, Capability::Cap22)) {
            throw new NotFound;
        }

        if (Read::int($values, 'refund_count') !== 0) {
            throw new LogicException('A report holds no drawer-funded refund before TASK-034, which adds their rows (SCHEMA section 5.10).');
        }

        $revision = $this->profiles->revision(Read::int($values, 'business_profile_revision_id'));

        if ($revision === null) {
            throw new LogicException('A report names a BusinessProfile revision, which is never deleted (SCHEMA section 5.2).');
        }

        $closedAt = Read::text($values, 'closed_at');

        return [
            'report' => [
                'sessionId' => Read::int($values, 'session_id'),
                'number' => Read::text($values, 'session_number'),
                'registerCode' => Read::text($values, 'register_code'),
                'registerLabel' => Read::text($values, 'register_label'),
                'responsible' => Read::text($values, 'responsible_display_name'),
                'openedAt' => Read::text($values, 'opened_at'),
                // The cutoff is the moment of the close under the session's lock (DC-02 row 4).
                'cutoffAt' => $closedAt,
                'closedAt' => $closedAt,
                'openingCash' => self::money($values, 'opening_cash'),
                'saleCount' => Read::int($values, 'completed_sale_count'),
                'saleValue' => self::money($values, 'completed_sale_value'),
                'cashPayments' => self::money($values, 'cash_applied'),
                'transferPayments' => self::money($values, 'bank_transfer_applied'),
                'drawerRefunds' => self::money($values, 'drawer_refund_total'),
                'expectedCash' => self::money($values, 'expected_cash'),
                'physicalCash' => self::money($values, 'physical_cash'),
                'variance' => self::money($values, 'variance'),
                'closedBy' => Read::text($values, 'close_actor_display_name'),
                'closeReason' => Read::nullableText($values, 'close_reason'),
                'notes' => Read::nullableText($values, 'notes'),
                'template' => Templates::sessionReport(Read::text($values, 'template_version')),
            ],
            'issuer' => ['name' => $revision->fields->shopName, 'identity' => $revision->fields->documentIdentity],
            'local' => [
                'openedAt' => Read::text($values, 'opened_local'),
                'cutoffAt' => Read::text($values, 'closed_local'),
                'closedAt' => Read::text($values, 'closed_local'),
            ],
        ];
    }

    /**
     * A stored amount as its canonical string of whole rupiah, a variance possibly negative.
     *
     * @param  array<array-key, mixed>  $values
     */
    private static function money(array $values, string $name): string
    {
        $value = $values[$name] ?? null;

        if (! is_int($value) && ! is_string($value)) {
            throw new LogicException(sprintf('The column %s holds whole rupiah (SCHEMA section 5.10).', $name));
        }

        return Money::fromDatabase($value)->toCanonical();
    }
}
