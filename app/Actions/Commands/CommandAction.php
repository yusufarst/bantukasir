<?php

namespace App\Actions\Commands;

use App\Database\SqlState;
use App\Domain\Access\ActorNotAllowed;
use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Commands\CommandIdentities;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredIdentity;
use App\Domain\Commands\StoredResult;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use LogicException;
use Throwable;

/**
 * The base of every command's Action (ADR-007; INTERACTION_CONTRACTS section
 * 1.1 step 4; TECHNICAL_RULES sections 6, 7 and 9; TASK-008 scope items 2 to
 * 4). One transaction, in this order:
 *
 * 1. the command identity, the first write (TR §7.1 step 1), on which a second
 *    request with the same identity waits;
 * 2. the epoch check, guard G-12: an earlier epoch is refused EPOCH_STALE
 *    before any fact;
 * 3. the actor guard, step 2.1 (SEC-AZ-04): the account ACTIVE and still
 *    holding every capability of the command;
 * 4. the command's own locks, checks under the locks, facts, projections and
 *    audit event (perform(), which calls written() after each write);
 * 5. the durable result, references only (ADR-007 section 2), whose presence
 *    key refuses a commit without it.
 *
 * Before any write the hard end of TR-TIME-03 holds: a command that starts
 * more than 30 seconds after its request was received is refused
 * SERVICE_BUSY. A deadlock or a serialization failure retries the whole
 * transaction with the same identity, at most three attempts, with a jittered
 * backoff of 50 to 250 ms, only within 15 seconds of the first attempt and
 * never for a bounded heavy command (TR-RETRY-01); after that, and on a lock
 * wait beyond the lock timeout (TR-RETRY-02), the answer is SERVICE_BUSY.
 *
 * A conflict on the identity is answered after the rollback by reading the
 * original (TR-TX-08; ADR-007 section 3): the original result, rebuilt under
 * the actor's current authorization, for the same fingerprint;
 * IDEMPOTENCY_CONFLICT for another; INTENT_ABANDONED for a resolution's fence.
 * An EPOCH_STALE refusal records, after the rollback and in a transaction of
 * its own, the intent's stale-intent report if absent and holds its PENDING
 * receipts of an earlier epoch (ADR-009 section 3).
 */
abstract class CommandAction
{
    /** TR-TIME-03: a command starting later than this after its receipt is refused. */
    public const int HARD_END_SECONDS = 30;

    /** TR-RETRY-01: attempts in total, and the backoff between them. */
    public const int ATTEMPTS = 3;

    public const int BACKOFF_MIN_MS = 50;

    public const int BACKOFF_MAX_MS = 250;

    /** The identity's unique keys, whose conflict is read after the rollback. */
    private const array IDENTITY_KEYS = ['command_identities_intent_unique', 'command_identities_user_key'];

    private int $writeStep = 0;

    /** @var array{url: string, expiresAt: string}|null the link this attempt hands over, never stored */
    private ?array $handedOver = null;

    private ?int $sessionsEndedOf = null;

    public function __construct(protected readonly CommandServices $services) {}

    /**
     * The operation of ROUTE_CONTRACTS section 6.
     */
    abstract public function operation(): string;

    /**
     * Every capability the actor must hold, checked by the actor guard in
     * the transaction and again before an original result is returned.
     *
     * @return non-empty-list<Capability>
     */
    abstract public function capabilities(): array;

    /**
     * The command's own work in its transaction, after the identity, the
     * epoch and the actor guard: its locks in the order of TR §7.1, its checks
     * under the locks (TR-VAL-08), its facts, projections and audit event,
     * each write followed by written(). A definite refusal throws
     * CommandRefused, which rolls everything back.
     */
    abstract protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult;

    /**
     * The result of the answer, rebuilt from the stored references for this
     * viewer under the viewer's current authorization and object scope, or
     * null when the viewer may not see it (ADR-007 section 3, SEC-AZ-06,
     * SEC-CH-11): the type, the internal key, the document number of a
     * numbered record and the path of ROUTE_CONTRACTS that shows it.
     *
     * @return array{type: string, id: int|null, number?: string, location: string}|null
     */
    abstract public function present(User $viewer, int $commandId, StoredResult $result): ?array;

