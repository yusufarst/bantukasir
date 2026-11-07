<?php

namespace App\Actions\Cash;

use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandContext;
use App\Actions\Commands\CommandServices;
use App\Database\SqlState;
use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Cash\Registers;
use App\Domain\Cash\Sessions;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Domain\Configuration\BusinessProfiles;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/**
 * RC-02 `cash_session.open` (INTERACTION_CONTRACTS RC-02; TECHNICAL_RULES section 7.2, "Open cash
 * session", TR-TX-10, RACE-07 and RACE-26; SCHEMA sections 5.10 and 6; BR-CASH-04 to BR-CASH-07;
 * G-18, G-19, G-23; CAP-21; TASK-027 scope item 3): a CASHIER, the Owner or a combined account
 * opens a session as themselves, with the explicit opening cash they counted — Rp0 is valid, a blank
 * amount is refused by the FormRequest. Steps 1, 2.1, 3 and 8:
 *
 * - the register under its update lock (step 3), which a second open of it and a configuration
 *   change wait for (RACE-07, RACE-26);
 * - under that lock: the register active and without an OPEN session (VALIDATION_FAILED at
 *   `registerId`), and the actor without an OPEN session (VALIDATION_FAILED at the form); a
 *   concurrent open by the actor on another register is refused by the unique index at the insert,
 *   in the same way (BR-CASH-06);
 * - a BusinessProfile revision exists, which the bootstrap writes and no command removes (G-23);
 * - at step 8, the time, its business date and the number, then the OPEN session, its audit event
 *   and the result, all or nothing (TR-TX-01). Opening creates no Sale and no movement.
 *
 * The responsible user is the actor, never a field of the request (TR-VAL-03), and the name is
 * frozen from the actor's row that the guard holds (G-16). The answer's location is RN-01, the
 * user's home, until TASK-029 delivers RP-101 (EXECUTION_CONTEXT EXC-52).
 *
 * Write steps (TEST_STRATEGY TS-21): the session, the audit event, the result.
 */
final class OpenCashSession extends CommandAction
{
    public const string OPERATION = 'cash_session.open';

    /** The result's type (SCHEMA section 5.3). */
    public const string RESULT_TYPE = 'cash_session';

    /** RN-01, until RP-101 exists (TASK-029). */
    public const string LOCATION = '/';

    public function __construct(
        CommandServices $services,
        private readonly Registers $registers,
        private readonly Sessions $sessions,
        private readonly BusinessProfiles $profiles,
    ) {
        parent::__construct($services);
    }

    public function operation(): string
    {
        return self::OPERATION;
    }

    public function capabilities(): array
    {
        return [Capability::Cap21];
    }

    /**
     * The session, its number and its page for its responsible user, and for the Owner, who sees
     * every session (CAP-22); nobody else (SEC-AZ-06).
     */
    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        $session = DB::table('cash_sessions')->where('id', $result->ref)->first(['responsible_user_id', 'session_number']);
        $values = $session === null ? [] : (array) $session;
        $number = $values['session_number'] ?? null;

        if (! is_string($number) || (($values['responsible_user_id'] ?? null) !== $viewer->id && ! Capabilities::allows($viewer, Capability::Cap22))) {
            return null;
        }

        return ['type' => $result->type, 'id' => $result->ref, 'number' => $number, 'location' => self::LOCATION];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $payload = $context->envelope->payload;
        $registerId = $payload['registerId'] ?? null;
        $openingCash = $payload['openingCash'] ?? null;

        if (! is_int($registerId) || ! is_string($openingCash) || ! is_numeric($openingCash)) {
            throw new LogicException('The payload was validated before the Action.');
        }

        $register = $this->registers->lock($registerId);
        $this->services->points->afterLock('3');

        if ($register === null) {
            throw CommandRefused::of(ErrorCode::NotFound);
        }

        if (! $register->active || $this->sessions->openOn($register->id)) {
            throw self::registerTaken();
        }

        if ($this->sessions->openFor($actor->id)) {
            throw self::actorHoldsSession();
        }

        // G-23: the bootstrap wrote the first revision, and no command removes one (BR-CFG-02).
        if ($this->profiles->current() === null) {
            throw new LogicException('A BusinessProfile revision exists after the bootstrap (G-23).');
        }

        try {
            $session = $this->sessions->open($register->id, $actor->id, $actor->display_name, $openingCash, $commandId);
        } catch (Throwable $failure) {
            throw match (true) {
                SqlState::uniqueOn($failure, Sessions::ONE_OPEN_PER_USER) => self::actorHoldsSession(),
                SqlState::uniqueOn($failure, Sessions::ONE_OPEN_PER_REGISTER) => self::registerTaken(),
                default => $failure,
            };
        }

        $this->written();

        $this->services->audit->record(
            AuditCategory::Business,
            'CASH_SESSION_OPEN',
            Actor::user($actor->id, $actor->display_name),
            ['number' => $session->number, 'register' => $register->code, 'openingCash' => $openingCash],
            subject: ['type' => self::RESULT_TYPE, 'id' => $session->id],
            commandId: $commandId,
            cashSessionId: $session->id,
        );
        $this->written();

        return new StoredResult(self::RESULT_TYPE, $session->id, ['number' => $session->number]);
    }

    /** The register is inactive or in use (COPY_DECK section 16.1, NAVIGATION_CONTRACTS UXP-104). */
    private static function registerTaken(): CommandRefused
    {
        return new CommandRefused(ErrorCode::ValidationFailed, fields: ['payload.registerId' => [['key' => 'ux.validation.register_taken', 'params' => []]]]);
    }

    /**
     * The actor is already responsible for an OPEN session: a refusal of the form, which the page
     * answers by loading the user's home again (BR-CASH-06).
     */
    private static function actorHoldsSession(): CommandRefused
    {
        return CommandRefused::of(ErrorCode::ValidationFailed);
    }
}
