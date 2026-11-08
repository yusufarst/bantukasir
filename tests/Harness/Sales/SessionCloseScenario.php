<?php

namespace Tests\Harness\Sales;

use App\Database\CommandPoints;
use Illuminate\Support\Facades\DB;
use Tests\Harness\Race\Scenario;

/**
 * The close of a cash session as RC-03 of TASK-030 will take it, in a race worker (RACE-08; TECHNICAL_RULES
 * section 7.1, family Close, step 4; BR-CASH-14, BR-CASH-15; TASK-028, the Sale side of RACE-08): the
 * session's row under its update lock; at that lock's point, the session OPEN, else SESSION_NOT_OPEN, and
 * its activity version the one the close preview reviewed, else RECOUNT_REQUIRED; then the session
 * CLOSED. Until RC-03 exists it writes the session's status alone, through the runtime role's privilege
 * (SCHEMA section 5.10), as the close will leave it. It answers its outcome, or the code of its refusal.
 */
final class SessionCloseScenario implements Scenario
{
    /**
     * @param  array<string, mixed>  $arguments  session, activityVersion
     * @return array<string, mixed>
     */
    public function run(array $arguments): array
    {
        $session = $arguments['session'] ?? null;
        $reviewed = $arguments['activityVersion'] ?? null;
        $points = app(CommandPoints::class);

        return DB::transaction(static function () use ($session, $reviewed, $points): array {
            $row = (array) DB::table('cash_sessions')->where('id', $session)->lock('for no key update')->first(['status', 'activity_version']);
            $points->afterLock('4');

            if (($row['status'] ?? null) !== 'OPEN') {
                return ['refused' => 'SESSION_NOT_OPEN'];
            }

            if (($row['activity_version'] ?? null) !== $reviewed) {
                return ['refused' => 'RECOUNT_REQUIRED'];
            }

            DB::table('cash_sessions')->where('id', $session)->update(['status' => 'CLOSED', 'updated_at' => now()]);
            $points->beforeCommit();

            return ['outcome' => 'CLOSED'];
        });
    }
}
