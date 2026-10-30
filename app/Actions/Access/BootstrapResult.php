<?php

namespace App\Actions\Access;

/**
 * What a committed bootstrap gives its operator: the new Owner account and
 * the address of its FIRST_PASSWORD link, which the procedure prints to the
 * operator's terminal only, never to a log (SECURITY_MODEL section 14,
 * OPERATIONS_RUNBOOK OPR-06), and the first revision.
 */
final readonly class BootstrapResult
{
    public function __construct(
        public int $ownerId,
        public int $revisionId,
        #[\SensitiveParameter] public string $link,
    ) {}
}
