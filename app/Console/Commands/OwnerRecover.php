<?php

namespace App\Console\Commands;

use App\Actions\Access\RecoverOwner;
use App\Domain\Access\OwnerProcedureRefused;

/**
 * OP-02 `owner:recover` (ROUTE_CONTRACTS section 7; SECURITY_MODEL SEC-OP-02;
 * OPERATIONS_RUNBOOK RB-10; TASK-011 scope item 3): after the Owner's request and
 * identity are verified outside the application, a RESET link for the one active
 * Owner account, replacing any earlier one, with the account's sign-in sessions
 * revoked. The link is printed here, to the operator's terminal only, for the
 * operator to hand to the Owner directly (OPR-06).
 */
final class OwnerRecover extends OperatorProcedure
{
    protected $signature = 'owner:recover
        {--environment= : The name of the environment, typed by the operator; it must equal the configured name and the database marker}
        {--note= : The operator\'s note for the audit, 1 to 1,000 characters}';

    protected $description = 'OP-02: issue a reset link for the Owner, replacing any earlier one, and revoke the Owner\'s sign-in sessions';

    protected function process(): string
    {
        return RecoverOwner::PROCESS;
    }

    protected function optionRules(): array
    {
        return [];
    }

    protected function perform(array $options, string $note): int
    {
        try {
            $result = app(RecoverOwner::class)($note);
        } catch (OwnerProcedureRefused $refusal) {
            throw new ProcedureRefused($refusal->getMessage());
        }

        $this->sessionsEndedOf = $result->sessionsEndedOf;
        $this->info(sprintf('The Owner account %d has a new reset link, and its sign-in sessions were revoked.', $result->accountId));
        $this->line('The Owner\'s reset link, valid for 60 minutes; hand it to the Owner directly and keep no copy (OPR-06):');
        $this->line($result->link);

        return $result->accountId;
    }
}
