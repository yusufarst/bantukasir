<?php

namespace App\Domain\Documents\SessionReports;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Models\User;
use App\Queries\Inventory\Column as Read;
use Illuminate\Support\Facades\DB;

/**
 * The print and export requests of a session report (RC-45, RC-46, RP-206P; DOCUMENT_CONTRACTS DC-02,
 * DC-03; BR-CASH-22, BR-AUD-06; TASK-031 scope items 2 and 3): each request is the committed command,
 * whose only record is its audit event — PRINT or EXPORT — about the session's report (subject
 * `cash_session_report`, the session's key), with the session's number as its reference and the
 * session as its filter (BR-AUD-04). RP-206P reads a print request's event, and rendering it is a read
 * (TR-HTTP-02). No copy marker is printed on a session report (DC-02).
 */
final class SessionReportPrints
{
    public const string PRINT = 'PRINT';

    public const string EXPORT = 'EXPORT';

    public const string AUDIT_SUBJECT = 'cash_session_report';

    /**
     * The address of RP-206P for a print request of a session's report.
     */
    public static function path(int $session, int $printRequest): string
    {
        return '/cash-sessions/'.(string) $session.'/report/print/'.(string) $printRequest;
    }

    /**
     * The CLOSED session of RC-45 and RC-46 in the actor's scope — its responsible user while they
     * hold CAP-21, or the Owner (CAP-22; BR-CASH-21) — by its report, which only a CLOSED session has:
     * its key and number; null otherwise, which the command answers NOT_FOUND (TR-ERR-03).
     *
     * @return array{id: int, number: string}|null
     */
    public function reportOf(int $session, User $actor): ?array
    {
        $row = DB::table('cash_session_reports as rep')
            ->join('cash_sessions as s', 's.id', '=', 'rep.session_id')
            ->where('rep.session_id', $session)
            ->first(['rep.session_id', 'rep.session_number', 's.responsible_user_id']);

        if ($row === null) {
            return null;
        }

        $values = (array) $row;
        $own = Read::int($values, 'responsible_user_id') === $actor->id && Capabilities::allows($actor, Capability::Cap21);

        if (! $own && ! Capabilities::allows($actor, Capability::Cap22)) {
            return null;
        }

        return ['id' => Read::int($values, 'session_id'), 'number' => Read::text($values, 'session_number')];
    }

    /**
     * Whether the command is a committed RC-45 of this session's report; RP-206P answers any other
     * command, session or key NOT_FOUND.
     */
    public function exists(int $session, int $printRequest): bool
    {
        return DB::table('audit_events')
            ->where('command_id', $printRequest)
            ->where('action', self::PRINT)
            ->where('subject_type', self::AUDIT_SUBJECT)
            ->where('subject_id', $session)
            ->exists();
    }
}
