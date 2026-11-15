<?php

namespace App\Console\Commands;

use App\Actions\Operations\FenceAfterRestore;
use App\Domain\Operations\Restore\RestoreRefused;

/**
 * OP-04 `restore:after` (ROUTE_CONTRACTS OP-04 and section 7; SECURITY_MODEL SEC-OP-04 and section 14;
 * BACKUP_RECOVERY RB-01 step 12, RB-02 step 6; TASK-037 scope item 2): run by the operator on the restored
 * target, before any user other than the Owner reaches it — a recovery epoch never issued before, every
 * sign-in session revoked, and an onboarding restore hold when the restored state is not RELEASED. The
 * environment and the note are OperatorProcedure's; it has no option of its own.
 */
final class RestoreAfter extends OperatorProcedure
{
    protected $signature = 'restore:after
        {--environment= : The name of the environment, typed by the operator; it must equal the configured name and the database marker}
        {--note= : The operator\'s note for the audit, 1 to 1,000 characters, naming the restore}';

    protected $description = 'OP-04: after a restore, set a new recovery epoch, revoke every sign-in session and hold onboarding when the restored state is not RELEASED';

    protected function process(): string
    {
        return FenceAfterRestore::PROCESS;
    }

    protected function optionRules(): array
    {
        return [];
    }

    protected function perform(array $options, string $note): ?int
    {
        $this->sessionsEndedOfAccounts = [];

        try {
            $result = app(FenceAfterRestore::class)($note);
        } catch (RestoreRefused $refusal) {
            throw new ProcedureRefused($refusal->getMessage());
        }

        $this->sessionsEndedOfAccounts = array_keys($result['sessions']);
        $this->info(sprintf('A new recovery epoch is set, and %d sign-in session(s) were revoked: every request from before it is refused EPOCH_STALE.', array_sum($result['sessions'])));
        $this->info($result['holdId'] === null
            ? 'The restored onboarding state is RELEASED: no hold is needed.'
            : sprintf('The restored onboarding state is %s: hold %d waits for the Owner\'s decision (restore:reconcile --hold=%d).', $result['restoredState'], $result['holdId'], $result['holdId']));

        return null;
    }
}
