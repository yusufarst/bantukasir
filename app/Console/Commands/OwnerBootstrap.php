<?php

namespace App\Console\Commands;

use App\Actions\Access\BootstrapInput;
use App\Actions\Access\BootstrapInstallation;
use App\Domain\Access\BootstrapRefused;

/**
 * OP-01 `owner:bootstrap` (ROUTE_CONTRACTS section 7; SECURITY_MODEL
 * SEC-OP-01; RELEASE_PROCESS RB-23 step 7; TASK-010 scope item 3): once, at
 * installation, the first Owner account with its FIRST_PASSWORD link, the
 * recovery epoch, the onboarding state and the first BusinessProfile revision
 * with the shop name given (BootstrapInstallation), refused when any account
 * exists. The link is printed here, to the operator's terminal only, for the
 * operator to hand to the Owner directly (OPERATIONS_RUNBOOK OPR-06); it
 * expires 60 minutes after it is issued. The Owner's recovery facts are
 * recorded by the operator privately (OPERATIONS_RUNBOOK section 8).
 */
final class OwnerBootstrap extends OperatorProcedure
{
    protected $signature = 'owner:bootstrap
        {--environment= : The name of the environment, typed by the operator; it must equal the configured name and the database marker}
        {--name= : The Owner\'s display name, 1 to 100 characters}
        {--email= : The Owner\'s email, the sign-in identifier, at most 254 characters}
        {--shop= : The shop name of the first BusinessProfile revision, 1 to 100 characters}
        {--note= : The operator\'s note for the audit, 1 to 1,000 characters}';

    protected $description = 'OP-01: bootstrap the installation with its first Owner and print the Owner\'s first-password link';

    protected function process(): string
    {
        return BootstrapInstallation::PROCESS;
    }

    protected function optionRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'regex:'.self::PLAIN_TEXT],
            'email' => ['required', 'string', 'max:254', 'email:rfc', 'regex:'.self::PLAIN_TEXT, 'not_regex:'.self::RESERVED_EMAIL],
            'shop' => ['required', 'string', 'max:100', 'regex:'.self::PLAIN_TEXT],
        ];
    }

    protected function perform(array $options, string $note): int
    {
        try {
            $result = app(BootstrapInstallation::class)(new BootstrapInput($options['name'] ?? '', $options['email'] ?? '', $options['shop'] ?? '', $note));
        } catch (BootstrapRefused $refusal) {
            throw new ProcedureRefused($refusal->getMessage());
        }

        $this->info(sprintf('The Owner account %d and the BusinessProfile revision %d were created.', $result->ownerId, $result->revisionId));
        $this->line('The Owner\'s first-password link, valid for 60 minutes; hand it to the Owner directly and keep no copy (OPR-06):');
        $this->line($result->link);

        return $result->ownerId;
    }
}
