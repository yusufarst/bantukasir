<?php

namespace App\Actions\Commands;

use App\Database\CommandPoints;
use App\Domain\Access\ActorGuard;
use App\Domain\Audit\AuditLog;
use App\Domain\Commands\CommandIdentities;
use App\Domain\Commands\RecoveryEpoch;
use App\Domain\Commands\RequestReceipts;
use App\Domain\Commands\StaleIntentReports;
use App\Support\Time\Clock;

/**
 * The services every command's Action uses through its base (CommandAction,
 * ResolveIntent), given to it in one constructor argument so that each
 * Action adds only its own.
 */
final readonly class CommandServices
{
    public function __construct(
        public CommandPoints $points,
        public CommandIdentities $identities,
        public RecoveryEpoch $epoch,
        public RequestReceipts $receipts,
        public StaleIntentReports $reports,
        public ActorGuard $actorGuard,
        public AuditLog $audit,
        public Clock $clock,
    ) {}
}
