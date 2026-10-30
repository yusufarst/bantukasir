<?php

namespace App\Actions\Access;

use App\Actions\Commands\CommandContext;
use App\Domain\Access\Capability;
use App\Domain\Audit\AuditCategory;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Models\User;

/**
 * RC-41 `user.issue_link` (INTERACTION_CONTRACTS RC-35 to RC-42; SECURITY_MODEL
 * SEC-ACC-04, SEC-ACC-05; CAP-25, CAP-26; RACE-20): a new link for an ACTIVE
 * staff account, replacing any earlier one, at most one per account per 60
 * seconds, returned once in this answer only. FIRST_PASSWORD is for an account
 * that has not set a password, RESET for one that has (UXI-342, UXI-343): a page
 * whose account set its password meanwhile is stale, since that does not move
 * the account's version.
 *
 * Write steps (TEST_STRATEGY TS-21): 1 the link, 2 LINK_ISSUE, then the result.
 */
final class IssuePasswordLink extends UserManagementAction
{
    public const string OPERATION = 'user.issue_link';

    public const string FIRST_PASSWORD = 'FIRST_PASSWORD';

    public const string RESET = 'RESET';

    public function operation(): string
    {
        return self::OPERATION;
    }

    public function capabilities(): array
    {
        return [Capability::Cap25, Capability::Cap26];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $target = $this->staffTarget($context, $actor);

        if (! $target->isActive()) {
            throw self::notAllowedInState();
        }

        $kind = self::text($context->envelope->payload['kind'] ?? null);

        if (($kind === self::RESET) !== ($target->password !== null)) {
            throw CommandRefused::of(ErrorCode::StaleVersion);
        }

        if ($this->links->recentlyIssued($target)) {
            throw CommandRefused::of(ErrorCode::Throttled);
        }

        $token = $this->links->issue($target);
        $this->written();

        $this->services->audit->record(AuditCategory::Security, 'LINK_ISSUE', self::actorOf($actor), ['kind' => $kind], subject: ['type' => self::RESULT_TYPE, 'id' => $target->id], commandId: $commandId);
        $this->written();

        $this->handOver($this->links::url($target, $token), $this->linkExpiry());

        return self::result($target);
    }

    /**
     * Committed now, the result leads to the link's one-time page (RP-606).
     */
    protected function presentNow(User $viewer, int $commandId, StoredResult $result): ?array
    {
        $shown = $this->present($viewer, $commandId, $result);

        return $shown === null ? null : [...$shown, 'location' => $shown['location'].'/link'];
    }
}
