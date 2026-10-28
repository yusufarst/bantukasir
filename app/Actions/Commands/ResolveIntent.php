<?php

namespace App\Actions\Commands;

use App\Database\SqlState;
use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Access\Grant;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\RequestReceipts;
use App\Domain\Commands\StoredIdentity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * RC-50, the status check of an uncertain intent (ADR-007 section 4; ADR-009
 * sections 1 and 3; INTERACTION_CONTRACTS section 2.1; TECHNICAL_RULES
 * section 7.2, "Resolution of an uncertain intent"; D-089; TASK-008 scope
 * item 5). The intent's actor may check it, and the Owner any user's intent
 * (CAP-34); anyone else gets NOT_FOUND and changes nothing. It carries no
 * envelope and writes no receipt.
 *
 * - A COMMAND identity of the operation and intent — also one of an earlier
 *   epoch present in restored data — answers COMMITTED with the result
 *   rebuilt under the checker's authorization (never a one-time link again),
 *   and settles the intent's PENDING or HELD receipts as COMMITTED in a short
 *   transaction of its own.
 * - An existing FENCE — the one a check wrote or a restore reconciliation's —
 *   answers NOT_COMMITTED and settles them as NOT_COMMITTED likewise.
 * - No identity, the current epoch: a FENCE with the checker as actor is
 *   written in a transaction of its own, which settles the receipts as
 *   NOT_COMMITTED before it commits; an original that still holds the
 *   identity makes the fence wait, and when the wait reaches the lock timeout
 *   the answer is IN_PROGRESS, which settles nothing; an original that
 *   commits meanwhile is read and answered COMMITTED (RACE-27, RACE-33).
 * - No identity, an earlier epoch: EPOCH_STALE, after a transaction of its
 *   own that records the stale-intent report if absent and holds the
 *   intent's PENDING receipts of an earlier epoch. The server's knowledge
 *   decides the epoch — a stale-intent report, or a receipt that carried
 *   another epoch, read again under the fence — and the epoch the check
 *   sends only for an intent of which it holds no trace (review finding
 *   R-02).
 *
 * The receipts are locked in ascending order and no session lock is taken.
 * A conclusive answer is given only after its settlement committed; if that
 * fails, the answer is SERVICE_BUSY and the state is unchanged.
 */
final class ResolveIntent
{
    public function __construct(private readonly CommandServices $services) {}

    /**
     * @throws CommandRefused NOT_FOUND, EPOCH_STALE or SERVICE_BUSY
     */
    public function __invoke(User $checker, string $operation, string $sourceIntentId, string $recoveryEpoch, CarbonImmutable $receivedAt): Resolution
    {
        CommandAction::refuseAfterHardEnd($receivedAt, $this->services->clock->now());
        $owner = Capabilities::of($checker, Capability::Cap34) === Grant::Full;
        $identity = $this->services->identities->byIntent($operation, $sourceIntentId);

        if ($identity instanceof StoredIdentity) {
            return $this->concluded($checker, $owner, $identity);
        }

        $current = $this->services->epoch->current();
        [$receiptActors, $reportActor, $earlier] = $this->known($operation, $sourceIntentId, $recoveryEpoch, $current);

        if (! $owner && ! self::ownsWithoutIdentity($checker, $receiptActors, $reportActor)) {
            throw CommandRefused::of(ErrorCode::NotFound);
        }

        if ($current === null || $earlier !== null) {
            $this->reportStale($operation, $sourceIntentId, $reportActor ?? $receiptActors[0] ?? $checker->id, $earlier ?? $recoveryEpoch, $current);

            throw CommandRefused::of(ErrorCode::EpochStale);
        }

        $fenced = false;

        try {
            CommandTransaction::run(function () use ($checker, $owner, $operation, $sourceIntentId, $recoveryEpoch, $current, $receivedAt, &$fenced): void {
                $services = $this->services;
                $fenceId = $services->identities->insertFence($operation, $sourceIntentId, $checker->id, $current, $receivedAt->format('Y-m-d H:i:s.uP'));
                $fenced = true;
                $services->points->afterIdentity();

                // Read again under the fence, on which every later original of
                // the intent waits: a receipt or report committed since the
                // first read can neither make the intent another user's nor
                // of an earlier epoch unnoticed (review findings R-02, R-09).
                // A receipt committed after this read is settled with the
                // fence, as one of an original arriving just after it would be.
                [$actors, $reporter, $earlier] = $this->known($operation, $sourceIntentId, $recoveryEpoch, $current);

                if (! $owner && ! self::ownsWithoutIdentity($checker, $actors, $reporter)) {
                    throw CommandRefused::of(ErrorCode::NotFound);
                }

                if ($earlier !== null) {
                    throw new StaleIntentFound($earlier, $reporter ?? $actors[0] ?? $checker->id);
                }

                $services->receipts->settle($operation, $sourceIntentId, RequestReceipts::NOT_COMMITTED, $fenceId, $checker->id);
                $services->points->afterWrite(1);
                $services->points->beforeCommit();
            });
        } catch (StaleIntentFound $stale) {
            $this->reportStale($operation, $sourceIntentId, $stale->actorId, $stale->epoch, $current);

            throw CommandRefused::of(ErrorCode::EpochStale);
        } catch (Throwable $failure) {
            if (SqlState::uniqueOn($failure, ['command_identities_intent_unique'])) {
                // An original or another fence committed first: answer it.
                $identity = $this->services->identities->byIntent($operation, $sourceIntentId);

                return $identity instanceof StoredIdentity
                    ? $this->concluded($checker, $owner, $identity)
                    : throw CommandRefused::of(ErrorCode::ServiceBusy);
            }

            if (SqlState::of($failure) === SqlState::LOCK_NOT_AVAILABLE) {
                // The original still holds the identity: it is running.
                if (! $fenced) {
                    return new Resolution(Resolution::IN_PROGRESS, $operation);
                }

                throw CommandRefused::of(ErrorCode::ServiceBusy);
            }

            if ($failure instanceof CommandRefused) {
                throw $failure;
            }

            report($failure);

            throw CommandRefused::of(ErrorCode::ServiceBusy);
        }

        return new Resolution(Resolution::NOT_COMMITTED, $operation);
    }