    /**
     * A bounded heavy command (TECHNICAL_RULES section 9): a budget of 30
     * seconds, never retried.
     */
    protected function heavy(): bool
    {
        return false;
    }

    /**
     * Whether the target of step 2.2 is the actor's own account: the actor
     * guard then takes that update lock instead, once (TR-LOCK-05).
     */
    protected function ownAccountIsTarget(): bool
    {
        return false;
    }

    /**
     * The result of the answer committed now; by default the same as a
     * repeat's and a status check's. An issued link leads to its one-time page
     * in this answer only (SEC-ACC-05).
     *
     * @return array{type: string, id: int|null, number?: string, location: string}|null
     */
    protected function presentNow(User $viewer, int $commandId, StoredResult $result): ?array
    {
        return $this->present($viewer, $commandId, $result);
    }

    /**
     * The one-time link of this commit, for its answer only: it is never
     * stored, so no repeat or status check can show it again (SEC-ACC-05).
     */
    final protected function handOver(string $url, string $expiresAt): void
    {
        $this->handedOver = ['url' => $url, 'expiresAt' => $expiresAt];
    }

    /**
     * Records that this commit ended the sign-in sessions of the account, for
     * the security log entry written after the transaction (SEC-LOG-03).
     */
    final protected function sessionsEnded(int $userId): void
    {
        $this->sessionsEndedOf = $userId;
    }

    /**
     * The command's work before its transaction, once per request and outside
     * it: an upload is validated by decoding and written under a new random
     * key before the transaction, which then records the reference
     * (TECHNICAL_RULES TR-TX-05; ARCHITECTURE ARC-41). Nothing by default. A
     * refusal here writes no fact.
     *
     * @throws CommandRefused
     */
    protected function beforeTransaction(CommandContext $context): void {}

