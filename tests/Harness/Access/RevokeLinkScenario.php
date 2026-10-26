<?php

namespace Tests\Harness\Access;

use App\Database\CommandPoints;
use App\Domain\Access\Accounts;
use App\Domain\Access\OneTimeLinks;
use Illuminate\Support\Facades\DB;
use Tests\Harness\Race\Scenario;

/**
 * A writer that revokes an account's link under the target account's lock,
 * in a race worker (RACE-20; TASK-006): it stands for the revocation, the
 * re-issue and the deactivation of TASK-011, which take the same lock
 * (TECHNICAL_RULES section 7.2, "User management") and call the broker's
 * revoke() inside their transaction.
 */
final class RevokeLinkScenario implements Scenario
{
    public function __construct(
        private readonly Accounts $accounts,
        private readonly OneTimeLinks $links,
        private readonly CommandPoints $points,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments  user
     * @return array{outcome: string}
     */
    public function run(array $arguments): array
    {
        $user = $arguments['user'] ?? null;

        if (! is_int($user)) {
            return ['outcome' => 'BAD_ARGUMENTS'];
        }

        return DB::transaction(function () use ($user): array {
            $account = $this->accounts->lockForUpdate($user);
            $this->points->afterLock('2.2');

            if ($account === null) {
                return ['outcome' => 'NO_ACCOUNT'];
            }

            $this->links->revoke($account);
            $this->points->afterWrite(1);
            $this->points->beforeCommit();

            return ['outcome' => 'REVOKED'];
        });
    }
}
