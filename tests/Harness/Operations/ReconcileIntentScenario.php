<?php

namespace Tests\Harness\Operations;

use App\Actions\Operations\ReconcileRestore;
use App\Domain\Operations\Restore\RestoreRefused;
use Tests\Harness\Commands\Probe;
use Tests\Harness\Race\Scenario;

/**
 * OP-05's reconciliation of a reported request in a race worker (TS-20; RACE-34; TASK-037): the Owner's
 * decision NOT_COMMITTED for one stale-intent report. It answers the fence it wrote with the receipts it
 * settled, or the refusal.
 */
final class ReconcileIntentScenario implements Scenario
{
    /**
     * @param  array<string, mixed>  $arguments  report
     * @return array<string, mixed>
     */
    public function run(array $arguments): array
    {
        Probe::register();

        $report = $arguments['report'] ?? null;

        try {
            $result = app(ReconcileRestore::class)->intent(is_int($report) ? $report : 0, 'Keputusan Pemilik');
        } catch (RestoreRefused) {
            return ['refused' => 'RESTORE_REFUSED'];
        }

        return ['reconciled' => true, 'settled' => $result['settled']];
    }
}
