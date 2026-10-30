<?php

namespace App\Console\Commands;

use App\Actions\Access\ReplaceOwner;
use App\Domain\Access\OwnerProcedureRefused;

/**
 * OP-03 `owner:replace` (ROUTE_CONTRACTS section 7; SECURITY_MODEL SEC-OP-03;
 * OPERATIONS_RUNBOOK RB-11; TASK-011 scope item 3): with the authorization
 * evidence in the operator's private record, the outgoing Owner, named by the
 * account's internal identifier, is deactivated with its sign-in sessions and
 * links revoked, and a new SUPER_ADMIN account is created with its FIRST_PASSWORD
 * link, printed here to the operator's terminal only (OPR-06). A repeated run is
 * refused. The outgoing account is never deleted; an OPEN cash session of the
 * outgoing Owner stays OPEN and unchanged.
 */
final class OwnerReplace extends OperatorProcedure
{
    protected $signature = 'owner:replace
        {--environment= : The name of the environment, typed by the operator; it must equal the configured name and the database marker}
        {--outgoing= : The internal identifier of the outgoing Owner account}
        {--name= : The new Owner\'s display name, 1 to 100 characters}
        {--email= : The new Owner\'s email, the sign-in identifier, at most 254 characters}
        {--note= : The operator\'s note for the audit, 1 to 1,000 characters}';

    protected $description = 'OP-03: deactivate the named outgoing Owner and create the new Owner, printing the new Owner\'s first-password link';

    protected function process(): string
    {
        return ReplaceOwner::PROCESS;
    }

    protected function optionRules(): array
    {
        return [
            'outgoing' => ['required', 'string', 'regex:/\A[1-9][0-9]{0,17}\z/'],
            'name' => ['required', 'string', 'max:100', 'regex:'.self::PLAIN_TEXT],
            'email' => ['required', 'string', 'max:254', 'email:rfc', 'regex:'.self::PLAIN_TEXT, 'not_regex:'.self::RESERVED_EMAIL],
        ];
    }

    protected function perform(array $options, string $note): int
    {
        try {
            $result = app(ReplaceOwner::class)((int) ($options['outgoing'] ?? '0'), $options['name'] ?? '', $options['email'] ?? '', $note);
        } catch (OwnerProcedureRefused $refusal) {
            throw new ProcedureRefused($refusal->getMessage());
        }

        $this->sessionsEndedOf = $result->sessionsEndedOf;
        $this->info(sprintf('The outgoing Owner was deactivated and the new Owner account %d was created.', $result->accountId));
        $this->line('The new Owner\'s first-password link, valid for 60 minutes; hand it to the new Owner directly and keep no copy (OPR-06):');
        $this->line($result->link);

        return $result->accountId;
    }
}
