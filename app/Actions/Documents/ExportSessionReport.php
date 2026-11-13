<?php

namespace App\Actions\Documents;

use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandContext;
use App\Actions\Commands\CommandServices;
use App\Domain\Access\Capability;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Cash\Sessions;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Domain\Documents\SessionReports\SessionReportPrints;
use App\Models\User;
use LogicException;

/**
 * RC-46 `document.export_session_report` (INTERACTION_CONTRACTS, "RC-44 to RC-47"; TECHNICAL_RULES
 * section 7.2; DOCUMENT_CONTRACTS DC-03; BR-CASH-21, BR-CASH-22, BR-AUD-06; ARC-46; TASK-031 scope item
 * 3): an export request of a CLOSED session's report as the CSV of DC-03, with the scope of RC-45. It
 * takes no lock and writes only its audit event EXPORT and its result, never a session's change. The
 * file is not stored: the controller renders it from the report's immutable snapshot for the answer
 * (ExportSessionReportController), so that a repeat with the same identity, and a retry after a failed
 * download, return the same file (BR-CASH-22). The result names the session and its number, with
 * RP-206 as its location.
 *
 * Write steps (TEST_STRATEGY TS-21): the audit event, the result.
 */
final class ExportSessionReport extends CommandAction
{
    public const string OPERATION = 'document.export_session_report';

    /** The result's type (SCHEMA section 5.3). */
    public const string RESULT = 'session_report_export';

    /** The format of the file, the only one of V1 (DC-03). */
    private const string FORMAT = 'CSV';

    public function __construct(CommandServices $services, private readonly SessionReportPrints $reports)
    {
        parent::__construct($services);
    }

    public function operation(): string
    {
        return self::OPERATION;
    }

    /**
     * CAP-21, which the Owner holds too, for the actor guard; another user's session needs CAP-22,
     * which the scope checks.
     */
    public function capabilities(): array
    {
        return [Capability::Cap21];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $sessionId = $context->envelope->payload['sessionId'] ?? null;

        if (! is_int($sessionId)) {
            throw new LogicException('The payload was validated before the Action.');
        }

        $report = $this->reports->reportOf($sessionId, $actor) ?? throw CommandRefused::of(ErrorCode::NotFound);

        $this->services->audit->record(
            AuditCategory::Business,
            SessionReportPrints::EXPORT,
            Actor::user($actor->id, $actor->display_name),
            ['format' => self::FORMAT],
            subject: ['type' => SessionReportPrints::AUDIT_SUBJECT, 'id' => $report['id']],
            reference: $report['number'],
            commandId: $commandId,
            cashSessionId: $report['id'],
        );
        $this->written();

        return new StoredResult(self::RESULT, $report['id'], ['number' => $report['number']]);
    }

    /**
     * The session whose report was exported, for this viewer; null for one who may not see it any more.
     *
     * @return array{type: string, id: int|null, number: string, location: string}|null
     */
    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        $session = $result->ref;
        $report = $session === null ? null : $this->reports->reportOf($session, $viewer);

        return $report === null ? null : ['type' => $result->type, 'id' => $report['id'], 'number' => $report['number'], 'location' => Sessions::detailPath($report['id'])];
    }
}
