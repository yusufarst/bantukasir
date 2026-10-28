<?php

namespace App\Http\Commands;

use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandContext;
use App\Domain\Access\ActorNotAllowed;
use App\Domain\Audit\SecurityActor;
use App\Domain\Audit\SecurityEvent;
use App\Domain\Audit\SecurityLog;
use App\Domain\Audit\SecurityOutcome;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\RecoveryEpoch;
use App\Domain\Commands\RequestReceipts;
use App\DTO\Commands\CommittedData;
use App\Http\Errors\ErrorAnswer;
use App\Http\Requests\Commands\CommandRequest;
use App\Models\User;
use App\Policies\NotFound;
use App\Support\Time\Clock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;
use Throwable;

/**
 * The server side of one command request after its middleware
 * (INTERACTION_CONTRACTS section 1.1 steps 2 to 5; ADR-009 section 1;
 * TASK-008 scope item 2), in this order:
 *
 * 2. the request receipt, in a short transaction of its own: the actor, the
 *    operation, the intent, the key, the epoch and the received time, never
 *    the payload. A request received more than 30 seconds ago is refused
 *    SERVICE_BUSY before it (TR-TIME-03); one whose envelope carries no
 *    usable operation, intent, key or epoch is refused VALIDATION_FAILED
 *    before it, since such a request can never be checked or repeated; a
 *    receipt that cannot be written refuses SERVICE_BUSY with nothing run
 *    (guard G-25);
 * 3. the FormRequest: shape, allowlist, formats, limits and object scope;
 * 4. the Action, in one transaction (CommandAction);
 * 5. the marking of the receipt, replacing only PENDING — COMMITTED;
 *    REJECTED; HELD with its code for IDEMPOTENCY_CONFLICT and EPOCH_STALE;
 *    NOT_COMMITTED for INTENT_ABANDONED; SERVICE_BUSY and any server error
 *    leave it PENDING, since the outcome is uncertain — then the security
 *    log, after every transaction (SEC-LOG-03), and the answer from an
 *    allowlisted DTO.
 *
 * A marking that fails is reported and leaves the receipt PENDING, which a
 * status check settles (D-089); the answer stays the command's own.
 */
final class CommandPipeline
{
    private const string UUID = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/D';

    public function __construct(
        private readonly RequestReceipts $receipts,
        private readonly SecurityLog $securityLog,
        private readonly Clock $clock,
    ) {}

    /**
     * @param  class-string<CommandRequest>  $requestClass
     * @param  class-string<CommandAction>  $actionClass
     */
    public function run(Request $request, string $requestClass, string $actionClass): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('A command route runs only for a signed-in user (ROUTE_CONTRACTS RT-04).');
        }

        $action = app($actionClass);
        $operation = $action->operation();
        $receivedAt = ReceivedAt::of($request);

        try {
            CommandAction::refuseAfterHardEnd($receivedAt, $this->clock->now());
        } catch (CommandRefused $refusal) {
            return ErrorAnswer::refusal($refusal);
        }

        $identity = self::identity($request, $operation);

        if ($identity === null) {
            return ErrorAnswer::refusal(CommandRefused::of(ErrorCode::ValidationFailed));
        }

        try {
            $receipt = $this->receipts->record($user->id, $operation, $identity['sourceIntentId'], $identity['idempotencyKey'], $identity['recoveryEpoch'], $receivedAt->format('Y-m-d H:i:s.uP'));
        } catch (Throwable $failure) {
            report($failure);

            return ErrorAnswer::refusal(CommandRefused::of(ErrorCode::ServiceBusy));
        }

        $refusedAuthorization = false;

        try {
            $form = app($requestClass);

            if ($form->operation() !== $operation) {
                throw new LogicException('The request and the Action of a route name one operation.');
            }

            $outcome = $action->execute(new CommandContext($user->id, $form->envelope(), $receivedAt));
            $this->mark($receipt, RequestReceipts::COMMITTED);
            $answer = new JsonResponse(CommittedData::command($outcome), $outcome->repeated ? 200 : 201);
        } catch (CommandRefused $refusal) {
            $this->markRefusal($receipt, $refusal->error);
            $answer = ErrorAnswer::refusal($refusal);
        } catch (NotFound) {
            $this->markRefusal($receipt, ErrorCode::NotFound);
            $answer = ErrorAnswer::refusal(CommandRefused::of(ErrorCode::NotFound));
        } catch (ActorNotAllowed|AuthorizationException) {
            $this->markRefusal($receipt, ErrorCode::Forbidden);
            $refusedAuthorization = true;
            $answer = ErrorAnswer::refusal(CommandRefused::of(ErrorCode::Forbidden));
        }

        if ($refusedAuthorization) {
            $this->securityLog->record(SecurityEvent::AuthorizationRefused, SecurityActor::user($user->id), SecurityOutcome::Refused, $request, $user->id);
        }

        return $answer;
    }

    /**
     * The operation, intent, key and epoch of the envelope, as the receipt
     * needs them, or null when one is missing or malformed.
     *
     * @return array{sourceIntentId: string, idempotencyKey: string, recoveryEpoch: string}|null
     */
    private static function identity(Request $request, string $operation): ?array
    {
        $intent = $request->input('sourceIntentId');
        $key = $request->input('idempotencyKey');
        $epoch = $request->input('recoveryEpoch');

        if ($request->input('operation') !== $operation
            || ! is_string($intent) || preg_match(self::UUID, $intent) !== 1
            || ! is_string($key) || preg_match(self::UUID, $key) !== 1
            || ! is_string($epoch) || ! RecoveryEpoch::wellFormed($epoch)) {
            return null;
        }

        return ['sourceIntentId' => strtolower($intent), 'idempotencyKey' => strtolower($key), 'recoveryEpoch' => $epoch];
    }

    private function markRefusal(int $receipt, ErrorCode $code): void
    {
        match ($code) {
            ErrorCode::ServiceBusy => null,
            ErrorCode::IdempotencyConflict, ErrorCode::EpochStale => $this->mark($receipt, RequestReceipts::HELD, $code->value),
            ErrorCode::IntentAbandoned => $this->mark($receipt, RequestReceipts::NOT_COMMITTED),
            default => $this->mark($receipt, RequestReceipts::REJECTED),
        };
    }

    private function mark(int $receipt, string $outcome, ?string $heldCode = null): void
    {
        try {
            $this->receipts->mark($receipt, $outcome, $heldCode);
        } catch (Throwable $failure) {
            report($failure);
        }
    }
}
