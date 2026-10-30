<?php

namespace App\Actions\Access;

/**
 * What OP-02 and OP-03 give their procedure: the account the link is for, the
 * link, which the procedure prints to the operator's terminal only and never
 * logs, and the account whose sign-in sessions it ended, for the security log.
 */
final readonly class OwnerLinkResult
{
    public function __construct(
        public int $accountId,
        #[\SensitiveParameter]
        public string $link,
        public ?int $sessionsEndedOf,
    ) {}
}
