<?php

namespace App\Actions\Access;

use App\Database\CommandPoints;
use App\Database\SqlState;
use App\Domain\Access\Accounts;
use App\Domain\Access\BootstrapRefused;
use App\Domain\Access\OneTimeLinks;
use App\Domain\Access\Role;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Audit\AuditLog;
use App\Domain\Commands\RecoveryEpoch;
use App\Domain\Configuration\BusinessProfiles;
use App\Domain\Configuration\ProfileFields;
use App\Domain\Inventory\OnboardingState;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * OP-01, the bootstrap of the installation (SECURITY_MODEL SEC-OP-01;
 * TECHNICAL_RULES section 7.2, "Bootstrap"; ROUTE_CONTRACTS OP-01; D-087). An
 * operator procedure, without an envelope (TR-CMD-07); in one transaction:
 *
 * 1. step 2.3, the installation state, inserted: the recovery epoch of 256
 *    random bits and the onboarding state NOT_STARTED. A concurrent bootstrap
 *    waits on their primary keys and then fails, so that of two runs exactly
 *    one succeeds (SCHEMA section 7; RACE-30);
 * 2. the guard, read after that: no account of any kind exists;
 * 3. step 8: the one SUPER_ADMIN account, ACTIVE and without a password; its
 *    FIRST_PASSWORD link, issued by the broker of TASK-006 under the new
 *    account's row; the first BusinessProfile revision with the shop name
 *    given, whose actor is the new Owner, and its current pointer (guard
 *    G-23); and the audit events USER_CREATE, LINK_ISSUE and PROFILE_CHANGE,
 *    each with the procedure as actor and the operator's note (section 14;
 *    INTERACTION_CONTRACTS section 8). The token never reaches the audit.
 *
 * A unique violation of the installation state or of the one active Owner,
 * read after the rollback, is the other run's success: refused. The security
 * log entry OPERATOR_PROCEDURE_RUN is written by the procedure after the
 * transaction (SEC-LOG-03).
 *
 * Write steps (TEST_STRATEGY TS-21): 1 the epoch, 2 the onboarding state,
 * 3 the account, 4 the link, 5 the revision and its pointer, 6 to 8 the
 * audit events.
 */
final class BootstrapInstallation
{
    public const string PROCESS = 'bootstrap';

    /** The unique keys whose conflict means another bootstrap committed first. */
    private const array INSTALLED = ['recovery_epoch_pkey', 'onboarding_state_pkey', 'business_profile_current_pkey', 'users_one_active_owner', 'users_email_ci_uniq'];

    public function __construct(
        private readonly Accounts $accounts,
        private readonly OneTimeLinks $links,
        private readonly RecoveryEpoch $epochs,
        private readonly OnboardingState $onboarding,
        private readonly BusinessProfiles $profiles,
        private readonly AuditLog $audit,
        private readonly CommandPoints $points,
    ) {}

    /**
     * @throws BootstrapRefused
     */
    public function __invoke(BootstrapInput $input): BootstrapResult
    {
        try {
            return DB::transaction(function () use ($input): BootstrapResult {
                $this->epochs->issue();
                $this->points->afterWrite(1);

                $this->onboarding->insertInitial();
                $this->points->afterWrite(2);
                $this->points->afterLock('2.3');

                if (DB::table('users')->exists()) {
                    throw new BootstrapRefused(BootstrapRefused::ACCOUNTS_EXIST);
                }

                $owner = $this->accounts->create($input->ownerName, $input->ownerEmail, [Role::SuperAdmin]);
                $this->points->afterWrite(3);

                $token = $this->links->issue($owner);
                $this->points->afterWrite(4);

                $revisionId = $this->profiles->insertFirst(new ProfileFields($input->shopName), $owner->id, $owner->display_name);
                $this->points->afterWrite(5);

                $actor = Actor::operatorProcedure(self::PROCESS);
                $this->audit->record(AuditCategory::Security, 'USER_CREATE', $actor, ['roles' => [Role::SuperAdmin->value]], subject: ['type' => 'user', 'id' => $owner->id], operatorNote: $input->operatorNote);
                $this->points->afterWrite(6);

                $this->audit->record(AuditCategory::Security, 'LINK_ISSUE', $actor, ['kind' => 'FIRST_PASSWORD'], subject: ['type' => 'user', 'id' => $owner->id], operatorNote: $input->operatorNote);
                $this->points->afterWrite(7);

                $this->audit->record(AuditCategory::Security, 'PROFILE_CHANGE', $actor, ['revisionId' => $revisionId, 'changed' => ['shopName']], subject: ['type' => 'business_profile_revision', 'id' => $revisionId], operatorNote: $input->operatorNote);
                $this->points->afterWrite(8);

                $this->points->beforeCommit();

                return new BootstrapResult($owner->id, $revisionId, OneTimeLinks::url($owner, $token));
            });
        } catch (Throwable $failure) {
            if (SqlState::uniqueOn($failure, self::INSTALLED)) {
                throw new BootstrapRefused(BootstrapRefused::ALREADY_INSTALLED);
            }

            throw $failure;
        }
    }
}
