<?php

namespace Tests\Harness\Sales;

use App\Actions\Cash\CloseCashSession;
use App\Domain\Commands\RecoveryEpoch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Harness\Access\UserCommandScenario;
use Tests\Harness\Race\Scenario;

/**
 * The close of a cash session by its responsible user through RC-03's Action, in a race worker
 * (RACE-08, RACE-09, RACE-35; TECHNICAL_RULES section 7.2, family Close; BR-CASH-14, BR-CASH-15,
 * BR-CASH-17; TASK-030, which replaced the stand-in of TASK-028 with the Action itself): the session's
 * row under FOR UPDATE at step 4, where the barrier `after_lock:4` holds it; under that lock the session
 * OPEN, else SESSION_NOT_OPEN, its activity version the one reviewed, else RECOUNT_REQUIRED, and no
 * unresolved checkout of the user, else RECOUNT_REQUIRED (G-27); then the session CLOSED with its close
 * and report. The physical cash is the one given, Rp0 unless the test names it; the key and intent are
 * new unless given. It answers CLOSED, or the code of its refusal.
 */
final class SessionCloseScenario implements Scenario
{
    /**
     * @param  array<string, mixed>  $arguments  session, activityVersion, and optionally physicalCash, intent, key
     * @return array<string, mixed>
     */
    public function run(array $arguments): array
    {
        $session = $arguments['session'] ?? null;
        $actor = DB::table('cash_sessions')->where('id', is_int($session) ? $session : 0)->value('responsible_user_id');
        $physical = $arguments['physicalCash'] ?? '0';
        $intent = $arguments['intent'] ?? null;
        $key = $arguments['key'] ?? null;

        $result = (new UserCommandScenario)->run([
            'operation' => CloseCashSession::OPERATION,
            'actor' => is_int($actor) ? $actor : 0,
            'intent' => is_string($intent) ? $intent : (string) Str::uuid(),
            'key' => is_string($key) ? $key : (string) Str::uuid(),
            'epoch' => app(RecoveryEpoch::class)->current() ?? '',
            'expected' => ['activityVersion' => $arguments['activityVersion'] ?? null],
            'payload' => ['sessionId' => $session, 'physicalCash' => $physical],
        ]);

        if (isset($result['refused'])) {
            return ['refused' => $result['refused']];
        }

        return ['outcome' => ($result['repeated'] ?? null) === true ? 'REPEATED' : 'CLOSED'];
    }
}
