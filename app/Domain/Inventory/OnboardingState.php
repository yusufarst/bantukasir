<?php

namespace App\Domain\Inventory;

use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The installation's onboarding state (SCHEMA section 5.7; BR-INV-24;
 * D-071, D-072), a service of the Inventory module, which alone writes it.
 * The bootstrap procedure inserts it NOT_STARTED (SEC-OP-01); the onboarding
 * commands of TASK-035 start and release it under its update lock, and the
 * restore procedures of TASK-037 hold it. Its presence key makes RELEASED
 * possible only with the durable release record of `onboarding_releases`.
 */
final class OnboardingState
{
    public const string NOT_STARTED = 'NOT_STARTED';

    public const string ACTIVE = 'ACTIVE';

    public const string RELEASED = 'RELEASED';

    public function __construct(private readonly Clock $clock) {}

    /**
     * Inserts the state NOT_STARTED, at version 1, in the bootstrap's
     * transaction; a concurrent bootstrap waits on the primary key and then
     * fails (SCHEMA section 7; RACE-30).
     */
    public function insertInitial(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('The onboarding state is inserted in the transaction of the bootstrap.');
        }

        DB::table('onboarding_state')->insert([
            'id' => 1,
            'state' => self::NOT_STARTED,
            'version' => 1,
            'changed_at' => $this->clock->now()->format('Y-m-d H:i:s.uP'),
            'changed_by_user_id' => null,
        ]);
    }

    /**
     * The state, read without a lock, or null before the bootstrap.
     */
    public function current(): ?string
    {
        $state = DB::table('onboarding_state')->where('id', 1)->value('state');

        return is_string($state) ? $state : null;
    }
}
