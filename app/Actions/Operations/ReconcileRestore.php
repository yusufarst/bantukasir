<?php

namespace App\Actions\Operations;

use App\Database\CommandPoints;
use App\Database\SqlState;
use App\Domain\Access\Accounts;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Audit\AuditLog;
use App\Domain\Commands\CommandIdentities;
use App\Domain\Commands\IntentReconciliations;
use App\Domain\Commands\RecoveryEpoch;
use App\Domain\Commands\RequestReceipts;
use App\Domain\Inventory\OnboardingState;
use App\Domain\Operations\Restore\RestoreRefused;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * OP-05, the restore reconciliation (SECURITY_MODEL SEC-OP-05 and section 14; ROUTE_CONTRACTS OP-05;
 * TECHNICAL_RULES section 7.2, "Restore reconciliation"; ADR-009 sections 1 and 3; BACKUP_RECOVERY RB-01
 * steps 16, 18, 19 and 21; D-072, D-073, D-089; RACE-34; TASK-037 scope item 3). An operator procedure,
 * without an envelope (TR-CMD-07), that records one decision of the Owner — the one ACTIVE SUPER_ADMIN
 * account, who decides; the operator records — in one transaction:
 *
 * - for a request reported from an earlier epoch: NOT_COMMITTED in the restored data, with a FENCE of the
 *   report's operation and intent in the current epoch whose actor is the Owner (step 1), so that the
 *   intent can never commit afterwards and a late original is refused INTENT_ABANDONED; the
 *   reconciliation of the report; every PENDING or HELD receipt of the intent settled NOT_COMMITTED by
 *   that fence and the Owner, in ascending order and with no session lock; the audit event
 *   RESTORE_RECONCILIATION. An intent with an identity — committed in the restored data, or fenced — is
 *   refused: a status check answers it;
 * - for an onboarding restore hold, under the update lock of the onboarding state (step 2.3): released
 *   before the restore — the release record of kind RESTORE_RECONCILIATION and the state RELEASED, unless
 *   the data hold the release already — or never released, the restored state continuing as the same
 *   period, refused for data that hold a release, which nothing undoes (G-26); the reconciliation of the
 *   hold; the audit event RESTORE_RECONCILIATION.
 *
 * A decision is recorded once: a second run for the same report or hold is refused with nothing written.
 * The order of the runbook — the Owner's own user-management requests first, before the security changes
 * after the backup point are re-applied (D-073) — is the operator's, one run per decision.
 *
 * Write steps (TEST_STRATEGY TS-21): a request — the fence (the identity point), 1 the reconciliation, 2
 * the settlement, 3 the audit event; a hold — 1 the release with the state, when released, then the
 * reconciliation and the audit event, each one step on.
 */
final class ReconcileRestore
{
    public const string PROCESS = 'restore_reconciliation';

    /** The keys whose conflict says that the intent, the report or the hold was decided already. */
    private const array DECIDED = ['command_identities_intent_unique', 'intent_reconciliations_pkey', 'intent_reconciliations_fence_unique', 'onboarding_restore_reconciliations_pkey', 'onboarding_releases_pkey'];

    private int $writeStep = 0;

    public function __construct(
        private readonly IntentReconciliations $reconciliations,
        private readonly CommandIdentities $identities,
        private readonly RequestReceipts $receipts,
        private readonly RecoveryEpoch $epoch,
        private readonly OnboardingState $onboarding,
        private readonly Accounts $accounts,
        private readonly AuditLog $audit,
        private readonly CommandPoints $points,
        private readonly Clock $clock,
    ) {}

    /**
     * Records NOT_COMMITTED for the reported request.
     *
     * @return array{operation: string, intent: string, fenceId: int, settled: int}
     *
     * @throws RestoreRefused
     */
    public function intent(int $reportId, string $operatorNote): array
    {
        $this->writeStep = 0;

        try {
            /** @var array{operation: string, intent: string, fenceId: int, settled: int} $result */
            $result = DB::transaction(fn (): array => $this->reconcileIntent($reportId, $operatorNote));
        } catch (Throwable $failure) {
            throw self::decided($failure, 'The intent has an identity already — committed in the restored data, or fenced — or the report was reconciled meanwhile: a status check answers it; nothing was changed.');
        }

        return $result;
    }

    /**
     * Records the Owner's outcome of the onboarding restore hold.
     *
     * @return array{restoredState: string, released: bool}
     *
     * @throws RestoreRefused
     */
    public function hold(int $holdId, string $outcome, string $operatorNote): array
    {
        $this->writeStep = 0;

        try {
            /** @var array{restoredState: string, released: bool} $result */
            $result = DB::transaction(fn (): array => $this->reconcileHold($holdId, $outcome, $operatorNote));
        } catch (Throwable $failure) {
            throw self::decided($failure, 'The hold was decided meanwhile: nothing was changed.');
        }

        return $result;
    }

