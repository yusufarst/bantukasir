<?php

namespace Tests\Concurrency;

use App\Domain\Commands\RequestReceipts;
use Illuminate\Support\Str;
use Tests\Harness\Commands\Probe;
use Tests\Harness\Commands\ProbeScenario;
use Tests\Harness\Commands\ResolveScenario;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\Scenario;
use Tests\Harness\Race\Worker;

/**
 * The races of the command platform (AC-008-02, AC-008-04, AC-008-05;
 * TECHNICAL_RULES section 8: RACE-01, RACE-02, RACE-27, RACE-33; ADR-007
 * sections 3 and 4; ADR-009 section 3; suite CMD01; TASK-008), each with two
 * processes of their own connections under barrier control, serialized by
 * the command identity: a repeat waits for the original and returns it or
 * conflicts; a fence and a late original exclude each other; a check that
 * finds the original still running answers IN_PROGRESS once the lock
 * timeout passes and settles nothing; a repeat waiting beyond the lock
 * timeout answers SERVICE_BUSY; and two concurrent refusals of an earlier
 * epoch write one stale-intent report.
 */
final class CommandRaceTest extends DatabaseTestCase
{
    /** @var list<Worker> */
    private array $workers = [];

    /** @var array<string, int> */
    private array $people = [];

    private string $epoch = '';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Probe::createTables($this->migration()) as $table) {
            $this->harnessTable($table);
        }

        foreach (['Hendra', 'Agus'] as $person) {
            $this->people[$person] = TokoContoh::person($person)['id'];
        }

        $this->epoch = TokoContoh::recoveryEpoch();
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }

        parent::tearDown();
    }

    public function test_race_01_the_same_key_twice_runs_once_and_both_get_the_original_or_a_conflict(): void
    {
        foreach (['Pertama', 'Lain'] as $secondNote) {
            $intent = (string) Str::uuid();
            $key = (string) Str::uuid();
            $first = $this->start(ProbeScenario::class, $this->command($intent, $key, 'Pertama'), ['after_identity']);
            $second = $this->start(ProbeScenario::class, $this->command($intent, $key, $secondNote));
            Worker::ready($first, $second);

            $first->run()->awaitBarrier('after_identity');
            $second->run()->awaitBlocked($this->observer());
            $first->release();

            $original = $first->finish()->result;
            $this->assertSame(['outcome' => 'COMMITTED', 'repeated' => false], array_intersect_key($original ?? [], ['outcome' => 0, 'repeated' => 0]));
            $expected = $secondNote === 'Pertama' ? ['outcome' => 'COMMITTED', 'repeated' => true, 'commandId' => $original['commandId'] ?? null] : ['refused' => 'IDEMPOTENCY_CONFLICT'];
            $this->assertSame($expected, $second->finish()->result);
            $this->assertSame(1, $this->observer()->table('harness_probe_facts')->where('note', 'Pertama')->whereIn('command_id', $this->observer()->table('command_identities')->where('source_intent_id', $intent)->pluck('id'))->count());
        }

        $this->assertSame(0, $this->observer()->table('harness_probe_facts')->where('note', 'Lain')->count());
    }

    public function test_race_02_another_key_of_the_same_intent_runs_once(): void
    {
        foreach (['Pertama', 'Lain'] as $secondNote) {
            $intent = (string) Str::uuid();
            $first = $this->start(ProbeScenario::class, $this->command($intent, (string) Str::uuid(), 'Pertama'), ['after_identity']);
            $second = $this->start(ProbeScenario::class, $this->command($intent, (string) Str::uuid(), $secondNote));
            Worker::ready($first, $second);

            $first->run()->awaitBarrier('after_identity');
            $second->run()->awaitBlocked($this->observer());
            $first->release();

            $original = $first->finish()->result ?? [];
            $expected = $secondNote === 'Pertama' ? ['outcome' => 'COMMITTED', 'repeated' => true, 'commandId' => $original['commandId'] ?? null] : ['refused' => 'IDEMPOTENCY_CONFLICT'];
            $this->assertSame($expected, $second->finish()->result);
            $this->assertSame(1, $this->observer()->table('command_identities')->where('source_intent_id', $intent)->count());
        }

        $this->assertSame(2, $this->observer()->table('harness_probe_facts')->count());
    }

    public function test_race_27_a_fence_first_refuses_the_late_original_and_an_original_first_is_reported_committed(): void
    {
        // Fence first: the original waits for it and is refused before any fact.
        $intent = (string) Str::uuid();
        $check = $this->start(ResolveScenario::class, $this->checkBy('Agus', $intent), ['after_identity']);
        $original = $this->start(ProbeScenario::class, $this->command($intent, (string) Str::uuid(), 'Terlambat'));
        Worker::ready($check, $original);

        $check->run()->awaitBarrier('after_identity');
        $original->run()->awaitBlocked($this->observer());
        $check->release();

        $this->assertSame(['outcome' => 'NOT_COMMITTED', 'commandId' => null], $check->finish()->result);
        $this->assertSame(['refused' => 'INTENT_ABANDONED'], $original->finish()->result);
        $this->assertSame(0, $this->observer()->table('harness_probe_facts')->count());

        // Original first: the check waits for it and reports it.
        $intent = (string) Str::uuid();
        $original = $this->start(ProbeScenario::class, $this->command($intent, (string) Str::uuid(), 'Asli'), ['after_identity']);
        $check = $this->start(ResolveScenario::class, $this->checkBy('Agus', $intent));
        Worker::ready($original, $check);

        $original->run()->awaitBarrier('after_identity');
        $check->run()->awaitBlocked($this->observer());
        $original->release();

        $committed = $original->finish()->result ?? [];
        $this->assertSame(['outcome' => 'COMMITTED', 'commandId' => $committed['commandId'] ?? null], $check->finish()->result);
        $this->assertSame(1, $this->observer()->table('harness_probe_facts')->count());
    }

    public function test_race_33_the_owners_check_against_the_users_repeat_settles_the_users_receipt(): void
    {
        foreach (['fence first', 'repeat first'] as $order) {
            $intent = (string) Str::uuid();
            $key = (string) Str::uuid();
            // The user's first request left its receipt PENDING (its process ended).
            $receipt = app(RequestReceipts::class)->record($this->people['Agus'], Probe::OPERATION, $intent, $key, $this->epoch, now()->format('Y-m-d H:i:s.uP'));

            $owner = $this->start(ResolveScenario::class, $this->checkBy('Hendra', $intent), $order === 'fence first' ? ['after_identity'] : []);
            $repeat = $this->start(ProbeScenario::class, $this->command($intent, $key, 'Ulang'), $order === 'fence first' ? [] : ['after_identity']);
            Worker::ready($owner, $repeat);
            [$first, $second] = $order === 'fence first' ? [$owner, $repeat] : [$repeat, $owner];

            $first->run()->awaitBarrier('after_identity');
            $second->run()->awaitBlocked($this->observer());
            $first->release();

            if ($order === 'fence first') {
                $this->assertSame(['outcome' => 'NOT_COMMITTED', 'commandId' => null], $owner->finish()->result);
                $this->assertSame(['refused' => 'INTENT_ABANDONED'], $repeat->finish()->result);
                $this->assertSame(['outcome' => 'NOT_COMMITTED', 'settled_by_user_id' => $this->people['Hendra']], (array) $this->observer()->table('command_request_receipts')->where('id', $receipt)->first(['outcome', 'settled_by_user_id']));
            } else {
                $committed = $repeat->finish()->result ?? [];
                $this->assertSame(['outcome' => 'COMMITTED', 'commandId' => $committed['commandId'] ?? null], $owner->finish()->result);
                $this->assertSame(['outcome' => 'COMMITTED', 'settled_by_user_id' => $this->people['Hendra']], (array) $this->observer()->table('command_request_receipts')->where('id', $receipt)->first(['outcome', 'settled_by_user_id']));
            }

        }
    }

    public function test_a_check_of_a_running_original_answers_in_progress_and_settles_nothing(): void
    {
        $intent = (string) Str::uuid();
        $key = (string) Str::uuid();
        $receipt = app(RequestReceipts::class)->record($this->people['Agus'], Probe::OPERATION, $intent, $key, $this->epoch, now()->format('Y-m-d H:i:s.uP'));
        $original = $this->start(ProbeScenario::class, $this->command($intent, $key, 'Lama'), ['after_identity']);
        $check = $this->start(ResolveScenario::class, $this->checkBy('Hendra', $intent));
        Worker::ready($original, $check);

        $original->run()->awaitBarrier('after_identity');
        $check->run();

        // The check waits for the identity up to the lock timeout (5 s).
        $this->assertSame(['outcome' => 'IN_PROGRESS', 'commandId' => null], $check->finish()->result);
        $this->assertSame('PENDING', $this->observer()->table('command_request_receipts')->where('id', $receipt)->value('outcome'));
        $this->assertSame(0, $this->observer()->table('command_identities')->where('kind', 'FENCE')->count());

        $original->release();
        $this->assertSame(false, ($original->finish()->result ?? [])['repeated'] ?? null);
    }

    public function test_the_check_reads_again_under_its_fence_and_rolls_it_back_for_an_earlier_epoch_or_another_users_intent(): void
    {
        // Review findings R-02, R-09 and R-16: a receipt that commits between
        // the check's first read and its fence's second read decides.
        $other = TokoContoh::person('Dewi')['id'];

        foreach (['earlier epoch' => [$this->people['Agus'], str_repeat('0b', 32)], 'another user' => [$other, null]] as $case => [$actor, $epoch]) {
            $intent = (string) Str::uuid();
            $check = $this->start(ResolveScenario::class, $this->checkBy('Agus', $intent), ['after_identity']);
            Worker::ready($check);

            $check->run()->awaitBarrier('after_identity');
            app(RequestReceipts::class)->record($actor, Probe::OPERATION, $intent, (string) Str::uuid(), $epoch ?? $this->epoch, now()->format('Y-m-d H:i:s.uP'));
            $check->release();

            $this->assertSame(['refused' => $epoch === null ? 'NOT_FOUND' : 'EPOCH_STALE'], $check->finish()->result, $case);
            $this->assertSame(0, $this->observer()->table('command_identities')->where('source_intent_id', $intent)->count(), $case);
            $this->assertSame($epoch === null ? 0 : 1, $this->observer()->table('stale_intent_reports')->where('source_intent_id', $intent)->count(), $case);
            $this->assertSame([$epoch === null ? 'PENDING' : 'HELD'], $this->observer()->table('command_request_receipts')->where('source_intent_id', $intent)->pluck('outcome')->all(), $case);
        }
    }

    public function test_a_repeat_waiting_beyond_the_lock_timeout_answers_service_busy(): void
    {
        $intent = (string) Str::uuid();
        $key = (string) Str::uuid();
        $original = $this->start(ProbeScenario::class, $this->command($intent, $key, 'Lama'), ['after_identity']);
        $repeat = $this->start(ProbeScenario::class, $this->command($intent, $key, 'Lama'));
        Worker::ready($original, $repeat);

        $original->run()->awaitBarrier('after_identity');
        $repeat->run();

        $this->assertSame(['refused' => 'SERVICE_BUSY'], $repeat->finish()->result);
        $original->release();
        $this->assertSame('COMMITTED', ($original->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(1, $this->observer()->table('harness_probe_facts')->count());
    }

    public function test_two_concurrent_refusals_of_an_earlier_epoch_write_one_report(): void
    {
        $intent = (string) Str::uuid();
        $earlier = str_repeat('0a', 32);
        $first = $this->start(ProbeScenario::class, [...$this->command($intent, (string) Str::uuid(), 'Lama'), 'epoch' => $earlier], ['own_write:report']);
        $second = $this->start(ProbeScenario::class, [...$this->command($intent, (string) Str::uuid(), 'Lama'), 'epoch' => $earlier]);
        Worker::ready($first, $second);

        $first->run()->awaitBarrier('own_write:report');
        $second->run()->awaitBlocked($this->observer());
        $first->release();

        $this->assertSame(['refused' => 'EPOCH_STALE'], $first->finish()->result);
        $this->assertSame(['refused' => 'EPOCH_STALE'], $second->finish()->result);
        $this->assertSame(1, $this->observer()->table('stale_intent_reports')->where('source_intent_id', $intent)->count());
        $this->assertSame(0, $this->observer()->table('command_identities')->where('operation', '<>', 'user.create')->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function command(string $intent, string $key, string $note): array
    {
        return ['actor' => $this->people['Agus'], 'intent' => $intent, 'key' => $key, 'epoch' => $this->epoch, 'note' => $note];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkBy(string $person, string $intent): array
    {
        return ['checker' => $this->people[$person], 'intent' => $intent, 'epoch' => $this->epoch];
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
