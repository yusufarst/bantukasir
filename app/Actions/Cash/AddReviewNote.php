<?php

namespace App\Actions\Cash;

use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandContext;
use App\Actions\Commands\CommandServices;
use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Cash\ReviewNotes;
use App\Domain\Cash\Sessions;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Models\User;
use LogicException;

/**
 * RC-43 `review_note.add` (INTERACTION_CONTRACTS RC-43; ROUTE_CONTRACTS section 6; TECHNICAL_RULES section
 * 7.2, "Owner review note on a CLOSED session"; SCHEMA section 5.10; BR-CASH-19; D-068, D-069 (f); CAP-22;
 * WF-10; TASK-032 scope item 4). The Owner adds a note to a CLOSED session, steps 1, 2.1, 4 and 8:
 *
 * - the session under a share lock (step 4; SCHEMA section 7: FOR SHARE), so that a close never
 *   interleaves with it; a session that does not exist is NOT_FOUND, one still OPEN STALE_VERSION;
 * - at step 8, the note with its time, the Owner's frozen name and the command, then the audit event
 *   REVIEW_NOTE_ADD and the result, all or nothing (TR-TX-01).
 *
 * The note is append-only: several per session, never edited or removed; a correction is a new note. It
 * changes no count, variance or report (BR-CASH-19). Its author is the actor, never a field of the
 * request (TR-VAL-03). The answer's location is RP-206, in place.
 *
 * Write steps (TEST_STRATEGY TS-21): the note, the audit event, the result.
 */
final class AddReviewNote extends CommandAction
{
    public const string OPERATION = 'review_note.add';

    /** The result's type (SCHEMA section 5.3): the note, whose session's page shows it. */
    public const string RESULT_TYPE = 'cash_session_review_note';

    public function __construct(
        CommandServices $services,
        private readonly Sessions $sessions,
        private readonly ReviewNotes $notes,
    ) {
        parent::__construct($services);
    }

    public function operation(): string
    {
        return self::OPERATION;
    }

    public function capabilities(): array
    {
        return [Capability::Cap22];
    }

    /**
     * The note and its session's page, RP-206, for the Owner (CAP-22); nobody else (SEC-AZ-06).
     */
    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        $session = $result->ref === null ? null : $this->notes->sessionOf($result->ref);

        if ($session === null || ! Capabilities::allows($viewer, Capability::Cap22)) {
            return null;
        }

        return ['type' => $result->type, 'id' => $result->ref, 'location' => Sessions::detailPath($session)];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $payload = $context->envelope->payload;
        $sessionId = $payload['sessionId'] ?? null;
        $text = $payload['text'] ?? null;

        if (! is_int($sessionId) || ! is_string($text) || $text === '') {
            throw new LogicException('The envelope was validated before the Action.');
        }

        $session = $this->sessions->lockForNote($sessionId);
        $this->services->points->afterLock('4');

        if ($session === null) {
            throw CommandRefused::of(ErrorCode::NotFound);
        }

        // A note belongs to a CLOSED session only (RC-43; BR-CASH-19).
        if (! $session['closed']) {
            throw CommandRefused::of(ErrorCode::StaleVersion);
        }

        $at = $this->services->clock->now()->format('Y-m-d H:i:s.uP');
        $note = $this->notes->add($sessionId, $text, $actor->id, $actor->display_name, $at, $commandId);
        $this->written();

        $this->services->audit->record(
            AuditCategory::Business,
            'REVIEW_NOTE_ADD',
            Actor::user($actor->id, $actor->display_name),
            ['number' => $session['number'], 'noteId' => $note],
            subject: ['type' => self::RESULT_TYPE, 'id' => $note],
            commandId: $commandId,
            cashSessionId: $sessionId,
        );
        $this->written();

        return new StoredResult(self::RESULT_TYPE, $note);
    }
}