    /**
     * @return array{operation: string, intent: string, fenceId: int, settled: int}
     *
     * @throws RestoreRefused
     */
    private function reconcileIntent(int $reportId, string $operatorNote): array
    {
        $report = $this->reconciliations->report($reportId) ?? throw new RestoreRefused('No such stale-intent report: nothing was changed.');

        if ($report['reconciled']) {
            throw new RestoreRefused('The report is reconciled already: nothing was changed.');
        }

        $owner = $this->owner();
        $current = $this->epoch->current() ?? throw new RestoreRefused('The installation holds no recovery epoch: nothing was changed.');

        if ($report['epoch'] === $current) {
            throw new RestoreRefused('The report names the current epoch, whose requests a status check resolves: nothing was changed.');
        }

        $fence = $this->identities->insertFence($report['operation'], $report['intent'], $owner, $current, $this->now());
        $this->points->afterIdentity();

        $this->reconciliations->record($reportId, $fence, $owner, $operatorNote);
        $this->written();

        $settled = $this->receipts->settle($report['operation'], $report['intent'], RequestReceipts::NOT_COMMITTED, $fence, $owner);
        $this->written();

        $this->audit->record(
            AuditCategory::Business,
            'RESTORE_RECONCILIATION',
            Actor::operatorProcedure(self::PROCESS),
            ['decision' => 'intent', 'reportId' => $reportId, 'operation' => $report['operation'], 'outcome' => IntentReconciliations::NOT_COMMITTED, 'fenceId' => $fence, 'receiptsSettled' => $settled],
            subject: ['type' => 'stale_intent_report', 'id' => $reportId],
            operatorNote: $operatorNote,
        );
        $this->written();
        $this->points->beforeCommit();

        return ['operation' => $report['operation'], 'intent' => $report['intent'], 'fenceId' => $fence, 'settled' => $settled];
    }

    /**
     * @return array{restoredState: string, released: bool}
     *
     * @throws RestoreRefused
     */
    private function reconcileHold(int $holdId, string $outcome, string $operatorNote): array
    {
        $status = $this->onboarding->lockForChange();
        $this->points->afterLock('2.3');

        $hold = $this->onboarding->holdOf($holdId) ?? throw new RestoreRefused('No such onboarding restore hold: nothing was changed.');

        if ($hold['reconciled']) {
            throw new RestoreRefused('The hold is decided already: nothing was changed.');
        }

        if ($outcome === OnboardingState::NEVER_RELEASED && $status->released) {
            throw new RestoreRefused('Onboarding is released for these data, and nothing undoes a release (G-26): this hold can only be decided RELEASED_BEFORE_RESTORE; nothing was changed.');
        }

        $owner = $this->owner();
        $released = $this->onboarding->reconcileHold($status, $holdId, $outcome, $owner, $operatorNote, $this->now());

        if ($released) {
            $this->written();
        }

        $this->written();

        $this->audit->record(
            AuditCategory::Business,
            'RESTORE_RECONCILIATION',
            Actor::operatorProcedure(self::PROCESS),
            ['decision' => 'onboarding_hold', 'holdId' => $holdId, 'restoredState' => $hold['restoredState'], 'outcome' => $outcome, 'state' => $released ? OnboardingState::RELEASED : $status->state, 'version' => $released ? $status->version + 1 : $status->version],
            subject: ['type' => 'onboarding', 'id' => 1],
            operatorNote: $operatorNote,
        );
        $this->written();
        $this->points->beforeCommit();

        return ['restoredState' => $hold['restoredState'], 'released' => $released];
    }

    /**
     * The Owner who decides: the one ACTIVE SUPER_ADMIN account.
     *
     * @throws RestoreRefused
     */
    private function owner(): int
    {
        return $this->accounts->activeOwnerId() ?? throw new RestoreRefused('The restored data hold no ACTIVE Owner account, whose decision this records: nothing was changed.');
    }

    /**
     * The failure of a decision as it is answered after the rollback (TR-TX-08): the conflict of a key
     * that another run, or an identity, took first is the refusal given; anything else stands.
     */
    private static function decided(Throwable $failure, string $conflict): Throwable
    {
        return SqlState::uniqueOn($failure, self::DECIDED) ? new RestoreRefused($conflict) : $failure;
    }

    private function written(): void
    {
        $this->points->afterWrite(++$this->writeStep);
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s.uP');
    }
}
