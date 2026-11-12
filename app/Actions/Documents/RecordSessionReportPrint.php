<?php

namespace App\Actions\Documents;

use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandContext;
use App\Actions\Commands\CommandServices;
use App\Domain\Access\Capability;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Domain\Documents\SessionReports\SessionReportPrints;
use App\Models\User;
use LogicException;

/**
 * RC-45 `document.print_session_report` (INTERACTION_CONTRACTS, "RC-44 to RC-47"; TECHNICAL_RULES
 * section 7.2; DOCUMENT_CONTRACTS DC-02; BR-CASH-21, BR-CASH-22, BR-AUD-06; TASK-031 scope item 2): a
 * print request of a CLOSED session's report, by its responsible user while they hold CAP-21 or by the
 * Owner (CAP-22); an OPEN session, which has no report, another user's and one that does not exist are
 * NOT_FOUND. It takes no lock: the report is immutable (BR-CASH-19). It writes only its audit event
 * PRINT and its result, never a session's change; a failed print is simply requested again and renders
 * the same snapshot (BR-CASH-22). The answer leads to RP-206P.
 *
 * Write steps (TEST_STRATEGY TS-21): the audit event, the result.
 */
final class RecordSessionReportPrint extends CommandAction
{
    public const string OPERATION = 'document.print_session_report';

    /** The result's type (SCHEMA section 5.3). */
    public const string RESULT = 'session_report_print';

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
            SessionReportPrints::PRINT,
            Actor::user($actor->id, $actor->display_name),
            [],
            subject: ['type' => SessionReportPrints::AUDIT_SUBJECT, 'id' => $report['id']],
            reference: $report['number'],
            commandId: $commandId,
            cashSessionId: $report['id'],
        );
        $this->written();

        return new StoredResult(self::RESULT, $commandId, ['sessionId' => $report['id']]);
    }

    /**
     * @return array{type: string, id: int|null, location: string}|null
     */
    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        $session = $result->detail['sessionId'] ?? null;

        if (! is_int($session)) {
            throw new LogicException('A print request stored its session.');
        }

        return $this->reports->reportOf($session, $viewer) === null ? null : ['type' => $result->type, 'id' => $result->ref, 'location' => SessionReportPrints::path($session, $commandId)];
    }
}
