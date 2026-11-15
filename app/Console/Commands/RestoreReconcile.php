<?php

namespace App\Console\Commands;

use App\Actions\Operations\ReconcileRestore;
use App\Domain\Inventory\OnboardingState;
use App\Domain\Operations\Restore\RestoreRefused;
use App\Policies\ObjectScope;

/**
 * OP-05 `restore:reconcile` (ROUTE_CONTRACTS OP-05 and section 7; SECURITY_MODEL SEC-OP-05 and section 14;
 * BACKUP_RECOVERY RB-01 steps 16, 18, 19 and 21; TASK-037 scope item 3): the operator records one decision
 * of the Owner — for a request reported from an earlier epoch (`--report`, the key of its stale-intent
 * report), NOT_COMMITTED in the restored data with a fence; for the onboarding restore hold (`--hold`
 * with `--outcome`), released before the restore or never released. Exactly one of the two per run, as
 * typed internal keys and codes; the environment and the note are OperatorProcedure's.
 */
final class RestoreReconcile extends OperatorProcedure
{
    private const string KEY = 'regex:/^[1-9][0-9]{0,18}$/D';

    protected $signature = 'restore:reconcile
        {--environment= : The name of the environment, typed by the operator; it must equal the configured name and the database marker}
        {--report= : The internal key of the stale-intent report that the Owner decided not committed in the restored data}
        {--hold= : The internal key of the onboarding restore hold that the Owner decided}
        {--outcome= : With --hold only: RELEASED_BEFORE_RESTORE or NEVER_RELEASED, as the Owner decided}
        {--note= : The operator\'s note for the audit, 1 to 1,000 characters, naming the Owner\'s decision}';

    protected $description = 'OP-05: record the Owner\'s decision on a request from before a restore, with a fence that settles its receipts, or on the onboarding restore hold';

    protected function process(): string
    {
        return ReconcileRestore::PROCESS;
    }

    protected function optionRules(): array
    {
        return [
            'report' => ['sometimes', 'string', self::KEY],
            'hold' => ['sometimes', 'string', self::KEY],
            'outcome' => ['sometimes', 'string', 'regex:/^(?:RELEASED_BEFORE_RESTORE|NEVER_RELEASED)$/D'],
        ];
    }

    protected function perform(array $options, string $note): ?int
    {
        $report = array_key_exists('report', $options) ? ObjectScope::key($options['report']) : null;
        $hold = array_key_exists('hold', $options) ? ObjectScope::key($options['hold']) : null;
        $outcome = $options['outcome'] ?? null;

        if (($report === null) === ($hold === null) || ($report !== null && $outcome !== null) || ($hold !== null && $outcome === null)) {
            throw new ProcedureRefused('Name exactly one decision: --report alone, or --hold with --outcome.');
        }

        try {
            if ($report !== null) {
                $result = app(ReconcileRestore::class)->intent($report, $note);
                $this->info(sprintf('The request %s of report %d is recorded NOT_COMMITTED with fence %d, and %d receipt(s) were settled: its status check now answers NOT_COMMITTED.', $result['operation'], $report, $result['fenceId'], $result['settled']));

                return null;
            }

            if ($hold === null || $outcome === null) {
                throw new ProcedureRefused('Name exactly one decision: --report alone, or --hold with --outcome.');
            }

            $result = app(ReconcileRestore::class)->hold($hold, $outcome, $note);
        } catch (RestoreRefused $refusal) {
            throw new ProcedureRefused($refusal->getMessage());
        }

        $this->info($outcome === OnboardingState::RELEASED_BEFORE_RESTORE
            ? sprintf('Hold %d is decided released before the restore: onboarding is RELEASED%s.', $hold, $result['released'] ? ' now' : ', as the data held it already')
            : sprintf('Hold %d is decided never released: the restored state %s continues as the same period.', $hold, $result['restoredState']));

        return null;
    }
}
