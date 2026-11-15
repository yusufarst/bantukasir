<?php

namespace Tests\Concurrency;

use App\Domain\Commands\RequestReceipts;
use App\Domain\Commands\StaleIntentReports;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Harness\Commands\Probe;
use Tests\Harness\Commands\ProbeScenario;
use Tests\Harness\Commands\ResolveScenario;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Operations\ReconcileIntentScenario;
use Tests\Harness\Race\Scenario;
use Tests\Harness\Race\Worker;

/**
 * RACE-34 (TECHNICAL_RULES section 8; ADR-009 section 3; D-073; AC-037-03; suite REC02; TASK-037): the
 * restore reconciliation OP-05 of an intent from an earlier epoch against a status check of it and against
 * a repeat of it, each in a process of its own connection under barrier control, serialized by the
 * command identity. Before the reconciliation the answer is EPOCH_STALE; after it, NOT_COMMITTED; the
 * intent never commits; the report is written once.
 */
final class RestoreReconciliationRaceTest extends DatabaseTestCase
{
    /** @var list<Worker> */
    private array $workers = [];

    private int $agus = 0;

    private string $epoch = '';

    private string $earlier = '';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Probe::createTables($this->migration()) as $table) {
            $this->harnessTable($table);
        }

        TokoContoh::person('Hendra');
        $this->agus = TokoContoh::person('Agus')['id'];
        $this->epoch = TokoContoh::recoveryEpoch();
        $this->earlier = str_repeat('0c', 32);
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }

        parent::tearDown();
    }

    public function test_a_reconciliation_first_makes_the_repeat_wait_and_refuses_it_intent_abandoned(): void
    {
        [$intent, $report] = $this->reported();
        $reconciliation = $this->start(ReconcileIntentScenario::class, ['report' => $report], ['after_identity']);
        $repeat = $this->start(ProbeScenario::class, $this->repeat($intent));
        Worker::ready($reconciliation, $repeat);

        $reconciliation->run()->awaitBarrier('after_identity');
        $repeat->run()->awaitBlocked($this->observer());
        $reconciliation->release();

        $this->assertSame(['reconciled' => true, 'settled' => 1], $reconciliation->finish()->result);
        $this->assertSame(['refused' => 'INTENT_ABANDONED'], $repeat->finish()->result);
        $this->assertNeverCommitted($intent);
        $this->assertSame(['NOT_COMMITTED'], $this->outcomesOf($intent));
    }

    public function test_a_status_check_during_the_reconciliation_answers_epoch_stale_and_after_it_not_committed(): void
    {
        [$intent, $report] = $this->reported();
        $reconciliation = $this->start(ReconcileIntentScenario::class, ['report' => $report], ['after_identity']);
        $check = $this->start(ResolveScenario::class, ['checker' => $this->agus, 'intent' => $intent, 'epoch' => $this->earlier]);
        Worker::ready($reconciliation, $check);

        $reconciliation->run()->awaitBarrier('after_identity');
        $this->assertSame(['refused' => 'EPOCH_STALE'], $check->run()->finish()->result);
        $reconciliation->release();
        $this->assertSame(['reconciled' => true, 'settled' => 1], $reconciliation->finish()->result);

        $after = $this->start(ResolveScenario::class, ['checker' => $this->agus, 'intent' => $intent, 'epoch' => $this->earlier]);
        Worker::ready($after);
        $this->assertSame(['outcome' => 'NOT_COMMITTED', 'commandId' => null], $after->run()->finish()->result);
        $this->assertNeverCommitted($intent);
        $this->assertSame(1, $this->observer()->table('stale_intent_reports')->where('source_intent_id', $intent)->count());
    }

    public function test_a_repeat_first_is_refused_epoch_stale_and_the_waiting_reconciliation_then_commits(): void
    {
        [$intent, $report] = $this->reported();
        $repeat = $this->start(ProbeScenario::class, $this->repeat($intent), ['after_identity']);
        $reconciliation = $this->start(ReconcileIntentScenario::class, ['report' => $report]);
        Worker::ready($repeat, $reconciliation);

        $repeat->run()->awaitBarrier('after_identity');
        $reconciliation->run()->awaitBlocked($this->observer());
        $repeat->release();

        $this->assertSame(['refused' => 'EPOCH_STALE'], $repeat->finish()->result);
        $this->assertSame(['reconciled' => true, 'settled' => 1], $reconciliation->finish()->result);
        $this->assertNeverCommitted($intent);
        $this->assertSame(1, $this->observer()->table('stale_intent_reports')->where('source_intent_id', $intent)->count());
    }

    /**
     * Agus's probe of an earlier epoch whose request reached the server, PENDING, and was reported by a
     * refused status check, as the restored data hold such a request: its intent and its report's key.
     *
     * @return array{string, int}
     */
    private function reported(): array
    {
        $intent = (string) Str::uuid();
        app(RequestReceipts::class)->record($this->agus, Probe::OPERATION, $intent, (string) Str::uuid(), $this->earlier, now()->format('Y-m-d H:i:s.uP'));
        DB::transaction(fn (): bool => app(StaleIntentReports::class)->reportIfAbsent(Probe::OPERATION, $intent, $this->agus, $this->earlier));
        $report = $this->observer()->table('stale_intent_reports')->where('source_intent_id', $intent)->value('id');
        $this->assertIsInt($report);
        $this->assertNotSame($this->epoch, $this->earlier);

        return [$intent, $report];
    }

    /**
     * A repeat of the original request, with its earlier epoch.
     *
     * @return array<string, mixed>
     */
    private function repeat(string $intent): array
    {
        return ['actor' => $this->agus, 'intent' => $intent, 'key' => (string) Str::uuid(), 'epoch' => $this->earlier, 'note' => 'Terlambat'];
    }

    private function assertNeverCommitted(string $intent): void
    {
        $this->assertSame(['FENCE'], $this->observer()->table('command_identities')->where('source_intent_id', $intent)->pluck('kind')->all());
        $this->assertSame(0, $this->observer()->table('harness_probe_facts')->count());
        $this->assertSame(1, $this->observer()->table('intent_reconciliations')->count());
    }

    /**
     * @return list<mixed>
     */
    private function outcomesOf(string $intent): array
    {
        return $this->observer()->table('command_request_receipts')->where('source_intent_id', $intent)->orderBy('id')->pluck('outcome')->all();
    }

    /**
     * @param  class-string<Scenario>  $scenario
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $barriers
     */
    private function start(string $scenario, array $arguments, array $barriers = []): Worker
    {
        $worker = Worker::start($scenario, $arguments, $barriers);
        $this->workers[] = $worker;

        return $worker;
    }
}