    /**
     * What the server knows of an intent without an identity: the actors of
     * its receipts, the actor of its stale-intent report, and the earlier
     * epoch it belongs to, or null when it is of the current epoch. The
     * server's own knowledge decides, not only the epoch the check sends
     * (review finding R-02): a report, or a receipt that carried another
     * epoch, makes the intent one of an earlier epoch; the epoch sent decides
     * only for an intent of which the server holds no receipt and no report.
     *
     * @return array{list<int>, int|null, string|null}
     */
    private function known(string $operation, string $sourceIntentId, string $sentEpoch, ?string $current): array
    {
        $actors = $this->services->receipts->actorsOf($operation, $sourceIntentId);
        $reporter = $this->services->reports->actorOf($operation, $sourceIntentId);
        $epochs = $this->services->receipts->epochsOf($operation, $sourceIntentId);
        $earlier = null;

        foreach ($epochs as $epoch) {
            if ($epoch !== $current) {
                $earlier = $epoch;

                break;
            }
        }

        if ($earlier === null && $reporter !== null) {
            $earlier = $this->services->reports->epochOf($operation, $sourceIntentId) ?? $sentEpoch;
        }

        if ($earlier === null && $epochs === [] && $sentEpoch !== $current) {
            $earlier = $sentEpoch;
        }

        return [$actors, $reporter, $earlier];
    }

    /**
     * Whether an intent without an identity is the checker's own: no receipt
     * and no report of it is another user's.
     *
     * @param  list<int>  $receiptActors
     */
    private static function ownsWithoutIdentity(User $checker, array $receiptActors, ?int $reportActor): bool
    {
        return array_diff($receiptActors, [$checker->id]) === [] && ($reportActor === null || $reportActor === $checker->id);
    }

    private function concluded(User $checker, bool $owner, StoredIdentity $identity): Resolution
    {
        if (! $owner && ! $this->checksOwn($checker, $identity)) {
            throw CommandRefused::of(ErrorCode::NotFound);
        }

        $committed = ! $identity->isFence();
        $this->settle($identity, $committed ? RequestReceipts::COMMITTED : RequestReceipts::NOT_COMMITTED, $checker);

        if (! $committed) {
            return new Resolution(Resolution::NOT_COMMITTED, $identity->operation);
        }

        $action = CommandActions::for($identity->operation);
        $result = $action?->present($checker, $identity->id, $this->services->identities->resultOf($identity->id));

        return new Resolution(Resolution::COMMITTED, $identity->operation, $identity->id, $result);
    }

    /**
     * Whether the identity is the checker's own intent: a COMMAND of the
     * checker; a FENCE written by the checker, or one of an intent whose
     * receipts are the checker's — and no other user's receipt.
     */
    private function checksOwn(User $checker, StoredIdentity $identity): bool
    {
        if (! $identity->isFence()) {
            return $identity->actorUserId === $checker->id;
        }

        $actors = $this->services->receipts->actorsOf($identity->operation, $identity->sourceIntentId);

        return array_diff($actors, [$checker->id]) === [] && ($identity->actorUserId === $checker->id || in_array($checker->id, $actors, true));
    }

    private function settle(StoredIdentity $identity, string $outcome, User $checker): void
    {
        $services = $this->services;

        try {
            CommandTransaction::run(static function () use ($services, $identity, $outcome, $checker): void {
                $services->receipts->settle($identity->operation, $identity->sourceIntentId, $outcome, $identity->id, $checker->id);
                $services->points->afterOwnWrite('settlement');
            });
        } catch (Throwable $failure) {
            report($failure);

            throw CommandRefused::of(ErrorCode::ServiceBusy);
        }
    }

    private function reportStale(string $operation, string $sourceIntentId, int $actorId, string $recoveryEpoch, ?string $current): void
    {
        $services = $this->services;

        try {
            CommandTransaction::run(static function () use ($services, $operation, $sourceIntentId, $actorId, $recoveryEpoch, $current): void {
                $services->reports->reportIfAbsent($operation, $sourceIntentId, $actorId, $recoveryEpoch);
                $services->receipts->holdStale($operation, $sourceIntentId, $current);
                $services->points->afterOwnWrite('report');
            });
        } catch (Throwable $failure) {
            report($failure);

            throw CommandRefused::of(ErrorCode::ServiceBusy);
        }
    }
}