    /**
     * Runs the command.
     *
     * @throws CommandRefused a definite refusal, or SERVICE_BUSY
     * @throws ActorNotAllowed FORBIDDEN, from the actor guard or the check of an original result
     */
    final public function execute(CommandContext $context): CommandOutcome
    {
        if ($context->envelope->operation !== $this->operation()) {
            throw new LogicException('The envelope names another operation than its Action.');
        }

        $first = $this->services->clock->now();
        self::refuseAfterHardEnd($context->receivedAt, $first);
        $this->beforeTransaction($context);
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return $this->attempt($context);
            } catch (CommandRefused $refusal) {
                if ($refusal->error === ErrorCode::EpochStale) {
                    $this->reportStale($context);
                }

                throw $refusal;
            } catch (Throwable $failure) {
                if (SqlState::uniqueOn($failure, self::IDENTITY_KEYS)) {
                    $original = $this->original($context);

                    if ($original !== null) {
                        return $original;
                    }
                } elseif (SqlState::of($failure) === SqlState::LOCK_NOT_AVAILABLE) {
                    throw CommandRefused::of(ErrorCode::ServiceBusy);
                } elseif (! SqlState::retryable($failure)) {
                    throw $failure;
                }

                $elapsed = $first->diffInMilliseconds($this->services->clock->now(), true);

                if ($this->heavy() || $attempt >= self::ATTEMPTS || $elapsed >= self::retryWindowMilliseconds()) {
                    throw CommandRefused::of(ErrorCode::ServiceBusy);
                }

                usleep(random_int(self::BACKOFF_MIN_MS, self::BACKOFF_MAX_MS) * 1000);
            }
        }
    }

    /**
     * Refuses SERVICE_BUSY, before any write, a request received more than 30
     * seconds ago (TR-TIME-03).
     */
    public static function refuseAfterHardEnd(CarbonImmutable $receivedAt, CarbonImmutable $now): void
    {
        if ($receivedAt->diffInMilliseconds($now, false) > self::HARD_END_SECONDS * 1000) {
            throw CommandRefused::of(ErrorCode::ServiceBusy);
        }
    }

    /**
     * Records that write step k of the command is done and calls its point
     * (CommandPoints::afterWrite), counting from 1 in each attempt.
     */
    protected function written(): void
    {
        $this->services->points->afterWrite(++$this->writeStep);
    }

    private function attempt(CommandContext $context): CommandOutcome
    {
        $this->writeStep = 0;
        $this->handedOver = null;
        $this->sessionsEndedOf = null;
        $services = $this->services;
        $envelope = $context->envelope;

        [$commandId, $actor, $result] = CommandTransaction::run(function (Connection $connection) use ($context, $services, $envelope): array {
            $commandId = $services->identities->insertCommand($envelope, $context->actorId, $context->fingerprint, $context->receivedAtText());
            $services->points->afterIdentity();

            // G-12, before any fact; the epoch needs no lock (SCHEMA section 5.3).
            if ($services->epoch->current() !== $envelope->recoveryEpoch) {
                throw CommandRefused::of(ErrorCode::EpochStale);
            }

            $actor = $services->actorGuard->hold($context->actorId, $this->capabilities(), $this->ownAccountIsTarget());
            $services->points->afterLock('2.1');

            $result = $this->perform($context, $actor, $commandId);

            $services->identities->recordResult($commandId, $result);
            $this->written();
            $services->points->beforeCommit();

            return [$commandId, $actor, $result];
        }, $this->heavy());

        return new CommandOutcome($envelope->operation, $commandId, false, $this->presentNow($actor, $commandId, $result), $this->handedOver, $this->sessionsEndedOf);
    }

    /**
     * The original of a conflicting identity, read after the rollback, or
     * null when none is there any more and the attempt is to be repeated.
     */
    private function original(CommandContext $context): ?CommandOutcome
    {
        $envelope = $context->envelope;
        $identities = $this->services->identities;
        $identity = $identities->byKey($context->actorId, $envelope->operation, $envelope->idempotencyKey)
            ?? $identities->byIntent($envelope->operation, $envelope->sourceIntentId);

        if (! $identity instanceof StoredIdentity) {
            return null;
        }

        if ($identity->isFence()) {
            throw CommandRefused::of(ErrorCode::IntentAbandoned);
        }

        if ($identity->kind !== CommandIdentities::COMMAND || $identity->fingerprint !== $context->fingerprint) {
            throw CommandRefused::of(ErrorCode::IdempotencyConflict);
        }

        // The actor's current authorization, read again (ADR-007 section 3).
        $actor = User::query()->find($context->actorId);

        if (! $actor instanceof User || ! $actor->isActive()) {
            throw new ActorNotAllowed;
        }

        foreach ($this->capabilities() as $capability) {
            if (! Capabilities::allows($actor, $capability)) {
                throw new ActorNotAllowed;
            }
        }

        return new CommandOutcome($envelope->operation, $identity->id, true, $this->presented($actor, $identity->id, $identities->resultOf($identity->id)));
    }

    /**
     * The result for the answer, or null when the viewer may not see it. A
     * committed command is always answered COMMITTED, never refused, so that
     * neither the client nor its receipt reads it as a rejection (review
     * finding R-05; BR-CMD-03).
     *
     * @return array{type: string, id: int|null, number?: string, location: string}|null
     */
    private function presented(User $viewer, int $commandId, StoredResult $result): ?array
    {
        return $this->present($viewer, $commandId, $result);
    }

    /**
     * After an EPOCH_STALE refusal: the report if absent and the intent's
     * PENDING receipts of an earlier epoch HELD, in a transaction of its own.
     * If that transaction fails, the answer is SERVICE_BUSY, never a refusal
     * without its report.
     */
    private function reportStale(CommandContext $context): void
    {
        $services = $this->services;
        $envelope = $context->envelope;

        try {
            CommandTransaction::run(static function () use ($services, $envelope, $context): void {
                $services->reports->reportIfAbsent($envelope->operation, $envelope->sourceIntentId, $context->actorId, $envelope->recoveryEpoch);
                $services->receipts->holdStale($envelope->operation, $envelope->sourceIntentId, $services->epoch->current());
                $services->points->afterOwnWrite('report');
            });
        } catch (Throwable $failure) {
            report($failure);

            throw CommandRefused::of(ErrorCode::ServiceBusy);
        }
    }

    private static function retryWindowMilliseconds(): int
    {
        $window = config()->string('database.limits.retry_window');

        return preg_match('/^([0-9]+)s$/', $window, $match) === 1 ? (int) $match[1] * 1000 : 15000;
    }
}
